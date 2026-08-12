<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../helpers/utils.php';

final class NumaPeriodResolver
{
    public const CURRENT_MONTH = 'mes_actual';
    public const PREVIOUS_MONTH = 'mes_anterior';
    public const CURRENT_YEAR = 'anio_actual';
    public const PREVIOUS_YEAR = 'anio_anterior';

    /** @var array<int, string> */
    private const RELATIVE_PERIODS = [
        self::CURRENT_MONTH,
        self::PREVIOUS_MONTH,
        self::CURRENT_YEAR,
        self::PREVIOUS_YEAR,
    ];

    /** @var array<string, int> */
    private const NAMED_MONTHS = [
        'enero' => 1,
        'febrero' => 2,
        'marzo' => 3,
        'abril' => 4,
        'mayo' => 5,
        'junio' => 6,
        'julio' => 7,
        'agosto' => 8,
        'septiembre' => 9,
        'octubre' => 10,
        'noviembre' => 11,
        'diciembre' => 12,
    ];

    private readonly DateTimeZone $timezone;

    public function __construct(private readonly ?DateTimeImmutable $now = null)
    {
        $this->timezone = new DateTimeZone('Europe/Madrid');
    }

    /** @return array<int, string> */
    public static function relativePeriods(): array
    {
        return [...self::RELATIVE_PERIODS, ...array_keys(self::NAMED_MONTHS)];
    }

    public function currentDate(): string
    {
        return $this->now()->format('Y-m-d');
    }

    /** @return array{inicio:string,fin:string} */
    public function resolve(string $period): array
    {
        $now = $this->now();

        return match ($period) {
            self::CURRENT_MONTH => $this->month($now),
            self::PREVIOUS_MONTH => $this->month($now->modify('first day of last month')),
            self::CURRENT_YEAR => $this->year($now),
            self::PREVIOUS_YEAR => $this->year($now->modify('first day of January last year')),
            default => isset(self::NAMED_MONTHS[$period])
                ? $this->month($now->setDate((int) $now->format('Y'), self::NAMED_MONTHS[$period], 1))
                : throw new InvalidArgumentException('Periodo relativo de Numa no permitido.'),
        };
    }

    /**
     * @param array{start:string,end:string}|null $referencePeriod
     * @return array{inicio:string,fin:string}
     */
    public function resolveForFollowUp(string $period, ?array $referencePeriod = null): array
    {
        if ($referencePeriod === null || !in_array($period, [self::PREVIOUS_MONTH, self::PREVIOUS_YEAR], true)) {
            return $this->resolve($period);
        }

        $referenceStart = $this->date($referencePeriod['start']);

        return $period === self::PREVIOUS_MONTH
            ? $this->month($referenceStart->modify('first day of last month'))
            : $this->year($referenceStart->modify('first day of January last year'));
    }

    /** @return array{inicio:string,fin:string} */
    public function normalize(string $start, string $end): array
    {
        $startDate = $this->date($start);
        $endDate = $this->date($end);

        if ($startDate > $endDate) {
            throw new InvalidArgumentException('Periodo de Numa no valido.');
        }

        return [
            'inicio' => $startDate->modify('first day of this month')->format('Y-m-d'),
            'fin' => $endDate->modify('last day of this month')->format('Y-m-d'),
        ];
    }

    private function now(): DateTimeImmutable
    {
        return ($this->now ?? new DateTimeImmutable('now', $this->timezone))->setTimezone($this->timezone);
    }

    /** @return array{inicio:string,fin:string} */
    private function month(DateTimeImmutable $date): array
    {
        return [
            'inicio' => $date->modify('first day of this month')->format('Y-m-d'),
            'fin' => $date->modify('last day of this month')->format('Y-m-d'),
        ];
    }

    /** @return array{inicio:string,fin:string} */
    private function year(DateTimeImmutable $date): array
    {
        return [
            'inicio' => $date->setDate((int) $date->format('Y'), 1, 1)->format('Y-m-d'),
            'fin' => $date->setDate((int) $date->format('Y'), 12, 31)->format('Y-m-d'),
        ];
    }

    private function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $this->timezone);

        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Fecha de Numa no valida.');
        }

        return $date;
    }
}

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

final class NumaFinancialToolLimitExceeded extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No hemos podido procesar la consulta.');
    }
}

