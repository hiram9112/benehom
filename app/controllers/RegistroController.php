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
            $passwordConfirm = trim($_POST['password_confirm'] ?? '');

            
            // Validación usuario
            if ($usuario === '') {
                $_SESSION['mensaje_error'] = "El nombre de usuario es obligatorio";
            }
            // Validación email
            elseif ($email === '') {
                $_SESSION['mensaje_error'] = "El email es obligatorio";
            } 
            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['mensaje_error'] = "El email no tiene un formato válido";
            }
            // Validación contraseña
            elseif ($password === '') {
                $_SESSION['mensaje_error'] = "La contraseña es obligatoria";
            } 
            elseif (
                strlen($password) < 8 ||
                !preg_match('/[a-z]/', $password) ||
                !preg_match('/[A-Z]/', $password) ||
                !preg_match('/[0-9]/', $password)
            ){
                $_SESSION['mensaje_error'] = "La contraseña debe tener al menos 8 caracteres,<br> una mayúscula, una minúscula y un número";
            }
            elseif ($password !== $passwordConfirm) {
            $_SESSION['mensaje_error'] = "Las contraseñas no coinciden";
            }
            // Validación aceptación de términos
            elseif (empty($_POST['acepta_terminos'])) {
                $_SESSION['mensaje_error'] ="Debes aceptar la Política de Privacidad y los Términos para registrarte";
            }




            // Si hubo error, redirigimos
            if (isset($_SESSION['mensaje_error'])) {
                header("Location: " . BASE_URL . "index.php?r=registro/registrarUsuario");
                exit;
            }


            //Intentamos registrar el usuario
            try {

                $email = strtolower($email);

                $registrado = Usuario::registrar($usuario, $email, $password);
            } catch (PDOException $e) {
                error_log('[AUTH][REGISTER] Error de base de datos: ' . $e->getMessage());

                $_SESSION['mensaje_error'] =
                    'No se pudo completar el registro. Inténtalo más tarde.';

                header("Location: " . BASE_URL . "index.php?r=registro/registrarUsuario");
                exit;
            }

            // Resultado esperado
            if ($registrado) {

                $usuarioRegistrado = Usuario::obtenerUsuario($email);

                if (!$usuarioRegistrado) {
                    $_SESSION['mensaje_error'] =
                        'No se pudo preparar la verificación del email. Solicita un nuevo enlace.';

                    header("Location: " . BASE_URL . "index.php?r=verificacion/mostrarFormularioReenvio");
                    exit;
                }

                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expira = date('Y-m-d H:i:s', time() + 1800);

                $guardado = Usuario::guardarTokenVerificacion($usuarioRegistrado['id'], $tokenHash, $expira);

                if (!$guardado) {
                    $_SESSION['mensaje_error'] =
                        'No se pudo preparar la verificación del email. Solicita un nuevo enlace.';

                    header("Location: " . BASE_URL . "index.php?r=verificacion/mostrarFormularioReenvio");
                    exit;
                }

                $verificationLink = bh_url('index.php?r=verificacion/verificar&token=' . urlencode($token));

                if (!enviarEmailVerificacion($usuarioRegistrado['email'], $verificationLink)) {
                    $_SESSION['mensaje_error'] = 'No se pudo enviar el email de verificación. Solicita un nuevo enlace.';

                    header("Location: " . BASE_URL . "index.php?r=verificacion/mostrarFormularioReenvio");
                    exit;
                }

                $_SESSION['mensaje_exitoso'] =
                    "Te hemos enviado un enlace de verificación a tu correo. Verifícalo antes de iniciar sesión.";

                header("Location: " . BASE_URL . "index.php?r=auth/login");
                exit;
            } else {

                // Email duplicado (caso controlado)
                $_SESSION['mensaje_error'] =
                    "Ya existe un usuario con ese email. Inicia sesión.";

                header("Location: " . BASE_URL . "index.php?r=auth/login");
                exit;
            }
        }
        else{
            require APP_PATH.'/views/auth/register.php';
        }
       

    }
}
