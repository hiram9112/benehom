<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/services/NumaFinancialTools.php';

final class NumaFinancialToolsTest extends IntegrationTestCase
{
    public function testCategoriaDevuelveSoloLaRamaSolicitadaConImporteExacto(): void
    {
        $user = $this->crearUsuario('numa-hierarchy-category@example.test');
        $this->insertGasto((int) $user['id'], 'esencial', 'electricidad', '12.34', '2026-07-03');

        $result = $this->execute((int) $user['id'], [
            'periodos' => [['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']],
            'selectores' => [['categoria' => 'electricidad']],
        ]);

        self::assertSame('consultar_datos_financieros', $result['tool']);
        self::assertSame([['mes' => '2026-07', 'gastos' => [
            'importe' => '12.34',
            'cobertura' => ['completa' => false, 'tipos_consultados' => 1, 'tipos_totales' => 2],
            'tipos' => [[
                'tipo' => 'esencial',
                'importe' => '12.34',
                'cobertura' => ['completa' => false, 'areas_consultadas' => 1, 'areas_totales' => 8],
                'areas' => [[
                    'area' => 'suministros',
                    'importe' => '12.34',
                    'cobertura' => ['completa' => false, 'categorias_consultadas' => 1, 'categorias_totales' => 5],
                    'categorias' => [['categoria' => 'electricidad', 'importe' => '12.34']],
                ]],
            ]],
        ]]], $result['meses']);
    }

    public function testAreaCompletaIncluyeCategoriasAusentesConCero(): void
    {
        $user = $this->crearUsuario('numa-hierarchy-area@example.test');
        $this->insertGasto((int) $user['id'], 'esencial', 'electricidad', '3.01', '2026-07-03');

        $area = $this->execute((int) $user['id'], [
            'periodos' => [['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']],
            'selectores' => [['area' => 'suministros']],
        ])['meses'][0]['gastos']['tipos'][0]['areas'][0];

        self::assertSame('suministros', $area['area']);
        self::assertSame('3.01', $area['importe']);
        self::assertSame(['completa' => true, 'categorias_consultadas' => 5, 'categorias_totales' => 5], $area['cobertura']);
        self::assertSame([
            'agua' => '0.00',
            'electricidad' => '3.01',
            'gas' => '0.00',
            'internet_telefonia_basica' => '0.00',
            'otros_gastos_suministros' => '0.00',
        ], array_column($area['categorias'], 'importe', 'categoria'));
    }

    public function testTipoAmbitoYJerarquiaCompletaUsanCoberturaDelCatalogo(): void
    {
        $user = $this->crearUsuario('numa-hierarchy-full@example.test');
        $userId = (int) $user['id'];
        $this->insertIngreso($userId, 'nomina', '1000.10', '2026-07-01');
        $this->insertGasto($userId, 'flexible', 'comida_domicilio', '20.20', '2026-07-02');

        $type = $this->execute($userId, [
            'periodos' => [['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']],
            'selectores' => [['tipo' => 'flexible']],
        ])['meses'][0]['gastos'];
        self::assertSame('20.20', $type['importe']);
        self::assertSame(['completa' => false, 'tipos_consultados' => 1, 'tipos_totales' => 2], $type['cobertura']);
        self::assertCount(8, $type['tipos'][0]['areas']);

        $full = $this->execute($userId, ['periodos' => [['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']]])['meses'][0];
        self::assertSame('1000.10', $full['ingresos']['importe']);
        self::assertSame(['completa' => true, 'areas_consultadas' => 5, 'areas_totales' => 5], $full['ingresos']['cobertura']);
        self::assertSame('20.20', $full['gastos']['importe']);
        self::assertSame(['completa' => true, 'tipos_consultados' => 2, 'tipos_totales' => 2], $full['gastos']['cobertura']);
        self::assertCount(2, $full['gastos']['tipos']);
    }

    public function testAmbitoIncluyeSoloLaRamaSolicitadaConCoberturaCompleta(): void
    {
        $user = $this->crearUsuario('numa-hierarchy-scope@example.test');
        $userId = (int) $user['id'];
        $this->insertIngreso($userId, 'nomina', '500.00', '2026-07-01');
        $this->insertGasto($userId, 'flexible', 'comida_domicilio', '20.00', '2026-07-02');

        $month = $this->execute($userId, [
            'periodos' => [['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']],
            'selectores' => [['ambito' => 'ingresos']],
        ])['meses'][0];

        self::assertSame(['mes', 'ingresos'], array_keys($month));
        self::assertSame('500.00', $month['ingresos']['importe']);
        self::assertSame(['completa' => true, 'areas_consultadas' => 5, 'areas_totales' => 5], $month['ingresos']['cobertura']);
        self::assertCount(5, $month['ingresos']['areas']);
    }

