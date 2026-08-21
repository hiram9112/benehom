<?php

declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/models/Database.php';
require_once APP_PATH . '/models/NumaUso.php';

final class NumaUsoTest extends TestCase
{
    private PDO $db;

    /** @var list<int> */
    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Database::getConnection();
        $this->ensureSchemaExists();
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

        parent::tearDown();
    }

    public function testPrimeraReservaDescuentaRestantes(): void
    {
        $usuarioId = $this->crearUsuario();
        $repo = $this->repo('2026-07-21 10:00:00');

        $reservaId = $repo->reservar($usuarioId);
        $estado = $repo->estado($usuarioId);

        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $reservaId);
        self::assertSame(0, $estado['daily_used']);
        self::assertSame(4, $estado['daily_remaining']);
        self::assertSame(19, $estado['monthly_remaining']);
    }

    public function testQuintaLlamadaPagadaPermitidaYSextaRechazada(): void
    {
        $usuarioId = $this->crearUsuario();
        $this->insertUso($usuarioId, '2026-07-21', 4);
        $repo = $this->repo('2026-07-21 10:00:00');

        $reservaId = $repo->reservar($usuarioId);

        self::assertNotSame('', $reservaId);
        self::assertSame(0, $repo->estado($usuarioId)['daily_remaining']);

        $this->expectException(\NumaUsoLimiteAlcanzado::class);
        $this->expectExceptionMessage('NUMA_DAILY_LIMIT_REACHED');

        $repo->reservar($usuarioId);
    }

    public function testVigesimaLlamadaPagadaMensualPermitidaYLlamada21Rechazada(): void
    {
        $usuarioId = $this->crearUsuario();
        $this->insertUso($usuarioId, '2026-07-01', 19);
        $repo = $this->repo('2026-07-21 10:00:00');

        $repo->reservar($usuarioId);

        self::assertSame(0, $repo->estado($usuarioId)['monthly_remaining']);

        $this->expectException(\NumaUsoLimiteAlcanzado::class);
        $this->expectExceptionMessage('NUMA_MONTHLY_LIMIT_REACHED');

        $repo->reservar($usuarioId);
    }

    public function testReinicioDiarioYMensual(): void
    {
        $usuarioId = $this->crearUsuario();
        $this->insertUso($usuarioId, '2026-06-30', 20);
        $this->insertUso($usuarioId, '2026-07-20', 5);
        $repo = $this->repo('2026-07-21 10:00:00');
        $estado = $repo->estado($usuarioId);

        self::assertSame(0, $estado['daily_used']);
        self::assertSame(5, $estado['daily_remaining']);
        self::assertSame(5, $estado['monthly_used']);
        self::assertSame(15, $estado['monthly_remaining']);
    }

    public function testCantidadConfirmadaRepresentaLlamadasPagadasConfirmadas(): void
    {
        $usuarioId = $this->crearUsuario();
        $this->insertUso($usuarioId, '2026-07-20', 2);
        $this->insertUso($usuarioId, '2026-07-21', 3);
        $repo = $this->repo('2026-07-21 10:00:00');

        self::assertSame(3, $repo->llamadasPagadasConfirmadasDia($usuarioId, '2026-07-21'));
        self::assertSame(5, $repo->llamadasPagadasConfirmadasMes($usuarioId, '2026-07-01', '2026-08-01'));
    }

    public function testDosUsuariosIndependientes(): void
    {
        $usuarioA = $this->crearUsuario();
        $usuarioB = $this->crearUsuario();
        $this->insertUso($usuarioA, '2026-07-21', 5);
        $repo = $this->repo('2026-07-21 10:00:00');

        $reservaB = $repo->reservar($usuarioB);

        self::assertNotSame('', $reservaB);
        self::assertSame(4, $repo->estado($usuarioB)['daily_remaining']);
    }

    public function testUsuarioExentoSuperaSuCuotaPeroConservaElUsoConfirmado(): void
    {
        $usuarioId = $this->crearUsuario();
        $this->insertUso($usuarioId, '2026-07-21', 5);
        $repo = $this->repo('2026-07-21 10:00:00');
        $previousExemptUsers = $_ENV['NUMA_LIMIT_EXEMPT_USER_IDS'] ?? null;

        $_ENV['NUMA_LIMIT_EXEMPT_USER_IDS'] = (string) $usuarioId;

        try {
            $reservationId = $repo->reservar($usuarioId);

            self::assertTrue($repo->confirmar($reservationId));
            self::assertSame(6, $repo->llamadasPagadasConfirmadasDia($usuarioId));
        } finally {
            if ($previousExemptUsers === null) {
                unset($_ENV['NUMA_LIMIT_EXEMPT_USER_IDS']);
            } else {
                $_ENV['NUMA_LIMIT_EXEMPT_USER_IDS'] = $previousExemptUsers;
            }
        }
    }

    public function testReservasConConexionesSeparadasBloqueanElLimite(): void
    {
        $_ENV['NUMA_DAILY_LIMIT'] = '1';
        $_ENV['NUMA_MONTHLY_LIMIT'] = '20';
        $usuarioId = $this->crearUsuario();

        $repoA = new \NumaUso($this->newConnection(), new DateTimeImmutable('2026-07-21 10:00:00'));
        $repoB = new \NumaUso($this->newConnection(), new DateTimeImmutable('2026-07-21 10:00:00'));

        $repoA->reservar($usuarioId);

        $this->expectException(\NumaUsoLimiteAlcanzado::class);
        $this->expectExceptionMessage('NUMA_DAILY_LIMIT_REACHED');

        $repoB->reservar($usuarioId);
    }

    public function testReservaBloqueaRangoMensualDeUsoNoSoloElDia(): void
    {
        $usuarioId = $this->crearUsuario();
        $this->insertUso($usuarioId, '2026-07-01', 0);
        $locker = $this->newConnection();
        $contender = $this->newConnection();

        $locker->beginTransaction();

        try {
            $stmt = $locker->prepare(
                'SELECT id FROM numa_uso WHERE usuario_id = :usuario_id AND fecha = :fecha FOR UPDATE'
            );
            $stmt->execute([':usuario_id' => $usuarioId, ':fecha' => '2026-07-01']);

            self::assertNotFalse($stmt->fetchColumn());

            $contender->exec('SET SESSION innodb_lock_wait_timeout = 1');
            $repo = new \NumaUso($contender, new DateTimeImmutable('2026-07-31 23:59:59'));

            try {
                $repo->reservar($usuarioId);
                self::fail('La reserva no esperó el bloqueo mensual existente.');
            } catch (\PDOException $e) {
                self::assertSame(1205, $e->errorInfo[1] ?? null);
            }
        } finally {
            if ($locker->inTransaction()) {
                $locker->rollBack();
            }
        }

        self::assertSame(0, $this->reservasDelUsuario($usuarioId));
    }

    public function testReservasConcurrentesConMesVacioNoSuperanElLimite(): void
    {
        $_ENV['NUMA_DAILY_LIMIT'] = '5';
        $_ENV['NUMA_MONTHLY_LIMIT'] = '1';
        $_ENV['NUMA_RESERVATION_TTL_SECONDS'] = '2678400';
        $usuarioId = $this->crearUsuario();
        $dir = sys_get_temp_dir() . '/benehom-numa-uso-' . bin2hex(random_bytes(8));

        self::assertTrue(mkdir($dir, 0700));
        self::assertSame(0, $this->reservasDelUsuario($usuarioId));

        $processA = $this->startConcurrentReservationProcess($usuarioId, $dir, 'a', '2026-07-21 10:00:00');
        $processB = $this->startConcurrentReservationProcess($usuarioId, $dir, 'b', '2026-07-22 10:00:00');

        try {
            $this->waitForReadyFiles([$processA['ready_file'], $processB['ready_file']]);
            file_put_contents($dir . '/start', '1');

            $resultA = $this->collectConcurrentReservationProcess($processA);
            $resultB = $this->collectConcurrentReservationProcess($processB);
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            rmdir($dir);
        }

        $statuses = [$resultA['status'], $resultB['status']];
        sort($statuses);

        self::assertSame(['limit', 'reserved'], $statuses);
        self::assertSame(1, $this->reservasDelUsuario($usuarioId));
        self::assertSame(0, $this->repo('2026-07-21 10:00:00')->estado($usuarioId)['monthly_remaining']);
    }

    public function testConfirmacionExactamenteUnaVez(): void
    {
        $usuarioId = $this->crearUsuario();
        $repo = $this->repo('2026-07-21 10:00:00');
        $reservaId = $repo->reservar($usuarioId);

        self::assertTrue($repo->confirmar($reservaId));
        self::assertFalse($repo->confirmar($reservaId));
        self::assertSame(1, $repo->estado($usuarioId)['daily_used']);
    }

    public function testReversionExactamenteUnaVezYNoPermiteConfirmarDespues(): void
    {
        $usuarioId = $this->crearUsuario();
        $repo = $this->repo('2026-07-21 10:00:00');
        $reservaId = $repo->reservar($usuarioId);

        self::assertTrue($repo->revertir($reservaId));
        self::assertFalse($repo->revertir($reservaId));
        self::assertFalse($repo->confirmar($reservaId));
        self::assertSame(0, $repo->estado($usuarioId)['daily_used']);
        self::assertSame(5, $repo->estado($usuarioId)['daily_remaining']);
    }

    public function testRevertirDespuesDeConfirmarNoRestaConsumo(): void
    {
        $usuarioId = $this->crearUsuario();
        $repo = $this->repo('2026-07-21 10:00:00');
        $reservaId = $repo->reservar($usuarioId);

        self::assertTrue($repo->confirmar($reservaId));
        self::assertFalse($repo->revertir($reservaId));
        self::assertSame(1, $repo->estado($usuarioId)['daily_used']);
    }

    public function testReservaExpiradaDejaDeBloquearElLimite(): void
    {
        $_ENV['NUMA_DAILY_LIMIT'] = '1';
        $_ENV['NUMA_RESERVATION_TTL_SECONDS'] = '60';
        $usuarioId = $this->crearUsuario();

        $this->repo('2026-07-21 10:00:00')->reservar($usuarioId);
        $repoDespues = $this->repo('2026-07-21 10:02:00');

        self::assertSame(1, $repoDespues->expirarReservasVencidas());
        self::assertSame(1, $repoDespues->estado($usuarioId)['daily_remaining']);
        self::assertNotSame('', $repoDespues->reservar($usuarioId));
    }

    public function testStatusSinConsumoYConReservasActivas(): void
    {
        $usuarioId = $this->crearUsuario();
        $repo = $this->repo('2026-07-21 10:00:00');

        self::assertSame([
            'daily_used' => 0,
            'daily_limit' => 5,
            'daily_remaining' => 5,
            'monthly_used' => 0,
            'monthly_limit' => 20,
            'monthly_remaining' => 20,
        ], $repo->estado($usuarioId));

        $repo->reservar($usuarioId);

        self::assertSame(4, $repo->estado($usuarioId)['daily_remaining']);
        self::assertSame(19, $repo->estado($usuarioId)['monthly_remaining']);
    }

    public function testLasTablasNoGuardanMensajesNiRespuestas(): void
    {
        $columnsUso = $this->columns('numa_uso');
        $columnsReservas = $this->columns('numa_reservas');
        $joined = implode(',', array_merge($columnsUso, $columnsReservas));

        self::assertStringNotContainsString('mensaje', $joined);
        self::assertStringNotContainsString('message', $joined);
        self::assertStringNotContainsString('pregunta', $joined);
        self::assertStringNotContainsString('respuesta', $joined);
        self::assertStringNotContainsString('prompt', $joined);
    }

    private function repo(string $now): \NumaUso
    {
        return new \NumaUso($this->db, new DateTimeImmutable($now));
    }

    private function crearUsuario(): int
    {
        $email = 'numa-' . bin2hex(random_bytes(8)) . '@example.test';
        $stmt = $this->db->prepare(
            'INSERT INTO usuarios (usuario, email, password) VALUES (:usuario, :email, :password)'
        );
        $stmt->execute([
            ':usuario' => 'Usuario Numa',
            ':email' => $email,
            ':password' => password_hash('Password-test-123', PASSWORD_DEFAULT),
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->userIds[] = $id;

        return $id;
    }

    private function insertUso(int $usuarioId, string $fecha, int $cantidad): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO numa_uso (usuario_id, fecha, cantidad_confirmada)
             VALUES (:usuario_id, :fecha, :cantidad)
             ON DUPLICATE KEY UPDATE cantidad_confirmada = VALUES(cantidad_confirmada)'
        );
        $stmt->execute([':usuario_id' => $usuarioId, ':fecha' => $fecha, ':cantidad' => $cantidad]);
    }

    private function reservasDelUsuario(int $usuarioId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM numa_reservas WHERE usuario_id = :usuario_id');
        $stmt->execute([':usuario_id' => $usuarioId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{process:resource,pipes:array<int,resource>,ready_file:string}
     */
    private function startConcurrentReservationProcess(int $usuarioId, string $dir, string $label, string $now): array
    {
        $config = require CONFIG_PATH . '/database.php';
        $readyFile = $dir . '/ready-' . $label;
        $payload = json_encode([
            'base_path' => BASE_PATH,
            'db' => $config,
            'usuario_id' => $usuarioId,
            'now' => $now,
            'daily_limit' => '5',
            'monthly_limit' => '1',
            'reservation_ttl' => '2678400',
            'ready_file' => $readyFile,
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
require APP_PATH . '/models/Database.php';
require APP_PATH . '/models/NumaUso.php';

$_ENV['NUMA_DAILY_LIMIT'] = (string) $payload['daily_limit'];
$_ENV['NUMA_MONTHLY_LIMIT'] = (string) $payload['monthly_limit'];
$_ENV['NUMA_RESERVATION_TTL_SECONDS'] = (string) $payload['reservation_ttl'];

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

$repo = new NumaUso($pdo, new DateTimeImmutable((string) $payload['now']));

try {
    $repo->reservar((int) $payload['usuario_id']);
    echo json_encode(['status' => 'reserved']);
    exit(0);
} catch (NumaUsoLimiteAlcanzado $e) {
    echo json_encode(['status' => 'limit', 'code' => $e->limitCode()]);
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
        ];
    }

    /**
     * @param list<string> $files
     */
    private function waitForReadyFiles(array $files): void
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

    /**
     * @param array{process:resource,pipes:array<int,resource>,ready_file:string} $process
     * @return array{status:string,code?:string}
     */
    private function collectConcurrentReservationProcess(array $process): array
    {
        $stdout = stream_get_contents($process['pipes'][1]);
        $stderr = stream_get_contents($process['pipes'][2]);
        fclose($process['pipes'][1]);
        fclose($process['pipes'][2]);
        $exitCode = proc_close($process['process']);

        self::assertSame(0, $exitCode, 'Proceso concurrente fallido: ' . (string) $stderr);
        $decoded = json_decode((string) $stdout, true);

        self::assertIsArray($decoded, 'Salida concurrente inválida: ' . (string) $stdout);
        self::assertIsString($decoded['status'] ?? null);

        return $decoded;
    }

    /**
     * @return list<string>
     */
    private function columns(string $table): array
    {
        $stmt = $this->db->query('SHOW COLUMNS FROM ' . $table);

        self::assertNotFalse($stmt);

        return array_map(static fn (array $row): string => (string) $row['Field'], $stmt->fetchAll(PDO::FETCH_ASSOC));
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
}
