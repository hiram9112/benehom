<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/FakeNumaProvider.php';

final class FakeNumaProviderTest extends TestCase
{
    public function testSimulaRespuestaValida(): void
    {
        $provider = \FakeNumaProvider::validResponse('Respuesta fake.');
        $request = new \NumaRequest('¿Cómo añado un movimiento?');

        $response = $provider->respond($request);

        self::assertSame('Respuesta fake.', $response->message());
        self::assertSame($request, $provider->lastRequest());
        self::assertSame([$request], $provider->requests());
    }

    public function testSimulaRespuestaEstructurada(): void
    {
        $provider = \FakeNumaProvider::structuredResponse(['intent' => 'producto', 'allowed' => true]);

        $response = $provider->respond(new \NumaRequest('¿Qué es ahorro posible?'));

        self::assertSame(['intent' => 'producto', 'allowed' => true], $response->structuredData());
        self::assertNull($response->toolRequest());
    }

    public function testSimulaSolicitudDeTool(): void
    {
        $provider = \FakeNumaProvider::toolRequest('obtener_resumen_financiero', ['periodo' => 'mes_actual']);

        $response = $provider->respond(new \NumaRequest('¿Cuánto gasté este mes?'));
        $toolRequest = $response->toolRequest();

        self::assertInstanceOf(\NumaToolRequest::class, $toolRequest);
        self::assertSame('obtener_resumen_financiero', $toolRequest->name());
        self::assertSame(['periodo' => 'mes_actual'], $toolRequest->arguments());
    }

    public function testSimulaTimeout(): void
    {
        $this->assertProviderError(
            \FakeNumaProvider::timeout(),
            \NumaProviderError::TIMEOUT,
            'NUMA_PROVIDER_TIMEOUT',
            true
        );
    }

    public function testSimulaErrorDeAutenticacion(): void
    {
        $this->assertProviderError(
            \FakeNumaProvider::authenticationError(),
            \NumaProviderError::AUTHENTICATION,
            'NUMA_PROVIDER_AUTH_ERROR'
        );
    }

    public function testSimulaCuotaAgotada(): void
    {
        $this->assertProviderError(
            \FakeNumaProvider::quotaExceeded(),
            \NumaProviderError::QUOTA,
            'NUMA_PROVIDER_QUOTA_EXCEEDED'
        );
    }

    public function testSimulaRateLimit(): void
    {
        $this->assertProviderError(
            \FakeNumaProvider::rateLimited(),
            \NumaProviderError::RATE_LIMIT,
            'NUMA_PROVIDER_RATE_LIMITED'
        );
    }

    public function testSimulaErrorTemporal(): void
    {
        $this->assertProviderError(
            \FakeNumaProvider::transientError(),
            \NumaProviderError::TRANSIENT,
            'NUMA_PROVIDER_UNAVAILABLE',
            true
        );
    }

    public function testSimulaJsonInvalido(): void
    {
        $this->assertProviderError(
            \FakeNumaProvider::invalidJson(),
            \NumaProviderError::INVALID_RESPONSE,
            'NUMA_PROVIDER_INVALID_RESPONSE'
        );
    }

    public function testSimulaRespuestaVacia(): void
    {
        $this->assertProviderError(
            \FakeNumaProvider::emptyResponse(),
            \NumaProviderError::INVALID_RESPONSE,
            'NUMA_PROVIDER_INVALID_RESPONSE'
        );
    }

    public function testSimulaUsoDeTokens(): void
    {
        $response = \FakeNumaProvider::withTokenUsage(120, 35)->respond(new \NumaRequest('Pregunta'));

        self::assertTrue($response->tokenUsage()->hasReliableTokens());
        self::assertSame(120, $response->tokenUsage()->inputTokens());
        self::assertSame(35, $response->tokenUsage()->outputTokens());
        self::assertSame(155, $response->tokenUsage()->totalTokens());
    }

    public function testSimulaAusenciaDeInformacionDeTokens(): void
    {
        $response = \FakeNumaProvider::withoutTokenUsage()->respond(new \NumaRequest('Pregunta'));

        self::assertFalse($response->tokenUsage()->hasReliableTokens());
        self::assertNull($response->tokenUsage()->inputTokens());
        self::assertNull($response->tokenUsage()->outputTokens());
    }

    private function assertProviderError(
        \NumaProviderInterface $provider,
        string $expectedType,
        string $expectedSafeCode,
        bool $expectedRetryable = false,
    ): void {
        try {
            $provider->respond(new \NumaRequest('Pregunta'));
            self::fail('Se esperaba una excepcion de proveedor.');
        } catch (\NumaProviderException $exception) {
            self::assertSame($expectedSafeCode, $exception->getMessage());
            self::assertSame($expectedType, $exception->providerError()->type());
            self::assertSame($expectedRetryable, $exception->providerError()->retryable());
        }
    }
}
