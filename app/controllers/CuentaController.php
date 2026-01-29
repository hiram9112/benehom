<?php

require_once APP_PATH."/models/Usuario.php";
require_once APP_PATH."/models/Gasto.php";
require_once APP_PATH."/models/Ingreso.php";

class CuentaController{    
    
    public function index(){
        //Cargamos la vista de la cuenta
        require APP_PATH."/views/cuenta.php";
    }

    //Funcion para cambiar la contraseña
    public function cambiarPassword(){

        //Comprobaciones de seguridad, nos aseguramos que la petición sea POST y  haya una sesión activa
        if($_SERVER['REQUEST_METHOD']!=='POST'){
            $_SESSION['mensaje_error']="Método no permitido.";
            header("Location: index.php?r=cuenta/index");
            return;
        }

        if(!isset($_SESSION['usuario_id'])){ 
            $_SESSION['mensaje_error']="Sesión no válida.";
            header("Location: index.php?r=auth/login");
            return;
        }           

                
        //Recogemos los datos necesarios hacer la consulta a la base de datos
        $id=$_SESSION['usuario_id'];
        $actual=$_POST['password_actual']?? '';
        $nueva=$_POST['password_nueva']?? '';
        

        //Obtenemos hash de  la contraseña actual
        $hashBD=Usuario::obtenerHashPassword($id);

        if(!$hashBD || !password_verify($actual, $hashBD)){
            $_SESSION['mensaje_error']="La contraseña actual no es correcta.";
            header("Location: index.php?r=cuenta/index");
            exit;
        }

        $nuevoHash=password_hash($nueva,PASSWORD_BCRYPT);
        $resultado=Usuario::actualizarPassword($id,$nuevoHash);

        //No aseguramos que se actulizó corrtamente
        if($resultado){
            $_SESSION['mensaje_exitoso']="Contraseña actualizada correctamente.";
        }else{
            $_SESSION['mensaje_error']="Error al actualizar la contraseña. Inténtelo de nuevo";
        }

        header("Location: index.php?r=cuenta/index");
        exit;             
    }        

    //Funcion para eliminar la cuenta
    public function eliminarCuenta(){

        //Comprobaciones de seguridad, nos aseguramos que la petición sea POST y  haya una sesión activa
        if($_SERVER['REQUEST_METHOD']!=='POST'){
            $_SESSION['mensaje_error']="Método no permitido.";
            header("Location: index.php?r=cuenta/index");
            return;
        }

        if(!isset($_SESSION['usuario_id'])){ 
            $_SESSION['mensaje_error']="Sesión no válida.";
            header("Location: index.php?r=auth/login");
            return;
        }           

                
        //Recogemos los datos necesarios hacer la consulta a la base de datos
        $id=$_SESSION['usuario_id'];
        $password=$_POST['password_confirmacion']?? '';
        

        //Obtenemos hash de  la contraseña actual
        $hashBD=Usuario::obtenerHashPassword($id);

        if(!$hashBD || !password_verify($password, $hashBD)){
            $_SESSION['mensaje_error']="La contraseña actual no es correcta.";
            header("Location: index.php?r=cuenta/index");
            exit;
        }
       

        //Eliminamos los ingresos del usuario
        $eliminarIngresos=Ingreso::eliminarTodosPorUsuario($id);
        //Eliminamos los gastos del usuario
        $eliminarGastos=Gasto::eliminarTodosPorUsuario($id);
        //Eliminamos usuario
        $eliminarUsuario=Usuario::eliminar($id);

        //Comprobamos que todo salió bien
        if($eliminarIngresos && $eliminarGastos && $eliminarUsuario){
            //Cerramos y destruimos sesión
            session_unset();
            session_destroy();

            //Creamos nueva sesión para mostrar mensaje final al usuario
            session_start();
            $_SESSION["mensaje_exitoso"]="Cuenta eliminada correctamente.";

            header("Location: index.php?r=auth/login");
            exit;
        }else{
            $_SESSION['mensaje_error']="Error eliminando la cuenta. Inténtelo de nuevo.";
            header("Location: index.php?r=cuenta/index");
            exit;
        }                     
    }        
}