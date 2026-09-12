<?php

declare(strict_types=1);

require_once __DIR__ . '/NumaFinancialTools.php';

/**
 * Contrato todavía aislado de la tool financiera única del Sprint 1.1.
 * El registro activo sigue declarando las seis tools anteriores hasta la Tarea 3.
 */
final class NumaFinancialDataToolContract
{
    public const NAME = 'consultar_datos_financieros';

    /** @var array<int, string> */
    private const PERIOD_TYPES = [
        'mes',
        'rango',
        'relativo',
        'ultimos_meses',
        'referencia_conversacional',
    ];

    /** @var array<int, string> */
    private const RELATIVE_PERIODS = [
        NumaPeriodResolver::CURRENT_MONTH,
        NumaPeriodResolver::PREVIOUS_MONTH,
        NumaPeriodResolver::CURRENT_YEAR,
        NumaPeriodResolver::PREVIOUS_YEAR,
    ];

    public function __construct(private readonly NumaFinancialCategoryCatalog $catalog = new NumaFinancialCategoryCatalog())
    {
    }

    /** @return array{name:string,description:string,parameters:array<string,mixed>} */
    public function functionDeclaration(): array
    {
        return [
            'name' => self::NAME,
            'description' => 'Consulta hechos financieros mensuales canónicos. Selecciona los períodos y ramas necesarias; la respuesta incluye solo importes, cobertura y sumas estructurales.',
            'parameters' => [
                'type' => 'object',
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
                    'description' => 'Meses naturales ordenados de forma ascendente.',
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
     * @return array{periodos:list<array<string, int|string>>,selectores:list<array<string, string>>}
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
            'description' => 'Lista no vacía de períodos. Cada elemento usa tipo=mes, rango, relativo, ultimos_meses o referencia_conversacional y solo sus campos correspondientes.',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'tipo' => $this->enumSchema(self::PERIOD_TYPES, 'Discriminante del período.'),
                    'mes' => ['type' => 'string', 'description' => 'Mes natural en formato YYYY-MM; se usa solo con tipo=mes.'],
                    'inicio' => ['type' => 'string', 'format' => 'date', 'description' => 'Fecha inicial inclusiva; se usa solo con tipo=rango.'],
                    'fin' => ['type' => 'string', 'format' => 'date', 'description' => 'Fecha final inclusiva; se usa solo con tipo=rango.'],
                    'periodo_relativo' => $this->enumSchema(self::RELATIVE_PERIODS, 'Período relativo resuelto por PHP; se usa solo con tipo=relativo.'),
                    'cantidad_meses' => ['type' => 'integer', 'description' => 'Número positivo de meses consecutivos, incluido el actual; se usa solo con tipo=ultimos_meses.'],
                    'indice' => ['type' => 'integer', 'description' => 'Índice desde cero de un período ya resuelto en la conversación actual; se usa solo con tipo=referencia_conversacional.'],
                ],
                'required' => ['tipo'],
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

    /** @param mixed $value @return list<array<string, int|string>> */
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

            $type = $period['tipo'] ?? null;
            if (!is_string($type) || !in_array($type, self::PERIOD_TYPES, true)) {
                throw new InvalidArgumentException('Tipo de periodo de Numa no permitido.');
            }

            $expectedKeys = match ($type) {
                'mes' => ['tipo', 'mes'],
                'rango' => ['tipo', 'inicio', 'fin'],
                'relativo' => ['tipo', 'periodo_relativo'],
                'ultimos_meses' => ['tipo', 'cantidad_meses'],
                'referencia_conversacional' => ['tipo', 'indice'],
            };
            $this->assertKeys($period, $expectedKeys);
            $this->assertRequiredKeys($period, $expectedKeys);

            if ($type === 'mes') {
                $month = $period['mes'];
                if (!is_string($month) || preg_match('/^20\\d{2}-(?:0[1-9]|1[0-2])$/', $month) !== 1) {
                    throw new InvalidArgumentException('Mes de Numa no valido.');
                }
            } elseif ($type === 'rango') {
                $start = $this->validDate($period['inicio']);
                $end = $this->validDate($period['fin']);
                if ($start > $end) {
                    throw new InvalidArgumentException('Rango de Numa no valido.');
                }
            } elseif ($type === 'relativo') {
                if (!is_string($period['periodo_relativo'])
                    || !in_array($period['periodo_relativo'], self::RELATIVE_PERIODS, true)
                ) {
                    throw new InvalidArgumentException('Periodo relativo de Numa no permitido.');
                }
            } elseif ($type === 'ultimos_meses') {
                if (!is_int($period['cantidad_meses']) || $period['cantidad_meses'] <= 0) {
                    throw new InvalidArgumentException('Cantidad de meses de Numa no valida.');
                }
            } elseif (!is_int($period['indice']) || $period['indice'] < 0) {
                throw new InvalidArgumentException('Referencia conversacional de Numa no valida.');
            }

            /** @var array<string, int|string> $period */
            $periods[] = $period;
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

    private function validDate(mixed $value): DateTimeImmutable
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Fecha de Numa no valida.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Europe/Madrid'));
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Fecha de Numa no valida.');
        }

        return $date;
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
