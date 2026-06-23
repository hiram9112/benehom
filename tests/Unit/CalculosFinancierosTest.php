<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CalculosFinancierosTest extends TestCase
{
    public function testCalculaHipotecaConAmortizacionFrancesa(): void
    {
        $resultado = \CalculosFinancieros::calcularCalculadoraHipoteca(200000, 3, 30);

        self::assertSame(843.21, $resultado['cuota_mensual']);
        self::assertSame(103554.9, $resultado['total_intereses']);
        self::assertSame(303554.9, $resultado['total_pagado']);
    }

    public function testCalculaHipotecaSinIntereses(): void
    {
        $resultado = \CalculosFinancieros::calcularCalculadoraHipoteca(120000, 0, 20);

        self::assertSame(500.0, $resultado['cuota_mensual']);
        self::assertSame(0.0, $resultado['total_intereses']);
        self::assertSame(120000.0, $resultado['total_pagado']);
    }

    public function testCalculaEscenarioInversionCompuesto(): void
    {
        $resultado = \CalculosFinancieros::calcularEscenarioInversion(10000, 250, 6, 10, 'mensual');

        self::assertSame(30000.0, $resultado['total_aportaciones_plazo']);
        self::assertSame(40000.0, $resultado['capital_total_aportado']);
        self::assertSame(59163.8, $resultado['valor_final_estimado']);
        self::assertSame(19163.8, $resultado['rendimiento_estimado']);
        self::assertSame(47.91, $resultado['roi_porcentaje']);
        self::assertSame(12, $resultado['periodos_por_anio']);
        self::assertSame(1, $resultado['meses_por_periodo']);
    }

    public function testCalculaEscenarioInversionSinRentabilidad(): void
    {
        $resultado = \CalculosFinancieros::calcularEscenarioInversion(10000, 250, 0, 10, 'mensual');

        self::assertSame(30000.0, $resultado['total_aportaciones_plazo']);
        self::assertSame(40000.0, $resultado['capital_total_aportado']);
        self::assertSame(40000.0, $resultado['valor_final_estimado']);
        self::assertSame(0.0, $resultado['rendimiento_estimado']);
        self::assertSame(0.0, $resultado['roi_porcentaje']);
    }

    public function testCalculaEscenarioInversionSinAportacionesMensuales(): void
    {
        $resultado = \CalculosFinancieros::calcularEscenarioInversion(10000, 0, 6, 10, 'mensual');

        self::assertSame(0.0, $resultado['total_aportaciones_plazo']);
        self::assertSame(10000.0, $resultado['capital_total_aportado']);
        self::assertSame(18193.97, $resultado['valor_final_estimado']);
        self::assertSame(8193.97, $resultado['rendimiento_estimado']);
        self::assertSame(81.94, $resultado['roi_porcentaje']);
    }

    /**
     * @param array{0:int, 1:int, 2:float} $esperado
     */
    #[DataProvider('frecuenciasReinversionProvider')]
    public function testCalculaEscenarioInversionSegunFrecuenciaReinversion(string $frecuencia, array $esperado): void
    {
        $resultado = \CalculosFinancieros::calcularEscenarioInversion(10000, 0, 6, 10, $frecuencia);

        self::assertSame($esperado[0], $resultado['periodos_por_anio']);
        self::assertSame($esperado[1], $resultado['meses_por_periodo']);
        self::assertSame($esperado[2], $resultado['valor_final_estimado']);
    }

    /**
     * @return array<string, array{0:string, 1:array{0:int, 1:int, 2:float}}>
     */
    public static function frecuenciasReinversionProvider(): array
    {
        return [
            'mensual' => ['mensual', [12, 1, 18193.97]],
            'trimestral' => ['trimestral', [4, 3, 18140.18]],
            'semestral' => ['semestral', [2, 6, 18061.11]],
            'anual' => ['anual', [1, 12, 17908.48]],
        ];
    }

    public function testCalculaInflacionProyeccion(): void
    {
        $resultado = \CalculosFinancieros::calcularInflacionProyeccion(10000, 3, 10);

        self::assertSame(7440.94, $resultado['poder_adquisitivo_final']);
        self::assertSame(2559.06, $resultado['perdida_estimada']);
        self::assertSame(13439.16, $resultado['cantidad_futura_necesaria']);
        self::assertSame(3439.16, $resultado['diferencia_necesaria']);
    }

    public function testCalculaMesesHastaFechaObjetivoConRelojInyectado(): void
    {
        $hoy = new DateTimeImmutable('2026-01-01');

        self::assertSame(6, \CalculosFinancieros::calcularMesesHastaFechaObjetivo('2026-06-23', $hoy));
        self::assertNull(\CalculosFinancieros::calcularMesesHastaFechaObjetivo('2026-01-01', $hoy));
        self::assertNull(\CalculosFinancieros::calcularMesesHastaFechaObjetivo('2025-12-31', $hoy));
        self::assertNull(\CalculosFinancieros::calcularMesesHastaFechaObjetivo('2026-02-31', $hoy));
        self::assertNull(\CalculosFinancieros::calcularMesesHastaFechaObjetivo('no-es-fecha', $hoy));
    }

    #[DataProvider('cantidadesNormalizablesProvider')]
    public function testNormalizaCantidades(mixed $valor, ?float $esperado): void
    {
        self::assertSame($esperado, \CalculosFinancieros::normalizarCantidad($valor));
    }

    /**
     * @return array<string, array{0:mixed, 1:?float}>
     */
    public static function cantidadesNormalizablesProvider(): array
    {
        return [
            'coma decimal' => ['123,45', 123.45],
            'punto decimal' => ['123.45', 123.45],
            'espacios' => ['  99,5  ', 99.5],
            'vacio' => ['', null],
            'no numerico' => ['abc', null],
            'no finito' => ['1e309', null],
        ];
    }

    public function testValidaOverflowDeEstimacionInversion(): void
    {
        $mensaje = \CalculosFinancieros::validarEstimacionEscenarioInversion(
            9007199254740991,
            0,
            100,
            100,
            'mensual'
        );

        self::assertIsString($mensaje);
        self::assertStringContainsString('resultado demasiado grande', $mensaje);
        self::assertNull(\CalculosFinancieros::validarEstimacionEscenarioInversion(10000, 250, 6, 10, 'mensual'));
    }
}
