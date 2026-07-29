<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../helpers/utils.php';

final class NumaFinancialToolDefinition
{
    /**
     * @param array<string, mixed> $parameterSchema
     * @param array<int, string> $requiredParameters
     * @param array<string, array<int, string>> $allowedValues
     * @param array<string, int> $resultLimit
     */
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly array $parameterSchema,
        private readonly array $requiredParameters,
        private readonly array $allowedValues,
        private readonly array $resultLimit,
        private readonly string $implementation,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('La tool de Numa debe tener nombre.');
        }

        if (trim($description) === '') {
            throw new InvalidArgumentException('La tool de Numa debe tener descripcion.');
        }

        if (trim($implementation) === '') {
            throw new InvalidArgumentException('La tool de Numa debe tener implementacion concreta.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * @return array<string, mixed>
     */
    public function parameterSchema(): array
    {
        return $this->parameterSchema;
    }

    /**
     * @return array<int, string>
     */
    public function requiredParameters(): array
    {
        return $this->requiredParameters;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function allowedValues(): array
    {
        return $this->allowedValues;
    }

    /**
     * @return array<string, int>
     */
    public function resultLimit(): array
    {
        return $this->resultLimit;
    }

    public function implementation(): string
    {
        return $this->implementation;
    }
}

final class NumaFinancialToolRegistry
{
    public const OBTENER_RESUMEN_FINANCIERO = 'obtener_resumen_financiero';
    public const OBTENER_RANKING_CATEGORIAS = 'obtener_ranking_categorias';
    public const OBTENER_EVOLUCION_FINANCIERA = 'obtener_evolucion_financiera';
    public const COMPARAR_PERIODOS = 'comparar_periodos';
    public const OBTENER_ESTADISTICAS_MOVIMIENTOS = 'obtener_estadisticas_movimientos';

    /** @var array<int, string> */
    private const TOOL_NAMES = [
        self::OBTENER_RESUMEN_FINANCIERO,
        self::OBTENER_RANKING_CATEGORIAS,
        self::OBTENER_EVOLUCION_FINANCIERA,
        self::COMPARAR_PERIODOS,
        self::OBTENER_ESTADISTICAS_MOVIMIENTOS,
    ];

    /** @var array<int, string> */
    private const FINANCIAL_METRICS = [
        'ingresos',
        'gastos',
        'gastos_esenciales',
        'gastos_flexibles',
        'ahorro_posible',
        'ahorro_real',
    ];

    /** @var array<int, string> */
    private const MOVEMENT_METRICS = [
        'ingresos',
        'gastos',
        'gastos_esenciales',
        'gastos_flexibles',
    ];

    /** @var array<int, string> */
    private const GROUPINGS = ['mes', 'categoria', 'tipo'];

    /** @var array<string, NumaFinancialToolDefinition> */
    private readonly array $definitions;

    public function __construct(
        private readonly NumaFinancialToolExecutor $executor = new NumaFinancialToolExecutor(),
    ) {
        $this->definitions = self::buildDefinitions();
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return self::TOOL_NAMES;
    }

    /**
     * @return array<string, NumaFinancialToolDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    public function get(string $name): NumaFinancialToolDefinition
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException('Tool financiera de Numa no registrada.');
        }

        return $this->definitions[$name];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function execute(string $name, int $usuarioId, array $arguments): array
    {
        return $this->executor->execute($this->get($name), $usuarioId, $arguments);
    }

    /**
     * @return array<string, NumaFinancialToolDefinition>
     */
    private static function buildDefinitions(): array
    {
        $categories = array_values(array_unique(array_merge(
            array_keys(gastoCategoriaLabels()),
            array_keys(ingresoCategoriaLabels())
        )));
        sort($categories);

        $definitions = [
            self::OBTENER_RESUMEN_FINANCIERO => new NumaFinancialToolDefinition(
                self::OBTENER_RESUMEN_FINANCIERO,
                'Devuelve totales agregados de ingresos, gastos, ahorro posible y ahorro real de un periodo.',
                self::dateRangeSchema(),
                ['fecha_inicio', 'fecha_fin'],
                [],
                ['max_items' => 1],
                'executeResumenFinanciero'
            ),
            self::OBTENER_RANKING_CATEGORIAS => new NumaFinancialToolDefinition(
                self::OBTENER_RANKING_CATEGORIAS,
                'Devuelve un ranking agregado por categoria para una metrica financiera permitida.',
                self::schema([
                    'fecha_inicio' => ['type' => 'string', 'format' => 'date'],
                    'fecha_fin' => ['type' => 'string', 'format' => 'date'],
                    'metrica' => ['type' => 'string', 'enum' => ['ingresos', 'gastos', 'gastos_esenciales', 'gastos_flexibles']],
                    'limite' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                ]),
                ['fecha_inicio', 'fecha_fin'],
                [
                    'metrica' => ['ingresos', 'gastos', 'gastos_esenciales', 'gastos_flexibles'],
                ],
                ['max_items' => 10],
                'executeRankingCategorias'
            ),
            self::OBTENER_EVOLUCION_FINANCIERA => new NumaFinancialToolDefinition(
                self::OBTENER_EVOLUCION_FINANCIERA,
                'Devuelve una evolucion agregada por mes, categoria o tipo permitido.',
                self::schema([
                    'fecha_inicio' => ['type' => 'string', 'format' => 'date'],
                    'fecha_fin' => ['type' => 'string', 'format' => 'date'],
                    'metrica' => ['type' => 'string', 'enum' => self::FINANCIAL_METRICS],
                    'agrupacion' => ['type' => 'string', 'enum' => self::GROUPINGS],
                    'limite' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 24],
                ]),
                ['fecha_inicio', 'fecha_fin', 'agrupacion'],
                [
                    'metrica' => self::FINANCIAL_METRICS,
                    'agrupacion' => self::GROUPINGS,
                ],
                ['max_items' => 24],
                'executeEvolucionFinanciera'
            ),
            self::COMPARAR_PERIODOS => new NumaFinancialToolDefinition(
                self::COMPARAR_PERIODOS,
                'Compara una metrica agregada entre dos periodos cerrados.',
                self::schema([
                    'fecha_inicio_a' => ['type' => 'string', 'format' => 'date'],
                    'fecha_fin_a' => ['type' => 'string', 'format' => 'date'],
                    'fecha_inicio_b' => ['type' => 'string', 'format' => 'date'],
                    'fecha_fin_b' => ['type' => 'string', 'format' => 'date'],
                    'metrica' => ['type' => 'string', 'enum' => self::FINANCIAL_METRICS],
                    'categoria' => ['type' => 'string', 'enum' => $categories],
                ]),
                ['fecha_inicio_a', 'fecha_fin_a', 'fecha_inicio_b', 'fecha_fin_b', 'metrica'],
                [
                    'metrica' => self::FINANCIAL_METRICS,
                    'categoria' => $categories,
                ],
                ['max_items' => 1],
                'executeCompararPeriodos'
            ),
            self::OBTENER_ESTADISTICAS_MOVIMIENTOS => new NumaFinancialToolDefinition(
                self::OBTENER_ESTADISTICAS_MOVIMIENTOS,
                'Devuelve promedio, maximo, minimo, total y cantidad de movimientos agregados de un periodo.',
                self::schema([
                    'fecha_inicio' => ['type' => 'string', 'format' => 'date'],
                    'fecha_fin' => ['type' => 'string', 'format' => 'date'],
                    'metrica' => ['type' => 'string', 'enum' => self::MOVEMENT_METRICS],
                    'categoria' => ['type' => 'string', 'enum' => $categories],
                ]),
                ['fecha_inicio', 'fecha_fin', 'metrica'],
                [
                    'metrica' => self::MOVEMENT_METRICS,
                    'categoria' => $categories,
                ],
                ['max_items' => 1],
                'executeEstadisticasMovimientos'
            ),
        ];

        if (array_keys($definitions) !== self::TOOL_NAMES) {
            throw new RuntimeException('El registro de tools financieras de Numa no coincide con el catalogo cerrado.');
        }

        return $definitions;
    }

    /**
     * @return array<string, mixed>
     */
    private static function dateRangeSchema(): array
    {
        return self::schema([
            'fecha_inicio' => ['type' => 'string', 'format' => 'date'],
            'fecha_fin' => ['type' => 'string', 'format' => 'date'],
        ]);
    }

    /**
     * @param array<string, mixed> $properties
     * @return array<string, mixed>
     */
    private static function schema(array $properties): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
        ];
    }
}

