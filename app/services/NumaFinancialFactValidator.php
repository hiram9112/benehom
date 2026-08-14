<?php

declare(strict_types=1);

/**
 * Comprueba solo los literales financieros que pueden aparecer en la redaccion final.
 * Los resultados de las tools siguen siendo la unica fuente de hechos financieros.
 */
final class NumaFinancialFactValidator
{
    private const AMOUNT_KEYS = [
        'ingresos', 'gastos', 'gastos_esenciales', 'gastos_flexibles', 'ahorro_posible', 'ahorro_real',
        'total', 'valor', 'valor_a', 'valor_b', 'diferencia_absoluta', 'promedio', 'maximo', 'minimo',
        'promedio_mensual', 'importe_total', 'cantidad',
    ];

    private const PERCENTAGE_KEYS = ['porcentaje', 'diferencia_porcentual'];
    private const DATE_KEYS = ['inicio', 'fin', 'fecha'];
    private const MONTH_KEYS = ['mes'];
    private const COUNT_KEYS = ['cantidad_movimientos', 'meses_con_datos', 'cantidad_total'];

    /**
     * @param array<int, array<string, mixed>> $toolResults
     * @return array<int, array{kind:string,value:string}>
     */
    public function facts(array $toolResults): array
    {
        $facts = [];

        foreach ($toolResults as $toolResult) {
            $this->collectFacts($toolResult, $facts);
        }

        $uniqueFacts = [];
        foreach ($facts as $fact) {
            $uniqueFacts[$fact['kind'] . ':' . $fact['value']] = $fact;
        }

        return array_values($uniqueFacts);
    }

