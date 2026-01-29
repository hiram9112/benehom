<?php
require_once __DIR__.'/../models/Usuario.php';
class AuthController {

    public function login(){
       //No aseguramos que el formulario haya sido enviado mediante POST
       if($_SERVER['REQUEST_METHOD']==='POST'){
           //Limpiamos y almacenamos los datos recibidos
           $email=trim($_POST['email']??'');
           $password=trim($_POST['password']??'');
           
           //Array para almacenar posibles errores
           $errores=[];

           //Validamos campos obligatorios
           if($email===''){
               $errores[]="El email es obligatorio"; 

           }
           if($password===''){
               $errores[]="La contraseña es obligatoria"; 

           }
           
           //Si no hay errores, intentamos autenticar
           if(empty($errores)){

                //Obtenemos el usuario de la base de datos
                $user=Usuario::obtenerUsuario($email);

                //Verificamos si el usuario existe y la contraseña coincide
                if($user && password_verify($password,$user['password'])){

                    //Guardamos el nombre del usuario y su id en la sesión
                    $_SESSION['usuario']=$user['usuario'];
                    $_SESSION['usuario_id']=$user['id'];


                    //Redirigimos al panel principal
                    header("Location: ".BASE_URL."index.php?r=dashboard/index");
                    exit;
                }
                else{
                    //Si el usuario no existe o la contraseña no coincide
                    $errores[]="Usuario o contraseña incorrectos";
                }
           }
            // Si apareció algún error volvemos a cargar la vista
            $_SESSION['mensaje_error']=$errores[0];
            require APP_PATH.'/views/auth/login.php';   

       }
       //Si no es una petición POST (el usuario entra por primera vez ) redirigimos
       else{
           require APP_PATH.'/views/auth/login.php'; 
       }
    }  

    public function logout(){
        
        //Eliminamos las variables de la sesión
        session_unset();

        //Destruimos la sesión
        session_destroy();

        //Redirigimos al inicio de sesión
        header("Location: ".BASE_URL."index.php?r=auth/login");
        exit;
    }
}