<?php
require_once APP_PATH.'/models/Ingreso.php';
require_once APP_PATH.'/models/Gasto.php';

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

                if (($_ENV['APP_ENV'] ?? 'production') === 'local') {
                    $_SESSION['mensaje_error'] = 'Error de base de datos: ' . $e->getMessage();
                } else {
                    $_SESSION['mensaje_error'] = 'No se pudieron cargar los datos del panel. Inicie sesión nuevamente .';
                }

                header("Location: " . BASE_URL . "index.php?r=auth/login");
                exit;
            }
        }


        //Guaradmos el el mes selecionado para para tenerlo disponible
        $mesSeleccionado=$mesActual;

        //cargamos la vista del panel principal, de esta forma tendremos acceso a toda la información necesaria para mantenerla actualizada
        require APP_PATH.'/views/dashboard.php';
    }
    
}
