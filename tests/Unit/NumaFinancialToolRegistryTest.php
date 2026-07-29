<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
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
            self::assertNotEmpty($definition->requiredParameters());
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

    public function testRechazaToolsNoRegistradas(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new \NumaFinancialToolRegistry())->get('ejecutar_sql');
    }

    public function testRechazaParametrosAdicionalesIncluidoUsuarioId(): void
    {
        $this->assertInvalidArguments('obtener_resumen_financiero', [
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
            'usuario_id' => 99,
        ]);

        $this->assertInvalidArguments('obtener_ranking_categorias', [
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
            'sql' => 'SELECT * FROM gastos',
        ]);
    }

    public function testRechazaEnumCategoriaFechaYLimiteInvalidos(): void
    {
        $this->assertInvalidArguments('obtener_evolucion_financiera', [
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
            'agrupacion' => 'semana',
        ]);

        $this->assertInvalidArguments('comparar_periodos', [
            'fecha_inicio_a' => '2026-07-01',
            'fecha_fin_a' => '2026-07-31',
            'fecha_inicio_b' => '2026-06-01',
            'fecha_fin_b' => '2026-06-30',
            'metrica' => 'gastos',
            'categoria' => 'categoria_inexistente',
        ]);

        $this->assertInvalidArguments('obtener_ranking_categorias', [
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
            'limite' => 25,
        ]);

        $this->assertInvalidArguments('obtener_estadisticas_movimientos', [
            'fecha_inicio' => '2026-02-30',
            'fecha_fin' => '2026-07-31',
            'metrica' => 'gastos',
        ]);
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
