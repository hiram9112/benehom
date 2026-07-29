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
}
