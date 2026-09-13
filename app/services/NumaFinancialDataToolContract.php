<?php

declare(strict_types=1);

require_once __DIR__ . '/NumaFinancialTools.php';

final class NumaFinancialDataToolContract
{
    public const NAME = 'consultar_datos_financieros';

    public function __construct(private readonly NumaFinancialCategoryCatalog $catalog = new NumaFinancialCategoryCatalog())
    {
    }

    /** @return array{name:string,description:string,parameters:array<string,mixed>} */
    public function functionDeclaration(): array
    {
        return [
            'name' => self::NAME,
            'description' => 'Consulta hechos financieros mensuales canónicos. Interpreta completamente las referencias temporales usando el contexto autoritativo de BeneHom y envía únicamente intervalos mensuales concretos en formato YYYY-MM.',
            'parameters' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'periodos' => $this->periodsSchema(),
                    'selectores' => $this->selectorsSchema(),
                ],
                'required' => ['periodos'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function resultSchema(): array
    {
        $category = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'categoria' => ['type' => 'string'],
                'importe' => ['type' => 'string', 'pattern' => '^-?\\d+\\.\\d{2}$'],
            ],
            'required' => ['categoria', 'importe'],
        ];
        $categoryCoverage = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'completa' => ['type' => 'boolean'],
                'categorias_consultadas' => ['type' => 'integer'],
                'categorias_totales' => ['type' => 'integer'],
            ],
            'required' => ['completa', 'categorias_consultadas', 'categorias_totales'],
        ];
        $area = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'area' => ['type' => 'string'],
                'importe' => ['type' => 'string', 'pattern' => '^-?\\d+\\.\\d{2}$'],
                'cobertura' => $categoryCoverage,
                'categorias' => ['type' => 'array', 'description' => 'Ordenadas según el catálogo canónico.', 'items' => $category],
            ],
            'required' => ['area', 'importe', 'cobertura', 'categorias'],
        ];
        $areaCoverage = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'completa' => ['type' => 'boolean'],
                'areas_consultadas' => ['type' => 'integer'],
                'areas_totales' => ['type' => 'integer'],
            ],
            'required' => ['completa', 'areas_consultadas', 'areas_totales'],
        ];
        $income = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'importe' => ['type' => 'string', 'pattern' => '^-?\\d+\\.\\d{2}$'],
                'cobertura' => $areaCoverage,
                'areas' => ['type' => 'array', 'description' => 'Ordenadas según el catálogo canónico.', 'items' => $area],
            ],
            'required' => ['importe', 'cobertura', 'areas'],
        ];
        $expenseArea = $area;
        $expenseType = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'tipo' => ['type' => 'string'],
                'importe' => ['type' => 'string', 'pattern' => '^-?\\d+\\.\\d{2}$'],
                'cobertura' => $areaCoverage,
                'areas' => ['type' => 'array', 'description' => 'Ordenadas según el catálogo canónico.', 'items' => $expenseArea],
            ],
            'required' => ['tipo', 'importe', 'cobertura', 'areas'],
        ];
        $expense = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'importe' => ['type' => 'string', 'pattern' => '^-?\\d+\\.\\d{2}$'],
                'cobertura' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'completa' => ['type' => 'boolean'],
                        'tipos_consultados' => ['type' => 'integer'],
                        'tipos_totales' => ['type' => 'integer'],
                    ],
                    'required' => ['completa', 'tipos_consultados', 'tipos_totales'],
                ],
                'tipos' => ['type' => 'array', 'description' => 'Ordenados según el catálogo canónico.', 'items' => $expenseType],
            ],
            'required' => ['importe', 'cobertura', 'tipos'],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'meses' => [
                    'type' => 'array',
                    'description' => 'Meses naturales en el orden solicitado, sin duplicados.',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'mes' => ['type' => 'string', 'pattern' => '^20\\d{2}-(0[1-9]|1[0-2])$'],
                            'ingresos' => $income,
                            'gastos' => $expense,
                        ],
                        'required' => ['mes'],
                    ],
                ],
            ],
            'required' => ['meses'],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{periodos:list<array{mes_inicio:string,mes_fin:string}>,selectores:list<array<string, string>>}
     */
    public function validateArguments(array $arguments): array
    {
        $this->assertKeys($arguments, ['periodos', 'selectores']);
        if (!array_key_exists('periodos', $arguments)) {
            throw new NumaFinancialToolInputIncomplete('Periodos de Numa ausentes.');
        }

        $periods = $this->validatePeriods($arguments['periodos']);
        $selectors = array_key_exists('selectores', $arguments)
            ? $this->validateSelectors($arguments['selectores'])
            : [];

        return ['periodos' => $periods, 'selectores' => $selectors];
    }

    /** @return array<string, mixed> */
    private function periodsSchema(): array
    {
        return [
            'type' => 'array',
            'minItems' => 1,
            'description' => 'Lista no vacía de intervalos mensuales concretos, en el orden solicitado. Para un solo mes usa el mismo valor en mes_inicio y mes_fin. Para meses no contiguos usa elementos separados. No envíes expresiones relativas ni referencias por índice.',
            'items' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'mes_inicio' => ['type' => 'string', 'pattern' => '^20\\d{2}-(0[1-9]|1[0-2])$', 'description' => 'Primer mes inclusivo en formato YYYY-MM.'],
                    'mes_fin' => ['type' => 'string', 'pattern' => '^20\\d{2}-(0[1-9]|1[0-2])$', 'description' => 'Último mes inclusivo en formato YYYY-MM.'],
                ],
                'required' => ['mes_inicio', 'mes_fin'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function selectorsSchema(): array
    {
        return [
            'type' => 'array',
            'minItems' => 1,
            'description' => 'Ramas canónicas que se unen. Un selector hijo incorpora sus ancestros. Omite selectores para consultar ambos ámbitos completos. Relaciones: ' . $this->catalogueDescription(),
            'items' => [
                'type' => 'object',
                'properties' => [
                    'ambito' => $this->enumSchema(['ingresos', 'gastos'], 'Ámbito financiero canónico.'),
                    'tipo' => $this->enumSchema($this->catalog->expenseTypeValues(), 'Tipo de gasto canónico; solo admite gastos.'),
                    'area' => $this->enumSchema($this->catalog->groupValues(), 'Área canónica. La relación declarada determina su ámbito y, en gastos, su tipo.'),
                    'categoria' => $this->enumSchema($this->catalog->categoryValues(), 'Categoría canónica. La relación declarada determina todos sus ancestros.'),
                ],
            ],
        ];
    }

    /** @param mixed $value @return list<array{mes_inicio:string,mes_fin:string}> */
    private function validatePeriods(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || $value === []) {
            throw new InvalidArgumentException('Periodos de Numa no validos.');
        }

        $periods = [];
        foreach ($value as $period) {
            if (!is_array($period)) {
                throw new InvalidArgumentException('Periodo de Numa no valido.');
            }

            $this->assertKeys($period, ['mes_inicio', 'mes_fin']);
            $this->assertRequiredKeys($period, ['mes_inicio', 'mes_fin']);

            $start = $period['mes_inicio'];
            $end = $period['mes_fin'];
            if (!is_string($start) || !is_string($end)
                || preg_match('/^20\\d{2}-(?:0[1-9]|1[0-2])$/', $start) !== 1
                || preg_match('/^20\\d{2}-(?:0[1-9]|1[0-2])$/', $end) !== 1
            ) {
                throw new InvalidArgumentException('Mes de Numa no valido.');
            }
            if ($start > $end) {
                throw new InvalidArgumentException('Rango de Numa no valido.');
            }

            $periods[] = ['mes_inicio' => $start, 'mes_fin' => $end];
        }

        return $periods;
    }

    /** @param mixed $value @return list<array<string, string>> */
    private function validateSelectors(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || $value === []) {
            throw new InvalidArgumentException('Selectores de Numa no validos.');
        }

        $selectors = [];
        foreach ($value as $selector) {
            if (!is_array($selector) || $selector === []) {
                throw new InvalidArgumentException('Selector de Numa no valido.');
            }
            $this->assertKeys($selector, ['ambito', 'tipo', 'area', 'categoria']);

            $normalised = $this->normaliseSelector($selector);
            $selectors[json_encode($normalised, JSON_THROW_ON_ERROR)] = $normalised;
        }

        $selectors = array_values($selectors);

        return array_values(array_filter($selectors, function (array $candidate) use ($selectors): bool {
            foreach ($selectors as $other) {
                if ($candidate !== $other && $this->selectorIncludes($other, $candidate)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /** @param array<string, mixed> $selector @return array<string, string> */
    private function normaliseSelector(array $selector): array
    {
        foreach ($selector as $key => $value) {
            if (!is_string($value) || $value === '') {
                throw new InvalidArgumentException('Valor de selector de Numa no valido.');
            }
        }

        $scope = $selector['ambito'] ?? null;
        if ($scope !== null && !in_array($scope, ['ingresos', 'gastos'], true)) {
            throw new InvalidArgumentException('Ambito de Numa no permitido.');
        }
        $expenseType = $selector['tipo'] ?? null;
        if ($expenseType !== null && !in_array($expenseType, $this->catalog->expenseTypeValues(), true)) {
            throw new InvalidArgumentException('Tipo de gasto de Numa no permitido.');
        }

        $areaData = null;
        if (isset($selector['area'])) {
            $areaData = $this->catalog->group($selector['area']);
        }
        $categoryData = null;
        if (isset($selector['categoria'])) {
            if (!in_array($selector['categoria'], $this->catalog->categoryValues(), true)) {
                throw new InvalidArgumentException('Categoria de Numa no permitida.');
            }
            $categoryData = $this->catalog->category($selector['categoria']);
        }

        $inferredScope = $categoryData['kind'] ?? $areaData['kind'] ?? ($expenseType === null ? null : 'gasto');
        $inferredType = $categoryData['expense_type'] ?? $areaData['expense_type'] ?? $expenseType;
        $canonicalScope = $inferredScope === null ? $scope : ($inferredScope === 'ingreso' ? 'ingresos' : 'gastos');

        if ($canonicalScope === null || ($scope !== null && $scope !== $canonicalScope)) {
            throw new InvalidArgumentException('Relacion de selector de Numa no valida.');
        }
        if ($expenseType !== null && $expenseType !== $inferredType) {
            throw new InvalidArgumentException('Relacion de selector de Numa no valida.');
        }
        if ($areaData !== null && $categoryData !== null && $categoryData['group'] !== $selector['area']) {
            throw new InvalidArgumentException('Relacion de selector de Numa no valida.');
        }

        $normalised = ['ambito' => $canonicalScope];
        if ($inferredType !== null) {
            $normalised['tipo'] = $inferredType;
        }
        if ($areaData !== null) {
            $normalised['area'] = $selector['area'];
        } elseif ($categoryData !== null) {
            $normalised['area'] = $categoryData['group'];
        }
        if ($categoryData !== null) {
            $normalised['categoria'] = $selector['categoria'];
        }

        return $normalised;
    }

    /** @param array<string, string> $parent @param array<string, string> $child */
    private function selectorIncludes(array $parent, array $child): bool
    {
        foreach ($parent as $key => $value) {
            if (($child[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $value @param list<string> $allowed */
    private function assertKeys(array $value, array $allowed): void
    {
        foreach (array_keys($value) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Campo de Numa no permitido.');
            }
        }
    }

    /** @param array<string, mixed> $value @param list<string> $required */
    private function assertRequiredKeys(array $value, array $required): void
    {
        if (array_diff($required, array_keys($value)) !== []) {
            throw new NumaFinancialToolInputIncomplete('Campos de Numa incompletos.');
        }
    }

    /** @param list<string> $values @return array<string, mixed> */
    private function enumSchema(array $values, string $description): array
    {
        return ['type' => 'string', 'enum' => $values, 'description' => $description];
    }

    private function catalogueDescription(): string
    {
        $parts = [];
        foreach ($this->catalog->incomeAreas() as $area => $details) {
            $parts[] = 'ingresos/' . $area . ' (' . $details['label'] . '): ' . $this->labelledValues($details['categories']);
        }
        foreach ($this->catalog->expenseTypes() as $type => $areas) {
            foreach ($areas as $area => $details) {
                $parts[] = 'gastos/' . $type . '/' . $area . ' (' . $details['label'] . '): '
                    . $this->labelledValues($details['categories']);
            }
        }

        return implode('; ', $parts) . '.';
    }

    /** @param array<string, string> $values */
    private function labelledValues(array $values): string
    {
        return implode(', ', array_map(
            static fn (string $key, string $label): string => $key . ' (' . $label . ')',
            array_keys($values),
            $values,
        ));
    }
}
