<?php

declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/models/Database.php';
require_once APP_PATH . '/models/NumaPublicUso.php';
require_once APP_PATH . '/services/NumaUsageBudget.php';
require_once APP_PATH . '/services/NumaService.php';

final class NumaPublicUsoTest extends TestCase
{
    private PDO $db;

    /** @var list<string> */
    private array $visitorHashes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Database::getConnection();
        $this->ensureSchemaExists();
        $_ENV['NUMA_PUBLIC_DAILY_LIMIT'] = '15';
        $_ENV['NUMA_PUBLIC_MONTHLY_LIMIT'] = '60';
        $_ENV['NUMA_RESERVATION_TTL_SECONDS'] = '120';
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

    public function testReservaPublicaDescuentaLaCuotaSinGuardarIdentidadOriginal(): void
    {
        $hash = $this->visitorHash();
        $repo = $this->repo('2026-08-17 10:00:00');

        $reservation = $repo->reservar($hash);
        $status = $repo->estado($hash);

        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $reservation);
        self::assertSame(14, $status['daily_remaining']);
        self::assertSame(59, $status['monthly_remaining']);

        $columns = implode(',', $this->columns('numa_uso_publico')) . ',' . implode(',', $this->columns('numa_reservas_publicas'));
        self::assertStringContainsString('visitante_hash', $columns);
        self::assertStringNotContainsString('usuario_id', $columns);
        self::assertStringNotContainsString('cookie', $columns);
        self::assertStringNotContainsString('message', $columns);
        self::assertStringNotContainsString('prompt', $columns);
    }

    public function testLimitesPublicosDiarioYMensualSeAplicanAlMismoVisitante(): void
    {
        $hash = $this->visitorHash();
        $_ENV['NUMA_PUBLIC_DAILY_LIMIT'] = '1';
        $_ENV['NUMA_PUBLIC_MONTHLY_LIMIT'] = '1';
        $repo = $this->repo('2026-08-17 10:00:00');

        $repo->reservar($hash);

        $this->expectException(\NumaUsoLimiteAlcanzado::class);
        $this->expectExceptionMessage('NUMA_DAILY_LIMIT_REACHED');
        $repo->reservar($hash);
    }

    public function testBypassLocalPermiteReservaPublicaPeroMantieneSuConfirmacion(): void
    {
        $hash = $this->visitorHash();
        $_ENV['NUMA_PUBLIC_DAILY_LIMIT'] = '1';
        $_ENV['NUMA_PUBLIC_MONTHLY_LIMIT'] = '1';
        $previousAppEnv = $_ENV['APP_ENV'] ?? null;
        $previousBypass = $_ENV['NUMA_BYPASS_LIMITS'] ?? null;
        $_ENV['APP_ENV'] = 'local';
        $_ENV['NUMA_BYPASS_LIMITS'] = 'true';
        $repo = $this->repo('2026-08-17 10:00:00');

        try {
            $firstReservation = $repo->reservar($hash);
            $secondReservation = $repo->reservar($hash);

            self::assertTrue($repo->confirmar($firstReservation));
            self::assertTrue($repo->confirmar($secondReservation));
            self::assertSame(2, $repo->estado($hash)['daily_used']);
        } finally {
            if ($previousAppEnv === null) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $previousAppEnv;
            }

            if ($previousBypass === null) {
                unset($_ENV['NUMA_BYPASS_LIMITS']);
            } else {
                $_ENV['NUMA_BYPASS_LIMITS'] = $previousBypass;
            }
        }
    }

    public function testLimiteMensualPublicoSeAplicaAunqueQuedeCuotaDiaria(): void
    {
        $hash = $this->visitorHash();
        $_ENV['NUMA_PUBLIC_DAILY_LIMIT'] = '15';
        $_ENV['NUMA_PUBLIC_MONTHLY_LIMIT'] = '1';
        $repo = $this->repo('2026-08-17 10:00:00');

        $repo->reservar($hash);

        $this->expectException(\NumaUsoLimiteAlcanzado::class);
        $this->expectExceptionMessage('NUMA_MONTHLY_LIMIT_REACHED');
        $repo->reservar($hash);
    }

    public function testConfirmacionYReversionPublicasSonIdempotentes(): void
    {
        $hash = $this->visitorHash();
        $repo = $this->repo('2026-08-17 10:00:00');
        $confirmed = $repo->reservar($hash);
        $reverted = $repo->reservar($hash);

        self::assertTrue($repo->confirmar($confirmed));
        self::assertFalse($repo->confirmar($confirmed));
        self::assertTrue($repo->revertir($reverted));
        self::assertFalse($repo->revertir($reverted));
        self::assertSame(1, $repo->estado($hash)['daily_used']);
    }

    public function testReservaPublicaExpiradaDejaDeBloquearLaCuota(): void
    {
        $hash = $this->visitorHash();
        $_ENV['NUMA_PUBLIC_DAILY_LIMIT'] = '1';
        $_ENV['NUMA_RESERVATION_TTL_SECONDS'] = '60';
        $this->repo('2026-08-17 10:00:00')->reservar($hash);
        $repo = $this->repo('2026-08-17 10:02:00');

        self::assertSame(1, $repo->expirarReservasVencidas());
        self::assertSame(1, $repo->estado($hash)['daily_remaining']);
        self::assertNotSame('', $repo->reservar($hash));
    }

    public function testVisitantesPublicosPermanecenAislados(): void
    {
        $visitorA = $this->visitorHash();
        $visitorB = $this->visitorHash();
        $_ENV['NUMA_PUBLIC_DAILY_LIMIT'] = '1';
        $repo = $this->repo('2026-08-17 10:00:00');

        $repo->reservar($visitorA);
        $repo->reservar($visitorB);

        self::assertSame(0, $repo->estado($visitorA)['daily_used']);
        self::assertSame(0, $repo->estado($visitorB)['daily_used']);
        self::assertSame(0, $repo->estado($visitorA)['daily_remaining']);
        self::assertSame(0, $repo->estado($visitorB)['daily_remaining']);
    }

    public function testPresupuestoPublicoSeVinculaAlVisitanteSinUsuario(): void
    {
        $hash = $this->visitorHash();
        $repo = $this->repo('2026-08-17 10:00:00');
        $budget = new \NumaPaidCallBudget(new \NumaPublicUsageBudget($repo, $hash), 5);

        $budget->iniciarLlamada();

        self::assertSame(1, $budget->llamadasIniciadas());
        self::assertSame(1, $repo->estado($hash)['daily_used']);
    }

    public function testReservasPublicasConcurrentesConMismoVisitanteNoSuperanElLimite(): void
    {
        $_ENV['NUMA_PUBLIC_DAILY_LIMIT'] = '15';
        $_ENV['NUMA_PUBLIC_MONTHLY_LIMIT'] = '1';
        $_ENV['NUMA_RESERVATION_TTL_SECONDS'] = '2678400';
        $hash = $this->visitorHash();
        $this->insertUsoPublico($hash, '2026-08-01', 0);
        $dir = sys_get_temp_dir() . '/benehom-numa-publico-' . bin2hex(random_bytes(8));
        $locker = $this->newConnection();

        self::assertTrue(mkdir($dir, 0700));
        self::assertSame(0, $this->reservasPublicasDelVisitante($hash));

        $locker->beginTransaction();

        try {
            $stmt = $locker->prepare('SELECT id FROM numa_uso_publico WHERE visitante_hash = :hash AND fecha = :fecha FOR UPDATE');
            $stmt->execute([':hash' => $hash, ':fecha' => '2026-08-01']);
            self::assertNotFalse($stmt->fetchColumn());

            $processA = $this->startConcurrentPublicReservationProcess($hash, $dir, 'a', '2026-08-17 10:00:00');
            $processB = $this->startConcurrentPublicReservationProcess($hash, $dir, 'b', '2026-08-18 10:00:00');

            $this->waitForFiles([$processA['ready_file'], $processB['ready_file']]);
            file_put_contents($dir . '/start', '1');
            $this->waitForFiles([$processA['attempt_file'], $processB['attempt_file']]);
            usleep(200000);

            self::assertTrue($this->processIsRunning($processA));
            self::assertTrue($this->processIsRunning($processB));

            $locker->commit();

            $resultA = $this->collectConcurrentPublicReservationProcess($processA);
            $resultB = $this->collectConcurrentPublicReservationProcess($processB);
        } finally {
            if ($locker->inTransaction()) {
                $locker->rollBack();
            }

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
        self::assertSame(1, $this->reservasPublicasDelVisitante($hash));
        self::assertSame(0, $this->repo('2026-08-17 10:00:00')->estado($hash)['monthly_remaining']);
    }

    private function repo(string $now): \NumaPublicUso
    {
        return new \NumaPublicUso($this->db, new DateTimeImmutable($now));
    }

    private function visitorHash(): string
    {
        $hash = hash('sha256', random_bytes(32));
        $this->visitorHashes[] = $hash;
        return $hash;
    }

    private function reservasPublicasDelVisitante(string $hash): int
    {
        $statement = $this->db->prepare('SELECT COUNT(*) FROM numa_reservas_publicas WHERE visitante_hash = :hash');
        $statement->execute([':hash' => $hash]);

        return (int) $statement->fetchColumn();
    }

    private function insertUsoPublico(string $hash, string $fecha, int $cantidad): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO numa_uso_publico (visitante_hash, fecha, cantidad_confirmada)
             VALUES (:hash, :fecha, :cantidad)
             ON DUPLICATE KEY UPDATE cantidad_confirmada = VALUES(cantidad_confirmada)'
        );
        $statement->execute([':hash' => $hash, ':fecha' => $fecha, ':cantidad' => $cantidad]);
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

    /**
     * @param array{process:resource,pipes:array<int,resource>,ready_file:string,attempt_file:string} $process
     */
    private function processIsRunning(array $process): bool
    {
        $status = proc_get_status($process['process']);

        return is_array($status) && ($status['running'] ?? false) === true;
    }

    /**
     * @return array{process:resource,pipes:array<int,resource>,ready_file:string,attempt_file:string}
     */
    private function startConcurrentPublicReservationProcess(string $hash, string $dir, string $label, string $now): array
    {
        $config = require CONFIG_PATH . '/database.php';
        $readyFile = $dir . '/ready-' . $label;
        $attemptFile = $dir . '/attempt-' . $label;
        $payload = json_encode([
            'base_path' => BASE_PATH,
            'db' => $config,
            'visitante_hash' => $hash,
            'now' => $now,
            'daily_limit' => '5',
            'monthly_limit' => '1',
            'reservation_ttl' => '2678400',
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
require APP_PATH . '/models/Database.php';
require APP_PATH . '/models/NumaPublicUso.php';

$_ENV['NUMA_PUBLIC_DAILY_LIMIT'] = (string) $payload['daily_limit'];
$_ENV['NUMA_PUBLIC_MONTHLY_LIMIT'] = (string) $payload['monthly_limit'];
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

file_put_contents((string) $payload['attempt_file'], '1');

$repo = new NumaPublicUso($pdo, new DateTimeImmutable((string) $payload['now']));

try {
    $repo->reservar((string) $payload['visitante_hash']);
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

    /**
     * @param array{process:resource,pipes:array<int,resource>,ready_file:string,attempt_file:string} $process
     * @return array{status:string,code?:string}
     */
    private function collectConcurrentPublicReservationProcess(array $process): array
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

    /** @return list<string> */
    private function columns(string $table): array
    {
        $statement = $this->db->query('SHOW COLUMNS FROM ' . $table);
        self::assertNotFalse($statement);
        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function ensureSchemaExists(): void
    {
        $schema = file_get_contents(BASE_PATH . '/database/schema.sql');
        self::assertIsString($schema);

        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            if (!preg_match('/^CREATE TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $statement, $matches)) {
                continue;
            }
            $table = $matches[1];
            $existing = $this->db->query('SHOW TABLES LIKE ' . $this->db->quote($table));
            if ($existing !== false && $existing->fetchColumn() !== false) {
                continue;
            }
            $this->db->exec($statement);
        }
    }
}
