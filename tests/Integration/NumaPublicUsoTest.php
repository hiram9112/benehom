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
        $_ENV['NUMA_PUBLIC_DAILY_LIMIT'] = '5';
        $_ENV['NUMA_PUBLIC_MONTHLY_LIMIT'] = '20';
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
        self::assertSame(4, $status['daily_remaining']);
        self::assertSame(19, $status['monthly_remaining']);

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

    public function testLimiteMensualPublicoSeAplicaAunqueQuedeCuotaDiaria(): void
    {
        $hash = $this->visitorHash();
        $_ENV['NUMA_PUBLIC_DAILY_LIMIT'] = '5';
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
        $budget = new \NumaPaidCallBudget(new \NumaPublicUsageBudget($repo, $hash), 3);

        $budget->iniciarLlamada();

        self::assertSame(1, $budget->llamadasIniciadas());
        self::assertSame(1, $repo->estado($hash)['daily_used']);
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
