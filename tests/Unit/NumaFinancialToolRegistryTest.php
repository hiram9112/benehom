<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/NumaFinancialTools.php';

final class NumaFinancialToolRegistryTest extends TestCase
{
    public function testRegistraUnicamenteLasToolsFinancierasPermitidas(): void
    {
        $registry = new \NumaFinancialToolRegistry();

        self::assertSame([
            'obtener_resumen_financiero',
            'obtener_ranking_categorias',
            'obtener_evolucion_financiera',
            'comparar_periodos',
            'obtener_estadisticas_movimientos',
            'obtener_movimientos',
        ], $registry->names());
        self::assertSame($registry->names(), array_keys($registry->all()));
    }

    public function testCadaToolTieneMetadatosYUnaImplementacionConcreta(): void
    {
        $registry = new \NumaFinancialToolRegistry();

        foreach ($registry->all() as $definition) {
            self::assertNotSame('', $definition->name());
            self::assertNotSame('', $definition->description());
            self::assertSame('object', $definition->parameterSchema()['type']);
            self::assertFalse($definition->parameterSchema()['additionalProperties']);
            self::assertTrue(
                in_array('metrica', $definition->requiredParameters(), true)
                || array_key_exists('periodo', $definition->parameterSchema()['properties'])
            );
            self::assertNotEmpty($definition->resultLimit());
            self::assertNotSame('', $definition->implementation());
        }
    }

    public function testDeclaraEnumsPermitidosSinParametrosDinamicos(): void
    {
        $registry = new \NumaFinancialToolRegistry();

        $ranking = $registry->get('obtener_ranking_categorias');
        self::assertSame(
            ['ingresos', 'gastos', 'gastos_esenciales', 'gastos_flexibles'],
            $ranking->allowedValues()['metrica']
        );

        $evolution = $registry->get('obtener_evolucion_financiera');
        self::assertSame(['mes', 'categoria', 'tipo'], $evolution->allowedValues()['agrupacion']);

        $stats = $registry->get('obtener_estadisticas_movimientos');
        self::assertSame(
            ['ingresos', 'gastos', 'gastos_esenciales', 'gastos_flexibles'],
            $stats->allowedValues()['metrica']
        );
    }

    public function testCatalogoReutilizaCategoriasDeFormulariosYDistingueGruposDeHojas(): void
    {
        $catalog = new \NumaFinancialCategoryCatalog();

        self::assertContains('nomina', $catalog->categoryValues());
        self::assertContains('regalos', $catalog->categoryValues());
        self::assertContains('compras', $catalog->groupValues());
        self::assertArrayHasKey('nomina', ingresoCategoriaLabels());
        self::assertArrayHasKey('regalos', gastoCategoriaLabels());
        self::assertSame('nomina', $catalog->resolveCategory('Nómina'));
        self::assertSame('regalos', $catalog->resolveCategory('Regalos'));
        self::assertSame('compras', $catalog->resolveGroup('Compras'));

        $this->expectException(InvalidArgumentException::class);
        $catalog->resolveCategory('Compras');
    }

