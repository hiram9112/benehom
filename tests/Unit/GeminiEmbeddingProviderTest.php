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
        $transportCalls = 0;
        $vector = array_merge([1.0], array_fill(0, 767, 0.0));
        $transport = function (string $url, array $headers, string $body, int $timeout) use (&$captured, &$transportCalls, $vector): array {
            $transportCalls++;
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
                    'usageMetadata' => [
                        'promptTokenCount' => 37,
                    ],
                ]),
            ];
        };

        $provider = new \GeminiEmbeddingProvider('embedding-key', 'gemini-embedding-001', 768, 20, $transport);
        $embedding = $provider->embed('Como se anade un movimiento en BeneHom');

        self::assertStringEndsWith('/models/gemini-embedding-001:embedContent', $captured['url']);
        self::assertContains('x-goog-api-key: embedding-key', $captured['headers']);
        self::assertSame(10, $captured['timeout']);
        self::assertArrayNotHasKey('embedContentConfig', $captured['body']);
        self::assertSame('RETRIEVAL_DOCUMENT', $captured['body']['taskType']);
        self::assertSame(768, $captured['body']['outputDimensionality']);
        self::assertSame('Como se anade un movimiento en BeneHom', $captured['body']['content']['parts'][0]['text']);
        self::assertSame(1, $transportCalls);
        self::assertCount(768, $embedding);
        self::assertSame(1.0, $embedding[0]);
        self::assertSame(37, $provider->tokenUsage()->inputTokens());
        self::assertSame(0, $provider->tokenUsage()->outputTokens());
        self::assertTrue($provider->tokenUsage()->hasReliableTokens());
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
        self::assertSame(768, $captured['outputDimensionality']);
    }

    public function testUsaTaskTypeDeConsultaYNormalizaVectoresReducidos(): void
    {
        $captured = [];
        $provider = new \GeminiEmbeddingProvider('key', 'gemini-embedding-001', 2, transport: function (
            string $url,
            array $headers,
            string $body
        ) use (&$captured): array {
            $captured = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            return [
                'status' => 200,
                'body' => json_encode(['embedding' => ['values' => [3.0, 4.0]]]),
            ];
        });

        self::assertSame([0.6, 0.8], $provider->embedQuery('Consulta documental'));
        self::assertSame('RETRIEVAL_QUERY', $captured['taskType']);
        self::assertSame('RETRIEVAL_DOCUMENT', json_decode($provider->signature()->value(), true, 512, JSON_THROW_ON_ERROR)['task_type']);
    }

    public function testRechazaVectorNulo(): void
    {
        $provider = new \GeminiEmbeddingProvider('key', 'gemini-embedding-001', 2, transport: fn (): array => [
            'status' => 200,
            'body' => json_encode(['embedding' => ['values' => [0.0, 0.0]]]),
        ]);

        $this->expectException(\NumaProviderException::class);
        $this->expectExceptionMessage('NUMA_PROVIDER_INVALID_RESPONSE');

        $provider->embedDocument('Texto publico de BeneHom');
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
        $provider = new class implements \NumaEmbeddingProviderUsageInterface {
            public int $calls = 0;

            public function embed(string $text): array
            {
                $this->calls++;

                return [0.1, 0.2];
            }

            public function tokenUsage(): \NumaTokenUsage
            {
                return new \NumaTokenUsage(23, 0);
            }

            public function signature(): \NumaEmbeddingSignature
            {
                return new \NumaEmbeddingSignature('fake', 'test', 'RETRIEVAL_DOCUMENT', 2, '1');
            }
        };
        $consumption = new class implements \NumaProviderConsumptionInterface {
            public int $calls = 0;

            public ?\NumaTokenUsage $usage = null;

            public function iniciarLlamada(): void
            {
                $this->calls++;
            }

            public function registrarTokens(\NumaTokenUsage $usage): void
            {
                $this->usage = $usage;
            }
        };

        $embedding = (new \NumaMeteredEmbeddingProvider($provider, $consumption))->embed('Texto publico de BeneHom');

        self::assertSame([0.1, 0.2], $embedding);
        self::assertSame(1, $consumption->calls);
        self::assertSame(1, $provider->calls);
        self::assertSame(23, $consumption->usage?->inputTokens());
        self::assertSame(0, $consumption->usage?->outputTokens());
    }

    public function testEmbeddingMedidoEspecializadoInvocaUnaSolaVezProveedorYConsumo(): void
    {
        $provider = new class implements \NumaEmbeddingProviderUsageInterface, \NumaEmbeddingTaskProviderInterface {
            public int $embedCalls = 0;

            public int $documentCalls = 0;

            public int $queryCalls = 0;

            public function embed(string $text): array
            {
                $this->embedCalls++;

                return [0.0, 1.0];
            }

            public function embedDocument(string $text): array
            {
                $this->documentCalls++;

                return [1.0, 0.0];
            }

            public function embedQuery(string $text): array
            {
                $this->queryCalls++;

                return [0.5, 0.5];
            }

            public function tokenUsage(): \NumaTokenUsage
            {
                return new \NumaTokenUsage(11, 0);
            }

            public function signature(): \NumaEmbeddingSignature
            {
                return new \NumaEmbeddingSignature('fake', 'test', 'RETRIEVAL_DOCUMENT', 2, '1');
            }
        };
        $consumption = new class implements \NumaProviderConsumptionInterface {
            public int $calls = 0;

            public int $tokenRegistrations = 0;

            public function iniciarLlamada(): void
            {
                $this->calls++;
            }

            public function registrarTokens(\NumaTokenUsage $usage): void
            {
                $this->tokenRegistrations++;
            }
        };
        $metered = new \NumaMeteredEmbeddingProvider($provider, $consumption);

        self::assertSame([1.0, 0.0], $metered->embedDocument('Documento publico de BeneHom'));
        self::assertSame(0, $provider->embedCalls);
        self::assertSame(1, $provider->documentCalls);
        self::assertSame(0, $provider->queryCalls);
        self::assertSame(1, $consumption->calls);
        self::assertSame(1, $consumption->tokenRegistrations);

        self::assertSame([0.5, 0.5], $metered->embedQuery('Consulta documental de BeneHom'));
        self::assertSame(0, $provider->embedCalls);
        self::assertSame(1, $provider->documentCalls);
        self::assertSame(1, $provider->queryCalls);
        self::assertSame(2, $consumption->calls);
        self::assertSame(2, $consumption->tokenRegistrations);
    }

    public function testEmbeddingMedidoNoConsumeSiElTextoEsInvalido(): void
    {
        $provider = new class implements \NumaEmbeddingProviderInterface {
            public function embed(string $text): array
            {
                throw new \RuntimeException('No debe invocarse.');
            }

            public function signature(): \NumaEmbeddingSignature
            {
                return new \NumaEmbeddingSignature('fake', 'test', 'RETRIEVAL_DOCUMENT', 2, '1');
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
