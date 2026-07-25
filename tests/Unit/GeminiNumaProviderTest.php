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
            [['title' => 'Movimientos', 'content' => 'Contexto controlado']],
            ['obtener_resumen_financiero']
        ));

        self::assertStringEndsWith('/models/gemini-model:generateContent', $captured['url']);
        self::assertContains('x-goog-api-key: server-api-key', $captured['headers']);
        self::assertSame(10, $captured['timeout']);
        self::assertSame(220, $captured['body']['generationConfig']['maxOutputTokens']);
        self::assertSame('low', $captured['body']['generationConfig']['thinkingConfig']['thinkingLevel']);
        self::assertArrayNotHasKey('tools', $captured['body']);
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
                ]],
            ]),
        ]);

        $response = $provider->respond(new \NumaRequest('Pregunta'));

        self::assertSame(['intent' => 'producto', 'allowed' => true], $response->structuredData());
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
                    ]],
                ]),
            ];
        });

        $response = $provider->respond(new \NumaRequest('Pregunta'));

        self::assertSame(2, $calls);
        self::assertSame('Disponible de nuevo.', $response->message());
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
}
