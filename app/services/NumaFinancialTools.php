<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../helpers/utils.php';
require_once __DIR__ . '/NumaFinancialDataToolContract.php';

final class NumaPeriodResolver
{
    private readonly DateTimeZone $timezone;

    public function __construct(private readonly ?DateTimeImmutable $now = null)
    {
        $this->timezone = new DateTimeZone('Europe/Madrid');
    }

    public function currentDate(): string
    {
        return $this->now()->format('Y-m-d');
    }

    /** @return array{inicio:string,fin:string} */
    public function referencePeriodForMonth(string $month): array
    {
        if (preg_match('/^20\d{2}-(?:0[1-9]|1[0-2])$/', $month) !== 1) {
            throw new InvalidArgumentException('Mes de referencia de Numa no valido.');
        }

        return $this->month($this->date($month . '-01'));
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
     * @param array<int, array<int, array<int, string>>> $requirementGroups
     * @param array<int, array<string, mixed>> $compatibilityRules
     * @param array<string, int> $resultLimit
     */
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly string $whenToUse,
        private readonly string $whenNotToUse,
        private readonly array $parameterSchema,
        private readonly array $requiredParameters,
        private readonly array $requirementGroups,
        private readonly array $compatibilityRules,
        private readonly array $resultLimit,
        private readonly string $implementation,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('La tool de Numa debe tener nombre.');
        }

        if (trim($description) === '') {
            throw new InvalidArgumentException('La tool de Numa debe tener descripcion.');
        }

        if (trim($whenToUse) === '' || trim($whenNotToUse) === '') {
            throw new InvalidArgumentException('La tool de Numa debe definir cuando usarla y cuando no usarla.');
        }

        if (trim($implementation) === '') {
            throw new InvalidArgumentException('La tool de Numa debe tener implementacion concreta.');
        }

        $properties = $parameterSchema['properties'] ?? null;
        if (($parameterSchema['type'] ?? null) !== 'object'
            || ($parameterSchema['additionalProperties'] ?? null) !== false
            || !is_array($properties)
        ) {
            throw new InvalidArgumentException('La tool de Numa debe tener un esquema de parametros cerrado.');
        }

        foreach ($properties as $parameter => $schema) {
            if (!is_string($parameter)
                || !is_array($schema)
                || !is_string($schema['type'] ?? null)
                || trim((string) ($schema['description'] ?? '')) === ''
            ) {
                throw new InvalidArgumentException('Los parametros de la tool de Numa deben tener tipo y descripcion.');
            }
        }

        foreach ($this->requiredParameterSets() as $requiredSet) {
            if (array_diff($requiredSet, array_keys($properties)) !== []) {
                throw new InvalidArgumentException('La tool de Numa declara un parametro obligatorio desconocido.');
            }
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

    public function whenToUse(): string
    {
        return $this->whenToUse;
    }

    public function whenNotToUse(): string
    {
        return $this->whenNotToUse;
    }

    /**
     * @return array<string, mixed>
     */
    public function parameterSchema(): array
    {
        $schema = $this->parameterSchema;
        $requiredSets = $this->requiredParameterSets();

        if (count($requiredSets) === 1) {
            $schema['required'] = $requiredSets[0];
        } else {
            $schema['anyOf'] = $this->parameterVariants($requiredSets);
        }

        return $schema;
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
        $allowedValues = [];

        foreach ($this->parameterSchema['properties'] as $name => $schema) {
            if (is_string($name) && is_array($schema) && isset($schema['enum']) && is_array($schema['enum'])) {
                $allowedValues[$name] = array_values(array_filter($schema['enum'], 'is_string'));
            }
        }

        return $allowedValues;
    }

    /** @return array<int, array<int, array<int, string>>> */
    public function requirementGroups(): array
    {
        return $this->requirementGroups;
    }

    /** @return array<int, array<string, mixed>> */
    public function compatibilityRules(): array
    {
        return $this->compatibilityRules;
    }

    /** @return array<int, array<int, string>> */
    public function requiredParameterSets(): array
    {
        $sets = [$this->requiredParameters];

        foreach ($this->requirementGroups as $alternatives) {
            $expanded = [];
            foreach ($sets as $set) {
                foreach ($alternatives as $alternative) {
                    $expanded[] = array_values(array_unique([...$set, ...$alternative]));
                }
            }

            $sets = $expanded;
        }

        return $sets;
    }

    /**
     * @param array<int, array<int, string>> $requiredSets
     * @return array<int, array<string, mixed>>
     */
    private function parameterVariants(array $requiredSets): array
    {
        $alternativeParameters = [];
        foreach ($this->requirementGroups as $alternatives) {
            foreach ($alternatives as $alternative) {
                $alternativeParameters = [...$alternativeParameters, ...$alternative];
            }
        }

        $commonProperties = array_diff_key(
            $this->parameterSchema['properties'],
            array_flip(array_unique($alternativeParameters)),
        );

        return array_map(function (array $required) use ($commonProperties): array {
            $selectedAlternativeProperties = array_intersect_key(
                $this->parameterSchema['properties'],
                array_flip($required),
            );

            return [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [...$commonProperties, ...$selectedAlternativeProperties],
                'required' => $required,
            ];
        }, $requiredSets);
    }

    /** @return array{name:string,description:string,parameters:array<string,mixed>} */
    public function functionDeclaration(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description
                . ' Usala cuando ' . $this->whenToUse
                . ' No la uses cuando ' . $this->whenNotToUse,
            'parameters' => $this->parameterSchema(),
        ];
    }

