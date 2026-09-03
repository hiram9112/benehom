<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;

require_once APP_PATH . '/services/GeminiNumaProvider.php';
require_once APP_PATH . '/services/NumaService.php';

final class NumaFinancialFunctionCallingTest extends IntegrationTestCase
{
    /** @var array<string, string|null> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['NUMA_ENABLED', 'NUMA_MAX_INPUT_TOKENS', 'NUMA_MAX_OUTPUT_TOKENS', 'NUMA_MAX_PROVIDER_CALLS', 'NUMA_MAX_TOOL_CALLS', 'NUMA_MAX_TOOL_RESULT_CHARS'] as $key) {
            $this->envBackup[$key] = array_key_exists($key, $_ENV) ? (string) $_ENV[$key] : null;
        }

        $_ENV['NUMA_ENABLED'] = 'true';
        $_ENV['NUMA_MAX_INPUT_TOKENS'] = '16000';
        $_ENV['NUMA_MAX_OUTPUT_TOKENS'] = '1000';
        $_ENV['NUMA_MAX_PROVIDER_CALLS'] = '5';
        $_ENV['NUMA_MAX_TOOL_CALLS'] = '5';
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

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('representativeFinancialRequests')]
    public function testFunctionCallingPreservesCanonicalFiltersAndReturnsAuthorizedFacts(
        string $message,
        string $tool,
        array $arguments,
        string $expectedResultKey,
        string $expectedResultValue,
    ): void {
        $usuario = $this->crearUsuario('numa-function-calling@example.test');
        $otroUsuario = $this->crearUsuario('numa-function-calling-otro@example.test');
        $usuarioId = (int) $usuario['id'];

        $this->insertGasto($usuarioId, 'esencial', 'electricidad', 40, '2026-07-03');
        $this->insertGasto($usuarioId, 'esencial', 'electricidad', 60, '2026-07-10');
        $this->insertGasto($usuarioId, 'flexible', 'comida_domicilio', 20, '2026-07-12');
        $this->insertGasto($usuarioId, 'flexible', 'comida_domicilio', 30, '2026-07-18');
        $this->insertGasto((int) $otroUsuario['id'], 'esencial', 'electricidad', 999, '2026-07-15');

        $payloads = [];
        $finalMessage = $tool === 'obtener_estadisticas_movimientos'
            ? 'En julio, tus gastos de electricidad sumaron 100.00 €.'
            : 'He procesado los datos autorizados de tu cuenta.';
        $result = $this->service($payloads, [
            $this->classificationResponse(),
            $this->functionCallResponse('financial-call-1', $tool, $arguments),
            $this->textResponse($finalMessage),
        ])->answer($usuarioId, $message);

        self::assertSame(['start' => '2026-07-01', 'end' => '2026-07-31'], $result->toArray()['period']);
        self::assertSame($finalMessage, $result->toArray()['message']);
        self::assertSame(3, $result->toArray()['usage']['daily_used']);
        self::assertCount(3, $payloads);
        self::assertSame('application/json', $payloads[0]['generationConfig']['responseMimeType']);
        self::assertArrayNotHasKey('tool', $payloads[0]['generationConfig']['responseSchema']['properties']);
        self::assertArrayNotHasKey('responseSchema', $payloads[1]['generationConfig']);
        self::assertSame('ANY', $payloads[1]['toolConfig']['functionCallingConfig']['mode']);
        self::assertSame('AUTO', $payloads[2]['toolConfig']['functionCallingConfig']['mode']);
        self::assertSame(
            array_map(
                fn (\NumaFinancialToolDefinition $definition): array => $this->withoutAdditionalProperties($definition->functionDeclaration()),
                array_values((new \NumaFinancialToolRegistry())->all()),
            ),
            $payloads[1]['tools'][0]['functionDeclarations'],
        );
        self::assertSame($tool, $payloads[2]['contents'][1]['parts'][0]['functionCall']['name']);
        self::assertSame('financial-call-1', $payloads[2]['contents'][1]['parts'][0]['functionCall']['id']);
        self::assertSame($arguments, $payloads[2]['contents'][1]['parts'][0]['functionCall']['args']);
        self::assertSame('financial-call-1', $payloads[2]['contents'][2]['parts'][0]['functionResponse']['id']);
        self::assertSame($tool, $payloads[2]['contents'][2]['parts'][0]['functionResponse']['name']);
        self::assertSame(
            $expectedResultValue,
            (string) $payloads[2]['contents'][2]['parts'][0]['functionResponse']['response']['result'][$expectedResultKey],
        );
    }

    public function testFunctionCallingCompletaDosToolsSecuencialesDentroDelPresupuesto(): void
    {
        $usuario = $this->crearUsuario('numa-function-sequential@example.test');
        $usuarioId = (int) $usuario['id'];
        $this->insertGasto($usuarioId, 'esencial', 'electricidad', 40, '2026-07-03');
        $this->insertGasto($usuarioId, 'flexible', 'comida_domicilio', 20, '2026-07-12');

        $payloads = [];
        $executedTools = [];
        $result = $this->service($payloads, [
            $this->classificationResponse(),
            $this->functionCallResponse('first-call', 'obtener_estadisticas_movimientos', [
                'periodo' => 'julio',
                'metrica' => 'gastos',
                'categoria' => 'electricidad',
            ]),
            $this->functionCallResponse('second-call', 'obtener_movimientos', [
                'periodo' => 'julio',
                'tipo_movimiento' => 'gasto',
                'categoria' => 'comida_domicilio',
                'orden' => 'fecha',
                'direccion' => 'asc',
            ]),
            $this->textResponse('He procesado los dos resultados autorizados.'),
        ], toolExecutionObserver: static function (string $tool) use (&$executedTools): void {
            $executedTools[] = $tool;
        })->answer($usuarioId, 'Compara mis gastos de luz y comida a domicilio de julio.');

        self::assertSame('He procesado los dos resultados autorizados.', $result->toArray()['message']);
        self::assertSame(4, $result->toArray()['usage']['daily_used']);
        self::assertCount(4, $payloads);
        self::assertSame(['ANY', 'AUTO', 'AUTO'], array_map(
            static fn (array $payload): string => $payload['toolConfig']['functionCallingConfig']['mode'],
            array_slice($payloads, 1),
        ));
        self::assertSame('first-call', $payloads[3]['contents'][1]['parts'][0]['functionCall']['id']);
        self::assertSame('first-call', $payloads[3]['contents'][2]['parts'][0]['functionResponse']['id']);
        self::assertSame('second-call', $payloads[3]['contents'][3]['parts'][0]['functionCall']['id']);
        self::assertSame('second-call', $payloads[3]['contents'][4]['parts'][0]['functionResponse']['id']);
        self::assertSame('obtener_estadisticas_movimientos', $payloads[3]['contents'][2]['parts'][0]['functionResponse']['name']);
        self::assertSame('obtener_movimientos', $payloads[3]['contents'][4]['parts'][0]['functionResponse']['name']);
        self::assertSame(['obtener_estadisticas_movimientos', 'obtener_movimientos'], $executedTools);
    }

    public function testProcesaLlamadasParalelasConRespuestasEmparejadas(): void
    {
        $usuario = $this->crearUsuario('numa-function-parallel@example.test');
        $usuarioId = (int) $usuario['id'];
        $this->insertGasto($usuarioId, 'esencial', 'electricidad', 40, '2026-06-03');
        $this->insertGasto($usuarioId, 'flexible', 'comida_domicilio', 20, '2026-06-12');
        $payloads = [];
        $result = $this->service($payloads, [
            $this->classificationResponse(),
            $this->parallelFunctionCallsResponse(),
            $this->textResponse('La comparación está lista.'),
        ])
            ->answer($usuarioId, 'Compara cuánto gasté en electricidad y comida a domicilio en junio.');

        self::assertSame('La comparación está lista.', $result->toArray()['message']);
        self::assertSame(3, $result->toArray()['usage']['daily_used']);
        self::assertCount(3, $payloads);
        self::assertSame('parallel-electricity', $payloads[2]['contents'][1]['parts'][0]['functionCall']['id']);
        self::assertSame('parallel-delivery', $payloads[2]['contents'][1]['parts'][1]['functionCall']['id']);
        self::assertSame('parallel-electricity', $payloads[2]['contents'][2]['parts'][0]['functionResponse']['id']);
        self::assertSame('parallel-delivery', $payloads[2]['contents'][2]['parts'][1]['functionResponse']['id']);
        self::assertSame('obtener_estadisticas_movimientos', $payloads[2]['contents'][2]['parts'][0]['functionResponse']['name']);
        self::assertSame('obtener_estadisticas_movimientos', $payloads[2]['contents'][2]['parts'][1]['functionResponse']['name']);
        self::assertSame('40.00', $payloads[2]['contents'][2]['parts'][0]['functionResponse']['response']['result']['total']);
        self::assertSame('20.00', $payloads[2]['contents'][2]['parts'][1]['functionResponse']['response']['result']['total']);
    }

    public function testListadoAcotadoConTresToolsAlcanzaLaRespuestaFinalDentroDelPresupuesto(): void
    {
        $usuario = $this->crearUsuario('numa-function-bounded-list@example.test');
        $usuarioId = (int) $usuario['id'];

        foreach (['2026-06-01', '2026-05-01', '2026-04-01', '2026-03-01', '2026-02-01'] as $date) {
            $this->insertGasto($usuarioId, 'esencial', 'alquiler_hipoteca', 720, $date);
        }
        for ($index = 0; $index < 82; $index++) {
            $this->insertGasto($usuarioId, 'flexible', 'ocio', 90, sprintf('2026-%02d-01', ($index % 6) + 1));
        }
        $this->insertGasto($usuarioId, 'flexible', 'ocio', 76.98, '2026-01-01');

        $payloads = [];
        $executedTools = [];
        $result = $this->service($payloads, [
            $this->classificationResponse(),
            $this->threeFinancialCallsResponse(),
            $this->textResponse('He preparado los datos solicitados.'),
        ], toolExecutionObserver: static function (string $tool) use (&$executedTools): void {
            $executedTools[] = $tool;
        })->answer(
            $usuarioId,
            'Quiero revisar mis gastos entre enero y junio de 2026. Dime cuántos movimientos hubo, cuánto gasté en total y muéstrame solo los 5 movimientos de mayor importe, sin listar el resto.',
        );

        self::assertSame(
            "He preparado los datos solicitados.\n\nEl listado es parcial; puedes consultar el completo en BeneHom.",
            $result->toArray()['message'],
        );
        self::assertSame([
            'obtener_resumen_financiero',
            'obtener_estadisticas_movimientos',
            'obtener_movimientos',
        ], $executedTools);
        self::assertCount(3, $payloads);

        $toolResults = array_map(
            static fn (array $part): array => $part['functionResponse']['response']['result'],
            $payloads[2]['contents'][2]['parts'],
        );
        self::assertLessThanOrEqual(
            \NumaFinancialToolRegistry::MAX_AGGREGATE_RESULT_JSON_CHARS,
            strlen(json_encode($toolResults, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
        );
        self::assertSame(88, $toolResults[2]['cantidad_total']);
        self::assertSame('11056.98', $toolResults[2]['importe_total']);
        self::assertCount(5, $toolResults[2]['movimientos']);
        self::assertSame('junio de 2026', $toolResults[2]['movimientos'][0]['fecha']);
        self::assertSame('Alquiler o hipoteca', $toolResults[2]['movimientos'][0]['label']);
        self::assertSame('720.00', $toolResults[2]['movimientos'][0]['cantidad']);
        self::assertArrayNotHasKey('categoria', $toolResults[2]['movimientos'][0]);
    }

    public function testConsultaFinancieraSinPeriodoPideAclaracionSinEjecutarTools(): void
    {
        $usuario = $this->crearUsuario('numa-function-missing-period@example.test');
        $usuarioId = (int) $usuario['id'];
        $this->insertGasto($usuarioId, 'esencial', 'electricidad', 40, '2026-06-03');
        $payloads = [];
        $recordingPdo = new NumaFunctionCallingRecordingPdo();
        $financialTools = new NumaFunctionCallingRecordingRegistry(
            new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($recordingPdo)),
        );
        $result = $this->service($payloads, [
            $this->classificationResponse(),
            $this->functionCallResponse('missing-period', 'obtener_estadisticas_movimientos', [
                'metrica' => 'gastos',
                'categoria' => 'electricidad',
            ]),
        ], $financialTools)->answer($usuarioId, '¿Cuánto gasté en electricidad?');

        self::assertSame('Necesito que concretes un poco más la consulta para poder ayudarte.', $result->toArray()['message']);
        self::assertSame(2, $result->toArray()['usage']['daily_used']);
        self::assertCount(2, $payloads);
        self::assertSame('ANY', $payloads[1]['toolConfig']['functionCallingConfig']['mode']);
        self::assertSame(0, $financialTools->executions);
        self::assertSame([], $recordingPdo->preparedSql);
        self::assertSame(1, $this->countGastos($usuarioId));
    }

    public function testPromedioMensualDeGastosEnRangoExplicitoDeVariosMesesEjecutaEstadisticas(): void
    {
        $usuario = $this->crearUsuario('numa-function-monthly-average-range@example.test');
        $usuarioId = (int) $usuario['id'];
        $this->insertGasto($usuarioId, 'esencial', 'alimentacion', 100.10, '2026-01-03');
        $this->insertGasto($usuarioId, 'flexible', 'ocio', 200.20, '2026-06-04');
        $payloads = [];
        $executedTools = [];

        $result = $this->service($payloads, [
            $this->classificationResponse(),
            $this->functionCallResponse('monthly-average-range', 'obtener_estadisticas_movimientos', [
                'periodo' => 'junio',
                'metrica' => 'gastos',
            ]),
            $this->textResponse('Tu promedio mensual de gastos fue 150.15 EUR.'),
        ], toolExecutionObserver: static function (string $tool) use (&$executedTools): void {
            $executedTools[] = $tool;
        })->answer($usuarioId, '¿Cuál fue mi promedio mensual de gastos entre enero y junio de 2026?');

        self::assertSame('Tu promedio mensual de gastos fue 150.15 EUR.', $result->toArray()['message']);
        self::assertNotSame('Necesito que concretes un poco más la consulta para poder ayudarte.', $result->toArray()['message']);
        self::assertSame(['start' => '2026-01-01', 'end' => '2026-06-30'], $result->toArray()['period']);
        self::assertSame(['obtener_estadisticas_movimientos'], $executedTools);
        self::assertSame('2026-01-01', $payloads[2]['contents'][2]['parts'][0]['functionResponse']['response']['result']['periodo']['inicio']);
        self::assertSame('2026-06-30', $payloads[2]['contents'][2]['parts'][0]['functionResponse']['response']['result']['periodo']['fin']);
        self::assertSame('300.30', $payloads[2]['contents'][2]['parts'][0]['functionResponse']['response']['result']['total']);
        self::assertSame('150.15', $payloads[2]['contents'][2]['parts'][0]['functionResponse']['response']['result']['promedio_mensual']);
    }

    public function testSeguimientoSinPeriodoReutilizaElPeriodoEstructuradoDeJunio(): void
    {
        $usuario = $this->crearUsuario('numa-function-follow-up-period@example.test');
        $usuarioId = (int) $usuario['id'];
        $this->insertGasto($usuarioId, 'esencial', 'electricidad', 40, '2026-06-03');
        $this->insertGasto($usuarioId, 'esencial', 'electricidad', 999, '2026-09-03');
        $payloads = [];
        $result = $this->service($payloads, [
            $this->classificationResponse(),
            $this->functionCallResponse('follow-up-period', 'obtener_estadisticas_movimientos', [
                'periodo' => 'septiembre',
                'metrica' => 'gastos',
                'categoria' => 'electricidad',
            ]),
            $this->textResponse('He usado el periodo conversacional autorizado.'),
        ])->answer($usuarioId, '¿Cuánto gasté en electricidad?', [
            ['role' => 'user', 'message' => '¿Cuánto gasté en junio?'],
            [
                'role' => 'assistant',
                'message' => 'En junio se registraron gastos.',
                'period' => ['start' => '2026-06-01', 'end' => '2026-06-30'],
            ],
        ]);

        self::assertSame('He usado el periodo conversacional autorizado.', $result->toArray()['message']);
        self::assertSame(['start' => '2026-06-01', 'end' => '2026-06-30'], $result->toArray()['period']);
        self::assertSame(3, $result->toArray()['usage']['daily_used']);
        self::assertCount(3, $payloads);
        self::assertSame('2026-06-01', $payloads[2]['contents'][4]['parts'][0]['functionResponse']['response']['result']['periodo']['inicio']);
        self::assertSame('2026-06-30', $payloads[2]['contents'][4]['parts'][0]['functionResponse']['response']['result']['periodo']['fin']);
        self::assertSame('40.00', $payloads[2]['contents'][4]['parts'][0]['functionResponse']['response']['result']['total']);
    }

    public function testPeriodoInventadoPorGeminiSinContextoPideAclaracionSinEjecutarTools(): void
    {
        $usuario = $this->crearUsuario('numa-function-invented-period@example.test');
        $usuarioId = (int) $usuario['id'];
        $payloads = [];
        $recordingPdo = new NumaFunctionCallingRecordingPdo();
        $financialTools = new NumaFunctionCallingRecordingRegistry(
            new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($recordingPdo)),
        );
        $result = $this->service($payloads, [
            $this->classificationResponse(),
            $this->functionCallResponse('invented-period', 'obtener_estadisticas_movimientos', [
                'periodo' => 'septiembre',
                'metrica' => 'gastos',
                'categoria' => 'electricidad',
            ]),
        ], $financialTools)->answer($usuarioId, '¿Cuánto gasté en electricidad?');

        self::assertSame('Necesito que concretes un poco más la consulta para poder ayudarte.', $result->toArray()['message']);
        self::assertSame(2, $result->toArray()['usage']['daily_used']);
        self::assertCount(2, $payloads);
        self::assertSame(0, $financialTools->executions);
        self::assertSame([], $recordingPdo->preparedSql);
    }

    public function testRechazaLoteParaleloConArgumentoInvalidoSinEjecutarTools(): void
    {
        $usuario = $this->crearUsuario('numa-function-parallel-invalid@example.test');
        $usuarioId = (int) $usuario['id'];
        $this->insertGasto($usuarioId, 'esencial', 'electricidad', 40, '2026-06-03');
        $payloads = [];
        $recordingPdo = new NumaFunctionCallingRecordingPdo();
        $financialTools = new NumaFunctionCallingRecordingRegistry(
            new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($recordingPdo)),
        );
        $service = $this->service($payloads, [
            $this->classificationResponse(),
            $this->parallelFunctionCallsResponse(true),
        ], $financialTools);

        try {
            $service->answer($usuarioId, 'Compara cuánto gasté en electricidad y comida a domicilio en junio.');
            self::fail('El lote con una segunda llamada invalida debe rechazarse completo.');
        } catch (\NumaServiceException $exception) {
            self::assertSame('NUMA_PROVIDER_INVALID_RESPONSE', $exception->safeCode());
        }

        self::assertCount(2, $payloads);
        self::assertSame(2, (new \NumaUso($this->db))->estado($usuarioId)['daily_used']);
        self::assertSame(0, $financialTools->executions);
        self::assertSame([], $recordingPdo->preparedSql);
        self::assertSame(1, $this->countGastos($usuarioId));
    }

    public function testRechazaUnaToolAdicionalCuandoSeAgotaElPresupuesto(): void
    {
        $_ENV['NUMA_MAX_TOOL_CALLS'] = '2';
        $usuario = $this->crearUsuario('numa-function-tool-budget@example.test');
        $usuarioId = (int) $usuario['id'];
        $this->insertGasto($usuarioId, 'esencial', 'electricidad', 40, '2026-07-03');

        $arguments = ['periodo' => 'julio', 'metrica' => 'gastos', 'categoria' => 'electricidad'];
        $payloads = [];
        $service = $this->service($payloads, [
            $this->classificationResponse(),
            $this->functionCallResponse('first-call', 'obtener_estadisticas_movimientos', $arguments),
            $this->functionCallResponse('second-call', 'obtener_estadisticas_movimientos', $arguments),
            $this->functionCallResponse('third-call', 'obtener_estadisticas_movimientos', $arguments),
        ]);

        try {
            $service->answer($usuarioId, '¿Cuánto gasté en electricidad en julio?');
            self::fail('La tercera tool debe quedar fuera del presupuesto.');
        } catch (\NumaServiceException $exception) {
            self::assertSame('NUMA_PROVIDER_INVALID_RESPONSE', $exception->safeCode());
        }

        self::assertCount(4, $payloads);
        self::assertSame('NONE', $payloads[3]['toolConfig']['functionCallingConfig']['mode']);
        self::assertSame('second-call', $payloads[3]['contents'][3]['parts'][0]['functionCall']['id']);
        self::assertSame('second-call', $payloads[3]['contents'][4]['parts'][0]['functionResponse']['id']);
        self::assertSame(4, (new \NumaUso($this->db))->estado($usuarioId)['daily_used']);
    }

    #[DataProvider('incompleteFinancialRequests')]
    public function testSolicitudFinancieraIncompletaPideAclaracionAntesDeDeclararTools(string $message): void
    {
        $usuario = $this->crearUsuario('numa-function-clarification@example.test');
        $payloads = [];
        $result = $this->service($payloads, [
            $this->classificationResponse(true),
        ])->answer((int) $usuario['id'], $message);

        self::assertSame('Necesito que concretes un poco más la consulta para poder ayudarte.', $result->toArray()['message']);
        self::assertSame(1, $result->toArray()['usage']['daily_used']);
        self::assertCount(1, $payloads);
        self::assertArrayNotHasKey('tools', $payloads[0]);
    }

    /** @return array<string, array{0:string}> */
    public static function incompleteFinancialRequests(): array
    {
        return [
            'periodo ausente' => ['¿Cuánto gasté?'],
            'metrica ausente' => ['Compara julio con agosto.'],
            'agrupacion ausente' => ['¿Cómo evolucionaron mis gastos en julio?'],
            'filtros de movimiento ausentes' => ['Muéstrame mis movimientos.'],
        ];
    }

