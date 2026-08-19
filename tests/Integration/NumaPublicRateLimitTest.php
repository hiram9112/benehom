<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/controllers/NumaController.php';
require_once APP_PATH . '/models/Database.php';
require_once APP_PATH . '/models/IntentoAcceso.php';

final class NumaPublicRateLimitTest extends TestCase
{
    private const ACTION = 'numa_public_chat_ip';
    private const HASH_KEY = 'test-public-hash-key';
    private const IP = '203.0.113.7';

    private PDO $db;

    /** @var list<string> */
    private array $rateLimitKeys = [];

    private array $serverBackup = [];
    private array $envBackup = [];
    private array $sessionBackup = [];
    private array $cookieBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Database::getConnection();
        $this->ensureSchemaExists();

        $this->serverBackup = $_SERVER;
        $this->envBackup = $_ENV;
        $this->sessionBackup = is_array($_SESSION ?? null) ? $_SESSION : [];
        $this->cookieBackup = is_array($_COOKIE ?? null) ? $_COOKIE : [];

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REMOTE_ADDR'] = self::IP;
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf-token';
        unset($_SERVER['HTTP_CONTENT_TYPE']);
        $_SESSION = ['csrf_token' => 'csrf-token'];
        $_COOKIE[\NumaPublicIdentity::COOKIE_NAME] = str_repeat('a', 64);

