<?php
require_once APP_PATH.'/models/Ingreso.php';
require_once APP_PATH.'/models/Gasto.php';

class GraficosController{

    //Función para presupuesto general 

    public function estadoGeneral(){

        //Comprobaciones de seguridad, nos aseguramos que la petición sea POST y  haya una sesión activa
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
        
        //Mes seleccionado o mes actual
        $mesActual=$_POST['mes']??date('Y-m');        
        
        //Añadimos el día para obtener la fecha de inicio para la consulta
        $fechaInicio=$mesActual."-01";
        //Obtenemos el último día del mes actual
        $fechaFin=date("Y-m-t",strtotime($fechaInicio));

        //Obtenemos datos filtrados por mes
        try {
            $ingresos = Ingreso::obtenerPorMes($usuario_id, $fechaInicio, $fechaFin);
            $gastosEsenciales = Gasto::obtenerPorMes($usuario_id, "obligatorio", $fechaInicio, $fechaFin);
            $gastosFlexibles = Gasto::obtenerPorMes($usuario_id, "voluntario", $fechaInicio, $fechaFin);
        } catch (PDOException $e) {

            if (($_ENV['APP_ENV'] ?? 'production') === 'local') {
                echo json_encode([
                    "ok" => false,
                    "msg" => "Error de base de datos: " . $e->getMessage()
                ]);
            } else {
                echo json_encode([
                    "ok" => false,
                    "msg" => "No se pudieron obtener los datos del gráfico."
                ]);
            }
            return;
        }


        //Calculamos totales
        $totalIngresos= array_sum(array_column($ingresos,"cantidad"));
        $totalGastosEsenciales= array_sum(array_column($gastosEsenciales,"cantidad"));
        $totalGastosFlexibles= array_sum(array_column($gastosFlexibles,"cantidad"));
        $gastosTotales=$totalGastosEsenciales+$totalGastosFlexibles;
        
        //Calculamos el ahorro real del mes
        $ahorroReal=$totalIngresos-($totalGastosFlexibles+$totalGastosEsenciales);

        
        //Enviamos respuesta
        echo json_encode([
            "ok"=>true,
            "data"=>[
            "ingresos"=>$totalIngresos,
            "gastosEsenciales"=>$totalGastosEsenciales,
            "gastosFlexibles"=>$totalGastosFlexibles,
            "ahorroReal"=>$ahorroReal,
            "gastosTotales"=>$gastosTotales
            ]
        ]);
    }

    //Función para evolución de gráficos de gastos esenciales y flexibles
    public function gastos6m(){
        // Comprobaciones de seguridad, nos aseguramos que la petición sea POST y haya una sesión activa
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode([
                "ok" => false,
                "msg" => "Método no permitido"
            ]);
            return;
        }

        if (!isset($_SESSION['usuario_id'])) {
            echo json_encode([
                "ok" => false,
                "msg" => "Sesión no válida"
            ]);
            return;
        }

        if (!isset($_POST['mes']) || !isset($_POST['tipo'])) {
            echo json_encode([
                "ok" => false,
                "msg" => "Faltan datos"
            ]);
            return;
        }

        // Recogemos los datos necesarios para hacer la consulta a la base de datos
        $mesSeleccionado = $_POST['mes'];
        $tipo = $_POST['tipo'];
        $usuario_id = $_SESSION['usuario_id'];

        // Generamos los últimos 6 meses
        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $mes = date("Y-m", strtotime("-$i months", strtotime($mesSeleccionado . "-01")));
            $meses[] = $mes;
        }

        
        $valores = [];
        foreach ($meses as $mes) {

            $total = Gasto::totalPorMes($usuario_id, $mes, $tipo);

            // si falla la consulta, devolvemos error controlado
            if ($total === false) {
                echo json_encode([
                    "ok" => false,
                    "msg" => "No se pudieron obtener los datos del gráfico."
                ]);
                return;
            }

            $valores[] = $total;
        }

        // Enviamos respuesta correcta
        echo json_encode([
            "ok" => true,
            "data" => [
                "meses" => $meses,
                "valores" => $valores
            ]
        ]);

        return;

    }

    public function ahorros6m(){
        // Comprobaciones de seguridad, nos aseguramos que la petición sea POST y haya una sesión activa
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode([
                "ok" => false,
                "msg" => "Método no permitido"
            ]);
            return;
        }

        if (!isset($_SESSION['usuario_id'])) {
            echo json_encode([
                "ok" => false,
                "msg" => "Sesión no válida"
            ]);
            return;
        }

        if (!isset($_POST['mes'])) {
            echo json_encode([
                "ok" => false,
                "msg" => "Faltan datos"
            ]);
            return;
        }

        // Recogemos los datos recibidos
        $usuario_id = $_SESSION['usuario_id'];
        $mesSeleccionado = $_POST['mes'];

        // Generamos los últimos 6 meses
        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $mes = date("Y-m", strtotime("-$i months", strtotime($mesSeleccionado . "-01")));
            $meses[] = $mes;
        }

        // Efectuamos los cálculos para cada mes
        $ahorroPosible = [];
        $ahorroReal = [];

        foreach ($meses as $mes) {

            // Establecemos el rango de fechas
            $fechaInicio = $mes . "-01";
            $fechaFin = date("Y-m-t", strtotime($fechaInicio));

            // Hacemos las consultas al modelo
            $tIngresos = Ingreso::totalPorRango($usuario_id, $fechaInicio, $fechaFin);
            $tGastosEsenciales = Gasto::totalPorRango($usuario_id, $fechaInicio, $fechaFin, "obligatorio");
            $tGastosFlexibles = Gasto::totalPorRango($usuario_id, $fechaInicio, $fechaFin, "voluntario");

            // control explícito de errores del modelo
            if ($tIngresos === false) {
                echo json_encode([
                    "ok" => false,
                    "msg" => "Error consultando ingresos"
                ]);
                return;
            }

            if ($tGastosEsenciales === false) {
                echo json_encode([
                    "ok" => false,
                    "msg" => "Error consultando gastos esenciales"
                ]);
                return;
            }

            if ($tGastosFlexibles === false) {
                echo json_encode([
                    "ok" => false,
                    "msg" => "Error consultando gastos flexibles"
                ]);
                return;
            }

            // Cálculos
            $posible = $tIngresos - $tGastosEsenciales;
            $real = $tIngresos - ($tGastosEsenciales + $tGastosFlexibles);

            $ahorroPosible[] = $posible;
            $ahorroReal[] = $real;
        }

        // Enviamos respuesta
        echo json_encode([
            "ok" => true,
            "data" => [
                "meses" => $meses,
                "ahorroPosible" => $ahorroPosible,
                "ahorroReal" => $ahorroReal
            ]
        ]);

        return;
           
    }
    
}
