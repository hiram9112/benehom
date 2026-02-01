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
            header("Location: " . BASE_URL . "index.php?r=auth/login");
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

            // ⚠️ De momento NO hacemos nada más con el token
            // (ni email, ni logs, ni mostrarlo)
        }

        // 7. Mensaje neutro SIEMPRE
        $_SESSION['mensaje_exitoso'] ='Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.';

        header("Location: " . BASE_URL . "index.php?r=password/mostrarFormularioOlvido");
        exit;

    }


}
