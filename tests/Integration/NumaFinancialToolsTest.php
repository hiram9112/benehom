<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PDOStatement;

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
        $this->insertGasto((int) $usuario['id'], 'flexible', 'regalos', 25, '2026-07-07');

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
        self::assertSame(['inicio' => '2026-07-01', 'fin' => '2026-07-31'], $result['periodo']);
        self::assertSame(2, $result['limite']);
        self::assertSame('alimentacion', $result['categorias'][0]['categoria']);
        self::assertSame(100.0, $result['categorias'][0]['total']);
        self::assertSame(50.0, $result['categorias'][0]['porcentaje']);
        self::assertSame(37.5, $result['categorias'][1]['porcentaje']);
        self::assertArrayNotHasKey('usuario_id', $result['categorias'][0]);
        self::assertArrayNotHasKey('id', $result['categorias'][0]);
    }

    public function testEvolucionPorTipoRespetaLaMetricaSolicitada(): void
    {
        $usuario = $this->crearUsuario('numa-tools-evolucion-tipo@example.test');
        $usuarioId = (int) $usuario['id'];
        $this->insertIngreso($usuarioId, 'salario', 1200, '2026-07-02');
        $this->insertGasto($usuarioId, 'esencial', 'alimentacion', 300, '2026-07-03');
        $this->insertGasto($usuarioId, 'flexible', 'ocio', 90, '2026-07-04');

        $registry = new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db));

        $ingresos = $registry->execute('obtener_evolucion_financiera', $usuarioId, [
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
            'metrica' => 'ingresos',
            'agrupacion' => 'tipo',
        ]);
        self::assertSame([
            ['tipo' => 'ingresos', 'valor' => 1200.0],
        ], $ingresos['evolucion']);

        $gastos = $registry->execute('obtener_evolucion_financiera', $usuarioId, [
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
            'metrica' => 'gastos',
            'agrupacion' => 'tipo',
        ]);
        self::assertSame([
            ['tipo' => 'esencial', 'valor' => 300.0],
            ['tipo' => 'flexible', 'valor' => 90.0],
        ], $gastos['evolucion']);
    }

    public function testSesionDefineUsuarioAutenticadoYNoPermiteElegirOtroUsuarioId(): void
    {
        $usuario = $this->crearUsuario('numa-tools-sesion@example.test');
        $otroUsuario = $this->crearUsuario('numa-tools-sesion-otro@example.test');
        $usuarioId = (int) $usuario['id'];
        $otroUsuarioId = (int) $otroUsuario['id'];
        $sessionBackup = is_array($_SESSION ?? null) ? $_SESSION : [];

        $this->insertIngreso($usuarioId, 'salario', 1000, '2026-07-02');
        $this->insertIngreso($otroUsuarioId, 'salario', 9000, '2026-07-02');

        try {
            $_SESSION = ['usuario_id' => $usuarioId];
            $registry = new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db));

            $resumen = $registry->executeForAuthenticatedSession('obtener_resumen_financiero', [
                'fecha_inicio' => '2026-07-01',
                'fecha_fin' => '2026-07-31',
            ]);

            self::assertSame(1000.0, $resumen['ingresos']);

            $this->expectException(\InvalidArgumentException::class);
            $registry->executeForAuthenticatedSession('obtener_resumen_financiero', [
                'fecha_inicio' => '2026-07-01',
                'fecha_fin' => '2026-07-31',
                'usuario_id' => $otroUsuarioId,
            ]);
        } finally {
            $_SESSION = $sessionBackup;
        }
    }

    public function testCompararPeriodosCalculaDiferenciasAgregadas(): void
    {
        $usuario = $this->crearUsuario('numa-tools-comparar@example.test');
        $usuarioId = (int) $usuario['id'];
        $this->insertGasto($usuarioId, 'esencial', 'alimentacion', 100, '2026-07-03');
        $this->insertGasto($usuarioId, 'flexible', 'ocio', 40, '2026-07-04');
        $this->insertGasto($usuarioId, 'flexible', 'ocio', 60, '2026-08-04');

        $comparacion = (new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db)))->execute('comparar_periodos', $usuarioId, [
            'fecha_inicio_a' => '2026-07-01',
            'fecha_fin_a' => '2026-07-31',
            'fecha_inicio_b' => '2026-08-01',
            'fecha_fin_b' => '2026-08-31',
            'metrica' => 'gastos',
        ]);

        self::assertSame(140.0, $comparacion['valor_a']);
        self::assertSame(60.0, $comparacion['valor_b']);
        self::assertSame(-80.0, $comparacion['diferencia_absoluta']);
        self::assertSame(-57.14, $comparacion['diferencia_porcentual']);
    }

    public function testEstadisticasMovimientosCalculaAgregados(): void
    {
        $usuario = $this->crearUsuario('numa-tools-estadisticas@example.test');
        $usuarioId = (int) $usuario['id'];
        $this->insertGasto($usuarioId, 'esencial', 'alimentacion', 100, '2026-07-03');
        $this->insertGasto($usuarioId, 'flexible', 'ocio', 40, '2026-07-04');

        $estadisticas = (new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db)))->execute(
            'obtener_estadisticas_movimientos',
            $usuarioId,
            ['fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-07-31', 'metrica' => 'gastos']
        );

        self::assertSame(2, $estadisticas['cantidad_movimientos']);
        self::assertSame(140.0, $estadisticas['total']);
        self::assertSame(70.0, $estadisticas['promedio']);
        self::assertSame(100.0, $estadisticas['maximo']);
        self::assertSame(40.0, $estadisticas['minimo']);
    }

    public function testUsuarioSinDatosYPeriodoSinMovimientosDevuelvenAgregadosVacios(): void
    {
        $usuario = $this->crearUsuario('numa-tools-sin-datos@example.test');
        $usuarioId = (int) $usuario['id'];
        $this->insertGasto($usuarioId, 'flexible', 'ocio', 40, '2026-06-04');
        $registry = new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db), 2);

        $resumen = $registry->execute('obtener_resumen_financiero', $usuarioId, [
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
        ]);
        $ranking = $registry->execute('obtener_ranking_categorias', $usuarioId, [
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
            'metrica' => 'gastos',
        ]);

        self::assertSame(0.0, $resumen['ingresos']);
        self::assertSame(0.0, $resumen['gastos']);
        self::assertSame([], $ranking['categorias']);
    }

    public function testResultadoAgregadoDeToolsQuedaAcotadoParaProveedorSinDatosPrivados(): void
    {
        $usuario = $this->crearUsuario('numa-tools-json-limit@example.test');
        $usuarioId = (int) $usuario['id'];
        $maxAggregateChars = 650;

        $registry = new \NumaFinancialToolRegistry(
            new \NumaFinancialToolExecutor($this->db),
            2,
            $maxAggregateChars
        );
        $resumen = $registry->execute('obtener_resumen_financiero', $usuarioId, [
            'fecha_inicio' => '2025-01-01',
            'fecha_fin' => '2026-12-31',
        ]);
        $evolucion = $registry->execute('obtener_evolucion_financiera', $usuarioId, [
            'fecha_inicio' => '2025-01-01',
            'fecha_fin' => '2026-12-31',
            'metrica' => 'gastos',
            'agrupacion' => 'mes',
            'limite' => 24,
        ]);

        $encoded = json_encode([$resumen, $evolucion], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        self::assertLessThanOrEqual($maxAggregateChars, strlen($encoded));
        self::assertLessThan(24, count($evolucion['evolucion']));
        self::assertNoPrivateKeys($resumen);
        self::assertNoPrivateKeys($evolucion);
    }

    public function testRegistroNoEjecutaMasDeDosToolsPorPregunta(): void
    {
        $usuario = $this->crearUsuario('numa-tools-max-calls@example.test');
        $usuarioId = (int) $usuario['id'];
        $registry = new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db), 2);
        $arguments = ['fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-07-31'];

        self::assertSame('obtener_resumen_financiero', $registry->execute('obtener_resumen_financiero', $usuarioId, $arguments)['tool']);
        self::assertSame('obtener_resumen_financiero', $registry->execute('obtener_resumen_financiero', $usuarioId, $arguments)['tool']);

        $this->expectException(\NumaFinancialToolLimitExceeded::class);
        $this->expectExceptionMessage('No hemos podido procesar la consulta.');

        $registry->execute('ejecutar_sql', $usuarioId, ['sql' => 'SELECT * FROM gastos']);
    }

    public function testToolsSonSoloLecturaYEjecutanUnicamenteSelect(): void
    {
        $pdo = new RecordingNumaPdo();
        $registry = new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($pdo), 5, 10000);

        $registry->execute('obtener_resumen_financiero', 1, [
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
        ]);
        $registry->execute('obtener_ranking_categorias', 1, [
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
            'metrica' => 'gastos',
        ]);
        $registry->execute('obtener_evolucion_financiera', 1, [
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
            'metrica' => 'gastos',
            'agrupacion' => 'tipo',
        ]);
        $registry->execute('comparar_periodos', 1, [
            'fecha_inicio_a' => '2026-07-01',
            'fecha_fin_a' => '2026-07-31',
            'fecha_inicio_b' => '2026-08-01',
            'fecha_fin_b' => '2026-08-31',
            'metrica' => 'gastos',
        ]);
        $registry->execute('obtener_estadisticas_movimientos', 1, [
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
            'metrica' => 'gastos',
        ]);

        self::assertNotSame([], $pdo->preparedSql);

        foreach ($pdo->preparedSql as $sql) {
            self::assertStringStartsWith('SELECT', ltrim($sql));
        }
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

    /** @param array<mixed> $value */
    private static function assertNoPrivateKeys(array $value): void
    {
        foreach ($value as $key => $item) {
            self::assertNotContains($key, ['usuario_id', 'user_id', 'id', 'email', 'correo', 'nombre', 'nota', 'notas']);

            if (is_array($item)) {
                self::assertNoPrivateKeys($item);
            }
        }
    }
}

final class RecordingNumaPdo extends PDO
{
    /** @var array<int, string> */
    public array $preparedSql = [];

    public function __construct()
    {
        $config = require CONFIG_PATH . '/database.php';

        parent::__construct(
            "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4",
            $config['user'],
            $config['password']
        );
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /** @param array<mixed> $options */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return parent::prepare($query, $options);
    }
}
