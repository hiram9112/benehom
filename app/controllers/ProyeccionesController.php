<?php
require_once APP_PATH . '/models/Ingreso.php';
require_once APP_PATH . '/models/Gasto.php';
require_once APP_PATH . '/models/MetaAhorro.php';
require_once APP_PATH . '/models/EscenarioInversion.php';
require_once APP_PATH . '/models/InflacionProyeccion.php';
require_once APP_PATH . '/models/CalculadoraHipoteca.php';

class ProyeccionesController {

    private const RENTABILIDAD_ANUAL_MAXIMA = 999.99;
    private const VALOR_ESTIMACION_INVERSION_MAXIMO = 9007199254740991;

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
        $gastosEsencialesMes = Gasto::totalPorRango($usuario_id, $fechaInicio, $fechaFin, 'esencial');
        $gastosFlexiblesMes = Gasto::totalPorRango($usuario_id, $fechaInicio, $fechaFin, 'flexible');
        $gastosFlexiblesPorCategoria = Gasto::totalesPorCategoriaYRango($usuario_id, $fechaInicio, $fechaFin, 'flexible');
        $ahorroAsignadoMetas = $this->totalAsignadoProyecciones($usuario_id);
        $metasAhorro = MetaAhorro::obtenerActivasPorUsuario($usuario_id);
        $escenariosInversion = EscenarioInversion::obtenerPorUsuario($usuario_id);
        $proyeccionesInflacion = InflacionProyeccion::obtenerPorUsuario($usuario_id);
        $calculadorasHipoteca = CalculadoraHipoteca::obtenerPorUsuario($usuario_id);

        if ($ingresosMes === false || $gastosEsencialesMes === false || $gastosFlexiblesMes === false) {
            $_SESSION['mensaje_error'] = 'No se pudieron cargar los datos de Proyecciones.';
            header("Location: " . BASE_URL . "index.php?r=dashboard/index");
            exit;
        }

        if ($ahorroAsignadoMetas === false) {
            $ahorroAsignadoMetas = 0;
            $avisoAhorroAsignado = 'No se pudo calcular el ahorro asignado a metas e inversiones. Se muestra como 0 hasta que estén disponibles.';
        } else {
            $avisoAhorroAsignado = '';
        }

        if ($metasAhorro === false) {
            $metasAhorro = [];
            $avisoAhorroAsignado = 'No se pudieron cargar tus metas de ahorro. Inténtalo de nuevo más tarde.';
        }

        if ($gastosFlexiblesPorCategoria === false) {
            $gastosFlexiblesPorCategoria = [];
            $avisoGastosFlexibles = 'No se pudieron cargar los gastos flexibles para proyectar reducciones.';
        } else {
            $avisoGastosFlexibles = '';
        }

        if ($escenariosInversion === false) {
            $escenariosInversion = [];
            $avisoEscenariosInversion = 'No se pudieron cargar tus escenarios de inversión. Inténtalo de nuevo más tarde.';
        } else {
            $avisoEscenariosInversion = '';
        }

        if ($proyeccionesInflacion === false) {
            $proyeccionesInflacion = [];
            $avisoProyeccionesInflacion = 'No se pudieron cargar las proyecciones de inflación. Inténtalo de nuevo más tarde.';
        } else {
            $avisoProyeccionesInflacion = '';
        }

        if ($calculadorasHipoteca === false) {
            $calculadorasHipoteca = [];
            $avisoCalculadorasHipoteca = 'No se pudieron cargar las calculadoras de hipoteca. Inténtalo de nuevo más tarde.';
        } else {
            $avisoCalculadorasHipoteca = '';
        }

        // Sugerencia: ahorro real del mes seleccionado (puede ser negativo). Es lo único
        // que cambia al cambiar de mes; no afecta a la capacidad de proyección.
        $ahorroRealMesSugerencia = $ingresosMes - $gastosEsencialesMes - $gastosFlexiblesMes;

        // Capacidad de proyección: valor global controlado por el usuario, desacoplado del mes.
        // Nunca baja de lo ya asignado a metas e inversiones guardadas.
        $ahorroMensualDisponible = $this->obtenerAhorroMensualConfigurado($usuario_id);

        $ahorroDisponibleMetas = max(0, $ahorroMensualDisponible - $ahorroAsignadoMetas);
        $metasAhorroPreparadas = array_map([$this, 'prepararMetaParaVista'], $metasAhorro);
        $escenariosInversionPreparados = array_map([$this, 'prepararEscenarioInversionParaVista'], $escenariosInversion);
        $proyeccionesInflacionPreparadas = array_map([$this, 'prepararInflacionProyeccionParaVista'], $proyeccionesInflacion);
        $calculadorasHipotecaPreparadas = array_map([$this, 'prepararCalculadoraHipotecaParaVista'], $calculadorasHipoteca);