    public function testRespuestaFinalConCifraNoAutorizadaSeSustituyePorFallbackDeterminista(): void
    {
        $usuario = $this->crearUsuario('numa-function-unbacked-fact@example.test');
        $usuarioId = (int) $usuario['id'];
        $this->insertGasto($usuarioId, 'esencial', 'electricidad', 40, '2026-07-03');
        $this->insertGasto($usuarioId, 'esencial', 'electricidad', 60, '2026-07-10');

        $payloads = [];
        $result = $this->service($payloads, [
            $this->classificationResponse(),
            $this->functionCallResponse('fact-call', 'obtener_estadisticas_movimientos', [
                'periodo' => 'julio',
                'metrica' => 'gastos',
                'categoria' => 'electricidad',
            ]),
            $this->textResponse('Tus gastos de electricidad fueron 101.00 EUR.'),
        ])->answer($usuarioId, '¿Cuánto gasté en electricidad en julio?');

        self::assertSame(
            'En julio de 2026, se registraron 2 movimientos, por un total de 100.00 EUR.',
            $result->toArray()['message'],
        );
        self::assertSame('100.00', $payloads[2]['contents'][2]['parts'][0]['functionResponse']['response']['result']['total']);
    }

    public function testComparacionConEtiquetasTecnicasSeReescribeComoRespuestaNatural(): void
    {
        $usuario = $this->crearUsuario('numa-function-natural-comparison@example.test');
        $usuarioId = (int) $usuario['id'];
        $this->insertGasto($usuarioId, 'flexible', 'ocio', 1854.58, '2026-05-15');
        $this->insertGasto($usuarioId, 'flexible', 'ocio', 2304.28, '2026-06-15');

        $payloads = [];
        $result = $this->service($payloads, [
            $this->classificationResponse(),
            $this->functionCallResponse('comparison-call', 'comparar_periodos', [
                'fecha_inicio_a' => '2026-05-01',
                'fecha_fin_a' => '2026-05-31',
                'fecha_inicio_b' => '2026-06-01',
                'fecha_fin_b' => '2026-06-30',
                'metrica' => 'gastos',
            ]),
            $this->textResponse('Periodo A: 2026-05-01 al 2026-05-31, 1854.58 EUR. Periodo B: 2026-06-01 al 2026-06-30, 2304.28 EUR. Diferencia: 449.70 EUR.'),
        ])->answer($usuarioId, 'Compara mis gastos de mayo y junio de 2026.');

        self::assertSame(
            'En mayo de 2026 gastaste 1854.58 EUR, mientras que en junio de 2026 gastaste 2304.28 EUR. Esto supone un aumento de 449.70 EUR en junio de 2026 respecto a mayo de 2026.',
            $result->toArray()['message'],
        );
        self::assertSame('1854.58', $payloads[2]['contents'][2]['parts'][0]['functionResponse']['response']['result']['valor_a']);
        self::assertSame('2304.28', $payloads[2]['contents'][2]['parts'][0]['functionResponse']['response']['result']['valor_b']);
        self::assertSame('449.70', $payloads[2]['contents'][2]['parts'][0]['functionResponse']['response']['result']['diferencia_absoluta']);
    }

