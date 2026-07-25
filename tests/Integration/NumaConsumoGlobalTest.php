<?php

declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/models/Database.php';
require_once APP_PATH . '/models/NumaConsumoGlobal.php';
require_once APP_PATH . '/services/NumaProvider.php';

final class NumaConsumoGlobalTest extends TestCase
{
    private PDO $db;

    /** @var list<string> */
    private array $fechasCreadas = ['2026-06-30', '2026-07-01', '2026-07-24', '2026-07-25'];

    /** @var array<string, string|null> */
    private array $envBackup = [];

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
    }

    protected function tearDown(): void
    {
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
        self::assertSame(0, $estado['daily_tokens']);
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

    public function testRegistrarTokensFiablesSoloAnadeLosValoresInformados(): void
    {
        $repo = $this->repo('2026-07-25 10:00:00');
        $repo->iniciarLlamada();

        $repo->registrarTokens(new \NumaTokenUsage(null, 50));

        $row = $this->row('2026-07-25');

        self::assertSame(0, $row['input_tokens']);
        self::assertSame(50, $row['output_tokens']);
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

    private function repo(string $now): \NumaConsumoGlobal
    {
        return new \NumaConsumoGlobal($this->db, new DateTimeImmutable($now));
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
        ];
    }
}