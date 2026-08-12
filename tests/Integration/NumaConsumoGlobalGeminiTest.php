<?php

declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/models/Database.php';
require_once APP_PATH . '/models/NumaConsumoGlobal.php';
require_once APP_PATH . '/services/GeminiNumaProvider.php';
require_once APP_PATH . '/services/NumaClassification.php';

final class NumaConsumoGlobalGeminiTest extends TestCase
{
    private const TEST_DATE = '2026-07-25';

    private PDO $db;

    /** @var array<string, string|null> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Database::getConnection();
        $this->ensureSchemaExists();
        $this->limpiarFilaDePrueba();

        foreach ($this->managedEnvKeys() as $key) {
            $this->envBackup[$key] = $_ENV[$key] ?? null;
        }

        $_ENV['NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT'] = '100';
        $_ENV['NUMA_GLOBAL_MONTHLY_PROVIDER_CALL_LIMIT'] = '1000';
        $_ENV['NUMA_GLOBAL_DAILY_TOKEN_LIMIT'] = '50000';
        $_ENV['NUMA_GLOBAL_MONTHLY_TOKEN_LIMIT'] = '300000';
        $_ENV['NUMA_MAX_INPUT_TOKENS'] = '5000';
        $_ENV['NUMA_MAX_OUTPUT_TOKENS'] = '220';
    }

    protected function tearDown(): void
    {
        $this->limpiarFilaDePrueba();

        foreach ($this->managedEnvKeys() as $key) {
            if ($this->envBackup[$key] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $this->envBackup[$key];
            }
        }

        parent::tearDown();
    }

    public function testCuentaLaLlamadaRealYRegistraLosTokensFiables(): void
    {
        $consumo = $this->consumo();
        $provider = new \GeminiNumaProvider(
            'key',
            'model',
            transport: fn (): array => $this->validResponse(120, 35),
            consumption: $consumo,
        );

        $response = $provider->respond(new \NumaRequest('Pregunta'));
        $row = $this->row();

        self::assertSame('Respuesta valida.', $response->message());
        self::assertSame(1, $row['llamadas']);
        self::assertSame(120, $row['input_tokens']);
        self::assertSame(35, $row['output_tokens']);
    }

    public function testClasificacionConProveedorCuentaComoLlamadaGlobal(): void
    {
        $provider = new \GeminiNumaProvider(
            'key',
            'model',
            transport: fn (): array => [
                'status' => 200,
                'body' => json_encode([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => json_encode([
                                    'intent' => 'producto',
                                    'allowed' => true,
                                    'reason' => 'product_help',
                                ], JSON_THROW_ON_ERROR),
                            ]],
                        ],
                    ]],
                    'usageMetadata' => [
                        'promptTokenCount' => 90,
                        'candidatesTokenCount' => 20,
                    ],
                ], JSON_THROW_ON_ERROR),
            ],
            consumption: $this->consumo(),
        );

        $classification = (new \NumaProviderScopeClassifier($provider))->classify('¿Cómo añado un movimiento?');
        $row = $this->row();

        self::assertSame('producto', $classification->intent());
        self::assertSame(1, $row['llamadas']);
        self::assertSame(90, $row['input_tokens']);
        self::assertSame(20, $row['output_tokens']);
    }

    public function testCadaReintentoRealCuentaComoOtraLlamada(): void
    {
        $transportCalls = 0;
        $consumo = $this->consumo();
        $provider = new \GeminiNumaProvider(
            'key',
            'model',
            transport: function () use (&$transportCalls): array {
                ++$transportCalls;

                return $transportCalls === 1
                    ? ['status' => 503, 'body' => '{}']
                    : $this->validResponse(120, 35);
            },
            consumption: $consumo,
        );

        $provider->respond(new \NumaRequest('Pregunta'));
        $row = $this->row();

        self::assertSame(2, $transportCalls);
        self::assertSame(2, $row['llamadas']);
        self::assertSame(5120, $row['input_tokens']);
        self::assertSame(255, $row['output_tokens']);
    }

    public function testElLimiteBloqueaElReintentoAntesDeLaSegundaLlamadaReal(): void
    {
        $_ENV['NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT'] = '1';
        $transportCalls = 0;
        $consumo = $this->consumo();
        $provider = new \GeminiNumaProvider(
            'key',
            'model',
            transport: function () use (&$transportCalls): array {
                ++$transportCalls;

                return ['status' => 503, 'body' => '{}'];
            },
            consumption: $consumo,
        );

        try {
            $provider->respond(new \NumaRequest('Pregunta'));
            self::fail('Se esperaba una excepcion de limite global.');
        } catch (\NumaGlobalLimiteAlcanzado $exception) {
            self::assertSame('NUMA_GLOBAL_LIMIT_REACHED', $exception->getMessage());
        }

        $row = $this->row();

        self::assertSame(1, $transportCalls);
        self::assertSame(1, $row['llamadas']);
        self::assertSame(5000, $row['input_tokens']);
        self::assertSame(220, $row['output_tokens']);
    }

    public function testMantieneElConteoCuandoLaLlamadaRealFalla(): void
    {
        $consumo = $this->consumo();
        $provider = new \GeminiNumaProvider(
            'key',
            'model',
            transport: fn (): array => ['status' => 401, 'body' => '{}'],
            consumption: $consumo,
        );

        try {
            $provider->respond(new \NumaRequest('Pregunta'));
            self::fail('Se esperaba una excepcion del proveedor.');
        } catch (\NumaProviderException $exception) {
            self::assertSame('NUMA_PROVIDER_AUTH_ERROR', $exception->getMessage());
        }

        $row = $this->row();

        self::assertSame(1, $row['llamadas']);
        self::assertSame(5000, $row['input_tokens']);
        self::assertSame(220, $row['output_tokens']);
    }

    public function testMantieneReservaConservadoraCuandoElProveedorNoInformaTokens(): void
    {
        $consumo = $this->consumo();
        $provider = new \GeminiNumaProvider(
            'key',
            'model',
            transport: fn (): array => $this->validResponse(),
            consumption: $consumo,
        );

        $provider->respond(new \NumaRequest('Pregunta'));
        $row = $this->row();

        self::assertSame(1, $row['llamadas']);
        self::assertSame(5000, $row['input_tokens']);
        self::assertSame(220, $row['output_tokens']);
    }

    public function testUsaTotalTokenCountComoUsoFacturableCuandoExiste(): void
    {
        $consumo = $this->consumo();
        $provider = new \GeminiNumaProvider(
            'key',
            'model',
            transport: fn (): array => $this->validResponse(120, 35, 200),
            consumption: $consumo,
        );

        $provider->respond(new \NumaRequest('Pregunta'));
        $row = $this->row();

        self::assertSame(1, $row['llamadas']);
        self::assertSame(200, $row['input_tokens']);
        self::assertSame(0, $row['output_tokens']);
    }

    private function consumo(): \NumaConsumoGlobal
    {
        return new \NumaConsumoGlobal(
            $this->db,
            new DateTimeImmutable(self::TEST_DATE . ' 10:00:00')
        );
    }

    /**
     * @return array{status:int,body:string}
     */
    private function validResponse(?int $inputTokens = null, ?int $outputTokens = null, ?int $totalTokens = null): array
    {
        $body = [
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => 'Respuesta valida.']],
                ],
            ]],
        ];

        if ($inputTokens !== null && $outputTokens !== null) {
            $body['usageMetadata'] = [
                'promptTokenCount' => $inputTokens,
                'candidatesTokenCount' => $outputTokens,
            ];
        }

        if ($totalTokens !== null) {
            $body['usageMetadata'] ??= [];
            $body['usageMetadata']['totalTokenCount'] = $totalTokens;
        }

        return [
            'status' => 200,
            'body' => json_encode($body, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @return array{llamadas:int,input_tokens:int,output_tokens:int}
     */
    private function row(): array
    {
        $stmt = $this->db->prepare(
            'SELECT llamadas, input_tokens, output_tokens FROM numa_uso_proveedor WHERE fecha = :fecha'
        );
        $stmt->execute([':fecha' => self::TEST_DATE]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($row);

        return [
            'llamadas' => (int) $row['llamadas'],
            'input_tokens' => (int) $row['input_tokens'],
            'output_tokens' => (int) $row['output_tokens'],
        ];
    }

    private function limpiarFilaDePrueba(): void
    {
        $stmt = $this->db->prepare('DELETE FROM numa_uso_proveedor WHERE fecha = :fecha');
        $stmt->execute([':fecha' => self::TEST_DATE]);
    }

    private function ensureSchemaExists(): void
    {
        $schema = file_get_contents(BASE_PATH . '/database/schema.sql');

        self::assertIsString($schema);

        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            if (!preg_match('/^CREATE TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $statement, $matches)) {
                continue;
            }

            $tableName = $matches[1];
            $stmt = $this->db->query('SHOW TABLES LIKE ' . $this->db->quote($tableName));

            if ($stmt !== false && $stmt->fetchColumn() !== false) {
                continue;
            }

            $this->db->exec($statement);
        }
    }

    /**
     * @return array<int, string>
     */
    private function managedEnvKeys(): array
    {
        return [
            'NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT',
            'NUMA_GLOBAL_MONTHLY_PROVIDER_CALL_LIMIT',
            'NUMA_GLOBAL_DAILY_TOKEN_LIMIT',
            'NUMA_GLOBAL_MONTHLY_TOKEN_LIMIT',
            'NUMA_MAX_INPUT_TOKENS',
            'NUMA_MAX_OUTPUT_TOKENS',
        ];
    }
}
