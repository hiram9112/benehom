<?php

class CalculosFinancieros {

    private const VALOR_ESTIMACION_INVERSION_MAXIMO = 9007199254740991;

    public static function normalizarCantidad($valor): ?float{
        $cantidad = trim((string) $valor);
        $cantidad = str_replace(',', '.', $cantidad);

        if ($cantidad === '' || !is_numeric($cantidad)) {
            return null;
        }

        $numero = floatval($cantidad);

        return is_finite($numero) ? $numero : null;
    }

    public static function calcularMesesHastaFechaObjetivo(string $fechaObjetivo, ?DateTimeImmutable $hoy = null): ?int{
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaObjetivo) !== 1) {
            return null;
        }

        $objetivo = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaObjetivo);

        if (!$objetivo || $objetivo->format('Y-m-d') !== $fechaObjetivo) {
            return null;
        }

        $hoy ??= new DateTimeImmutable('today');

        if ($objetivo <= $hoy) {
            return null;
        }

        $dias = $hoy->diff($objetivo)->days;

        return max(1, (int) ceil($dias / 30.4375));
    }

    public static function calcularEscenarioInversion($capitalInicial, $aportacionMensual, $rentabilidadAnual, $plazoAnios, $frecuenciaReinversion): array{
        $periodosPorAnio = self::periodosPorAnio($frecuenciaReinversion);
        $mesesPorPeriodo = intdiv(12, $periodosPorAnio);
        $tasaPeriodo = $rentabilidadAnual > 0 ? ($rentabilidadAnual / 100) / $periodosPorAnio : 0;
        // Tasa mensual equivalente a la del periodo de reinversión: permite incorporar las
        // aportaciones mes a mes en lugar de agruparlas al inicio del periodo.
        $tasaMensual = $tasaPeriodo > 0 ? pow(1 + $tasaPeriodo, 1 / $mesesPorPeriodo) - 1 : 0;
        $meses = $plazoAnios * 12;
        $capital = $capitalInicial;

        for ($mes = 1; $mes <= $meses; $mes++) {
            if ($tasaMensual > 0) {
                $capital += $capital * $tasaMensual;
            }

            $capital += $aportacionMensual;
        }

        $totalAportacionesPlazo = $aportacionMensual * $meses;
        $capitalTotalAportado = $capitalInicial + $totalAportacionesPlazo;
        $valorFinalEstimado = $capital;
        $rendimientoEstimado = max(0, $valorFinalEstimado - $capitalTotalAportado);
        $roiPorcentaje = $capitalTotalAportado > 0
            ? ($rendimientoEstimado / $capitalTotalAportado) * 100
            : 0;

        return [
            'total_aportaciones_plazo' => round($totalAportacionesPlazo, 2),
            'capital_total_aportado' => round($capitalTotalAportado, 2),
            'valor_final_estimado' => round($valorFinalEstimado, 2),
            'rendimiento_estimado' => round($rendimientoEstimado, 2),
            'roi_porcentaje' => round($roiPorcentaje, 2),
            'periodos_por_anio' => $periodosPorAnio,
            'meses_por_periodo' => $mesesPorPeriodo,
        ];
    }

    public static function validarEstimacionEscenarioInversion($capitalInicial, $aportacionMensual, $rentabilidadAnual, $plazoAnios, $frecuenciaReinversion): ?string{
        $resultado = self::calcularEscenarioInversion(
            $capitalInicial,
            $aportacionMensual,
            $rentabilidadAnual,
            $plazoAnios,
            $frecuenciaReinversion
        );

        foreach (['total_aportaciones_plazo', 'capital_total_aportado', 'valor_final_estimado', 'rendimiento_estimado'] as $campo) {
            $valor = (float) ($resultado[$campo] ?? INF);

            if (!is_finite($valor) || $valor > self::VALOR_ESTIMACION_INVERSION_MAXIMO) {
                return 'Con esos datos la estimación genera un resultado demasiado grande para calcularse de forma fiable. Reduce la rentabilidad, el plazo, el capital inicial o la aportación mensual.';
            }
        }

        return null;
    }

    public static function frecuenciasReinversionPermitidas(): array{
        return [
            'mensual' => 'Mensual',
            'trimestral' => 'Trimestral',
            'semestral' => 'Semestral',
            'anual' => 'Anual',
        ];
    }

    public static function periodosPorAnio($frecuenciaReinversion): int{
        return match ($frecuenciaReinversion) {
            'mensual' => 12,
            'trimestral' => 4,
            'semestral' => 2,
            'anual' => 1,
            default => 12,
        };
    }

    public static function calcularInflacionProyeccion($cantidadInicial, $inflacionAnual, $plazoAnios): array{
        $factor = pow(1 + $inflacionAnual / 100, $plazoAnios);
        $poderAdquisitivoFinal = $cantidadInicial / $factor;
        $perdidaEstimada = $cantidadInicial - $poderAdquisitivoFinal;
        $cantidadFuturaNecesaria = $cantidadInicial * $factor;
        $diferenciaNecesaria = $cantidadFuturaNecesaria - $cantidadInicial;

        return [
            'poder_adquisitivo_final' => round($poderAdquisitivoFinal, 2),
            'perdida_estimada' => round($perdidaEstimada, 2),
            'cantidad_futura_necesaria' => round($cantidadFuturaNecesaria, 2),
            'diferencia_necesaria' => round($diferenciaNecesaria, 2),
        ];
    }

    public static function calcularCalculadoraHipoteca($importePrestamo, $interesAnual, $plazoAnios): array{
        if ($interesAnual <= 0) {
            $cuotaMensual = $importePrestamo / ($plazoAnios * 12);
            $totalPagado = $importePrestamo;
            $totalIntereses = 0;
        } else {
            $tasaMensual = ($interesAnual / 100) / 12;
            $meses = $plazoAnios * 12;
            $factor = pow(1 + $tasaMensual, $meses);
            $cuotaMensual = $importePrestamo * ($tasaMensual * $factor) / ($factor - 1);
            $totalPagado = $cuotaMensual * $meses;
            $totalIntereses = $totalPagado - $importePrestamo;
        }

        return [
            'cuota_mensual' => round($cuotaMensual, 2),
            'total_intereses' => round($totalIntereses, 2),
            'total_pagado' => round($totalPagado, 2),
        ];
    }
}
