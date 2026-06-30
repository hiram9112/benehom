<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/IntentoAcceso.php';

class VerificacionController {

    private const VERIFICACION_MAX_SOLICITUDES = 3;
    private const VERIFICACION_VENTANA_SEGUNDOS = 3600;
    private const VERIFICACION_BLOQUEO_SEGUNDOS = 3600;

    public function verificar(){
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            $_SESSION['mensaje_error'] = 'El enlace de verificación es inválido o ha expirado.';
            header("Location: " . BASE_URL . "index.php?r=auth/login");
            exit;
        }

        $tokenHash = hash('sha256', $token);
        $usuario = Usuario::obtenerUsuarioPorTokenVerificacion($tokenHash);

        if (!$usuario) {
            $_SESSION['mensaje_error'] = 'El enlace de verificación es inválido o ha expirado.';
            header("Location: " . BASE_URL . "index.php?r=auth/login");
            exit;
        }

        if (!Usuario::marcarEmailVerificado($usuario['id'])) {
            $_SESSION['mensaje_error'] = 'No se pudo verificar el email. Solicita un nuevo enlace.';
            header("Location: " . BASE_URL . "index.php?r=verificacion/mostrarFormularioReenvio");
            exit;
        }

        $_SESSION['mensaje_exitoso'] = 'Email verificado. Ya puedes iniciar sesión.';
        header("Location: " . BASE_URL . "index.php?r=auth/login");
        exit;
    }

    public function mostrarFormularioReenvio(){
        require APP_PATH . '/views/auth/resend_verification.php';
    }

    public function reenviar(){
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['mensaje_error'] = 'Introduce un correo electrónico válido.';
            header("Location: " . BASE_URL . "index.php?r=verificacion/mostrarFormularioReenvio");
            exit;
        }

        $claveRateLimit = IntentoAcceso::claveHash($email);

        if (IntentoAcceso::estaBloqueado('email_verification', $claveRateLimit)) {
            $_SESSION['mensaje_exitoso'] = 'Si el correo está registrado y pendiente de verificación, recibirás un nuevo enlace.';
            header("Location: " . BASE_URL . "index.php?r=verificacion/mostrarFormularioReenvio");
            exit;
        }

        try {
            $usuario = Usuario::obtenerUsuario($email);
        } catch (PDOException $e) {
            $usuario = false;
        }

        if ($usuario && empty($usuario['email_verificado_en'])) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expira = date('Y-m-d H:i:s', time() + 1800);

            if (Usuario::guardarTokenVerificacion($usuario['id'], $tokenHash, $expira)) {
                $verificationLink = bh_url('index.php?r=verificacion/verificar&token=' . urlencode($token));

                enviarEmailVerificacion($usuario['email'], $verificationLink);
            }
        }

        IntentoAcceso::registrarFallo(
            'email_verification',
            $claveRateLimit,
            self::VERIFICACION_MAX_SOLICITUDES,
            self::VERIFICACION_VENTANA_SEGUNDOS,
            self::VERIFICACION_BLOQUEO_SEGUNDOS
        );

        $_SESSION['mensaje_exitoso'] = 'Si el correo está registrado y pendiente de verificación, recibirás un nuevo enlace.';
        header("Location: " . BASE_URL . "index.php?r=verificacion/mostrarFormularioReenvio");
        exit;
    }
}
