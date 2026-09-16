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
require_once APP_PATH . '/services/GeminiNumaProvider.php';
require_once APP_PATH . '/services/GeminiEmbeddingProvider.php';
require_once APP_PATH . '/services/NumaService.php';

if (!in_array('--real', $argv, true)) {
    fwrite(STDERR, "Smoke E2E real no iniciado. Confirma el coste externo con --real.\n");
    exit(1);
}

if (bh_env_bool('CI', false) || strtolower((string) bh_env_value('APP_ENV', 'local')) === 'testing') {
    fwrite(STDERR, "El smoke E2E real esta bloqueado en CI y testing.\n");
    exit(1);
}

if (strtolower((string) bh_env_value('NUMA_PROVIDER', 'gemini')) !== 'gemini'
    || strtolower((string) bh_env_value('NUMA_EMBEDDING_PROVIDER', 'gemini')) !== 'gemini'
) {
    fwrite(STDERR, "El smoke E2E requiere Gemini para generacion y embeddings.\n");
    exit(1);
}

if (trim((string) bh_env_value('NUMA_API_KEY', '')) === '') {
    fwrite(STDERR, "Falta NUMA_API_KEY en el entorno local.\n");
    exit(1);
}

$evaluationDatabase = trim((string) bh_env_value('NUMA_RAG_EVALUATION_DB_NAME', ''));
$applicationDatabase = trim((string) bh_env_value('DB_NAME', ''));
if ($evaluationDatabase === ''
    || $evaluationDatabase === $applicationDatabase
    || preg_match('/^[A-Za-z0-9_]+_(?:test|sandbox)$/', $evaluationDatabase) !== 1
) {
    fwrite(STDERR, "Configura NUMA_RAG_EVALUATION_DB_NAME con una base aislada terminada en _test o _sandbox.\n");
    exit(1);
}

$lockPath = sys_get_temp_dir() . '/benehom-evaluar-e2e-numa.lock';
$lockHandle = fopen($lockPath, 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Ya hay un smoke E2E de Numa en curso o no se pudo crear su lock.\n");
    is_resource($lockHandle) && fclose($lockHandle);
    exit(1);
}

$connection = null;
$userIds = [];
$previousEnabled = $_ENV['NUMA_ENABLED'] ?? null;
$previousExemptUsers = $_ENV['NUMA_LIMIT_EXEMPT_USER_IDS'] ?? null;