        require_once APP_PATH . "/views/proyecciones.php";
    }

    public function simularCategoriaAjax(){
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
            return;
        }

        if(!isset($_SESSION['usuario_id'])){
            echo json_encode(['ok' => false, 'msg' => 'Sesión no válida']);
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $categoria = trim((string) ($_POST['categoria'] ?? ''));
        $mesSeleccionado = trim((string) ($_POST['mes'] ?? ''));

        if (!$this->mesValido($mesSeleccionado)) {
            echo json_encode(['ok' => false, 'msg' => 'Mes no válido']);
            return;
        }

        if ($categoria === '' || !gastoCategoriaPermitida('flexible', $categoria)) {
            echo json_encode(['ok' => false, 'msg' => 'Categoría no válida']);
            return;
        }

        $fechaInicio = date('Y-m-01', strtotime('-5 months', strtotime($mesSeleccionado . '-01')));
        $fechaFin = date('Y-m-t', strtotime($mesSeleccionado . '-01'));
        $totales = Gasto::totalesPorCategoriaYRango($usuario_id, $fechaInicio, $fechaFin, 'flexible');
        $mesesUsados = Gasto::mesesConMovimientosPorRango($usuario_id, $fechaInicio, $fechaFin, 'flexible');

        if ($totales === false || $mesesUsados === false) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudieron calcular los datos de la instantánea.']);
            return;
        }

        $totalCategoria = 0.0;

        foreach ($totales as $fila) {
            if (($fila['categoria'] ?? '') === $categoria) {
                $totalCategoria = floatval($fila['total']);
                break;
            }
        }

        if ($mesesUsados <= 0 || $totalCategoria <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'No hay gastos suficientes en esta categoría para simular.']);
            return;
        }

        $mediaMensual = round($totalCategoria / $mesesUsados, 2);
        $escenarios = [];

        foreach (['todo' => 1, 'mitad' => 0.5] as $aportacionClave => $factorAportacion) {
            $aportacionMensual = round($mediaMensual * $factorAportacion, 2);

            foreach ([3, 6, 9] as $rentabilidad) {
                foreach ([5, 10, 15] as $plazoAnios) {
                    $resultado = $this->calcularEscenarioInversion(
                        0,
                        $aportacionMensual,
                        $rentabilidad,
                        $plazoAnios,
                        'anual'
                    );

                    $escenarios[$aportacionClave][(string) $rentabilidad][(string) $plazoAnios] = [
                        'aportacionMensual' => $aportacionMensual,
                        'valorFinalEstimado' => $resultado['valor_final_estimado'],
                        'eurosGenerados' => $resultado['rendimiento_estimado'],
                        'totalAportado' => $resultado['capital_total_aportado'],
                    ];
                }
            }
        }

        echo json_encode([
            'ok' => true,
            'data' => [
                'categoria' => $categoria,
                'label' => formatearCategoria($categoria),
                'mediaMensual' => $mediaMensual,
                'mesesUsados' => $mesesUsados,
                'fechaInicio' => substr($fechaInicio, 0, 7),
                'fechaFin' => substr($fechaFin, 0, 7),
                'escenarios' => $escenarios,
            ],
        ]);
    }

    public function crearEscenarioInversion(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $resultado = $this->validarDatosEscenarioInversion($usuario_id);

        if (!$resultado['ok']) {
            $_SESSION['mensaje_error'] = $resultado['mensaje'];
            $this->redirigirAProyecciones();
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
            $this->redirigirAProyecciones();
        }

        $_SESSION['mensaje_exitoso'] = 'Escenario guardado con éxito. Puedes ajustar el capital inicial, la rentabilidad o la aportación mensual para ver cómo cambia.';
        $this->redirigirAProyecciones();
    }

    public function actualizarEscenarioInversion(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['mensaje_error'] = 'No se recibió un escenario válido para editar.';
            $this->redirigirAProyecciones();
        }

        if (!EscenarioInversion::obtenerPorIdYUsuario($id, $usuario_id)) {
            $_SESSION['mensaje_error'] = 'No se encontró el escenario de inversión que quieres editar.';
            $this->redirigirAProyecciones();
        }

        $resultado = $this->validarDatosEscenarioInversion($usuario_id, $id);

        if (!$resultado['ok']) {
            $_SESSION['mensaje_error'] = $resultado['mensaje'];
            $this->redirigirAProyecciones();
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
            $this->redirigirAProyecciones();
        }

        $_SESSION['mensaje_exitoso'] = 'Escenario actualizado.';
        $this->redirigirAProyecciones();
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
        $valorRaw = trim((string) ($_POST['valor'] ?? ''));
        $valor = $this->normalizarCantidad($_POST['valor'] ?? null);
        $camposPermitidos = ['capital_inicial', 'aportacion_mensual', 'rentabilidad_anual', 'plazo_anios'];

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'No se recibió un escenario válido.']);
            return;
        }

        if (!in_array($campo, $camposPermitidos, true)) {
            echo json_encode(['ok' => false, 'msg' => 'No se recibió un campo editable válido.']);
            return;
        }

        if ($campo === 'plazo_anios') {
            if ($valorRaw === '' || !ctype_digit($valorRaw) || intval($valorRaw) <= 0) {
                echo json_encode(['ok' => false, 'msg' => 'El plazo en años debe ser mayor que 0.']);
                return;
            }
        } elseif ($valor === null || $valor < 0) {
            echo json_encode(['ok' => false, 'msg' => 'Introduce un valor igual o superior a 0.']);
            return;
        }

        if ($campo === 'rentabilidad_anual' && $valor > self::RENTABILIDAD_ANUAL_MAXIMA) {
            echo json_encode(['ok' => false, 'msg' => $this->mensajeRentabilidadMaxima($valor)]);
            return;
        }

        $escenario = EscenarioInversion::obtenerPorIdYUsuario($id, $usuario_id);

        if (!$escenario) {
            echo json_encode(['ok' => false, 'msg' => 'No se encontró el escenario de inversión que quieres actualizar.']);
            return;
        }

        // La aportación mensual consume el presupuesto compartido con las metas.
        if ($campo === 'aportacion_mensual') {
            $nuevaAportacion = round($valor, 2);
            $capacidadMensual = $this->obtenerAhorroMensualConfigurado($usuario_id);
            $asignado = $this->totalAsignadoProyecciones($usuario_id, null, $id);

            if ($asignado === false) {
                echo json_encode(['ok' => false, 'msg' => 'No se pudo calcular la capacidad ya asignada a metas e inversiones.']);
                return;
            }

            $capacidadDisponible = max(0, $capacidadMensual - $asignado);

            if ($nuevaAportacion > $capacidadDisponible) {
                // El mensaje compara contra el ahorro libre real (sin contar lo que este
                // escenario ya tiene asignado), igual que el "Disponible" que muestra la UI.
                $libreParaAsignar = round(max(0, $capacidadMensual - $asignado - round((float) $escenario['aportacion_mensual'], 2)), 2);
                echo json_encode([
                    'ok' => false,
                    'msg' => $this->mensajeCapacidad('No se pudo actualizar la aportación', $nuevaAportacion, $libreParaAsignar),
                    'tipo' => 'capacidad',
                ]);
                return;
            }
        }

        $escenario[$campo] = $campo === 'plazo_anios' ? intval($valorRaw) : round($valor, 2);
        $frecuenciaReinversion = $escenario['frecuencia_reinversion'] ?? 'mensual';

        if (!array_key_exists($frecuenciaReinversion, $this->frecuenciasReinversionPermitidas())) {
            $frecuenciaReinversion = 'mensual';
        }

        $mensajeEstimacion = $this->validarEstimacionEscenarioInversion(
            round(floatval($escenario['capital_inicial']), 2),
            round(floatval($escenario['aportacion_mensual']), 2),
            round(floatval($escenario['rentabilidad_anual']), 2),
            intval($escenario['plazo_anios']),
            $frecuenciaReinversion
        );

        if ($mensajeEstimacion !== null) {
            echo json_encode(['ok' => false, 'msg' => $mensajeEstimacion]);
            return;
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
        $capacidad = $this->calcularCapacidadMetas($usuario_id);

        echo json_encode([
            'ok' => true,
            'capitalInicial' => floatval($escenarioPreparado['capital_inicial']),
            'aportacionMensual' => floatval($escenarioPreparado['aportacion_mensual']),
            'rentabilidadAnual' => floatval($escenarioPreparado['rentabilidad_anual']),
            'plazoAnios' => intval($escenarioPreparado['plazo_anios']),
            'capitalTotalAportado' => floatval($escenarioPreparado['capital_total_aportado']),
            'valorFinalEstimado' => floatval($escenarioPreparado['valor_final_estimado']),
            'rendimientoEstimado' => floatval($escenarioPreparado['rendimiento_estimado']),
            'roiPorcentaje' => floatval($escenarioPreparado['roi_porcentaje']),
            'ahorroAsignadoMetas' => $capacidad['asignado'],
            'ahorroDisponibleMetas' => $capacidad['disponible'],
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
            $this->redirigirAProyecciones();
        }

        if (!EscenarioInversion::obtenerPorIdYUsuario($id, $usuario_id)) {
            $_SESSION['mensaje_error'] = 'No se encontró el escenario de inversión que quieres eliminar.';
            $this->redirigirAProyecciones();
        }

        $eliminado = EscenarioInversion::eliminarPorUsuario($id, $usuario_id);

        if (!$eliminado) {
            $_SESSION['mensaje_error'] = 'No se pudo eliminar el escenario de inversión. Inténtalo de nuevo.';
            $this->redirigirAProyecciones();
        }

        $_SESSION['mensaje_exitoso'] = 'Escenario eliminado.';
        $this->redirigirAProyecciones();
    }

    public function crearMetaAhorro(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $resultado = $this->validarDatosMeta($usuario_id, null);

        if (!$resultado['ok']) {
            $_SESSION['mensaje_error'] = $resultado['mensaje'];
            $this->redirigirAProyecciones();
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
            $this->redirigirAProyecciones();
        }

        $_SESSION['mensaje_exitoso'] = 'Meta creada. Ya cuenta en tu capacidad mensual proyectada.';
        $this->redirigirAProyecciones();
    }

    public function actualizarMetaAhorro(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $_SESSION['mensaje_error'] = 'Solo puedes editar el importe objetivo de una meta desde la propia card.';
        $this->redirigirAProyecciones();
    }

    public function eliminarMetaAhorro(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['mensaje_error'] = 'No se recibió una meta válida para eliminar.';
            $this->redirigirAProyecciones();
        }

        if (!MetaAhorro::obtenerPorIdYUsuario($id, $usuario_id)) {
            $_SESSION['mensaje_error'] = 'No se encontró la meta que quieres eliminar.';
            $this->redirigirAProyecciones();
        }

        $eliminada = MetaAhorro::eliminarPorUsuario($id, $usuario_id);

        if (!$eliminada) {
            $_SESSION['mensaje_error'] = 'No se pudo eliminar la meta. Inténtalo de nuevo.';
            $this->redirigirAProyecciones();
        }

        $_SESSION['mensaje_exitoso'] = 'Meta eliminada. Su aportación ya no cuenta en la capacidad usada.';
        $this->redirigirAProyecciones();
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

        $ahorroMensual = trim((string) ($_POST['ahorro_mensual'] ?? ''));

        if ($ahorroMensual === '') {
            echo json_encode(['ok' => false, 'msg' => 'Introduce un ahorro mensual válido.']);
            return;
        }

        if (substr($ahorroMensual, 0, 1) === '-') {
            echo json_encode(['ok' => false, 'msg' => 'El ahorro mensual no puede ser negativo.']);
            return;
        }

        if (preg_match('/^\d+[,.]\d{3,}$/', $ahorroMensual)) {
            echo json_encode(['ok' => false, 'msg' => 'El ahorro mensual solo puede tener hasta 2 decimales.']);
            return;
        }

        if (!preg_match('/^\d+(?:[,.]\d{1,2})?$/', $ahorroMensual)) {
            echo json_encode(['ok' => false, 'msg' => 'Introduce un ahorro mensual válido.']);
            return;
        }

        $ahorroMensual = str_replace(',', '.', $ahorroMensual);

        if (!is_numeric($ahorroMensual)) {
            echo json_encode(['ok' => false, 'msg' => 'Introduce un ahorro mensual válido.']);
            return;
        }

        // Capacidad global desacoplada del mes: se guarda el valor indicado por el usuario,
        // pero el valor efectivo no baja de lo ya asignado a metas e inversiones.
        $ahorroMensualManual = round(floatval($ahorroMensual), 2);
        $_SESSION['proyecciones_ahorro_mensual_manual'] = $ahorroMensualManual;

        $ahorroAsignadoMetas = $this->totalAsignadoProyecciones($_SESSION['usuario_id']);

        if ($ahorroAsignadoMetas === false) {
            $ahorroAsignadoMetas = 0;
        }

        $ahorroMensualDisponible = round(max($ahorroMensualManual, (float) $ahorroAsignadoMetas), 2);

        echo json_encode([
            'ok' => true,
            'ahorroMensualDisponible' => $ahorroMensualDisponible,
            'ahorroAsignadoMetas' => $ahorroAsignadoMetas,
            'ahorroDisponibleMetas' => max(0, $ahorroMensualDisponible - $ahorroAsignadoMetas),
        ]);
    }

    public function crearInflacionProyeccion(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $resultado = $this->validarDatosInflacionProyeccion();

        if (!$resultado['ok']) {
            $_SESSION['mensaje_error'] = $resultado['mensaje'];
            $this->redirigirAProyecciones();
        }

        $datos = $resultado['datos'];
        $nuevaProyeccion = InflacionProyeccion::crear(
            $usuario_id,
            $datos['nombre'],
            $datos['cantidad_inicial'],
            $datos['inflacion_anual'],
            $datos['plazo_anios']
        );

        if (!$nuevaProyeccion) {
            $_SESSION['mensaje_error'] = 'No se pudo guardar la proyección de inflación. Revisa los datos e inténtalo de nuevo.';
            $this->redirigirAProyecciones();
        }

        $_SESSION['mensaje_exitoso'] = 'Proyección de inflación guardada. Puedes consultarla cuando quieras.';
        $this->redirigirAProyecciones();
    }

    public function actualizarInflacionProyeccion(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['mensaje_error'] = 'No se recibió una proyección válida para editar.';
            $this->redirigirAProyecciones();
        }

        if (!InflacionProyeccion::obtenerPorIdYUsuario($id, $usuario_id)) {
            $_SESSION['mensaje_error'] = 'No se encontró la proyección de inflación que quieres editar.';
            $this->redirigirAProyecciones();
        }

        $resultado = $this->validarDatosInflacionProyeccion();

        if (!$resultado['ok']) {
            $_SESSION['mensaje_error'] = $resultado['mensaje'];
            $this->redirigirAProyecciones();
        }

        $datos = $resultado['datos'];
        $actualizado = InflacionProyeccion::actualizar(
            $id,
            $usuario_id,
            $datos['nombre'],
            $datos['cantidad_inicial'],
            $datos['inflacion_anual'],
            $datos['plazo_anios']
        );

        if (!$actualizado) {
            $_SESSION['mensaje_error'] = 'No se pudo actualizar la proyección de inflación. Inténtalo de nuevo.';
            $this->redirigirAProyecciones();
        }

        $_SESSION['mensaje_exitoso'] = 'Proyección de inflación actualizada.';
        $this->redirigirAProyecciones();
    }

    public function actualizarInflacionProyeccionAjax(){
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
            echo json_encode(['ok' => false, 'msg' => 'No se recibió una proyección válida.']);
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

        $proyeccion = InflacionProyeccion::obtenerPorIdYUsuario($id, $usuario_id);

        if (!$proyeccion) {
            echo json_encode(['ok' => false, 'msg' => 'No se encontró la proyección que quieres actualizar.']);
            return;
        }

        $proyeccion[$campo] = $campo === 'plazo_anios' ? intval($valor) : round(floatval($valor), 2);

        $actualizado = InflacionProyeccion::actualizar(
            $id,
            $usuario_id,
            $proyeccion['nombre'],
            round(floatval($proyeccion['cantidad_inicial']), 2),
            round(floatval($proyeccion['inflacion_anual']), 2),
            intval($proyeccion['plazo_anios'])
        );

        if (!$actualizado) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo actualizar la proyección de inflación.']);
            return;
        }

        $proyeccionPreparada = $this->prepararInflacionProyeccionParaVista($proyeccion);

        echo json_encode([
            'ok' => true,
            'cantidadInicial' => floatval($proyeccionPreparada['cantidad_inicial']),
            'inflacionAnual' => floatval($proyeccionPreparada['inflacion_anual']),
            'plazoAnios' => intval($proyeccionPreparada['plazo_anios']),
            'poderAdquisitivoFinal' => floatval($proyeccionPreparada['poder_adquisitivo_final']),
            'perdidaEstimada' => floatval($proyeccionPreparada['perdida_estimada']),
            'cantidadFuturaNecesaria' => floatval($proyeccionPreparada['cantidad_futura_necesaria']),
            'diferenciaNecesaria' => floatval($proyeccionPreparada['diferencia_necesaria']),
        ]);
    }

    public function eliminarInflacionProyeccion(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['mensaje_error'] = 'No se recibió una proyección válida para eliminar.';
            $this->redirigirAProyecciones();
        }

        if (!InflacionProyeccion::obtenerPorIdYUsuario($id, $usuario_id)) {
            $_SESSION['mensaje_error'] = 'No se encontró la proyección de inflación que quieres eliminar.';
            $this->redirigirAProyecciones();
        }

        $eliminado = InflacionProyeccion::eliminarPorUsuario($id, $usuario_id);

        if (!$eliminado) {
            $_SESSION['mensaje_error'] = 'No se pudo eliminar la proyección de inflación. Inténtalo de nuevo.';
            $this->redirigirAProyecciones();
        }

        $_SESSION['mensaje_exitoso'] = 'Proyección de inflación eliminada.';
        $this->redirigirAProyecciones();
    }

    public function crearCalculadoraHipoteca(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $resultado = $this->validarDatosCalculadoraHipoteca();

        if (!$resultado['ok']) {
            $_SESSION['mensaje_error'] = $resultado['mensaje'];
            $this->redirigirAProyecciones();
        }

        $datos = $resultado['datos'];
        $nuevaCalculadora = CalculadoraHipoteca::crear(
            $usuario_id,
            $datos['nombre'],
            $datos['precio_inmueble'],
            $datos['porcentaje_financiacion'],
            $datos['importe_prestamo'],
            $datos['interes_anual'],
            $datos['plazo_anios']
        );

        if (!$nuevaCalculadora) {
            $_SESSION['mensaje_error'] = 'No se pudo guardar la calculadora de hipoteca. Revisa los datos e inténtalo de nuevo.';
            $this->redirigirAProyecciones();
        }

        $_SESSION['mensaje_exitoso'] = 'Nueva simulación de hipoteca creada.';
        $this->redirigirAProyecciones();
    }

    public function actualizarCalculadoraHipoteca(){
        if (!$this->peticionPostAutenticada()) {
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['mensaje_error'] = 'No se recibió una calculadora válida para editar.';
            $this->redirigirAProyecciones();
        }

        if (!CalculadoraHipoteca::obtenerPorIdYUsuario($id, $usuario_id)) {
            $_SESSION['mensaje_error'] = 'No se encontró la calculadora de hipoteca que quieres editar.';
            $this->redirigirAProyecciones();
        }

        $resultado = $this->validarDatosCalculadoraHipoteca();

        if (!$resultado['ok']) {
            $_SESSION['mensaje_error'] = $resultado['mensaje'];
            $this->redirigirAProyecciones();
        }

        $datos = $resultado['datos'];
        $actualizado = CalculadoraHipoteca::actualizar(
            $id,
            $usuario_id,
            $datos['nombre'],
            $datos['precio_inmueble'],
            $datos['porcentaje_financiacion'],
            $datos['importe_prestamo'],
            $datos['interes_anual'],
            $datos['plazo_anios']
        );

        if (!$actualizado) {
            $_SESSION['mensaje_error'] = 'No se pudo actualizar la calculadora de hipoteca. Inténtalo de nuevo.';
            $this->redirigirAProyecciones();
        }

        $_SESSION['mensaje_exitoso'] = 'Calculadora de hipoteca actualizada.';
        $this->redirigirAProyecciones();
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
        $camposPermitidos = ['precio_inmueble', 'porcentaje_financiacion', 'interes_anual', 'plazo_anios'];

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
        } elseif ($campo === 'porcentaje_financiacion') {
            if ($valor === null || $valor <= 0 || $valor > 100) {
                echo json_encode(['ok' => false, 'msg' => 'El porcentaje financiado debe estar entre 0 y 100.']);
                return;
            }
        } else {
            if ($valor === null || $valor <= 0) {
                echo json_encode(['ok' => false, 'msg' => 'El precio del inmueble debe ser mayor que 0.']);
                return;
            }
        }

        $calculadora = CalculadoraHipoteca::obtenerPorIdYUsuario($id, $usuario_id);

        if (!$calculadora) {
            echo json_encode(['ok' => false, 'msg' => 'No se encontró la calculadora que quieres actualizar.']);
            return;
        }

        $calculadora[$campo] = $campo === 'plazo_anios' ? intval($valor) : round(floatval($valor), 2);

        // El importe del préstamo es derivado: precio del inmueble x porcentaje financiado.
        $precioInmueble = round(floatval($calculadora['precio_inmueble']), 2);
        $porcentajeFinanciacion = round(floatval($calculadora['porcentaje_financiacion']), 2);
        $calculadora['importe_prestamo'] = round($precioInmueble * $porcentajeFinanciacion / 100, 2);

        $actualizado = CalculadoraHipoteca::actualizar(
            $id,
            $usuario_id,
            $calculadora['nombre'],
            $precioInmueble,
            $porcentajeFinanciacion,
            $calculadora['importe_prestamo'],
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
            'precioInmueble' => floatval($calculadoraPreparada['precio_inmueble']),
            'porcentajeFinanciacion' => floatval($calculadoraPreparada['porcentaje_financiacion']),
            'importePrestamo' => floatval($calculadoraPreparada['importe_prestamo']),
            'entradaNecesaria' => floatval($calculadoraPreparada['entrada_necesaria']),
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
            $this->redirigirAProyecciones();
        }

        if (!CalculadoraHipoteca::obtenerPorIdYUsuario($id, $usuario_id)) {
            $_SESSION['mensaje_error'] = 'No se encontró la calculadora de hipoteca que quieres eliminar.';
            $this->redirigirAProyecciones();
        }

        $eliminado = CalculadoraHipoteca::eliminarPorUsuario($id, $usuario_id);

        if (!$eliminado) {
            $_SESSION['mensaje_error'] = 'No se pudo eliminar la calculadora de hipoteca. Inténtalo de nuevo.';
            $this->redirigirAProyecciones();
        }

        $_SESSION['mensaje_exitoso'] = 'Calculadora de hipoteca eliminada.';
        $this->redirigirAProyecciones();
    }

    private function mesValido($mes): bool{
        return is_string($mes) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) === 1;
    }

    private function peticionPostAutenticada(): bool{
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            $_SESSION['mensaje_error'] = 'Método no permitido.';
            $this->redirigirAProyecciones();
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
        $aportacionesActuales = $this->totalAsignadoProyecciones($usuario_id, $meta_id, null);

        if ($aportacionesActuales === false) {
            return $this->errorValidacion('No se pudo calcular la capacidad ya asignada a metas e inversiones.');
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
            $mensajeCapacidad = $modoCalculo === 'fecha'
                ? $this->mensajeCapacidadFechaMeta('No se pudo crear la meta', $aportacionMensual, $capacidadDisponible)
                : $this->mensajeCapacidad('No se pudo crear la meta', $aportacionMensual, $capacidadDisponible);

            return $this->errorValidacion(
                $mensajeCapacidad,
                'capacidad'
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
        $asignado = $this->totalAsignadoProyecciones($usuario_id);
        $minimoAsignado = $asignado === false ? 0 : max(0, (float) $asignado);

        // Capacidad de proyección global desacoplada del mes: la fija el usuario,
        // pero como mínimo debe cubrir lo ya asignado en metas e inversiones.
        if (
            isset($_SESSION['proyecciones_ahorro_mensual_manual']) &&
            is_numeric($_SESSION['proyecciones_ahorro_mensual_manual'])
        ) {
            return round(max(floatval($_SESSION['proyecciones_ahorro_mensual_manual']), $minimoAsignado), 2);
        }

        return round($minimoAsignado, 2);
    }

    private function normalizarCantidad($valor): ?float{
        $cantidad = trim((string) $valor);
        $cantidad = str_replace(',', '.', $cantidad);

        if ($cantidad === '' || !is_numeric($cantidad)) {
            return null;
        }

        $numero = floatval($cantidad);

        return is_finite($numero) ? $numero : null;
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

    private function validarDatosEscenarioInversion($usuario_id, ?int $escenario_id = null): array{
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

        if ($rentabilidadAnual > self::RENTABILIDAD_ANUAL_MAXIMA) {
            return $this->errorValidacion($this->mensajeRentabilidadMaxima($rentabilidadAnual));
        }

        if ($plazoAnios === '' || !ctype_digit($plazoAnios) || intval($plazoAnios) <= 0) {
            return $this->errorValidacion('El plazo en años debe ser mayor que 0.');
        }

        if (!array_key_exists($frecuenciaReinversion, $this->frecuenciasReinversionPermitidas())) {
            return $this->errorValidacion('Selecciona una frecuencia de reinversión válida.');
        }

        $capitalInicial = round($capitalInicial, 2);
        $aportacionMensual = round($aportacionMensual, 2);
        $rentabilidadAnual = round($rentabilidadAnual, 2);

        $mensajeEstimacion = $this->validarEstimacionEscenarioInversion(
            $capitalInicial,
            $aportacionMensual,
            $rentabilidadAnual,
            intval($plazoAnios),
            $frecuenciaReinversion
        );

        if ($mensajeEstimacion !== null) {
            return $this->errorValidacion($mensajeEstimacion);
        }

        // La aportación mensual consume el mismo presupuesto que las metas.
        $capacidadMensual = $this->obtenerAhorroMensualConfigurado($usuario_id);
        $asignado = $this->totalAsignadoProyecciones($usuario_id, null, $escenario_id);

        if ($asignado === false) {
            return $this->errorValidacion('No se pudo calcular la capacidad ya asignada a metas e inversiones.');
        }

        $capacidadDisponible = max(0, $capacidadMensual - $asignado);

        if ($aportacionMensual > $capacidadDisponible) {
            // El mensaje muestra el disponible para asignar real (el mismo que la UI), no el
            // máximo del campo: al editar, este excluye lo que el propio escenario ya tiene.
            $libreParaAsignar = $this->calcularCapacidadMetas($usuario_id)['disponible'];
            return $this->errorValidacion(
                $this->mensajeCapacidad('No se pudo guardar el escenario', $aportacionMensual, $libreParaAsignar),
                'capacidad'
            );
        }

        return [
            'ok' => true,
            'datos' => [
                'nombre' => $nombre,
                'capital_inicial' => $capitalInicial,
                'aportacion_mensual' => $aportacionMensual,
                'rentabilidad_anual' => $rentabilidadAnual,
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
        $escenario['roi_porcentaje'] = $resultado['roi_porcentaje'];
        $escenario['periodos_por_anio'] = $resultado['periodos_por_anio'];
        $escenario['meses_por_periodo'] = $resultado['meses_por_periodo'];

        return $escenario;
    }

    private function calcularEscenarioInversion($capitalInicial, $aportacionMensual, $rentabilidadAnual, $plazoAnios, $frecuenciaReinversion): array{
        $periodosPorAnio = $this->periodosPorAnio($frecuenciaReinversion);
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

    private function validarEstimacionEscenarioInversion($capitalInicial, $aportacionMensual, $rentabilidadAnual, $plazoAnios, $frecuenciaReinversion): ?string{
        $resultado = $this->calcularEscenarioInversion(
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

    private function validarDatosInflacionProyeccion(): array{
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $cantidadInicial = $this->normalizarCantidad($_POST['cantidad_inicial'] ?? null);
        $inflacionAnual = $this->normalizarCantidad($_POST['inflacion_anual'] ?? null);
        $plazoAnios = trim((string) ($_POST['plazo_anios'] ?? ''));

        if ($nombre === '') {
            return $this->errorValidacion('El nombre de la proyección es obligatorio.');
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
        $precioInmueble = $this->normalizarCantidad($_POST['precio_inmueble'] ?? null);
        $porcentajeFinanciacion = $this->normalizarCantidad($_POST['porcentaje_financiacion'] ?? null);
        $interesAnual = $this->normalizarCantidad($_POST['interes_anual'] ?? null);
        $plazoAnios = trim((string) ($_POST['plazo_anios'] ?? ''));

        if ($nombre === '') {
            return $this->errorValidacion('El nombre de la calculadora es obligatorio.');
        }

        if (strlen($nombre) > 100) {
            return $this->errorValidacion('El nombre no puede superar 100 caracteres.');
        }

        if ($precioInmueble === null || $precioInmueble <= 0) {
            return $this->errorValidacion('El precio del inmueble debe ser mayor que 0.');
        }

        if ($porcentajeFinanciacion === null || $porcentajeFinanciacion <= 0 || $porcentajeFinanciacion > 100) {
            return $this->errorValidacion('El porcentaje financiado debe estar entre 0 y 100.');
        }

        if ($interesAnual === null || $interesAnual < 0) {
            return $this->errorValidacion('El interés anual debe ser igual o superior a 0.');
        }

        if ($plazoAnios === '' || !ctype_digit($plazoAnios) || intval($plazoAnios) <= 0) {
            return $this->errorValidacion('El plazo en años debe ser mayor que 0.');
        }

        $precioInmueble = round($precioInmueble, 2);
        $porcentajeFinanciacion = round($porcentajeFinanciacion, 2);
        $importePrestamo = round($precioInmueble * $porcentajeFinanciacion / 100, 2);

        return [
            'ok' => true,
            'datos' => [
                'nombre' => $nombre,
                'precio_inmueble' => $precioInmueble,
                'porcentaje_financiacion' => $porcentajeFinanciacion,
                'importe_prestamo' => $importePrestamo,
                'interes_anual' => round($interesAnual, 2),
                'plazo_anios' => intval($plazoAnios),
            ],
        ];
    }

    private function prepararInflacionProyeccionParaVista($proyeccion): array{
        $cantidadInicial = floatval($proyeccion['cantidad_inicial']);
        $inflacionAnual = floatval($proyeccion['inflacion_anual']);
        $plazoAnios = intval($proyeccion['plazo_anios']);

        $resultado = $this->calcularInflacionProyeccion(
            $cantidadInicial,
            $inflacionAnual,
            $plazoAnios
        );

        $proyeccion['poder_adquisitivo_final'] = $resultado['poder_adquisitivo_final'];
        $proyeccion['perdida_estimada'] = $resultado['perdida_estimada'];
        $proyeccion['cantidad_futura_necesaria'] = $resultado['cantidad_futura_necesaria'];
        $proyeccion['diferencia_necesaria'] = $resultado['diferencia_necesaria'];

        return $proyeccion;
    }

    private function prepararCalculadoraHipotecaParaVista($calculadora): array{
        $precioInmueble = floatval($calculadora['precio_inmueble']);
        $importePrestamo = floatval($calculadora['importe_prestamo']);
        $interesAnual = floatval($calculadora['interes_anual']);
        $plazoAnios = intval($calculadora['plazo_anios']);

        $resultado = $this->calcularCalculadoraHipoteca(
            $importePrestamo,
            $interesAnual,
            $plazoAnios
        );

        // La entrada es el dinero que aporta el usuario: precio del inmueble - importe financiado.
        $calculadora['entrada_necesaria'] = round($precioInmueble - $importePrestamo, 2);
        $calculadora['cuota_mensual'] = $resultado['cuota_mensual'];
        $calculadora['total_intereses'] = $resultado['total_intereses'];
        $calculadora['total_pagado'] = $resultado['total_pagado'];

        return $calculadora;
    }

    private function calcularInflacionProyeccion($cantidadInicial, $inflacionAnual, $plazoAnios): array{
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

    private function errorValidacion($mensaje, string $tipo = 'error'): array{
        return [
            'ok' => false,
            'mensaje' => $mensaje,
            'tipo' => $tipo,
        ];
    }

    /**
     * Comprueba que la petición AJAX es un POST autenticado. Devuelve el
     * id de usuario o null (habiendo emitido ya el JSON de error).
     */
    private function peticionAjaxAutenticada(): ?int{
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
            return null;
        }

        if (!isset($_SESSION['usuario_id'])) {
            echo json_encode(['ok' => false, 'msg' => 'Sesión no válida']);
            return null;
        }

        return (int) $_SESSION['usuario_id'];
    }

    private function gastosFlexiblesPorCategoriaActual($usuario_id): array{
        $mesSeleccionado = $_SESSION['dashboard_mes_seleccionado'] ?? date('Y-m');

        if (!$this->mesValido($mesSeleccionado)) {
            $mesSeleccionado = date('Y-m');
        }

        $fechaInicio = $mesSeleccionado . '-01';
        $fechaFin = date('Y-m-t', strtotime($fechaInicio));
        $categorias = Gasto::totalesPorCategoriaYRango($usuario_id, $fechaInicio, $fechaFin, 'flexible');

        return $categorias === false ? [] : $categorias;
    }

    private function calcularCapacidadMetas($usuario_id): array{
        $capacidadMensual = $this->obtenerAhorroMensualConfigurado($usuario_id);
        $asignado = $this->totalAsignadoProyecciones($usuario_id);

        if ($asignado === false) {
            $asignado = 0;
        }

        return [
            'asignado' => round((float) $asignado, 2),
            'disponible' => round(max(0, $capacidadMensual - $asignado), 2),
        ];
    }

    /**
     * Presupuesto mensual ya asignado: aportaciones de metas + escenarios de
     * inversión (presupuesto compartido). Permite excluir una meta o un escenario
     * concretos cuando se está editando uno de ellos. Devuelve false si alguna
     * consulta falla.
     *
     * @return float|false
     */
    private function totalAsignadoProyecciones($usuario_id, ?int $excluirMeta = null, ?int $excluirEscenario = null){
        $metas = $excluirMeta
            ? MetaAhorro::totalAportacionesActivasExcluyendoMeta($usuario_id, $excluirMeta)
            : MetaAhorro::totalAportacionesActivas($usuario_id);

        $inversiones = $excluirEscenario
            ? EscenarioInversion::totalAportacionesExcluyendo($usuario_id, $excluirEscenario)
            : EscenarioInversion::totalAportaciones($usuario_id);

        if ($metas === false || $inversiones === false) {
            return false;
        }

        return round((float) $metas + (float) $inversiones, 2);
    }

    /**
     * Mensaje homogéneo cuando una aportación supera el presupuesto disponible.
     * Se reutiliza al crear meta, crear/editar inversión, etc.
     */
    private function mensajeCapacidad(string $lead, float $necesita, float $disponible): string{
        return $lead . ': ' . formatearCantidadPHP($necesita) . ' €/mes supera tu ahorro mensual simulado de ' .
            formatearCantidadPHP($disponible) . ' €/mes. Reduce la aportación o aumenta tu ahorro mensual simulado.';
    }

    private function mensajeCapacidadFechaMeta(string $lead, float $necesita, float $disponible): string{
        return $lead . ': para cumplir ese plazo necesitas ' . formatearCantidadPHP($necesita) . ' €/mes, pero solo tienes ' .
            formatearCantidadPHP($disponible) . ' €/mes disponibles. Aumenta tu ahorro mensual simulado o elige una fecha más lejana.';
    }

    private function mensajeRentabilidadMaxima(float $rentabilidad): string{
        return 'La rentabilidad anual estimada de ' . formatearCantidadPHP($rentabilidad) . '% supera el máximo permitido de ' .
            formatearCantidadPHP(self::RENTABILIDAD_ANUAL_MAXIMA) . '%. Introduce una rentabilidad menor para poder guardar y calcular el escenario.';
    }

    public function crearMetaAhorroAjax(){
        $usuario_id = $this->peticionAjaxAutenticada();

        if ($usuario_id === null) {
            return;
        }

        $resultado = $this->validarDatosMeta($usuario_id, null);

        if (!$resultado['ok']) {
            echo json_encode(['ok' => false, 'msg' => $resultado['mensaje'], 'tipo' => $resultado['tipo'] ?? 'error']);
            return;
        }

        $datos = $resultado['datos'];
        $id = MetaAhorro::crear(
            $usuario_id,
            $datos['nombre'],
            $datos['importe_objetivo'],
            $datos['aportacion_mensual'],
            $datos['fecha_objetivo']
        );

        if (!$id) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo guardar la meta. Revisa los datos e inténtalo de nuevo.']);
            return;
        }

        $meta = MetaAhorro::obtenerPorIdYUsuario($id, $usuario_id);

        if (!$meta) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo cargar la meta creada.']);
            return;
        }

        require_once APP_PATH . '/views/partials/proyecciones-cards.php';
        $cardHtml = bh_render_meta_card(
            $this->prepararMetaParaVista($meta),
            $this->gastosFlexiblesPorCategoriaActual($usuario_id)
        );
        $capacidad = $this->calcularCapacidadMetas($usuario_id);
        $metas = MetaAhorro::obtenerActivasPorUsuario($usuario_id);

        echo json_encode([
            'ok' => true,
            'msg' => 'Meta creada. Ya cuenta en tu capacidad mensual proyectada.',
            'cardHtml' => $cardHtml,
            'count' => is_array($metas) ? count($metas) : 0,
            'ahorroAsignadoMetas' => $capacidad['asignado'],
            'ahorroDisponibleMetas' => $capacidad['disponible'],
        ]);
    }

    public function eliminarMetaAhorroAjax(){
        $usuario_id = $this->peticionAjaxAutenticada();

        if ($usuario_id === null) {
            return;
        }

        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'No se recibió una meta válida para eliminar.']);
            return;
        }

        if (!MetaAhorro::obtenerPorIdYUsuario($id, $usuario_id)) {
            echo json_encode(['ok' => false, 'msg' => 'No se encontró la meta que quieres eliminar.']);
            return;
        }

        if (!MetaAhorro::eliminarPorUsuario($id, $usuario_id)) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo eliminar la meta. Inténtalo de nuevo.']);
            return;
        }

        $capacidad = $this->calcularCapacidadMetas($usuario_id);
        $metas = MetaAhorro::obtenerActivasPorUsuario($usuario_id);

        echo json_encode([
            'ok' => true,
            'msg' => 'Meta eliminada. Su aportación ya no cuenta en la capacidad usada.',
            'count' => is_array($metas) ? count($metas) : 0,
            'ahorroAsignadoMetas' => $capacidad['asignado'],
            'ahorroDisponibleMetas' => $capacidad['disponible'],
        ]);
    }

    public function crearEscenarioInversionAjax(){
        $usuario_id = $this->peticionAjaxAutenticada();

        if ($usuario_id === null) {
            return;
        }

        $resultado = $this->validarDatosEscenarioInversion($usuario_id);

        if (!$resultado['ok']) {
            echo json_encode(['ok' => false, 'msg' => $resultado['mensaje'], 'tipo' => $resultado['tipo'] ?? 'error']);
            return;
        }

        $datos = $resultado['datos'];
        $id = EscenarioInversion::crear(
            $usuario_id,
            $datos['nombre'],
            $datos['capital_inicial'],
            $datos['aportacion_mensual'],
            $datos['rentabilidad_anual'],
            $datos['plazo_anios'],
            $datos['frecuencia_reinversion']
        );

        if (!$id) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo guardar el escenario de inversión. Revisa los datos e inténtalo de nuevo.']);
            return;
        }

        $escenario = EscenarioInversion::obtenerPorIdYUsuario($id, $usuario_id);

        if (!$escenario) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo cargar el escenario creado.']);
            return;
        }

        require_once APP_PATH . '/views/partials/proyecciones-cards.php';
        $cardHtml = bh_render_escenario_inversion_card($this->prepararEscenarioInversionParaVista($escenario));
        $escenarios = EscenarioInversion::obtenerPorUsuario($usuario_id);
        $capacidad = $this->calcularCapacidadMetas($usuario_id);

        echo json_encode([
            'ok' => true,
            'msg' => 'Escenario guardado con éxito. Puedes ajustar el capital inicial, la rentabilidad o la aportación mensual para ver cómo cambia.',
            'cardHtml' => $cardHtml,
            'count' => is_array($escenarios) ? count($escenarios) : 0,
            'ahorroAsignadoMetas' => $capacidad['asignado'],
            'ahorroDisponibleMetas' => $capacidad['disponible'],
        ]);
    }

    public function eliminarEscenarioInversionAjax(){
        $usuario_id = $this->peticionAjaxAutenticada();

        if ($usuario_id === null) {
            return;
        }

        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'No se recibió un escenario válido para eliminar.']);
            return;
        }

        if (!EscenarioInversion::obtenerPorIdYUsuario($id, $usuario_id)) {
            echo json_encode(['ok' => false, 'msg' => 'No se encontró el escenario de inversión que quieres eliminar.']);
            return;
        }

        if (!EscenarioInversion::eliminarPorUsuario($id, $usuario_id)) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo eliminar el escenario de inversión. Inténtalo de nuevo.']);
            return;
        }

        $escenarios = EscenarioInversion::obtenerPorUsuario($usuario_id);
        $capacidad = $this->calcularCapacidadMetas($usuario_id);

        echo json_encode([
            'ok' => true,
            'msg' => 'Escenario eliminado.',
            'count' => is_array($escenarios) ? count($escenarios) : 0,
            'ahorroAsignadoMetas' => $capacidad['asignado'],
            'ahorroDisponibleMetas' => $capacidad['disponible'],
        ]);
    }

    public function crearInflacionProyeccionAjax(){
        $usuario_id = $this->peticionAjaxAutenticada();

        if ($usuario_id === null) {
            return;
        }

        $resultado = $this->validarDatosInflacionProyeccion();

        if (!$resultado['ok']) {
            echo json_encode(['ok' => false, 'msg' => $resultado['mensaje']]);
            return;
        }

        $datos = $resultado['datos'];
        $id = InflacionProyeccion::crear(
            $usuario_id,
            $datos['nombre'],
            $datos['cantidad_inicial'],
            $datos['inflacion_anual'],
            $datos['plazo_anios']
        );

        if (!$id) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo guardar la proyección de inflación. Revisa los datos e inténtalo de nuevo.']);
            return;
        }

        $proyeccion = InflacionProyeccion::obtenerPorIdYUsuario($id, $usuario_id);

        if (!$proyeccion) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo cargar la proyección creada.']);
            return;
        }

        require_once APP_PATH . '/views/partials/proyecciones-cards.php';
        $cardHtml = bh_render_inflacion_card($this->prepararInflacionProyeccionParaVista($proyeccion));
        $proyecciones = InflacionProyeccion::obtenerPorUsuario($usuario_id);

        echo json_encode([
            'ok' => true,
            'msg' => 'Proyección de inflación guardada. Puedes consultarla cuando quieras.',
            'cardHtml' => $cardHtml,
            'count' => is_array($proyecciones) ? count($proyecciones) : 0,
        ]);
    }

    public function eliminarInflacionProyeccionAjax(){
        $usuario_id = $this->peticionAjaxAutenticada();

        if ($usuario_id === null) {
            return;
        }

        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'No se recibió una proyección válida para eliminar.']);
            return;
        }

        if (!InflacionProyeccion::obtenerPorIdYUsuario($id, $usuario_id)) {
            echo json_encode(['ok' => false, 'msg' => 'No se encontró la proyección de inflación que quieres eliminar.']);
            return;
        }

        if (!InflacionProyeccion::eliminarPorUsuario($id, $usuario_id)) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo eliminar la proyección de inflación. Inténtalo de nuevo.']);
            return;
        }

        $proyecciones = InflacionProyeccion::obtenerPorUsuario($usuario_id);

        echo json_encode([
            'ok' => true,
            'msg' => 'Proyección de inflación eliminada.',
            'count' => is_array($proyecciones) ? count($proyecciones) : 0,
        ]);
    }

    public function crearCalculadoraHipotecaAjax(){
        $usuario_id = $this->peticionAjaxAutenticada();

        if ($usuario_id === null) {
            return;
        }

        $resultado = $this->validarDatosCalculadoraHipoteca();

        if (!$resultado['ok']) {
            echo json_encode(['ok' => false, 'msg' => $resultado['mensaje']]);
            return;
        }

        $datos = $resultado['datos'];
        $id = CalculadoraHipoteca::crear(
            $usuario_id,
            $datos['nombre'],
            $datos['precio_inmueble'],
            $datos['porcentaje_financiacion'],
            $datos['importe_prestamo'],
            $datos['interes_anual'],
            $datos['plazo_anios']
        );

        if (!$id) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo guardar la calculadora de hipoteca. Revisa los datos e inténtalo de nuevo.']);
            return;
        }

        $calculadora = CalculadoraHipoteca::obtenerPorIdYUsuario($id, $usuario_id);

        if (!$calculadora) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo cargar la calculadora creada.']);
            return;
        }

        require_once APP_PATH . '/views/partials/proyecciones-cards.php';
        $cardHtml = bh_render_calculadora_hipoteca_card($this->prepararCalculadoraHipotecaParaVista($calculadora));
        $calculadoras = CalculadoraHipoteca::obtenerPorUsuario($usuario_id);

        echo json_encode([
            'ok' => true,
            'msg' => 'Nueva simulación de hipoteca creada.',
            'cardHtml' => $cardHtml,
            'count' => is_array($calculadoras) ? count($calculadoras) : 0,
        ]);
    }

    public function eliminarCalculadoraHipotecaAjax(){
        $usuario_id = $this->peticionAjaxAutenticada();

        if ($usuario_id === null) {
            return;
        }

        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'No se recibió una calculadora válida para eliminar.']);
            return;
        }

        if (!CalculadoraHipoteca::obtenerPorIdYUsuario($id, $usuario_id)) {
            echo json_encode(['ok' => false, 'msg' => 'No se encontró la calculadora de hipoteca que quieres eliminar.']);
            return;
        }

        if (!CalculadoraHipoteca::eliminarPorUsuario($id, $usuario_id)) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo eliminar la calculadora de hipoteca. Inténtalo de nuevo.']);
            return;
        }

        $calculadoras = CalculadoraHipoteca::obtenerPorUsuario($usuario_id);

        echo json_encode([
            'ok' => true,
            'msg' => 'Calculadora de hipoteca eliminada.',
            'count' => is_array($calculadoras) ? count($calculadoras) : 0,
        ]);
    }

    private function redirigirAProyecciones(): void{
        header("Location: " . BASE_URL . "index.php?r=proyecciones/index");
        exit;
    }

}
