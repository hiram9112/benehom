<?php

function bh_mes_valido(string $mes): bool
{
    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) === 1;
}

function gastoCategorias(): array
{
    static $categorias = null;

    if ($categorias === null) {
        $categorias = require CONFIG_PATH . '/gasto_categorias.php';
    }

    return $categorias;
}

function gastoCategoriasPorTipo(string $tipo): array
{
    $categorias = gastoCategorias();

    return $categorias[$tipo] ?? [];
}

function gastoCategoriaLabels(): array
{
    $labels = [];

    foreach (gastoCategorias() as $grupos) {
        foreach ($grupos as $grupo) {
            foreach ($grupo['items'] as $valor => $label) {
                $labels[$valor] = $label;
            }
        }
    }

    return $labels;
}

function gastoCategoriaPermitida(string $tipo, string $categoria): bool
{
    foreach (gastoCategoriasPorTipo($tipo) as $grupo) {
        if (isset($grupo['items'][$categoria])) {
            return true;
        }
    }

    return false;
}

function ingresoCategorias(): array
{
    static $categorias = null;

    if ($categorias === null) {
        $categorias = require CONFIG_PATH . '/ingreso_categorias.php';
    }

    return $categorias;
}

function ingresoCategoriaPermitida(string $categoria): bool
{
    return isset(ingresoCategorias()[$categoria]);
}

//Funcion para formatear las categorias
function formatearCategoria($texto){
    $labels = gastoCategoriaLabels();

    if (isset($labels[$texto])) {
        return $labels[$texto];
    }

    $labelsIngresos = ingresoCategorias();

    if (isset($labelsIngresos[$texto])) {
        return $labelsIngresos[$texto];
    }

    //Reemplazamos "_" por espacios
    $texto=str_replace("_"," ",$texto);

    //Ponemos mayúsucula inicial a cada palabra
    return ucwords($texto);
}

//Función para formatear cantidades
function formatearCantidadPHP($valor)
{
    return number_format($valor, 2, ',', '');
}

function bh_url(string $ruta = ''): string
{
    if (preg_match('#^https?://#i', $ruta)) {
        return $ruta;
    }

    $appEnv = (string) ($_ENV['APP_ENV'] ?? 'local');
    $configuredBase = trim((string) ($_ENV['APP_URL'] ?? ''), " \t\n\r\0\x0B\"'");
    $base = $appEnv === 'production' ? $configuredBase : '';

    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if ($host !== '') {
            $base = $scheme . '://' . $host;

            if (defined('BASE_URL') && trim(BASE_URL, '/') !== '') {
                $base .= '/' . trim(BASE_URL, '/');
            }
        } elseif ($configuredBase !== '') {
            $base = $configuredBase;
        } elseif (defined('BASE_URL')) {
            $base = BASE_URL;
        }
    }

    $base = rtrim($base, '/');
    $ruta = ltrim($ruta, '/');

    if ($ruta === '') {
        return $base . '/';
    }

    return $base . '/' . $ruta;
}

function bh_asset(string $ruta): string
{
    $ruta = ltrim($ruta, '/');
    $url = BASE_URL . $ruta;
    $assetPath = BASE_PATH . '/public/' . $ruta;

    if (!is_file($assetPath)) {
        return $url;
    }

    return $url . '?v=' . filemtime($assetPath);
}

function bh_css_tags(): string
{
    $cssFiles = ($_ENV['APP_ENV'] ?? 'local') === 'production'
        ? ['css/app.min.css']
        : [
            'css/src/base.css',
            'css/src/layout.css',
            'css/src/components.css',
            'css/src/dashboard.css',
            'css/src/proyecciones.css',
            'css/src/auth.css',
            'css/src/home.css',
            'css/src/blog.css',
            'css/src/cuenta.css',
            'css/src/legal.css',
            'css/src/responsive.css',
        ];

    $tags = [];

    foreach ($cssFiles as $cssFile) {
        $href = htmlspecialchars(bh_asset($cssFile), ENT_QUOTES, 'UTF-8');
        $tags[] = '    <link rel="stylesheet" href="' . $href . '">';
    }

    return implode(PHP_EOL, $tags) . PHP_EOL;
}

function bh_csp_nonce(): string
{
    static $nonce = null;

    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }

    return $nonce;
}

function bh_nonce_attr(): string
{
    return ' nonce="' . htmlspecialchars(bh_csp_nonce(), ENT_QUOTES, 'UTF-8') . '"';
}