    /**
     * @return array<string, array{0:string, 1:string, 2:array<string, mixed>, 3:string, 4:string}>
     */
    public static function representativeFinancialRequests(): array
    {
        return [
            'luz se resuelve como electricidad' => [
                '¿Cuál fue el gasto medio de luz en julio?',
                'obtener_estadisticas_movimientos',
                ['periodo' => 'julio', 'metrica' => 'gastos', 'categoria' => 'electricidad'],
                'total',
                '100.00',
            ],
            'rango explicito conserva el periodo completo' => [
                '¿Cuánto gasté en electricidad entre el 1 y el 31 de julio?',
                'obtener_estadisticas_movimientos',
                [
                    'fecha_inicio' => '2026-07-01',
                    'fecha_fin' => '2026-07-31',
                    'metrica' => 'gastos',
                    'categoria' => 'electricidad',
                ],
                'total',
                '100.00',
            ],
            'comida a domicilio conserva categoria concreta' => [
                'Muéstrame los movimientos de comida a domicilio de julio.',
                'obtener_movimientos',
                [
                    'periodo' => 'julio',
                    'tipo_movimiento' => 'gasto',
                    'tipo_gasto' => 'flexible',
                    'categoria' => 'comida_domicilio',
                    'orden' => 'fecha',
                    'direccion' => 'asc',
                ],
                'cantidad_total',
                '2',
            ],
            'grupo conserva filtro sin degradarlo a resumen' => [
                'Muéstrame los gastos del grupo suministros de julio.',
                'obtener_movimientos',
                [
                    'periodo' => 'julio',
                    'tipo_movimiento' => 'gasto',
                    'grupo' => 'suministros',
                    'orden' => 'fecha',
                    'direccion' => 'asc',
                ],
                'cantidad_total',
                '2',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('invalidFunctionCalls')]
    public function testFunctionCallsInvalidasOTruncadasNoEjecutanNiDevuelvenResultadosParciales(
        array $arguments,
        string $finishReason = 'STOP',
        string $name = 'obtener_estadisticas_movimientos',
    ): void {
        $usuario = $this->crearUsuario('numa-function-invalid@example.test');
        $usuarioId = (int) $usuario['id'];
        $this->insertGasto($usuarioId, 'esencial', 'electricidad', 40, '2026-07-03');

        $payloads = [];
        $recordingPdo = new NumaFunctionCallingRecordingPdo();
        $service = $this->service($payloads, [
            $this->classificationResponse(),
            $finishReason === 'MAX_TOKENS'
                ? ['candidates' => [['finishReason' => 'MAX_TOKENS']]]
                : $this->functionCallResponse('invalid-call', $name, $arguments),
        ], new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($recordingPdo)));

        try {
            $service->answer($usuarioId, '¿Cuánto gasté en julio?');
            self::fail('La llamada funcional invalida no fue rechazada.');
        } catch (\NumaServiceException $exception) {
            self::assertSame(
                $finishReason === 'MAX_TOKENS' ? 'NUMA_PROVIDER_MAX_TOKENS' : 'NUMA_PROVIDER_INVALID_RESPONSE',
                $exception->safeCode(),
            );
        }

        self::assertCount(2, $payloads);
        self::assertSame('ANY', $payloads[1]['toolConfig']['functionCallingConfig']['mode']);
        self::assertSame(2, (new \NumaUso($this->db))->estado($usuarioId)['daily_used']);
        self::assertSame(1, $this->countGastos($usuarioId));
        self::assertSame([], $recordingPdo->preparedSql);
    }

    /**
     * @return array<string, array{0:array<string, mixed>, 1?:string, 2?:string}>
     */
    public static function invalidFunctionCalls(): array
    {
        return [
            'salida truncada' => [[], 'MAX_TOKENS'],
            'tool desconocida' => [[], 'STOP', 'ejecutar_sql'],
            'argumento adicional' => [[
                'periodo' => 'julio',
                'metrica' => 'gastos',
                'usuario_id' => 999,
            ]],
            'enum no permitido' => [[
                'periodo' => 'julio',
                'metrica' => 'saldo',
            ]],
            'combinacion incompatible' => [[
                'periodo' => 'julio',
                'metrica' => 'ahorro_real',
                'categoria' => 'electricidad',
            ]],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $payloads
     * @param array<int, array<string, mixed>> $responses
     */
    private function service(
        array &$payloads,
        array $responses,
        ?\NumaFinancialToolRegistryInterface $financialTools = null,
        ?callable $toolExecutionObserver = null,
    ): \NumaService
    {
        $responseIndex = 0;
        $providerFactory = function (?\NumaProviderConsumptionInterface $consumption) use (&$payloads, &$responses, &$responseIndex): \NumaProviderInterface {
            return new \GeminiNumaProvider(
                'test-key',
                'gemini-test-model',
                1000,
                10,
                0,
                function (string $url, array $headers, string $body) use (&$payloads, &$responses, &$responseIndex): array {
                    $payloads[] = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

                    return [
                        'status' => 200,
                        'body' => json_encode($responses[$responseIndex++] ?? [], JSON_THROW_ON_ERROR),
                    ];
                },
                consumption: $consumption,
            );
        };

        return new \NumaService(
            new \NumaUso($this->db),
            new \NumaLocalScopeClassifier(),
            $providerFactory,
            static fn (): array => [],
            $financialTools ?? new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db)),
            new class implements \NumaGlobalAvailabilityInterface {
                public function assertAvailable(): void
                {
                }
            },
            new \NumaPeriodResolver(new \DateTimeImmutable('2026-08-12', new \DateTimeZone('Europe/Madrid'))),
            toolExecutionObserver: $toolExecutionObserver,
        );
    }

    /** @return array<string, mixed> */
    private function classificationResponse(bool $needsClarification = false): array
    {
        return $this->textResponse(json_encode([
            'intent' => 'datos_usuario',
            'allowed' => true,
            'reason' => 'user_data',
            'needs_clarification' => $needsClarification,
            'knowledge_query' => null,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function functionCallResponse(string $id, string $name, array $arguments): array
    {
        return [
            'candidates' => [[
                'content' => [
                    'role' => 'model',
                    'parts' => [['functionCall' => [
                        'id' => $id,
                        'name' => $name,
                        'args' => $arguments,
                    ]]],
                ],
                'finishReason' => 'STOP',
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function parallelFunctionCallsResponse(bool $invalidSecond = false): array
    {
        return [
            'candidates' => [[
                'content' => [
                        'role' => 'model',
                        'parts' => [
                        ['functionCall' => [
                            'id' => 'parallel-electricity',
                            'name' => 'obtener_estadisticas_movimientos',
                            'args' => ['periodo' => 'junio', 'metrica' => 'gastos', 'categoria' => 'electricidad'],
                        ]],
                        ['functionCall' => [
                            'id' => 'parallel-delivery',
                            'name' => 'obtener_estadisticas_movimientos',
                            'args' => [
                                'periodo' => 'junio',
                                'metrica' => $invalidSecond ? 'saldo' : 'gastos',
                                'categoria' => 'comida_domicilio',
                            ],
                        ]],
                    ],
                ],
                'finishReason' => 'STOP',
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function threeFinancialCallsResponse(): array
    {
        return [
            'candidates' => [[
                'content' => [
                    'role' => 'model',
                    'parts' => [
                        ['functionCall' => [
                            'id' => 'summary-call',
                            'name' => 'obtener_resumen_financiero',
                            'args' => ['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-30'],
                        ]],
                        ['functionCall' => [
                            'id' => 'statistics-call',
                            'name' => 'obtener_estadisticas_movimientos',
                            'args' => [
                                'fecha_inicio' => '2026-01-01',
                                'fecha_fin' => '2026-06-30',
                                'metrica' => 'gastos',
                            ],
                        ]],
                        ['functionCall' => [
                            'id' => 'movements-call',
                            'name' => 'obtener_movimientos',
                            'args' => [
                                'fecha_inicio' => '2026-01-01',
                                'fecha_fin' => '2026-06-30',
                                'tipo_movimiento' => 'gasto',
                                'orden' => 'cantidad',
                                'direccion' => 'desc',
                                'limite' => 5,
                            ],
                        ]],
                    ],
                ],
                'finishReason' => 'STOP',
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function textResponse(string $text): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [['text' => $text]]],
                'finishReason' => 'STOP',
            ]],
        ];
    }

    /**
     * @param array<array-key, mixed> $schema
     * @return array<array-key, mixed>
     */
    private function withoutAdditionalProperties(array $schema): array
    {
        unset($schema['additionalProperties']);

        foreach ($schema as $key => $value) {
            if (is_array($value)) {
                $schema[$key] = $this->withoutAdditionalProperties($value);
            }
        }

        return $schema;
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

    private function countGastos(int $usuarioId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM gastos WHERE usuario_id = :usuario_id');
        $stmt->execute([':usuario_id' => $usuarioId]);

        return (int) $stmt->fetchColumn();
    }
}

final class NumaFunctionCallingRecordingPdo extends \PDO
{
    /** @var array<int, string> */
    public array $preparedSql = [];

    public function __construct()
    {
        $config = require CONFIG_PATH . '/database.php';

        parent::__construct(
            "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4",
            $config['user'],
            $config['password'],
        );
        $this->setAttribute(self::ATTR_ERRMODE, self::ERRMODE_EXCEPTION);
    }

    /** @param array<mixed> $options */
    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return parent::prepare($query, $options);
    }
}

final class NumaFunctionCallingRecordingRegistry implements \NumaFinancialToolRegistryInterface
{
    public int $executions = 0;

    public function __construct(private readonly \NumaFinancialToolRegistryInterface $registry)
    {
    }

    public function names(): array
    {
        return $this->registry->names();
    }

    public function get(string $name): \NumaFinancialToolDefinition
    {
        return $this->registry->get($name);
    }

    public function validate(string $name, int $authenticatedUserId, array $arguments): array
    {
        return $this->registry->validate($name, $authenticatedUserId, $arguments);
    }

    public function execute(string $name, int $authenticatedUserId, array $arguments): array
    {
        ++$this->executions;

        return $this->registry->execute($name, $authenticatedUserId, $arguments);
    }
}
