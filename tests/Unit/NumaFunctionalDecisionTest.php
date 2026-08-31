<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/NumaClassification.php';

final class NumaFunctionalDecisionTest extends TestCase
{
    public function testClasificacionFinancieraNoSeleccionaTools(): void
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
                ]);
            }
        };

        $decision = (new \NumaProviderFunctionalDecider($provider))->decide('¿Cuánto gasté este mes?');

        self::assertSame('datos_usuario', $decision->classification()->intent());
        self::assertSame([], $provider->request?->availableTools());
        self::assertSame(\NumaRequest::CLASSIFICATION_OUTPUT_TOKENS, $provider->request?->maxOutputTokens());
        self::assertArrayNotHasKey('tool', $provider->request?->responseSchema()['properties'] ?? []);
        self::assertSame(
            ['intent', 'allowed', 'reason', 'needs_clarification', 'knowledge_query'],
            $provider->request?->responseSchema()['required'] ?? [],
        );
    }

    public function testRechazaElAntiguoArbolDeSeleccionDeTool(): void
    {
        $this->expectException(InvalidArgumentException::class);

        \NumaFunctionalDecision::fromStructuredData([
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

    public function testRechazaClavesDesconocidasEnLaDecision(): void
    {
        $this->expectException(InvalidArgumentException::class);

        \NumaFunctionalDecision::fromStructuredData([
            'intent' => 'producto',
            'allowed' => true,
            'reason' => 'product_help',
            'needs_clarification' => false,
            'knowledge_query' => 'movimientos',
            'usuario_id' => 7,
        ]);
    }

    public function testAceptaDatosFinancierosCompletosSinPreseleccionarTool(): void
    {
        $decision = \NumaFunctionalDecision::fromStructuredData([
            'intent' => 'datos_usuario',
            'allowed' => true,
            'reason' => 'user_data',
            'needs_clarification' => false,
            'knowledge_query' => null,
        ]);

        self::assertSame('datos_usuario', $decision->classification()->intent());
        self::assertFalse($decision->needsClarification());
    }

    public function testAceptaInteraccionConversacionalSinCapacidades(): void
    {
        $decision = \NumaFunctionalDecision::fromStructuredData([
            'intent' => 'interaccion_conversacional',
            'allowed' => true,
            'reason' => 'social_continuity',
            'needs_clarification' => false,
            'knowledge_query' => null,
        ]);

        self::assertSame('interaccion_conversacional', $decision->classification()->intent());
        self::assertFalse($decision->needsClarification());
    }

    #[DataProvider('decisionesInvalidasProvider')]
    public function testRechazaCombinacionesDeCapacidadesInvalidas(array $decision): void
    {
        $this->expectException(InvalidArgumentException::class);

        \NumaFunctionalDecision::fromStructuredData($decision);
    }

    /** @return array<string, array{0:array<string, mixed>}> */
    public static function decisionesInvalidasProvider(): array
    {
        return [
            'datos privados con RAG' => [[
                'intent' => 'datos_usuario',
                'allowed' => true,
                'reason' => 'user_data',
                'needs_clarification' => false,
                'knowledge_query' => 'documentacion',
            ]],
            'combinada sin RAG' => [[
                'intent' => 'consulta_combinada',
                'allowed' => true,
                'reason' => 'combined',
                'needs_clarification' => false,
                'knowledge_query' => null,
            ]],
            'aclaracion con RAG' => [[
                'intent' => 'producto',
                'allowed' => true,
                'reason' => 'ambiguous',
                'needs_clarification' => true,
                'knowledge_query' => 'movimientos',
            ]],
            'rechazo con acciones posteriores' => [[
                'intent' => 'fuera_de_ambito',
                'allowed' => false,
                'reason' => 'general',
                'needs_clarification' => false,
                'knowledge_query' => 'consulta',
            ]],
        ];
    }
}