function bh_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');

    $appEnv = $_ENV['APP_ENV'] ?? 'local';
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $nonce = bh_csp_nonce();
    $directives = [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
        "object-src 'none'",
        "img-src 'self' data:",
        "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com data:",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
        "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net",
        "connect-src 'self'",
    ];

    if ($isHttps) {
        $directives[] = 'upgrade-insecure-requests';
    }

    header('Content-Security-Policy: ' . implode('; ', $directives));

    if ($appEnv === 'production' && $isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

function bh_blog_url(string $slug = ''): string
{
    $slug = trim($slug, '/');

    if ($slug === '') {
        return bh_url('blog');
    }

    return bh_url('blog/' . rawurlencode($slug));
}

function bh_public_page_url(string $pagina = ''): string
{
    $pagina = trim($pagina, '/');

    if ($pagina === '') {
        return bh_url();
    }

    return bh_url($pagina);
}

function bh_query_route_requested(string $route): bool
{
    $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if (!in_array($method, ['GET', 'HEAD'], true)) {
        return false;
    }

    $query = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);

    if (!is_string($query) || $query === '') {
        return false;
    }

    parse_str($query, $params);

    return trim((string) ($params['r'] ?? ''), '/') === trim($route, '/');
}

function bh_redirect_permanent(string $url): void
{
    header('Location: ' . $url, true, 301);
    exit;
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

function bh_is_ajax_request(?string $route = null): bool
{
    $route = trim((string) ($route ?? ($_GET['r'] ?? '')), '/');
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

    if (str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest') {
        return true;
    }

    if (str_ends_with($route, 'Ajax')) {
        return true;
    }

    return str_starts_with($route, 'graficos/');
}

function bh_render_error_page(int $statusCode, string $title, string $message, string $actionLabel = 'Volver al inicio', string $actionUrl = ''): never
{
    http_response_code($statusCode);

    $actionUrl = $actionUrl !== '' ? $actionUrl : BASE_URL . 'index.php?r=home/index';

    require APP_PATH . '/views/error.php';
    exit;
}

/**
 * Envía email de recuperación de contraseña
 * Local: log
 * Producción: PGPMailer()
 */
function enviarEmailReset(string $email, string $resetLink): bool
{
    $appEnv = $_ENV['APP_ENV'] ?? 'local';

    // Construimos URL absoluta
    $resetLink = rtrim($_ENV['APP_URL'], '/') . $resetLink;

    $subject = 'Recuperación de contraseña - BeneHom';

    if ($appEnv === 'production') {
        try {

            $autoloadPath = BASE_PATH . '/vendor/autoload.php';

            if (file_exists($autoloadPath)) {
                require_once $autoloadPath;
            }

            if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
                error_log('[MAILER][RESET] PHPMailer no disponible.');
                return false;
            }

            $mail = new \PHPMailer\PHPMailer\PHPMailer();

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

            if (!$mail->send()) {
                error_log('[MAILER][RESET] No se pudo enviar el email de recuperación.');
                return false;
            }

            return true;
        } catch (Throwable $e) {
            error_log('[MAILER][RESET] Error enviando email de recuperación: ' . $e->getMessage());
            return false;
        }
    } else {
        error_log('[DEV][RESET LINK] ' . $resetLink);
        return true;
    }
}

/**
 * Envía email de verificación de cuenta
 * Local: log
 * Producción: PHPMailer()
 */
function enviarEmailVerificacion(string $email, string $verificationLink): bool
{
    $appEnv = $_ENV['APP_ENV'] ?? 'local';

    if (!preg_match('#^https?://#i', $verificationLink)) {
        $verificationLink = rtrim($_ENV['APP_URL'], '/') . $verificationLink;
    }

    $subject = 'Verifica tu correo - BeneHom';
    $safeLink = htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8');

    if ($appEnv === 'production') {
        try {

            $autoloadPath = BASE_PATH . '/vendor/autoload.php';

            if (file_exists($autoloadPath)) {
                require_once $autoloadPath;
            }

            if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
                error_log('[MAILER][VERIFY] PHPMailer no disponible.');
                return false;
            }

            $mail = new \PHPMailer\PHPMailer\PHPMailer();

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
            <p>Gracias por crear tu cuenta en BeneHom.</p>
            <p><strong>Verifica tu correo con este enlace (válido 30 minutos):</strong></p>
            <p>
                <a href='{$safeLink}'>{$safeLink}</a>
            </p>
            <p>Si no creaste esta cuenta, ignora este mensaje.</p>
            <p>— Equipo de BeneHom</p>
        ";

            $mail->AltBody =
                "Hola,\n\n" .
                "Gracias por crear tu cuenta en BeneHom.\n\n" .
                "Verifica tu correo con este enlace (válido 30 minutos):\n" .
                $verificationLink . "\n\n" .
                "Si no creaste esta cuenta, ignora este mensaje.\n\n" .
                "— Equipo de BeneHom";

            if (!$mail->send()) {
                error_log('[MAILER][VERIFY] No se pudo enviar el email de verificación.');
                return false;
            }

            return true;
        } catch (Throwable $e) {
            error_log('[MAILER][VERIFY] Error enviando email de verificación: ' . $e->getMessage());
            return false;
        }
    } else {
        error_log('[DEV][VERIFY LINK] ' . $verificationLink);
        return true;
    }
}
