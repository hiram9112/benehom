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
            $obligatorios = Gasto::obtenerPorMes($usuario_id, "obligatorio", $fechaInicio, $fechaFin);
            $voluntarios = Gasto::obtenerPorMes($usuario_id, "voluntario", $fechaInicio, $fechaFin);
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
        $totalObligatorios= array_sum(array_column($obligatorios,"cantidad"));
        $totalVoluntarios= array_sum(array_column($voluntarios,"cantidad"));
        $gastosTotales=$totalObligatorios+$totalVoluntarios;
        
        //Calculamos la capacidad de ahorro real
        $capacidadAhorroReal=$totalIngresos-($totalVoluntarios+$totalObligatorios);

        
        //Enviamos respuesta
        echo json_encode([
            "ok"=>true,
            "data"=>[
            "ingresos"=>$totalIngresos,
            "obligatorios"=>$totalObligatorios,
            "voluntarios"=>$totalVoluntarios,
            "ahorroReal"=>$capacidadAhorroReal,
            "gastosTotales"=>$gastosTotales
            ]
        ]);
    }

    //Función para evolución de gráficos voluntarios y obligatorios
    public function gastos6m(){
        try{
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

            if(!isset($_POST['mes'])||!isset($_POST['tipo'])){
                echo json_encode(["ok"=>false,"msg"=>"Faltan datos"]);
                return;
            }

                
            //Recogemos los datos necesarios hacer la consulta a la base de datos
            $mesSeleccionado=$_POST['mes'];
            $tipo=$_POST['tipo'];
            $usuario_id=$_SESSION['usuario_id'];

            //Generamos lo los últimos 6 meses
            $meses=[];
            for($i=5;$i>=0;$i--){
                $mes=date("Y-m",strtotime("-$i months",strtotime($mesSeleccionado."-01")));
                $meses[]=$mes;
            }

            //Calculamos el total de gastos voluntarios de cada mes
            $valores=[];
            foreach($meses as $mes){
                $valores[]=Gasto::totalPorMes($usuario_id,$mes,$tipo);
            }

            echo json_encode([
                "ok"=>true,
                "data"=>[
                    "meses"=>$meses,
                    "valores"=>$valores
                ]
            ]);

            return;

        }catch(Exception $e){
            echo json_encode([
                "ok"=>false,
                "msg"=>"Error inesperado en el servidor",
                "error"=>$e->getMessage()
            ]);

            return;

        }     

    }

    public function ahorros6m(){
       
       
        try{
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

            if(!isset($_POST['mes'])){
                echo json_encode(["ok"=>false,"msg"=>"Faltan datos"]);
                return;
            }

            //Recogemos los datos recibidos
            $usuario_id=$_SESSION['usuario_id'];
            $mesSeleccionado=$_POST['mes'];

            //Generamos lo los últimos 6 meses
            $meses=[];
            for($i=5;$i>=0;$i--){
                $mes=date("Y-m",strtotime("-$i months",strtotime($mesSeleccionado."-01")));
                $meses[]=$mes;
            }

            //Efectuamos los cácluos para cada mes
            $capacidadAhorro=[];
            $ahorroReal=[];

            foreach($meses as $mes){

                //Establecemos el rango de fechas
                $fechaInicio=$mes."-01";
                $fechaFin=date("Y-m-t",strtotime($fechaInicio));

                //Hacemos las consultas al modelo
                $tIngresos=Ingreso::totalPorRango($usuario_id,$fechaInicio,$fechaFin);
                $tObligatorios=Gasto::totalPorRango($usuario_id,$fechaInicio,$fechaFin,"obligatorio");
                $tVoluntarios=Gasto::totalPorRango($usuario_id,$fechaInicio,$fechaFin,"voluntario");

                if($tIngresos===false){
                    echo json_encode([
                        "ok"=>false,
                        "msg"=>"Error consultando ingresos"
                    ]);
                    return;
                };
                if($tObligatorios===false){
                    echo json_encode([
                        "ok"=>false,
                        "msg"=>"Error consultando gastos obligatorios"
                    ]);
                    return;
                };
                if($tVoluntarios===false){
                    echo json_encode([
                        "ok"=>false,
                        "msg"=>"Error consultando gastos voluntarios"
                    ]);
                    return;
                };

                //Cálculos
                $cap=$tIngresos-$tObligatorios;
                $real=$tIngresos-($tObligatorios+$tVoluntarios);

                $capacidadAhorro[]=$cap;
                $ahorroReal[]=$real;
            } 

            echo json_encode([
                "ok"=>true,
                "data"=>[
                    "meses"=>$meses,
                    "capacidad"=>$capacidadAhorro,
                    "ahorroReal"=>$ahorroReal
                ]
            ]);

            return;

        }catch(Exception $e){

            echo json_encode([
                "ok"=>false,
                "msg"=>"Error inesperado en el servidor",
                "error"=>$e->getMessage()
            ]);

            return;
        }
           
    }
    
}