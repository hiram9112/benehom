<?php

declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/models/Database.php';
require_once APP_PATH . '/models/NumaConsumoGlobal.php';
require_once APP_PATH . '/services/NumaEmbeddingProvider.php';
require_once APP_PATH . '/services/GeminiEmbeddingProvider.php';
require_once APP_PATH . '/services/GeminiNumaProvider.php';
require_once APP_PATH . '/services/NumaService.php';

final class NumaUsoFallaDespuesDeConfirmar extends \NumaUso
{
    public function confirmar(string $reservaId): bool
    {
        parent::confirmar($reservaId);

        throw new \RuntimeException('Fallo de confirmacion simulado.');
    }
}

final class NumaConsumoGlobalTest extends TestCase
{
    private PDO $db;

    /** @var list<string> */
    private array $fechasCreadas = ['2026-06-30', '2026-07-01', '2026-07-24', '2026-07-25'];

    /** @var array<string, string|null> */
    private array $envBackup = [];

    /** @var list<int> */
    private array $userIds = [];

    /** @var list<string> */
    private array $visitorHashes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Database::getConnection();
        $this->limpiarFilasDePrueba();
        $this->ensureSchemaExists();

        foreach ($this->managedEnvKeys() as $key) {
            $this->envBackup[$key] = $_ENV[$key] ?? null;
        }

