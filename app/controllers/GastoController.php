<?php
require_once APP_PATH.'/models/Gasto.php';
class GastoController{

    //Método para manejar la petición AJAX de agregar un gasto obligatorio
    public function agregarGastoObligAjax(){

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
        $categoria=trim($_POST['categoria_gasto_obligatorio']??'');
        $cantidad=trim($_POST['cantidad_gasto_obligatorio']??'');
        $fecha=$_POST['mes_seleccionado']."-01";
        $tipo="obligatorio";

        //Validación básica de los datos
        if($categoria ===''||$cantidad===''||!is_numeric($cantidad)||$cantidad<=0){
            echo json_encode([
                "ok"=>false,
                "msg"=>"Datos inválidos"
            ]);
            return;    
        }

        //Insertamos en la base de datos el nuevo gasto obligatorio(devolverá el ID del recién creado gasto)
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
            "gasto_oblig"=>[
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

        //Recogemos el id enviado 
        $id=$_POST['id']??null;

        //Comprobamos que lo hayamos recibido correctamente esto a su vez nos permite sabe que hay sesión activa
        if(!$id){
            echo json_encode(['ok'=>false,'msg'=>'ID no recibido']);
            return;
        }

        //Ejecutamos la función correspondiente
        $resultado=Gasto::eliminarGasto($id);

        if($resultado){
            echo json_encode(['ok'=>true]);
        }else{
            echo json_encode(['ok'=>false,'msg'=>'No se pudo eliminar']);
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
        $id=intval($_POST['id']);
        $cantidad=floatval($_POST['cantidad']);

        //Validación básica
        if($id<=0 || $cantidad<0){
            echo json_encode(['ok'=>false, 'msg'=>'Datos inválidos']);
            return;
        }

        //Cargamos el modelo y ejecutamos la fución correspondiente
        $resultado=Gasto::actualizarGasto($id,$cantidad);

        //COmprobamos si todo fue bien con la actualización
        if($resultado){
            echo json_encode(['ok'=>true]);
        }else{
            echo json_encode(['ok'=> false,'msg'=>'No se pudo actualizar']);
        }        
    }


    //Método para manejar la petición AJAX de agregar un gasto Voluntario
    public function agregarGastoVolunAjax(){

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
        $categoria=trim($_POST['categoria_gasto_voluntario']??'');
        $cantidad=trim($_POST['cantidad_gasto_voluntario']??'');
        $fecha=$_POST['mes_seleccionado']."-01";
        $tipo="voluntario";

        //Validación básica de los datos
        if($categoria ===''||$cantidad===''||!is_numeric($cantidad)||$cantidad<=0){
            echo json_encode([
                "ok"=>false,
                "msg"=>"Datos inválidos"
            ]);
            return;    
        }

        //Insertamos en la base de datos el nuevo gasto Voluntario(devolverá el ID del recién creado gasto)
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
            "gasto_volun"=>[
                "id"=>$nuevoID,
                "categoria"=>$categoria,
                "cantidad"=>$cantidad
            ]

            ]);
    }
}