final class NumaFinancialToolExecutor
{
    public function __construct(private readonly ?PDO $connection = null)
    {
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function execute(NumaFinancialToolDefinition $definition, int $usuarioId, array $arguments): array
    {
        if ($usuarioId <= 0) {
            throw new InvalidArgumentException('Usuario de Numa no valido.');
        }

        $this->validateArguments($definition, $arguments);

        return match ($definition->implementation()) {
            'executeResumenFinanciero' => $this->executeResumenFinanciero($usuarioId, $arguments),
            'executeRankingCategorias' => $this->executeRankingCategorias($usuarioId, $arguments),
            'executeEvolucionFinanciera' => $this->executeEvolucionFinanciera($usuarioId, $arguments),
            'executeCompararPeriodos' => $this->executeCompararPeriodos($usuarioId, $arguments),
            'executeEstadisticasMovimientos' => $this->executeEstadisticasMovimientos($usuarioId, $arguments),
            default => throw new InvalidArgumentException('Implementacion de tool financiera de Numa no registrada.'),
        };
    }

    /** @param array<string, mixed> $arguments */
    private function validateArguments(NumaFinancialToolDefinition $definition, array $arguments): void
    {
        $schema = $definition->parameterSchema();
        $properties = $schema['properties'] ?? [];

        if (!is_array($properties)) {
            throw new InvalidArgumentException('Esquema de parametros de Numa no valido.');
        }

        foreach ($arguments as $key => $_value) {
            if (!is_string($key) || !array_key_exists($key, $properties)) {
                throw new InvalidArgumentException('Parametro de Numa no permitido.');
            }
        }

        foreach ($definition->requiredParameters() as $key) {
            if (!array_key_exists($key, $arguments)) {
                throw new InvalidArgumentException('Parametro obligatorio de Numa ausente.');
            }
        }

        foreach ($properties as $key => $property) {
            if (!array_key_exists($key, $arguments) || !is_array($property)) {
                continue;
            }

            $type = $property['type'] ?? null;

            if ($type === 'string') {
                $value = ($property['format'] ?? null) === 'date'
                    ? $this->dateArg($arguments, $key)
                    : $this->stringArg($arguments, $key);

                if (isset($property['enum']) && is_array($property['enum']) && !in_array($value, $property['enum'], true)) {
                    throw new InvalidArgumentException('Valor de parametro de Numa no permitido.');
                }

                continue;
            }

            if ($type === 'integer') {
                $this->boundedLimit(
                    $arguments[$key],
                    isset($property['minimum']) ? (int) $property['minimum'] : 1,
                    isset($property['maximum']) ? (int) $property['maximum'] : PHP_INT_MAX
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function executeResumenFinanciero(int $usuarioId, array $arguments): array
    {
        [$start, $end] = $this->period($arguments);
        $ingresos = $this->sumIngresos($usuarioId, $start, $end);
        $gastosEsenciales = $this->sumGastos($usuarioId, $start, $end, 'esencial');
        $gastosFlexibles = $this->sumGastos($usuarioId, $start, $end, 'flexible');
        $gastos = $gastosEsenciales + $gastosFlexibles;

        return [
            'tool' => NumaFinancialToolRegistry::OBTENER_RESUMEN_FINANCIERO,
            'periodo' => ['inicio' => $start, 'fin' => $end],
            'ingresos' => $this->money($ingresos),
            'gastos' => $this->money($gastos),
            'gastos_esenciales' => $this->money($gastosEsenciales),
            'gastos_flexibles' => $this->money($gastosFlexibles),
            'ahorro_posible' => $this->money($ingresos - $gastosEsenciales),
            'ahorro_real' => $this->money($ingresos - $gastos),
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function executeRankingCategorias(int $usuarioId, array $arguments): array
    {
        [$start, $end] = $this->period($arguments);
        $metric = $this->stringArg($arguments, 'metrica', 'gastos');
        $limit = $this->boundedLimit($arguments['limite'] ?? 5, 1, 10);

        $rows = match ($metric) {
            'ingresos' => $this->categoryTotalsIngresos($usuarioId, $start, $end, $limit),
            'gastos' => $this->categoryTotalsGastos($usuarioId, $start, $end, null, $limit),
            'gastos_esenciales' => $this->categoryTotalsGastos($usuarioId, $start, $end, 'esencial', $limit),
            'gastos_flexibles' => $this->categoryTotalsGastos($usuarioId, $start, $end, 'flexible', $limit),
            default => throw new InvalidArgumentException('Metrica de ranking de Numa no permitida.'),
        };
        $total = array_sum(array_column($rows, 'total'));

        return [
            'tool' => NumaFinancialToolRegistry::OBTENER_RANKING_CATEGORIAS,
            'periodo' => ['inicio' => $start, 'fin' => $end],
            'metrica' => $metric,
            'limite' => $limit,
            'categorias' => array_map(function (array $row) use ($total): array {
                $amount = (float) $row['total'];

                return [
                    'categoria' => (string) $row['categoria'],
                    'label' => formatearCategoria((string) $row['categoria']),
                    'total' => $this->money($amount),
                    'porcentaje' => $total > 0 ? round(($amount / $total) * 100, 2) : null,
                ];
            }, $rows),
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function executeEvolucionFinanciera(int $usuarioId, array $arguments): array
    {
        [$start, $end] = $this->period($arguments);
        $metric = $this->stringArg($arguments, 'metrica', 'ahorro_real');
        $grouping = $this->stringArg($arguments, 'agrupacion');
        $limit = $this->boundedLimit($arguments['limite'] ?? 12, 1, 24);

        $items = match ($grouping) {
            'mes' => $this->monthlyEvolution($usuarioId, $start, $end, $metric, $limit),
            'categoria' => $this->categoryEvolution($usuarioId, $start, $end, $metric, $limit),
            'tipo' => $this->typeEvolution($usuarioId, $start, $end),
            default => throw new InvalidArgumentException('Agrupacion de Numa no permitida.'),
        };

        return [
            'tool' => NumaFinancialToolRegistry::OBTENER_EVOLUCION_FINANCIERA,
            'periodo' => ['inicio' => $start, 'fin' => $end],
            'metrica' => $metric,
            'agrupacion' => $grouping,
            'limite' => $limit,
            'evolucion' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function executeCompararPeriodos(int $usuarioId, array $arguments): array
    {
        $startA = $this->dateArg($arguments, 'fecha_inicio_a');
        $endA = $this->dateArg($arguments, 'fecha_fin_a');
        $startB = $this->dateArg($arguments, 'fecha_inicio_b');
        $endB = $this->dateArg($arguments, 'fecha_fin_b');

        $this->assertPeriodOrder($startA, $endA);
        $this->assertPeriodOrder($startB, $endB);

        $metric = $this->stringArg($arguments, 'metrica');
        $category = isset($arguments['categoria']) ? $this->stringArg($arguments, 'categoria') : null;
        $valueA = $this->calculateMetric($usuarioId, $startA, $endA, $metric, $category);
        $valueB = $this->calculateMetric($usuarioId, $startB, $endB, $metric, $category);
        $difference = $valueB - $valueA;

        return [
            'tool' => NumaFinancialToolRegistry::COMPARAR_PERIODOS,
            'metrica' => $metric,
            'categoria' => $category,
            'periodo_a' => ['inicio' => $startA, 'fin' => $endA],
            'periodo_b' => ['inicio' => $startB, 'fin' => $endB],
            'valor_a' => $this->money($valueA),
            'valor_b' => $this->money($valueB),
            'diferencia_absoluta' => $this->money($difference),
            'diferencia_porcentual' => $valueA != 0.0 ? round(($difference / abs($valueA)) * 100, 2) : null,
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function executeEstadisticasMovimientos(int $usuarioId, array $arguments): array
    {
        [$start, $end] = $this->period($arguments);
        $metric = $this->stringArg($arguments, 'metrica');
        $category = isset($arguments['categoria']) ? $this->stringArg($arguments, 'categoria') : null;
        $stats = $this->movementStats($usuarioId, $start, $end, $metric, $category);

        return [
            'tool' => NumaFinancialToolRegistry::OBTENER_ESTADISTICAS_MOVIMIENTOS,
            'periodo' => ['inicio' => $start, 'fin' => $end],
            'metrica' => $metric,
            'categoria' => $category,
            'promedio' => $this->money($stats['average']),
            'maximo' => $this->money($stats['max']),
            'minimo' => $this->money($stats['min']),
            'total' => $this->money($stats['total']),
            'cantidad_movimientos' => $stats['count'],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{0:string, 1:string}
     */
    private function period(array $arguments): array
    {
        $start = $this->dateArg($arguments, 'fecha_inicio');
        $end = $this->dateArg($arguments, 'fecha_fin');
        $this->assertPeriodOrder($start, $end);

        return [$start, $end];
    }

    /** @param array<string, mixed> $arguments */
    private function dateArg(array $arguments, string $key): string
    {
        $value = $arguments[$key] ?? null;

        if (!is_string($value)) {
            throw new InvalidArgumentException('Fecha de Numa no valida.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Fecha de Numa no valida.');
        }

        return $value;
    }

    private function assertPeriodOrder(string $start, string $end): void
    {
        if ($start > $end) {
            throw new InvalidArgumentException('Periodo de Numa no valido.');
        }
    }

    /** @param array<string, mixed> $arguments */
    private function stringArg(array $arguments, string $key, ?string $default = null): string
    {
        $value = $arguments[$key] ?? $default;

        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('Parametro de Numa no valido.');
        }

        return trim($value);
    }

    private function boundedLimit(mixed $value, int $min, int $max): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new InvalidArgumentException('Limite de Numa no valido.');
        }

        $limit = (int) $value;

        if ($limit < $min || $limit > $max) {
            throw new InvalidArgumentException('Limite de Numa no valido.');
        }

        return $limit;
    }

    private function calculateMetric(int $usuarioId, string $start, string $end, string $metric, ?string $category = null): float
    {
        return match ($metric) {
            'ingresos' => $this->sumIngresos($usuarioId, $start, $end, $category),
            'gastos' => $this->sumGastos($usuarioId, $start, $end, null, $category),
            'gastos_esenciales' => $this->sumGastos($usuarioId, $start, $end, 'esencial', $category),
            'gastos_flexibles' => $this->sumGastos($usuarioId, $start, $end, 'flexible', $category),
            'ahorro_posible' => $this->sumIngresos($usuarioId, $start, $end) - $this->sumGastos($usuarioId, $start, $end, 'esencial'),
            'ahorro_real' => $this->sumIngresos($usuarioId, $start, $end) - $this->sumGastos($usuarioId, $start, $end),
            default => throw new InvalidArgumentException('Metrica financiera de Numa no permitida.'),
        };
    }

    private function sumIngresos(int $usuarioId, string $start, string $end, ?string $category = null): float
    {
        $sql = 'SELECT SUM(cantidad) AS total FROM ingresos WHERE usuario_id = :usuario_id AND DATE(fecha) BETWEEN :inicio AND :fin';
        $params = [':usuario_id' => $usuarioId, ':inicio' => $start, ':fin' => $end];

        if ($category !== null) {
            $sql .= ' AND categoria = :categoria';
            $params[':categoria'] = $category;
        }

        return $this->sum($sql, $params);
    }

    private function sumGastos(int $usuarioId, string $start, string $end, ?string $type = null, ?string $category = null): float
    {
        $sql = 'SELECT SUM(cantidad) AS total FROM gastos WHERE usuario_id = :usuario_id AND DATE(fecha) BETWEEN :inicio AND :fin';
        $params = [':usuario_id' => $usuarioId, ':inicio' => $start, ':fin' => $end];

        if ($type !== null) {
            $sql .= ' AND tipo = :tipo';
            $params[':tipo'] = $type;
        }

        if ($category !== null) {
            $sql .= ' AND categoria = :categoria';
            $params[':categoria'] = $category;
        }

        return $this->sum($sql, $params);
    }

    /**
     * @param array<string, int|string> $params
     */
    private function sum(string $sql, array $params): float
    {
        $stmt = $this->db()->prepare($sql);
        $this->bindAndExecute($stmt, $params);
        $value = $stmt->fetchColumn();

        return $value === false || $value === null ? 0.0 : (float) $value;
    }

    /**
     * @return array<int, array{categoria:string, total:float}>
     */
    private function categoryTotalsIngresos(int $usuarioId, string $start, string $end, int $limit): array
    {
        $sql = "SELECT categoria, SUM(cantidad) AS total
                FROM ingresos
                WHERE usuario_id = :usuario_id AND DATE(fecha) BETWEEN :inicio AND :fin
                GROUP BY categoria
                HAVING total > 0
                ORDER BY total DESC, categoria ASC
                LIMIT " . $limit;
        $stmt = $this->db()->prepare($sql);
        $this->bindAndExecute($stmt, [':usuario_id' => $usuarioId, ':inicio' => $start, ':fin' => $end]);

        return $this->fetchCategoryTotals($stmt);
    }

    /**
     * @return array<int, array{categoria:string, total:float}>
     */
    private function categoryTotalsGastos(int $usuarioId, string $start, string $end, ?string $type, int $limit): array
    {
        $sql = "SELECT categoria, SUM(cantidad) AS total
                FROM gastos
                WHERE usuario_id = :usuario_id AND DATE(fecha) BETWEEN :inicio AND :fin";
        $params = [':usuario_id' => $usuarioId, ':inicio' => $start, ':fin' => $end];

        if ($type !== null) {
            $sql .= ' AND tipo = :tipo';
            $params[':tipo'] = $type;
        }

        $sql .= " GROUP BY categoria
                  HAVING total > 0
                  ORDER BY total DESC, categoria ASC
                  LIMIT " . $limit;
        $stmt = $this->db()->prepare($sql);
        $this->bindAndExecute($stmt, $params);

        return $this->fetchCategoryTotals($stmt);
    }

    /**
     * @return array<int, array{categoria:string, total:float}>
     */
    private function fetchCategoryTotals(PDOStatement $stmt): array
    {
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'categoria' => (string) $row['categoria'],
            'total' => (float) $row['total'],
        ], $rows ?: []);
    }

    /**
     * @return array<int, array<string, float|string>>
     */
    private function monthlyEvolution(int $usuarioId, string $start, string $end, string $metric, int $limit): array
    {
        $items = [];
        $cursor = new DateTimeImmutable(substr($start, 0, 7) . '-01');
        $last = new DateTimeImmutable(substr($end, 0, 7) . '-01');

        while ($cursor <= $last && count($items) < $limit) {
            $monthStart = max($start, $cursor->format('Y-m-01'));
            $monthEnd = min($end, $cursor->format('Y-m-t'));
            $items[] = [
                'mes' => $cursor->format('Y-m'),
                'valor' => $this->money($this->calculateMetric($usuarioId, $monthStart, $monthEnd, $metric)),
            ];
            $cursor = $cursor->modify('+1 month');
        }

        return $items;
    }

    /**
     * @return array<int, array<string, float|string|null>>
     */
    private function categoryEvolution(int $usuarioId, string $start, string $end, string $metric, int $limit): array
    {
        if ($metric === 'ingresos') {
            $rows = $this->categoryTotalsIngresos($usuarioId, $start, $end, $limit);
        } elseif (in_array($metric, ['gastos', 'gastos_esenciales', 'gastos_flexibles'], true)) {
            $type = $metric === 'gastos_esenciales' ? 'esencial' : ($metric === 'gastos_flexibles' ? 'flexible' : null);
            $rows = $this->categoryTotalsGastos($usuarioId, $start, $end, $type, $limit);
        } else {
            throw new InvalidArgumentException('La evolucion por categoria requiere una metrica de movimientos.');
        }

        return array_map(function (array $row): array {
            return [
                'categoria' => $row['categoria'],
                'label' => formatearCategoria($row['categoria']),
                'valor' => $this->money($row['total']),
            ];
        }, $rows);
    }

    /**
     * @return array<int, array{tipo:string, valor:float}>
     */
    private function typeEvolution(int $usuarioId, string $start, string $end): array
    {
        return [
            ['tipo' => 'esencial', 'valor' => $this->money($this->sumGastos($usuarioId, $start, $end, 'esencial'))],
            ['tipo' => 'flexible', 'valor' => $this->money($this->sumGastos($usuarioId, $start, $end, 'flexible'))],
        ];
    }

    /**
     * @return array{average:float, max:float, min:float, total:float, count:int}
     */
    private function movementStats(int $usuarioId, string $start, string $end, string $metric, ?string $category): array
    {
        [$table, $type] = match ($metric) {
            'ingresos' => ['ingresos', null],
            'gastos' => ['gastos', null],
            'gastos_esenciales' => ['gastos', 'esencial'],
            'gastos_flexibles' => ['gastos', 'flexible'],
            default => throw new InvalidArgumentException('Metrica de movimientos de Numa no permitida.'),
        };

        $sql = "SELECT AVG(cantidad) AS promedio, MAX(cantidad) AS maximo, MIN(cantidad) AS minimo,
                       SUM(cantidad) AS total, COUNT(*) AS cantidad
                FROM {$table}
                WHERE usuario_id = :usuario_id AND DATE(fecha) BETWEEN :inicio AND :fin";
        $params = [':usuario_id' => $usuarioId, ':inicio' => $start, ':fin' => $end];

        if ($type !== null) {
            $sql .= ' AND tipo = :tipo';
            $params[':tipo'] = $type;
        }

        if ($category !== null) {
            $sql .= ' AND categoria = :categoria';
            $params[':categoria'] = $category;
        }

        $stmt = $this->db()->prepare($sql);
        $this->bindAndExecute($stmt, $params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'average' => (float) ($row['promedio'] ?? 0),
            'max' => (float) ($row['maximo'] ?? 0),
            'min' => (float) ($row['minimo'] ?? 0),
            'total' => (float) ($row['total'] ?? 0),
            'count' => (int) ($row['cantidad'] ?? 0),
        ];
    }

    /**
     * @param array<string, int|string> $params
     */
    private function bindAndExecute(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->execute();
    }

    private function money(float $value): float
    {
        return round($value, 2);
    }

    private function db(): PDO
    {
        return $this->connection ?? Database::getConnection();
    }
}