    /** @return array<string, mixed> */
    public function externalContract(): array
    {
        return [
            ...$this->functionDeclaration(),
            'result_limit' => $this->resultLimit,
            'compatibility_rules' => $this->compatibilityRules,
        ];
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

final class NumaFinancialCategoryCatalog
{
    /** @var array<string, array{kind:string,expense_type:?string,group:string,label:string}> */
    private array $categories = [];

    /** @var array<string, array{kind:string,expense_type:?string,label:string}> */
    private array $groups = [];

    public function __construct()
    {
        foreach (gastoCategorias() as $expenseType => $expenseGroups) {
            foreach ($expenseGroups as $groupName => $group) {
                $this->addGroup($groupName, 'gasto', $expenseType, (string) $group['label']);

                foreach ($group['items'] as $category => $label) {
                    $this->addCategory($category, 'gasto', $expenseType, $groupName, $label);
                }
            }
        }

        foreach (ingresoCategorias() as $groupName => $group) {
            $this->addGroup($groupName, 'ingreso', null, (string) $group['label']);

            foreach (($group['conceptos'] ?? []) as $category => $label) {
                $this->addCategory($category, 'ingreso', null, $groupName, $label);
            }
        }

    }

    /** @return array<int, string> */
    public function categoryValues(): array
    {
        return array_keys($this->categories);
    }

    /** @return array<int, string> */
    public function groupValues(): array
    {
        return array_keys($this->groups);
    }

    /** @return array<int, string> */
    public function incomeAreaValues(): array
    {
        return array_keys(array_filter(
            $this->groups,
            static fn (array $group): bool => $group['kind'] === 'ingreso'
        ));
    }

    /** @return array<int, string> */
    public function expenseAreaValues(): array
    {
        return array_keys(array_filter(
            $this->groups,
            static fn (array $group): bool => $group['kind'] === 'gasto'
        ));
    }

    /** @return array<int, string> */
    public function expenseTypeValues(): array
    {
        return array_keys(gastoCategorias());
    }

    /**
     * @return array<string, array{label:string,categories:array<string,string>}>
     */
    public function incomeAreas(): array
    {
        return $this->areasForKind('ingreso');
    }

    /**
     * @return array<string, array<string, array{label:string,categories:array<string,string>}>>
     */
    public function expenseTypes(): array
    {
        $types = [];
        foreach ($this->expenseTypeValues() as $expenseType) {
            $areas = [];
            foreach ($this->expenseAreaValues() as $area) {
                $group = $this->group($area);
                if ($group['expense_type'] !== $expenseType) {
                    continue;
                }

                $areas[$area] = [
                    'label' => $group['label'],
                    'categories' => $this->categoryLabelsForGroup($area),
                ];
            }
            $types[$expenseType] = $areas;
        }

        return $types;
    }

    /** @return array{kind:string,expense_type:?string,group:string,label:string} */
    public function category(string $category): array
    {
        if (!isset($this->categories[$category])) {
            throw new InvalidArgumentException('Categoria de Numa no permitida.');
        }

        return $this->categories[$category];
    }

    /** @return array{kind:string,expense_type:?string,label:string} */
    public function group(string $group): array
    {
        if (!isset($this->groups[$group])) {
            throw new InvalidArgumentException('Grupo de movimientos de Numa no permitido.');
        }

        return $this->groups[$group];
    }

    /** @return array<int, string> */
    public function categoriesForGroup(string $group): array
    {
        return array_keys(array_filter(
            $this->categories,
            static fn (array $category): bool => $category['group'] === $group
        ));
    }

    /**
     * @return array<string, array{label:string,categories:array<string,string>}>
     */
    private function areasForKind(string $kind): array
    {
        $areas = [];
        foreach ($this->groups as $area => $group) {
            if ($group['kind'] !== $kind) {
                continue;
            }

            $areas[$area] = [
                'label' => $group['label'],
                'categories' => $this->categoryLabelsForGroup($area),
            ];
        }

        return $areas;
    }

    /** @return array<string, string> */
    private function categoryLabelsForGroup(string $group): array
    {
        $categories = [];
        foreach ($this->categoriesForGroup($group) as $category) {
            $categories[$category] = $this->category($category)['label'];
        }

        return $categories;
    }

    private function addGroup(string $name, string $kind, ?string $expenseType, string $label): void
    {
        $this->groups[$name] = ['kind' => $kind, 'expense_type' => $expenseType, 'label' => $label];
    }

    private function addCategory(string $name, string $kind, ?string $expenseType, string $group, string $label): void
    {
        $this->categories[$name] = [
            'kind' => $kind,
            'expense_type' => $expenseType,
            'group' => $group,
            'label' => $label,
        ];
    }
}

final class NumaFinancialToolLimitExceeded extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No hemos podido procesar la consulta.');
    }
}