        $_ENV['NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT'] = '100';
        $_ENV['NUMA_GLOBAL_MONTHLY_PROVIDER_CALL_LIMIT'] = '1000';
        $_ENV['NUMA_GLOBAL_DAILY_TOKEN_LIMIT'] = '50000';
        $_ENV['NUMA_GLOBAL_MONTHLY_TOKEN_LIMIT'] = '300000';
        $_ENV['NUMA_MAX_INPUT_TOKENS'] = '5000';
        $_ENV['NUMA_MAX_OUTPUT_TOKENS'] = '220';
        $_ENV['NUMA_MAX_RAG_CHUNK_CHARS'] = '900';
        $_ENV['NUMA_DAILY_LIMIT'] = '5';
        $_ENV['NUMA_MONTHLY_LIMIT'] = '20';
        $_ENV['NUMA_RESERVATION_TTL_SECONDS'] = '120';
    }

    protected function tearDown(): void
    {
        if ($this->userIds !== []) {
            $ids = implode(',', array_map('intval', $this->userIds));
            $this->db->exec("DELETE FROM numa_reservas WHERE usuario_id IN ($ids)");
            $this->db->exec("DELETE FROM numa_uso WHERE usuario_id IN ($ids)");
            $this->db->exec("DELETE FROM usuarios WHERE id IN ($ids)");
        }

        if ($this->visitorHashes !== []) {
            $hashes = implode(',', array_map([$this->db, 'quote'], array_unique($this->visitorHashes)));
            $this->db->exec("DELETE FROM numa_reservas_publicas WHERE visitante_hash IN ($hashes)");
            $this->db->exec("DELETE FROM numa_uso_publico WHERE visitante_hash IN ($hashes)");
        }

        $this->limpiarFilasDePrueba();

        foreach ($this->managedEnvKeys() as $key) {
            if ($this->envBackup[$key] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $this->envBackup[$key];
            }
        }

        parent::tearDown();
    }

    public function testIniciarLlamadaIncrementaElContadorDelDia(): void
    {
        $repo = $this->repo('2026-07-25 10:00:00');

        $repo->iniciarLlamada();
        $repo->iniciarLlamada();

        $estado = $repo->estadoGlobal();

        self::assertSame(2, $repo->llamadasDia('2026-07-25'));
        self::assertSame(2, $estado['daily_calls']);
        self::assertSame(2, $estado['monthly_calls']);
        self::assertSame(100, $estado['daily_calls_limit']);
        self::assertSame(1000, $estado['monthly_calls_limit']);
        self::assertSame(10440, $estado['daily_tokens']);
        self::assertSame(50000, $estado['daily_tokens_limit']);
    }

    public function testReinicioDiarioNoSumaLlamadasDeOtrosDias(): void
    {
        $this->insertRow('2026-06-30', 50, 0, 0);
        $this->insertRow('2026-07-24', 30, 0, 0);
        $repo = $this->repo('2026-07-25 10:00:00');

        $repo->iniciarLlamada();

        $estado = $repo->estadoGlobal();

        self::assertSame(1, $repo->llamadasDia('2026-07-25'));
        self::assertSame(31, $estado['monthly_calls']);
        self::assertSame(50, $repo->llamadasMes('2026-06-01', '2026-07-01'));
    }

    public function testLimiteGlobalDiarioDeLlamadasBloqueaNuevasLlamadas(): void
    {
        $_ENV['NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT'] = '2';
        $_ENV['NUMA_GLOBAL_MONTHLY_PROVIDER_CALL_LIMIT'] = '1000';
        $repo = $this->repo('2026-07-25 10:00:00');

        $repo->iniciarLlamada();
        $repo->iniciarLlamada();

        $this->expectException(\NumaGlobalLimiteAlcanzado::class);
        $this->expectExceptionMessage('NUMA_GLOBAL_LIMIT_REACHED');

        $repo->iniciarLlamada();
    }

    public function testLimiteGlobalMensualDeLlamadasBloqueaNuevasLlamadas(): void
    {
        $_ENV['NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT'] = '100';
        $_ENV['NUMA_GLOBAL_MONTHLY_PROVIDER_CALL_LIMIT'] = '2';
        $repo = $this->repo('2026-07-25 10:00:00');

        $repo->iniciarLlamada();
        $repo->iniciarLlamada();

        $this->expectException(\NumaGlobalLimiteAlcanzado::class);
        $this->expectExceptionMessage('NUMA_GLOBAL_LIMIT_REACHED');

        $repo->iniciarLlamada();
    }

    public function testLlamadasPublicasIncrementanAmbosContadoresEnLaMismaFila(): void
    {
        $_ENV['NUMA_PUBLIC_GLOBAL_DAILY_CALL_LIMIT'] = '40';
        $_ENV['NUMA_PUBLIC_GLOBAL_MONTHLY_CALL_LIMIT'] = '400';
        $public = \NumaConsumoGlobal::forPublicLlm($this->db, new DateTimeImmutable('2026-07-25 10:00:00'));
        $private = new \NumaConsumoGlobal($this->db, new DateTimeImmutable('2026-07-25 10:00:00'));

        $public->iniciarLlamada();
        $private->iniciarLlamada();

        $statement = $this->db->prepare(
            'SELECT llamadas, llamadas_publicas FROM numa_uso_proveedor WHERE fecha = :fecha'
        );
        $statement->execute([':fecha' => '2026-07-25']);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($row);
        self::assertSame(2, (int) $row['llamadas']);
        self::assertSame(1, (int) $row['llamadas_publicas']);
    }

    public function testLimiteGlobalPublicoBloqueaAunqueQuedeCapacidadGeneral(): void
    {
        $_ENV['NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT'] = '100';
        $_ENV['NUMA_GLOBAL_MONTHLY_PROVIDER_CALL_LIMIT'] = '1000';
        $_ENV['NUMA_PUBLIC_GLOBAL_DAILY_CALL_LIMIT'] = '1';
        $_ENV['NUMA_PUBLIC_GLOBAL_MONTHLY_CALL_LIMIT'] = '400';
        $repo = \NumaConsumoGlobal::forPublicEmbedding($this->db, new DateTimeImmutable('2026-07-25 10:00:00'));

        $repo->iniciarLlamada();

        $this->expectException(\NumaGlobalLimiteAlcanzado::class);
        $this->expectExceptionMessage('NUMA_PUBLIC_GLOBAL_LIMIT_REACHED');
        $repo->iniciarLlamada();
    }

    public function testLimiteGlobalDiarioDeTokensBloqueaNuevasLlamadas(): void
    {
        $_ENV['NUMA_GLOBAL_DAILY_TOKEN_LIMIT'] = '1000';
        $this->insertRow('2026-07-25', 0, 600, 400);

        $repo = $this->repo('2026-07-25 10:00:00');

        $this->expectException(\NumaGlobalLimiteAlcanzado::class);
        $this->expectExceptionMessage('NUMA_GLOBAL_LIMIT_REACHED');

        $repo->iniciarLlamada();
    }

    public function testLimiteGlobalMensualDeTokensBloqueaNuevasLlamadas(): void
    {
        $_ENV['NUMA_GLOBAL_MONTHLY_TOKEN_LIMIT'] = '500';
        $this->insertRow('2026-07-01', 0, 250, 250);
        $repo = $this->repo('2026-07-25 10:00:00');

        $this->expectException(\NumaGlobalLimiteAlcanzado::class);
        $this->expectExceptionMessage('NUMA_GLOBAL_LIMIT_REACHED');

        $repo->iniciarLlamada();
    }

    public function testRegistrarTokensFiablesIncrementaElContador(): void
    {
        $repo = $this->repo('2026-07-25 10:00:00');
        $repo->iniciarLlamada();

        $repo->registrarTokens(new \NumaTokenUsage(120, 35));

        $row = $this->row('2026-07-25');

        self::assertSame(1, $row['llamadas']);
        self::assertSame(120, $row['input_tokens']);
        self::assertSame(35, $row['output_tokens']);
        self::assertSame(155, $repo->tokensDia('2026-07-25'));
    }

    public function testUsoParcialMantieneLaReservaConservadora(): void
    {
        $repo = $this->repo('2026-07-25 10:00:00');
        $repo->iniciarLlamada();

        $repo->registrarTokens(new \NumaTokenUsage(null, 50));

        $row = $this->row('2026-07-25');

        self::assertSame(5000, $row['input_tokens']);
        self::assertSame(220, $row['output_tokens']);
    }

    public function testEmbeddingReservaLlamadaGlobalYTokensConservadoresDeEntrada(): void
    {
        $transportCalls = 0;
        $reservedBeforeTransport = null;
        $provider = new \GeminiEmbeddingProvider('key', 'model', 2, transport: function () use (&$transportCalls, &$reservedBeforeTransport): array {
            ++$transportCalls;
            $reservedBeforeTransport = $this->row('2026-07-25');

            return $this->validEmbeddingResponse();
        });
        $consumo = \NumaConsumoGlobal::forEmbedding($this->db, new DateTimeImmutable('2026-07-25 10:00:00'));

        $embedding = (new \NumaMeteredEmbeddingProvider($provider, $consumo))->embed('Consulta documental de BeneHom');
        $row = $this->row('2026-07-25');

        self::assertSame([0.1, 0.2], $embedding);
        self::assertSame(1, $transportCalls);
        self::assertIsArray($reservedBeforeTransport);
        self::assertSame(2048, $reservedBeforeTransport['input_tokens']);
        self::assertSame(0, $reservedBeforeTransport['output_tokens']);
        self::assertSame(1, $row['llamadas']);
        self::assertSame(2048, $row['input_tokens']);
        self::assertSame(0, $row['output_tokens']);
    }

    public function testEmbeddingExitosoReemplazaLaReservaPorPromptTokenCount(): void
    {
        $provider = new \GeminiEmbeddingProvider(
            'key',
            'model',
            2,
            transport: fn (): array => $this->validEmbeddingResponse(41)
        );
        $consumo = \NumaConsumoGlobal::forEmbedding($this->db, new DateTimeImmutable('2026-07-25 10:00:00'));

        (new \NumaMeteredEmbeddingProvider($provider, $consumo))->embed('Consulta documental de BeneHom');
        $row = $this->row('2026-07-25');

        self::assertSame(1, $row['llamadas']);
        self::assertSame(41, $row['input_tokens']);
        self::assertSame(0, $row['output_tokens']);
    }

    public function testEmbeddingFallidoMantieneLaReservaConservadora(): void
    {
        $provider = new \GeminiEmbeddingProvider('key', 'model', 2, transport: static function (): array {
            throw new \RuntimeException('Fallo de transporte simulado.');
        });
        $consumo = \NumaConsumoGlobal::forEmbedding($this->db, new DateTimeImmutable('2026-07-25 10:00:00'));

        try {
            (new \NumaMeteredEmbeddingProvider($provider, $consumo))->embed('Consulta documental de BeneHom');
            self::fail('Se esperaba una excepcion del proveedor.');
        } catch (\NumaProviderException $exception) {
            self::assertSame('NUMA_PROVIDER_UNAVAILABLE', $exception->getMessage());
        }

        $row = $this->row('2026-07-25');

        self::assertSame(1, $row['llamadas']);
        self::assertSame(2048, $row['input_tokens']);
        self::assertSame(0, $row['output_tokens']);
    }

    public function testEmbeddingReservaElMaximoDelModeloSinDependerDeLimitesEnCaracteresNiSalida(): void
    {
        $_ENV['NUMA_MAX_RAG_CHUNK_CHARS'] = '1200';
        $_ENV['NUMA_MAX_MESSAGE_LENGTH'] = '1600';
        $_ENV['NUMA_MAX_OUTPUT_TOKENS'] = '1';
        $provider = new class implements \NumaEmbeddingProviderInterface {
            public function embed(string $text): array
            {
                return [0.1, 0.2];
            }

            public function signature(): \NumaEmbeddingSignature
            {
                return new \NumaEmbeddingSignature('fake', 'test', 'RETRIEVAL_DOCUMENT', 2, '1');
            }
        };
        $consumo = \NumaConsumoGlobal::forEmbedding($this->db, new DateTimeImmutable('2026-07-24 10:00:00'));

        (new \NumaMeteredEmbeddingProvider($provider, $consumo))->embed('Consulta documental de BeneHom');
        $row = $this->row('2026-07-24');

        self::assertSame(1, $row['llamadas']);
        self::assertSame(2048, $row['input_tokens']);
        self::assertSame(0, $row['output_tokens']);
    }

    public function testRegistrarTokensDesconocidosNoCreaNiModificaFilas(): void
    {
        $repo = $this->repo('2026-07-25 10:00:00');

        $repo->registrarTokens(\NumaTokenUsage::unknown());

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM numa_uso_proveedor WHERE fecha = :fecha');
        $stmt->execute([':fecha' => '2026-07-25']);

        self::assertSame(0, (int) $stmt->fetchColumn());
    }

    public function testReservaAtomicaBloqueaLlamadasConConexionesSeparadas(): void
    {
        $_ENV['NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT'] = '1';
        $_ENV['NUMA_GLOBAL_MONTHLY_PROVIDER_CALL_LIMIT'] = '1000';
        $fecha = '2026-07-25';

        $repoA = new \NumaConsumoGlobal($this->newConnection(), new DateTimeImmutable($fecha . ' 10:00:00'));
        $repoB = new \NumaConsumoGlobal($this->newConnection(), new DateTimeImmutable($fecha . ' 10:00:00'));

        $repoA->iniciarLlamada();

        $this->expectException(\NumaGlobalLimiteAlcanzado::class);
        $this->expectExceptionMessage('NUMA_GLOBAL_LIMIT_REACHED');

        $repoB->iniciarLlamada();
    }

    public function testLlamadasConcurrentesSolapadasConConexionesSeparadasNoSuperanElLimiteMensual(): void
    {
        $_ENV['NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT'] = '100';
        $_ENV['NUMA_GLOBAL_MONTHLY_PROVIDER_CALL_LIMIT'] = '1';
        $_ENV['NUMA_GLOBAL_DAILY_TOKEN_LIMIT'] = '50000';
        $_ENV['NUMA_GLOBAL_MONTHLY_TOKEN_LIMIT'] = '300000';
        $dir = sys_get_temp_dir() . '/benehom-numa-global-' . bin2hex(random_bytes(8));
        $locker = $this->newConnection();

        $this->insertRow('2026-07-01', 0, 0, 0);
        $this->insertRow('2026-07-21', 0, 0, 0);
        $this->insertRow('2026-07-22', 0, 0, 0);
        self::assertTrue(mkdir($dir, 0700));

        $locker->beginTransaction();

        try {
            $stmt = $locker->prepare('SELECT fecha FROM numa_uso_proveedor WHERE fecha = :fecha FOR UPDATE');
            $stmt->execute([':fecha' => '2026-07-01']);
            self::assertSame('2026-07-01', $stmt->fetchColumn());

            $processA = $this->startConcurrentGlobalCallProcess($dir, 'a', '2026-07-21 10:00:00');
            $processB = $this->startConcurrentGlobalCallProcess($dir, 'b', '2026-07-22 10:00:00');

            $this->waitForFiles([$processA['ready_file'], $processB['ready_file']]);
            file_put_contents($dir . '/start', '1');
            $this->waitForFiles([$processA['attempt_file'], $processB['attempt_file']]);
            usleep(200000);

            self::assertTrue($this->processIsRunning($processA));
            self::assertTrue($this->processIsRunning($processB));

            $locker->commit();

            $resultA = $this->collectConcurrentGlobalCallProcess($processA);
            $resultB = $this->collectConcurrentGlobalCallProcess($processB);
        } finally {
            if ($locker->inTransaction()) {
                $locker->rollBack();
            }

            foreach (glob($dir . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            if (is_dir($dir)) {
                rmdir($dir);
            }
        }

        $statuses = [$resultA['status'], $resultB['status']];
        sort($statuses);

        self::assertSame(['limit', 'started'], $statuses);
        self::assertSame(1, $this->llamadasMes('2026-07-01', '2026-08-01'));
    }

    public function testLlamadasPublicasConcurrentesDeVisitantesDistintosNoSuperanElLimiteGlobalPublico(): void
    {
        $_ENV['NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT'] = '100';
        $_ENV['NUMA_GLOBAL_MONTHLY_PROVIDER_CALL_LIMIT'] = '1000';
        $_ENV['NUMA_GLOBAL_DAILY_TOKEN_LIMIT'] = '50000';
        $_ENV['NUMA_GLOBAL_MONTHLY_TOKEN_LIMIT'] = '300000';
        $_ENV['NUMA_PUBLIC_GLOBAL_DAILY_CALL_LIMIT'] = '1';
        $_ENV['NUMA_PUBLIC_GLOBAL_MONTHLY_CALL_LIMIT'] = '400';
        $_ENV['NUMA_PUBLIC_DAILY_LIMIT'] = '5';
        $_ENV['NUMA_PUBLIC_MONTHLY_LIMIT'] = '20';
        $_ENV['NUMA_RESERVATION_TTL_SECONDS'] = '120';

        $visitorA = $this->visitorHash();
        $visitorB = $this->visitorHash();
        $this->fechasCreadas[] = '2026-09-18';
        $dir = sys_get_temp_dir() . '/benehom-numa-publico-global-' . bin2hex(random_bytes(8));
        $locker = $this->newConnection();

        $this->insertRow('2026-09-01', 0, 0, 0);
        self::assertTrue(mkdir($dir, 0700));

        $locker->beginTransaction();

        try {
            $stmt = $locker->prepare('SELECT fecha FROM numa_uso_proveedor WHERE fecha = :fecha FOR UPDATE');
            $stmt->execute([':fecha' => '2026-09-01']);
            self::assertSame('2026-09-01', $stmt->fetchColumn());

            $processA = $this->startConcurrentPublicGlobalCallProcess($visitorA, $dir, 'a', '2026-09-18 10:00:00');
            $processB = $this->startConcurrentPublicGlobalCallProcess($visitorB, $dir, 'b', '2026-09-18 10:00:00');

            $this->waitForFiles([$processA['ready_file'], $processB['ready_file']]);
            file_put_contents($dir . '/start', '1');
            $this->waitForFiles([$processA['attempt_file'], $processB['attempt_file']]);
            usleep(200000);

            self::assertTrue($this->processIsRunning($processA));
            self::assertTrue($this->processIsRunning($processB));

            $locker->commit();

            $resultA = $this->collectConcurrentGlobalCallProcess($processA);
            $resultB = $this->collectConcurrentGlobalCallProcess($processB);
        } finally {
            if ($locker->inTransaction()) {
                $locker->rollBack();
            }

            foreach (glob($dir . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            if (is_dir($dir)) {
                rmdir($dir);
            }
        }

        $statuses = [$resultA['status'], $resultB['status']];
        sort($statuses);

        self::assertSame(['limit', 'started'], $statuses);
        self::assertSame(1, $this->llamadasPublicasDia('2026-09-18'));
        self::assertSame(1, $this->confirmadasPublicas($visitorA) + $this->confirmadasPublicas($visitorB));
        self::assertSame(0, $this->pendientesPublicas($visitorA) + $this->pendientesPublicas($visitorB));
    }

    public function testElErrorDeLimiteNoRevelaProveedorNiSecretos(): void
    {
        $_ENV['NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT'] = '0';
        $repo = $this->repo('2026-07-25 10:00:00');

        try {
            $repo->iniciarLlamada();
            self::fail('Se esperaba una excepcion de limite global.');
        } catch (\NumaGlobalLimiteAlcanzado $e) {
            self::assertSame('NUMA_GLOBAL_LIMIT_REACHED', $e->getMessage());
            self::assertStringNotContainsString('gemini', $e->getMessage());
            self::assertStringNotContainsString('key', $e->getMessage());
            self::assertStringNotContainsString('token', $e->getMessage());
        }
    }

    public function testConfirmacionConjuntaRevierteUsuarioYGlobalSiFallaAntesDelProveedor(): void
    {
        $now = new DateTimeImmutable('2026-07-25 10:00:00');
        $usuarioId = $this->crearUsuario();
        $usage = new NumaUsoFallaDespuesDeConfirmar($this->db, $now);
        $global = new \NumaConsumoGlobal($this->db, $now);
        $budget = new \NumaPaidCallBudget(new \NumaPrivateUsageBudget($usage, $usuarioId), 3);
        $transportCalls = 0;
        $provider = new \GeminiNumaProvider(
            'server-key',
            'gemini-model',
            transport: static function () use (&$transportCalls): array {
                $transportCalls++;

                return ['status' => 200, 'body' => '{}'];
            },
            consumption: new \NumaProviderConsumptionChain($budget, $global),
        );

        try {
            $provider->respond(new \NumaRequest('Pregunta de prueba'));
            self::fail('Se esperaba el fallo simulado de confirmacion.');
        } catch (\NumaProviderException $exception) {
            self::assertSame('NUMA_USAGE_ERROR', $exception->providerError()->safeCode());
        }

        self::assertSame(0, $transportCalls);
        self::assertSame(0, $usage->llamadasPagadasConfirmadasDia($usuarioId, '2026-07-25'));
        self::assertSame(0, $global->llamadasDia('2026-07-25'));
        self::assertSame(0, $this->reservasPendientes($usuarioId));
        self::assertSame(['revertida'], $this->estadosReservas($usuarioId));
    }

    private function repo(string $now): \NumaConsumoGlobal
    {
        return new \NumaConsumoGlobal($this->db, new DateTimeImmutable($now));
    }

    private function crearUsuario(): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO usuarios (usuario, email, password) VALUES (:usuario, :email, :password)'
        );
        $stmt->execute([
            ':usuario' => 'Usuario Numa',
            ':email' => 'numa-atomic-' . bin2hex(random_bytes(8)) . '@example.test',
            ':password' => password_hash('Password-test-123', PASSWORD_DEFAULT),
        ]);

        $usuarioId = (int) $this->db->lastInsertId();
        $this->userIds[] = $usuarioId;

        return $usuarioId;
    }

    private function visitorHash(): string
    {
        $hash = hash('sha256', random_bytes(32));
        $this->visitorHashes[] = $hash;

        return $hash;
    }

    private function confirmadasPublicas(string $visitorHash): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(cantidad_confirmada), 0) FROM numa_uso_publico WHERE visitante_hash = :hash'
        );
        $stmt->execute([':hash' => $visitorHash]);

        return (int) $stmt->fetchColumn();
    }

    private function pendientesPublicas(string $visitorHash): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM numa_reservas_publicas WHERE visitante_hash = :hash AND estado = 'pendiente'"
        );
        $stmt->execute([':hash' => $visitorHash]);

        return (int) $stmt->fetchColumn();
    }

    private function llamadasPublicasDia(string $fecha): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(llamadas_publicas, 0) FROM numa_uso_proveedor WHERE fecha = :fecha'
        );
        $stmt->execute([':fecha' => $fecha]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{process:resource,pipes:array<int,resource>,ready_file:string,attempt_file:string}
     */
    private function startConcurrentPublicGlobalCallProcess(string $visitorHash, string $dir, string $label, string $now): array
    {
        $config = require CONFIG_PATH . '/database.php';
        $readyFile = $dir . '/ready-' . $label;
        $attemptFile = $dir . '/attempt-' . $label;
        $payload = json_encode([
            'base_path' => BASE_PATH,
            'db' => $config,
            'visitante_hash' => $visitorHash,
            'now' => $now,
            'daily_call_limit' => '100',
            'monthly_call_limit' => '1000',
            'daily_token_limit' => '50000',
            'monthly_token_limit' => '300000',
            'public_daily_call_limit' => '1',
            'public_monthly_call_limit' => '400',
            'public_daily_limit' => '5',
            'public_monthly_limit' => '20',
            'reservation_ttl' => '120',
            'max_input_tokens' => '5000',
            'max_output_tokens' => '220',
            'ready_file' => $readyFile,
            'attempt_file' => $attemptFile,
            'start_file' => $dir . '/start',
        ]);

        self::assertIsString($payload);

        $code = <<<'PHP'
$payload = json_decode($argv[1] ?? '', true);

if (!is_array($payload)) {
    fwrite(STDERR, "Payload inválido\n");
    exit(2);
}

define('BASE_PATH', (string) $payload['base_path']);
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');

require APP_PATH . '/helpers/utils.php';
require APP_PATH . '/models/NumaPublicUso.php';
require APP_PATH . '/models/NumaConsumoGlobal.php';
require APP_PATH . '/services/NumaUsageBudget.php';
require APP_PATH . '/services/NumaService.php';

$_ENV['NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT'] = (string) $payload['daily_call_limit'];
$_ENV['NUMA_GLOBAL_MONTHLY_PROVIDER_CALL_LIMIT'] = (string) $payload['monthly_call_limit'];
$_ENV['NUMA_GLOBAL_DAILY_TOKEN_LIMIT'] = (string) $payload['daily_token_limit'];
$_ENV['NUMA_GLOBAL_MONTHLY_TOKEN_LIMIT'] = (string) $payload['monthly_token_limit'];
$_ENV['NUMA_PUBLIC_GLOBAL_DAILY_CALL_LIMIT'] = (string) $payload['public_daily_call_limit'];
$_ENV['NUMA_PUBLIC_GLOBAL_MONTHLY_CALL_LIMIT'] = (string) $payload['public_monthly_call_limit'];
$_ENV['NUMA_PUBLIC_DAILY_LIMIT'] = (string) $payload['public_daily_limit'];
$_ENV['NUMA_PUBLIC_MONTHLY_LIMIT'] = (string) $payload['public_monthly_limit'];
$_ENV['NUMA_RESERVATION_TTL_SECONDS'] = (string) $payload['reservation_ttl'];
$_ENV['NUMA_MAX_INPUT_TOKENS'] = (string) $payload['max_input_tokens'];
$_ENV['NUMA_MAX_OUTPUT_TOKENS'] = (string) $payload['max_output_tokens'];

$db = $payload['db'];

if (!is_array($db)) {
    fwrite(STDERR, "Configuración de base de datos inválida\n");
    exit(2);
}

$pdo = new PDO(
    "mysql:host={$db['host']};port={$db['port']};dbname={$db['dbname']};charset=utf8mb4",
    (string) $db['user'],
    (string) $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

file_put_contents((string) $payload['ready_file'], '1');
$deadline = microtime(true) + 10;

while (!is_file((string) $payload['start_file'])) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, "Timeout esperando inicio\n");
        exit(2);
    }

    usleep(1000);
}