    public function testMesSinDatosConAmbitoCompletoConservaHojasYCobertura(): void
    {
        $user = $this->crearUsuario('numa-hierarchy-empty-month@example.test');

        $month = $this->execute((int) $user['id'], [
            'periodos' => [['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']],
            'selectores' => [['ambito' => 'ingresos']],
        ])['meses'][0];

        self::assertSame(['mes', 'ingresos'], array_keys($month));
        self::assertSame('2026-07', $month['mes']);
        self::assertSame('0.00', $month['ingresos']['importe']);
        self::assertSame(['completa' => true, 'areas_consultadas' => 5, 'areas_totales' => 5], $month['ingresos']['cobertura']);
        self::assertSame(19, count(array_merge(...array_column($month['ingresos']['areas'], 'categorias'))));
        self::assertSame(['0.00'], array_values(array_unique(array_column(
            array_merge(...array_column($month['ingresos']['areas'], 'categorias')),
            'importe',
        ))));
    }

    public function testRangosSolapadosConservanLaPrimeraAparicionSinDuplicados(): void
    {
        $user = $this->crearUsuario('numa-hierarchy-months@example.test');
        $userId = (int) $user['id'];
        $this->insertIngreso($userId, 'nomina', '1.10', '2026-06-01');
        $this->insertIngreso($userId, 'nomina', '2.20', '2026-08-01');

        $months = $this->execute($userId, [
            'periodos' => [
                ['mes_inicio' => '2026-08', 'mes_fin' => '2026-08'],
                ['mes_inicio' => '2026-06', 'mes_fin' => '2026-08'],
            ],
            'selectores' => [['categoria' => 'nomina']],
        ])['meses'];

        self::assertSame(['2026-08', '2026-06', '2026-07'], array_column($months, 'mes'));
        self::assertSame(['2.20', '1.10', '0.00'], array_column(array_column($months, 'ingresos'), 'importe'));
    }

    public function testMesesNoContiguosLleganComoPeriodosSeparados(): void
    {
        $user = $this->crearUsuario('numa-hierarchy-non-contiguous-months@example.test');
        $userId = (int) $user['id'];
        $this->insertIngreso($userId, 'nomina', '1.10', '2026-01-01');
        $this->insertIngreso($userId, 'nomina', '3.30', '2026-03-01');

        $months = $this->execute($userId, [
            'periodos' => [
                ['mes_inicio' => '2026-01', 'mes_fin' => '2026-01'],
                ['mes_inicio' => '2026-03', 'mes_fin' => '2026-03'],
            ],
            'selectores' => [['categoria' => 'nomina']],
        ])['meses'];

        self::assertSame(['2026-01', '2026-03'], array_column($months, 'mes'));
        self::assertSame(['1.10', '3.30'], array_column(array_column($months, 'ingresos'), 'importe'));
    }

    public function testDatosDeOtroUsuarioNoAfectanElResultado(): void
    {
        $user = $this->crearUsuario('numa-hierarchy-owner@example.test');
        $other = $this->crearUsuario('numa-hierarchy-other@example.test');
        $this->insertGasto((int) $user['id'], 'flexible', 'comida_domicilio', '9.99', '2026-07-01');
        $this->insertGasto((int) $other['id'], 'flexible', 'comida_domicilio', '999.99', '2026-07-01');

        $result = $this->execute((int) $user['id'], [
            'periodos' => [['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']],
            'selectores' => [['categoria' => 'comida_domicilio']],
        ]);

        self::assertSame('9.99', $result['meses'][0]['gastos']['importe']);
    }

    public function testCentimosSeConservan(): void
    {
        $user = $this->crearUsuario('numa-hierarchy-limits@example.test');
        $userId = (int) $user['id'];
        $this->insertIngreso($userId, 'nomina', '0.10', '2026-07-01');
        $this->insertIngreso($userId, 'paga_extra', '0.20', '2026-07-02');

        $result = $this->execute($userId, [
            'periodos' => [['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']],
            'selectores' => [['area' => 'trabajo']],
        ]);
        self::assertSame('0.30', $result['meses'][0]['ingresos']['importe']);
    }

    public function testResultadoQueExcedeElTamanoSerializadoFallaSinTruncar(): void
    {
        $user = $this->crearUsuario('numa-hierarchy-json-limit@example.test');
        $registry = new \NumaFinancialToolRegistry(
            new \NumaFinancialToolExecutor($this->db),
            maxToolResultBytes: 10,
        );

        $this->expectException(\NumaFinancialToolLimitExceeded::class);
        $registry->execute('consultar_datos_financieros', (int) $user['id'], [
            'periodos' => [['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']],
            'selectores' => [['categoria' => 'electricidad']],
        ]);
    }

