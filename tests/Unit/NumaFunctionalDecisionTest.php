<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('validToolArgumentsProvider')]
    public function testAceptaArgumentosValidosParaCadaTool(string $tool, array $arguments): void
    {
        $decision = \NumaFunctionalDecision::fromStructuredData($this->toolDecision($tool, $arguments));

        self::assertSame($tool, $decision->toolRequest()?->name());
        self::assertSame($arguments, $decision->toolRequest()?->arguments());
        self::assertTrue($this->schemaAllowsArguments($tool, $arguments));
    }

    public function testElEsquemaExigePeriodosCompletosParaLasTools(): void
    {
        $schema = \NumaFunctionalDecision::responseSchema();

        self::assertFalse($this->schemaAllowsArguments('obtener_resumen_financiero', []));
        self::assertFalse($this->schemaAllowsArguments('obtener_ranking_categorias', ['metrica' => 'gastos']));
        self::assertFalse($this->schemaAllowsArguments('comparar_periodos', [
            'metrica' => 'gastos',
            'periodo_a' => 'mes_actual',
        ]));
        self::assertFalse($this->schemaAllowsArguments('obtener_ranking_categorias', [
            'periodo' => 'mes_actual',
            'metrica' => 'saldo',
        ]));
        self::assertSame('OBJECT', $schema['properties']['tool']['type']);
        self::assertTrue($schema['properties']['tool']['nullable']);
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

    public function testAceptaInteraccionConversacionalSinCapacidades(): void
    {
        $decision = \NumaFunctionalDecision::fromStructuredData([
            'intent' => 'interaccion_conversacional',
            'allowed' => true,
            'reason' => 'social_continuity',
            'needs_clarification' => false,
            'knowledge_query' => null,
            'tool' => null,
        ]);

        self::assertSame('interaccion_conversacional', $decision->classification()->intent());
        self::assertFalse($decision->needsClarification());
        self::assertNull($decision->toolRequest());
        self::assertTrue(\NumaFunctionalDecision::responseSchema()['properties']['tool']['nullable']);
    }

    #[DataProvider('decisionesConversacionalesInvalidasProvider')]
    public function testRechazaCapacidadesEnDecisionConversacional(array $overrides): void
    {
        $this->expectException(InvalidArgumentException::class);

        \NumaFunctionalDecision::fromStructuredData([
            'intent' => 'interaccion_conversacional',
            'allowed' => true,
            'reason' => 'social_continuity',
            'needs_clarification' => false,
            'knowledge_query' => null,
            'tool' => null,
            ...$overrides,
        ]);
    }

    public static function decisionesConversacionalesInvalidasProvider(): array
    {
        return [
            'aclaracion estructurada' => [['needs_clarification' => true]],
            'consulta documental' => [['knowledge_query' => 'BeneHom']],
            'tool' => [[
                'tool' => [
                    'name' => 'obtener_resumen_financiero',
                    'arguments' => [],
                ],
            ]],
        ];
    }

    /** @return array<string, array{0:string, 1:array<string, mixed>}> */
    public static function validToolArgumentsProvider(): array
    {
        return [
            'resumen con periodo relativo' => ['obtener_resumen_financiero', ['periodo' => 'mes_actual']],
            'ranking con rango de fechas' => ['obtener_ranking_categorias', [
                'fecha_inicio' => '2026-07-01',
                'fecha_fin' => '2026-07-31',
                'metrica' => 'gastos',
            ]],
            'evolucion con periodo relativo' => ['obtener_evolucion_financiera', [
                'periodo' => 'anio_actual',
                'agrupacion' => 'mes',
            ]],
            'comparacion con periodos relativos' => ['comparar_periodos', [
                'periodo_a' => 'mes_actual',
                'periodo_b' => 'mes_anterior',
                'metrica' => 'gastos',
            ]],
            'estadisticas con rango de fechas' => ['obtener_estadisticas_movimientos', [
                'fecha_inicio' => '2026-07-01',
                'fecha_fin' => '2026-07-31',
                'metrica' => 'ingresos',
            ]],
            'movimientos con periodo relativo' => ['obtener_movimientos', [
                'periodo' => 'mes_anterior',
                'orden' => 'fecha',
                'direccion' => 'desc',
            ]],
        ];
    }

    /** @param array<string, mixed> $arguments */
    private function toolDecision(string $tool, array $arguments): array
    {
        return [
            'intent' => 'datos_usuario',
            'allowed' => true,
            'reason' => 'user_data',
            'needs_clarification' => false,
            'knowledge_query' => null,
            'tool' => ['name' => $tool, 'arguments' => $arguments],
        ];
    }

    /** @param array<string, mixed> $arguments */
    private function schemaAllowsArguments(string $tool, array $arguments): bool
    {
        $toolSchema = \NumaFunctionalDecision::responseSchema()['properties']['tool'];
        $variants = $toolSchema['anyOf'];

        foreach ($variants as $variant) {
            if (($variant['properties']['name']['enum'] ?? null) !== [$tool]) {
                continue;
            }

            $argumentsSchema = $variant['properties']['arguments'];
            $properties = $argumentsSchema['properties'] ?? [];

            foreach ($argumentsSchema['anyOf'] ?? [] as $requirement) {
                $required = $requirement['required'] ?? [];
                if (array_diff(array_keys($arguments), array_keys($properties)) === []
                    && array_diff($required, array_keys($arguments)) === []
                    && $this->argumentValuesMatchSchema($arguments, $properties)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, array<string, mixed>> $properties
     */
    private function argumentValuesMatchSchema(array $arguments, array $properties): bool
    {
        foreach ($arguments as $name => $value) {
            $property = $properties[$name];
            if (isset($property['enum']) && !in_array($value, $property['enum'], true)) {
                return false;
            }

            if (($property['type'] ?? null) === 'INTEGER'
                && (!is_int($value)
                    || (isset($property['minimum']) && $value < $property['minimum'])
                    || (isset($property['maximum']) && $value > $property['maximum'])
                )
            ) {
                return false;
            }
        }

        return true;
    }
}