interface NumaFinancialToolRegistryInterface
{
    /** @return array<int, string> */
    public function names(): array;

    public function get(string $name): NumaFinancialToolDefinition;

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function execute(string $name, int $authenticatedUserId, array $arguments): array;
}

final class NumaFinancialToolRegistry implements NumaFinancialToolRegistryInterface
{
    private const MAX_TOOL_CALLS = 2;
    private const MAX_AGGREGATE_RESULT_JSON_CHARS = 1600;

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

    private readonly int $maxToolCalls;

    private readonly int $maxAggregateResultJsonChars;

    private int $executedToolCalls = 0;

    /** @var array<int, array<string, mixed>> */
    private array $executedToolResults = [];

    public function __construct(
        private readonly NumaFinancialToolExecutor $executor = new NumaFinancialToolExecutor(),
        ?int $maxToolCalls = null,
        ?int $maxAggregateResultJsonChars = null,
    ) {
        $this->maxToolCalls = $maxToolCalls ?? bh_env_int('NUMA_MAX_TOOL_CALLS', self::MAX_TOOL_CALLS);
        $this->maxAggregateResultJsonChars = $maxAggregateResultJsonChars
            ?? bh_env_int('NUMA_MAX_TOOL_RESULT_CHARS', self::MAX_AGGREGATE_RESULT_JSON_CHARS);

        if ($this->maxToolCalls <= 0) {
            throw new InvalidArgumentException('El limite de llamadas a tools de Numa no es valido.');
        }

        if ($this->maxAggregateResultJsonChars <= 0) {
            throw new InvalidArgumentException('El limite agregado de resultado de tools de Numa no es valido.');
        }

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
    public function execute(string $name, int $authenticatedUserId, array $arguments): array
    {
        if ($this->executedToolCalls >= $this->maxToolCalls) {
            throw new NumaFinancialToolLimitExceeded();
        }

        $definition = $this->get($name);
        $result = $this->executor->execute($definition, $authenticatedUserId, $arguments);
        $result = $this->limitAggregateResult($result);

        $this->executedToolResults[] = $result;
        $this->executedToolCalls++;

        return $result;
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function executeForAuthenticatedSession(string $name, array $arguments): array
    {
        $usuarioId = $_SESSION['usuario_id'] ?? null;

        if (!is_int($usuarioId) && !(is_string($usuarioId) && ctype_digit($usuarioId))) {
            throw new InvalidArgumentException('Usuario de Numa no valido.');
        }

        return $this->execute($name, (int) $usuarioId, $arguments);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function limitAggregateResult(array $result): array
    {
        if ($this->aggregateJsonLength([...$this->executedToolResults, $result]) <= $this->maxAggregateResultJsonChars) {
            return $result;
        }

        foreach (['categorias', 'evolucion'] as $itemsKey) {
            if (!isset($result[$itemsKey]) || !is_array($result[$itemsKey]) || !array_is_list($result[$itemsKey])) {
                continue;
            }

            while ($result[$itemsKey] !== []
                && $this->aggregateJsonLength([...$this->executedToolResults, $result]) > $this->maxAggregateResultJsonChars
            ) {
                array_pop($result[$itemsKey]);
            }

            if (isset($result['limite']) && is_int($result['limite'])) {
                $result['limite'] = min($result['limite'], count($result[$itemsKey]));
            }

            if ($this->aggregateJsonLength([...$this->executedToolResults, $result]) <= $this->maxAggregateResultJsonChars) {
                return $result;
            }
        }

        throw new NumaFinancialToolLimitExceeded();
    }

    /** @param array<int, array<string, mixed>> $results */
    private function aggregateJsonLength(array $results): int
    {
        return strlen(json_encode($results, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
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
                [],
                [],
                ['max_items' => 1],
                'executeResumenFinanciero'
            ),
            self::OBTENER_RANKING_CATEGORIAS => new NumaFinancialToolDefinition(
                self::OBTENER_RANKING_CATEGORIAS,
                'Devuelve un ranking agregado por categoria para una metrica financiera permitida.',
                self::periodSchema([
                    'metrica' => ['type' => 'string', 'enum' => ['ingresos', 'gastos', 'gastos_esenciales', 'gastos_flexibles']],
                    'limite' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                ]),
                [],
                [
                    'metrica' => ['ingresos', 'gastos', 'gastos_esenciales', 'gastos_flexibles'],
                ],
                ['max_items' => 10],
                'executeRankingCategorias'
            ),
            self::OBTENER_EVOLUCION_FINANCIERA => new NumaFinancialToolDefinition(
                self::OBTENER_EVOLUCION_FINANCIERA,
                'Devuelve una evolucion agregada por mes, categoria o tipo permitido.',
                self::periodSchema([
                    'metrica' => ['type' => 'string', 'enum' => self::FINANCIAL_METRICS],
                    'agrupacion' => ['type' => 'string', 'enum' => self::GROUPINGS],
                    'limite' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 24],
                ]),
                ['agrupacion'],
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
                    'periodo_a' => ['type' => 'string', 'enum' => NumaPeriodResolver::relativePeriods()],
                    'fecha_inicio_b' => ['type' => 'string', 'format' => 'date'],
                    'fecha_fin_b' => ['type' => 'string', 'format' => 'date'],
                    'periodo_b' => ['type' => 'string', 'enum' => NumaPeriodResolver::relativePeriods()],
                    'metrica' => ['type' => 'string', 'enum' => self::FINANCIAL_METRICS],
                    'categoria' => ['type' => 'string', 'enum' => $categories],
                ]),
                ['metrica'],
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
                self::periodSchema([
                    'metrica' => ['type' => 'string', 'enum' => self::MOVEMENT_METRICS],
                    'categoria' => ['type' => 'string', 'enum' => $categories],
                ]),
                ['metrica'],
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
        return self::periodSchema();
    }

    /**
     * @param array<string, mixed> $properties
     * @return array<string, mixed>
     */
    private static function periodSchema(array $properties = []): array
    {
        return self::schema([
            'fecha_inicio' => ['type' => 'string', 'format' => 'date'],
            'fecha_fin' => ['type' => 'string', 'format' => 'date'],
            'periodo' => ['type' => 'string', 'enum' => NumaPeriodResolver::relativePeriods()],
            ...$properties,
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
    private const MAX_TOOL_RANGE_DAYS = 731;

    private readonly int $maxToolRangeDays;

    public function __construct(
        private readonly ?PDO $connection = null,
        ?int $maxToolRangeDays = null,
        private readonly NumaPeriodResolver $periodResolver = new NumaPeriodResolver(),
    ) {
        $this->maxToolRangeDays = $maxToolRangeDays
            ?? bh_env_int('NUMA_MAX_TOOL_RANGE_DAYS', self::MAX_TOOL_RANGE_DAYS);

        if ($this->maxToolRangeDays <= 0) {
            throw new InvalidArgumentException('El limite de rango de fechas de tools de Numa no es valido.');
        }
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function execute(NumaFinancialToolDefinition $definition, int $authenticatedUserId, array $arguments): array
    {
        if ($authenticatedUserId <= 0) {
            throw new InvalidArgumentException('Usuario de Numa no valido.');
        }

        $this->validateArguments($definition, $arguments);

        $result = match ($definition->implementation()) {
            'executeResumenFinanciero' => $this->executeResumenFinanciero($authenticatedUserId, $arguments),
            'executeRankingCategorias' => $this->executeRankingCategorias($authenticatedUserId, $arguments),
            'executeEvolucionFinanciera' => $this->executeEvolucionFinanciera($authenticatedUserId, $arguments),
            'executeCompararPeriodos' => $this->executeCompararPeriodos($authenticatedUserId, $arguments),
            'executeEstadisticasMovimientos' => $this->executeEstadisticasMovimientos($authenticatedUserId, $arguments),
            default => throw new InvalidArgumentException('Implementacion de tool financiera de Numa no registrada.'),
        };

        return $result;
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

        $this->assertPeriodArguments($definition->implementation(), $arguments);
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
        $total = $this->calculateMetric($usuarioId, $start, $end, $metric);

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
        $grouping = $this->stringArg($arguments, 'agrupacion');
        $metric = $this->stringArg($arguments, 'metrica', $grouping === 'tipo' ? 'gastos' : 'ahorro_real');
        $limit = $this->boundedLimit($arguments['limite'] ?? 12, 1, 24);

        $items = match ($grouping) {
            'mes' => $this->monthlyEvolution($usuarioId, $start, $end, $metric, $limit),
            'categoria' => $this->categoryEvolution($usuarioId, $start, $end, $metric, $limit),
            'tipo' => $this->typeEvolution($usuarioId, $start, $end, $metric),
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
        [$startA, $endA] = $this->comparisonPeriod($arguments, 'a');
        [$startB, $endB] = $this->comparisonPeriod($arguments, 'b');

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
        if (isset($arguments['periodo'])) {
            $period = $this->periodResolver->resolve($this->stringArg($arguments, 'periodo'));

            return [$period['inicio'], $period['fin']];
        }

        $start = $this->dateArg($arguments, 'fecha_inicio');
        $end = $this->dateArg($arguments, 'fecha_fin');
        $period = $this->periodResolver->normalize($start, $end);
        $this->assertPeriodOrder($period['inicio'], $period['fin']);

        return [$period['inicio'], $period['fin']];
    }

    /** @param array<string, mixed> $arguments */
    private function comparisonPeriod(array $arguments, string $suffix): array
    {
        $relativeKey = 'periodo_' . $suffix;
        if (isset($arguments[$relativeKey])) {
            $period = $this->periodResolver->resolve($this->stringArg($arguments, $relativeKey));

            return [$period['inicio'], $period['fin']];
        }

        $period = $this->periodResolver->normalize(
            $this->dateArg($arguments, 'fecha_inicio_' . $suffix),
            $this->dateArg($arguments, 'fecha_fin_' . $suffix),
        );
        $this->assertPeriodOrder($period['inicio'], $period['fin']);

        return [$period['inicio'], $period['fin']];
    }

    /** @param array<string, mixed> $arguments */
    private function assertPeriodArguments(string $implementation, array $arguments): void
    {
        if ($implementation === 'executeCompararPeriodos') {
            foreach (['a', 'b'] as $suffix) {
                $hasRelative = isset($arguments['periodo_' . $suffix]);
                $hasExplicit = isset($arguments['fecha_inicio_' . $suffix], $arguments['fecha_fin_' . $suffix]);
                if ($hasRelative === $hasExplicit) {
                    throw new InvalidArgumentException('Periodo de Numa incompleto.');
                }
            }

            return;
        }

        $hasRelative = isset($arguments['periodo']);
        $hasExplicit = isset($arguments['fecha_inicio'], $arguments['fecha_fin']);
        if ($hasRelative === $hasExplicit) {
            throw new InvalidArgumentException('Periodo de Numa incompleto.');
        }
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

        $startDate = new DateTimeImmutable($start);
        $endDate = new DateTimeImmutable($end);

        if ($startDate->diff($endDate)->days > $this->maxToolRangeDays) {
            throw new InvalidArgumentException('Intervalo de fechas de Numa excesivo.');
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
                LIMIT :limite";
        $stmt = $this->db()->prepare($sql);
        $this->bindAndExecute($stmt, [
            ':usuario_id' => $usuarioId,
            ':inicio' => $start,
            ':fin' => $end,
            ':limite' => $limit,
        ]);

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
                  LIMIT :limite";
        $params[':limite'] = $limit;
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
    private function typeEvolution(int $usuarioId, string $start, string $end, string $metric): array
    {
        return match ($metric) {
            'ingresos' => [
                ['tipo' => 'ingresos', 'valor' => $this->money($this->sumIngresos($usuarioId, $start, $end))],
            ],
            'gastos' => [
                ['tipo' => 'esencial', 'valor' => $this->money($this->sumGastos($usuarioId, $start, $end, 'esencial'))],
                ['tipo' => 'flexible', 'valor' => $this->money($this->sumGastos($usuarioId, $start, $end, 'flexible'))],
            ],
            'gastos_esenciales' => [
                ['tipo' => 'esencial', 'valor' => $this->money($this->sumGastos($usuarioId, $start, $end, 'esencial'))],
            ],
            'gastos_flexibles' => [
                ['tipo' => 'flexible', 'valor' => $this->money($this->sumGastos($usuarioId, $start, $end, 'flexible'))],
            ],
            default => throw new InvalidArgumentException('La evolucion por tipo requiere una metrica de movimientos.'),
        };
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
