<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/services/NumaFinancialTools.php';

final class NumaFinancialToolsTest extends IntegrationTestCase
{
    public function testResumenFinancieroCalculaAgregadosDelUsuarioAutenticado(): void
    {
        $usuario = $this->crearUsuario('numa-tools-resumen@example.test');
        $otroUsuario = $this->crearUsuario('numa-tools-resumen-otro@example.test');
        $this->insertIngreso((int) $usuario['id'], 'salario', 2000, '2026-07-02');
        $this->insertGasto((int) $usuario['id'], 'esencial', 'vivienda', 800, '2026-07-03');
        $this->insertGasto((int) $usuario['id'], 'flexible', 'ocio', 150, '2026-07-04');
        $this->insertIngreso((int) $otroUsuario['id'], 'salario', 9000, '2026-07-02');
        $this->insertGasto((int) $otroUsuario['id'], 'flexible', 'ocio', 9000, '2026-07-04');

        $result = (new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db)))->execute(
            'obtener_resumen_financiero',
            (int) $usuario['id'],
            ['fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-07-31']
        );

        self::assertSame(2000.0, $result['ingresos']);
        self::assertSame(950.0, $result['gastos']);
        self::assertSame(800.0, $result['gastos_esenciales']);
        self::assertSame(150.0, $result['gastos_flexibles']);
        self::assertSame(1200.0, $result['ahorro_posible']);
        self::assertSame(1050.0, $result['ahorro_real']);
    }

    public function testRankingCategoriasDevuelveTotalesAcotadosSinDatosPrivados(): void
    {
        $usuario = $this->crearUsuario('numa-tools-ranking@example.test');
        $this->insertGasto((int) $usuario['id'], 'flexible', 'ocio', 50, '2026-07-04');
        $this->insertGasto((int) $usuario['id'], 'flexible', 'ocio', 25, '2026-07-05');
        $this->insertGasto((int) $usuario['id'], 'esencial', 'alimentacion', 100, '2026-07-06');

        $result = (new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db)))->execute(
            'obtener_ranking_categorias',
            (int) $usuario['id'],
            [
                'fecha_inicio' => '2026-07-01',
                'fecha_fin' => '2026-07-31',
                'metrica' => 'gastos',
                'limite' => 2,
            ]
        );

        self::assertCount(2, $result['categorias']);
        self::assertSame('alimentacion', $result['categorias'][0]['categoria']);
        self::assertSame(100.0, $result['categorias'][0]['total']);
        self::assertArrayNotHasKey('usuario_id', $result['categorias'][0]);
        self::assertArrayNotHasKey('id', $result['categorias'][0]);
    }

    private function insertIngreso(int $usuarioId, string $categoria, float $cantidad, string $fecha): void
    {
        $stmt = $this->db->prepare('INSERT INTO ingresos (usuario_id, categoria, cantidad, fecha) VALUES (:usuario_id, :categoria, :cantidad, :fecha)');
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':categoria' => $categoria,
            ':cantidad' => $cantidad,
            ':fecha' => $fecha,
        ]);
    }

    private function insertGasto(int $usuarioId, string $tipo, string $categoria, float $cantidad, string $fecha): void
    {
        $stmt = $this->db->prepare('INSERT INTO gastos (usuario_id, tipo, categoria, cantidad, fecha) VALUES (:usuario_id, :tipo, :categoria, :cantidad, :fecha)');
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':tipo' => $tipo,
            ':categoria' => $categoria,
            ':cantidad' => $cantidad,
            ':fecha' => $fecha,
        ]);
    }
}
