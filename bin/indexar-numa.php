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
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }
}

require_once APP_PATH . '/helpers/utils.php';
require_once APP_PATH . '/services/NumaConfiguration.php';
require_once APP_PATH . '/models/Database.php';
require_once APP_PATH . '/models/NumaConsumoGlobal.php';
require_once APP_PATH . '/models/ArticuloBlog.php';
require_once APP_PATH . '/services/NumaEmbeddingProvider.php';
require_once APP_PATH . '/services/NumaKnowledge.php';
require_once APP_PATH . '/services/GeminiEmbeddingProvider.php';

$lockPath = sys_get_temp_dir() . '/benehom-indexar-numa.lock';
$lockHandle = fopen($lockPath, 'c');
if ($lockHandle === false) {
    fwrite(STDERR, "No se pudo crear el lock de indexacion de Numa.\n");
    exit(1);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Ya hay una indexacion de Numa en curso.\n");
    fclose($lockHandle);
    exit(1);
}

try {
    NumaConfiguration::assertIndexing();
    $knowledgeDirectory = BASE_PATH . '/knowledge/numa';
    $markdownDocuments = bh_numa_validate_knowledge_directory($knowledgeDirectory);
    $articles = ArticuloBlog::publicadosParaRag();
    $dimensions = bh_env_int('NUMA_EMBEDDING_DIMENSIONS', 768);
    $embeddingProvider = new NumaMeteredEmbeddingProvider(
        NumaEmbeddingProviderFactory::fromEnvironment(),
        NumaConsumoGlobal::forEmbedding(Database::getConnection())
    );
    $signature = $embeddingProvider->signature();
    $indexer = new NumaKnowledgeIndexer(
        Database::getConnection(),
        $embeddingProvider,
        new NumaKnowledgeFragmenter(maxContentChars: bh_env_int('NUMA_MAX_RAG_CHUNK_CHARS', 900)),
        $dimensions,
        $signature
    );

    $summary = $indexer->indexCorpus(
        $knowledgeDirectory,
        $articles
    );

    fwrite(STDOUT, "Indexacion de Numa completada.\n");
    fwrite(STDOUT, 'Firma embeddings: ' . $signature->value() . "\n");
    fwrite(STDOUT, 'Documentos Markdown leidos: ' . count($markdownDocuments) . "\n");
    fwrite(STDOUT, 'Articulos de blog leidos: ' . count($articles) . "\n");
    fwrite(STDOUT, 'Fuentes totales: ' . $summary->documents . "\n");
    fwrite(STDOUT, 'Fragmentos: ' . $summary->fragments . "\n");
    fwrite(STDOUT, 'Creados: ' . $summary->created . "\n");
    fwrite(STDOUT, 'Actualizados: ' . $summary->updated . "\n");
    fwrite(STDOUT, 'Sin cambios: ' . $summary->unchanged . "\n");
    fwrite(STDOUT, 'Obsoletos eliminados: ' . $summary->deleted . "\n");
    fwrite(STDOUT, 'Embeddings generados: ' . $summary->embeddingsGenerated . "\n");
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'No se pudo indexar el conocimiento de Numa: ' . $exception->getMessage() . "\n");
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(1);
}

/**
 * @return array<int, string>
 */
function bh_numa_validate_knowledge_directory(string $directory): array
{
    $expectedDocuments = [
        'introduccion.md',
        'dashboard.md',
        'movimientos.md',
        'gastos.md',
        'ahorro.md',
        'metas.md',
        'proyecciones.md',
        'cuenta.md',
        'preguntas-frecuentes.md',
    ];

    if (!is_dir($directory) || !is_readable($directory)) {
        throw new RuntimeException('Directorio de conocimiento de Numa no legible.');
    }

    $paths = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.md') ?: [];
    $documents = array_map('basename', $paths);
    sort($documents, SORT_STRING);
    $expected = $expectedDocuments;
    sort($expected, SORT_STRING);

    if ($documents !== $expected) {
        throw new RuntimeException('Deben existir exactamente los nueve documentos obligatorios de conocimiento de Numa.');
    }

    foreach ($expectedDocuments as $document) {
        $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $document;
        if (!is_readable($path) || trim((string) file_get_contents($path)) === '') {
            throw new RuntimeException('Documento obligatorio de conocimiento de Numa no legible o vacio: ' . $document);
        }
    }

    return $expectedDocuments;
}
