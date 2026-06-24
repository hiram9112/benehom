<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/models/Database.php';
require_once APP_PATH . '/models/Usuario.php';

abstract class IntegrationTestCase extends TestCase
{
    protected PDO $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Database::getConnection();
        $this->ensureSchemaExists();
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        parent::tearDown();
    }

    /**
     * @return array{id:int, usuario:string, email:string, password:string, fecha_registro:string, reset_token_hash:?string, reset_token_expires_at:?string}
     */
    protected function crearUsuario(string $email, string $password = 'Password-test-123'): array
    {
        self::assertTrue(\Usuario::registrar('Usuario test', $email, $password));

        $usuario = \Usuario::obtenerUsuario($email);

        self::assertIsArray($usuario);

        return $usuario;
    }

    private function ensureSchemaExists(): void
    {
        $stmt = $this->db->query("SHOW TABLES LIKE 'usuarios'");

        if ($stmt !== false && $stmt->fetchColumn() !== false) {
            return;
        }

        $schemaPath = BASE_PATH . '/database/schema.sql';
        $schema = file_get_contents($schemaPath);

        if ($schema === false) {
            self::fail('No se pudo cargar database/schema.sql');
        }

        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            $this->db->exec($statement);
        }
    }
}