file_put_contents((string) $payload['attempt_file'], '1');

$publicUso = new NumaPublicUso($pdo, new DateTimeImmutable((string) $payload['now']));
$budget = new NumaPaidCallBudget(new NumaPublicUsageBudget($publicUso, (string) $payload['visitante_hash']), 3);
$global = NumaConsumoGlobal::forPublicLlm($pdo, new DateTimeImmutable((string) $payload['now']));
$chain = new NumaProviderConsumptionChain($budget, $global);

try {
    $chain->iniciarLlamada();
    echo json_encode(['status' => 'started']);
    exit(0);
} catch (NumaGlobalLimiteAlcanzado $e) {
    echo json_encode(['status' => 'limit', 'code' => $e->getMessage()]);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e::class . ': ' . $e->getMessage() . "\n");
    exit(1);
}
PHP;

        $process = proc_open(
            [PHP_BINARY, '-d', 'variables_order=EGPCS', '-r', $code, $payload],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            BASE_PATH
        );

        self::assertIsResource($process);
        fclose($pipes[0]);

        return [
            'process' => $process,
            'pipes' => $pipes,
            'ready_file' => $readyFile,
            'attempt_file' => $attemptFile,
        ];
    }

    private function reservasPendientes(int $usuarioId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM numa_reservas WHERE usuario_id = :usuario_id AND estado = 'pendiente'"
        );
        $stmt->execute([':usuario_id' => $usuarioId]);

        return (int) $stmt->fetchColumn();
    }

    /** @return list<string> */
    private function estadosReservas(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT estado FROM numa_reservas WHERE usuario_id = :usuario_id ORDER BY created_at, id'
        );
        $stmt->execute([':usuario_id' => $usuarioId]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @return array{llamadas:int,input_tokens:int,output_tokens:int}
     */
    private function row(string $fecha): array
    {
        $stmt = $this->db->prepare(
            'SELECT llamadas, input_tokens, output_tokens FROM numa_uso_proveedor WHERE fecha = :fecha'
        );
        $stmt->execute([':fecha' => $fecha]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($row, "No existe fila para la fecha $fecha.");

        return [
            'llamadas' => (int) $row['llamadas'],
            'input_tokens' => (int) $row['input_tokens'],
            'output_tokens' => (int) $row['output_tokens'],
        ];
    }

    private function insertRow(string $fecha, int $llamadas, int $inputTokens, int $outputTokens): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO numa_uso_proveedor (fecha, llamadas, input_tokens, output_tokens)
             VALUES (:fecha, :llamadas, :input, :output)
             ON DUPLICATE KEY UPDATE
               llamadas = VALUES(llamadas),
               input_tokens = VALUES(input_tokens),
               output_tokens = VALUES(output_tokens)'
        );
        $stmt->execute([
            ':fecha' => $fecha,
            ':llamadas' => $llamadas,
            ':input' => $inputTokens,
            ':output' => $outputTokens,
        ]);

        $this->fechasCreadas[] = $fecha;
    }

    /**
     * @return array{process:resource,pipes:array<int,resource>,ready_file:string,attempt_file:string}
     */
    private function startConcurrentGlobalCallProcess(string $dir, string $label, string $now): array
    {
        $config = require CONFIG_PATH . '/database.php';
        $readyFile = $dir . '/ready-' . $label;
        $attemptFile = $dir . '/attempt-' . $label;
        $payload = json_encode([
            'base_path' => BASE_PATH,
            'db' => $config,
            'now' => $now,
            'daily_call_limit' => '100',
            'monthly_call_limit' => '1',
            'daily_token_limit' => '50000',
            'monthly_token_limit' => '300000',
            'max_input_tokens' => '5000',
            'max_output_tokens' => '220',
            'ready_file' => $readyFile,
            'attempt_file' => $attemptFile,
            'start_file' => $dir . '/start',
        ]);

        self::assertIsString($payload);

        $code = <<<'PHP'
$payload = json_decode($argv[1] ?? '', true);

if (!is_array($payload)) {
    fwrite(STDERR, "Payload invalido\n");
    exit(2);
}

define('BASE_PATH', (string) $payload['base_path']);
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');

require APP_PATH . '/models/NumaConsumoGlobal.php';

$_ENV['NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT'] = (string) $payload['daily_call_limit'];
$_ENV['NUMA_GLOBAL_MONTHLY_PROVIDER_CALL_LIMIT'] = (string) $payload['monthly_call_limit'];
$_ENV['NUMA_GLOBAL_DAILY_TOKEN_LIMIT'] = (string) $payload['daily_token_limit'];
$_ENV['NUMA_GLOBAL_MONTHLY_TOKEN_LIMIT'] = (string) $payload['monthly_token_limit'];
$_ENV['NUMA_MAX_INPUT_TOKENS'] = (string) $payload['max_input_tokens'];
$_ENV['NUMA_MAX_OUTPUT_TOKENS'] = (string) $payload['max_output_tokens'];

$db = $payload['db'];

if (!is_array($db)) {
    fwrite(STDERR, "Configuracion de base de datos invalida\n");
    exit(2);
}

$pdo = new PDO(
    "mysql:host={$db['host']};port={$db['port']};dbname={$db['dbname']};charset=utf8mb4",
    (string) $db['user'],
    (string) $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

file_put_contents((string) $payload['ready_file'], '1');
$deadline = microtime(true) + 10;

while (!is_file((string) $payload['start_file'])) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, "Timeout esperando inicio\n");
        exit(2);
    }

    usleep(1000);
}

