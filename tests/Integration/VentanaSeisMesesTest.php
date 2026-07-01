<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/models/Gasto.php';

final class VentanaSeisMesesTest extends IntegrationTestCase
{
    public function testUnMesConDatosDevuelveDivisor1(): void
    {
        $usuario = $this->crearUsuario('ventana-1m.integration@example.test');

        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 50, '2026-05-10');

        $meses = \Gasto::mesesConMovimientosPorRango($usuario['id'], '2026-01-01', '2026-06-30', 'flexible');

        self::assertSame(1, $meses);
    }

    public function testDosMesesConDatosDevuelveDivisor2(): void
    {
        $usuario = $this->crearUsuario('ventana-2m.integration@example.test');

        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 30, '2026-04-10');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 50, '2026-05-10');

        $meses = \Gasto::mesesConMovimientosPorRango($usuario['id'], '2026-01-01', '2026-06-30', 'flexible');

        self::assertSame(2, $meses);
    }

    public function testTresMesesConsecutivosConDatosDevuelveDivisor3(): void
    {
        $usuario = $this->crearUsuario('ventana-3m-consec.integration@example.test');

        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 30, '2026-03-10');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'restaurantes_bares_cafeterias', 50, '2026-04-10');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 70, '2026-05-10');

        $meses = \Gasto::mesesConMovimientosPorRango($usuario['id'], '2026-01-01', '2026-06-30', 'flexible');

        self::assertSame(3, $meses);
    }

    public function testCuatroMesesConHuecoInicialDevuelveDivisor4(): void
    {
        $usuario = $this->crearUsuario('ventana-4m-huecoinicio.integration@example.test');

        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 30, '2026-03-10');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'restaurantes_bares_cafeterias', 50, '2026-04-10');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 70, '2026-05-10');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 20, '2026-06-10');

        $meses = \Gasto::mesesConMovimientosPorRango($usuario['id'], '2026-01-01', '2026-06-30', 'flexible');

        self::assertSame(4, $meses);
    }

    public function testCincoMesesConHuecoMedioDevuelveDivisor5(): void
    {
        $usuario = $this->crearUsuario('ventana-5m-huecomedio.integration@example.test');

        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 30, '2026-01-10');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'restaurantes_bares_cafeterias', 50, '2026-02-10');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 70, '2026-04-10');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 20, '2026-05-10');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'restaurantes_bares_cafeterias', 60, '2026-06-10');

        $meses = \Gasto::mesesConMovimientosPorRango($usuario['id'], '2026-01-01', '2026-06-30', 'flexible');

        self::assertSame(5, $meses);
    }

    public function testSeisMesesConsecutivosDevuelveDivisor6(): void
    {
        $usuario = $this->crearUsuario('ventana-6m.integration@example.test');

        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 30, '2026-01-10');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 30, '2026-02-10');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 30, '2026-03-10');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 30, '2026-04-10');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 30, '2026-05-10');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 30, '2026-06-10');

        $meses = \Gasto::mesesConMovimientosPorRango($usuario['id'], '2026-01-01', '2026-06-30', 'flexible');

        self::assertSame(6, $meses);
    }

    public function testMesesConHuecosNoConsecutivosCuentaSoloMesesConDatos(): void
    {
        $usuario = $this->crearUsuario('ventana-huecos.integration@example.test');

        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 30, '2026-01-15');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 30, '2026-03-15');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 30, '2026-05-15');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 30, '2026-06-15');

        $meses = \Gasto::mesesConMovimientosPorRango($usuario['id'], '2026-01-01', '2026-06-30', 'flexible');

        self::assertSame(4, $meses);
    }

    public function testMovimientosDeOtroUsuarioNoCuentan(): void
    {
        $duenio = $this->crearUsuario('ventana-duenio.integration@example.test');
        $otro = $this->crearUsuario('ventana-otro.integration@example.test');

        \Gasto::agregarGasto($duenio['id'], 'flexible', 'ocio_entretenimiento', 30, '2026-03-10');
        \Gasto::agregarGasto($duenio['id'], 'flexible', 'ocio_entretenimiento', 40, '2026-04-10');
        \Gasto::agregarGasto($otro['id'], 'flexible', 'ocio_entretenimiento', 999, '2026-05-10');
        \Gasto::agregarGasto($otro['id'], 'flexible', 'ocio_entretenimiento', 999, '2026-06-10');

        $mesesDuenio = \Gasto::mesesConMovimientosPorRango($duenio['id'], '2026-01-01', '2026-06-30', 'flexible');
        $mesesOtro = \Gasto::mesesConMovimientosPorRango($otro['id'], '2026-01-01', '2026-06-30', 'flexible');

        self::assertSame(2, $mesesDuenio);
        self::assertSame(2, $mesesOtro);
    }

    public function testMediaCalculadaConMesesRealesNoDividePor6Fijo(): void
    {
        $usuario = $this->crearUsuario('ventana-media.integration@example.test');

        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 60, '2026-05-10');

        $totales = \Gasto::totalesPorCategoriaYRango($usuario['id'], '2026-01-01', '2026-06-30', 'flexible');
        $meses = \Gasto::mesesConMovimientosPorRango($usuario['id'], '2026-01-01', '2026-06-30', 'flexible');

        self::assertSame(1, $meses);

        $total = floatval($totales[0]['total']);
        $mediaReal = $total / $meses;
        $mediaFalsa = $total / 6;

        self::assertSame(60.0, $mediaReal);
        self::assertSame(10.0, $mediaFalsa);
        self::assertNotSame($mediaFalsa, $mediaReal);
    }

    public function testSinMovimientosDevuelveCero(): void
    {
        $usuario = $this->crearUsuario('ventana-vacia.integration@example.test');

        $meses = \Gasto::mesesConMovimientosPorRango($usuario['id'], '2026-01-01', '2026-06-30', 'flexible');

        self::assertSame(0, $meses);
    }

    public function testTipoEsencialYFlexibleIndependientes(): void
    {
        $usuario = $this->crearUsuario('ventana-tipos.integration@example.test');

        \Gasto::agregarGasto($usuario['id'], 'esencial', 'alquiler_hipoteca', 700, '2026-01-10');
        \Gasto::agregarGasto($usuario['id'], 'esencial', 'electricidad', 80, '2026-02-10');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 30, '2026-03-10');

        $mesesEsencial = \Gasto::mesesConMovimientosPorRango($usuario['id'], '2026-01-01', '2026-06-30', 'esencial');
        $mesesFlexible = \Gasto::mesesConMovimientosPorRango($usuario['id'], '2026-01-01', '2026-06-30', 'flexible');

        self::assertSame(2, $mesesEsencial);
        self::assertSame(1, $mesesFlexible);
    }
}
