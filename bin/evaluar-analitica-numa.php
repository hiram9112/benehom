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

if (!in_array('--real', $argv, true)) {
    fwrite(STDERR, "Evaluacion real no iniciada. Confirma el coste externo con --real.\n");
    exit(1);
}

if (bh_env_bool('CI', false) || strtolower((string) bh_env_value('APP_ENV', 'local')) === 'testing') {
    fwrite(STDERR, "La evaluacion analitica real esta bloqueada en CI y testing.\n");
    exit(1);
}

if (strtolower((string) bh_env_value('NUMA_PROVIDER', 'gemini')) !== 'gemini') {
    fwrite(STDERR, "La evaluacion analitica requiere NUMA_PROVIDER=gemini.\n");
    exit(1);
}

if (trim((string) bh_env_value('NUMA_API_KEY', '')) === '') {
    fwrite(STDERR, "Falta NUMA_API_KEY en el entorno local.\n");
    exit(1);
}

$toolName = NumaFinancialDataToolContract::NAME;
$toolDefinition = (new NumaFinancialToolRegistry())->get($toolName)->externalContract();
$cases = [
    [
        'id' => 'diferencia',
        'message' => '¿Cuál es la diferencia entre mis ingresos y gastos?',
        'result' => bh_numa_analitica_monthly_result('2026-07', '1200.00', '800.00'),
        'assert' => static fn (string $response): ?string => bh_numa_analitica_assert_number($response, 400.0, 0.01),
    ],
    [
        'id' => 'porcentaje',
        'message' => '¿Qué porcentaje de mis ingresos representan mis gastos?',
        'result' => bh_numa_analitica_monthly_result('2026-07', '1200.00', '800.00'),
        'assert' => static fn (string $response): ?string => bh_numa_analitica_assert_number($response, 66.67, 0.2),
    ],
    [
        'id' => 'media',
        'message' => '¿Cuál fue la media mensual de mis gastos?',
        'result' => [
            'tool' => NumaFinancialDataToolContract::NAME,
            'meses' => [
                bh_numa_analitica_expense_month('2026-01', '200.00'),
                bh_numa_analitica_expense_month('2026-02', '400.00'),
                bh_numa_analitica_expense_month('2026-03', '900.00'),
            ],
        ],
        'assert' => static fn (string $response): ?string => bh_numa_analitica_assert_number($response, 500.0, 0.01),
    ],
    [
        'id' => 'ranking',
        'message' => 'Ordena estas categorías de mayor a menor gasto.',
        'result' => bh_numa_analitica_partial_categories_result(),
        'assert' => static fn (string $response): ?string => bh_numa_analitica_assert_order(
            $response,
            [
                ['vivienda', 'alquiler', 'hipoteca'],
                ['alimentacion', 'compra basica'],
                ['transporte'],
            ],
        ),
    ],
    [
        'id' => 'cobertura-parcial',
        'message' => '¿Cuál fue mi gasto total y qué categorías gastaron más?',
        'result' => bh_numa_analitica_partial_categories_result(),
        'assert' => static fn (string $response): ?string => bh_numa_analitica_assert_partial_coverage($response),
    ],
    [
        'id' => 'consulta-combinada',
        'message' => 'En una sola frase, indica mi gasto de electricidad de julio y la comparación que propone la guía de BeneHom.',
        'intent' => 'consulta_combinada',
        'knowledge_context' => [
            'type' => 'knowledge_fragments',
            'items' => [[
                'title' => 'Revisar los suministros',
                'section' => 'Comparación mensual',
                'url' => '/conocimiento/gastos',
                'content' => 'Para interpretar un gasto de suministros, BeneHom propone compararlo con meses anteriores antes de extraer conclusiones.',
            ]],
        ],
        'result' => [
            'tool' => NumaFinancialDataToolContract::NAME,
            'meses' => [[
                'mes' => '2026-07',
                'gastos' => bh_numa_analitica_expense_branch('80.00'),
            ]],
        ],
        'assert' => static fn (string $response): ?string => bh_numa_analitica_assert_combined($response),
    ],
];

