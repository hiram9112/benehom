<?php
require_once APP_PATH . '/models/Ingreso.php';
require_once APP_PATH . '/models/Gasto.php';
require_once APP_PATH . '/models/MetaAhorro.php';
require_once APP_PATH . '/models/EscenarioInversion.php';
require_once APP_PATH . '/models/InflacionSimulacion.php';
require_once APP_PATH . '/models/CalculadoraHipoteca.php';

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
        $gastosFlexiblesPorCategoria = Gasto::totalesPorCategoriaYRango($usuario_id, $fechaInicio, $fechaFin, 'voluntario');
        $ahorroAsignadoMetas = MetaAhorro::totalAportacionesActivas($usuario_id);
        $metasAhorro = MetaAhorro::obtenerActivasPorUsuario($usuario_id);
        $escenariosInversion = EscenarioInversion::obtenerPorUsuario($usuario_id);
        $simulacionesInflacion = InflacionSimulacion::obtenerPorUsuario($usuario_id);
        $calculadorasHipoteca = CalculadoraHipoteca::obtenerPorUsuario($usuario_id);

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

        if ($gastosFlexiblesPorCategoria === false) {
            $gastosFlexiblesPorCategoria = [];
            $avisoGastosFlexibles = 'No se pudieron cargar los gastos flexibles para simular reducciones.';
        } else {
            $avisoGastosFlexibles = '';
        }

        if ($escenariosInversion === false) {
            $escenariosInversion = [];
            $avisoEscenariosInversion = 'No se pudieron cargar tus escenarios de inversión. Inténtalo de nuevo más tarde.';
        } else {
            $avisoEscenariosInversion = '';
        }

        if ($simulacionesInflacion === false) {
            $simulacionesInflacion = [];
            $avisoSimulacionesInflacion = 'No se pudieron cargar las simulaciones de inflación. Inténtalo de nuevo más tarde.';
        } else {
            $avisoSimulacionesInflacion = '';
        }

        if ($calculadorasHipoteca === false) {
            $calculadorasHipoteca = [];
            $avisoCalculadorasHipoteca = 'No se pudieron cargar las calculadoras de hipoteca. Inténtalo de nuevo más tarde.';
        } else {
            $avisoCalculadorasHipoteca = '';
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
        $escenariosInversionPreparados = array_map([$this, 'prepararEscenarioInversionParaVista'], $escenariosInversion);
        $simulacionesInflacionPreparadas = array_map([$this, 'prepararInflacionSimulacionParaVista'], $simulacionesInflacion);
        $calculadorasHipotecaPreparadas = array_map([$this, 'prepararCalculadoraHipotecaParaVista'], $calculadorasHipoteca);

        require_once APP_PATH . "/views/simulador.php";
    }

    public function crearEscenarioInversion(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $resultado = $this->validarDatosEscenarioInversion();

        if (!$resultado['ok']) {
            $_SESSION['mensaje_error'] = $resultado['mensaje'];
            $this->redirigirAlSimulador();
        }

        $datos = $resultado['datos'];
        $nuevoEscenario = EscenarioInversion::crear(
            $usuario_id,
            $datos['nombre'],
            $datos['capital_inicial'],
            $datos['aportacion_mensual'],
            $datos['rentabilidad_anual'],
            $datos['plazo_anios'],
            $datos['frecuencia_reinversion']
        );

        if (!$nuevoEscenario) {
            $_SESSION['mensaje_error'] = 'No se pudo guardar el escenario de inversión. Revisa los datos e inténtalo de nuevo.';
            $this->redirigirAlSimulador();
        }

        $_SESSION['mensaje_exitoso'] = 'Escenario guardado. Puedes comparar la estimación cuando quieras.';
        $this->redirigirAlSimulador();
    }

    public function actualizarEscenarioInversion(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['mensaje_error'] = 'No se recibió un escenario válido para editar.';
            $this->redirigirAlSimulador();
        }

        if (!EscenarioInversion::obtenerPorIdYUsuario($id, $usuario_id)) {
            $_SESSION['mensaje_error'] = 'No se encontró el escenario de inversión que quieres editar.';
            $this->redirigirAlSimulador();
        }

        $resultado = $this->validarDatosEscenarioInversion();

        if (!$resultado['ok']) {
            $_SESSION['mensaje_error'] = $resultado['mensaje'];
            $this->redirigirAlSimulador();
        }

        $datos = $resultado['datos'];
        $actualizado = EscenarioInversion::actualizar(
            $id,
            $usuario_id,
            $datos['nombre'],
            $datos['capital_inicial'],
            $datos['aportacion_mensual'],
            $datos['rentabilidad_anual'],
            $datos['plazo_anios'],
            $datos['frecuencia_reinversion']
        );

        if (!$actualizado) {
            $_SESSION['mensaje_error'] = 'No se pudo actualizar el escenario de inversión. Inténtalo de nuevo.';
            $this->redirigirAlSimulador();
        }

        $_SESSION['mensaje_exitoso'] = 'Escenario actualizado.';
        $this->redirigirAlSimulador();
    }

    public function actualizarEscenarioInversionAjax(){
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
            return;
        }

        if(!isset($_SESSION['usuario_id'])){
            echo json_encode(['ok' => false, 'msg' => 'Sesión no válida']);
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $id = intval($_POST['id'] ?? 0);
        $campo = trim((string) ($_POST['campo'] ?? ''));
        $valor = $this->normalizarCantidad($_POST['valor'] ?? null);
        $camposPermitidos = ['capital_inicial', 'aportacion_mensual', 'rentabilidad_anual'];

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'No se recibió un escenario válido.']);
            return;
        }

        if (!in_array($campo, $camposPermitidos, true)) {
            echo json_encode(['ok' => false, 'msg' => 'No se recibió un campo editable válido.']);
            return;
        }

        if ($valor === null || $valor < 0) {
            echo json_encode(['ok' => false, 'msg' => 'Introduce un valor igual o superior a 0.']);
            return;
        }

        $escenario = EscenarioInversion::obtenerPorIdYUsuario($id, $usuario_id);

        if (!$escenario) {
            echo json_encode(['ok' => false, 'msg' => 'No se encontró el escenario de inversión que quieres actualizar.']);
            return;
        }

        $escenario[$campo] = round($valor, 2);
        $frecuenciaReinversion = $escenario['frecuencia_reinversion'] ?? 'mensual';

        if (!array_key_exists($frecuenciaReinversion, $this->frecuenciasReinversionPermitidas())) {
            $frecuenciaReinversion = 'mensual';
        }

        $actualizado = EscenarioInversion::actualizar(
            $id,
            $usuario_id,
            $escenario['nombre'],
            round(floatval($escenario['capital_inicial']), 2),
            round(floatval($escenario['aportacion_mensual']), 2),
            round(floatval($escenario['rentabilidad_anual']), 2),
            intval($escenario['plazo_anios']),
            $frecuenciaReinversion
        );

        if (!$actualizado) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo actualizar el escenario de inversión.']);
            return;
        }

        $escenario['frecuencia_reinversion'] = $frecuenciaReinversion;
        $escenarioPreparado = $this->prepararEscenarioInversionParaVista($escenario);

        echo json_encode([
            'ok' => true,
            'capitalInicial' => floatval($escenarioPreparado['capital_inicial']),
            'aportacionMensual' => floatval($escenarioPreparado['aportacion_mensual']),
            'rentabilidadAnual' => floatval($escenarioPreparado['rentabilidad_anual']),
            'capitalTotalAportado' => floatval($escenarioPreparado['capital_total_aportado']),
            'valorFinalEstimado' => floatval($escenarioPreparado['valor_final_estimado']),
            'rendimientoEstimado' => floatval($escenarioPreparado['rendimiento_estimado']),
        ]);
    }

    public function eliminarEscenarioInversion(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['mensaje_error'] = 'No se recibió un escenario válido para eliminar.';
            $this->redirigirAlSimulador();
        }

        if (!EscenarioInversion::obtenerPorIdYUsuario($id, $usuario_id)) {
            $_SESSION['mensaje_error'] = 'No se encontró el escenario de inversión que quieres eliminar.';
            $this->redirigirAlSimulador();
        }

        $eliminado = EscenarioInversion::eliminarPorUsuario($id, $usuario_id);

        if (!$eliminado) {
            $_SESSION['mensaje_error'] = 'No se pudo eliminar el escenario de inversión. Inténtalo de nuevo.';
            $this->redirigirAlSimulador();
        }

        $_SESSION['mensaje_exitoso'] = 'Escenario eliminado.';
        $this->redirigirAlSimulador();
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
            $datos['importe_objetivo'],
            $datos['aportacion_mensual'],
            $datos['fecha_objetivo']
        );

        if (!$nuevaMeta) {
            $_SESSION['mensaje_error'] = 'No se pudo guardar la meta. Revisa los datos e inténtalo de nuevo.';
            $this->redirigirAlSimulador();
        }

        $_SESSION['mensaje_exitoso'] = 'Meta creada. Ya cuenta en tu capacidad mensual simulada.';
        $this->redirigirAlSimulador();
    }

    public function actualizarMetaAhorro(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $_SESSION['mensaje_error'] = 'Solo puedes editar el importe objetivo de una meta desde la propia card.';
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

        $_SESSION['mensaje_exitoso'] = 'Meta eliminada. Su aportación ya no cuenta en la capacidad usada.';
        $this->redirigirAlSimulador();
    }

    public function actualizarImporteMetaAjax(){
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
            return;
        }

        if(!isset($_SESSION['usuario_id'])){
            echo json_encode(['ok' => false, 'msg' => 'Sesión no válida']);
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $id = intval($_POST['id'] ?? 0);
        $importeObjetivo = $this->normalizarCantidad($_POST['importe_objetivo'] ?? null);

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'No se recibió una meta válida.']);
            return;
        }

        $meta = MetaAhorro::obtenerPorIdYUsuario($id, $usuario_id);

        if (!$meta) {
            echo json_encode(['ok' => false, 'msg' => 'No se encontró la meta que quieres actualizar.']);
            return;
        }

        if ($importeObjetivo === null || $importeObjetivo <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'El importe objetivo debe ser mayor que 0.']);
            return;
        }

        $importeObjetivo = round($importeObjetivo, 2);
        $actualizada = MetaAhorro::actualizarImporteObjetivo($id, $usuario_id, $importeObjetivo);

        if (!$actualizada) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo actualizar el importe objetivo.']);
            return;
        }

        $meta['importe_objetivo'] = $importeObjetivo;
        $meta['fecha_objetivo'] = null;
        $metaPreparada = $this->prepararMetaParaVista($meta);

        echo json_encode([
            'ok' => true,
            'importeObjetivo' => $importeObjetivo,
            'aportacionMensual' => floatval($metaPreparada['aportacion_mensual']),
            'plazoMesesEstimado' => $metaPreparada['plazo_meses_estimado'],
            'fechaFinalizacionEstimada' => $metaPreparada['fecha_finalizacion_estimada'],
        ]);
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

    public function crearInflacionSimulacion(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $resultado = $this->validarDatosInflacionSimulacion();

        if (!$resultado['ok']) {
            $_SESSION['mensaje_error'] = $resultado['mensaje'];
            $this->redirigirAlSimulador();
        }

        $datos = $resultado['datos'];
        $nuevaSimulacion = InflacionSimulacion::crear(
            $usuario_id,
            $datos['nombre'],
            $datos['cantidad_inicial'],
            $datos['inflacion_anual'],
            $datos['plazo_anios']
        );

        if (!$nuevaSimulacion) {
            $_SESSION['mensaje_error'] = 'No se pudo guardar la simulación de inflación. Revisa los datos e inténtalo de nuevo.';
            $this->redirigirAlSimulador();
        }

        $_SESSION['mensaje_exitoso'] = 'Simulación de inflación guardada. Puedes consultarla cuando quieras.';
        $this->redirigirAlSimulador();
    }

    public function actualizarInflacionSimulacion(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['mensaje_error'] = 'No se recibió una simulación válida para editar.';
            $this->redirigirAlSimulador();
        }

        if (!InflacionSimulacion::obtenerPorIdYUsuario($id, $usuario_id)) {
            $_SESSION['mensaje_error'] = 'No se encontró la simulación de inflación que quieres editar.';
            $this->redirigirAlSimulador();
        }

        $resultado = $this->validarDatosInflacionSimulacion();

        if (!$resultado['ok']) {
            $_SESSION['mensaje_error'] = $resultado['mensaje'];
            $this->redirigirAlSimulador();
        }

        $datos = $resultado['datos'];
        $actualizado = InflacionSimulacion::actualizar(
            $id,
            $usuario_id,
            $datos['nombre'],
            $datos['cantidad_inicial'],
            $datos['inflacion_anual'],
            $datos['plazo_anios']
        );

        if (!$actualizado) {
            $_SESSION['mensaje_error'] = 'No se pudo actualizar la simulación de inflación. Inténtalo de nuevo.';
            $this->redirigirAlSimulador();
        }

        $_SESSION['mensaje_exitoso'] = 'Simulación de inflación actualizada.';
        $this->redirigirAlSimulador();
    }

    public function actualizarInflacionSimulacionAjax(){
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
            return;
        }

        if(!isset($_SESSION['usuario_id'])){
            echo json_encode(['ok' => false, 'msg' => 'Sesión no válida']);
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $id = intval($_POST['id'] ?? 0);
        $campo = trim((string) ($_POST['campo'] ?? ''));
        $valor = $this->normalizarCantidad($_POST['valor'] ?? null);
        $camposPermitidos = ['cantidad_inicial', 'inflacion_anual', 'plazo_anios'];

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'No se recibió una simulación válida.']);
            return;
        }

        if (!in_array($campo, $camposPermitidos, true)) {
            echo json_encode(['ok' => false, 'msg' => 'No se recibió un campo editable válido.']);
            return;
        }

        if ($campo === 'plazo_anios') {
            $valor = intval($_POST['valor'] ?? 0);

            if ($valor <= 0) {
                echo json_encode(['ok' => false, 'msg' => 'El plazo en años debe ser mayor que 0.']);
                return;
            }
        } elseif ($campo === 'inflacion_anual') {
            if ($valor === null || $valor < 0) {
                echo json_encode(['ok' => false, 'msg' => 'La inflación anual debe ser igual o superior a 0.']);
                return;
            }
        } else {
            if ($valor === null || $valor <= 0) {
                echo json_encode(['ok' => false, 'msg' => 'La cantidad inicial debe ser mayor que 0.']);
                return;
            }
        }

        $simulacion = InflacionSimulacion::obtenerPorIdYUsuario($id, $usuario_id);

        if (!$simulacion) {
            echo json_encode(['ok' => false, 'msg' => 'No se encontró la simulación que quieres actualizar.']);
            return;
        }

        $simulacion[$campo] = $campo === 'plazo_anios' ? intval($valor) : round(floatval($valor), 2);

        $actualizado = InflacionSimulacion::actualizar(
            $id,
            $usuario_id,
            $simulacion['nombre'],
            round(floatval($simulacion['cantidad_inicial']), 2),
            round(floatval($simulacion['inflacion_anual']), 2),
            intval($simulacion['plazo_anios'])
        );

        if (!$actualizado) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo actualizar la simulación de inflación.']);
            return;
        }

        $simulacionPreparada = $this->prepararInflacionSimulacionParaVista($simulacion);

        echo json_encode([
            'ok' => true,
            'cantidadInicial' => floatval($simulacionPreparada['cantidad_inicial']),
            'inflacionAnual' => floatval($simulacionPreparada['inflacion_anual']),
            'plazoAnios' => intval($simulacionPreparada['plazo_anios']),
            'poderAdquisitivoFinal' => floatval($simulacionPreparada['poder_adquisitivo_final']),
            'perdidaEstimada' => floatval($simulacionPreparada['perdida_estimada']),
            'cantidadFuturaNecesaria' => floatval($simulacionPreparada['cantidad_futura_necesaria']),
            'diferenciaNecesaria' => floatval($simulacionPreparada['diferencia_necesaria']),
        ]);
    }

    public function eliminarInflacionSimulacion(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['mensaje_error'] = 'No se recibió una simulación válida para eliminar.';
            $this->redirigirAlSimulador();
        }

        if (!InflacionSimulacion::obtenerPorIdYUsuario($id, $usuario_id)) {
            $_SESSION['mensaje_error'] = 'No se encontró la simulación de inflación que quieres eliminar.';
            $this->redirigirAlSimulador();
        }

        $eliminado = InflacionSimulacion::eliminarPorUsuario($id, $usuario_id);

        if (!$eliminado) {
            $_SESSION['mensaje_error'] = 'No se pudo eliminar la simulación de inflación. Inténtalo de nuevo.';
            $this->redirigirAlSimulador();
        }

        $_SESSION['mensaje_exitoso'] = 'Simulación de inflación eliminada.';
        $this->redirigirAlSimulador();
    }

    public function crearCalculadoraHipoteca(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $resultado = $this->validarDatosCalculadoraHipoteca();

        if (!$resultado['ok']) {
            $_SESSION['mensaje_error'] = $resultado['mensaje'];
            $this->redirigirAlSimulador();
        }

        $datos = $resultado['datos'];
        $nuevaCalculadora = CalculadoraHipoteca::crear(
            $usuario_id,
            $datos['nombre'],
            $datos['importe_prestamo'],
            $datos['interes_anual'],
            $datos['plazo_anios']
        );

        if (!$nuevaCalculadora) {
            $_SESSION['mensaje_error'] = 'No se pudo guardar la calculadora de hipoteca. Revisa los datos e inténtalo de nuevo.';
            $this->redirigirAlSimulador();
        }

        $_SESSION['mensaje_exitoso'] = 'Calculadora de hipoteca guardada. Puedes consultarla cuando quieras.';
        $this->redirigirAlSimulador();
    }

    public function actualizarCalculadoraHipoteca(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['mensaje_error'] = 'No se recibió una calculadora válida para editar.';
            $this->redirigirAlSimulador();
        }

        if (!CalculadoraHipoteca::obtenerPorIdYUsuario($id, $usuario_id)) {
            $_SESSION['mensaje_error'] = 'No se encontró la calculadora de hipoteca que quieres editar.';
            $this->redirigirAlSimulador();
        }

        $resultado = $this->validarDatosCalculadoraHipoteca();

        if (!$resultado['ok']) {
            $_SESSION['mensaje_error'] = $resultado['mensaje'];
            $this->redirigirAlSimulador();
        }

        $datos = $resultado['datos'];
        $actualizado = CalculadoraHipoteca::actualizar(
            $id,
            $usuario_id,
            $datos['nombre'],
            $datos['importe_prestamo'],
            $datos['interes_anual'],
            $datos['plazo_anios']
        );

        if (!$actualizado) {
            $_SESSION['mensaje_error'] = 'No se pudo actualizar la calculadora de hipoteca. Inténtalo de nuevo.';
            $this->redirigirAlSimulador();
        }

        $_SESSION['mensaje_exitoso'] = 'Calculadora de hipoteca actualizada.';
        $this->redirigirAlSimulador();
    }

    public function actualizarCalculadoraHipotecaAjax(){
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
            return;
        }

        if(!isset($_SESSION['usuario_id'])){
            echo json_encode(['ok' => false, 'msg' => 'Sesión no válida']);
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $id = intval($_POST['id'] ?? 0);
        $campo = trim((string) ($_POST['campo'] ?? ''));
        $valor = $this->normalizarCantidad($_POST['valor'] ?? null);
        $camposPermitidos = ['importe_prestamo', 'interes_anual', 'plazo_anios'];

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'No se recibió una calculadora válida.']);
            return;
        }

        if (!in_array($campo, $camposPermitidos, true)) {
            echo json_encode(['ok' => false, 'msg' => 'No se recibió un campo editable válido.']);
            return;
        }

        if ($campo === 'plazo_anios') {
            $valor = intval($_POST['valor'] ?? 0);

            if ($valor <= 0) {
                echo json_encode(['ok' => false, 'msg' => 'El plazo en años debe ser mayor que 0.']);
                return;
            }
        } elseif ($campo === 'interes_anual') {
            if ($valor === null || $valor < 0) {
                echo json_encode(['ok' => false, 'msg' => 'El interés anual debe ser igual o superior a 0.']);
                return;
            }
        } else {
            if ($valor === null || $valor <= 0) {
                echo json_encode(['ok' => false, 'msg' => 'El importe del préstamo debe ser mayor que 0.']);
                return;
            }
        }

        $calculadora = CalculadoraHipoteca::obtenerPorIdYUsuario($id, $usuario_id);

        if (!$calculadora) {
            echo json_encode(['ok' => false, 'msg' => 'No se encontró la calculadora que quieres actualizar.']);
            return;
        }

        $calculadora[$campo] = $campo === 'plazo_anios' ? intval($valor) : round(floatval($valor), 2);

        $actualizado = CalculadoraHipoteca::actualizar(
            $id,
            $usuario_id,
            $calculadora['nombre'],
            round(floatval($calculadora['importe_prestamo']), 2),
            round(floatval($calculadora['interes_anual']), 2),
            intval($calculadora['plazo_anios'])
        );

        if (!$actualizado) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo actualizar la calculadora de hipoteca.']);
            return;
        }

        $calculadoraPreparada = $this->prepararCalculadoraHipotecaParaVista($calculadora);

        echo json_encode([
            'ok' => true,
            'importePrestamo' => floatval($calculadoraPreparada['importe_prestamo']),
            'interesAnual' => floatval($calculadoraPreparada['interes_anual']),
            'plazoAnios' => intval($calculadoraPreparada['plazo_anios']),
            'cuotaMensual' => floatval($calculadoraPreparada['cuota_mensual']),
            'totalIntereses' => floatval($calculadoraPreparada['total_intereses']),
            'totalPagado' => floatval($calculadoraPreparada['total_pagado']),
        ]);
    }

    public function eliminarCalculadoraHipoteca(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['mensaje_error'] = 'No se recibió una calculadora válida para eliminar.';
            $this->redirigirAlSimulador();
        }

        if (!CalculadoraHipoteca::obtenerPorIdYUsuario($id, $usuario_id)) {
            $_SESSION['mensaje_error'] = 'No se encontró la calculadora de hipoteca que quieres eliminar.';
            $this->redirigirAlSimulador();
        }

        $eliminado = CalculadoraHipoteca::eliminarPorUsuario($id, $usuario_id);

        if (!$eliminado) {
            $_SESSION['mensaje_error'] = 'No se pudo eliminar la calculadora de hipoteca. Inténtalo de nuevo.';
            $this->redirigirAlSimulador();
        }

        $_SESSION['mensaje_exitoso'] = 'Calculadora de hipoteca eliminada.';
        $this->redirigirAlSimulador();
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
        $modoCalculo = trim((string) ($_POST['modo_calculo'] ?? ''));
        $importeObjetivo = $this->normalizarCantidad($_POST['importe_objetivo'] ?? null);

        if ($nombre === '') {
            return $this->errorValidacion('El nombre de la meta es obligatorio.');
        }

        if (strlen($nombre) > 100) {
            return $this->errorValidacion('El nombre no puede superar 100 caracteres.');
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
                'No se pudo crear la meta. Necesita ' . formatearCantidadPHP($aportacionMensual) . ' €/mes y tienes ' .
                formatearCantidadPHP($capacidadDisponible) . ' €/mes sin asignar. Redistribuye el ahorro asignado a otras metas, reduce gastos para aumentar tu capacidad de ahorro o aumenta ingresos. Faltarían ' .
                formatearCantidadPHP($faltante) . ' €/mes.'
            );
        }

        return [
            'ok' => true,
            'datos' => [
                'nombre' => $nombre,
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

    private function validarDatosEscenarioInversion(): array{
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $capitalInicial = $this->normalizarCantidad($_POST['capital_inicial'] ?? null);
        $aportacionMensual = $this->normalizarCantidad($_POST['aportacion_mensual'] ?? null);
        $rentabilidadAnual = $this->normalizarCantidad($_POST['rentabilidad_anual'] ?? null);
        $plazoAnios = trim((string) ($_POST['plazo_anios'] ?? ''));
        $frecuenciaReinversion = trim((string) ($_POST['frecuencia_reinversion'] ?? ''));

        if ($nombre === '') {
            return $this->errorValidacion('El nombre del escenario de inversión es obligatorio.');
        }

        if (strlen($nombre) > 100) {
            return $this->errorValidacion('El nombre del escenario no puede superar 100 caracteres.');
        }

        if ($capitalInicial === null || $capitalInicial < 0) {
            return $this->errorValidacion('El capital inicial debe ser igual o superior a 0.');
        }

        if ($aportacionMensual === null || $aportacionMensual < 0) {
            return $this->errorValidacion('La aportación mensual debe ser igual o superior a 0.');
        }

        if ($rentabilidadAnual === null || $rentabilidadAnual < 0) {
            return $this->errorValidacion('La rentabilidad anual estimada debe ser igual o superior a 0.');
        }

        if ($plazoAnios === '' || !ctype_digit($plazoAnios) || intval($plazoAnios) <= 0) {
            return $this->errorValidacion('El plazo en años debe ser mayor que 0.');
        }

        if (!array_key_exists($frecuenciaReinversion, $this->frecuenciasReinversionPermitidas())) {
            return $this->errorValidacion('Selecciona una frecuencia de reinversión válida.');
        }

        return [
            'ok' => true,
            'datos' => [
                'nombre' => $nombre,
                'capital_inicial' => round($capitalInicial, 2),
                'aportacion_mensual' => round($aportacionMensual, 2),
                'rentabilidad_anual' => round($rentabilidadAnual, 2),
                'plazo_anios' => intval($plazoAnios),
                'frecuencia_reinversion' => $frecuenciaReinversion,
            ],
        ];
    }

    private function prepararEscenarioInversionParaVista($escenario): array{
        $capitalInicial = floatval($escenario['capital_inicial']);
        $aportacionMensual = floatval($escenario['aportacion_mensual']);
        $rentabilidadAnual = floatval($escenario['rentabilidad_anual']);
        $plazoAnios = intval($escenario['plazo_anios']);
        $frecuenciaReinversion = (string) ($escenario['frecuencia_reinversion'] ?? 'mensual');

        if (!array_key_exists($frecuenciaReinversion, $this->frecuenciasReinversionPermitidas())) {
            $frecuenciaReinversion = 'mensual';
        }

        $resultado = $this->calcularEscenarioInversion(
            $capitalInicial,
            $aportacionMensual,
            $rentabilidadAnual,
            $plazoAnios,
            $frecuenciaReinversion
        );

        $escenario['frecuencia_reinversion'] = $frecuenciaReinversion;
        $escenario['frecuencia_reinversion_label'] = $this->frecuenciasReinversionPermitidas()[$frecuenciaReinversion];
        $escenario['total_aportaciones_plazo'] = $resultado['total_aportaciones_plazo'];
        $escenario['capital_total_aportado'] = $resultado['capital_total_aportado'];
        $escenario['valor_final_estimado'] = $resultado['valor_final_estimado'];
        $escenario['rendimiento_estimado'] = $resultado['rendimiento_estimado'];
        $escenario['periodos_por_anio'] = $resultado['periodos_por_anio'];
        $escenario['meses_por_periodo'] = $resultado['meses_por_periodo'];

        return $escenario;
    }

    private function calcularEscenarioInversion($capitalInicial, $aportacionMensual, $rentabilidadAnual, $plazoAnios, $frecuenciaReinversion): array{
        $periodosPorAnio = $this->periodosPorAnio($frecuenciaReinversion);
        $mesesPorPeriodo = intdiv(12, $periodosPorAnio);
        $tasaPeriodo = $rentabilidadAnual > 0 ? ($rentabilidadAnual / 100) / $periodosPorAnio : 0;
        $meses = $plazoAnios * 12;
        $capital = $capitalInicial;

        for ($mes = 1; $mes <= $meses; $mes++) {
            $capital += $aportacionMensual;

            if ($tasaPeriodo > 0 && $mes % $mesesPorPeriodo === 0) {
                $capital += $capital * $tasaPeriodo;
            }
        }

        $totalAportacionesPlazo = $aportacionMensual * $meses;
        $capitalTotalAportado = $capitalInicial + $totalAportacionesPlazo;
        $valorFinalEstimado = $capital;
        $rendimientoEstimado = max(0, $valorFinalEstimado - $capitalTotalAportado);

        return [
            'total_aportaciones_plazo' => round($totalAportacionesPlazo, 2),
            'capital_total_aportado' => round($capitalTotalAportado, 2),
            'valor_final_estimado' => round($valorFinalEstimado, 2),
            'rendimiento_estimado' => round($rendimientoEstimado, 2),
            'periodos_por_anio' => $periodosPorAnio,
            'meses_por_periodo' => $mesesPorPeriodo,
        ];
    }

    private function frecuenciasReinversionPermitidas(): array{
        return [
            'mensual' => 'Mensual',
            'trimestral' => 'Trimestral',
            'semestral' => 'Semestral',
            'anual' => 'Anual',
        ];
    }

    private function periodosPorAnio($frecuenciaReinversion): int{
        return match ($frecuenciaReinversion) {
            'mensual' => 12,
            'trimestral' => 4,
            'semestral' => 2,
            'anual' => 1,
            default => 12,
        };
    }

    private function validarDatosInflacionSimulacion(): array{
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $cantidadInicial = $this->normalizarCantidad($_POST['cantidad_inicial'] ?? null);
        $inflacionAnual = $this->normalizarCantidad($_POST['inflacion_anual'] ?? null);
        $plazoAnios = trim((string) ($_POST['plazo_anios'] ?? ''));

        if ($nombre === '') {
            return $this->errorValidacion('El nombre de la simulación es obligatorio.');
        }

        if (strlen($nombre) > 100) {
            return $this->errorValidacion('El nombre no puede superar 100 caracteres.');
        }

        if ($cantidadInicial === null || $cantidadInicial <= 0) {
            return $this->errorValidacion('La cantidad inicial debe ser mayor que 0.');
        }

        if ($inflacionAnual === null || $inflacionAnual < 0) {
            return $this->errorValidacion('La inflación anual estimada debe ser igual o superior a 0.');
        }

        if ($plazoAnios === '' || !ctype_digit($plazoAnios) || intval($plazoAnios) <= 0) {
            return $this->errorValidacion('El plazo en años debe ser mayor que 0.');
        }

        return [
            'ok' => true,
            'datos' => [
                'nombre' => $nombre,
                'cantidad_inicial' => round($cantidadInicial, 2),
                'inflacion_anual' => round($inflacionAnual, 2),
                'plazo_anios' => intval($plazoAnios),
            ],
        ];
    }

    private function validarDatosCalculadoraHipoteca(): array{
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $importePrestamo = $this->normalizarCantidad($_POST['importe_prestamo'] ?? null);
        $interesAnual = $this->normalizarCantidad($_POST['interes_anual'] ?? null);
        $plazoAnios = trim((string) ($_POST['plazo_anios'] ?? ''));

        if ($nombre === '') {
            return $this->errorValidacion('El nombre de la calculadora es obligatorio.');
        }

        if (strlen($nombre) > 100) {
            return $this->errorValidacion('El nombre no puede superar 100 caracteres.');
        }

        if ($importePrestamo === null || $importePrestamo <= 0) {
            return $this->errorValidacion('El importe del préstamo debe ser mayor que 0.');
        }

        if ($interesAnual === null || $interesAnual < 0) {
            return $this->errorValidacion('El interés anual debe ser igual o superior a 0.');
        }

        if ($plazoAnios === '' || !ctype_digit($plazoAnios) || intval($plazoAnios) <= 0) {
            return $this->errorValidacion('El plazo en años debe ser mayor que 0.');
        }

        return [
            'ok' => true,
            'datos' => [
                'nombre' => $nombre,
                'importe_prestamo' => round($importePrestamo, 2),
                'interes_anual' => round($interesAnual, 2),
                'plazo_anios' => intval($plazoAnios),
            ],
        ];
    }

    private function prepararInflacionSimulacionParaVista($simulacion): array{
        $cantidadInicial = floatval($simulacion['cantidad_inicial']);
        $inflacionAnual = floatval($simulacion['inflacion_anual']);
        $plazoAnios = intval($simulacion['plazo_anios']);

        $resultado = $this->calcularInflacionSimulacion(
            $cantidadInicial,
            $inflacionAnual,
            $plazoAnios
        );

        $simulacion['poder_adquisitivo_final'] = $resultado['poder_adquisitivo_final'];
        $simulacion['perdida_estimada'] = $resultado['perdida_estimada'];
        $simulacion['cantidad_futura_necesaria'] = $resultado['cantidad_futura_necesaria'];
        $simulacion['diferencia_necesaria'] = $resultado['diferencia_necesaria'];

        return $simulacion;
    }

    private function prepararCalculadoraHipotecaParaVista($calculadora): array{
        $importePrestamo = floatval($calculadora['importe_prestamo']);
        $interesAnual = floatval($calculadora['interes_anual']);
        $plazoAnios = intval($calculadora['plazo_anios']);

        $resultado = $this->calcularCalculadoraHipoteca(
            $importePrestamo,
            $interesAnual,
            $plazoAnios
        );

        $calculadora['cuota_mensual'] = $resultado['cuota_mensual'];
        $calculadora['total_intereses'] = $resultado['total_intereses'];
        $calculadora['total_pagado'] = $resultado['total_pagado'];

        return $calculadora;
    }

    private function calcularInflacionSimulacion($cantidadInicial, $inflacionAnual, $plazoAnios): array{
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

    private function calcularCalculadoraHipoteca($importePrestamo, $interesAnual, $plazoAnios): array{
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
