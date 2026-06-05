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
        $metasAhorro = MetaAhorro::obtenerActivasPorUsuario($usuario_id);

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

        if ($metasAhorro === false) {
            $metasAhorro = [];
            $avisoAhorroAsignado = 'No se pudieron cargar tus metas de ahorro. Inténtalo de nuevo más tarde.';
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
        $metasAhorroPreparadas = array_map([$this, 'prepararMetaParaVista'], $metasAhorro);

        require_once APP_PATH . "/views/simulador.php";
    }

    public function crearMetaAhorro(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $resultado = $this->validarDatosMeta($usuario_id, null);

        if (!$resultado['ok']) {
            $_SESSION['mensaje_error'] = $resultado['mensaje'];
            $this->redirigirAlSimulador();
        }

        $datos = $resultado['datos'];
        $nuevaMeta = MetaAhorro::crear(
            $usuario_id,
            $datos['nombre'],
            $datos['categoria'],
            $datos['importe_objetivo'],
            $datos['aportacion_mensual'],
            $datos['fecha_objetivo']
        );

        if (!$nuevaMeta) {
            $_SESSION['mensaje_error'] = 'No se pudo guardar la meta. Revisa los datos e inténtalo de nuevo.';
            $this->redirigirAlSimulador();
        }

        $_SESSION['mensaje_exitoso'] = 'Meta de ahorro creada como escenario simulado.';
        $this->redirigirAlSimulador();
    }

    public function actualizarMetaAhorro(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0 || !MetaAhorro::obtenerPorIdYUsuario($id, $usuario_id)) {
            $_SESSION['mensaje_error'] = 'No se encontró la meta que quieres editar.';
            $this->redirigirAlSimulador();
        }

        $resultado = $this->validarDatosMeta($usuario_id, $id);

        if (!$resultado['ok']) {
            $_SESSION['mensaje_error'] = $resultado['mensaje'];
            $this->redirigirAlSimulador();
        }

        $datos = $resultado['datos'];
        $actualizada = MetaAhorro::actualizar(
            $id,
            $usuario_id,
            $datos['nombre'],
            $datos['categoria'],
            $datos['importe_objetivo'],
            $datos['aportacion_mensual'],
            $datos['fecha_objetivo']
        );

        if (!$actualizada) {
            $_SESSION['mensaje_error'] = 'No se pudo actualizar la meta. Se conservó el escenario anterior.';
            $this->redirigirAlSimulador();
        }

        $_SESSION['mensaje_exitoso'] = 'Meta de ahorro actualizada.';
        $this->redirigirAlSimulador();
    }

    public function eliminarMetaAhorro(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['mensaje_error'] = 'No se recibió una meta válida para eliminar.';
            $this->redirigirAlSimulador();
        }

        if (!MetaAhorro::obtenerPorIdYUsuario($id, $usuario_id)) {
            $_SESSION['mensaje_error'] = 'No se encontró la meta que quieres eliminar.';
            $this->redirigirAlSimulador();
        }

        $eliminada = MetaAhorro::eliminarPorUsuario($id, $usuario_id);

        if (!$eliminada) {
            $_SESSION['mensaje_error'] = 'No se pudo eliminar la meta. Inténtalo de nuevo.';
            $this->redirigirAlSimulador();
        }

        $_SESSION['mensaje_exitoso'] = 'Meta eliminada. Su aportación deja de contar como capacidad usada.';
        $this->redirigirAlSimulador();
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

    private function peticionPostAutenticada(): bool{
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            $_SESSION['mensaje_error'] = 'Método no permitido.';
            $this->redirigirAlSimulador();
        }

        if(!isset($_SESSION['usuario_id'])){
            header("Location: " . BASE_URL . "index.php?r=auth/login");
            exit;
        }

        return true;
    }

    private function validarDatosMeta($usuario_id, $meta_id): array{
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $categoria = trim((string) ($_POST['categoria'] ?? ''));
        $modoCalculo = trim((string) ($_POST['modo_calculo'] ?? ''));
        $importeObjetivo = $this->normalizarCantidad($_POST['importe_objetivo'] ?? null);

        if ($nombre === '') {
            return $this->errorValidacion('El nombre de la meta es obligatorio.');
        }

        if (strlen($nombre) > 100) {
            return $this->errorValidacion('El nombre no puede superar 100 caracteres.');
        }

        if ($categoria === '') {
            return $this->errorValidacion('La categoría de la meta es obligatoria.');
        }

        if (strlen($categoria) > 60) {
            return $this->errorValidacion('La categoría no puede superar 60 caracteres.');
        }

        if ($importeObjetivo === null || $importeObjetivo <= 0) {
            return $this->errorValidacion('El importe objetivo debe ser mayor que 0.');
        }

        if (!in_array($modoCalculo, ['aportacion', 'fecha'], true)) {
            return $this->errorValidacion('Selecciona un modo de cálculo válido.');
        }

        $capacidadMensual = $this->obtenerAhorroMensualConfigurado($usuario_id);
        $aportacionesActuales = $meta_id
            ? MetaAhorro::totalAportacionesActivasExcluyendoMeta($usuario_id, $meta_id)
            : MetaAhorro::totalAportacionesActivas($usuario_id);

        if ($aportacionesActuales === false) {
            return $this->errorValidacion('No se pudo calcular la capacidad ya asignada a metas.');
        }

        $capacidadDisponible = max(0, $capacidadMensual - $aportacionesActuales);
        $fechaObjetivo = null;

        if ($modoCalculo === 'aportacion') {
            $aportacionMensual = $this->normalizarCantidad($_POST['aportacion_mensual'] ?? null);

            if ($aportacionMensual === null || $aportacionMensual <= 0) {
                return $this->errorValidacion('La aportación mensual debe ser mayor que 0.');
            }
        } else {
            $fechaObjetivo = trim((string) ($_POST['fecha_objetivo'] ?? ''));
            $mesesHastaObjetivo = $this->calcularMesesHastaFechaObjetivo($fechaObjetivo);

            if ($mesesHastaObjetivo === null) {
                return $this->errorValidacion('La fecha objetivo debe ser futura.');
            }

            $aportacionMensual = ceil(($importeObjetivo / $mesesHastaObjetivo) * 100) / 100;
        }

        $aportacionMensual = round($aportacionMensual, 2);

        if ($aportacionMensual > $capacidadDisponible) {
            $faltante = $aportacionMensual - $capacidadDisponible;
            return $this->errorValidacion(
                'Esta meta necesita ' . formatearCantidadPHP($aportacionMensual) . ' €/mes y tu capacidad disponible para nuevas metas es ' .
                formatearCantidadPHP($capacidadDisponible) . ' €/mes. Faltarían ' . formatearCantidadPHP($faltante) . ' €/mes.'
            );
        }

        return [
            'ok' => true,
            'datos' => [
                'nombre' => $nombre,
                'categoria' => $categoria,
                'importe_objetivo' => round($importeObjetivo, 2),
                'aportacion_mensual' => $aportacionMensual,
                'fecha_objetivo' => $fechaObjetivo,
            ],
        ];
    }

    private function obtenerAhorroMensualConfigurado($usuario_id): float{
        $mesSeleccionado = $_SESSION['dashboard_mes_seleccionado'] ?? date('Y-m');

        if (!$this->mesValido($mesSeleccionado)) {
            $mesSeleccionado = date('Y-m');
        }

        if (
            isset($_SESSION['simulador_ahorro_mensual_manual'], $_SESSION['simulador_ahorro_mensual_manual_mes']) &&
            $_SESSION['simulador_ahorro_mensual_manual_mes'] === $mesSeleccionado &&
            is_numeric($_SESSION['simulador_ahorro_mensual_manual'])
        ) {
            return max(0, floatval($_SESSION['simulador_ahorro_mensual_manual']));
        }

        $fechaInicio = $mesSeleccionado . '-01';
        $fechaFin = date('Y-m-t', strtotime($fechaInicio));
        $ingresosMes = Ingreso::totalPorRango($usuario_id, $fechaInicio, $fechaFin);
        $gastosEsencialesMes = Gasto::totalPorRango($usuario_id, $fechaInicio, $fechaFin, 'obligatorio');
        $gastosFlexiblesMes = Gasto::totalPorRango($usuario_id, $fechaInicio, $fechaFin, 'voluntario');

        if ($ingresosMes === false || $gastosEsencialesMes === false || $gastosFlexiblesMes === false) {
            return 0;
        }

        return max(0, $ingresosMes - $gastosEsencialesMes - $gastosFlexiblesMes);
    }

    private function normalizarCantidad($valor): ?float{
        $cantidad = trim((string) $valor);
        $cantidad = str_replace(',', '.', $cantidad);

        if ($cantidad === '' || !is_numeric($cantidad)) {
            return null;
        }

        return floatval($cantidad);
    }

    private function calcularMesesHastaFechaObjetivo($fechaObjetivo): ?int{
        if (!is_string($fechaObjetivo) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaObjetivo) !== 1) {
            return null;
        }

        $objetivo = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaObjetivo);

        if (!$objetivo || $objetivo->format('Y-m-d') !== $fechaObjetivo) {
            return null;
        }

        $hoy = new DateTimeImmutable('today');

        if ($objetivo <= $hoy) {
            return null;
        }

        $dias = $hoy->diff($objetivo)->days;

        return max(1, (int) ceil($dias / 30.4375));
    }

    private function prepararMetaParaVista($meta): array{
        $importeObjetivo = floatval($meta['importe_objetivo']);
        $aportacionMensual = floatval($meta['aportacion_mensual']);
        $plazoMeses = $aportacionMensual > 0 ? (int) ceil($importeObjetivo / $aportacionMensual) : null;
        $fechaEstimada = null;

        if (!empty($meta['fecha_objetivo'])) {
            $fechaEstimada = $meta['fecha_objetivo'];
            $plazoMeses = $this->calcularMesesHastaFechaObjetivo($meta['fecha_objetivo']) ?? $plazoMeses;
        } elseif ($plazoMeses !== null) {
            $fechaEstimada = (new DateTimeImmutable('today'))->modify('+' . $plazoMeses . ' months')->format('Y-m-d');
        }

        $meta['modo_calculo'] = !empty($meta['fecha_objetivo']) ? 'fecha' : 'aportacion';
        $meta['plazo_meses_estimado'] = $plazoMeses;
        $meta['fecha_finalizacion_estimada'] = $fechaEstimada;

        return $meta;
    }

    private function errorValidacion($mensaje): array{
        return [
            'ok' => false,
            'mensaje' => $mensaje,
        ];
    }

    private function redirigirAlSimulador(): void{
        header("Location: " . BASE_URL . "index.php?r=simulador/index");
        exit;
    }

}
