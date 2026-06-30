<?php
require_once APP_PATH.'/models/Ingreso.php';
class IngresoController{

    //Método para manejar la petición AJAX de agregar ingreso
    public function agregarAjax(){

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
        $categoria=trim($_POST['categoria_ingreso']??'');
        $cantidad=trim($_POST['cantidad_ingreso']??'');
        $fecha=$_POST['mes_seleccionado']."-01";

        //Validación básica de los datos
        if($categoria ===''||$cantidad===''||!is_numeric($cantidad)||$cantidad<=0){
            echo json_encode([
                "ok"=>false,
                "msg"=>"Datos inválidos"
            ]);
            return;
        }

        if(!ingresoCategoriaPermitida($categoria)){
            echo json_encode([
                "ok"=>false,
                "msg"=>"Categoría de ingreso no válida"
            ]);
            return;
        }

        //Insertamos en la base de datos el nuevo ingreso(devolverá el ID del recién creado ingreso)
        $nuevoID=Ingreso::agregarIngreso($usuario_id,$categoria,$cantidad,$fecha);

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
            "ingreso"=>[
                "id"=>$nuevoID,
                "categoria"=>$categoria,
                "cantidad"=>$cantidad
            ]

            ]);
    }

    //Método para manejar la petición AJAX de eliminar ingreso 
    public function eliminarAjax(){

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
        $resultado=Ingreso::eliminarIngreso($id,$usuario_id);

        if($resultado){
            echo json_encode(['ok'=>true]);
        }else{
            echo json_encode(['ok'=>false,'msg'=>'No se encontró el ingreso o no tienes permiso para eliminarlo']);
        }
    }


    //Método para manejar la petición AJAX de editar un ingreso
    public function editarAjax(){

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
        $resultado=Ingreso::actualizarIngreso($id,$usuario_id,$cantidad);

        //COmprobamos si todo fue bien con la actualización
        if($resultado){
            echo json_encode(['ok'=>true]);
        }else{
            echo json_encode(['ok'=> false,'msg'=>'No se encontró el ingreso o no tienes permiso para actualizarlo']);
        }
    }
}
