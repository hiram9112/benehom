<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/models/Database.php';
require_once APP_PATH . '/models/NumaPublicUso.php';
require_once APP_PATH . '/services/NumaService.php';

final class NumaPublicServiceTest extends TestCase
{
    private PDO $db;

    /** @var list<string> */
    private array $visitorHashes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Database::getConnection();
        $this->ensureSchemaExists();
        $_ENV['NUMA_ENABLED'] = 'true';
        $_ENV['NUMA_PUBLIC_ENABLED'] = 'true';
        $_ENV['NUMA_PUBLIC_DAILY_LIMIT'] = '15';
        $_ENV['NUMA_PUBLIC_MONTHLY_LIMIT'] = '60';
        $_ENV['NUMA_MAX_PROVIDER_CALLS'] = '5';
        $_ENV['NUMA_MAX_TRANSIENT_RETRIES'] = '1';
        $_ENV['NUMA_REQUEST_TIMEOUT_SECONDS'] = '25';
    }

    protected function tearDown(): void
    {
        foreach ($this->visitorHashes as $hash) {
            $statement = $this->db->prepare('DELETE FROM numa_reservas_publicas WHERE visitante_hash = :hash');
            $statement->execute([':hash' => $hash]);
            $statement = $this->db->prepare('DELETE FROM numa_uso_publico WHERE visitante_hash = :hash');
            $statement->execute([':hash' => $hash]);
        }

        parent::tearDown();
    }

    public function testRechazoLocalPublicoNoResuelveProveedorRagNiRegistroDeTools(): void
    {
        $providerFactoryCalls = 0;
        $knowledgeCalls = 0;
        $toolFactoryCalls = 0;
        $usage = new \NumaPublicUso($this->db);

        $result = (new \NumaService(
            $usage,
            new \NumaLocalScopeClassifier(),
            function () use (&$providerFactoryCalls): \NumaProviderInterface {
                ++$providerFactoryCalls;
                throw new \LogicException('El proveedor no debe resolverse.');
            },
            function () use (&$knowledgeCalls): array {
                ++$knowledgeCalls;
                return [];
            },
            function () use (&$toolFactoryCalls): \NumaFinancialToolRegistryInterface {
                ++$toolFactoryCalls;
                throw new \LogicException('Las tools no deben resolverse.');
            },
            $this->available(),
        ))->answerPublic($this->visitorHash(), '¿Cuánto gasté este mes?');

        self::assertSame('Para analizar tus datos financieros personales, inicia sesión en BeneHom.', $result->toArray()['message']);
        self::assertSame(0, $providerFactoryCalls);
        self::assertSame(0, $knowledgeCalls);
        self::assertSame(0, $toolFactoryCalls);
        self::assertSame(0, $result->toArray()['usage']['daily_used']);
    }

    public function testRutaAmbiguaPublicaPuedeTerminarConUnaSolaUnidadDisponible(): void
    {
        $_ENV['NUMA_PUBLIC_DAILY_LIMIT'] = '1';
        $_ENV['NUMA_PUBLIC_MONTHLY_LIMIT'] = '1';
        $visitorHash = $this->visitorHash();
        $usage = new \NumaPublicUso($this->db);
        $provider = new NumaPublicServiceProvider(new \NumaResponse('clasificacion', [
            'intent' => 'fuera_de_ambito',
            'allowed' => false,
            'reason' => 'general_knowledge',
        ]));
        $toolFactoryCalls = 0;

        $result = $this->service($usage, $provider, $toolFactoryCalls)->answerPublic(
            $visitorHash,
            'Necesito ayuda con esta consulta.'
        );

        self::assertSame(
            'Puedo ayudarte con BeneHom, conceptos de economía familiar y el análisis de los datos que hayas registrado. No respondo preguntas generales ajenas a estas funciones.',
            $result->toArray()['message']
        );
        self::assertSame(1, $result->toArray()['usage']['daily_used']);
        self::assertSame(0, $result->toArray()['usage']['daily_remaining']);
        self::assertSame(1, $result->toArray()['usage']['interaction_used']);
        self::assertSame(0, $toolFactoryCalls);
    }

    public function testSolicitudDeToolInesperadaEsInvalidaSinResolverNiEjecutarTools(): void
    {
        $toolFactoryCalls = 0;
        $provider = new NumaPublicServiceProvider(new \NumaResponse(
            'No debe ejecutarse.',
            null,
            new \NumaToolRequest('obtener_resumen_financiero'),
        ));
        $usage = new \NumaPublicUso($this->db);
        $visitorHash = $this->visitorHash();

        $service = $this->service($usage, $provider, $toolFactoryCalls);

        try {
            $service->answerPublic($visitorHash, '¿Cómo añado un movimiento?');
            self::fail('Una tool inesperada en modo público debe invalidar la respuesta.');
        } catch (\NumaServiceException $exception) {
            self::assertSame('NUMA_PROVIDER_INVALID_RESPONSE', $exception->safeCode());
        }

        self::assertSame(0, $toolFactoryCalls);
        self::assertSame(2, $usage->estado($visitorHash)['daily_used']);
    }

    public function testLlamadaPublicaIniciadaPermaneceConsumidaAunqueElProveedorFalle(): void
    {
        $provider = new NumaPublicServiceProvider(null, new \NumaProviderException(new \NumaProviderError(
            \NumaProviderError::TIMEOUT,
            'NUMA_PROVIDER_TIMEOUT',
            true,
        )));
        $usage = new \NumaPublicUso($this->db);
        $visitorHash = $this->visitorHash();
        $toolFactoryCalls = 0;

        try {
            $this->service($usage, $provider, $toolFactoryCalls)->answerPublic($visitorHash, 'Necesito ayuda.');
            self::fail('El timeout del proveedor debe devolverse como error seguro.');
        } catch (\NumaServiceException $exception) {
            self::assertSame('NUMA_PROVIDER_TIMEOUT', $exception->safeCode());
        }

        self::assertSame(1, $usage->estado($visitorHash)['daily_used']);
        self::assertSame(0, $toolFactoryCalls);
    }

    public function testLlamadasPublicasDeRagYProveedorConfirmanCuotaYContadoresGlobales(): void
    {
        $_ENV['NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT'] = '1000';
        $_ENV['NUMA_GLOBAL_MONTHLY_PROVIDER_CALL_LIMIT'] = '1000';
        $_ENV['NUMA_PUBLIC_GLOBAL_DAILY_CALL_LIMIT'] = '1000';
        $_ENV['NUMA_PUBLIC_GLOBAL_MONTHLY_CALL_LIMIT'] = '1000';
        $_ENV['NUMA_GLOBAL_DAILY_TOKEN_LIMIT'] = '10000000';
        $_ENV['NUMA_GLOBAL_MONTHLY_TOKEN_LIMIT'] = '10000000';

        $visitorHash = $this->visitorHash();
        $usage = new \NumaPublicUso($this->db);
        $provider = new NumaPublicServiceProvider(new \NumaResponse('Puedes añadir movimientos desde el formulario.'));
        $before = $this->providerCalls();
        $toolFactoryCalls = 0;
        $service = new \NumaService(
            $usage,
            new \NumaLocalScopeClassifier(),
            fn (?\NumaProviderConsumptionInterface $consumption = null): \NumaProviderInterface => $provider->withConsumption(
                $consumption === null
                    ? null
                    : new \NumaProviderConsumptionChain($consumption, \NumaConsumoGlobal::forPublicLlm($this->db))
            ),
            function (\NumaClassification $classification, string $message, ?\NumaProviderConsumptionInterface $consumption = null): array {
                if ($consumption !== null) {
                    (new \NumaProviderConsumptionChain(
                        $consumption,
                        \NumaConsumoGlobal::forPublicEmbedding($this->db),
                    ))->iniciarLlamada();
                }

                return [new \NumaKnowledgeSearchResult('fragmento-global', 'movimientos', 'Movimientos', 'Añadir', '/movimientos', 'Contenido público de prueba.', 0.9)];
            },
            function () use (&$toolFactoryCalls): \NumaFinancialToolRegistryInterface {
                ++$toolFactoryCalls;
                throw new \LogicException('El registro financiero no debe resolverse en modo público.');
            },
            $this->available(),
        );

        $result = $service->answerPublic($visitorHash, '¿Cómo añado un movimiento?');
        $after = $this->providerCalls();

        self::assertSame(2, $result->toArray()['usage']['daily_used']);
        self::assertSame($before['llamadas'] + 2, $after['llamadas']);
        self::assertSame($before['llamadas_publicas'] + 2, $after['llamadas_publicas']);
        self::assertSame(0, $toolFactoryCalls);
    }

    public function testCadaIntentoPublicoIncluidoUnReintentoUsaLaMismaCadenaDeConsumo(): void
    {
        $_ENV['NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT'] = '1000';
        $_ENV['NUMA_GLOBAL_MONTHLY_PROVIDER_CALL_LIMIT'] = '1000';
        $_ENV['NUMA_PUBLIC_GLOBAL_DAILY_CALL_LIMIT'] = '1000';
        $_ENV['NUMA_PUBLIC_GLOBAL_MONTHLY_CALL_LIMIT'] = '1000';
        $_ENV['NUMA_GLOBAL_DAILY_TOKEN_LIMIT'] = '10000000';
        $_ENV['NUMA_GLOBAL_MONTHLY_TOKEN_LIMIT'] = '10000000';

        $visitorHash = $this->visitorHash();
        $usage = new \NumaPublicUso($this->db);
        $budget = new \NumaPaidCallBudget(new \NumaPublicUsageBudget($usage, $visitorHash), 3);
        $chain = new \NumaProviderConsumptionChain($budget, \NumaConsumoGlobal::forPublicLlm($this->db));
        $before = $this->providerCalls();

        $chain->iniciarLlamada();
        $chain->iniciarLlamada();

        $after = $this->providerCalls();
        self::assertSame(2, $usage->estado($visitorHash)['daily_used']);
        self::assertSame($before['llamadas'] + 2, $after['llamadas']);
        self::assertSame($before['llamadas_publicas'] + 2, $after['llamadas_publicas']);
    }

    private function service(\NumaPublicUso $usage, NumaPublicServiceProvider $provider, int &$toolFactoryCalls): \NumaService
    {
        return new \NumaService(
            $usage,
            new \NumaLocalScopeClassifier(),
            static fn (?\NumaProviderConsumptionInterface $consumption = null): \NumaProviderInterface => $provider->withConsumption($consumption),
            static function (\NumaClassification $classification, string $message, ?\NumaProviderConsumptionInterface $consumption = null): array {
                $consumption?->iniciarLlamada();

                return [new \NumaKnowledgeSearchResult('fragmento', 'movimientos', 'Movimientos', 'Añadir', '/movimientos', 'Contenido público de prueba.', 0.9)];
            },
            function () use (&$toolFactoryCalls): \NumaFinancialToolRegistryInterface {
                ++$toolFactoryCalls;
                throw new \LogicException('El registro financiero no debe resolverse en modo público.');
            },
            $this->available(),
        );
    }

    private function available(): \NumaGlobalAvailabilityInterface
    {
        return new class implements \NumaGlobalAvailabilityInterface {
            public function assertAvailable(): void
            {
            }
        };
    }

    private function visitorHash(): string
    {
        $hash = hash('sha256', random_bytes(32));
        $this->visitorHashes[] = $hash;

        return $hash;
    }

    private function ensureSchemaExists(): void
    {
        $schema = file_get_contents(BASE_PATH . '/database/schema.sql');
        self::assertIsString($schema);

        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            if (!preg_match('/^CREATE TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $statement, $matches)) {
                continue;
            }

            $existing = $this->db->query('SHOW TABLES LIKE ' . $this->db->quote($matches[1]));
            if ($existing !== false && $existing->fetchColumn() !== false) {
                continue;
            }

            $this->db->exec($statement);
        }
    }

    /** @return array{llamadas:int,llamadas_publicas:int} */
    private function providerCalls(): array
    {
        $statement = $this->db->prepare(
            'SELECT COALESCE(llamadas, 0) AS llamadas, COALESCE(llamadas_publicas, 0) AS llamadas_publicas
             FROM numa_uso_proveedor WHERE fecha = :fecha'
        );
        $statement->execute([':fecha' => date('Y-m-d')]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return [
            'llamadas' => (int) ($row['llamadas'] ?? 0),
            'llamadas_publicas' => (int) ($row['llamadas_publicas'] ?? 0),
        ];
    }
}

final class NumaPublicServiceProvider implements \NumaProviderInterface
{
    private ?\NumaProviderConsumptionInterface $consumption = null;

    public function __construct(
        private readonly ?\NumaResponse $response,
        private readonly ?\NumaProviderException $exception = null,
    ) {
    }

    public function withConsumption(?\NumaProviderConsumptionInterface $consumption): self
    {
        $this->consumption = $consumption;

        return $this;
    }

    public function respond(\NumaRequest $request): \NumaResponse
    {
        $this->consumption?->iniciarLlamada();

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->response ?? new \NumaResponse('Respuesta pública.');
    }
}
