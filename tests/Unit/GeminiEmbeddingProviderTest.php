<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/GeminiEmbeddingProvider.php';

final class GeminiEmbeddingProviderTest extends TestCase
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

    public function testConstruyeSolicitudDeEmbeddingYMapeaVectorValido(): void
    {
        $captured = [];
        $vector = array_fill(0, 768, 0.125);
        $transport = function (string $url, array $headers, string $body, int $timeout) use (&$captured, $vector): array {
            $captured = [
                'url' => $url,
                'headers' => $headers,
                'body' => json_decode($body, true, 512, JSON_THROW_ON_ERROR),
                'timeout' => $timeout,
            ];

            return [
                'status' => 200,
                'body' => json_encode([
                    'embedding' => [
                        'values' => $vector,
                    ],
                ]),
            ];
        };

        $provider = new \GeminiEmbeddingProvider('embedding-key', 'gemini-embedding-001', 768, 20, $transport);
        $embedding = $provider->embed('Como se anade un movimiento en BeneHom');

        self::assertStringEndsWith('/models/gemini-embedding-001:embedContent', $captured['url']);
        self::assertContains('x-goog-api-key: embedding-key', $captured['headers']);
        self::assertSame(10, $captured['timeout']);
        self::assertSame('SEMANTIC_SIMILARITY', $captured['body']['taskType']);
        self::assertSame(768, $captured['body']['output_dimensionality']);
        self::assertSame('Como se anade un movimiento en BeneHom', $captured['body']['content']['parts'][0]['text']);
        self::assertCount(768, $embedding);
        self::assertSame(0.125, $embedding[0]);
    }

    public function testAceptaRespuestaConListaDeEmbeddingsDocumentada(): void
    {
        $vector = array_fill(0, 768, 1);
        $provider = new \GeminiEmbeddingProvider('key', 'model', transport: fn (): array => [
            'status' => 200,
            'body' => json_encode([
                'embeddings' => [[
                    'values' => $vector,
                ]],
            ]),
        ]);

        $embedding = $provider->embed('Texto publico de BeneHom');

        self::assertCount(768, $embedding);
        self::assertSame(1.0, $embedding[0]);
    }

    public function testRechazaDimensionDistintaALaConfigurada(): void
    {
        $provider = new \GeminiEmbeddingProvider('key', 'model', transport: fn (): array => [
            'status' => 200,
            'body' => json_encode([
                'embedding' => [
                    'values' => array_fill(0, 767, 0.1),
                ],
            ]),
        ]);

        $this->expectException(\NumaProviderException::class);
        $this->expectExceptionMessage('NUMA_PROVIDER_INVALID_RESPONSE');

        $provider->embed('Texto publico de BeneHom');
    }

    public function testRechazaClaveModeloODimensionesInvalidas(): void
    {
        $this->expectException(\NumaProviderException::class);
        $this->expectExceptionMessage('NUMA_CONFIGURATION_ERROR');

        new \GeminiEmbeddingProvider('', 'gemini-embedding-001');
    }

    public function testMapeaErroresHttpSinExponerCuerpoTecnico(): void
    {
        $provider = new \GeminiEmbeddingProvider('key', 'model', transport: fn (): array => [
            'status' => 401,
            'body' => '{"error":{"message":"secret technical body"}}',
        ]);

        try {
            $provider->embed('Texto publico de BeneHom');
            self::fail('Se esperaba una excepcion de proveedor.');
        } catch (\NumaProviderException $exception) {
            self::assertSame('NUMA_PROVIDER_AUTH_ERROR', $exception->getMessage());
            self::assertSame(\NumaProviderError::AUTHENTICATION, $exception->providerError()->type());
        }
    }

    public function testFactoryUsaConfiguracionDeEmbeddings(): void
    {
        $_ENV['NUMA_EMBEDDING_PROVIDER'] = 'gemini';
        $_ENV['NUMA_EMBEDDING_API_KEY'] = 'embedding-key';
        $_ENV['NUMA_EMBEDDING_MODEL'] = 'gemini-embedding-001';
        $_ENV['NUMA_EMBEDDING_DIMENSIONS'] = '768';

        $captured = [];
        $provider = \NumaEmbeddingProviderFactory::fromEnvironment(function (string $url, array $headers, string $body, int $timeout) use (&$captured): array {
            $captured = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            return [
                'status' => 200,
                'body' => json_encode([
                    'embedding' => [
                        'values' => array_fill(0, 768, 0.2),
                    ],
                ]),
            ];
        });

        $embedding = $provider->embed('Texto publico de BeneHom');

        self::assertCount(768, $embedding);
        self::assertSame(768, $captured['output_dimensionality']);
    }

    public function testFactoryRechazaProveedorNoSoportado(): void
    {
        $_ENV['NUMA_EMBEDDING_PROVIDER'] = 'otro';
        $_ENV['NUMA_EMBEDDING_API_KEY'] = 'key';
        $_ENV['NUMA_EMBEDDING_MODEL'] = 'model';

        $this->expectException(\NumaProviderException::class);
        $this->expectExceptionMessage('NUMA_CONFIGURATION_ERROR');

        \NumaEmbeddingProviderFactory::fromEnvironment(fn (): array => ['status' => 200, 'body' => '{}']);
    }

    public function testEmbeddingMedidoConsumeUnaUnidadAntesDeLaLlamadaReal(): void
    {
        $provider = new class implements \NumaEmbeddingProviderInterface {
            public int $calls = 0;

            public function embed(string $text): array
            {
                $this->calls++;

                return [0.1, 0.2];
            }
        };
        $consumption = new class implements \NumaProviderConsumptionInterface {
            public int $calls = 0;

            public function iniciarLlamada(): void
            {
                $this->calls++;
            }

            public function registrarTokens(\NumaTokenUsage $usage): void
            {
            }
        };

        $embedding = (new \NumaMeteredEmbeddingProvider($provider, $consumption))->embed('Texto publico de BeneHom');

        self::assertSame([0.1, 0.2], $embedding);
        self::assertSame(1, $consumption->calls);
        self::assertSame(1, $provider->calls);
    }

    public function testEmbeddingMedidoNoConsumeSiElTextoEsInvalido(): void
    {
        $provider = new class implements \NumaEmbeddingProviderInterface {
            public function embed(string $text): array
            {
                throw new \RuntimeException('No debe invocarse.');
            }
        };
        $consumption = new class implements \NumaProviderConsumptionInterface {
            public int $calls = 0;

            public function iniciarLlamada(): void
            {
                $this->calls++;
            }

            public function registrarTokens(\NumaTokenUsage $usage): void
            {
            }
        };

        try {
            (new \NumaMeteredEmbeddingProvider($provider, $consumption))->embed('   ');
            self::fail('Se esperaba una excepcion por texto vacio.');
        } catch (\InvalidArgumentException) {
            self::assertSame(0, $consumption->calls);
        }
    }

    /**
     * @return array<int, string>
     */
    private function managedEnvKeys(): array
    {
        return [
            'NUMA_EMBEDDING_PROVIDER',
            'NUMA_EMBEDDING_API_KEY',
            'NUMA_EMBEDDING_MODEL',
            'NUMA_EMBEDDING_DIMENSIONS',
            'NUMA_PROVIDER_TIMEOUT_SECONDS',
        ];
    }
}
