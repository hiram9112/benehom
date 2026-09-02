<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/NumaMinimalLogger.php';

final class NumaMinimalLoggerTest extends TestCase
{
    public function testRegistraSoloMetadatosTecnicosPermitidos(): void
    {
        $entries = [];
        $logger = new \NumaMinimalLogger(static function (string $entry) use (&$entries): void {
            $entries[] = $entry;
        });

        $logger->record('classification', hrtime(true), 2, 340, false, 'NUMA_PROVIDER_TIMEOUT');

        self::assertCount(1, $entries);
        self::assertStringStartsWith('numa ', $entries[0]);
        $payload = json_decode(substr($entries[0], 5), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([
            'correlation_id',
            'stage',
            'duration_ms',
            'calls',
            'tokens',
            'outcome',
            'error_code',
        ], array_keys($payload));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $payload['correlation_id']);
        self::assertSame('classification', $payload['stage']);
        self::assertSame(2, $payload['calls']);
        self::assertSame(340, $payload['tokens']);
        self::assertSame('error', $payload['outcome']);
        self::assertSame('NUMA_PROVIDER_TIMEOUT', $payload['error_code']);
    }

    public function testRegistraSoloToolsEjecutadasConNombreTecnicoSeguro(): void
    {
        $entries = [];
        $logger = new \NumaMinimalLogger(
            static function (string $entry) use (&$entries): void {
                $entries[] = $entry;
            },
            detailedDiagnostics: true,
        );

        $logger->recordExecutedTool('obtener_estadisticas_movimientos');
        $logger->recordExecutedTool('contenido privado');
        $logger->record('response', hrtime(true), 3, null, true);

        $payload = json_decode(substr($entries[0], 5), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(['obtener_estadisticas_movimientos'], $payload['tools']);
        self::assertStringNotContainsString('contenido privado', $entries[0]);
    }

    public function testNoPermiteQueUnCodigoTecnicoNoSeguroLlegueAlLog(): void
    {
        $entries = [];
        $logger = new \NumaMinimalLogger(static function (string $entry) use (&$entries): void {
            $entries[] = $entry;
        });

        $logger->record('response', hrtime(true), 1, null, false, 'clave=secret-message');

        self::assertStringNotContainsString('secret-message', $entries[0]);
        $payload = json_decode(substr($entries[0], 5), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('NUMA_INTERNAL_ERROR', $payload['error_code']);
        self::assertNull($payload['tokens']);
    }

    public function testDistingueEtapasRealesYNormalizaUnaEtapaNoPermitida(): void
    {
        $entries = [];
        $logger = new \NumaMinimalLogger(static function (string $entry) use (&$entries): void {
            $entries[] = $entry;
        });

        $logger->record('knowledge', hrtime(true), 1, 120, true);
        $logger->record('contenido privado', hrtime(true), 0, 0, false, 'NUMA_NOT_AVAILABLE');

        $success = json_decode(substr($entries[0], 5), true, 512, JSON_THROW_ON_ERROR);
        $failure = json_decode(substr($entries[1], 5), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('knowledge', $success['stage']);
        self::assertSame('success', $success['outcome']);
        self::assertSame('request', $failure['stage']);
        self::assertSame('NUMA_NOT_AVAILABLE', $failure['error_code']);
        self::assertStringNotContainsString('contenido privado', $entries[1]);
    }
}
