<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/GeminiNumaProvider.php';

final class GeminiNumaProviderTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->managedEnvKeys() as $key) {
            $this->envBackup[$key] = $_ENV[$key] ?? null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->managedEnvKeys() as $key) {
            if ($this->envBackup[$key] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $this->envBackup[$key];
            }
        }

        parent::tearDown();
    }

    public function testConstruyeSolicitudSeguraYMapeaRespuestaValida(): void
    {
        $captured = [];
        $transport = function (string $url, array $headers, string $body, int $timeout) use (&$captured): array {
            $captured = [
                'url' => $url,
                'headers' => $headers,
                'body' => json_decode($body, true, 512, JSON_THROW_ON_ERROR),
                'timeout' => $timeout,
            ];

            return [
                'status' => 200,
                'body' => json_encode([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => 'Respuesta breve de Numa.',
                            ]],
                        ],
                        'finishReason' => 'STOP',
                    ]],
                    'usageMetadata' => [
                        'promptTokenCount' => 120,
                        'candidatesTokenCount' => 35,
                    ],
                ], JSON_UNESCAPED_UNICODE),
            ];
        };

        $provider = new \GeminiNumaProvider('server-api-key', 'gemini-model', 500, 20, 1, $transport);
        $response = $provider->respond(new \NumaRequest(
            '¿Cómo añado un movimiento?',
            'Instrucciones internas de Numa',
            $this->toolContext(),
            ['obtener_resumen_financiero']
        ));

        self::assertStringEndsWith('/models/gemini-model:generateContent', $captured['url']);
        self::assertContains('x-goog-api-key: server-api-key', $captured['headers']);
        self::assertSame(10, $captured['timeout']);
        self::assertSame(500, $captured['body']['generationConfig']['maxOutputTokens']);
        self::assertSame('low', $captured['body']['generationConfig']['thinkingConfig']['thinkingLevel']);
        self::assertSame('obtener_resumen_financiero', $captured['body']['tools'][0]['functionDeclarations'][0]['name']);
        self::assertSame(['fecha_inicio', 'fecha_fin'], $captured['body']['tools'][0]['functionDeclarations'][0]['parameters']['required']);
        self::assertArrayNotHasKey('additionalProperties', $captured['body']['tools'][0]['functionDeclarations'][0]['parameters']);
        self::assertSame('Instrucciones internas de Numa', $captured['body']['system_instruction']['parts'][0]['text']);
        self::assertStringContainsString('Mensaje actual del usuario:', $captured['body']['contents'][0]['parts'][0]['text']);
        self::assertStringContainsString('¿Cómo añado un movimiento?', $captured['body']['contents'][0]['parts'][0]['text']);
        self::assertSame('Respuesta breve de Numa.', $response->message());
        self::assertSame(120, $response->tokenUsage()->inputTokens());
        self::assertSame(35, $response->tokenUsage()->outputTokens());
    }

    public function testPermiteRespuestaEstructuradaJson(): void
    {
        $provider = new \GeminiNumaProvider('key', 'model', transport: fn (): array => [
            'status' => 200,
            'body' => json_encode([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => '{"intent":"producto","allowed":true}',
                        ]],
                    ],
                    'finishReason' => 'STOP',
                ]],
            ]),
        ]);

        $response = $provider->respond(new \NumaRequest('Pregunta'));

        self::assertSame(['intent' => 'producto', 'allowed' => true], $response->structuredData());
    }

    public function testRechazaCuerpoDeRespuestaExcesivoAntesDeDecodificarlo(): void
    {
        $provider = new \GeminiNumaProvider('key', 'model', maxResponseBodyBytes: 32, transport: static fn (): array => [
            'status' => 200,
            'body' => str_repeat('{', 33),
        ]);

        $this->expectException(\NumaProviderException::class);
        $this->expectExceptionMessage('NUMA_PROVIDER_INVALID_RESPONSE');

        $provider->respond(new \NumaRequest('Pregunta'));
    }

    public function testRechazaRespuestasBloqueadasTruncadasOVariasCandidatas(): void
    {
        foreach ([
            ['finishReason' => 'SAFETY'],
            ['finishReason' => 'MAX_TOKENS'],
            ['candidates' => [
                ['content' => ['parts' => [['text' => 'Primera respuesta.']]]],
                ['content' => ['parts' => [['text' => 'Segunda respuesta.']]]],
            ]],
        ] as $candidate) {
            $provider = new \GeminiNumaProvider('key', 'model', transport: static fn (): array => [
                'status' => 200,
                'body' => json_encode([
                    'candidates' => [$candidate],
                ], JSON_THROW_ON_ERROR),
            ]);

            if (isset($candidate['candidates'])) {
                $provider = new \GeminiNumaProvider('key', 'model', transport: static function () use ($candidate): array {
                    return [
                    'status' => 200,
                    'body' => json_encode($candidate, JSON_THROW_ON_ERROR),
                    ];
                });
            }

            try {
                $provider->respond(new \NumaRequest('Pregunta'));
                self::fail('Se esperaba rechazo de una respuesta no utilizable.');
            } catch (\NumaProviderException $exception) {
                self::assertSame('NUMA_PROVIDER_INVALID_RESPONSE', $exception->getMessage());
            }
        }
    }

    public function testRechazaRespuestaConContenidoSinFinishReason(): void
    {
        $provider = new \GeminiNumaProvider('key', 'model', transport: static fn (): array => [
            'status' => 200,
            'body' => json_encode([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Respuesta aparentemente válida.']]],
                ]],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->expectException(\NumaProviderException::class);
        $this->expectExceptionMessage('NUMA_PROVIDER_INVALID_RESPONSE');

        $provider->respond(new \NumaRequest('Pregunta'));
    }

    public function testRechazaTextoConFunctionCallSalidaExcesivaYContenidoInseguro(): void
    {
        $responses = [
            [
                'candidates' => [[
                    'content' => ['parts' => [
                        ['text' => 'Ya tengo la respuesta final.'],
                        ['functionCall' => ['name' => 'obtener_resumen_financiero', 'args' => []]],
                    ]],
                    'finishReason' => 'STOP',
                ]],
            ],
            [
                'candidates' => [
                    ['content' => ['parts' => [['text' => str_repeat('a', 9000)]]], 'finishReason' => 'STOP'],
                ],
            ],
            [
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Te recomiendo comprar acciones.']]], 'finishReason' => 'STOP'],
                ],
            ],
            [
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'La clave de API es confidencial.']]], 'finishReason' => 'STOP'],
                ],
            ],
        ];

        foreach ($responses as $body) {
            $provider = new \GeminiNumaProvider('key', 'model', transport: static fn () => [
                'status' => 200,
                'body' => json_encode($body, JSON_THROW_ON_ERROR),
            ]);

            try {
                $provider->respond(new \NumaRequest('Pregunta', '', $this->toolContext(), ['obtener_resumen_financiero']));
                self::fail('Se esperaba rechazo de una respuesta no utilizable.');
            } catch (\NumaProviderException $exception) {
                self::assertSame('NUMA_PROVIDER_INVALID_RESPONSE', $exception->getMessage());
            }
        }
    }

    public function testRechazaUsoDeSalidaPorEncimaDelLimite(): void
    {
        $provider = new \GeminiNumaProvider('key', 'model', transport: static fn (): array => [
            'status' => 200,
            'body' => json_encode([
                'candidates' => [['content' => ['parts' => [['text' => 'Respuesta.']]], 'finishReason' => 'STOP']],
                'usageMetadata' => ['candidatesTokenCount' => 521],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->expectException(\NumaProviderException::class);
        $this->expectExceptionMessage('NUMA_PROVIDER_INVALID_RESPONSE');

        $provider->respond(new \NumaRequest('Pregunta'));
    }

    public function testEnviaHistorialControladoConRolesDeGemini(): void
    {
        $captured = [];
        $provider = new \GeminiNumaProvider('key', 'model', transport: function (string $url, array $headers, string $body) use (&$captured): array {
            $captured = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            return [
                'status' => 200,
                'body' => json_encode([
                    'candidates' => [['content' => ['parts' => [['text' => 'Respuesta actual.']]], 'finishReason' => 'STOP']],
                ], JSON_THROW_ON_ERROR),
            ];
        });

        $provider->respond(new \NumaRequest(
            '¿Y el anterior?',
            'Prompt controlado',
            [],
            [],
            [
                ['role' => 'user', 'message' => 'Pregunta anterior'],
                ['role' => 'assistant', 'message' => 'Respuesta anterior'],
            ],
        ));

        self::assertSame(['user', 'model', 'user'], array_column($captured['contents'], 'role'));
        self::assertSame('Pregunta anterior', $captured['contents'][0]['parts'][0]['text']);
        self::assertSame('Respuesta anterior', $captured['contents'][1]['parts'][0]['text']);
        self::assertStringContainsString('¿Y el anterior?', $captured['contents'][2]['parts'][0]['text']);
    }

    public function testConvierteFunctionCallEnToolRequestYEnviaFunctionResponseEstructurado(): void
    {
        $requests = [];
        $provider = new \GeminiNumaProvider('key', 'model', transport: function (string $url, array $headers, string $body) use (&$requests): array {
            $requests[] = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            if (count($requests) === 1) {
                return [
                    'status' => 200,
                    'body' => json_encode([
                        'candidates' => [[
                            'content' => [
                                'role' => 'model',
                                'parts' => [[
                                    'functionCall' => [
                                        'id' => 'call-1',
                                        'name' => 'obtener_resumen_financiero',
                                        'args' => [
                                            'fecha_inicio' => '2026-07-01',
                                            'fecha_fin' => '2026-07-31',
                                        ],
                                    ],
                                    'thoughtSignature' => 'signature-1',
                                ]],
                            ],
                            'finishReason' => 'STOP',
                        ]],
                    ], JSON_THROW_ON_ERROR),
                ];
            }

            return [
                'status' => 200,
                'body' => json_encode([
                    'candidates' => [['content' => ['parts' => [['text' => 'En julio ingresaste 1200 € y gastaste 800 €.']]], 'finishReason' => 'STOP']],
                ], JSON_THROW_ON_ERROR),
            ];
        });

        $toolResponse = $provider->respond(new \NumaRequest(
            '¿Cuál es mi resumen financiero de julio?',
            '',
            $this->toolContext(),
            ['obtener_resumen_financiero']
        ));

        self::assertSame('obtener_resumen_financiero', $toolResponse->toolRequest()?->name());
        self::assertSame([
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
        ], $toolResponse->toolRequest()?->arguments());

        $finalResponse = $provider->respond(new \NumaRequest(
            '¿Cuál es mi resumen financiero de julio?',
            '',
            $this->toolContext([[
                'tool' => 'obtener_resumen_financiero',
                'periodo' => ['inicio' => '2026-07-01', 'fin' => '2026-07-31'],
                'ingresos' => 1200.0,
                'gastos' => 800.0,
            ]]),
            ['obtener_resumen_financiero']
        ));

        self::assertSame('En julio ingresaste 1200 € y gastaste 800 €.', $finalResponse->message());
        self::assertSame('model', $requests[1]['contents'][1]['role']);
        self::assertSame('call-1', $requests[1]['contents'][1]['parts'][0]['functionCall']['id']);
        self::assertSame('signature-1', $requests[1]['contents'][1]['parts'][0]['thoughtSignature']);
        self::assertSame('call-1', $requests[1]['contents'][2]['parts'][0]['functionResponse']['id']);
        self::assertSame('obtener_resumen_financiero', $requests[1]['contents'][2]['parts'][0]['functionResponse']['name']);
        self::assertSame(1200, $requests[1]['contents'][2]['parts'][0]['functionResponse']['response']['result']['ingresos']);
        self::assertStringNotContainsString('financial_tool_results', $requests[1]['contents'][0]['parts'][0]['text']);
    }

    public function testPermiteUnaSegundaToolDentroDelMismoIntercambio(): void
    {
        $requests = [];
        $provider = new \GeminiNumaProvider('key', 'model', transport: function (string $url, array $headers, string $body) use (&$requests): array {
            $requests[] = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $callNumber = count($requests);

            if ($callNumber <= 2) {
                return [
                    'status' => 200,
                    'body' => json_encode([
                        'candidates' => [[
                            'content' => [
                                'role' => 'model',
                                'parts' => [[
                                    'functionCall' => [
                                        'id' => 'call-' . $callNumber,
                                        'name' => 'obtener_resumen_financiero',
                                        'args' => [
                                            'fecha_inicio' => $callNumber === 1 ? '2026-07-01' : '2026-08-01',
                                            'fecha_fin' => $callNumber === 1 ? '2026-07-31' : '2026-08-31',
                                        ],
                                    ],
                                ]],
                            ],
                            'finishReason' => 'STOP',
                        ]],
                    ], JSON_THROW_ON_ERROR),
                ];
            }

            return [
                'status' => 200,
                'body' => json_encode([
                    'candidates' => [['content' => ['parts' => [['text' => 'Comparativa lista.']]], 'finishReason' => 'STOP']],
                ], JSON_THROW_ON_ERROR),
            ];
        });

        $first = $provider->respond(new \NumaRequest('Compara julio y agosto', '', $this->toolContext(), ['obtener_resumen_financiero']));
        $second = $provider->respond(new \NumaRequest('Compara julio y agosto', '', $this->toolContext([['tool' => 'obtener_resumen_financiero', 'ingresos' => 1200.0]]), ['obtener_resumen_financiero']));
        $final = $provider->respond(new \NumaRequest('Compara julio y agosto', '', $this->toolContext([
            ['tool' => 'obtener_resumen_financiero', 'ingresos' => 1200.0],
            ['tool' => 'obtener_resumen_financiero', 'ingresos' => 1300.0],
        ]), ['obtener_resumen_financiero']));

        self::assertSame('2026-07-01', $first->toolRequest()?->arguments()['fecha_inicio']);
        self::assertSame('2026-08-01', $second->toolRequest()?->arguments()['fecha_inicio']);
        self::assertSame('Comparativa lista.', $final->message());
        self::assertCount(5, $requests[2]['contents']);
        self::assertSame('call-1', $requests[2]['contents'][2]['parts'][0]['functionResponse']['id']);
        self::assertSame('call-2', $requests[2]['contents'][4]['parts'][0]['functionResponse']['id']);
        self::assertSame(1300, $requests[2]['contents'][4]['parts'][0]['functionResponse']['response']['result']['ingresos']);
    }

    public function testRechazaFunctionCallDesconocidoOMalformado(): void
    {
        $unknownProvider = new \GeminiNumaProvider('key', 'model', transport: fn (): array => [
            'status' => 200,
            'body' => json_encode([
                'candidates' => [['content' => ['parts' => [['functionCall' => ['name' => 'tool_desconocida', 'args' => []]]]], 'finishReason' => 'STOP']],
            ], JSON_THROW_ON_ERROR),
        ]);

        try {
            $unknownProvider->respond(new \NumaRequest('Pregunta', '', $this->toolContext(), ['obtener_resumen_financiero']));
            self::fail('Se esperaba rechazo de functionCall desconocido.');
        } catch (\NumaProviderException $exception) {
            self::assertSame('NUMA_PROVIDER_INVALID_RESPONSE', $exception->getMessage());
        }

        $malformedProvider = new \GeminiNumaProvider('key', 'model', transport: fn (): array => [
            'status' => 200,
            'body' => json_encode([
                'candidates' => [['content' => ['parts' => [['functionCall' => ['args' => ['fecha_inicio' => '2026-07-01']]]]], 'finishReason' => 'STOP']],
            ], JSON_THROW_ON_ERROR),
        ]);

        try {
            $malformedProvider->respond(new \NumaRequest('Pregunta', '', $this->toolContext(), ['obtener_resumen_financiero']));
            self::fail('Se esperaba rechazo de functionCall malformado.');
        } catch (\NumaProviderException $exception) {
            self::assertSame('NUMA_PROVIDER_INVALID_RESPONSE', $exception->getMessage());
        }
    }

    public function testReintentaUnaVezUnFalloTransitorioSeguro(): void
    {
        $calls = 0;
        $provider = new \GeminiNumaProvider('key', 'model', transport: function () use (&$calls): array {
            ++$calls;

            if ($calls === 1) {
                return ['status' => 503, 'body' => '{}'];
            }

            return [
                'status' => 200,
                'body' => json_encode([
                    'candidates' => [[
                        'content' => [
                            'parts' => [['text' => 'Disponible de nuevo.']],
                        ],
                        'finishReason' => 'STOP',
                    ]],
                ]),
            ];
        });

        $response = $provider->respond(new \NumaRequest('Pregunta'));

        self::assertSame(2, $calls);
        self::assertSame('Disponible de nuevo.', $response->message());
    }

    public function testComparteElUnicoReintentoYElTimeoutConLaInteraccion(): void
    {
        $transportCalls = 0;
        $timeouts = [];
        $consumption = new class implements \NumaProviderConsumptionInterface, \NumaInteractionBudgetInterface {
            public int $calls = 0;
            public int $retries = 0;

            public function iniciarLlamada(): void
            {
                ++$this->calls;
            }

            public function registrarTokens(\NumaTokenUsage $usage): void
            {
            }

            public function timeoutForCall(int $configuredTimeoutSeconds): int
            {
                return 2;
            }

            public function allowTransientRetry(): bool
            {
                if ($this->retries >= 1) {
                    return false;
                }

                ++$this->retries;

                return true;
            }
        };
        $provider = new \GeminiNumaProvider(
            'key',
            'model',
            transport: function (string $url, array $headers, string $body, int $timeout) use (&$transportCalls, &$timeouts): array {
                ++$transportCalls;
                $timeouts[] = $timeout;

                if ($transportCalls === 1 || $transportCalls === 3) {
                    return ['status' => 503, 'body' => '{}'];
                }

                return [
                    'status' => 200,
                    'body' => json_encode([
                        'candidates' => [['content' => ['parts' => [['text' => 'Respuesta válida.']]], 'finishReason' => 'STOP']],
                    ], JSON_THROW_ON_ERROR),
                ];
            },
            consumption: $consumption,
        );

        self::assertSame('Respuesta válida.', $provider->respond(new \NumaRequest('Primera consulta'))->message());

        try {
            $provider->respond(new \NumaRequest('Segunda consulta'));
            self::fail('La segunda llamada transitoria no debe reutilizar el reintento.');
        } catch (\NumaProviderException $exception) {
            self::assertSame('NUMA_PROVIDER_UNAVAILABLE', $exception->providerError()->safeCode());
        }

        self::assertSame(3, $transportCalls);
        self::assertSame(3, $consumption->calls);
        self::assertSame(1, $consumption->retries);
        self::assertSame([2, 2, 2], $timeouts);
    }

    public function testNoReintentaErrorDeAutenticacion(): void
    {
        $calls = 0;
        $provider = new \GeminiNumaProvider('key', 'model', transport: function () use (&$calls): array {
            ++$calls;

            return ['status' => 401, 'body' => '{"error":{"message":"secret technical body"}}'];
        });

        try {
            $provider->respond(new \NumaRequest('Pregunta sensible'));
            self::fail('Se esperaba una excepcion de proveedor.');
        } catch (\NumaProviderException $exception) {
            self::assertSame(1, $calls);
            self::assertSame('NUMA_PROVIDER_AUTH_ERROR', $exception->getMessage());
            self::assertSame(\NumaProviderError::AUTHENTICATION, $exception->providerError()->type());
        }
    }

    public function testNoReintentaJsonInvalido(): void
    {
        $calls = 0;
        $provider = new \GeminiNumaProvider('key', 'model', transport: function () use (&$calls): array {
            ++$calls;

            return ['status' => 200, 'body' => '{invalid-json'];
        });

        try {
            $provider->respond(new \NumaRequest('Pregunta'));
            self::fail('Se esperaba una excepcion de proveedor.');
        } catch (\NumaProviderException $exception) {
            self::assertSame(1, $calls);
            self::assertSame('NUMA_PROVIDER_INVALID_RESPONSE', $exception->getMessage());
        }
    }

    public function testNoReintentaCuotaAgotada(): void
    {
        $calls = 0;
        $provider = new \GeminiNumaProvider('key', 'model', transport: function () use (&$calls): array {
            ++$calls;

            return ['status' => 429, 'body' => '{"error":{"status":"RESOURCE_EXHAUSTED"}}'];
        });

        try {
            $provider->respond(new \NumaRequest('Pregunta'));
            self::fail('Se esperaba una excepcion de proveedor.');
        } catch (\NumaProviderException $exception) {
            self::assertSame(1, $calls);
            self::assertSame('NUMA_PROVIDER_QUOTA_EXCEEDED', $exception->getMessage());
            self::assertSame(\NumaProviderError::QUOTA, $exception->providerError()->type());
        }
    }

    public function testNoReintentaRateLimit(): void
    {
        $calls = 0;
        $provider = new \GeminiNumaProvider('key', 'model', transport: function () use (&$calls): array {
            ++$calls;

            return ['status' => 429, 'body' => '{"error":{"message":"too many requests"}}'];
        });

        try {
            $provider->respond(new \NumaRequest('Pregunta'));
            self::fail('Se esperaba una excepcion de proveedor.');
        } catch (\NumaProviderException $exception) {
            self::assertSame(1, $calls);
            self::assertSame('NUMA_PROVIDER_RATE_LIMITED', $exception->getMessage());
            self::assertSame(\NumaProviderError::RATE_LIMIT, $exception->providerError()->type());
        }
    }

    public function testRechazaProveedorNoSoportadoConErrorSeguro(): void
    {
        $_ENV['NUMA_PROVIDER'] = 'otro';
        $_ENV['NUMA_API_KEY'] = 'key';
        $_ENV['NUMA_MODEL'] = 'model';

        $this->expectException(\NumaProviderException::class);
        $this->expectExceptionMessage('NUMA_CONFIGURATION_ERROR');

        \NumaProviderFactory::fromEnvironment(fn (): array => ['status' => 200, 'body' => '{}']);
    }

    public function testFactoryInyectaPromptBaseComoSystemInstruction(): void
    {
        $_ENV['NUMA_PROVIDER'] = 'gemini';
        $_ENV['NUMA_API_KEY'] = 'key';
        $_ENV['NUMA_MODEL'] = 'model';

        $captured = [];
        $consumption = new class implements \NumaProviderConsumptionInterface {
            public int $calls = 0;

            public int $tokenRegistrations = 0;

            public function iniciarLlamada(): void
            {
                ++$this->calls;
            }

            public function registrarTokens(\NumaTokenUsage $usage): void
            {
                ++$this->tokenRegistrations;
            }
        };
        $provider = \NumaProviderFactory::fromEnvironment(function (string $url, array $headers, string $body, int $timeout) use (&$captured): array {
            $captured = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            return [
                'status' => 200,
                'body' => json_encode([
                    'candidates' => [[
                        'content' => [
                            'parts' => [['text' => 'Respuesta breve de Numa.']],
                        ],
                        'finishReason' => 'STOP',
                    ]],
                ]),
            ];
        }, $consumption);

        $provider->respond(new \NumaRequest('Pregunta', 'Instruccion no controlada'));

        $systemInstruction = $captured['system_instruction']['parts'][0]['text'] ?? '';
        self::assertStringContainsString('Eres Numa, la guia inteligente de BeneHom.', $systemInstruction);
        self::assertStringContainsString('No actues como asistente generalista.', $systemInstruction);
        self::assertStringNotContainsString('Instruccion no controlada', $systemInstruction);
        self::assertSame(1, $consumption->calls);
        self::assertSame(1, $consumption->tokenRegistrations);
    }

    public function testRechazaClaveOModeloAusente(): void
    {
        $this->expectException(\NumaProviderException::class);
        $this->expectExceptionMessage('NUMA_CONFIGURATION_ERROR');

        new \GeminiNumaProvider('', 'model');
    }

    public function testBloqueaElTransporteRealDuranteTestsAutomatizados(): void
    {
        $provider = new \GeminiNumaProvider('key', 'model');

        try {
            $provider->respond(new \NumaRequest('Pregunta de prueba'));
            self::fail('El transporte real no debe iniciarse durante los tests automatizados.');
        } catch (\NumaProviderException $exception) {
            self::assertSame('NUMA_CONFIGURATION_ERROR', $exception->getMessage());
        }
    }

    public function testFactoryAplicaFronteraDeDatosAntesDelTransporte(): void
    {
        $_ENV['NUMA_PROVIDER'] = 'gemini';
        $_ENV['NUMA_API_KEY'] = 'key';
        $_ENV['NUMA_MODEL'] = 'model';

        $calls = 0;
        $provider = \NumaProviderFactory::fromEnvironment(function () use (&$calls): array {
            $calls++;

            return ['status' => 200, 'body' => '{}'];
        });

        try {
            $provider->respond(new \NumaRequest(
                'Pregunta',
                '',
                [['type' => 'financial_tool_results', 'items' => [['usuario_id' => 7]]]],
            ));
            self::fail('Se esperaba que la frontera de datos rechazara la solicitud.');
        } catch (\NumaProviderException $exception) {
            self::assertSame('NUMA_PROVIDER_INVALID_RESPONSE', $exception->getMessage());
            self::assertSame(0, $calls);
        }
    }

    /**
     * @return array<int, string>
     */
    private function managedEnvKeys(): array
    {
        return [
            'NUMA_PROVIDER',
            'NUMA_API_KEY',
            'NUMA_MODEL',
            'NUMA_MAX_OUTPUT_TOKENS',
            'NUMA_PROVIDER_TIMEOUT_SECONDS',
            'NUMA_MAX_TRANSIENT_RETRIES',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $toolResults
     * @return array<int, array<string, mixed>>
     */
    private function toolContext(array $toolResults = []): array
    {
        $context = [[
            'type' => 'available_financial_tools',
            'items' => [[
                'name' => 'obtener_resumen_financiero',
                'description' => 'Devuelve totales agregados de ingresos y gastos de un periodo.',
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'fecha_inicio' => ['type' => 'string', 'format' => 'date'],
                        'fecha_fin' => ['type' => 'string', 'format' => 'date'],
                    ],
                ],
                'required' => ['fecha_inicio', 'fecha_fin'],
                'allowed_values' => [],
                'result_limit' => ['max_items' => 1],
            ]],
        ]];

        if ($toolResults !== []) {
            $context[] = [
                'type' => 'financial_tool_results',
                'items' => $toolResults,
            ];
        }

        return $context;
    }
}