$consumption = new class implements NumaProviderConsumptionInterface, NumaInteractionBudgetInterface {
    public int $calls = 0;
    public int $inputTokens = 0;
    public int $outputTokens = 0;
    private bool $transientRetryUsed = false;

    public function iniciarLlamada(): void
    {
        ++$this->calls;
    }

    public function registrarTokens(NumaTokenUsage $usage): void
    {
        $this->inputTokens += $usage->inputTokens() ?? 0;
        $this->outputTokens += $usage->outputTokens() ?? 0;
    }

    public function timeoutForCall(int $configuredTimeoutSeconds): int
    {
        return max(1, $configuredTimeoutSeconds);
    }

    public function allowTransientRetry(): bool
    {
        if ($this->transientRetryUsed) {
            return false;
        }

        $this->transientRetryUsed = true;

        return true;
    }

    public function resetInteraction(): void
    {
        $this->transientRetryUsed = false;
    }
};
$failures = [];
$inconclusive = [];
$passes = 0;
$lastCaseIndex = array_key_last($cases);

foreach ($cases as $caseIndex => $case) {
    $consumption->resetInteraction();
    $finalMessage = null;
    try {
        $provider = NumaSystemInstructionProvider::fromBasePrompt(new NumaProviderBoundary(
            GeminiNumaProvider::fromEnvironment(consumption: $consumption),
        ));
        $baseContext = bh_numa_analitica_base_context(
            $toolDefinition,
            (string) ($case['intent'] ?? 'datos_usuario'),
        );
        if (isset($case['knowledge_context'])) {
            array_splice($baseContext, 1, 0, [$case['knowledge_context']]);
        }
        $toolCallResponse = $provider->respond(new NumaRequest(
            $case['message'],
            '',
            $baseContext,
            [$toolName],
            [],
            null,
            NumaRequest::FUNCTION_CALLING_ANY,
            NumaConfiguration::maxOutputTokens(),
        ));
        $toolRequests = $toolCallResponse->toolRequests();
        if (count($toolRequests) !== 1 || $toolRequests[0]->name() !== $toolName || $toolRequests[0]->id() === null) {
            throw new RuntimeException('Gemini no solicito exactamente la tool financiera esperada.');
        }

        $toolRequest = $toolRequests[0];
        $finalContext = [...$baseContext, [
            'type' => 'financial_tool_results',
            'items' => [[
                'call_id' => $toolRequest->id(),
                'name' => $toolName,
                'arguments' => $toolRequest->arguments(),
                'result' => $case['result'],
            ]],
        ]];
        $response = $provider->respond(new NumaRequest(
            $case['message'],
            '',
            $finalContext,
            [$toolName],
            [],
            null,
            NumaRequest::FUNCTION_CALLING_AUTO,
            NumaConfiguration::maxOutputTokens(),
        ));
        if ($response->toolRequests() !== []) {
            throw new RuntimeException('Gemini solicito otra tool en lugar de responder al resultado financiero.');
        }

        $finalMessage = trim($response->message());
        if ($finalMessage === '') {
            throw new RuntimeException('Gemini devolvio una respuesta final vacia.');
        }

        $error = ($case['assert'])($finalMessage);
        if ($error !== null) {
            throw new RuntimeException($error);
        }

        fwrite(STDOUT, "PASS {$case['id']}.\n");
        ++$passes;
    } catch (NumaProviderException $exception) {
        $error = $exception->providerError();
        if (in_array($error->type(), [
            NumaProviderError::TRANSIENT,
            NumaProviderError::UNAVAILABLE,
            NumaProviderError::TIMEOUT,
            NumaProviderError::RATE_LIMIT,
        ], true)) {
            $inconclusive[] = $case['id'] . ': ' . $error->safeCode();
            fwrite(STDOUT, "INCONCLUSIVE {$case['id']}: {$error->safeCode()}.\n");
            continue;
        }

        if (in_array($error->type(), [
            NumaProviderError::QUOTA,
            NumaProviderError::AUTHENTICATION,
            NumaProviderError::CONFIGURATION,
        ], true)) {
            fwrite(STDERR, "ABORT {$case['id']}: {$error->safeCode()}.\n");
            exit(2);
        }

        $failure = $case['id'] . ': ' . $error->safeCode();
        if ($finalMessage !== null) {
            $failure .= ' Respuesta: ' . $finalMessage;
        }
        $failures[] = $failure;
        fwrite(STDOUT, "FAIL {$case['id']}.\n");
    } catch (Throwable $exception) {
        $failure = $case['id'] . ': ' . $exception->getMessage();
        if ($finalMessage !== null) {
            $failure .= ' Respuesta: ' . $finalMessage;
        }
        $failures[] = $failure;
        fwrite(STDOUT, "FAIL {$case['id']}.\n");
    } finally {
        if ($caseIndex !== $lastCaseIndex) {
            usleep(1_000_000);
        }
    }
}

