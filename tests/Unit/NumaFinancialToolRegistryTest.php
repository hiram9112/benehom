<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/NumaFinancialTools.php';
require_once APP_PATH . '/services/NumaFinancialDataToolContract.php';

final class NumaFinancialToolRegistryTest extends TestCase
{
    public function testRegistraSoloLaConsultaFinancieraCanonica(): void
    {
        $registry = new \NumaFinancialToolRegistry();

        self::assertSame(['consultar_datos_financieros'], $registry->names());
        self::assertSame($registry->names(), array_keys($registry->all()));
        self::assertSame('consultar_datos_financieros', $registry->get('consultar_datos_financieros')->functionDeclaration()['name']);

        $this->expectException(InvalidArgumentException::class);
        $registry->get('obtener_resumen_financiero');
    }

    public function testDeclaracionActivaEsElContratoCanonicoCompatibleConGemini(): void
    {
        $declaration = (new \NumaFinancialToolRegistry())->get('consultar_datos_financieros')->functionDeclaration();

        self::assertSame('consultar_datos_financieros', $declaration['name']);
        self::assertSame(['periodos'], $declaration['parameters']['required']);
        self::assertArrayNotHasKey('oneOf', $declaration['parameters']);
        self::assertArrayNotHasKey('anyOf', $declaration['parameters']);
        self::assertStringNotContainsString('"salario"', json_encode($declaration, JSON_THROW_ON_ERROR));
    }

    public function testContratoDerivaLaTaxonomiaCompletaDeLosCatalogos(): void
    {
        $parameters = (new \NumaFinancialDataToolContract())->functionDeclaration()['parameters']['properties'];
        $selector = $parameters['selectores']['items']['properties'];
        $catalog = new \NumaFinancialCategoryCatalog();

        self::assertCount(5, $catalog->incomeAreaValues());
        self::assertCount(19, array_filter($selector['categoria']['enum'], fn (string $key): bool => $catalog->category($key)['kind'] === 'ingreso'));
        self::assertCount(2, $selector['tipo']['enum']);
        self::assertCount(16, $catalog->expenseAreaValues());
        self::assertCount(71, array_filter($selector['categoria']['enum'], fn (string $key): bool => $catalog->category($key)['kind'] === 'gasto'));
        self::assertStringContainsString('gastos/esencial/suministros', $parameters['selectores']['description']);
        self::assertStringContainsString('electricidad (Electricidad)', $parameters['selectores']['description']);
        self::assertStringContainsString('gastos/flexible/restauracion', $parameters['selectores']['description']);
        self::assertStringContainsString('comida_domicilio (Comida a domicilio)', $parameters['selectores']['description']);
    }

    public function testContratoNormalizaYDeduplicaSelectores(): void
    {
        $validated = (new \NumaFinancialDataToolContract())->validateArguments([
            'periodos' => [['tipo' => 'mes', 'mes' => '2026-07']],
            'selectores' => [
                ['categoria' => 'electricidad'],
                ['categoria' => 'electricidad'],
                ['area' => 'trabajo'],
            ],
        ]);

        self::assertSame([
            ['ambito' => 'gastos', 'tipo' => 'esencial', 'area' => 'suministros', 'categoria' => 'electricidad'],
            ['ambito' => 'ingresos', 'area' => 'trabajo'],
        ], $validated['selectores']);
    }

    #[DataProvider('invalidArguments')]
    public function testContratoRechazaSelectoresYPeriodosInvalidos(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new \NumaFinancialDataToolContract())->validateArguments($arguments);
    }

    /** @return array<string, array{0:array<string, mixed>}> */
    public static function invalidArguments(): array
    {
        return [
            'campo ambiguo en periodo' => [[
                'periodos' => [['tipo' => 'mes', 'mes' => '2026-07', 'inicio' => '2026-07-01']],
            ]],
            'categoria legacy' => [[
                'periodos' => [['tipo' => 'mes', 'mes' => '2026-07']],
                'selectores' => [['categoria' => 'salario']],
            ]],
            'relacion incompatible' => [[
                'periodos' => [['tipo' => 'mes', 'mes' => '2026-07']],
                'selectores' => [['ambito' => 'ingresos', 'categoria' => 'electricidad']],
            ]],
        ];
    }

    public function testLimitesDeRegistroNoAdmitenTruncado(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new \NumaFinancialToolRegistry(maxToolCalls: 6);
    }
}
