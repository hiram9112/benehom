<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/NumaClassification.php';

final class NumaFunctionalDecisionTest extends TestCase
{
    public function testPrimeraLlamadaSolicitaDecisionNativaConTool(): void
    {
        $provider = new class implements \NumaProviderInterface {
            public ?\NumaRequest $request = null;

            public function respond(\NumaRequest $request): \NumaResponse
            {
                $this->request = $request;

                return new \NumaResponse('', [
                    'intent' => 'datos_usuario',
                    'allowed' => true,
                    'reason' => 'user_data',
                    'needs_clarification' => false,
                    'knowledge_query' => null,
                    'tool' => [
                        'name' => 'obtener_resumen_financiero',
                        'arguments' => ['periodo' => 'mes_actual'],
                    ],
                ]);
            }
        };

        $decision = (new \NumaProviderFunctionalDecider($provider))->decide('¿Cuánto gasté este mes?');

        self::assertSame('datos_usuario', $decision->classification()->intent());
        self::assertSame('obtener_resumen_financiero', $decision->toolRequest()?->name());
        self::assertSame(['periodo' => 'mes_actual'], $decision->toolRequest()?->arguments());
        self::assertSame([], $provider->request?->availableTools());
        self::assertSame('OBJECT', $provider->request?->responseSchema()['type']);
        self::assertSame('BOOLEAN', $provider->request?->responseSchema()['properties']['allowed']['type']);
    }

    public function testRechazaClavesDesconocidasEnLaDecision(): void
    {
        $this->expectException(InvalidArgumentException::class);

        \NumaFunctionalDecision::fromStructuredData([
            'intent' => 'producto',
            'allowed' => true,
            'reason' => 'product_help',
            'needs_clarification' => false,
            'knowledge_query' => 'movimientos',
            'tool' => null,
            'usuario_id' => 7,
        ]);
    }

    public function testRechazaDatosFinancierosSinTool(): void
    {
        $this->expectException(InvalidArgumentException::class);

        \NumaFunctionalDecision::fromStructuredData([
            'intent' => 'datos_usuario',
            'allowed' => true,
            'reason' => 'user_data',
            'needs_clarification' => false,
            'knowledge_query' => null,
            'tool' => null,
        ]);
    }
}