try {
    $connection = new PDO(
        'mysql:host=' . bh_env_value('NUMA_RAG_EVALUATION_DB_HOST', bh_env_value('DB_HOST', 'localhost'))
            . ';port=' . bh_env_value('NUMA_RAG_EVALUATION_DB_PORT', bh_env_value('DB_PORT', '3306'))
            . ';dbname=' . $evaluationDatabase
            . ';charset=utf8mb4',
        (string) bh_env_value('NUMA_RAG_EVALUATION_DB_USER', ''),
        (string) bh_env_value('NUMA_RAG_EVALUATION_DB_PASS', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    if ($connection->query('SELECT DATABASE()')?->fetchColumn() !== $evaluationDatabase) {
        throw new RuntimeException('La conexion no apunta a la base aislada solicitada.');
    }

    bh_numa_e2e_assert_tables($connection);
    bh_numa_e2e_remove_stale_users($connection);
    $userIds = bh_numa_e2e_seed($connection);
    $_ENV['NUMA_ENABLED'] = 'true';
    $_ENV['NUMA_LIMIT_EXEMPT_USER_IDS'] = implode(',', $userIds);

    $cases = bh_numa_e2e_cases();
    $failures = [];
    foreach ($cases as $case) {
        $trace = ['provider_calls' => 0, 'tool_requests' => [], 'tool_results' => [], 'executed_tools' => []];
        $finalMessage = null;

        try {
            $service = bh_numa_e2e_service($connection, $trace);
            $result = $service->answer(
                $userIds[0],
                $case['message'],
                $case['history'] ?? [],
                $case['dashboard_month'] ?? null,
            );
            $finalMessage = $result->toArray()['message'];

            bh_numa_e2e_assert_case($case, $result, $trace);
            fwrite(
                STDOUT,
                'PASS ' . $case['id']
                    . ': llamadas=' . $trace['provider_calls']
                    . ', tools=' . count($trace['tool_results'])
                    . ', ids=' . implode(',', array_keys($trace['tool_results']))
                    . ".\n",
            );
        } catch (Throwable $exception) {
            $failure = $case['id'] . ': ' . $exception->getMessage();
            if (is_string($finalMessage) && $finalMessage !== '') {
                $failure .= ' Respuesta: ' . $finalMessage;
            }
            $failures[] = $failure;
            fwrite(STDOUT, "FAIL {$case['id']}.\n");
        }
    }

    if ($failures !== []) {
        throw new RuntimeException(implode("\n", $failures));
    }

    fwrite(STDOUT, 'Smoke E2E completado: ' . count($cases) . " casos correctos con NumaService, Gemini y BD reales.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    bh_numa_e2e_exit_code(1);
} finally {
    if ($connection instanceof PDO && $userIds !== []) {
        try {
            bh_numa_e2e_remove_users($connection, $userIds);
        } catch (Throwable $cleanupError) {
            fwrite(STDERR, 'No se pudieron eliminar los usuarios sinteticos: ' . $cleanupError->getMessage() . "\n");
            bh_numa_e2e_exit_code(1);
        }
    }

    if ($previousEnabled === null) {
        unset($_ENV['NUMA_ENABLED']);
    } else {
        $_ENV['NUMA_ENABLED'] = $previousEnabled;
    }
    if ($previousExemptUsers === null) {
        unset($_ENV['NUMA_LIMIT_EXEMPT_USER_IDS']);
    } else {
        $_ENV['NUMA_LIMIT_EXEMPT_USER_IDS'] = $previousExemptUsers;
    }

    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}

exit(bh_numa_e2e_exit_code());

function bh_numa_e2e_exit_code(?int $value = null): int
{
    static $code = 0;
    if ($value !== null) {
        $code = max($code, $value);
    }

    return $code;
}

/** @return list<array<string, mixed>> */
function bh_numa_e2e_cases(): array
{
    $electricity = ['ambito' => 'gastos', 'tipo' => 'esencial', 'area' => 'suministros', 'categoria' => 'electricidad'];

    return [
        [
            'id' => 'dashboard-sin-periodo',
            'message' => '¿Cuánto gasté en electricidad?',
            'dashboard_month' => '2026-07',
            'months' => ['2026-07'],
            'selectors' => [$electricity],
            'facts' => [['month' => '2026-07', 'category' => 'electricidad', 'amount' => '80.00']],
            'numbers' => [80.0],
        ],
        [
            'id' => 'continuacion-conversacional',
            'message' => '¿Y en julio de 2026?',
            'dashboard_month' => '2026-05',
            'history' => [[
                'role' => 'user',
                'message' => '¿Cuánto gasté en electricidad en junio de 2026?',
            ], [
                'role' => 'assistant',
                'message' => 'En junio de 2026 gastaste 60 euros en electricidad.',
                'periods' => [['mes_inicio' => '2026-06', 'mes_fin' => '2026-06']],
            ]],
            'months' => ['2026-07'],
            'selectors' => [$electricity],
            'facts' => [['month' => '2026-07', 'category' => 'electricidad', 'amount' => '80.00']],
            'numbers' => [80.0],
        ],
        [
            'id' => 'promedio-desde-bd',
            'message' => '¿Cuál fue mi media mensual de electricidad entre enero y marzo de 2026?',
            'dashboard_month' => '2026-07',
            'months' => ['2026-01', '2026-02', '2026-03'],
            'selectors' => [$electricity],
            'facts' => [
                ['month' => '2026-01', 'category' => 'electricidad', 'amount' => '30.00'],
                ['month' => '2026-02', 'category' => 'electricidad', 'amount' => '40.00'],
                ['month' => '2026-03', 'category' => 'electricidad', 'amount' => '50.00'],
            ],
            'numbers' => [40.0],
        ],
        [
            'id' => 'combinada-rag-datos',
            'message' => '¿Cuánto gasté en electricidad en julio y qué recomienda BeneHom para controlar los gastos esenciales?',
            'dashboard_month' => '2026-07',
            'months' => ['2026-07'],
            'selectors' => [$electricity],
            'facts' => [['month' => '2026-07', 'category' => 'electricidad', 'amount' => '80.00']],
            'numbers' => [80.0],
            'sources' => true,
            'terms' => ['esencial', 'seguimiento', 'separar'],
        ],
    ];
}

/** @param array<string, mixed> $trace */
function bh_numa_e2e_service(PDO $connection, array &$trace): NumaService
{
    $providerFactory = static function (?NumaProviderConsumptionInterface $consumption) use ($connection, &$trace): NumaProviderInterface {
        if ($consumption === null) {
            throw new RuntimeException('NumaService no entrego el presupuesto de consumo al proveedor.');
        }

        $provider = NumaProviderFactory::fromEnvironment(consumption: new NumaProviderConsumptionChain(
            $consumption,
            NumaConsumoGlobal::forLlm($connection),
        ));
        $observe = static function (NumaRequest $request, NumaResponse $response) use (&$trace): void {
            ++$trace['provider_calls'];
            foreach ($request->context() as $contextItem) {
                if (($contextItem['type'] ?? null) !== 'financial_tool_results') {
                    continue;
                }
                foreach ($contextItem['items'] ?? [] as $item) {
                    if (is_array($item) && is_string($item['call_id'] ?? null) && $item['call_id'] !== '') {
                        $trace['tool_results'][$item['call_id']] = $item;
                    }
                }
            }
            foreach ($response->toolRequests() as $toolRequest) {
                $trace['tool_requests'][] = [
                    'id' => $toolRequest->id(),
                    'name' => $toolRequest->name(),
                    'arguments' => $toolRequest->arguments(),
                ];
            }
        };

        return new class($provider, $observe) implements NumaProviderInterface {
            public function __construct(
                private readonly NumaProviderInterface $provider,
                private readonly Closure $observe,
            ) {
            }

            public function respond(NumaRequest $request): NumaResponse
            {
                $response = $this->provider->respond($request);
                ($this->observe)($request, $response);

                return $response;
            }
        };
    };

    $knowledgeSearch = static function (
        NumaClassification $classification,
        string $message,
        ?NumaProviderConsumptionInterface $consumption,
    ) use ($connection): array {
        if ($consumption === null) {
            throw new RuntimeException('NumaService no entrego el presupuesto de consumo al buscador RAG.');
        }

        $embeddingProvider = new NumaMeteredEmbeddingProvider(
            NumaEmbeddingProviderFactory::fromEnvironment(),
            new NumaProviderConsumptionChain($consumption, NumaConsumoGlobal::forEmbedding($connection)),
        );
        $searcher = new NumaKnowledgeSearcher(
            $connection,
            $embeddingProvider,
            bh_env_int('NUMA_EMBEDDING_DIMENSIONS', 768),
            bh_env_int('NUMA_MAX_RAG_RESULTS', NumaKnowledgeSearcher::MAX_RESULTS),
            (float) bh_env_value('NUMA_RAG_MIN_SIMILARITY', (string) NumaKnowledgeSearcher::DEFAULT_MIN_SIMILARITY),
            $embeddingProvider->signature(),
        );

        return $searcher->search($classification->knowledgeQuery() ?? $message);
    };

    return new NumaService(
        new NumaUso($connection),
        new NumaLocalScopeClassifier(),
        $providerFactory,
        $knowledgeSearch,
        static fn (): NumaFinancialToolRegistryInterface => new NumaFinancialToolRegistry(new NumaFinancialToolExecutor($connection)),
        static fn (): NumaGlobalAvailabilityInterface => new NumaGlobalAvailability(NumaConsumoGlobal::forLlm($connection)),
        new NumaPeriodResolver(new DateTimeImmutable('2026-08-12', new DateTimeZone('Europe/Madrid'))),
        static function (string $tool) use (&$trace): void {
            $trace['executed_tools'][] = $tool;
        },
    );
}

/** @param array<string, mixed> $case @param array<string, mixed> $trace */
function bh_numa_e2e_assert_case(array $case, NumaServiceResult $result, array $trace): void
{
    if (bh_numa_e2e_months($result->periods()) !== $case['months']) {
        throw new RuntimeException('Los períodos resueltos no coinciden con la expectativa.');
    }
    if ($trace['tool_results'] === [] || $trace['executed_tools'] === []) {
        throw new RuntimeException('No se ejecuto la tool financiera real.');
    }
    if (array_unique($trace['executed_tools']) !== [NumaFinancialDataToolContract::NAME]) {
        throw new RuntimeException('Se ejecuto una tool distinta de la canonica.');
    }

    $selectors = [];
    foreach ($trace['tool_results'] as $execution) {
        $selectors = [...$selectors, ...($execution['arguments']['selectores'] ?? [])];
        if (str_contains(json_encode($execution['result'], JSON_THROW_ON_ERROR), '9999.00')) {
            throw new RuntimeException('La tool devolvio datos del segundo usuario sintetico.');
        }
    }
    if (bh_numa_e2e_normalise_arrays($selectors) !== bh_numa_e2e_normalise_arrays($case['selectors'])) {
        throw new RuntimeException('Los selectores canonicos ejecutados no coinciden con la expectativa.');
    }

    foreach ($case['facts'] as $fact) {
        if (!bh_numa_e2e_has_fact($trace['tool_results'], $fact['month'], $fact['category'], $fact['amount'])) {
            throw new RuntimeException('La tool no devolvio el hecho sintetico esperado.');
        }
    }

    $message = (string) $result->toArray()['message'];
    foreach ($case['numbers'] as $number) {
        if (!bh_numa_e2e_has_number($message, $number)) {
            throw new RuntimeException('La respuesta final no contiene el resultado numerico esperado.');
        }
    }
    if (($case['sources'] ?? false) === true && $result->sources() === []) {
        throw new RuntimeException('La consulta combinada no recupero fuentes RAG reales.');
    }
    $normalisedMessage = mb_strtolower($message, 'UTF-8');
    if (isset($case['terms']) && array_filter(
        $case['terms'],
        static fn (string $term): bool => str_contains($normalisedMessage, $term),
    ) === []) {
        throw new RuntimeException('La respuesta final no integra la pauta documental esperada.');
    }
}

/** @param array<string, array<string, mixed>> $toolResults */
function bh_numa_e2e_has_fact(array $toolResults, string $month, string $category, string $amount): bool
{
    foreach ($toolResults as $execution) {
        foreach ($execution['result']['meses'] ?? [] as $monthResult) {
            if (($monthResult['mes'] ?? null) !== $month) {
                continue;
            }
            foreach ($monthResult['gastos']['tipos'] ?? [] as $type) {
                foreach ($type['areas'] ?? [] as $area) {
                    foreach ($area['categorias'] ?? [] as $categoryResult) {
                        if (($categoryResult['categoria'] ?? null) === $category
                            && ($categoryResult['importe'] ?? null) === $amount
                        ) {
                            return true;
                        }
                    }
                }
            }
        }
    }

    return false;
}

function bh_numa_e2e_has_number(string $response, float $expected): bool
{
    preg_match_all('/(?<!\d)(?:\d{1,3}(?:[.\s]\d{3})+|\d+)(?:[,.]\d+)?/u', $response, $matches);
    foreach ($matches[0] as $literal) {
        $normalised = preg_replace('/(?<=\d)[.\s](?=\d{3}(?:\D|$))/', '', $literal) ?? $literal;
        if (abs((float) str_replace(',', '.', $normalised) - $expected) <= 0.01) {
            return true;
        }
    }

    return false;
}

/** @param list<array<string, mixed>> $items @return list<array<string, mixed>> */
function bh_numa_e2e_normalise_arrays(array $items): array
{
    $items = array_values(array_unique($items, SORT_REGULAR));
    usort($items, static fn (array $left, array $right): int => strcmp(
        json_encode($left, JSON_THROW_ON_ERROR),
        json_encode($right, JSON_THROW_ON_ERROR),
    ));

    return $items;
}

/** @param list<array{mes_inicio:string,mes_fin:string}> $periods @return list<string> */
function bh_numa_e2e_months(array $periods): array
{
    $months = [];
    foreach ($periods as $period) {
        $month = new DateTimeImmutable($period['mes_inicio'] . '-01', new DateTimeZone('Europe/Madrid'));
        $last = new DateTimeImmutable($period['mes_fin'] . '-01', new DateTimeZone('Europe/Madrid'));
        while ($month <= $last) {
            $months[] = $month->format('Y-m');
            $month = $month->modify('first day of next month');
        }
    }

    return array_values(array_unique($months));
}

function bh_numa_e2e_assert_tables(PDO $connection): void
{
    $required = ['usuarios', 'gastos', 'numa_uso', 'numa_reservas', 'numa_uso_proveedor', 'numa_conocimiento'];
    $statement = $connection->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name',
    );
    foreach ($required as $table) {
        $statement->execute([':table_name' => $table]);
        if ((int) $statement->fetchColumn() !== 1) {
            throw new RuntimeException('Falta la tabla requerida en la base aislada: ' . $table);
        }
    }
    if ((int) $connection->query('SELECT COUNT(*) FROM numa_conocimiento')?->fetchColumn() === 0) {
        throw new RuntimeException('La base aislada no contiene un indice RAG. Ejecuta primero la evaluacion/indexacion real de RAG.');
    }
}

/** @return list<int> */
function bh_numa_e2e_seed(PDO $connection): array
{
    $suffix = bin2hex(random_bytes(8));
    $insertUser = $connection->prepare(
        'INSERT INTO usuarios (usuario, email, password) VALUES (:usuario, :email, :password)',
    );
    $userIds = [];
    try {
        foreach (['primary', 'isolation'] as $role) {
            $insertUser->execute([
                ':usuario' => 'Numa E2E ' . $role,
                ':email' => 'numa-e2e-' . $role . '-' . $suffix . '@example.test',
                ':password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            ]);
            $userIds[] = (int) $connection->lastInsertId();
        }

        $insertExpense = $connection->prepare(
            'INSERT INTO gastos (usuario_id, tipo, categoria, cantidad, fecha) VALUES (:usuario_id, :tipo, :categoria, :cantidad, :fecha)',
        );
        foreach ([
            ['esencial', 'electricidad', '30.00', '2026-01-03'],
            ['esencial', 'electricidad', '40.00', '2026-02-03'],
            ['esencial', 'electricidad', '50.00', '2026-03-03'],
            ['esencial', 'electricidad', '60.00', '2026-06-03'],
            ['esencial', 'electricidad', '80.00', '2026-07-03'],
            ['flexible', 'comida_domicilio', '25.00', '2026-07-04'],
        ] as [$type, $category, $amount, $date]) {
            $insertExpense->execute([
                ':usuario_id' => $userIds[0],
                ':tipo' => $type,
                ':categoria' => $category,
                ':cantidad' => $amount,
                ':fecha' => $date,
            ]);
        }
        $insertExpense->execute([
            ':usuario_id' => $userIds[1],
            ':tipo' => 'esencial',
            ':categoria' => 'electricidad',
            ':cantidad' => '9999.00',
            ':fecha' => '2026-07-03',
        ]);
    } catch (Throwable $exception) {
        if ($userIds !== []) {
            bh_numa_e2e_remove_users($connection, $userIds);
        }
        throw $exception;
    }

    return $userIds;
}

function bh_numa_e2e_remove_stale_users(PDO $connection): void
{
    $statement = $connection->query("SELECT id FROM usuarios WHERE email LIKE 'numa-e2e-%@example.test'");
    $ids = $statement === false ? [] : array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    if ($ids !== []) {
        bh_numa_e2e_remove_users($connection, $ids);
    }
}

/** @param list<int> $userIds */
function bh_numa_e2e_remove_users(PDO $connection, array $userIds): void
{
    if ($userIds === []) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $connection->beginTransaction();
    try {
        $connection->prepare("DELETE FROM gastos WHERE usuario_id IN ($placeholders)")->execute($userIds);
        $connection->prepare("DELETE FROM ingresos WHERE usuario_id IN ($placeholders)")->execute($userIds);
        $connection->prepare("DELETE FROM usuarios WHERE id IN ($placeholders)")->execute($userIds);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}