final class NumaFinancialToolInputIncomplete extends InvalidArgumentException
{
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
    public function validate(string $name, int $authenticatedUserId, array $arguments): array;

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function execute(string $name, int $authenticatedUserId, array $arguments): array;
}

final class NumaFinancialToolRegistry implements NumaFinancialToolRegistryInterface
{
    public const MAX_TOOL_CALLS = 5;
    public const MAX_TOOL_RESULT_BYTES = 262144;
    public const CONSULTAR_DATOS_FINANCIEROS = NumaFinancialDataToolContract::NAME;

    /** @var array<int, string> */
    private const TOOL_NAMES = [
        self::CONSULTAR_DATOS_FINANCIEROS,
    ];

    /** @var array<string, NumaFinancialToolDefinition> */
    private readonly array $definitions;

    private readonly int $maxToolCalls;

    private readonly int $maxToolResultBytes;

    private int $executedToolCalls = 0;

    /** @var array<int, array<string, mixed>> */
    private array $executedToolResults = [];

    public function __construct(
        private readonly NumaFinancialToolExecutor $executor = new NumaFinancialToolExecutor(),
        ?int $maxToolCalls = null,
        ?int $maxToolResultBytes = null,
    ) {
        $this->maxToolCalls = $maxToolCalls ?? bh_env_int('NUMA_MAX_TOOL_CALLS', self::MAX_TOOL_CALLS);
        $this->maxToolResultBytes = $maxToolResultBytes
            ?? bh_env_int('NUMA_MAX_TOOL_RESULT_BYTES', self::MAX_TOOL_RESULT_BYTES);

        if ($this->maxToolCalls <= 0 || $this->maxToolCalls > self::MAX_TOOL_CALLS) {
            throw new InvalidArgumentException('El limite de llamadas a tools de Numa no es valido.');
        }

        if ($this->maxToolResultBytes <= 0
            || $this->maxToolResultBytes > self::MAX_TOOL_RESULT_BYTES
        ) {
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
    public function validate(string $name, int $authenticatedUserId, array $arguments): array
    {
        return $this->executor->validate($this->get($name), $authenticatedUserId, $arguments);
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
        if ($this->aggregateJsonBytes([...$this->executedToolResults, $result]) <= $this->maxToolResultBytes) {
            return $result;
        }

        throw new NumaFinancialToolLimitExceeded();
    }

    /** @param array<int, array<string, mixed>> $results */
    private function aggregateJsonBytes(array $results): int
    {
        return strlen(json_encode($results, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, NumaFinancialToolDefinition>
     */
    private static function buildDefinitions(): array
    {
        $definitions = [
            self::CONSULTAR_DATOS_FINANCIEROS => new NumaFinancialToolDefinition(
                name: self::CONSULTAR_DATOS_FINANCIEROS,
                description: (new NumaFinancialDataToolContract())->functionDeclaration()['description'],
                whenToUse: 'necesites hechos financieros privados del usuario para responder.',
                whenNotToUse: 'la pregunta se responde solo con información pública o del producto.',
                parameterSchema: (new NumaFinancialDataToolContract())->functionDeclaration()['parameters'],
                requiredParameters: ['periodos'],
                requirementGroups: [],
                compatibilityRules: [],
                resultLimit: ['max_items' => 10000],
                implementation: 'executeConsultarDatosFinancieros',
            ),
        ];

        if (array_keys($definitions) !== self::TOOL_NAMES) {
            throw new RuntimeException('El registro de tools financieras de Numa no coincide con el catalogo cerrado.');
        }

        return $definitions;
    }

}

final class NumaFinancialToolExecutor
{
    public const MAX_TOOL_RESULT_ROWS = 10000;

    private readonly int $maxToolResultRows;

    public function __construct(
        private readonly ?PDO $connection = null,
        private readonly NumaFinancialCategoryCatalog $categoryCatalog = new NumaFinancialCategoryCatalog(),
        private readonly NumaFinancialDataToolContract $dataContract = new NumaFinancialDataToolContract(),
        ?int $maxToolResultRows = null,
    ) {
        $this->maxToolResultRows = $maxToolResultRows
            ?? bh_env_int('NUMA_MAX_TOOL_RESULT_ROWS', self::MAX_TOOL_RESULT_ROWS);
        if ($this->maxToolResultRows <= 0 || $this->maxToolResultRows > self::MAX_TOOL_RESULT_ROWS) {
            throw new InvalidArgumentException('El limite de filas de resultado de Numa no es valido.');
        }
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function execute(NumaFinancialToolDefinition $definition, int $authenticatedUserId, array $arguments): array
    {
        if ($definition->implementation() !== 'executeConsultarDatosFinancieros') {
            throw new InvalidArgumentException('Implementacion de tool financiera de Numa no registrada.');
        }

        return $this->executeConsultarDatosFinancieros($authenticatedUserId, $arguments);
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function validate(NumaFinancialToolDefinition $definition, int $authenticatedUserId, array $arguments): array
    {
        if ($authenticatedUserId <= 0) {
            throw new InvalidArgumentException('Usuario de Numa no valido.');
        }

        if ($definition->implementation() !== 'executeConsultarDatosFinancieros') {
            throw new InvalidArgumentException('Implementacion de tool financiera de Numa no registrada.');
        }

        return $this->dataContract->validateArguments($arguments);
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function executeConsultarDatosFinancieros(int $usuarioId, array $arguments): array
    {
        if ($usuarioId <= 0) {
            throw new InvalidArgumentException('Usuario de Numa no valido.');
        }

        $validated = $this->dataContract->validateArguments($arguments);
        $months = $this->canonicalMonths($validated['periodos']);
        $selection = $this->canonicalSelection($validated['selectores']);

        $facts = $this->canonicalFacts($usuarioId, $months, $selection);
        $logicalRows = 0;
        $result = ['tool' => NumaFinancialToolRegistry::CONSULTAR_DATOS_FINANCIEROS, 'meses' => []];
        foreach ($months as $month) {
            $this->countLogicalRows($logicalRows);
            $monthResult = ['mes' => $month];
            if ($selection['ingresos'] !== []) {
                $monthResult['ingresos'] = $this->canonicalIncomeBranch($month, $selection['ingresos'], $facts, $logicalRows);
            }
            if ($selection['gastos'] !== []) {
                $monthResult['gastos'] = $this->canonicalExpenseBranch($month, $selection['gastos'], $facts, $logicalRows);
            }
            $result['meses'][] = $monthResult;
        }

        return $result;
    }

    /**
     * @param list<array<string, int|string>> $periods
     * @return list<string>
     */
    private function canonicalMonths(array $periods): array
    {
        $months = [];
        $seen = [];
        foreach ($periods as $period) {
            foreach ($this->monthsBetween($period['mes_inicio'], $period['mes_fin']) as $month) {
                if (!isset($seen[$month])) {
                    $seen[$month] = true;
                    $months[] = $month;
                }
            }
        }

        return $months;
    }

    /** @return list<string> */
    private function monthsBetween(string $start, string $end): array
    {
        $first = new DateTimeImmutable(substr($start, 0, 7) . '-01', new DateTimeZone('Europe/Madrid'));
        $last = new DateTimeImmutable(substr($end, 0, 7) . '-01', new DateTimeZone('Europe/Madrid'));
        $months = [];
        while ($first <= $last) {
            $months[] = $first->format('Y-m');
            $first = $first->modify('+1 month');
        }

        return $months;
    }

    /**
     * @param list<array<string, string>> $selectors
     * @return array{ingresos:array<string, list<string>|null>,gastos:array<string, array<string, list<string>|null>>}
     */
    private function canonicalSelection(array $selectors): array
    {
        $incomeAreas = $this->categoryCatalog->incomeAreas();
        $expenseTypes = $this->categoryCatalog->expenseTypes();
        $selection = ['ingresos' => [], 'gastos' => []];

        if ($selectors === []) {
            foreach ($incomeAreas as $area => $_details) {
                $selection['ingresos'][$area] = null;
            }
            foreach ($expenseTypes as $type => $areas) {
                foreach ($areas as $area => $_details) {
                    $selection['gastos'][$type][$area] = null;
                }
            }

            return $selection;
        }

        foreach ($selectors as $selector) {
            if ($selector['ambito'] === 'ingresos') {
                if (!isset($selector['area'])) {
                    foreach ($incomeAreas as $area => $_details) {
                        $selection['ingresos'][$area] = null;
                    }
                    continue;
                }
                $this->addCanonicalSelection($selection['ingresos'], $selector['area'], $selector['categoria'] ?? null);
                continue;
            }

            if (!isset($selector['tipo'])) {
                foreach ($expenseTypes as $type => $areas) {
                    foreach ($areas as $area => $_details) {
                        $selection['gastos'][$type][$area] = null;
                    }
                }
                continue;
            }

            $type = $selector['tipo'];
            if (!isset($selector['area'])) {
                foreach ($expenseTypes[$type] as $area => $_details) {
                    $selection['gastos'][$type][$area] = null;
                }
                continue;
            }
            $selection['gastos'][$type] ??= [];
            $this->addCanonicalSelection($selection['gastos'][$type], $selector['area'], $selector['categoria'] ?? null);
        }

        return $selection;
    }

    /** @param array<string, list<string>|null> $areas */
    private function addCanonicalSelection(array &$areas, string $area, ?string $category): void
    {
        if (($areas[$area] ?? false) === null && array_key_exists($area, $areas)) {
            return;
        }
        if ($category === null) {
            $areas[$area] = null;

            return;
        }
        $areas[$area] = array_values(array_unique([...( $areas[$area] ?? []), $category]));
    }

    /**
     * @param list<string> $months
     * @param array{ingresos:array<string, list<string>|null>,gastos:array<string, array<string, list<string>|null>>} $selection
     * @return array<string, array<string, array<string, int>>>
     */
    private function canonicalFacts(int $usuarioId, array $months, array $selection): array
    {
        $selects = [];
        $params = [':usuario_id' => $usuarioId];

        if ($selection['ingresos'] !== []) {
            $incomeMonths = $this->canonicalPlaceholders($months, 'ingreso_mes', $params);
            $incomeCategories = $this->selectedCanonicalCategories($selection['ingresos']);
            $incomePlaceholders = $this->canonicalPlaceholders($incomeCategories, 'ingreso_categoria', $params);
            $selects[] = "SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes, 'ingresos' AS ambito, NULL AS tipo, categoria, cantidad FROM ingresos"
                . ' WHERE usuario_id = :usuario_id AND DATE_FORMAT(fecha, \'%Y-%m\') IN (' . implode(', ', $incomeMonths) . ')'
                . ' AND categoria IN (' . implode(', ', $incomePlaceholders) . ')';
        }
        if ($selection['gastos'] !== []) {
            $expenseMonths = $this->canonicalPlaceholders($months, 'gasto_mes', $params);
            $expenseCategories = $this->selectedCanonicalCategories($selection['gastos']);
            $expensePlaceholders = $this->canonicalPlaceholders($expenseCategories, 'gasto_categoria', $params);
            $selects[] = "SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes, 'gastos' AS ambito, tipo, categoria, cantidad FROM gastos"
                . ' WHERE usuario_id = :usuario_id AND DATE_FORMAT(fecha, \'%Y-%m\') IN (' . implode(', ', $expenseMonths) . ')'
                . ' AND categoria IN (' . implode(', ', $expensePlaceholders) . ')';
        }

        if ($selects === []) {
            return [];
        }

        $stmt = $this->db()->prepare(
            implode(' UNION ALL ', $selects) . ' LIMIT ' . ($this->maxToolResultRows + 1)
        );
        $this->bindAndExecute($stmt, $params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($rows) > $this->maxToolResultRows) {
            throw new NumaFinancialToolLimitExceeded();
        }

        $facts = [];
        foreach ($rows as $row) {
            $month = (string) $row['mes'];
            $scope = (string) $row['ambito'];
            $category = (string) $row['categoria'];
            $type = $row['tipo'] === null ? '' : (string) $row['tipo'];
            // La invariante mensual garantiza una única hoja por categoría.
            $facts[$month][$scope][$type . ':' . $category] = $this->cents($row['cantidad']);
        }

        return $facts;
    }

    /** @param array<string, mixed> $params @return list<string> */
    private function canonicalPlaceholders(array $values, string $prefix, array &$params): array
    {
        $placeholders = [];
        foreach (array_values($values) as $index => $value) {
            $placeholder = ':' . $prefix . '_' . $index;
            $params[$placeholder] = $value;
            $placeholders[] = $placeholder;
        }

        return $placeholders;
    }

    /** @param array<string, mixed> $selection @return list<string> */
    private function selectedCanonicalCategories(array $selection): array
    {
        $categories = [];
        $walk = function (array $areas) use (&$categories, &$walk): void {
            foreach ($areas as $area => $value) {
                if (is_array($value) && !array_is_list($value)) {
                    $walk($value);
                    continue;
                }
                $categories = [...$categories, ...($value ?? $this->categoryCatalog->categoriesForGroup($area))];
            }
        };
        $walk($selection);

        return array_values(array_unique($categories));
    }

    /**
     * @param array<string, list<string>|null> $selection
     * @param array<string, array<string, array<string, int>>> $facts
     * @return array<string, mixed>
     */
    private function canonicalIncomeBranch(string $month, array $selection, array $facts, int &$logicalRows): array
    {
        $areas = [];
        $total = 0;
        foreach ($this->categoryCatalog->incomeAreas() as $area => $details) {
            if (!array_key_exists($area, $selection)) {
                continue;
            }
            $areas[] = $this->canonicalArea($month, $area, $selection[$area], null, $facts, $logicalRows);
            $total += $this->cents($areas[array_key_last($areas)]['importe']);
        }

        $this->countLogicalRows($logicalRows);
        return [
            'importe' => $this->money($total),
            'cobertura' => $this->canonicalCoverage(count($areas), count($this->categoryCatalog->incomeAreas()), 'areas'),
            'areas' => $areas,
        ];
    }

    /**
     * @param array<string, array<string, list<string>|null>> $selection
     * @param array<string, array<string, array<string, int>>> $facts
     * @return array<string, mixed>
     */
    private function canonicalExpenseBranch(string $month, array $selection, array $facts, int &$logicalRows): array
    {
        $types = [];
        $total = 0;
        foreach ($this->categoryCatalog->expenseTypes() as $type => $catalogueAreas) {
            if (!isset($selection[$type])) {
                continue;
            }
            $areas = [];
            $typeTotal = 0;
            foreach ($catalogueAreas as $area => $_details) {
                if (!array_key_exists($area, $selection[$type])) {
                    continue;
                }
                $areas[] = $this->canonicalArea($month, $area, $selection[$type][$area], $type, $facts, $logicalRows);
                $typeTotal += $this->cents($areas[array_key_last($areas)]['importe']);
            }
            $this->countLogicalRows($logicalRows);
            $types[] = [
                'tipo' => $type,
                'importe' => $this->money($typeTotal),
                'cobertura' => $this->canonicalCoverage(count($areas), count($catalogueAreas), 'areas'),
                'areas' => $areas,
            ];
            $total += $typeTotal;
        }

        $this->countLogicalRows($logicalRows);
        return [
            'importe' => $this->money($total),
            'cobertura' => $this->canonicalCoverage(count($types), count($this->categoryCatalog->expenseTypes()), 'tipos'),
            'tipos' => $types,
        ];
    }

    /**
     * @param list<string>|null $selectedCategories
     * @param array<string, array<string, array<string, int>>> $facts
     * @return array<string, mixed>
     */
    private function canonicalArea(
        string $month,
        string $area,
        ?array $selectedCategories,
        ?string $expenseType,
        array $facts,
        int &$logicalRows,
    ): array {
        $categories = $selectedCategories ?? $this->categoryCatalog->categoriesForGroup($area);
        $items = [];
        $total = 0;
        $scope = $expenseType === null ? 'ingresos' : 'gastos';
        foreach ($this->categoryCatalog->categoriesForGroup($area) as $category) {
            if (!in_array($category, $categories, true)) {
                continue;
            }
            $amount = $facts[$month][$scope][($expenseType ?? '') . ':' . $category] ?? 0;
            $this->countLogicalRows($logicalRows);
            $items[] = ['categoria' => $category, 'importe' => $this->money($amount)];
            $total += $amount;
        }

        $this->countLogicalRows($logicalRows);
        return [
            'area' => $area,
            'importe' => $this->money($total),
            'cobertura' => $this->canonicalCoverage(count($items), count($this->categoryCatalog->categoriesForGroup($area)), 'categorias'),
            'categorias' => $items,
        ];
    }

    /** @return array<string, bool|int> */
    private function canonicalCoverage(int $selected, int $total, string $noun): array
    {
        $consulted = $noun === 'tipos' ? 'tipos_consultados' : $noun . '_consultadas';

        return [
            'completa' => $selected === $total,
            $consulted => $selected,
            $noun . '_totales' => $total,
        ];
    }

    private function countLogicalRows(int &$logicalRows): void
    {
        if (++$logicalRows > $this->maxToolResultRows) {
            throw new NumaFinancialToolLimitExceeded();
        }
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

    private function money(?int $cents): ?string
    {
        if ($cents === null) {
            return null;
        }

        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign . intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function cents(mixed $value): int
    {
        if (is_int($value)) {
            return $value * 100;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException('Cantidad financiera de Numa no valida.');
        }

        $amount = trim((string) $value);
        if (!preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new UnexpectedValueException('Cantidad financiera de Numa no valida.');
        }

        $fraction = str_pad($matches[3] ?? '', 2, '0');
        $cents = ((int) $matches[2] * 100) + (int) $fraction;

        return $matches[1] === '-' ? -$cents : $cents;
    }

    private function db(): PDO
    {
        return $this->connection ?? Database::getConnection();
    }
}