        $_ENV['NUMA_ENABLED'] = 'true';
        $_ENV['NUMA_PUBLIC_ENABLED'] = 'true';
        $_ENV['NUMA_PUBLIC_HASH_KEY'] = self::HASH_KEY;
        $_ENV['NUMA_CHAT_BURST_MAX_REQUESTS'] = '2';
        $_ENV['NUMA_CHAT_BURST_WINDOW_SECONDS'] = '60';
        $_ENV['NUMA_CHAT_BURST_BLOCK_SECONDS'] = '60';
    }

    protected function tearDown(): void
    {
        foreach ($this->rateLimitKeys as $key) {
            $statement = $this->db->prepare('DELETE FROM intentos_acceso WHERE accion = :accion AND clave_hash = :clave');
            $statement->execute([':accion' => self::ACTION, ':clave' => $key]);
        }

        $_SERVER = $this->serverBackup;
        $_ENV = $this->envBackup;
        $_SESSION = $this->sessionBackup;
        $_COOKIE = $this->cookieBackup;

        parent::tearDown();
    }

    public function testRateLimitPublicoPorIpBloqueaAntesDelRecorridoPagado(): void
    {
        $this->trackRateLimitKey();

        $controller = new NumaPublicRateLimitController();

        for ($i = 0; $i < 2; $i++) {
            $response = $this->runPublicChat($controller);
            self::assertTrue($response['ok']);
        }

        self::assertSame(2, $controller->answerCalls);

        $blocked = $this->runPublicChat($controller);

        self::assertFalse($blocked['ok']);
        self::assertSame(429, $blocked['_status']);
        self::assertSame('NUMA_RATE_LIMITED', $blocked['error']['code']);
        self::assertSame(2, $controller->answerCalls, 'El bloqueo debe ocurrir antes de ejecutar el recorrido pagado.');
    }

    public function testRateLimitPublicoNoAlmacenaLaIpEnClaro(): void
    {
        $this->trackRateLimitKey();

        $controller = new NumaPublicRateLimitController();
        $this->runPublicChat($controller);

        $expectedKey = hash_hmac('sha256', self::IP, self::HASH_KEY);
        $statement = $this->db->prepare('SELECT clave_hash FROM intentos_acceso WHERE accion = :accion');
        $statement->execute([':accion' => self::ACTION]);
        $hashes = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));

        self::assertContains($expectedKey, $hashes);

        foreach ($hashes as $hash) {
            self::assertNotSame(self::IP, $hash);
            self::assertStringNotContainsString(self::IP, $hash);
        }
    }

    public function testConcurrenciaDelRateLimitNoSuperaElLimite(): void
    {
        $key = hash_hmac('sha256', self::IP, self::HASH_KEY);
        $this->rateLimitKeys[] = $key;
        $this->insertIntentoAcceso($key, 0);

        $dir = sys_get_temp_dir() . '/benehom-numa-rate-' . bin2hex(random_bytes(8));
        $locker = $this->newConnection();

        self::assertTrue(mkdir($dir, 0700));

        $locker->beginTransaction();

        try {
            $stmt = $locker->prepare('SELECT id FROM intentos_acceso WHERE accion = :accion AND clave_hash = :clave FOR UPDATE');
            $stmt->execute([':accion' => self::ACTION, ':clave' => $key]);
            self::assertNotFalse($stmt->fetchColumn());

            $processA = $this->startConcurrentRateLimitProcess($key, $dir, 'a');
            $processB = $this->startConcurrentRateLimitProcess($key, $dir, 'b');

            $this->waitForFiles([$processA['ready_file'], $processB['ready_file']]);
            file_put_contents($dir . '/start', '1');
            $this->waitForFiles([$processA['attempt_file'], $processB['attempt_file']]);
            usleep(200000);

            self::assertTrue($this->processIsRunning($processA));
            self::assertTrue($this->processIsRunning($processB));

            $locker->commit();

            $resultA = $this->collectConcurrentProcess($processA);
            $resultB = $this->collectConcurrentProcess($processB);
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

        $blocked = [$resultA['blocked'], $resultB['blocked']];
        sort($blocked);

        self::assertSame([false, true], $blocked);
        self::assertTrue(\IntentoAcceso::estaBloqueado(self::ACTION, $key));
        self::assertSame(2, $this->intentosDeAccion($key));
    }

    /** @return array<string, mixed> */
    private function runPublicChat(NumaPublicRateLimitController $controller): array
    {
        http_response_code(200);

        ob_start();
        $controller->publicChat();
        $output = (string) ob_get_clean();

        $decoded = json_decode($output, true);

        if (!is_array($decoded)) {
            return ['ok' => false, 'raw' => $output, '_status' => http_response_code()];
        }

        $decoded['_status'] = http_response_code();

        return $decoded;
    }

    private function trackRateLimitKey(): void
    {
        $this->rateLimitKeys[] = hash_hmac('sha256', self::IP, self::HASH_KEY);
    }

    private function intentosDeAccion(string $key): int
    {
        $statement = $this->db->prepare(
            'SELECT intentos FROM intentos_acceso WHERE accion = :accion AND clave_hash = :clave'
        );
        $statement->execute([':accion' => self::ACTION, ':clave' => $key]);

        return (int) $statement->fetchColumn();
    }

    private function insertIntentoAcceso(string $key, int $intentos): void
    {
        $ahora = date('Y-m-d H:i:s');
        $statement = $this->db->prepare(
            'INSERT INTO intentos_acceso (accion, clave_hash, intentos, primer_intento, ultimo_intento)
             VALUES (:accion, :clave, :intentos, :primer_intento, :ultimo_intento)'
        );
        $statement->execute([
            ':accion' => self::ACTION,
            ':clave' => $key,
            ':intentos' => $intentos,
            ':primer_intento' => $ahora,
            ':ultimo_intento' => $ahora,
        ]);
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
    private function startConcurrentRateLimitProcess(string $key, string $dir, string $label): array
    {
        $config = require CONFIG_PATH . '/database.php';
        $readyFile = $dir . '/ready-' . $label;
        $attemptFile = $dir . '/attempt-' . $label;
        $payload = json_encode([
            'base_path' => BASE_PATH,
            'db' => $config,
            'accion' => self::ACTION,
            'clave_hash' => $key,
            'max_intentos' => 2,
            'ventana' => 60,
            'bloqueo' => 60,
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

date_default_timezone_set('Europe/Madrid');

require APP_PATH . '/helpers/utils.php';
require APP_PATH . '/models/Database.php';
require APP_PATH . '/models/IntentoAcceso.php';

$db = $payload['db'];

if (!is_array($db)) {
    fwrite(STDERR, "Configuración de base de datos inválida\n");
    exit(2);
}

$_ENV['DB_HOST'] = (string) $db['host'];
$_ENV['DB_PORT'] = (string) $db['port'];
$_ENV['DB_NAME'] = (string) $db['dbname'];
$_ENV['DB_USER'] = (string) $db['user'];
$_ENV['DB_PASS'] = (string) $db['password'];

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

$blocked = IntentoAcceso::registrarFallo(
    (string) $payload['accion'],
    (string) $payload['clave_hash'],
    (int) $payload['max_intentos'],
    (int) $payload['ventana'],
    (int) $payload['bloqueo']
);

echo json_encode(['blocked' => $blocked]);
exit(0);
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
     * @return array{blocked:bool}
     */
    private function collectConcurrentProcess(array $process): array
    {
        $stdout = stream_get_contents($process['pipes'][1]);
        $stderr = stream_get_contents($process['pipes'][2]);
        fclose($process['pipes'][1]);
        fclose($process['pipes'][2]);
        $exitCode = proc_close($process['process']);

        self::assertSame(0, $exitCode, 'Proceso concurrente fallido: ' . (string) $stderr);
        $decoded = json_decode((string) $stdout, true);

        self::assertIsArray($decoded, 'Salida concurrente inválida: ' . (string) $stdout);
        self::assertIsBool($decoded['blocked'] ?? null);

        return ['blocked' => $decoded['blocked']];
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

final class NumaPublicRateLimitController extends \NumaController
{
    public int $answerCalls = 0;

    protected function rawBody(): string
    {
        return '{"message":"¿Cómo añado un movimiento?"}';
    }

    protected function answerPublic(string $visitorHash, string $message, array $context): \NumaServiceResult
    {
        ++$this->answerCalls;

        return new \NumaServiceResult(
            'Puedes añadir movimientos desde el formulario.',
            [],
            null,
            [
                'daily_used' => 1,
                'daily_limit' => 5,
                'daily_remaining' => 4,
                'monthly_used' => 1,
                'monthly_limit' => 20,
                'monthly_remaining' => 19,
                'interaction_used' => 1,
            ],
        );
    }
}
