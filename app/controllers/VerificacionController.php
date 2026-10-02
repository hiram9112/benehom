<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/IntentoAcceso.php';
require_once __DIR__ . '/../services/N8nWebhookService.php';

class VerificacionController {

    private const VERIFICACION_INVALIDA = 'invalid';
    private const VERIFICACION_FALLIDA = 'failed';
    private const VERIFICACION_CORRECTA = 'verified';
    private const VERIFICACION_MAX_SOLICITUDES = 3;
    private const VERIFICACION_VENTANA_SEGUNDOS = 3600;
    private const VERIFICACION_BLOQUEO_SEGUNDOS = 3600;

    public function verificar(){
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            $_SESSION['mensaje_error'] = 'El enlace de verificación es inválido o ha expirado.';
            header('Location: ' . bh_page_url('auth/login'));
            exit;
        }

        $resultado = $this->verificarToken($token);

        if ($resultado === self::VERIFICACION_INVALIDA) {
            $_SESSION['mensaje_error'] = 'El enlace de verificación es inválido o ha expirado.';
            header('Location: ' . bh_page_url('auth/login'));
            exit;
        }

        if ($resultado === self::VERIFICACION_FALLIDA) {
            $_SESSION['mensaje_error'] = 'No se pudo verificar el email. Solicita un nuevo enlace.';
            header('Location: ' . bh_page_url('verificacion/mostrarFormularioReenvio'));
            exit;
        }

        $_SESSION['mensaje_exitoso'] = 'Email verificado. Ya puedes iniciar sesión.';
        header('Location: ' . bh_page_url('auth/login'));
        exit;
    }

    protected function verificarToken(string $token): string
    {
        $usuario = Usuario::obtenerUsuarioPorTokenVerificacion(hash('sha256', $token));

        if (!$usuario) {
            return self::VERIFICACION_INVALIDA;
        }

        if (!$this->confirmarEmailVerificado((int) $usuario['id'])) {
            return self::VERIFICACION_FALLIDA;
        }

        $this->n8nWebhooks()->notifyVerifiedUser((int) $usuario['id'], (string) $usuario['usuario']);

        return self::VERIFICACION_CORRECTA;
    }

    protected function confirmarEmailVerificado(int $userId): bool
    {
        return Usuario::marcarEmailVerificado($userId);
    }

    protected function n8nWebhooks(): N8nWebhookService
    {
        return new N8nWebhookService();
    }

    public function mostrarFormularioReenvio(){
        require APP_PATH . '/views/auth/resend_verification.php';
    }

    public function reenviar(){
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['mensaje_error'] = 'Introduce un correo electrónico válido.';
            header('Location: ' . bh_page_url('verificacion/mostrarFormularioReenvio'));
            exit;
        }

        $claveRateLimit = IntentoAcceso::claveHash($email);

        if (IntentoAcceso::estaBloqueado('email_verification', $claveRateLimit)) {
            $_SESSION['mensaje_exitoso'] = 'Si el correo está registrado y pendiente de verificación, recibirás un nuevo enlace.';
            header('Location: ' . bh_page_url('verificacion/mostrarFormularioReenvio'));
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
                $verificationLink = bh_page_url('verificacion/verificar', ['token' => $token]);

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
        header('Location: ' . bh_page_url('verificacion/mostrarFormularioReenvio'));
        exit;
    }
}
