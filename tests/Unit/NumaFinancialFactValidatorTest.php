<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/NumaFinancialTools.php';
require_once APP_PATH . '/services/NumaFinancialFactValidator.php';

final class NumaFinancialFactValidatorTest extends TestCase
{
    public function testAutorizaSoloLosLiteralesCanonicosDeLaTool(): void
    {
        $validator = new \NumaFinancialFactValidator();
        $results = [[
            'tool' => \NumaFinancialToolRegistry::OBTENER_ESTADISTICAS_MOVIMIENTOS,
            'periodo' => ['inicio' => '2026-07-01', 'fin' => '2026-07-31'],
            'total' => '1200.00',
            'promedio' => '600.00',
            'cantidad_movimientos' => 2,
            'porcentaje' => 50.0,
        ]];

        self::assertTrue($validator->validates(
            'Del 2026-07-01 al 2026-07-31, el total fue 1200 euros, el promedio 600,00 EUR, hubo 2 movimientos y representó 50%.',
            $results
        ));
        self::assertFalse($validator->validates('El total fue 1201 EUR.', $results));
        self::assertFalse($validator->validates('El total fue 1200 EUR y representó 50.01%.', $results));
        self::assertFalse($validator->validates('El total fue 1200 EUR el 2026-08-01.', $results));
        self::assertFalse($validator->validates('El total fue 1200 EUR en 3 movimientos.', $results));
    }

    public function testReconoceImportesEURConSeparadorDecimalYRechazaLosNoAutorizados(): void
    {
        $validator = new \NumaFinancialFactValidator();
        $results = [[
            'tool' => \NumaFinancialToolRegistry::OBTENER_RESUMEN_FINANCIERO,
            'ingresos' => '1200.50',
        ]];

        self::assertTrue($validator->validates('Los ingresos fueron 1200,50 EUR.', $results));
        self::assertTrue($validator->validates('Los ingresos fueron 1200.50 EUR.', $results));
        self::assertFalse($validator->validates('Los ingresos fueron 1200,51 EUR.', $results));
    }

    public function testExtraeHechosDeCategoriaAnidada(): void
    {
        $validator = new \NumaFinancialFactValidator();
        $results = [[
            'tool' => \NumaFinancialToolRegistry::OBTENER_RANKING_CATEGORIAS,
            'categorias' => [[
                'total' => '200.50',
                'porcentaje' => 33.33,
            ]],
        ]];

        self::assertTrue($validator->validates('La categoría suma 200,50 EUR y representa 33,33%.', $results));
    }

    public function testExtraeHechosDeEvolucionAnidada(): void
    {
        $validator = new \NumaFinancialFactValidator();
        $results = [[
            'tool' => \NumaFinancialToolRegistry::OBTENER_EVOLUCION_FINANCIERA,
            'evolucion' => [[
                'mes' => '2026-07',
                'valor' => '450.00',
            ]],
        ]];

        self::assertTrue($validator->validates('En 2026-07 el valor fue 450.00 EUR.', $results));
    }

    public function testExtraeHechosDeMovimientoAnidado(): void
    {
        $validator = new \NumaFinancialFactValidator();
        $results = [[
            'tool' => \NumaFinancialToolRegistry::OBTENER_MOVIMIENTOS,
            'movimientos' => [[
                'fecha' => '2026-07-15',
                'cantidad' => '78.25',
            ]],
        ]];

        self::assertTrue($validator->validates('El 2026-07-15 hay un movimiento de 78.25 EUR.', $results));
    }

    public function testRechazaEtiquetasTecnicasAunqueLosHechosEstanAutorizados(): void
    {
        $results = [[
            'tool' => \NumaFinancialToolRegistry::COMPARAR_PERIODOS,
            'periodo_a' => ['inicio' => '2026-05-01', 'fin' => '2026-05-31'],
            'periodo_b' => ['inicio' => '2026-06-01', 'fin' => '2026-06-30'],
            'valor_a' => '1854.58',
            'valor_b' => '2304.28',
            'diferencia_absoluta' => '449.70',
        ]];

        self::assertFalse((new \NumaFinancialFactValidator())->validates(
            'Periodo A: 2026-05-01 al 2026-05-31, 1854.58 EUR. Periodo B: 2026-06-01 al 2026-06-30, 2304.28 EUR. Diferencia: 449.70 EUR.',
            $results,
        ));
    }