    public function testCapDeDiezMilFilasRechazaDiezMilUnaSinDevolverResultadoParcial(): void
    {
        $previous = $_ENV['NUMA_MAX_TOOL_RESULT_ROWS'] ?? null;
        $_ENV['NUMA_MAX_TOOL_RESULT_ROWS'] = '10000';
        $user = $this->crearUsuario('numa-real-row-cap@example.test');
        $userId = (int) $user['id'];
        $this->insertManyGastos($userId, 10001);

        try {
            $registry = new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db));
            try {
                $registry->execute('consultar_datos_financieros', $userId, [
                    'periodos' => [['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']],
                    'selectores' => [['categoria' => 'electricidad']],
                ]);
                self::fail('La fila 10001 debía abortar la tool sin devolver una lista parcial.');
            } catch (\NumaFinancialToolLimitExceeded) {
                self::addToAssertionCount(1);
            }
        } finally {
            if ($previous === null) {
                unset($_ENV['NUMA_MAX_TOOL_RESULT_ROWS']);
            } else {
                $_ENV['NUMA_MAX_TOOL_RESULT_ROWS'] = $previous;
            }
        }
    }

    public function testRangoAmplioConPocasFilasRealesNoSeRechazaPorCardinalidadTeorica(): void
    {
        $user = $this->crearUsuario('numa-sparse-large-range@example.test');
        $userId = (int) $user['id'];
        $this->insertGasto($userId, 'esencial', 'electricidad', '12.34', '2000-01-03');
        $definition = (new \NumaFinancialToolRegistry())->get('consultar_datos_financieros');

        $result = (new \NumaFinancialToolExecutor($this->db))->execute($definition, $userId, [
            'periodos' => [['mes_inicio' => '2000-01', 'mes_fin' => '2099-12']],
            'selectores' => [['categoria' => 'electricidad']],
        ]);

        self::assertSame(1200, count($result['meses']));
        self::assertSame('12.34', $result['meses'][0]['gastos']['importe']);
        self::assertSame('0.00', $result['meses'][1199]['gastos']['importe']);
    }

    public function testCapDeFilasLogicasSeAplicaDuranteLaConstruccionSinResultadoParcial(): void
    {
        $user = $this->crearUsuario('numa-logical-row-cap@example.test');
        $registry = new \NumaFinancialToolRegistry(
            new \NumaFinancialToolExecutor($this->db, maxToolResultRows: 3),
        );

        $this->expectException(\NumaFinancialToolLimitExceeded::class);
        $registry->execute('consultar_datos_financieros', (int) $user['id'], [
            'periodos' => [['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']],
            'selectores' => [['categoria' => 'electricidad']],
        ]);
    }

    public function testResultadoLegitimoSuperiorAlAntiguoLimiteSeEntregaCompleto(): void
    {
        $user = $this->crearUsuario('numa-hierarchy-large-result@example.test');
        $result = (new \NumaFinancialToolRegistry(
            new \NumaFinancialToolExecutor($this->db),
            maxToolResultBytes: \NumaFinancialToolRegistry::MAX_TOOL_RESULT_BYTES,
        ))->execute('consultar_datos_financieros', (int) $user['id'], [
            'periodos' => [['mes_inicio' => '2025-08', 'mes_fin' => '2026-07']],
        ]);

        $serialized = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        self::assertGreaterThan(2500, strlen($serialized));
        self::assertSame(12, count($result['meses']));
        self::assertSame('2025-08', $result['meses'][0]['mes']);
        self::assertSame('2026-07', $result['meses'][11]['mes']);
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    private function execute(int $userId, array $arguments): array
    {
        return (new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db)))->execute(
            'consultar_datos_financieros',
            $userId,
            $arguments,
        );
    }

    private function insertIngreso(int $userId, string $category, string $amount, string $date): void
    {
        $stmt = $this->db->prepare('INSERT INTO ingresos (usuario_id, categoria, cantidad, fecha) VALUES (:usuario_id, :categoria, :cantidad, :fecha)');
        $stmt->execute([':usuario_id' => $userId, ':categoria' => $category, ':cantidad' => $amount, ':fecha' => $date]);
    }

    private function insertGasto(int $userId, string $type, string $category, string $amount, string $date): void
    {
        $stmt = $this->db->prepare('INSERT INTO gastos (usuario_id, tipo, categoria, cantidad, fecha) VALUES (:usuario_id, :tipo, :categoria, :cantidad, :fecha)');
        $stmt->execute([
            ':usuario_id' => $userId,
            ':tipo' => $type,
            ':categoria' => $category,
            ':cantidad' => $amount,
            ':fecha' => $date,
        ]);
    }

    private function insertManyGastos(int $userId, int $count): void
    {
        for ($offset = 0; $offset < $count; $offset += 500) {
            $batchSize = min(500, $count - $offset);
            $values = implode(', ', array_fill(0, $batchSize, "(?, 'esencial', 'electricidad', '1.00', '2026-07-03')"));
            $statement = $this->db->prepare(
                'INSERT INTO gastos (usuario_id, tipo, categoria, cantidad, fecha) VALUES ' . $values
            );
            $statement->execute(array_fill(0, $batchSize, $userId));
        }
    }
}