    public function testCatalogoRechazaAliasDeCategoriaAmbiguo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new \NumaFinancialCategoryCatalog())->resolveCategory('Otros');
    }

    public function testRechazaToolsNoRegistradas(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new \NumaFinancialToolRegistry())->get('ejecutar_sql');
    }

    public function testRechazaUsuarioAutenticadoInternoInvalido(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new \NumaFinancialToolRegistry())->execute('obtener_resumen_financiero', 0, [
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
        ]);
    }

    /** @param array<string, mixed> $arguments */
    #[DataProvider('invalidArgumentCases')]
    public function testValidadorRechazaReglasComunes(string $tool, array $arguments): void
    {
        $this->assertInvalidArguments($tool, $arguments);
    }

    /**
     * @return array<string, array{0:string, 1:array<string, mixed>}>
     */
    public static function invalidArgumentCases(): array
    {
        return [
            'parametro adicional usuario_id' => ['obtener_resumen_financiero', [
                'fecha_inicio' => '2026-07-01',
                'fecha_fin' => '2026-07-31',
                'usuario_id' => 99,
            ]],
            'parametro adicional sql' => ['obtener_ranking_categorias', [
                'fecha_inicio' => '2026-07-01',
                'fecha_fin' => '2026-07-31',
                'sql' => 'SELECT * FROM gastos',
            ]],
            'fecha invalida' => ['obtener_estadisticas_movimientos', [
                'fecha_inicio' => '2026-02-30',
                'fecha_fin' => '2026-07-31',
                'metrica' => 'gastos',
            ]],
            'periodo invertido' => ['obtener_resumen_financiero', [
                'fecha_inicio' => '2026-07-31',
                'fecha_fin' => '2026-07-01',
            ]],
            'intervalo excesivo' => ['obtener_resumen_financiero', [
                'fecha_inicio' => '2024-01-01',
                'fecha_fin' => '2026-12-31',
            ]],
            'enum de agrupacion invalido' => ['obtener_evolucion_financiera', [
                'fecha_inicio' => '2026-07-01',
                'fecha_fin' => '2026-07-31',
                'agrupacion' => 'semana',
            ]],
            'enum de metrica invalido' => ['obtener_ranking_categorias', [
                'fecha_inicio' => '2026-07-01',
                'fecha_fin' => '2026-07-31',
                'metrica' => 'saldo',
            ]],
            'categoria inexistente' => ['comparar_periodos', [
                'fecha_inicio_a' => '2026-07-01',
                'fecha_fin_a' => '2026-07-31',
                'fecha_inicio_b' => '2026-06-01',
                'fecha_fin_b' => '2026-06-30',
                'metrica' => 'gastos',
                'categoria' => 'categoria_inexistente',
            ]],
            'grupo aplicado como categoria' => ['comparar_periodos', [
                'fecha_inicio_a' => '2026-07-01',
                'fecha_fin_a' => '2026-07-31',
                'fecha_inicio_b' => '2026-06-01',
                'fecha_fin_b' => '2026-06-30',
                'metrica' => 'gastos',
                'categoria' => 'Compras',
            ]],
            'limite fuera de rango' => ['obtener_ranking_categorias', [
                'fecha_inicio' => '2026-07-01',
                'fecha_fin' => '2026-07-31',
                'limite' => 25,
            ]],
            'limite no numerico' => ['obtener_ranking_categorias', [
                'fecha_inicio' => '2026-07-01',
                'fecha_fin' => '2026-07-31',
                'limite' => 'dos',
            ]],
            'orden de movimientos invalido' => ['obtener_movimientos', [
                'fecha_inicio' => '2026-07-01',
                'fecha_fin' => '2026-07-31',
                'orden' => 'id',
            ]],
            'movimientos con parametro dinamico' => ['obtener_movimientos', [
                'fecha_inicio' => '2026-07-01',
                'fecha_fin' => '2026-07-31',
                'campo' => 'cantidad',
            ]],
            'movimientos con direccion invalida' => ['obtener_movimientos', [
                'fecha_inicio' => '2026-07-01',
                'fecha_fin' => '2026-07-31',
                'direccion' => 'aleatoria',
            ]],
            'movimientos con limite excesivo' => ['obtener_movimientos', [
                'fecha_inicio' => '2026-07-01',
                'fecha_fin' => '2026-07-31',
                'limite' => 11,
            ]],
        ];
    }

    /** @param array<string, mixed> $arguments */
    private function assertInvalidArguments(string $tool, array $arguments): void
    {
        try {
            (new \NumaFinancialToolRegistry())->execute($tool, 1, $arguments);
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
            return;
        }

        self::fail('La tool financiera de Numa acepto parametros no permitidos.');
    }
}