    public function testFallbackDeComparacionExplicaUnaDisminucion(): void
    {
        $message = (new \NumaFinancialFactValidator())->fallback([[
            'tool' => \NumaFinancialToolRegistry::COMPARAR_PERIODOS,
            'metrica' => 'gastos',
            'periodo_a' => ['inicio' => '2026-06-01', 'fin' => '2026-06-30'],
            'periodo_b' => ['inicio' => '2026-07-01', 'fin' => '2026-07-31'],
            'valor_a' => '800.00',
            'valor_b' => '700.00',
            'diferencia_absoluta' => '-100.00',
        ]]);

        self::assertSame(
            'En junio de 2026 gastaste 800.00 EUR, mientras que en julio de 2026 gastaste 700.00 EUR. Esto supone una disminución de 100.00 EUR en julio de 2026 respecto a junio de 2026.',
            $message,
        );
    }

    #[DataProvider('toolFallbacks')]
    public function testGeneraUnFallbackDeterministaParaCadaTool(array $result, string $expected): void
    {
        self::assertSame($expected, (new \NumaFinancialFactValidator())->fallback([$result]));
    }

    /** @return array<string, array{0:array<string, mixed>,1:string}> */
    public static function toolFallbacks(): array
    {
        return [
            'resumen' => [[
                'tool' => \NumaFinancialToolRegistry::OBTENER_RESUMEN_FINANCIERO,
                'periodo' => ['inicio' => '2026-07-01', 'fin' => '2026-07-31'],
                'ingresos' => '1200.00', 'gastos' => '800.00', 'ahorro_real' => '400.00',
            ], 'En julio de 2026, tus ingresos fueron 1200.00 EUR. Tus gastos fueron 800.00 EUR. Tu ahorro real fue 400.00 EUR.'],
            'ranking' => [[
                'tool' => \NumaFinancialToolRegistry::OBTENER_RANKING_CATEGORIAS,
                'periodo' => ['inicio' => '2026-07-01', 'fin' => '2026-07-31'],
                'categorias' => [['label' => 'Alimentación', 'total' => '200.00', 'porcentaje' => 50.0]],
            ], 'En julio de 2026, la categoría con mayor importe fue Alimentación, con 200.00 EUR (50.00%).'],
            'evolucion' => [[
                'tool' => \NumaFinancialToolRegistry::OBTENER_EVOLUCION_FINANCIERA,
                'periodo' => ['inicio' => '2026-07-01', 'fin' => '2026-07-31'],
                'evolucion' => [['mes' => '2026-07', 'valor' => '800.00']],
            ], 'En julio de 2026, el valor fue 800.00 EUR.'],
            'comparacion' => [[
                'tool' => \NumaFinancialToolRegistry::COMPARAR_PERIODOS,
                'metrica' => 'gastos',
                'periodo_a' => ['inicio' => '2026-06-01', 'fin' => '2026-06-30'],
                'periodo_b' => ['inicio' => '2026-07-01', 'fin' => '2026-07-31'],
                'valor_a' => '700.00', 'valor_b' => '800.00', 'diferencia_absoluta' => '100.00',
            ], 'En junio de 2026 gastaste 700.00 EUR, mientras que en julio de 2026 gastaste 800.00 EUR. Esto supone un aumento de 100.00 EUR en julio de 2026 respecto a junio de 2026.'],
            'estadisticas' => [[
                'tool' => \NumaFinancialToolRegistry::OBTENER_ESTADISTICAS_MOVIMIENTOS,
                'periodo' => ['inicio' => '2026-07-01', 'fin' => '2026-07-31'],
                'cantidad_movimientos' => 2, 'total' => '800.00',
            ], 'En julio de 2026, se registraron 2 movimientos, por un total de 800.00 EUR.'],
            'movimientos' => [[
                'tool' => \NumaFinancialToolRegistry::OBTENER_MOVIMIENTOS,
                'periodo' => ['inicio' => '2026-07-01', 'fin' => '2026-07-31'],
                'cantidad_total' => 2, 'importe_total' => '800.00',
            ], 'En julio de 2026, se encontraron 2 movimientos, con un importe total de 800.00 EUR.'],
        ];
    }
}
