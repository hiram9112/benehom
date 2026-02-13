<?php

//Funcion para formatear las categorias
function formatearCategoria($texto){
    //Reemplazamos "_" por espacios 
    $texto=str_replace("_"," ",$texto);

    //Ponemos mayúsucula inicial a cada palabra 
    return ucwords($texto);
}

//Función para formatear cantidades
function formatearCantidadPHP($valor)
{
    if (intval($valor) == $valor) {
        return (string)intval($valor);
    }
    return number_format($valor, 2, ',', '');
}






/**
 * CSRF = Cross-Site Request Forgery
 * Genera y valida tokens para proteger formularios POST
 */

/**
 * Genera o devuelve el token CSRF de la sesión
 */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('Sesión no iniciada');
    }

    if (empty($_SESSION['csrf_token'])) {
        // random_bytes → genera bytes aleatorios seguros
        // bin2hex → los convierte en texto hexadecimal
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Devuelve el input hidden con el token CSRF
 */
function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf" value="'.$token.'">';
}

/**
 * Valida el token CSRF recibido por POST
 */
function csrf_validate(): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $postToken    = $_POST['_csrf'] ?? '';

    if (!$sessionToken || !$postToken) {
        return false;
    }

    // hash_equals → comparación segura (evita ataques de timing)
    return hash_equals($sessionToken, $postToken);
}

/**
 * Envía email de recuperación de contraseña
 * Local: log
 * Producción: PGPMailer()
 */
function enviarEmailReset(string $email, string $resetLink): void
{
    $appEnv = $_ENV['APP_ENV'] ?? 'local';

    // Construimos URL absoluta
    $resetLink = rtrim($_ENV['APP_URL'], '/') . $resetLink;

    $subject = 'Recuperación de contraseña - BeneHom';

    if ($appEnv === 'production') {

        require_once BASE_PATH . '/vendor/PHPMailer/PHPMailer.php';
        require_once BASE_PATH . '/vendor/PHPMailer/SMTP.php';
        require_once BASE_PATH . '/vendor/PHPMailer/Exception.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer();

        $mail->CharSet = 'UTF-8';

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom($_ENV['SMTP_USER'], 'BeneHom');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = $subject;

        $mail->Body = "
            <p>Hola,</p>
            <p>Has solicitado restablecer tu contraseña.</p>
            <p><strong>Enlace (válido 30 minutos):</strong></p>
            <p>
                <a href='{$resetLink}'>{$resetLink}</a>
            </p>
            <p>Si no lo solicitaste, ignora este mensaje.</p>
            <p>— Equipo de BeneHom</p>
        ";

        $mail->AltBody =
            "Hola,\n\n" .
            "Has solicitado restablecer tu contraseña.\n\n" .
            "Enlace (válido 30 minutos):\n" .
            $resetLink . "\n\n" .
            "Si no lo solicitaste, ignora este mensaje.\n\n" .
            "— Equipo de BeneHom";

        $mail->send();
    } else {
        error_log('[DEV][RESET LINK] ' . $resetLink);
    }
}
