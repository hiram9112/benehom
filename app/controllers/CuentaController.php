<?php

require_once APP_PATH."/models/Usuario.php";
require_once APP_PATH."/models/Gasto.php";
require_once APP_PATH."/models/Ingreso.php";
require_once APP_PATH."/models/MetaAhorro.php";
require_once APP_PATH."/models/EscenarioInversion.php";
require_once APP_PATH."/models/InflacionProyeccion.php";
require_once APP_PATH."/models/CalculadoraHipoteca.php";

class CuentaController{    
    
    public function index(){
        //Recuperamos los datos de perfil del usuario para mostrarlos en la vista
        $id = $_SESSION['usuario_id'] ?? 0;
        $perfil = Usuario::obtenerPorId($id);

        $nombreUsuario = $perfil['usuario'] ?? ($_SESSION['usuario'] ?? 'Usuario');
        $emailUsuario  = $perfil['email'] ?? '';
        $fechaRegistro = $perfil['fecha_registro'] ?? null;

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
        $confirmacion=$_POST['password_confirmacion_nueva']?? '';

        //Comprobamos que todos los campos estén rellenos
        if ($actual === '' || $nueva === '' || $confirmacion === '') {
            $_SESSION['mensaje_error'] = "Todos los campos son obligatorios.";
            header("Location: index.php?r=cuenta/index");
            exit;
        }

        //Comprobamos que la nueva contraseña y su confirmación coincidan
        if ($nueva !== $confirmacion) {
            $_SESSION['mensaje_error'] = "La nueva contraseña y su confirmación no coinciden.";
            header("Location: index.php?r=cuenta/index");
            exit;
        }

        //Obtenemos hash de  la contraseña actual
        $hashBD=Usuario::obtenerHashPassword($id);

        if(!$hashBD || !password_verify($actual, $hashBD)){
            $_SESSION['mensaje_error']="La contraseña actual no es correcta.";
            header("Location: index.php?r=cuenta/index");
            exit;
        }

        // Evitar reutilizar la misma contraseña
        if (password_verify($nueva, $hashBD)) {
            $_SESSION['mensaje_error'] = "La nueva contraseña no puede ser igual a la actual.";
            header("Location: index.php?r=cuenta/index");
            exit;
        }

        // Validación de contraseña fuerte
        if (
            strlen($nueva) < 8 ||
            !preg_match('/[a-z]/', $nueva) ||
            !preg_match('/[A-Z]/', $nueva) ||
            !preg_match('/[0-9]/', $nueva)
        ) {
            $_SESSION['mensaje_error'] =
            "La nueva contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número.";
            header("Location: index.php?r=cuenta/index");
            exit;
        }

        $nuevoHash=password_hash($nueva,PASSWORD_BCRYPT);
        $resultado=Usuario::actualizarPassword($id,$nuevoHash);

        //Actualizamos mensajes
        if ($resultado) {
            $_SESSION['mensaje_exitoso'] = "Contraseña actualizada correctamente.";
        } else {
            $_SESSION['mensaje_error'] = "No se pudo actualizar la contraseña. Inténtalo de nueva más tarde";
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
        
        //Iniciamos una transacción 
        $db = Database::getConnection();
        $db->beginTransaction();
       

        //Eliminamos las metas de ahorro del usuario
        $eliminarMetas=MetaAhorro::eliminarTodosPorUsuario($id);
        //Eliminamos los escenarios de inversión del usuario
        $eliminarEscenarios=EscenarioInversion::eliminarTodosPorUsuario($id);
        //Eliminamos las proyecciones de inflación del usuario
        $eliminarInflacion=InflacionProyeccion::eliminarTodosPorUsuario($id);
        //Eliminamos las calculadoras de hipoteca del usuario
        $eliminarHipoteca=CalculadoraHipoteca::eliminarTodosPorUsuario($id);
        //Eliminamos los ingresos del usuario
        $eliminarIngresos=Ingreso::eliminarTodosPorUsuario($id);
        //Eliminamos los gastos del usuario
        $eliminarGastos=Gasto::eliminarTodosPorUsuario($id);
        //Eliminamos usuario
        $eliminarUsuario=Usuario::eliminar($id);

        //Comprobamos que todo salió bien antes de confirmar la transacción
        if ($eliminarMetas && $eliminarEscenarios && $eliminarInflacion && $eliminarHipoteca && $eliminarIngresos && $eliminarGastos && $eliminarUsuario) {

            // CONFIRMAMOS CAMBIOS
            $db->commit();

            session_unset();
            session_destroy();

            session_start();
            $_SESSION['mensaje_exitoso'] = "Cuenta eliminada correctamente.";

            header("Location: index.php?r=auth/login");
            exit;
        } else {

            //DESHACEMOS TODO
            $db->rollBack();

            $_SESSION['mensaje_error'] = "Error eliminando la cuenta. Inténtelo de nuevo.";
            header("Location: index.php?r=cuenta/index");
            exit;
        }
    }

    //Funcion para exportar todos los datos del usuario (portabilidad de datos / RGPD)
    public function exportarDatos(){

        //Comprobaciones de seguridad: petición POST y sesión activa
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

        $id=$_SESSION['usuario_id'];

        //Reunimos toda la información asociada al usuario
        $datos = [
            'aplicacion'             => 'BeneHom',
            'exportado_el'           => date('Y-m-d H:i:s'),
            'perfil'                 => Usuario::obtenerPorId($id) ?: [],
            'ingresos'               => Ingreso::obtenerTodosPorUsuario($id),
            'gastos'                 => Gasto::obtenerTodosPorUsuario($id),
            'metas_ahorro'           => MetaAhorro::obtenerActivasPorUsuario($id),
            'escenarios_inversion'   => EscenarioInversion::obtenerPorUsuario($id),
            'proyecciones_inflacion' => InflacionProyeccion::obtenerPorUsuario($id),
            'calculadoras_hipoteca'  => CalculadoraHipoteca::obtenerPorUsuario($id),
        ];

        //Servimos el archivo como descarga JSON
        $nombreArchivo = 'benehom-datos-' . date('Y-m-d') . '.json';

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        echo json_encode(
            $datos,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }
}
