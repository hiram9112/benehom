#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));
defined('APP_PATH') || define('APP_PATH', BASE_PATH . '/app');
defined('CONFIG_PATH') || define('CONFIG_PATH', BASE_PATH . '/config');
defined('BASE_URL') || define('BASE_URL', '/');

date_default_timezone_set('Europe/Madrid');

$envPath = BASE_PATH . '/.env';
if (is_file($envPath) && is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $processValue = getenv($key);
        if ($processValue !== false) {
            $_ENV[$key] = (string) $processValue;
        } elseif (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }
}

require_once APP_PATH . '/helpers/utils.php';
require_once APP_PATH . '/services/NumaConfiguration.php';
require_once APP_PATH . '/models/NumaConsumoGlobal.php';
require_once APP_PATH . '/models/ArticuloBlog.php';
require_once APP_PATH . '/services/NumaEmbeddingProvider.php';
require_once APP_PATH . '/services/GeminiEmbeddingProvider.php';
require_once APP_PATH . '/services/NumaClassification.php';
require_once APP_PATH . '/services/NumaKnowledge.php';

if (!in_array('--real', $argv, true)) {
    fwrite(STDERR, "Evaluacion real no iniciada. Confirma el coste externo con --real.\n");
    exit(1);
}

if (bh_env_bool('CI', false) || strtolower((string) bh_env_value('APP_ENV', 'local')) === 'testing') {
    fwrite(STDERR, "La evaluacion real de RAG esta bloqueada en CI y testing.\n");
    exit(1);
}

$evaluationDatabase = trim((string) bh_env_value('NUMA_RAG_EVALUATION_DB_NAME', ''));
$applicationDatabase = trim((string) bh_env_value('DB_NAME', ''));
if (
    $evaluationDatabase === ''
    || $evaluationDatabase === $applicationDatabase
    || preg_match('/^[A-Za-z0-9_]+_(?:test|sandbox)$/', $evaluationDatabase) !== 1
) {
    fwrite(STDERR, "Configura NUMA_RAG_EVALUATION_DB_NAME con una base aislada terminada en _test o _sandbox.\n");
    exit(1);
}

if (trim((string) bh_env_value('NUMA_API_KEY', '')) === '') {
    fwrite(STDERR, "Falta NUMA_API_KEY en el entorno local.\n");
    exit(1);
}

if (strtolower((string) bh_env_value('NUMA_EMBEDDING_PROVIDER', 'gemini')) !== 'gemini') {
    fwrite(STDERR, "La evaluacion real de 12.6 requiere el proveedor de embeddings Gemini.\n");
    exit(1);
}

try {
    NumaConfiguration::assertRagEvaluation();
} catch (NumaConfigurationException $exception) {
    fwrite(STDERR, "Configuracion de evaluacion RAG invalida.\n");
    exit(1);
}

$lockPath = sys_get_temp_dir() . '/benehom-evaluar-rag-numa.lock';
$lockHandle = fopen($lockPath, 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Ya hay una evaluacion RAG de Numa en curso o no se pudo crear su lock.\n");
    is_resource($lockHandle) && fclose($lockHandle);
    exit(1);
}