file_put_contents((string) $payload['attempt_file'], '1');
$repo = new NumaConsumoGlobal($pdo, new DateTimeImmutable((string) $payload['now']));

try {
    $repo->iniciarLlamada();
    echo json_encode(['status' => 'started']);
    exit(0);
} catch (NumaGlobalLimiteAlcanzado $e) {
    echo json_encode(['status' => 'limit', 'code' => $e->getMessage()]);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e::class . ': ' . $e->getMessage() . "\n");
    exit(1);
}
PHP;

        $process = proc_open(
            [PHP_BINARY, '-d', 'variables_order=EGPCS', '-r', $code, $payload],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            BASE_PATH
        );

        self::assertIsResource($process);
        fclose($pipes[0]);

        return [
            'process' => $process,
            'pipes' => $pipes,
            'ready_file' => $readyFile,
            'attempt_file' => $attemptFile,
        ];
    }

    /**
     * @param list<string> $files
     */
    private function waitForFiles(array $files): void
    {
        $deadline = microtime(true) + 10;

        do {
            $ready = true;

            foreach ($files as $file) {
                if (!is_file($file)) {
                    $ready = false;
                    break;
                }
            }

            if ($ready) {
                return;
            }

            usleep(10000);
        } while (microtime(true) <= $deadline);

        self::fail('Los procesos concurrentes no llegaron a la barrera de inicio.');
    }

    /** @param array{process:resource,pipes:array<int,resource>,ready_file:string,attempt_file:string} $process */
    private function processIsRunning(array $process): bool
    {
        $status = proc_get_status($process['process']);

        return is_array($status) && ($status['running'] ?? false) === true;
    }

    /**
     * @param array{process:resource,pipes:array<int,resource>,ready_file:string,attempt_file:string} $process
     * @return array{status:string,code?:string}
     */
    private function collectConcurrentGlobalCallProcess(array $process): array
    {
        $stdout = stream_get_contents($process['pipes'][1]);
        $stderr = stream_get_contents($process['pipes'][2]);
        fclose($process['pipes'][1]);
        fclose($process['pipes'][2]);
        $exitCode = proc_close($process['process']);

        self::assertSame(0, $exitCode, 'Proceso concurrente fallido: ' . (string) $stderr);
        $decoded = json_decode((string) $stdout, true);

        self::assertIsArray($decoded, 'Salida concurrente invalida: ' . (string) $stdout);
        self::assertIsString($decoded['status'] ?? null);

        return $decoded;
    }

    private function llamadasMes(string $monthStart, string $nextMonthStart): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(llamadas), 0)
             FROM numa_uso_proveedor
             WHERE fecha >= :month_start AND fecha < :next_month_start'
        );
        $stmt->execute([
            ':month_start' => $monthStart,
            ':next_month_start' => $nextMonthStart,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{status:int,body:string} */
    private function validEmbeddingResponse(?int $promptTokenCount = null): array
    {
        $body = [
            'embedding' => [
                'values' => [0.1, 0.2],
            ],
        ];

        if ($promptTokenCount !== null) {
            $body['usageMetadata'] = [
                'promptTokenCount' => $promptTokenCount,
            ];
        }

        return [
            'status' => 200,
            'body' => json_encode($body, JSON_THROW_ON_ERROR),
        ];
    }

    private function newConnection(): PDO
    {
        $config = require CONFIG_PATH . '/database.php';
        $pdo = new PDO(
            "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4",
            $config['user'],
            $config['password']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private function limpiarFilasDePrueba(): void
    {
        if ($this->fechasCreadas !== []) {
            $fechas = implode(',', array_map([$this->db, 'quote'], array_unique($this->fechasCreadas)));
            $this->db->exec("DELETE FROM numa_uso_proveedor WHERE fecha IN ($fechas)");
        }
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
            'NUMA_MAX_MESSAGE_LENGTH',
            'NUMA_MAX_RAG_CHUNK_CHARS',
            'NUMA_DAILY_LIMIT',
            'NUMA_MONTHLY_LIMIT',
            'NUMA_RESERVATION_TTL_SECONDS',
            'NUMA_PUBLIC_GLOBAL_DAILY_CALL_LIMIT',
            'NUMA_PUBLIC_GLOBAL_MONTHLY_CALL_LIMIT',
        ];
    }
}
