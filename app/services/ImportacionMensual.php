<?php
require_once __DIR__.'/../models/Database.php';
require_once __DIR__.'/../models/Ingreso.php';
require_once __DIR__.'/../models/Gasto.php';

class ImportacionMensual{

    public static function calcularMesAnterior($mesDestino){
        $partes = explode('-', $mesDestino);
        $anio = (int) $partes[0];
        $mes = (int) $partes[1];

        if ($mes === 1) {
            return sprintf('%04d-%02d', $anio - 1, 12);
        }

        return sprintf('%04d-%02d', $anio, $mes - 1);
    }

    public static function mesTieneMovimientos($usuario_id, $mes){
        $fechaInicio = $mes . '-01';
        $fechaFin = date('Y-m-t', strtotime($fechaInicio));

        $ingresos = Ingreso::obtenerPorMes($usuario_id, $fechaInicio, $fechaFin);
        $gastosEsenciales = Gasto::obtenerPorMes($usuario_id, 'esencial', $fechaInicio, $fechaFin);
        $gastosFlexibles = Gasto::obtenerPorMes($usuario_id, 'flexible', $fechaInicio, $fechaFin);

        return count($ingresos) > 0 || count($gastosEsenciales) > 0 || count($gastosFlexibles) > 0;
    }

    public static function importar($usuario_id, $mesDestino){
        $mesAnterior = self::calcularMesAnterior($mesDestino);

        $fechaInicioAnterior = $mesAnterior . '-01';
        $fechaFinAnterior = date('Y-m-t', strtotime($fechaInicioAnterior));

        $ingresosAnteriores = Ingreso::obtenerPorMes($usuario_id, $fechaInicioAnterior, $fechaFinAnterior);
        $esencialesAnteriores = Gasto::obtenerPorMes($usuario_id, 'esencial', $fechaInicioAnterior, $fechaFinAnterior);

        if (count($ingresosAnteriores) === 0 && count($esencialesAnteriores) === 0) {
            return [
                'ok' => false,
                'msg' => 'No hay ingresos ni gastos esenciales en el mes anterior para importar.',
                'codigo' => 'sin_datos_anteriores'
            ];
        }

        if (self::mesTieneMovimientos($usuario_id, $mesDestino)) {
            return [
                'ok' => false,
                'msg' => 'Este mes ya contiene movimientos y no se puede importar para evitar duplicados.',
                'codigo' => 'destino_no_vacio'
            ];
        }

        $fechaDestino = $mesDestino . '-01';

        $db = Database::getConnection();
        $transaccionIniciada = false;

        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $transaccionIniciada = true;
        }

        try {
            $ingresosImportados = 0;
            $esencialesImportados = 0;
            $totalIngresos = 0;
            $totalEsenciales = 0;

            foreach ($ingresosAnteriores as $ingreso) {
                $resultado = Ingreso::agregarIngreso(
                    $usuario_id,
                    $ingreso['categoria'],
                    $ingreso['cantidad'],
                    $fechaDestino
                );

                if ($resultado === false) {
                    throw new \Exception('Error al importar ingreso');
                }

                $ingresosImportados++;
                $totalIngresos += (float) $ingreso['cantidad'];
            }

            foreach ($esencialesAnteriores as $gasto) {
                $resultado = Gasto::agregarGasto(
                    $usuario_id,
                    'esencial',
                    $gasto['categoria'],
                    $gasto['cantidad'],
                    $fechaDestino
                );

                if ($resultado === false) {
                    throw new \Exception('Error al importar gasto esencial');
                }

                $esencialesImportados++;
                $totalEsenciales += (float) $gasto['cantidad'];
            }

            if ($transaccionIniciada) {
                $db->commit();
            }

            return [
                'ok' => true,
                'msg' => 'Importación completada correctamente.',
                'resumen' => [
                    'ingresos' => $ingresosImportados,
                    'esenciales' => $esencialesImportados,
                    'total_ingresos' => $totalIngresos,
                    'total_esenciales' => $totalEsenciales,
                    'mes_anterior' => $mesAnterior
                ]
            ];

        } catch (\Exception $e) {
            if ($transaccionIniciada && $db->inTransaction()) {
                $db->rollBack();
            }

            return [
                'ok' => false,
                'msg' => 'Error al importar los movimientos. Inténtalo de nuevo.',
                'codigo' => 'error_importacion'
            ];
        }
    }
}
