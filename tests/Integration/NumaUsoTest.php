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

    public function testQuintaConsultaPermitidaYSextaRechazada(): void
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

    public function testVigesimaConsultaMensualPermitidaYConsulta21Rechazada(): void
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
