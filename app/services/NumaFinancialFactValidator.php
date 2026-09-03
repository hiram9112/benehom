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
    private const DATE_KEYS = ['inicio', 'fin'];
    private const MONTH_KEYS = ['mes'];
    private const COUNT_KEYS = ['cantidad_movimientos', 'meses_con_datos', 'cantidad_total'];
    private const TECHNICAL_LABEL_PATTERN = '/\b(?:periodo_[ab]|valor_[ab]|diferencia_(?:absoluta|porcentual)|cantidad_movimientos|cantidad_total|importe_total|promedio_mensual|meses_con_datos|mes_mayor_valor|periodo_solicitado|tipo_movimiento|tipo_gasto|seleccion_acotada|resultado_acotado|obtener_[a-z_]+)\b|\b(?:per[ií]odo|valor)\s+[ab]\b/iu';

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
        if (preg_match(self::TECHNICAL_LABEL_PATTERN, $message) === 1) {
            return false;
        }

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
            '/\b(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre)\s+de\s+(\d{4})\b/ui',
            function (array $matches) use ($allowed): string {
                $monthNumbers = [
                    'enero' => '01', 'febrero' => '02', 'marzo' => '03', 'abril' => '04', 'mayo' => '05', 'junio' => '06',
                    'julio' => '07', 'agosto' => '08', 'septiembre' => '09', 'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12',
                ];
                $month = $matches[2] . '-' . $monthNumbers[strtolower($matches[1])];

                return isset($allowed['month'][$month]) ? str_repeat(' ', strlen($matches[0])) : $matches[0];
            },
            $remaining
        );

        if ($remaining === null) {
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

            if ($key === 'fecha' && is_string($item)
                && preg_match('/^\d{4}-(0[1-9]|1[0-2])-\d{2}(?:\s.*)?$/', $item) === 1
            ) {
                $facts[] = ['kind' => 'month', 'value' => substr($item, 0, 7)];
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
        $sentences = [];
        foreach ([
            'ingresos' => 'Tus ingresos fueron',
            'gastos' => 'Tus gastos fueron',
            'ahorro_real' => 'Tu ahorro real fue',
        ] as $key => $label) {
            if (array_key_exists($key, $result)) {
                $sentences[] = ($sentences === [] ? lcfirst($label) : $label) . ' '
                    . $this->amountText($result[$key]) . '.';
            }
        }

        return $this->periodPrefix($result) . ($sentences === [] ? 'No hay datos financieros.' : implode(' ', $sentences));
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

        return $this->periodPrefix($result) . 'la categoría con mayor importe fue ' . ($first['label'] ?? $first['categoria'] ?? 'sin nombre')
            . ', con ' . $this->amountText($first['total'] ?? null) . ' (' . $this->percentageText($first['porcentaje'] ?? null) . ').';
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

        $month = $first['mes'] ?? null;
        if (is_string($month)) {
            return 'En ' . $this->monthText($month) . ', el valor fue ' . $this->amountText($first['valor'] ?? null) . '.';
        }

        $label = $first['label'] ?? $first['categoria'] ?? $first['tipo'] ?? 'El primer resultado';

        return $this->periodPrefix($result) . $label . ' fue ' . $this->amountText($first['valor'] ?? null) . '.';
    }

    /** @param array<string, mixed> $result */
    private function comparisonFallback(array $result): string
    {
        $metric = is_string($result['metrica'] ?? null) ? $result['metrica'] : '';
        $periodA = $this->periodText($result['periodo_a'] ?? null);
        $periodB = $this->periodText($result['periodo_b'] ?? null);

        return $this->comparisonValueText($periodA, $metric, $result['valor_a'] ?? null)
            . ', mientras que ' . $this->comparisonValueText($periodB, $metric, $result['valor_b'] ?? null, false)
            . '. ' . $this->comparisonDifferenceText($result['diferencia_absoluta'] ?? null, $periodA, $periodB);
    }

    /** @param array<string, mixed> $result */
    private function statisticsFallback(array $result): string
    {
        $count = is_int($result['cantidad_movimientos'] ?? null) ? (string) $result['cantidad_movimientos'] : '0';

        return $this->periodPrefix($result) . 'se registraron ' . $count . ' movimientos, por un total de '
            . $this->amountText($result['total'] ?? null) . '.';
    }

    /** @param array<string, mixed> $result */
    private function movementsFallback(array $result): string
    {
        $count = is_int($result['cantidad_total'] ?? null) ? (string) $result['cantidad_total'] : '0';

        return $this->periodPrefix($result) . 'se encontraron ' . $count . ' movimientos, con un importe total de '
            . $this->amountText($result['importe_total'] ?? null) . '.';
    }

    /** @param array<string, mixed> $result */
    private function periodPrefix(array $result): string
    {
        return 'En ' . $this->periodText($result['periodo'] ?? null) . ', ';
    }

    private function periodText(mixed $period): string
    {
        if (!is_array($period) || !is_string($period['inicio'] ?? null) || !is_string($period['fin'] ?? null)) {
            return 'el periodo consultado';
        }

        $start = $period['inicio'];
        $end = $period['fin'];
        $startDate = $this->date($start);
        if ($startDate !== null
            && $startDate->format('d') === '01'
            && $startDate->modify('last day of this month')->format('Y-m-d') === $end
        ) {
            return $this->monthText(substr($start, 0, 7));
        }

        return 'del ' . $this->dateText($start) . ' al ' . $this->dateText($end);
    }

    private function comparisonValueText(string $period, string $metric, mixed $value, bool $capitalize = true): string
    {
        $prefix = $capitalize ? 'En ' : 'en ';
        $amount = $this->amountText($value);

        return match ($metric) {
            'gastos' => $prefix . $period . ' gastaste ' . $amount,
            'ingresos' => $prefix . $period . ' tus ingresos fueron ' . $amount,
            'gastos_esenciales' => $prefix . $period . ' tus gastos esenciales fueron ' . $amount,
            'gastos_flexibles' => $prefix . $period . ' tus gastos flexibles fueron ' . $amount,
            'ahorro_posible' => $prefix . $period . ' tu ahorro posible fue ' . $amount,
            'ahorro_real' => $prefix . $period . ' tu ahorro real fue ' . $amount,
            default => $prefix . $period . ' el valor fue ' . $amount,
        };
    }

    private function comparisonDifferenceText(mixed $difference, string $periodA, string $periodB): string
    {
        $normalisedDifference = $this->normaliseDecimal($difference);
        if ($normalisedDifference === null) {
            return 'La diferencia entre ambos periodos fue ' . $this->amountText($difference) . '.';
        }

        $amount = ltrim($normalisedDifference, '-') . ' EUR';
        if (str_starts_with($normalisedDifference, '-')) {
            return 'Esto supone una disminución de ' . $amount . ' en ' . $periodB . ' respecto a ' . $periodA . '.';
        }

        if ($normalisedDifference !== '0.00') {
            return 'Esto supone un aumento de ' . $amount . ' en ' . $periodB . ' respecto a ' . $periodA . '.';
        }

        return 'No hubo variación entre ' . $periodA . ' y ' . $periodB . ': la diferencia fue ' . $amount . '.';
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    private function dateText(string $date): string
    {
        $parsed = $this->date($date);

        return $parsed === null ? 'la fecha consultada' : $parsed->format('j') . ' de '
            . $this->monthName((int) $parsed->format('n')) . ' de ' . $parsed->format('Y');
    }

    private function monthText(string $month): string
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $month, $matches) !== 1) {
            return 'el periodo consultado';
        }

        $monthNumber = (int) $matches[2];

        return $monthNumber >= 1 && $monthNumber <= 12
            ? $this->monthName($monthNumber) . ' de ' . $matches[1]
            : 'el periodo consultado';
    }

    private function monthName(int $month): string
    {
        return [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
            7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ][$month];
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
