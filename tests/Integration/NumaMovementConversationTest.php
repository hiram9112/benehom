<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/services/NumaService.php';

final class NumaMovementConversationTest extends IntegrationTestCase
{
    /** @var array<string, string|null> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['NUMA_ENABLED', 'NUMA_MAX_INPUT_TOKENS', 'NUMA_MAX_OUTPUT_TOKENS', 'NUMA_MAX_PROVIDER_CALLS', 'NUMA_MAX_TOOL_RESULT_CHARS'] as $key) {
            $this->envBackup[$key] = array_key_exists($key, $_ENV) ? (string) $_ENV[$key] : null;
        }

        $_ENV['NUMA_ENABLED'] = 'true';
        $_ENV['NUMA_MAX_INPUT_TOKENS'] = '16000';
        $_ENV['NUMA_MAX_OUTPUT_TOKENS'] = '1000';
        $_ENV['NUMA_MAX_PROVIDER_CALLS'] = '5';
        $_ENV['NUMA_MAX_TOOL_RESULT_CHARS'] = (string) \NumaFinancialToolRegistry::MAX_AGGREGATE_RESULT_JSON_CHARS;
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
                continue;
            }

            $_ENV[$key] = $value;
        }

        parent::tearDown();
    }

    public function testConversacionDeMovimientosSeleccionaToolFiltraPorUsuarioYAnunciaListadoAcotado(): void
    {
        $usuario = $this->crearUsuario('numa-movimientos-conversacion@example.test');
        $otroUsuario = $this->crearUsuario('numa-movimientos-conversacion-otro@example.test');
        $usuarioId = (int) $usuario['id'];

        $months = ['2026-05-01', '2026-06-01', '2026-07-01'];
        for ($day = 1; $day <= 12; $day++) {
            $this->insertGasto($usuarioId, 'flexible', 'regalos', $day, $months[($day - 1) % count($months)]);
        }
        $this->insertGasto((int) $otroUsuario['id'], 'flexible', 'regalos', 999, '2026-07-31');
        $this->insertGasto($usuarioId, 'flexible', 'regalos', 999, '2026-08-01');

        $provider = new NumaMovementConversationProvider(
            new \NumaResponse('clasificacion', [
                'intent' => 'datos_usuario',
                'allowed' => true,
                'reason' => 'user_movements',
                'needs_clarification' => false,
                'knowledge_query' => null,
            ]),
            new \NumaResponse('consulta', null, new \NumaToolRequest('obtener_movimientos', [
                'periodo' => 'mayo a julio de 2026',
                'tipo_movimiento' => 'gasto',
                'tipo_gasto' => 'flexible',
                'categoria' => 'regalos',
                'orden' => 'cantidad',
                'direccion' => 'desc',
                'limite' => 5,
            ])),
            new \NumaResponse('Estos son los movimientos seleccionados de mayo, junio y julio de 2026. Este listado es una selección acotada; puedes consultar el registro completo en BeneHom.'),
        );
        $service = new \NumaService(
            new \NumaUso($this->db, new \DateTimeImmutable('2026-08-12')),
            new \NumaLocalScopeClassifier(),
            static fn (?\NumaProviderConsumptionInterface $consumption): \NumaProviderInterface => $provider,
            static fn (): array => [],
            new \NumaFinancialToolRegistry(
                new \NumaFinancialToolExecutor($this->db),
                2,
                \NumaFinancialToolRegistry::MAX_AGGREGATE_RESULT_JSON_CHARS,
            ),
            new class implements \NumaGlobalAvailabilityInterface {
                public function assertAvailable(): void
                {
                }
            },
            new \NumaPeriodResolver(new \DateTimeImmutable('2026-08-12', new \DateTimeZone('Europe/Madrid'))),
        );

        $result = $service->answer($usuarioId, 'Muéstrame mis movimientos de regalos entre mayo y julio de 2026.', [[
            'role' => 'assistant',
            'message' => 'En julio registraste varios regalos.',
            'period' => ['start' => '2026-07-01', 'end' => '2026-07-31'],
        ]]);

        self::assertSame([
            'start' => '2026-05-01',
            'end' => '2026-07-31',
        ], $result->toArray()['period']);
        self::assertSame(
            'Estos son los movimientos seleccionados de mayo, junio y julio de 2026. Este listado es una selección acotada; puedes consultar el registro completo en BeneHom.',
            $result->toArray()['message']
        );
        self::assertSame(1, substr_count($result->toArray()['message'], 'BeneHom'));
        self::assertCount(3, $provider->requests());
        self::assertSame([1000, 1000, 1000], array_map(
            static fn (\NumaRequest $request): ?int => $request->maxOutputTokens(),
            $provider->requests(),
        ));
        self::assertSame((new \NumaFinancialToolRegistry())->names(), $provider->requests()[1]->availableTools());
        self::assertSame(\NumaRequest::FUNCTION_CALLING_ANY, $provider->requests()[1]->functionCallingMode());
        self::assertSame(\NumaRequest::FUNCTION_CALLING_AUTO, $provider->requests()[2]->functionCallingMode());

        $toolResults = $this->financialToolResults($provider->requests()[2]->context());
        self::assertCount(1, $toolResults);
        self::assertSame('obtener_movimientos', $toolResults[0]['tool']);
        self::assertSame(['inicio' => '2026-05-01', 'fin' => '2026-07-31'], $toolResults[0]['periodo']);
        self::assertSame('gasto', $toolResults[0]['tipo_movimiento']);
        self::assertSame('flexible', $toolResults[0]['tipo_gasto']);
        self::assertSame('regalos', $toolResults[0]['categoria']);
        self::assertSame(12, $toolResults[0]['cantidad_total']);
        self::assertSame('78.00', $toolResults[0]['importe_total']);
        self::assertTrue($toolResults[0]['seleccion_acotada']);
        self::assertCount(5, $toolResults[0]['movimientos']);
        self::assertSame('julio de 2026', $toolResults[0]['movimientos'][0]['fecha']);
        self::assertContains('junio de 2026', array_column($toolResults[0]['movimientos'], 'fecha'));
        self::assertContains('mayo de 2026', array_column($toolResults[0]['movimientos'], 'fecha'));
        self::assertNotContains('2026-06-01', array_column($toolResults[0]['movimientos'], 'fecha'));
        self::assertArrayNotHasKey('categoria', $toolResults[0]['movimientos'][0]);
        self::assertLessThanOrEqual(
            \NumaFinancialToolRegistry::MAX_AGGREGATE_RESULT_JSON_CHARS,
            strlen(json_encode($toolResults, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
        );
        self::assertSame('12.00', $toolResults[0]['movimientos'][0]['cantidad']);
    }

    private function insertGasto(int $usuarioId, string $tipo, string $categoria, float $cantidad, string $fecha): void
    {
        $stmt = $this->db->prepare('INSERT INTO gastos (usuario_id, tipo, categoria, cantidad, fecha) VALUES (:usuario_id, :tipo, :categoria, :cantidad, :fecha)');
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':tipo' => $tipo,
            ':categoria' => $categoria,
            ':cantidad' => $cantidad,
            ':fecha' => $fecha,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $context
     * @return array<int, array<string, mixed>>
     */
    private function financialToolResults(array $context): array
    {
        foreach ($context as $item) {
            if (($item['type'] ?? null) === 'financial_tool_results' && is_array($item['items'] ?? null)) {
                return $item['items'];
            }
        }

        self::fail('La respuesta final no recibió resultados de tools.');
    }
}

final class NumaMovementConversationProvider implements \NumaProviderInterface
{
    /** @var array<int, \NumaResponse> */
    private array $responses;

    /** @var array<int, \NumaRequest> */
    private array $requests = [];

    public function __construct(\NumaResponse ...$responses)
    {
        $this->responses = $responses;
    }

    public function respond(\NumaRequest $request): \NumaResponse
    {
        $this->requests[] = $request;

        if ($this->responses === []) {
            throw new \RuntimeException('El proveedor de prueba no tiene más respuestas.');
        }

        return array_shift($this->responses);
    }

    /** @return array<int, \NumaRequest> */
    public function requests(): array
    {
        return $this->requests;
    }
}