try {
    $connection = new PDO(
        'mysql:host=' . bh_env_value('NUMA_RAG_EVALUATION_DB_HOST', bh_env_value('DB_HOST', 'localhost'))
            . ';port=' . bh_env_value('NUMA_RAG_EVALUATION_DB_PORT', bh_env_value('DB_PORT', '3306'))
            . ';dbname=' . $evaluationDatabase
            . ';charset=utf8mb4',
        (string) bh_env_value('NUMA_RAG_EVALUATION_DB_USER', ''),
        (string) bh_env_value('NUMA_RAG_EVALUATION_DB_PASS', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    if ($connection->query('SELECT DATABASE()')?->fetchColumn() !== $evaluationDatabase) {
        throw new RuntimeException('La conexion no apunta a la base aislada solicitada.');
    }
    bh_numa_assert_evaluation_tables($connection);

    $evaluation = require BASE_PATH . '/resources/numa/evaluacion-rag.php';
    if (!is_array($evaluation) || !isset($evaluation['version'], $evaluation['cases']) || !is_array($evaluation['cases'])) {
        throw new RuntimeException('El conjunto versionado de evaluacion RAG es invalido.');
    }

    $knowledgeDirectory = BASE_PATH . '/knowledge/numa';
    $articles = ArticuloBlog::publicadosParaRag();
    $dimensions = bh_env_int('NUMA_EMBEDDING_DIMENSIONS', 768);
    $provider = new NumaMeteredEmbeddingProvider(
        NumaEmbeddingProviderFactory::fromEnvironment(),
        NumaConsumoGlobal::forEmbedding($connection)
    );
    $signature = $provider->signature();
    $fragmenter = new NumaKnowledgeFragmenter(maxContentChars: bh_env_int(
        'NUMA_MAX_RAG_CHUNK_CHARS',
        NumaKnowledgeFragmenter::MAX_CONTENT_CHARS,
    ));
    $fragments = array_merge(
        $fragmenter->fragmentDirectory($knowledgeDirectory),
        $fragmenter->fragmentArticles($articles)
    );
    $queryCalls = count(array_filter(
        $evaluation['cases'],
        static fn (array $case): bool => ($case['type'] ?? null) !== 'out_of_scope'
    ));
    $documentCalls = bh_numa_pending_document_embeddings($connection, $fragments, $signature, $dimensions);
    bh_numa_assert_evaluation_capacity(
        NumaConsumoGlobal::forEmbedding($connection),
        $documentCalls + $queryCalls
    );
    $indexer = new NumaKnowledgeIndexer($connection, $provider, $fragmenter, $dimensions, $signature);
    $summary = $indexer->indexCorpus($knowledgeDirectory, $articles);
    $searcher = new NumaKnowledgeSearcher(
        $connection,
        $provider,
        $dimensions,
        NumaKnowledgeSearcher::MAX_RESULTS,
        -1.0,
        $signature
    );
    $classifier = new NumaLocalScopeClassifier();
    $caseResults = [];
    $calibrationPositiveScores = [];
    $calibrationNegativeScores = [];
    $validationPositiveScores = [];
    $validationNegativeScores = [];
    $structuralFailures = [];

    foreach ($evaluation['cases'] as $case) {
        if (!is_array($case) || !isset($case['id'], $case['type'], $case['question'])) {
            throw new RuntimeException('Caso de evaluacion RAG invalido.');
        }

        $id = (string) $case['id'];
        $split = (string) ($case['split'] ?? '');
        $type = (string) $case['type'];
        $question = (string) $case['question'];
        $rejection = $classifier->classify($question);

        if ($type === 'out_of_scope') {
            $valid = $rejection !== null
                && $rejection->classification()->intent() === ($case['expected_intent'] ?? null)
                && $rejection->classification()->reason() === ($case['expected_reason'] ?? null);

            if (!$valid) {
                $structuralFailures[] = $id . ': rechazo local inesperado';
            }

            $caseResults[] = [
                'id' => $id,
                'type' => $type,
                'status' => $valid ? 'pass' : 'fail',
                'local_rejection' => $rejection === null ? null : [
                    'intent' => $rejection->classification()->intent(),
                    'reason' => $rejection->classification()->reason(),
                ],
                'top' => [],
            ];
            continue;
        }

        if ($rejection !== null) {
            $structuralFailures[] = $id . ': la consulta documental fue rechazada localmente';
            $caseResults[] = [
                'id' => $id,
                'type' => $type,
                'status' => 'fail',
                'local_rejection' => [
                    'intent' => $rejection->classification()->intent(),
                    'reason' => $rejection->classification()->reason(),
                ],
                'top' => [],
            ];
            continue;
        }

        $knowledgeQuery = trim((string) ($case['knowledge_query'] ?? ''));
        if ($knowledgeQuery === '') {
            throw new RuntimeException('Caso documental sin knowledge_query: ' . $id);
        }

        $results = $searcher->search($knowledgeQuery);
        $top = array_slice($results, 0, 3);
        $topRows = array_map(static fn (NumaKnowledgeSearchResult $result): array => [
            'fragment_id' => $result->fragmentId(),
            'document' => $result->document(),
            'section' => $result->section(),
            'similarity' => $result->similarity(),
        ], $top);

        if ($type === 'no_result') {
            $topScore = $top[0]->similarity() ?? -1.0;
            if ($split === 'calibration') {
                $calibrationNegativeScores[$id] = $topScore;
            } else {
                $validationNegativeScores[$id] = $topScore;
            }
            $caseResults[] = [
                'id' => $id,
                'type' => $type,
                'status' => 'pending_threshold',
                'local_rejection' => null,
                'top' => $topRows,
            ];
            continue;
        }

        $requiredScores = [];
        if (isset($case['expected_top_documents'])) {
            $topDocument = $top[0]->document() ?? null;
            if ($topDocument === null || !in_array($topDocument, $case['expected_top_documents'], true)) {
                $structuralFailures[] = $id . ': no prevalece la fuente esperada';
            } else {
                $requiredScores[] = $top[0]->similarity();
            }
        }

        if (isset($case['expected_documents'])) {
            $score = bh_numa_best_expected_score(
                $top,
                static fn (NumaKnowledgeSearchResult $result): bool => in_array($result->document(), $case['expected_documents'], true)
            );
            if ($score === null) {
                $structuralFailures[] = $id . ': no hay documento esperado en top 3';
            } else {
                $requiredScores[] = $score;
            }
        }

        if (isset($case['expected_fragment_ids'])) {
            $score = bh_numa_best_expected_score(
                $top,
                static fn (NumaKnowledgeSearchResult $result): bool => in_array($result->fragmentId(), $case['expected_fragment_ids'], true)
            );
            if ($score === null) {
                $structuralFailures[] = $id . ': no hay fragmento esperado en top 3';
            } else {
                $requiredScores[] = $score;
            }
        }

        if ($requiredScores === []) {
            $structuralFailures[] = $id . ': no define una expectativa recuperable';
        } else {
            if ($split === 'calibration') {
                $calibrationPositiveScores[$id] = min($requiredScores);
            } else {
                $validationPositiveScores[$id] = min($requiredScores);
            }
        }

        $caseResults[] = [
            'id' => $id,
            'type' => $type,
            'status' => $requiredScores === [] ? 'fail' : 'pending_threshold',
            'local_rejection' => null,
            'top' => $topRows,
        ];
    }

    $calibratedThreshold = $structuralFailures === []
        ? bh_numa_calibrate_threshold($calibrationPositiveScores, $calibrationNegativeScores)
        : null;

    if ($calibratedThreshold === null && $structuralFailures === []) {
        $structuralFailures[] = 'No existe un umbral centesimal que separe positivos y no_result.';
    }

    foreach ($caseResults as &$caseResult) {
        if ($caseResult['type'] === 'out_of_scope' || $calibratedThreshold === null) {
            continue;
        }

        $id = $caseResult['id'];
        $positiveScores = array_replace($calibrationPositiveScores, $validationPositiveScores);
        $negativeScores = array_replace($calibrationNegativeScores, $validationNegativeScores);
        $caseResult['status'] = $caseResult['type'] === 'no_result'
            ? (($negativeScores[$id] ?? 1.0) < $calibratedThreshold ? 'pass' : 'fail')
            : (($positiveScores[$id] ?? -1.0) >= $calibratedThreshold ? 'pass' : 'fail');

        if ($caseResult['status'] === 'fail') {
            $structuralFailures[] = $id . ': falla con el umbral calibrado';
        }
    }
    unset($caseResult);

    $reportPath = BASE_PATH . '/resources/numa/evaluacion-rag-resultados.md';
    $report = bh_numa_evaluation_report(
        $evaluation,
        $signature,
        $summary,
        $caseResults,
        $calibrationPositiveScores,
        $calibrationNegativeScores,
        $validationPositiveScores,
        $validationNegativeScores,
        $calibratedThreshold,
        $structuralFailures
    );
    if (file_put_contents($reportPath, $report) === false) {
        throw new RuntimeException('No se pudo guardar el informe de evaluacion RAG.');
    }

    fwrite(STDOUT, "Evaluacion RAG real completada.\n");
    fwrite(STDOUT, 'Firma: ' . $signature->value() . "\n");
    fwrite(STDOUT, 'Fuentes: ' . $summary->documents . "\n");
    fwrite(STDOUT, 'Fragmentos: ' . $summary->fragments . "\n");
    fwrite(STDOUT, 'Embeddings documentales generados: ' . $summary->embeddingsGenerated . "\n");
    fwrite(STDOUT, 'Informe: ' . $reportPath . "\n");

    if ($calibratedThreshold === null || $structuralFailures !== []) {
        fwrite(STDERR, "No se pudo justificar un umbral de produccion. Revisa el informe.\n");
        exit(2);
    }

    fwrite(STDOUT, 'Umbral centesimal justificable: ' . number_format($calibratedThreshold, 2, '.', '') . "\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'No se pudo evaluar el RAG de Numa: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}

/** @param array<int, NumaKnowledgeSearchResult> $results */
function bh_numa_best_expected_score(array $results, callable $matches): ?float
{
    foreach ($results as $result) {
        if ($matches($result)) {
            return $result->similarity();
        }
    }

    return null;
}

/**
 * @param array<string, float> $positiveScores
 * @param array<string, float> $negativeScores
 */
function bh_numa_calibrate_threshold(array $positiveScores, array $negativeScores): ?float
{
    if ($positiveScores === [] || $negativeScores === []) {
        return null;
    }

    $minimumPositive = min($positiveScores);
    $maximumNegative = max($negativeScores);

    if ($minimumPositive - $maximumNegative < 0.02) {
        return null;
    }

    $threshold = round(($maximumNegative + $minimumPositive) / 2, 2);
    if ($maximumNegative < $threshold && $minimumPositive >= $threshold) {
        return $threshold;
    }

    return null;
}

function bh_numa_assert_evaluation_tables(PDO $connection): void
{
    foreach (['numa_conocimiento', 'numa_uso_proveedor'] as $table) {
        $stmt = $connection->query('SHOW TABLES LIKE ' . $connection->quote($table));
        if ($stmt === false || $stmt->fetchColumn() === false) {
            throw new RuntimeException('La base aislada no contiene la tabla requerida: ' . $table);
        }
    }
}

/**
 * @param array<int, NumaKnowledgeFragment> $fragments
 */
function bh_numa_pending_document_embeddings(
    PDO $connection,
    array $fragments,
    NumaEmbeddingSignature $signature,
    int $dimensions,
): int {
    $stmt = $connection->query('SELECT fragmento_id, hash, dimensiones, firma_embedding FROM numa_conocimiento');
    if ($stmt === false) {
        throw new RuntimeException('No se pudo comprobar el indice aislado existente.');
    }

    $existing = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[(string) $row['fragmento_id']] = $row;
    }

    $pending = 0;
    foreach ($fragments as $fragment) {
        $current = $existing[$fragment->id()] ?? null;
        if (
            !is_array($current)
            || (string) $current['hash'] !== $fragment->hash()
            || (int) $current['dimensiones'] !== $dimensions
            || (string) $current['firma_embedding'] !== $signature->value()
        ) {
            ++$pending;
        }
    }

    return $pending;
}

function bh_numa_assert_evaluation_capacity(NumaConsumoGlobal $consumption, int $requiredCalls): void
{
    $status = $consumption->estadoGlobal();
    $reservedTokens = $requiredCalls * 2048;
    $dailyRemainingCalls = $status['daily_calls_limit'] - $status['daily_calls'];
    $monthlyRemainingCalls = $status['monthly_calls_limit'] - $status['monthly_calls'];
    $dailyRemainingTokens = $status['daily_tokens_limit'] - $status['daily_tokens'];
    $monthlyRemainingTokens = $status['monthly_tokens_limit'] - $status['monthly_tokens'];

    if (
        $requiredCalls > $dailyRemainingCalls
        || $requiredCalls > $monthlyRemainingCalls
        || $reservedTokens > $dailyRemainingTokens
        || $reservedTokens > $monthlyRemainingTokens
    ) {
        throw new RuntimeException(
            'Los limites globales de la base aislada no permiten las '
            . $requiredCalls . ' llamadas y ' . $reservedTokens . ' tokens conservadores requeridos.'
        );
    }
}

/**
 * @param array<string, mixed> $evaluation
 * @param array<int, array<string, mixed>> $caseResults
 * @param array<string, float> $calibrationPositiveScores
 * @param array<string, float> $calibrationNegativeScores
 * @param array<string, float> $validationPositiveScores
 * @param array<string, float> $validationNegativeScores
 * @param array<int, string> $failures
 */
function bh_numa_evaluation_report(
    array $evaluation,
    NumaEmbeddingSignature $signature,
    NumaKnowledgeIndexSummary $summary,
    array $caseResults,
    array $calibrationPositiveScores,
    array $calibrationNegativeScores,
    array $validationPositiveScores,
    array $validationNegativeScores,
    ?float $threshold,
    array $failures,
): string {
    $lines = [
        '# Resultado de evaluacion RAG real de Numa',
        '',
        '- Fecha: ' . (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
        '- Conjunto: `' . (string) $evaluation['version'] . '`',
        '- Firma: `' . $signature->value() . '`',
        '- Fuentes: ' . $summary->documents,
        '- Fragmentos: ' . $summary->fragments,
        '- Estado: ' . ($threshold === null || $failures !== [] ? 'no calibrado' : 'calibrado'),
        '- Umbral candidato: ' . ($threshold === null ? 'ninguno' : number_format($threshold, 2, '.', '')),
        '- Menor similitud positiva de calibracion: ' . ($calibrationPositiveScores === [] ? 'n/a' : number_format(min($calibrationPositiveScores), 6, '.', '')),
        '- Mayor similitud no_result de calibracion: ' . ($calibrationNegativeScores === [] ? 'n/a' : number_format(max($calibrationNegativeScores), 6, '.', '')),
        '- Menor similitud positiva de validacion: ' . ($validationPositiveScores === [] ? 'n/a' : number_format(min($validationPositiveScores), 6, '.', '')),
        '- Mayor similitud no_result de validacion: ' . ($validationNegativeScores === [] ? 'n/a' : number_format(max($validationNegativeScores), 6, '.', '')),
        '',
        'El informe no contiene claves, vectores ni datos privados. Las consultas pertenecen al conjunto publico versionado.',
        '',
        '## Casos',
        '',
    ];

    foreach ($caseResults as $result) {
        $lines[] = '### `' . $result['id'] . '` - ' . $result['status'];
        $lines[] = '';
        if ($result['local_rejection'] !== null) {
            $lines[] = '- Rechazo local: `' . $result['local_rejection']['intent'] . '/' . $result['local_rejection']['reason'] . '`';
        } else {
            foreach ($result['top'] as $index => $row) {
                $lines[] = '- ' . ($index + 1) . '. `' . $row['fragment_id'] . '` ('
                    . number_format((float) $row['similarity'], 6, '.', '') . ')';
            }
        }
        $lines[] = '';
    }

    if ($failures !== []) {
        $lines[] = '## Incumplimientos';
        $lines[] = '';
        foreach ($failures as $failure) {
            $lines[] = '- ' . $failure;
        }
        $lines[] = '';
    }

    return implode("\n", $lines) . "\n";
}
