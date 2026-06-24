<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/models/Gasto.php';

final class GastoAgregacionesTest extends IntegrationTestCase
{
    public function testMesesConMovimientosPorRangoCuentaMesesDistintosConDatos(): void
    {
        $usuario = $this->crearUsuario('gastos-meses.integration@example.test');
        $otroUsuario = $this->crearUsuario('gastos-meses-otro.integration@example.test');

        self::assertNotFalse(\Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 20, '2026-01-05'));
        self::assertNotFalse(\Gasto::agregarGasto($usuario['id'], 'flexible', 'restaurantes_bares_cafeterias', 30, '2026-01-20'));
        self::assertNotFalse(\Gasto::agregarGasto($usuario['id'], 'flexible', 'viajes_escapadas', 100, '2026-03-10'));
        self::assertNotFalse(\Gasto::agregarGasto($usuario['id'], 'esencial', 'alquiler_hipoteca', 700, '2026-02-01'));
        self::assertNotFalse(\Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 40, '2025-12-31'));
        self::assertNotFalse(\Gasto::agregarGasto($otroUsuario['id'], 'flexible', 'ocio_entretenimiento', 999, '2026-02-10'));

        $meses = \Gasto::mesesConMovimientosPorRango($usuario['id'], '2026-01-01', '2026-06-30', 'flexible');

        self::assertSame(2, $meses);
    }

    public function testTotalesPorCategoriaYRangoAgrupaYFiltraPorUsuarioTipoYRango(): void
    {
        $usuario = $this->crearUsuario('gastos-totales.integration@example.test');
        $otroUsuario = $this->crearUsuario('gastos-totales-otro.integration@example.test');

        self::assertNotFalse(\Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 20, '2026-01-05'));
        self::assertNotFalse(\Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 30, '2026-02-05'));
        self::assertNotFalse(\Gasto::agregarGasto($usuario['id'], 'flexible', 'restaurantes_bares_cafeterias', 25, '2026-02-10'));
        self::assertNotFalse(\Gasto::agregarGasto($usuario['id'], 'flexible', 'streaming_contenido_digital', -5, '2026-03-10'));
        self::assertNotFalse(\Gasto::agregarGasto($usuario['id'], 'esencial', 'alquiler_hipoteca', 700, '2026-02-01'));
        self::assertNotFalse(\Gasto::agregarGasto($usuario['id'], 'flexible', 'viajes_escapadas', 200, '2025-12-15'));
        self::assertNotFalse(\Gasto::agregarGasto($otroUsuario['id'], 'flexible', 'ocio_entretenimiento', 999, '2026-02-10'));

        $totales = \Gasto::totalesPorCategoriaYRango($usuario['id'], '2026-01-01', '2026-06-30', 'flexible');

        self::assertSame(
            [
                ['categoria' => 'ocio_entretenimiento', 'total' => '50.00'],
                ['categoria' => 'restaurantes_bares_cafeterias', 'total' => '25.00'],
            ],
            $totales
        );
    }
}
