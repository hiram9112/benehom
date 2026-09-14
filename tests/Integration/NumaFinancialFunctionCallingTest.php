<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/services/GeminiNumaProvider.php';
require_once APP_PATH . '/services/NumaService.php';

final class NumaFinancialFunctionCallingTest extends IntegrationTestCase
{
    private ?string $enabledBefore = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enabledBefore = $_ENV['NUMA_ENABLED'] ?? null;
        $_ENV['NUMA_ENABLED'] = 'true';
    }

    protected function tearDown(): void
    {
        if ($this->enabledBefore === null) {
            unset($_ENV['NUMA_ENABLED']);
        } else {
            $_ENV['NUMA_ENABLED'] = $this->enabledBefore;
        }
        parent::tearDown();
    }

    public function testFunctionCallingAceptaUnaSeleccionTemporalValidaSinReleerElMensaje(): void
    {
        $user = $this->crearUsuario('numa-canonical-function@example.test');
        $userId = (int) $user['id'];
        $statement = $this->db->prepare('INSERT INTO gastos (usuario_id, tipo, categoria, cantidad, fecha) VALUES (:usuario_id, :tipo, :categoria, :cantidad, :fecha)');
        $statement->execute([
            ':usuario_id' => $userId,
            ':tipo' => 'esencial',
            ':categoria' => 'electricidad',
            ':cantidad' => '42.50',
            ':fecha' => '2026-07-03',
        ]);

        $requests = [];
        $responses = [
            $this->textResponse(json_encode([
                'intent' => 'datos_usuario',
                'allowed' => true,
                'reason' => 'user_data',
                'needs_clarification' => false,
                'knowledge_query' => null,
            ], JSON_THROW_ON_ERROR)),
            $this->functionCallResponse('financial-call', [
                'periodos' => [['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']],
                'selectores' => [['categoria' => 'electricidad']],
            ]),
            $this->textResponse('He consultado los datos solicitados.'),
        ];
        $index = 0;
        $providerFactory = function (?\NumaProviderConsumptionInterface $consumption) use (&$requests, &$responses, &$index): \NumaProviderInterface {
            return new \GeminiNumaProvider(
                'test-key',
                'gemini-test-model',
                transport: function (string $url, array $headers, string $body) use (&$requests, &$responses, &$index): array {
                    $requests[] = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

                    return ['status' => 200, 'body' => json_encode($responses[$index++], JSON_THROW_ON_ERROR)];
                },
                consumption: $consumption,
            );
        };
        $service = new \NumaService(
            new \NumaUso($this->db),
            new \NumaLocalScopeClassifier(),
            $providerFactory,
            static fn (): array => [],
            new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db)),
            new class implements \NumaGlobalAvailabilityInterface {
                public function assertAvailable(): void
                {
                }
            },
            new \NumaPeriodResolver(new \DateTimeImmutable('2026-08-12', new \DateTimeZone('Europe/Madrid'))),
        );

        $result = $service->answer($userId, '¿Cuánto pagué de electricidad?');

        self::assertSame('He consultado los datos solicitados.', $result->toArray()['message']);
        self::assertSame('consultar_datos_financieros', $requests[1]['tools'][0]['functionDeclarations'][0]['name']);
        self::assertSame(['periodos'], $requests[1]['tools'][0]['functionDeclarations'][0]['parameters']['required']);
        self::assertSame('ANY', $requests[1]['toolConfig']['functionCallingConfig']['mode']);
        self::assertSame('financial-call', $requests[2]['contents'][2]['parts'][0]['functionResponse']['id']);
        self::assertSame('consultar_datos_financieros', $requests[2]['contents'][2]['parts'][0]['functionResponse']['name']);
        self::assertSame('42.50', $requests[2]['contents'][2]['parts'][0]['functionResponse']['response']['result']['meses'][0]['gastos']['importe']);
    }

    public function testGeminiDerivaUnRankingDeHojasConCoberturaParcial(): void
    {
        $user = $this->crearUsuario('numa-derived-ranking@example.test');
        $userId = (int) $user['id'];
        $statement = $this->db->prepare('INSERT INTO gastos (usuario_id, tipo, categoria, cantidad, fecha) VALUES (:usuario_id, :tipo, :categoria, :cantidad, :fecha)');
        foreach ([
            ['alquiler_hipoteca', '700.00'],
            ['compra_basica', '400.00'],
            ['transporte_publico', '150.00'],
        ] as [$category, $amount]) {
            $statement->execute([
                ':usuario_id' => $userId,
                ':tipo' => 'esencial',
                ':categoria' => $category,
                ':cantidad' => $amount,
                ':fecha' => '2026-07-03',
            ]);
        }

        $requests = [];
        $responses = [
            $this->classificationResponse(),
            $this->functionCallResponse('derived-ranking', [
                'periodos' => [['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']],
                'selectores' => [
                    ['categoria' => 'alquiler_hipoteca'],
                    ['categoria' => 'compra_basica'],
                    ['categoria' => 'transporte_publico'],
                ],
            ]),
            $this->textResponse('Entre las categorías consultadas, Vivienda fue la mayor con 700 EUR, seguida de Alimentación con 400 EUR y Transporte con 150 EUR. Esos 1.250 EUR corresponden solo a las 3 categorías consultadas, no al total de gastos.'),
        ];
        $index = 0;
        $providerFactory = function (?\NumaProviderConsumptionInterface $consumption) use (&$requests, &$responses, &$index): \NumaProviderInterface {
            return new \GeminiNumaProvider(
                'test-key',
                'gemini-test-model',
                transport: function (string $url, array $headers, string $body) use (&$requests, &$responses, &$index): array {
                    $requests[] = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

                    return ['status' => 200, 'body' => json_encode($responses[$index++], JSON_THROW_ON_ERROR)];
                },
                consumption: $consumption,
            );
        };
        $service = new \NumaService(
            new \NumaUso($this->db),
            new \NumaLocalScopeClassifier(),
            $providerFactory,
            static fn (): array => [],
            new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db)),
            new class implements \NumaGlobalAvailabilityInterface {
                public function assertAvailable(): void
                {
                }
            },
            new \NumaPeriodResolver(new \DateTimeImmutable('2026-08-12', new \DateTimeZone('Europe/Madrid'))),
        );

        $result = $service->answer($userId, 'Ordena mis gastos de julio por categoría.');

        self::assertSame(
            'Entre las categorías consultadas, Vivienda fue la mayor con 700 EUR, seguida de Alimentación con 400 EUR y Transporte con 150 EUR. Esos 1.250 EUR corresponden solo a las 3 categorías consultadas, no al total de gastos.',
            $result->toArray()['message'],
        );
        $financialResult = $requests[2]['contents'][2]['parts'][0]['functionResponse']['response']['result'];
        self::assertSame('1250.00', $financialResult['meses'][0]['gastos']['importe']);
        self::assertSame(
            ['completa' => false, 'tipos_consultados' => 1, 'tipos_totales' => 2],
            $financialResult['meses'][0]['gastos']['cobertura'],
        );
        self::assertSame(
            ['completa' => false, 'categorias_consultadas' => 1, 'categorias_totales' => 6],
            $financialResult['meses'][0]['gastos']['tipos'][0]['areas'][0]['cobertura'],
        );
        self::assertSame('700.00', $financialResult['meses'][0]['gastos']['tipos'][0]['areas'][0]['categorias'][0]['importe']);
        self::assertSame('400.00', $financialResult['meses'][0]['gastos']['tipos'][0]['areas'][1]['categorias'][0]['importe']);
        self::assertSame('150.00', $financialResult['meses'][0]['gastos']['tipos'][0]['areas'][2]['categorias'][0]['importe']);
    }

    public function testGeminiDerivaLaMediaDeVariasHojasMensuales(): void
    {
        $user = $this->crearUsuario('numa-derived-average@example.test');
        $userId = (int) $user['id'];
        $statement = $this->db->prepare('INSERT INTO gastos (usuario_id, tipo, categoria, cantidad, fecha) VALUES (:usuario_id, :tipo, :categoria, :cantidad, :fecha)');
        foreach ([['2026-01-03', '300.00'], ['2026-02-03', '500.00'], ['2026-03-03', '400.00']] as [$date, $amount]) {
            $statement->execute([
                ':usuario_id' => $userId,
                ':tipo' => 'esencial',
                ':categoria' => 'electricidad',
                ':cantidad' => $amount,
                ':fecha' => $date,
            ]);
        }

        $requests = [];
        $responses = [
            $this->classificationResponse(),
            $this->functionCallResponse('derived-average', [
                'periodos' => [['mes_inicio' => '2026-01', 'mes_fin' => '2026-03']],
                'selectores' => [['categoria' => 'electricidad']],
            ]),
            $this->textResponse('La media de electricidad entre enero y marzo, usando los 3 meses consultados, es de 400 EUR.'),
        ];
        $index = 0;
        $providerFactory = function (?\NumaProviderConsumptionInterface $consumption) use (&$requests, &$responses, &$index): \NumaProviderInterface {
            return new \GeminiNumaProvider(
                'test-key',
                'gemini-test-model',
                transport: function (string $url, array $headers, string $body) use (&$requests, &$responses, &$index): array {
                    $requests[] = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

                    return ['status' => 200, 'body' => json_encode($responses[$index++], JSON_THROW_ON_ERROR)];
                },
                consumption: $consumption,
            );
        };
        $service = new \NumaService(
            new \NumaUso($this->db),
            new \NumaLocalScopeClassifier(),
            $providerFactory,
            static fn (): array => [],
            new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db)),
            new class implements \NumaGlobalAvailabilityInterface {
                public function assertAvailable(): void
                {
                }
            },
            new \NumaPeriodResolver(new \DateTimeImmutable('2026-08-12', new \DateTimeZone('Europe/Madrid'))),
        );

        $result = $service->answer($userId, '¿Cuál fue la media de electricidad entre enero y marzo?');

        self::assertSame(
            'La media de electricidad entre enero y marzo, usando los 3 meses consultados, es de 400 EUR.',
            $result->toArray()['message'],
        );
        $months = $requests[2]['contents'][2]['parts'][0]['functionResponse']['response']['result']['meses'];
        self::assertSame(['2026-01', '2026-02', '2026-03'], array_column($months, 'mes'));
        self::assertSame(['300.00', '500.00', '400.00'], array_column(array_column($months, 'gastos'), 'importe'));
    }

    public function testFunctionCallingConservaElAnclaExplicitaDelMismoMensaje(): void
    {
        $user = $this->crearUsuario('numa-current-message-anchor@example.test');
        $userId = (int) $user['id'];
        $statement = $this->db->prepare('INSERT INTO gastos (usuario_id, tipo, categoria, cantidad, fecha) VALUES (:usuario_id, :tipo, :categoria, :cantidad, :fecha)');
        foreach ([['10.00', '2025-12-03'], ['20.00', '2026-01-03']] as [$amount, $date]) {
            $statement->execute([
                ':usuario_id' => $userId,
                ':tipo' => 'esencial',
                ':categoria' => 'electricidad',
                ':cantidad' => $amount,
                ':fecha' => $date,
            ]);
        }

        $responses = [
            $this->classificationResponse(),
            $this->functionCallResponse('current-message-anchor', [
                'periodos' => [
                    ['mes_inicio' => '2026-01', 'mes_fin' => '2026-01'],
                    ['mes_inicio' => '2025-12', 'mes_fin' => '2025-12'],
                ],
                'selectores' => [['categoria' => 'electricidad']],
            ]),
            $this->textResponse('He comparado enero con diciembre.'),
        ];
        $index = 0;
        $providerFactory = function (?\NumaProviderConsumptionInterface $consumption) use (&$responses, &$index): \NumaProviderInterface {
            return new \GeminiNumaProvider(
                'test-key',
                'gemini-test-model',
                transport: function () use (&$responses, &$index): array {
                    return ['status' => 200, 'body' => json_encode($responses[$index++], JSON_THROW_ON_ERROR)];
                },
                consumption: $consumption,
            );
        };
        $service = new \NumaService(
            new \NumaUso($this->db),
            new \NumaLocalScopeClassifier(),
            $providerFactory,
            static fn (): array => [],
            new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db)),
            new class implements \NumaGlobalAvailabilityInterface {
                public function assertAvailable(): void
                {
                }
            },
            new \NumaPeriodResolver(new \DateTimeImmutable('2026-09-12', new \DateTimeZone('Europe/Madrid'))),
        );

        $result = $service->answer(
            $userId,
            'Compara enero de 2026 con el mes anterior.',
            dashboardMonth: '2026-06',
        );

        self::assertSame([
            ['mes_inicio' => '2026-01', 'mes_fin' => '2026-01'],
            ['mes_inicio' => '2025-12', 'mes_fin' => '2025-12'],
        ], $result->periods());
    }

    public function testFunctionCallingReutilizaVariosPeriodosDeLaConversacionActual(): void
    {
        $user = $this->crearUsuario('numa-conversation-periods@example.test');
        $userId = (int) $user['id'];
        $statement = $this->db->prepare('INSERT INTO gastos (usuario_id, tipo, categoria, cantidad, fecha) VALUES (:usuario_id, :tipo, :categoria, :cantidad, :fecha)');
        $statement->execute([
            ':usuario_id' => $userId,
            ':tipo' => 'esencial',
            ':categoria' => 'electricidad',
            ':cantidad' => '20.00',
            ':fecha' => '2026-06-03',
        ]);
        $statement->execute([
            ':usuario_id' => $userId,
            ':tipo' => 'esencial',
            ':categoria' => 'electricidad',
            ':cantidad' => '30.00',
            ':fecha' => '2026-07-03',
        ]);

        $responses = [
            $this->classificationResponse(),
            $this->functionCallResponse('conversation-periods', [
                'periodos' => [
                    ['mes_inicio' => '2026-06', 'mes_fin' => '2026-06'],
                    ['mes_inicio' => '2026-07', 'mes_fin' => '2026-07'],
                ],
                'selectores' => [['categoria' => 'electricidad']],
            ]),
            $this->textResponse('He consultado ambos meses.'),
        ];
        $index = 0;
        $providerFactory = function (?\NumaProviderConsumptionInterface $consumption) use (&$responses, &$index): \NumaProviderInterface {
            return new \GeminiNumaProvider(
                'test-key',
                'gemini-test-model',
                transport: function () use (&$responses, &$index): array {
                    return ['status' => 200, 'body' => json_encode($responses[$index++], JSON_THROW_ON_ERROR)];
                },
                consumption: $consumption,
            );
        };
        $service = new \NumaService(
            new \NumaUso($this->db),
            new \NumaLocalScopeClassifier(),
            $providerFactory,
            static fn (): array => [],
            new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db)),
            new class implements \NumaGlobalAvailabilityInterface {
                public function assertAvailable(): void
                {
                }
            },
            new \NumaPeriodResolver(new \DateTimeImmutable('2026-08-12', new \DateTimeZone('Europe/Madrid'))),
        );

        $result = $service->answer($userId, 'Compáralos.', [
            ['role' => 'user', 'message' => 'Consulta junio y julio.'],
            ['role' => 'assistant', 'message' => 'He consultado ambos meses.', 'periods' => [
                ['mes_inicio' => '2026-06', 'mes_fin' => '2026-06'],
                ['mes_inicio' => '2026-07', 'mes_fin' => '2026-07'],
            ]],
        ]);

        self::assertSame('He consultado ambos meses.', $result->toArray()['message']);
        self::assertSame([
            ['mes_inicio' => '2026-06', 'mes_fin' => '2026-06'],
            ['mes_inicio' => '2026-07', 'mes_fin' => '2026-07'],
        ], $result->periods());
    }

    public function testGeminiRecibeLosPeriodosAsociadosACadaIntercambio(): void
    {
        $user = $this->crearUsuario('numa-conversation-period-index@example.test');
        $userId = (int) $user['id'];
        $statement = $this->db->prepare('INSERT INTO gastos (usuario_id, tipo, categoria, cantidad, fecha) VALUES (:usuario_id, :tipo, :categoria, :cantidad, :fecha)');
        foreach ([['10.00', '2026-01-03'], ['20.00', '2026-03-03'], ['30.00', '2026-06-03']] as [$amount, $date]) {
            $statement->execute([
                ':usuario_id' => $userId,
                ':tipo' => 'esencial',
                ':categoria' => 'electricidad',
                ':cantidad' => $amount,
                ':fecha' => $date,
            ]);
        }

        $requests = [];
        $responses = [
            $this->classificationResponse(),
            $this->functionCallResponse('period-index', [
                'periodos' => [['mes_inicio' => '2026-03', 'mes_fin' => '2026-03']],
                'selectores' => [['categoria' => 'electricidad']],
            ]),
            $this->textResponse('He consultado marzo.'),
        ];
        $index = 0;
        $providerFactory = function (?\NumaProviderConsumptionInterface $consumption) use (&$requests, &$responses, &$index): \NumaProviderInterface {
            return new \GeminiNumaProvider(
                'test-key',
                'gemini-test-model',
                transport: function (string $url, array $headers, string $body) use (&$requests, &$responses, &$index): array {
                    $requests[] = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

                    return ['status' => 200, 'body' => json_encode($responses[$index++], JSON_THROW_ON_ERROR)];
                },
                consumption: $consumption,
            );
        };
        $service = new \NumaService(
            new \NumaUso($this->db),
            new \NumaLocalScopeClassifier(),
            $providerFactory,
            static fn (): array => [],
            new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db)),
            new class implements \NumaGlobalAvailabilityInterface {
                public function assertAvailable(): void
                {
                }
            },
            new \NumaPeriodResolver(new \DateTimeImmutable('2026-08-12', new \DateTimeZone('Europe/Madrid'))),
        );

        $result = $service->answer($userId, 'Compáralos.', [
            ['role' => 'user', 'message' => 'Consulta enero y marzo.'],
            ['role' => 'assistant', 'message' => 'He consultado ambos meses.', 'periods' => [
                ['mes_inicio' => '2026-01', 'mes_fin' => '2026-01'],
                ['mes_inicio' => '2026-03', 'mes_fin' => '2026-03'],
            ]],
            ['role' => 'user', 'message' => 'Consulta también junio.'],
            ['role' => 'assistant', 'message' => 'He consultado junio.', 'periods' => [
                ['mes_inicio' => '2026-06', 'mes_fin' => '2026-06'],
            ]],
        ]);

        self::assertSame([
            ['mes_inicio' => '2026-03', 'mes_fin' => '2026-03'],
        ], $result->periods());
        self::assertSame('2026-03', $requests[2]['contents'][6]['parts'][0]['functionResponse']['response']['result']['meses'][0]['mes']);
        self::assertStringContainsString(
            '[Periodos asociados al intercambio: 2026-01 a 2026-01; 2026-03 a 2026-03]',
            $requests[1]['contents'][1]['parts'][0]['text'],
        );
    }

    public function testFunctionCallingEmparejaLlamadasParalelasPorIdAunqueLosResultadosLleguenInvertidos(): void
    {
        $user = $this->crearUsuario('numa-canonical-parallel@example.test');
        $userId = (int) $user['id'];
        $statement = $this->db->prepare('INSERT INTO gastos (usuario_id, tipo, categoria, cantidad, fecha) VALUES (:usuario_id, :tipo, :categoria, :cantidad, :fecha)');
        $statement->execute([
            ':usuario_id' => $userId,
            ':tipo' => 'esencial',
            ':categoria' => 'electricidad',
            ':cantidad' => '10.00',
            ':fecha' => '2026-07-03',
        ]);
        $statement->execute([
            ':usuario_id' => $userId,
            ':tipo' => 'flexible',
            ':categoria' => 'comida_domicilio',
            ':cantidad' => '20.00',
            ':fecha' => '2026-07-04',
        ]);

        $requests = [];
        $parsedToolRequests = [];
        $reorderedToolResultIds = [];
        $calls = [
            ['id' => 'electricidad', 'args' => [
                'periodos' => [['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']],
                'selectores' => [['categoria' => 'electricidad']],
            ]],
            ['id' => 'domicilio', 'args' => [
                'periodos' => [['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']],
                'selectores' => [['categoria' => 'comida_domicilio']],
            ]],
        ];
        $responses = [
            $this->classificationResponse(),
            $this->functionCallsResponse($calls),
            $this->textResponse('He consultado ambas categorías.'),
        ];
        $index = 0;
        $providerFactory = function (?\NumaProviderConsumptionInterface $consumption) use (&$requests, &$responses, &$index, &$parsedToolRequests, &$reorderedToolResultIds): \NumaProviderInterface {
            $provider = new \GeminiNumaProvider(
                'test-key',
                'gemini-test-model',
                transport: function (string $url, array $headers, string $body) use (&$requests, &$responses, &$index): array {
                    $requests[] = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

                    return ['status' => 200, 'body' => json_encode($responses[$index++], JSON_THROW_ON_ERROR)];
                },
                consumption: $consumption,
            );

            return new class(
                $provider,
                static function (array $toolRequests) use (&$parsedToolRequests): void {
                    $parsedToolRequests = array_map(static fn (\NumaToolRequest $request): array => [
                        'id' => $request->id(),
                        'name' => $request->name(),
                        'arguments' => $request->arguments(),
                    ], $toolRequests);
                },
                static function (\NumaRequest $request) use (&$reorderedToolResultIds): \NumaRequest {
                    $context = $request->context();
                    foreach ($context as &$contextItem) {
                        if (($contextItem['type'] ?? null) !== 'financial_tool_results'
                            || !is_array($contextItem['items'] ?? null)
                        ) {
                            continue;
                        }

                        $contextItem['items'] = array_reverse($contextItem['items']);
                        $reorderedToolResultIds = array_column($contextItem['items'], 'call_id');
                    }
                    unset($contextItem);

                    return new \NumaRequest(
                        $request->message(),
                        $request->systemInstruction(),
                        $context,
                        $request->availableTools(),
                        $request->history(),
                        $request->responseSchema(),
                        $request->functionCallingMode(),
                        $request->maxOutputTokens(),
                    );
                },
            ) implements \NumaProviderInterface {
                public function __construct(
                    private readonly \NumaProviderInterface $provider,
                    private readonly \Closure $observeToolRequests,
                    private readonly \Closure $reorderToolResults,
                ) {
                }

                public function respond(\NumaRequest $request): \NumaResponse
                {
                    $request = ($this->reorderToolResults)($request);
                    $response = $this->provider->respond($request);
                    if ($response->toolRequests() !== []) {
                        ($this->observeToolRequests)($response->toolRequests());
                    }

                    return $response;
                }
            };
        };
        $service = new \NumaService(
            new \NumaUso($this->db),
            new \NumaLocalScopeClassifier(),
            $providerFactory,
            static fn (): array => [],
            new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db)),
            new class implements \NumaGlobalAvailabilityInterface {
                public function assertAvailable(): void
                {
                }
            },
            new \NumaPeriodResolver(new \DateTimeImmutable('2026-08-12', new \DateTimeZone('Europe/Madrid'))),
        );

        self::assertSame('He consultado ambas categorías.', $service->answer($userId, 'Compara electricidad y comida a domicilio de julio.')->toArray()['message']);
        $parts = $requests[2]['contents'];
        self::assertSame([
            ['id' => 'electricidad', 'name' => 'consultar_datos_financieros', 'arguments' => $calls[0]['args']],
            ['id' => 'domicilio', 'name' => 'consultar_datos_financieros', 'arguments' => $calls[1]['args']],
        ], $parsedToolRequests);
        self::assertSame(['domicilio', 'electricidad'], $reorderedToolResultIds);
        self::assertSame('electricidad', $parts[1]['parts'][0]['functionCall']['id']);
        self::assertSame('domicilio', $parts[1]['parts'][1]['functionCall']['id']);
        self::assertSame('consultar_datos_financieros', $parts[1]['parts'][0]['functionCall']['name']);
        self::assertSame('consultar_datos_financieros', $parts[1]['parts'][1]['functionCall']['name']);
        self::assertSame($calls[0]['args'], $parts[1]['parts'][0]['functionCall']['args']);
        self::assertSame($calls[1]['args'], $parts[1]['parts'][1]['functionCall']['args']);
        self::assertSame('electricidad', $parts[2]['parts'][0]['functionResponse']['id']);
        self::assertSame('domicilio', $parts[2]['parts'][1]['functionResponse']['id']);
        self::assertSame('consultar_datos_financieros', $parts[2]['parts'][0]['functionResponse']['name']);
        self::assertSame('consultar_datos_financieros', $parts[2]['parts'][1]['functionResponse']['name']);
        self::assertSame('10.00', $parts[2]['parts'][0]['functionResponse']['response']['result']['meses'][0]['gastos']['importe']);
        self::assertSame('20.00', $parts[2]['parts'][1]['functionResponse']['response']['result']['meses'][0]['gastos']['importe']);
    }

    /** @return array<string, mixed> */
    private function classificationResponse(): array
    {
        return $this->textResponse(json_encode([
            'intent' => 'datos_usuario',
            'allowed' => true,
            'reason' => 'user_data',
            'needs_clarification' => false,
            'knowledge_query' => null,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    private function functionCallResponse(string $id, array $arguments): array
    {
        return [
            'candidates' => [[
                'content' => ['role' => 'model', 'parts' => [['functionCall' => [
                    'id' => $id,
                    'name' => 'consultar_datos_financieros',
                    'args' => $arguments,
                ]]]],
                'finishReason' => 'STOP',
            ]],
        ];
    }

    /** @param list<array{id:string,args:array<string,mixed>}> $calls @return array<string, mixed> */
    private function functionCallsResponse(array $calls): array
    {
        return [
            'candidates' => [[
                'content' => [
                    'role' => 'model',
                    'parts' => array_map(static fn (array $call): array => ['functionCall' => [
                        'id' => $call['id'],
                        'name' => 'consultar_datos_financieros',
                        'args' => $call['args'],
                    ]], $calls),
                ],
                'finishReason' => 'STOP',
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function textResponse(string $text): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $text]]], 'finishReason' => 'STOP']]];
    }
}
