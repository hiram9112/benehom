<?php
require_once __DIR__.'/../models/Usuario.php';

class RegistroController{
    
    //Creamos una función para registrar nuevos usuarios
    public function registrarUsuario(){
        if($_SERVER['REQUEST_METHOD']==='POST'){
            
            //recogemos los datos del formulario
            $usuario=trim($_POST['usuario']??'');
            $email=trim($_POST['email']??'');
            $password=trim($_POST['password']??'');

            //hacemos uan validación básica 
            if(empty($usuario)||empty($email)||empty($password)){
                die("Todos los campos son obligatorios");
            }

            //Intentamos registrar el usuario
            if(Usuario::registrar($usuario,$email,$password)){
                $_SESSION['mensaje_exitoso']="Se ha completado el registro. Ahora puedes iniciar sesión.";

                //redirigimos al login
                header("Location: ".BASE_URL."index.php?r=auth/login");
                exit;
            }
            else{
                //Ya existe un usuario con ese email creamos una varibale de sessión temporal para almacenar un mensaje
                $_SESSION['mensaje_error']="Ya existe un usuario con ese email. Inicia sesión.";
                header("Location: ".BASE_URL."index.php?r=auth/login");
                exit;
            }
        }
        else{
            require APP_PATH.'/views/auth/register.php';
        }
       

    }
}