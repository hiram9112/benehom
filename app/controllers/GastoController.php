<?php
require_once APP_PATH.'/models/Gasto.php';
class GastoController{

    //Método para manejar la petición AJAX de agregar un gasto esencial
    public function agregarGastoEsencialAjax(){

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

        //Recuperamos datos enviados desde el formulario
        $usuario_id=$_SESSION['usuario_id'];
        $categoria=trim($_POST['categoria_gasto_esencial']??'');
        $cantidad=trim($_POST['cantidad_gasto_esencial']??'');
        $mesSeleccionado=trim((string)($_POST['mes_seleccionado']??''));

        if(!bh_mes_valido($mesSeleccionado)){
            echo json_encode([
                "ok"=>false,
                "msg"=>"Mes no válido"
            ]);
            return;
        }

        $fecha=$mesSeleccionado."-01";
        $tipo="esencial";

        //Validación básica de los datos
        if($categoria ===''||$cantidad===''||!is_numeric($cantidad)||$cantidad<=0){
            echo json_encode([
                "ok"=>false,
                "msg"=>"Datos inválidos"
            ]);
            return;
        }

        if(!gastoCategoriaPermitida($tipo,$categoria)){
            echo json_encode([
                "ok"=>false,
                "msg"=>"Selecciona una categoría válida."
            ]);
            return;
        }

        //Insertamos en la base de datos el nuevo gasto esencial(devolverá el ID del recién creado gasto)
        $nuevoID=Gasto::agregarGasto($usuario_id,$tipo,$categoria,$cantidad,$fecha);

        //Si falla la inserción
        if(!$nuevoID){
            echo json_encode([
                "ok"=>false,
                "msg"=>"Error al guardar en la base de datos"
            ]);
            return;
        }

        //Enviamos respueesta exitosa en formato JSON
        echo json_encode([
            "ok"=>true,
            "gasto_esencial"=>[
                "id"=>$nuevoID,
                "categoria"=>$categoria,
                "cantidad"=>$cantidad
            ]

            ]);
    }

    //Método para manejar la petición AJAX de eliminar gasto
    public function eliminarGastoAjax(){

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

        //Recogemos el id enviado y el usuario autenticado
        $usuario_id=$_SESSION['usuario_id'];
        $id=isset($_POST['id']) ? intval($_POST['id']) : 0;

        if($id<=0){
            echo json_encode(['ok'=>false,'msg'=>'ID no recibido']);
            return;
        }

        //Ejecutamos la función correspondiente, que valida propiedad por usuario_id
        $resultado=Gasto::eliminarGasto($id,$usuario_id);

        if($resultado){
            echo json_encode(['ok'=>true]);
        }else{
            echo json_encode(['ok'=>false,'msg'=>'No se encontró el gasto o no tienes permiso para eliminarlo']);
        }
    }


    //Método para manejar la petición AJAX de editar un gasto  
    public function editarGastoAjax(){

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

        //Validamos que los campos existan
        if(!isset($_POST['id'],$_POST['cantidad'])){
            echo json_encode(['ok'=> false, 'msg'=>'Datos incompletos']);
            return;
        }

        //Limpiamos y convertimos los datos
        $usuario_id=$_SESSION['usuario_id'];
        $id=intval($_POST['id']);
        $cantidad=floatval($_POST['cantidad']);

        //Validación básica
        if($id<=0 || $cantidad<0){
            echo json_encode(['ok'=>false, 'msg'=>'Datos inválidos']);
            return;
        }

        //Cargamos el modelo y ejecutamos la fución correspondiente, que valida propiedad por usuario_id
        $resultado=Gasto::actualizarGasto($id,$usuario_id,$cantidad);

        //COmprobamos si todo fue bien con la actualización
        if($resultado){
            echo json_encode(['ok'=>true]);
        }else{
            echo json_encode(['ok'=> false,'msg'=>'No se encontró el gasto o no tienes permiso para actualizarlo']);
        }
    }


    //Método para manejar la petición AJAX de agregar un gasto flexible
    public function agregarGastoFlexibleAjax(){

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

        //Recuperamos datos enviados desde el formulario
        $usuario_id=$_SESSION['usuario_id'];
        $categoria=trim($_POST['categoria_gasto_flexible']??'');
        $cantidad=trim($_POST['cantidad_gasto_flexible']??'');
        $mesSeleccionado=trim((string)($_POST['mes_seleccionado']??''));

        if(!bh_mes_valido($mesSeleccionado)){
            echo json_encode([
                "ok"=>false,
                "msg"=>"Mes no válido"
            ]);
            return;
        }

        $fecha=$mesSeleccionado."-01";
        $tipo="flexible";

        //Validación básica de los datos
        if($categoria ===''||$cantidad===''||!is_numeric($cantidad)||$cantidad<=0){
            echo json_encode([
                "ok"=>false,
                "msg"=>"Datos inválidos"
            ]);
            return;
        }

        if(!gastoCategoriaPermitida($tipo,$categoria)){
            echo json_encode([
                "ok"=>false,
                "msg"=>"Selecciona una categoría válida."
            ]);
            return;
        }

        //Insertamos en la base de datos el nuevo gasto flexible(devolverá el ID del recién creado gasto)
        $nuevoID=Gasto::agregarGasto($usuario_id,$tipo,$categoria,$cantidad,$fecha);

        //Si falla la inserción
        if(!$nuevoID){
            echo json_encode([
                "ok"=>false,
                "msg"=>"Error al guardar en la base de datos"
            ]);
            return;
        }

        //Enviamos respueesta exitosa en formato JSON
        echo json_encode([
            "ok"=>true,
            "gasto_flexible"=>[
                "id"=>$nuevoID,
                "categoria"=>$categoria,
                "cantidad"=>$cantidad
            ]

            ]);
    }
}
