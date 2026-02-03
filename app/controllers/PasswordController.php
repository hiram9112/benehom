<?php
require_once __DIR__ . '/../models/Usuario.php';

class PasswordController {

    // Muestra el formulario de "Olvidé mi contraseña"
    public function mostrarFormularioOlvido(){
        require_once __DIR__ . '/../views/auth/forgot_password.php';
    }


    public function procesarFormularioOlvido(){
        // CSRF ya validado en index.php

    
        $email = $_POST['email'] ?? '';

        // Validación mínima
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['mensaje_error'] = 'Introduce un correo electrónico válido.';
            header("Location: " . BASE_URL . "index.php?r=password/mostrarFormularioOlvido");
            exit;
        }

        //Buscar usuario
        $usuario = Usuario::obtenerUsuario($email);

        if ($usuario) {
            // Generar token seguro
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);          
            


            // Expiración (30 minutos)
            $expira = date('Y-m-d H:i:s', time() + 1800);

            //Guardar token en BD
            Usuario::guardarTokenReset($usuario['id'],$tokenHash, $expira);

            $resetLink = BASE_URL . "index.php?r=password/reset&token=" . $token;
            
            //Almacenamos el token para pruebas si estamos en local
            if (($_ENV['APP_ENV'] ?? 'local') === 'local') {
                error_log('[DEV][RESET LINK] ' . $resetLink);
            }

            enviarEmailReset($usuario['email'], $resetLink);
        }

        // 7. Mensaje neutro SIEMPRE
        $_SESSION['mensaje_exitoso'] ='Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.';

        header("Location: " . BASE_URL . "index.php?r=password/mostrarFormularioOlvido");
        exit;

    }

    public function reset(){
        
        //Token recibido por GET
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            $_SESSION['mensaje_error'] = 'Enlace de recuperación inválido.';
            header('Location: ?r=auth/login');
            exit;
        }

        //Hasheamos el token recibido
        $tokenHash = hash('sha256', $token);

        //Buscamos usuario con token válido
        $usuario = Usuario::obtenerUsuarioPorTokenReset($tokenHash);

        if (!$usuario) {
            $_SESSION['mensaje_error'] = 'El enlace es inválido o ha expirado.';
            header('Location: ?r=auth/login');
            exit;
        }

        //Token válido → mostramos formulario
        require APP_PATH . '/views/auth/reset_password.php';
    }


    public function procesarReset(){
        
        // CSRF ya validado en index.php

        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        // Si falta token, cortamos flujo
        if (empty($token)) {
            $_SESSION['mensaje_error'] = 'Enlace inválido.';
            header('Location: ?r=auth/login');
            exit;
        }

        // Errores de formulario → volver al reset
        if (empty($password) || empty($passwordConfirm)) {
            $_SESSION['mensaje_error'] = 'Debes completar todos los campos.';
            header('Location: ?r=password/reset&token=' . urlencode($token));
            exit;
        }

        if (strlen($password) < 8 ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[0-9]/', $password)) {

            $_SESSION['mensaje_error'] ='La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número.';
            header('Location: ?r=password/reset&token=' . urlencode($token));
            exit;
        }

        if ($password !== $passwordConfirm) {
            $_SESSION['mensaje_error'] = 'Las contraseñas no coinciden.';
            header('Location: ?r=password/reset&token=' . urlencode($token));
            exit;
        }

        // Hasheamos token recibido
        $tokenHash = hash('sha256', $token);

        // Validamos token otra vez (seguridad)
        $usuario = Usuario::obtenerUsuarioPorTokenReset($tokenHash);

        //cortamos el flujo si no encuentra coincidencia
        if (!$usuario) {
            $_SESSION['mensaje_error'] = 'El enlace es inválido o ha expirado.';
            header('Location: ?r=auth/login');
            exit;
        }

        // Actualizamos contraseña
        $nuevoHash = password_hash($password, PASSWORD_BCRYPT);
        Usuario::actualizarPassword($usuario['id'], $nuevoHash);

        // Invalidamos token
        Usuario::limpiarTokenReset($usuario['id']);

        $_SESSION['mensaje_exitoso'] = 'Contraseña actualizada correctamente.';

        header('Location: ?r=auth/login');
        exit;
    }


}
