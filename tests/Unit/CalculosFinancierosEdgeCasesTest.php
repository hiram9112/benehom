<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CalculosFinancierosEdgeCasesTest extends TestCase
{
    #[DataProvider('normalizarCantidadEdgeCasesProvider')]
    public function testNormalizarCantidadCasosLimite(mixed $valor, ?float $esperado): void
    {
        self::assertSame($esperado, \CalculosFinancieros::normalizarCantidad($valor));
    }

    /**
     * @return array<string, array{0:mixed, 1:?float}>
     */
    public static function normalizarCantidadEdgeCasesProvider(): array
    {
        return [
            'null' => [null, null],
            'false' => [false, null],
            'true' => [true, 1.0],
            'entero' => [42, 42.0],
            'flotante' => [3.14, 3.14],
            'cero string' => ['0', 0.0],
            'cero entero' => [0, 0.0],
            'negativo' => ['-12.50', -12.5],
            'separador miles europeo no soportado' => ['1.234,56', null],
            'separador miles ingles no soportado' => ['1,234.56', null],
            'muchos decimales' => ['3.14159265', 3.14159265],
            'espacios internos' => ['  12.5  ', 12.5],
            'punto multiple' => ['1.2.3', null],
            'coma multiple' => ['1,2,3', null],
            'texto' => ['abc', null],
            'notacion cientifica valida' => ['1e3', 1000.0],
            'notacion cientifica overflow' => ['1e309', null],
            'inf' => [INF, null],
            'nan' => [NAN, null],
            'array ignorado' => ['cualquier cosa no numerica', null],
        ];
    }

    #[DataProvider('calcularMesesHastaFechaObjetivoEdgeCasesProvider')]
    public function testCalcularMesesHastaFechaObjetivoCasosLimite(string $fechaObjetivo, ?DateTimeImmutable $hoy, ?int $esperado): void
    {
        self::assertSame($esperado, \CalculosFinancieros::calcularMesesHastaFechaObjetivo($fechaObjetivo, $hoy));
    }

    /**
     * @return array<string, array{0:string, 1:?DateTimeImmutable, 2:?int}>
     */
    public static function calcularMesesHastaFechaObjetivoEdgeCasesProvider(): array
    {
        $hoy = new DateTimeImmutable('2026-01-01');

        return [
            'anio bisiesto fecha valida' => ['2028-02-29', new DateTimeImmutable('2026-01-01'), 26],
            'anio bisiesto fecha invalida' => ['2027-02-29', $hoy, null],
            'mes invalido 13' => ['2026-13-01', $hoy, null],
            'mes invalido 00' => ['2026-00-01', $hoy, null],
            'mes formato corto' => ['2026-1-1', $hoy, null],
            'con hora' => ['2027-01-01T00:00:00', $hoy, null],
            'con espacios' => ['2027 01 01', $hoy, null],
            'con separador' => ['2027/01/01', $hoy, null],
            'vacio' => ['', $hoy, null],
            'anio muy lejano' => ['2056-01-01', $hoy, 360],
            'hoy mismo' => ['2026-01-01', $hoy, null],
            'ayer' => ['2025-12-31', $hoy, null],
            'manana exacto' => ['2026-01-31', $hoy, 1],
        ];
    }

    public function testCalcularMesesHastaFechaObjetivoSinRelojUsaHoy(): void
    {
        $futuro = (new DateTimeImmutable('today'))->modify('+6 months')->format('Y-m-d');
        $resultado = \CalculosFinancieros::calcularMesesHastaFechaObjetivo($futuro);

        self::assertIsInt($resultado);
        self::assertGreaterThanOrEqual(5, $resultado);
        self::assertLessThanOrEqual(7, $resultado);
    }

    public function testCalcularCalculadoraHipotecaConPlazoCero(): void
    {
        $resultado = \CalculosFinancieros::calcularCalculadoraHipoteca(100000, 3, 0);

        self::assertSame(0.0, $resultado['cuota_mensual']);
        self::assertSame(0.0, $resultado['total_intereses']);
        self::assertSame(0.0, $resultado['total_pagado']);
    }

    public function testCalcularCalculadoraHipotecaConImporteCero(): void
    {
        $resultado = \CalculosFinancieros::calcularCalculadoraHipoteca(0, 3, 20);

        self::assertSame(0.0, $resultado['cuota_mensual']);
        self::assertSame(0.0, $resultado['total_intereses']);
        self::assertSame(0.0, $resultado['total_pagado']);
    }

    public function testCalcularEscenarioInversionConPlazoCero(): void
    {
        $resultado = \CalculosFinancieros::calcularEscenarioInversion(10000, 100, 5, 0, 'mensual');

        self::assertSame(0.0, $resultado['total_aportaciones_plazo']);
        self::assertSame(10000.0, $resultado['capital_total_aportado']);
        self::assertSame(10000.0, $resultado['valor_final_estimado']);
        self::assertSame(0.0, $resultado['rendimiento_estimado']);
        self::assertSame(0.0, $resultado['roi_porcentaje']);
    }

    public function testCalcularEscenarioInversionTodoACero(): void
    {
        $resultado = \CalculosFinancieros::calcularEscenarioInversion(0, 0, 0, 10, 'mensual');

        self::assertSame(0.0, $resultado['total_aportaciones_plazo']);
        self::assertSame(0.0, $resultado['capital_total_aportado']);
        self::assertSame(0.0, $resultado['valor_final_estimado']);
        self::assertSame(0.0, $resultado['rendimiento_estimado']);
        self::assertSame(0.0, $resultado['roi_porcentaje']);
    }

    public function testValidarEstimacionEnElUmbralExactoNoSuperaMaximo(): void
    {
        $capital = 9007199254740991.0;
        $resultado = \CalculosFinancieros::calcularEscenarioInversion(
            $capital / 4,
            0,
            0,
            1,
            'mensual'
        );

        self::assertNull(\CalculosFinancieros::validarEstimacionEscenarioInversion(
            $capital / 4,
            0,
            0,
            1,
            'mensual'
        ));
        self::assertLessThanOrEqual($capital, $resultado['valor_final_estimado']);
    }

    public function testValidarEstimacionDetectaOverflowDeAportacionYRentabilidad(): void
    {
        $mensaje = \CalculosFinancieros::validarEstimacionEscenarioInversion(
            0,
            1000000,
            100,
            50,
            'mensual'
        );

        self::assertIsString($mensaje);
        self::assertStringContainsString('demasiado grande', $mensaje);
    }

    public function testCalcularInflacionConPlazoCero(): void
    {
        $resultado = \CalculosFinancieros::calcularInflacionProyeccion(10000, 3, 0);

        self::assertSame(10000.0, $resultado['poder_adquisitivo_final']);
        self::assertSame(0.0, $resultado['perdida_estimada']);
        self::assertSame(10000.0, $resultado['cantidad_futura_necesaria']);
        self::assertSame(0.0, $resultado['diferencia_necesaria']);
    }

    public function testCalcularInflacionConCantidadCero(): void
    {
        $resultado = \CalculosFinancieros::calcularInflacionProyeccion(0, 3, 10);

        self::assertSame(0.0, $resultado['poder_adquisitivo_final']);
        self::assertSame(0.0, $resultado['perdida_estimada']);
        self::assertSame(0.0, $resultado['cantidad_futura_necesaria']);
        self::assertSame(0.0, $resultado['diferencia_necesaria']);
    }
}
