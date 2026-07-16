<?php
require_once APP_PATH.'/models/Ingreso.php';
require_once APP_PATH.'/models/Gasto.php';
require_once APP_PATH.'/services/ImportacionMensual.php';

class DashboardController{
    //Mostramos el panel principal
    public function index(){

        //Comprobamos que haya sesión activa
        if(!isset($_SESSION['usuario'])){
            header("Location: ".BASE_URL."index.php?r=auth/login");
            exit;
        }

        //Recuperamos el id del usuario actual
        $usuario_id=$_SESSION['usuario_id']??null;

        //Si recibimos un mes válido del selector lo usamos,de lo contrario cargamos el mes actual.
        $mesActual = date('Y-m');

        if (isset($_GET['mes']) && bh_mes_valido((string) $_GET['mes'])) {
            $mesActual = (string) $_GET['mes'];
        }

        $_SESSION['dashboard_mes_seleccionado'] = $mesActual;
        
        //Añadimos el día para obtener la fecha de inicio para la consulta
        $fechaInicio=$mesActual."-01";
        //Obtenemos el último día del mes actual
        $fechaFin=date("Y-m-t",strtotime($fechaInicio));

        
        //Obtenemos los datos filtrados por mes
        $ingresos=[];
        $gastosEsenciales=[];
        $gastosFlexibles=[];

        if($usuario_id){
            try {
                $ingresos = Ingreso::obtenerPorMes($usuario_id, $fechaInicio, $fechaFin);
                $gastosEsenciales = Gasto::obtenerPorMes($usuario_id, "esencial", $fechaInicio, $fechaFin);
                $gastosFlexibles = Gasto::obtenerPorMes($usuario_id, "flexible", $fechaInicio, $fechaFin);
            } catch (PDOException $e) {
                error_log('[DASHBOARD] Error de base de datos: ' . $e->getMessage());

                $_SESSION['mensaje_error'] = 'No se pudieron cargar los datos del panel. Inicia sesión nuevamente.';

                header("Location: " . BASE_URL . "index.php?r=auth/login");
                exit;
            }
        }


        //Guaradmos el el mes selecionado para para tenerlo disponible
        $mesSeleccionado=$mesActual;

        //cargamos la vista del panel principal, de esta forma tendremos acceso a toda la información necesaria para mantenerla actualizada
        require APP_PATH.'/views/dashboard.php';
    }

    public function importarMesAnteriorAjax(){

        if($_SERVER['REQUEST_METHOD']!=='POST'){
            echo json_encode([
                "ok"=>false,
                "msg"=>"Método no permitido"
            ]);
            return;
        }

        if(!isset($_SESSION['usuario_id'])){
            echo json_encode([
                "ok"=>false,
                "msg"=>"Sesión no válida"
            ]);
            return;
        }

        $usuario_id=$_SESSION['usuario_id'];
        $mesDestino=trim((string)($_POST['mes_destino']??''));

        if(!bh_mes_valido($mesDestino)){
            echo json_encode([
                "ok"=>false,
                "msg"=>"Mes no válido"
            ]);
            return;
        }

        $resultado = ImportacionMensual::importar($usuario_id, $mesDestino);

        if ($resultado['ok']) {
            $resumen = $resultado['resumen'];
            $ingresos = $resumen['ingresos'];
            $esenciales = $resumen['esenciales'];

            $textoIngresos = $ingresos === 1 ? '1 ingreso' : $ingresos . ' ingresos';
            $textoEsenciales = $esenciales === 1 ? '1 gasto esencial' : $esenciales . ' gastos esenciales';

            $mensaje = sprintf(
                'Se importaron %s y %s del mes anterior. Recuerda que los gastos flexibles no se importan: debes registrarlos manualmente.',
                $textoIngresos,
                $textoEsenciales
            );

            echo json_encode([
                "ok"=>true,
                "msg"=>$mensaje,
                "resumen"=>$resumen
            ]);
        } else {
            echo json_encode([
                "ok"=>false,
                "msg"=>$resultado['msg'],
                "codigo"=>$resultado['codigo'] ?? null
            ]);
        }
    }
    
}