fwrite(
    STDOUT,
    'Llamadas a Gemini: ' . $consumption->calls
        . '; tokens informados: entrada=' . $consumption->inputTokens
        . ', salida=' . $consumption->outputTokens . ".\n",
);

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

if ($inconclusive !== []) {
    fwrite(STDERR, implode("\n", $inconclusive) . "\n");
    fwrite(STDOUT, "Evaluacion analitica inconclusa: {$passes} PASS, " . count($inconclusive) . " INCONCLUSIVE.\n");
    exit(2);
}

fwrite(STDOUT, "Evaluacion analitica completada: {$passes} PASS.\n");

/** @param array<string, mixed> $toolDefinition @return array<int, array<string, mixed>> */
function bh_numa_analitica_base_context(array $toolDefinition, string $intent): array
{
    return [[
        'type' => 'numa_final_response',
        'classification' => [
            'intent' => $intent,
            'allowed' => true,
            'reason' => $intent === 'consulta_combinada' ? 'combined' : 'user_data',
        ],
        'server_date' => '2026-08-12',
        'business_timezone' => 'Europe/Madrid',
        'dashboard_month' => '2026-07',
        'rules' => [
            'Usa solo el contexto de BeneHom y los resultados de tools entregados por el backend.',
            'No inventes datos si falta informacion.',
            'Devuelve una respuesta breve en español para el usuario final.',
            'Redacta los resultados de tools en lenguaje natural: no muestres nombres de tools, claves de campos ni estructuras JSON.',
            'Puedes calcular comparaciones, diferencias, porcentajes, medias, rankings y tendencias solo a partir de las hojas financieras entregadas. Antes de llamar total a un importe o concluir sobre el universo completo, comprueba cobertura: una rama parcial representa solo sus elementos declarados por los contadores de cobertura.',
        ],
    ], [
        'type' => 'available_financial_tools',
        'items' => [$toolDefinition],
    ]];
}

/** @return array<string, mixed> */
function bh_numa_analitica_monthly_result(string $month, string $income, string $expense): array
{
    return [
        'tool' => NumaFinancialDataToolContract::NAME,
        'meses' => [[
            'mes' => $month,
            'ingresos' => [
                'importe' => $income,
                'cobertura' => ['completa' => true, 'areas_consultadas' => 1, 'areas_totales' => 1],
                'areas' => [[
                    'area' => 'trabajo',
                    'importe' => $income,
                    'cobertura' => ['completa' => true, 'categorias_consultadas' => 1, 'categorias_totales' => 1],
                    'categorias' => [['categoria' => 'nomina', 'importe' => $income]],
                ]],
            ],
            'gastos' => bh_numa_analitica_expense_branch($expense),
        ]],
    ];
}

/** @return array<string, mixed> */
function bh_numa_analitica_expense_month(string $month, string $expense): array
{
    return ['mes' => $month, 'gastos' => bh_numa_analitica_expense_branch($expense)];
}

/** @return array<string, mixed> */
function bh_numa_analitica_expense_branch(string $expense): array
{
    return [
        'importe' => $expense,
        'cobertura' => ['completa' => true, 'tipos_consultados' => 1, 'tipos_totales' => 1],
        'tipos' => [[
            'tipo' => 'esencial',
            'importe' => $expense,
            'cobertura' => ['completa' => true, 'areas_consultadas' => 1, 'areas_totales' => 1],
            'areas' => [[
                'area' => 'suministros',
                'importe' => $expense,
                'cobertura' => ['completa' => true, 'categorias_consultadas' => 1, 'categorias_totales' => 1],
                'categorias' => [['categoria' => 'electricidad', 'importe' => $expense]],
            ]],
        ]],
    ];
}

