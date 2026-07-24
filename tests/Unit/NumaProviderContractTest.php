<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/NumaProvider.php';

final class NumaProviderContractTest extends TestCase
{
    public function testProveedorUsaContratoSinJsonEspecificoDeGemini(): void
    {
        $provider = new class implements \NumaProviderInterface {
            public ?\NumaRequest $lastRequest = null;

            public function respond(\NumaRequest $request): \NumaResponse
            {
                $this->lastRequest = $request;

                return new \NumaResponse('Respuesta breve de Numa.');
            }
        };

        $request = new \NumaRequest(
            '¿Cómo añado un movimiento?',
            'Instrucciones internas',
            [['title' => 'Movimientos', 'content' => 'Contenido controlado']],
            ['obtener_resumen_financiero']
        );

        $response = $provider->respond($request);

        self::assertSame($request, $provider->lastRequest);
        self::assertSame('¿Cómo añado un movimiento?', $request->message());
        self::assertSame('Instrucciones internas', $request->systemInstruction());
        self::assertSame([['title' => 'Movimientos', 'content' => 'Contenido controlado']], $request->context());
        self::assertSame(['obtener_resumen_financiero'], $request->availableTools());
        self::assertSame('Respuesta breve de Numa.', $response->message());
        self::assertNull($response->structuredData());
        self::assertNull($response->toolRequest());
    }

    public function testRespuestaPermiteDatosEstructuradosToolYTokens(): void
    {
        $toolRequest = new \NumaToolRequest('obtener_resumen_financiero', ['periodo' => 'mes_actual']);
        $tokenUsage = new \NumaTokenUsage(120, 35);
        $response = new \NumaResponse(
            'Necesito consultar datos agregados.',
            ['intent' => 'datos_usuario', 'allowed' => true],
            $toolRequest,
            $tokenUsage
        );

        self::assertSame('Necesito consultar datos agregados.', $response->message());
        self::assertSame(['intent' => 'datos_usuario', 'allowed' => true], $response->structuredData());
        self::assertSame($toolRequest, $response->toolRequest());
        self::assertSame('obtener_resumen_financiero', $toolRequest->name());
        self::assertSame(['periodo' => 'mes_actual'], $toolRequest->arguments());
        self::assertSame($tokenUsage, $response->tokenUsage());
        self::assertTrue($tokenUsage->hasReliableTokens());
        self::assertSame(155, $tokenUsage->totalTokens());
    }

    public function testUsoDeTokensPuedeSerDesconocido(): void
    {
        $response = new \NumaResponse('Sin metrica fiable.');
        $usage = $response->tokenUsage();

        self::assertNull($usage->inputTokens());
        self::assertNull($usage->outputTokens());
        self::assertNull($usage->totalTokens());
        self::assertFalse($usage->hasReliableTokens());
    }

    public function testUsoDeTokensRechazaValoresNegativos(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new \NumaTokenUsage(-1, 0);
    }

    public function testSolicitudDeToolRequiereNombre(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new \NumaToolRequest('   ');
    }

    public function testErrorDeProveedorEsSeguroYTransportableEnExcepcion(): void
    {
        $error = new \NumaProviderError(
            \NumaProviderError::TIMEOUT,
            'NUMA_PROVIDER_TIMEOUT',
            true
        );
        $exception = new \NumaProviderException($error);

        self::assertSame(\NumaProviderError::TIMEOUT, $error->type());
        self::assertSame('NUMA_PROVIDER_TIMEOUT', $error->safeCode());
        self::assertTrue($error->retryable());
        self::assertSame($error, $exception->providerError());
        self::assertSame('NUMA_PROVIDER_TIMEOUT', $exception->getMessage());
    }

    public function testErrorDeProveedorRechazaTiposNoSoportados(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new \NumaProviderError('gemini_specific_error', 'NUMA_PROVIDER_UNAVAILABLE');
    }
}