    /**
     * @param array<int, array<string, mixed>> $toolResults
     */
    public function validates(string $message, array $toolResults): bool
    {
        $allowed = ['amount' => [], 'percentage' => [], 'date' => [], 'month' => [], 'count' => []];
        foreach ($this->facts($toolResults) as $fact) {
            $allowed[$fact['kind']][$fact['value']] = true;
        }

        $remaining = preg_replace_callback(
            '/(?<!\d)\d{4}-\d{2}-\d{2}(?!\d)/',
            function (array $matches) use ($allowed): string {
                $date = $matches[0];
                if (!isset($allowed['date'][$date])) {
                    return $date;
                }

                return str_repeat(' ', strlen($date));
            },
            $message
        );

        if ($remaining === null || preg_match('/(?<!\d)\d{4}-\d{2}-\d{2}(?!\d)/', $remaining) === 1) {
            return false;
        }

        $remaining = preg_replace_callback(
            '/(?<!\d)\d{4}-\d{2}(?!-\d{2}|\d)/',
            function (array $matches) use ($allowed): string {
                $month = $matches[0];
                if (!isset($allowed['month'][$month])) {
                    return $month;
                }

                return str_repeat(' ', strlen($month));
            },
            $remaining
        );

        if ($remaining === null || preg_match('/(?<!\d)\d{4}-\d{2}(?!-\d{2}|\d)/', $remaining) === 1) {
            return false;
        }

        $remaining = preg_replace_callback(
            '/(?<![\d.,])-?\d+(?:[.,]\d{1,2})?\s*(?:%|€|euros?\b|eur\b)/ui',
            function (array $matches) use ($allowed): string {
                $literal = $matches[0];
                $kind = preg_match('/%\s*$/u', $literal) === 1 ? 'percentage' : 'amount';
                $value = $this->normaliseDecimal($literal);

                if ($value === null || !isset($allowed[$kind][$value])) {
                    return $literal;
                }

                return str_repeat(' ', strlen($literal));
            },
            $remaining
        );

        if ($remaining === null || preg_match('/(?<![\d.,])-?\d+(?:[.,]\d{1,2})?\s*(?:%|€|euros?\b|eur\b)/ui', $remaining) === 1) {
            return false;
        }

        preg_match_all('/(?<![\d.,])-?\d+(?![\d.,])/u', $remaining, $matches);
        foreach ($matches[0] as $literal) {
            if (!isset($allowed['count'][(string) (int) $literal])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $toolResults
     */
    public function fallback(array $toolResults): string
    {
        $messages = [];

        foreach ($toolResults as $result) {
            $messages[] = match ($result['tool'] ?? null) {
                NumaFinancialToolRegistry::OBTENER_RESUMEN_FINANCIERO => $this->summaryFallback($result),
                NumaFinancialToolRegistry::OBTENER_RANKING_CATEGORIAS => $this->rankingFallback($result),
                NumaFinancialToolRegistry::OBTENER_EVOLUCION_FINANCIERA => $this->evolutionFallback($result),
                NumaFinancialToolRegistry::COMPARAR_PERIODOS => $this->comparisonFallback($result),
                NumaFinancialToolRegistry::OBTENER_ESTADISTICAS_MOVIMIENTOS => $this->statisticsFallback($result),
                NumaFinancialToolRegistry::OBTENER_MOVIMIENTOS => $this->movementsFallback($result),
                default => 'He consultado tus datos financieros en BeneHom.',
            };
        }

        return implode("\n\n", $messages);
    }

    /**
     * @param array<string, mixed> $value
     * @param array<int, array{kind:string,value:string}> $facts
     */
    private function collectFacts(array $value, array &$facts): void
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                if (is_string($key) && in_array($key, ['categorias', 'evolucion', 'movimientos'], true)) {
                    $facts[] = ['kind' => 'count', 'value' => (string) count($item)];
                }

                $this->collectFacts($item, $facts);
                continue;
            }

            if (!is_string($key)) {
                continue;
            }

            if (in_array($key, self::AMOUNT_KEYS, true)) {
                $amount = $this->normaliseDecimal($item);
                if ($amount !== null) {
                    $facts[] = ['kind' => 'amount', 'value' => $amount];
                }

                continue;
            }

            if (in_array($key, self::PERCENTAGE_KEYS, true)) {
                $percentage = $this->normaliseDecimal($item);
                if ($percentage !== null) {
                    $facts[] = ['kind' => 'percentage', 'value' => $percentage];
                }

                continue;
            }

            if (in_array($key, self::DATE_KEYS, true) && is_string($item)
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $item) === 1
            ) {
                $facts[] = ['kind' => 'date', 'value' => $item];
                continue;
            }

            if (in_array($key, self::MONTH_KEYS, true) && is_string($item)
                && preg_match('/^\d{4}-\d{2}$/', $item) === 1
            ) {
                $facts[] = ['kind' => 'month', 'value' => $item];
                continue;
            }

            if (in_array($key, self::COUNT_KEYS, true) && is_int($item)) {
                $facts[] = ['kind' => 'count', 'value' => (string) $item];
            }
        }
    }

    private function normaliseDecimal(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/(?:%|€|euros?|eur)/ui', '', $value) ?? '');
        $value = str_replace(' ', '', $value);
        if (!preg_match('/^-?\d+(?:[.,]\d{1,2})?$/', $value)) {
            return null;
        }

        return number_format((float) str_replace(',', '.', $value), 2, '.', '');
    }

    /** @param array<string, mixed> $result */
    private function summaryFallback(array $result): string
    {
        return $this->periodPrefix($result) . $this->factsSentence($result, [
            'ingresos' => 'Ingresos', 'gastos' => 'Gastos', 'ahorro_real' => 'Ahorro real',
        ]);
    }

    /** @param array<string, mixed> $result */
    private function rankingFallback(array $result): string
    {
        $categories = $result['categorias'] ?? [];
        if (!is_array($categories) || $categories === []) {
            return $this->periodPrefix($result) . 'No hay categorías con datos para ese periodo.';
        }

        $first = $categories[0];
        if (!is_array($first)) {
            return $this->periodPrefix($result) . 'No hay categorías con datos para ese periodo.';
        }

        return $this->periodPrefix($result) . 'La primera categoría es ' . ($first['label'] ?? $first['categoria'] ?? 'sin nombre')
            . ': ' . $this->amountText($first['total'] ?? null) . ' (' . $this->percentageText($first['porcentaje'] ?? null) . ').';
    }

    /** @param array<string, mixed> $result */
    private function evolutionFallback(array $result): string
    {
        $items = $result['evolucion'] ?? [];
        if (!is_array($items) || $items === []) {
            return $this->periodPrefix($result) . 'No hay evolución registrada para ese periodo.';
        }

        $first = $items[0];
        if (!is_array($first)) {
            return $this->periodPrefix($result) . 'No hay evolución registrada para ese periodo.';
        }

        $label = $first['mes'] ?? $first['label'] ?? $first['categoria'] ?? $first['tipo'] ?? 'El primer resultado';

        return $this->periodPrefix($result) . $label . ': ' . $this->amountText($first['valor'] ?? null) . '.';
    }

    /** @param array<string, mixed> $result */
    private function comparisonFallback(array $result): string
    {
        return 'Periodo A: ' . $this->periodText($result['periodo_a'] ?? null) . ', ' . $this->amountText($result['valor_a'] ?? null)
            . '. Periodo B: ' . $this->periodText($result['periodo_b'] ?? null) . ', ' . $this->amountText($result['valor_b'] ?? null)
            . '. Diferencia: ' . $this->amountText($result['diferencia_absoluta'] ?? null) . '.';
    }

    /** @param array<string, mixed> $result */
    private function statisticsFallback(array $result): string
    {
        $count = is_int($result['cantidad_movimientos'] ?? null) ? (string) $result['cantidad_movimientos'] : '0';

        return $this->periodPrefix($result) . 'Movimientos: ' . $count . '. Total: ' . $this->amountText($result['total'] ?? null) . '.';
    }

    /** @param array<string, mixed> $result */
    private function movementsFallback(array $result): string
    {
        $count = is_int($result['cantidad_total'] ?? null) ? (string) $result['cantidad_total'] : '0';

        return $this->periodPrefix($result) . 'Movimientos encontrados: ' . $count
            . '. Importe total: ' . $this->amountText($result['importe_total'] ?? null) . '.';
    }

    /** @param array<string, mixed> $result */
    private function periodPrefix(array $result): string
    {
        return 'Del ' . $this->periodText($result['periodo'] ?? null) . ': ';
    }

    private function periodText(mixed $period): string
    {
        if (!is_array($period) || !is_string($period['inicio'] ?? null) || !is_string($period['fin'] ?? null)) {
            return 'periodo consultado';
        }

        return $period['inicio'] . ' al ' . $period['fin'];
    }

    /** @param array<string, mixed> $result @param array<string, string> $labels */
    private function factsSentence(array $result, array $labels): string
    {
        $facts = [];
        foreach ($labels as $key => $label) {
            if (array_key_exists($key, $result)) {
                $facts[] = $label . ': ' . $this->amountText($result[$key]);
            }
        }

        return implode('. ', $facts) . ($facts === [] ? 'No hay datos financieros para ese periodo.' : '.');
    }

    private function amountText(mixed $value): string
    {
        return ($this->normaliseDecimal($value) ?? '0.00') . ' EUR';
    }

    private function percentageText(mixed $value): string
    {
        return ($this->normaliseDecimal($value) ?? '0.00') . '%';
    }
}