/** @return array<string, mixed> */
function bh_numa_analitica_partial_categories_result(): array
{
    return [
        'tool' => NumaFinancialDataToolContract::NAME,
        'meses' => [[
            'mes' => '2026-07',
            'gastos' => [
                'importe' => '1250.00',
                'cobertura' => ['completa' => false, 'tipos_consultados' => 1, 'tipos_totales' => 2],
                'tipos' => [[
                    'tipo' => 'esencial',
                    'importe' => '1250.00',
                    'cobertura' => ['completa' => false, 'areas_consultadas' => 3, 'areas_totales' => 8],
                    'areas' => [
                        bh_numa_analitica_partial_area('transporte_necesario', 'transporte_publico', '150.00', 5),
                        bh_numa_analitica_partial_area('vivienda', 'alquiler_hipoteca', '700.00', 6),
                        bh_numa_analitica_partial_area('alimentacion_hogar', 'compra_basica', '400.00', 4),
                    ],
                ]],
            ],
        ]],
    ];
}

/** @return array<string, mixed> */
function bh_numa_analitica_partial_area(string $area, string $category, string $amount, int $totalCategories): array
{
    return [
        'area' => $area,
        'importe' => $amount,
        'cobertura' => ['completa' => false, 'categorias_consultadas' => 1, 'categorias_totales' => $totalCategories],
        'categorias' => [['categoria' => $category, 'importe' => $amount]],
    ];
}

function bh_numa_analitica_assert_number(string $response, float $expected, float $tolerance): ?string
{
    preg_match_all('/(?<!\d)(?:\d{1,3}(?:[.\s]\d{3})+|\d+)(?:[,.]\d+)?/u', $response, $matches);
    foreach ($matches[0] as $literal) {
        $normalised = preg_replace('/(?<=\d)[.\s](?=\d{3}(?:\D|$))/', '', $literal) ?? $literal;
        $value = (float) str_replace(',', '.', $normalised);
        if (abs($value - $expected) <= $tolerance) {
            return null;
        }
    }

    return 'No aparece el resultado numerico esperado (' . str_replace('.', ',', (string) $expected) . ').';
}

/** @param list<list<string>> $expected */
function bh_numa_analitica_assert_order(string $response, array $expected): ?string
{
    $normalised = bh_numa_analitica_normalise($response);
    $previousPosition = -1;
    foreach ($expected as $alternatives) {
        $positions = [];
        foreach ($alternatives as $term) {
            $position = strpos($normalised, bh_numa_analitica_normalise($term));
            if ($position !== false) {
                $positions[] = $position;
            }
        }

        $position = $positions === [] ? false : min($positions);
        if ($position === false || $position <= $previousPosition) {
            return 'No aparece el ranking esperado: Vivienda > Alimentación > Transporte.';
        }

        $previousPosition = $position;
    }

    return null;
}

function bh_numa_analitica_assert_partial_coverage(string $response): ?string
{
    $normalised = bh_numa_analitica_normalise($response);
    $partialIndicators = ['parcial', 'consultad', 'no incluye', 'no abarca', 'no representa el total'];
    $hasPartialIndicator = false;
    foreach ($partialIndicators as $indicator) {
        if (str_contains($normalised, $indicator)) {
            $hasPartialIndicator = true;
            break;
        }
    }

    if (!$hasPartialIndicator) {
        return 'La respuesta no reconoce que los datos financieros son parciales.';
    }

    $sentences = preg_split('/[.!?\n]+/u', $normalised) ?: [];
    foreach ($sentences as $sentence) {
        if (preg_match('/1[.\s]?250(?:[,.]0{1,2})?/u', $sentence) !== 1 || !str_contains($sentence, 'total')) {
            continue;
        }

        if (!str_contains($sentence, 'parcial')
            && !str_contains($sentence, 'consultad')
            && !str_contains($sentence, 'solo')
            && !str_contains($sentence, 'unicamente')
            && !str_contains($sentence, 'no representa el total')
        ) {
            return 'La respuesta presenta 1.250 como un total sin acotarlo a la cobertura parcial.';
        }
    }

    return null;
}

function bh_numa_analitica_assert_combined(string $response): ?string
{
    $numberError = bh_numa_analitica_assert_number($response, 80.0, 0.01);
    if ($numberError !== null) {
        return $numberError;
    }

    if (!str_contains(bh_numa_analitica_normalise($response), 'compar')) {
        return 'La respuesta no integra la pauta documental de comparación mensual.';
    }

    return null;
}

function bh_numa_analitica_normalise(string $value): string
{
    $value = mb_strtolower($value, 'UTF-8');

    return strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
}
