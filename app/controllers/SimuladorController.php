<?php
require_once APP_PATH . '/models/Ingreso.php';
require_once APP_PATH . '/models/Gasto.php';
require_once APP_PATH . '/models/MetaAhorro.php';

class SimuladorController {

    public function index(){
        if(!isset($_SESSION['usuario_id'])){
            header("Location: " . BASE_URL . "index.php?r=auth/login");
            exit;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $mesSeleccionado = $_SESSION['dashboard_mes_seleccionado'] ?? date('Y-m');

        if (!$this->mesValido($mesSeleccionado)) {
            $mesSeleccionado = date('Y-m');
        }

        $fechaInicio = $mesSeleccionado . '-01';
        $fechaFin = date('Y-m-t', strtotime($fechaInicio));

        $ingresosMes = Ingreso::totalPorRango($usuario_id, $fechaInicio, $fechaFin);
        $gastosEsencialesMes = Gasto::totalPorRango($usuario_id, $fechaInicio, $fechaFin, 'obligatorio');
        $gastosFlexiblesMes = Gasto::totalPorRango($usuario_id, $fechaInicio, $fechaFin, 'voluntario');
        $ahorroAsignadoMetas = MetaAhorro::totalAportacionesActivas($usuario_id);

        if ($ingresosMes === false || $gastosEsencialesMes === false || $gastosFlexiblesMes === false) {
            $_SESSION['mensaje_error'] = 'No se pudieron cargar los datos del Simulador.';
            header("Location: " . BASE_URL . "index.php?r=dashboard/index");
            exit;
        }

        if ($ahorroAsignadoMetas === false) {
            $ahorroAsignadoMetas = 0;
            $avisoAhorroAsignado = 'No se pudo calcular el ahorro asignado a metas. Se muestra como 0 hasta que las metas estén disponibles.';
        } else {
            $avisoAhorroAsignado = '';
        }

        $ahorroMensualDelMes = $ingresosMes - $gastosEsencialesMes - $gastosFlexiblesMes;
        $ahorroMensualDisponible = max(0, $ahorroMensualDelMes);

        if (
            isset($_SESSION['simulador_ahorro_mensual_manual'], $_SESSION['simulador_ahorro_mensual_manual_mes']) &&
            $_SESSION['simulador_ahorro_mensual_manual_mes'] === $mesSeleccionado &&
            is_numeric($_SESSION['simulador_ahorro_mensual_manual'])
        ) {
            $ahorroMensualDisponible = max(0, floatval($_SESSION['simulador_ahorro_mensual_manual']));
        }

        $ahorroDisponibleMetas = max(0, $ahorroMensualDisponible - $ahorroAsignadoMetas);
        $ahorroAsignadoSuperaDisponible = $ahorroAsignadoMetas > $ahorroMensualDisponible;

        require_once APP_PATH . "/views/simulador.php";
    }

    public function actualizarAhorroMensualAjax(){
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
            return;
        }

        if(!isset($_SESSION['usuario_id'])){
            echo json_encode(['ok' => false, 'msg' => 'Sesión no válida']);
            return;
        }

        $mesSeleccionado = $_SESSION['dashboard_mes_seleccionado'] ?? date('Y-m');

        if (!$this->mesValido($mesSeleccionado)) {
            $mesSeleccionado = date('Y-m');
        }

        $ahorroMensual = trim((string) ($_POST['ahorro_mensual'] ?? ''));
        $ahorroMensual = str_replace(',', '.', $ahorroMensual);

        if ($ahorroMensual === '' || !is_numeric($ahorroMensual) || floatval($ahorroMensual) < 0) {
            echo json_encode(['ok' => false, 'msg' => 'Introduce un ahorro mensual igual o superior a 0.']);
            return;
        }

        $ahorroMensualDisponible = round(floatval($ahorroMensual), 2);
        $_SESSION['simulador_ahorro_mensual_manual'] = $ahorroMensualDisponible;
        $_SESSION['simulador_ahorro_mensual_manual_mes'] = $mesSeleccionado;

        $ahorroAsignadoMetas = MetaAhorro::totalAportacionesActivas($_SESSION['usuario_id']);

        if ($ahorroAsignadoMetas === false) {
            $ahorroAsignadoMetas = 0;
        }

        echo json_encode([
            'ok' => true,
            'ahorroMensualDisponible' => $ahorroMensualDisponible,
            'ahorroAsignadoMetas' => $ahorroAsignadoMetas,
            'ahorroDisponibleMetas' => max(0, $ahorroMensualDisponible - $ahorroAsignadoMetas),
        ]);
    }

    private function mesValido($mes): bool{
        return is_string($mes) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) === 1;
    }

}
