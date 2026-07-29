<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class NumaKnowledgeStorageSchemaTest extends TestCase
{
    public function testDefineTablaMysqlParaConocimientoNuma(): void
    {
        $schema = file_get_contents(BASE_PATH . '/database/schema.sql');

        self::assertIsString($schema);
        $table = $this->createTableStatement($schema, 'numa_conocimiento');

        self::assertStringContainsString('id INT AUTO_INCREMENT PRIMARY KEY', $table);
        self::assertStringContainsString('fragmento_id VARCHAR(191) NOT NULL', $table);
        self::assertStringContainsString('documento VARCHAR(120) NOT NULL', $table);
        self::assertStringContainsString('titulo VARCHAR(160) NOT NULL', $table);
        self::assertStringContainsString('seccion VARCHAR(220) NOT NULL', $table);
        self::assertStringContainsString('ruta VARCHAR(255) NOT NULL', $table);
        self::assertStringContainsString('contenido TEXT NOT NULL', $table);
        self::assertStringContainsString('hash CHAR(64) NOT NULL', $table);
        self::assertStringContainsString('embedding JSON NOT NULL', $table);
        self::assertStringContainsString('dimensiones INT UNSIGNED NOT NULL', $table);
        self::assertStringContainsString('indexed_at DATETIME NOT NULL', $table);
        self::assertStringContainsString('UNIQUE KEY numa_conocimiento_fragmento_id_unique (fragmento_id)', $table);
        self::assertStringContainsString('ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', $table);
    }

    private function createTableStatement(string $schema, string $tableName): string
    {
        $pattern = '/CREATE TABLE ' . preg_quote($tableName, '/') . ' \(.+?\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;/s';

        self::assertMatchesRegularExpression($pattern, $schema);
        preg_match($pattern, $schema, $matches);

        return $matches[0];
    }
}
