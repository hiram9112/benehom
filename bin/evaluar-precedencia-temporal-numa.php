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
require_once APP_PATH . '/services/NumaClassification.php';

if (!in_array('--real', $argv, true)) {
    fwrite(STDERR, "Evaluacion real no iniciada. Confirma el coste externo con --real.\n");
    exit(1);
}

if (bh_env_bool('CI', false) || strtolower((string) bh_env_value('APP_ENV', 'local')) === 'testing') {
    fwrite(STDERR, "La evaluacion temporal real esta bloqueada en CI y testing.\n");
    exit(1);
}

if (trim((string) bh_env_value('NUMA_API_KEY', '')) === '') {
    fwrite(STDERR, "Falta NUMA_API_KEY en el entorno local.\n");
    exit(1);
}

$serverDate = '2026-09-12';
$timezone = 'Europe/Madrid';
$cases = [
    [
        'id' => 'explicit-current-message',
        'message' => '¿Cuánto gasté en enero de 2026?',
        'dashboard_month' => '2026-06',
        'history' => [],
        'expected_months' => ['2026-01'],
    ],
    [
        'id' => 'conversation-before-dashboard',
        'message' => '¿Y el mes anterior?',
        'dashboard_month' => '2026-06',
        'history' => [[
            'role' => 'user',
            'message' => 'Consulta febrero.',
        ], [
            'role' => 'assistant',
            'message' => 'He consultado febrero.',
            'periods' => [['mes_inicio' => '2026-02', 'mes_fin' => '2026-02']],
        ]],
        'expected_months' => ['2026-01'],
    ],
    [
        'id' => 'dashboard-this-month',
        'message' => '¿Cuánto gasté este mes?',
        'dashboard_month' => '2026-06',
        'history' => [],
        'expected_months' => ['2026-06'],
    ],
    [
        'id' => 'real-calendar-month',
        'message' => '¿Cuánto gasté el mes actual del calendario?',
        'dashboard_month' => '2026-06',
        'history' => [],
        'expected_months' => ['2026-09'],
    ],
    [
        'id' => 'current-year',
        'message' => '¿Cuánto gasté durante el año actual?',
        'dashboard_month' => '2026-06',
        'history' => [],
        'expected_months' => [
            '2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06',
            '2026-07', '2026-08', '2026-09',
        ],
    ],
    [
        'id' => 'last-three-months',
        'message' => '¿Cuánto gasté en los últimos 3 meses?',
        'dashboard_month' => '2026-06',
        'history' => [],
        'expected_months' => ['2026-06', '2026-07', '2026-08'],
    ],
    [
        'id' => 'ambiguous-conversation-anchor',
        'message' => '¿Y el mes anterior?',
        'dashboard_month' => '2026-06',
        'history' => [[
            'role' => 'user',
            'message' => 'Compara enero y marzo.',
        ], [
            'role' => 'assistant',
            'message' => 'He comparado enero y marzo.',
            'periods' => [
                ['mes_inicio' => '2026-01', 'mes_fin' => '2026-01'],
                ['mes_inicio' => '2026-03', 'mes_fin' => '2026-03'],
            ],
        ]],
        'expected_clarification' => true,
    ],
    [
        'id' => 'current-message-relative-anchor',
        'message' => 'Compara enero de 2026 con el mes anterior.',
        'dashboard_month' => '2026-06',
        'history' => [],
        'expected_months' => ['2025-12', '2026-01'],
    ],
];

$consumption = new class implements NumaProviderConsumptionInterface {
    public function iniciarLlamada(): void
    {
    }

    public function registrarTokens(NumaTokenUsage $usage): void
    {
    }
};
$definition = (new NumaFinancialToolRegistry())
    ->get(NumaFinancialDataToolContract::NAME)
    ->functionDeclaration();
$failures = [];

foreach ($cases as $case) {
    $temporalContext = [
        'type' => 'authoritative_temporal_context',
        'server_date' => $serverDate,
        'business_timezone' => $timezone,
        'dashboard_month' => $case['dashboard_month'],
    ];

    try {
        $provider = NumaSystemInstructionProvider::fromBasePrompt(new NumaProviderBoundary(
            GeminiNumaProvider::fromEnvironment(consumption: $consumption),
        ));
        $decision = (new NumaProviderFunctionalDecider($provider))->decide(
            $case['message'],
            $case['history'],
            $temporalContext,
        );
        $expectsClarification = ($case['expected_clarification'] ?? false) === true;
        if ($decision->needsClarification() !== $expectsClarification) {
            throw new RuntimeException(
                'needs_clarification esperado=' . ($expectsClarification ? 'true' : 'false')
                    . ' recibido=' . ($decision->needsClarification() ? 'true' : 'false'),
            );
        }

        if ($expectsClarification) {
            fwrite(STDOUT, "PASS {$case['id']}: aclaracion previa a Function Calling.\n");
            continue;
        }

        if ($decision->classification()->intent() !== NumaClassificationIntent::DATOS_USUARIO) {
            throw new RuntimeException('clasificacion financiera no obtenida');
        }

        $response = $provider->respond(new NumaRequest(
            $case['message'],
            '',
            [[
                'type' => 'numa_final_response',
                'classification' => $decision->classification()->toStructuredData(),
                'server_date' => $serverDate,
                'business_timezone' => $timezone,
                'dashboard_month' => $case['dashboard_month'],
                'rules' => [
                    'Envía a la tool únicamente períodos mensuales concretos con mes_inicio y mes_fin en formato YYYY-MM.',
                ],
            ], [
                'type' => 'available_financial_tools',
                'items' => [$definition],
            ]],
            [NumaFinancialDataToolContract::NAME],
            $case['history'],
            null,
            NumaRequest::FUNCTION_CALLING_ANY,
            NumaConfiguration::maxOutputTokens(),
        ));

        $months = [];
        foreach ($response->toolRequests() as $toolRequest) {
            if ($toolRequest->name() !== NumaFinancialDataToolContract::NAME) {
                throw new RuntimeException('tool financiera inesperada');
            }

            $arguments = (new NumaFinancialDataToolContract())->validateArguments($toolRequest->arguments());
            $months = [...$months, ...bh_numa_temporal_evaluation_months($arguments['periodos'])];
        }

        $months = array_values(array_unique($months));
        $expectedMonths = $case['expected_months'];
        $missingMonths = array_values(array_diff($expectedMonths, $months));
        $unexpectedMonths = array_values(array_diff($months, $expectedMonths));
        if ($missingMonths !== [] || $unexpectedMonths !== []) {
            throw new RuntimeException(
                'meses faltantes=' . implode(',', $missingMonths)
                    . ' meses sobrantes=' . implode(',', $unexpectedMonths),
            );
        }

        fwrite(STDOUT, "PASS {$case['id']}: " . implode(', ', $months) . ".\n");
    } catch (Throwable $exception) {
        $failures[] = $case['id'] . ': ' . $exception->getMessage();
        fwrite(STDOUT, "FAIL {$case['id']}.\n");
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, 'Evaluacion temporal completada: ' . count($cases) . " casos correctos.\n");

/**
 * @param list<array{mes_inicio:string,mes_fin:string}> $periods
 * @return list<string>
 */
function bh_numa_temporal_evaluation_months(array $periods): array
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

    return $months;
}
