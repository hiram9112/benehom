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

function ingresoCategoriaLabelsLegacy(): array
{
    return [
        'salario' => 'Salario o nómina',
        'actividad_propia' => 'Actividad propia o autónomo',
        'prestaciones_ayudas' => 'Prestaciones y ayudas públicas',
        'alquileres' => 'Ingresos por alquiler',
        'inversiones' => 'Inversiones, dividendos e intereses',
        'ventas_segunda_mano' => 'Ventas de segunda mano',
        'aportaciones_regalos' => 'Aportaciones o regalos familiares',
    ];
}

function ingresoCategoriaLabels(): array
{
    $labels = [];

    foreach (ingresoCategorias() as $grupo) {
        foreach (($grupo['conceptos'] ?? []) as $valor => $label) {
            $labels[$valor] = $label;
        }
    }

    foreach (ingresoCategoriaLabelsLegacy() as $valor => $label) {
        $labels[$valor] ??= $label;
    }

    return $labels;
}

function ingresoCategoriaPermitida(string $categoria): bool
{
    return isset(ingresoCategoriaLabels()[$categoria]);
}

//Funcion para formatear las categorias
function formatearCategoria($texto){
    $labels = gastoCategoriaLabels();

    if (isset($labels[$texto])) {
        return $labels[$texto];
    }

    $labelsIngresos = ingresoCategoriaLabels();

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

function bh_format_amount($valor, int $decimals = 2): string
{
    $numero = is_numeric($valor) ? (float) $valor : 0.0;

    return number_format($numero, $decimals, ',', '.');
}

function bh_format_money($valor, int $decimals = 2): string
{
    return bh_format_amount($valor, $decimals) . ' €';
}

function bh_format_money_delta($valor, int $decimals = 2): string
{
    $numero = is_numeric($valor) ? (float) $valor : 0.0;
    $signo = $numero > 0 ? '+' : ($numero < 0 ? '-' : '');

    return $signo . bh_format_money(abs($numero), $decimals);
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
            'css/src/vendor/lenis.css',
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
        "font-src 'self' https://cdn.jsdelivr.net data:",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
        "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net",
        "connect-src 'self' https://cdn.jsdelivr.net",
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

function bh_session_idle_expired(int $lastActivity, int $idleTimeout, ?int $now = null): bool
{
    if ($idleTimeout <= 0) {
        return false;
    }

    $now ??= time();

    return $lastActivity < ($now - $idleTimeout);
}

function bh_env_value(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false || $value === null) {
        return $default;
    }

    return trim((string) $value, " \t\n\r\0\x0B\"'");
}

function bh_env_bool(string $key, bool $default = false): bool
{
    $value = bh_env_value($key);

    if ($value === null || $value === '') {
        return $default;
    }

    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

function bh_env_int(string $key, int $default): int
{
    $value = bh_env_value($key);

    if ($value === null || $value === '' || !is_numeric($value)) {
        return $default;
    }

    return (int) $value;
}

function bh_routes(): array
{
    static $routes = null;

    if ($routes === null) {
        $routes = require CONFIG_PATH . '/routes.php';
    }

    return $routes;
}

function bh_route_definition(string $route): ?array
{
    $route = trim($route, '/');

    return bh_routes()[$route] ?? null;
}

function bh_route_allows_method(?array $routeDefinition, string $method): bool
{
    if ($routeDefinition === null) {
        return false;
    }

    $allowedMethods = array_map('strtoupper', $routeDefinition['methods'] ?? []);

    return in_array(strtoupper($method), $allowedMethods, true);
}

function bh_route_response_type(?array $routeDefinition): string
{
    if ($routeDefinition !== null) {
        return ($routeDefinition['response'] ?? 'html') === 'json' ? 'json' : 'html';
    }

    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return str_contains($accept, 'application/json') ? 'json' : 'html';
}

function bh_route_requires_global_csrf(array $routeDefinition): bool
{
    return (bool) ($routeDefinition['csrf'] ?? true);
}

function bh_controller_action_callable(string $controllerClass, string $action): bool
{
    if (!class_exists($controllerClass)) {
        return false;
    }

    $reflection = new ReflectionClass($controllerClass);

    if (!$reflection->hasMethod($action)) {
        return false;
    }

    $method = $reflection->getMethod($action);

    if (!$method->isPublic() || $method->isStatic()) {
        return false;
    }

    return is_callable([$reflection->newInstance(), $action]);
}

function bh_json_success(array $data = [], int $statusCode = 200): void
{
    http_response_code($statusCode);

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
}

function bh_json_error(string $code, string $message, int $statusCode): void
{
    http_response_code($statusCode);

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ], JSON_UNESCAPED_UNICODE);
}

function bh_router_error_message(string $code): string
{
    return [
        'NOT_FOUND' => 'No hemos encontrado la página solicitada.',
        'METHOD_NOT_ALLOWED' => 'El método HTTP no está permitido para esta ruta.',
        'UNAUTHENTICATED' => 'Inicia sesión para acceder a esa sección.',
        'INVALID_CSRF' => 'Solicitud no válida. Recarga la página e inténtalo de nuevo.',
        'INTERNAL_ERROR' => 'No hemos podido completar la solicitud.',
    ][$code] ?? 'No hemos podido completar la solicitud.';
}

function bh_router_error_title(string $code): string
{
    return [
        'NOT_FOUND' => 'Página no encontrada',
        'METHOD_NOT_ALLOWED' => 'Método no permitido',
        'UNAUTHENTICATED' => 'Sesión necesaria',
        'INVALID_CSRF' => 'Solicitud no válida',
        'INTERNAL_ERROR' => 'Error interno',
    ][$code] ?? 'Error';
}

function bh_numa_error_message(string $code): string
{
    return [
        'NUMA_INVALID_CSRF' => 'Solicitud no válida. Recarga la página e inténtalo de nuevo.',
        'NUMA_INVALID_MESSAGE' => 'Escribe una consulta válida.',
        'NUMA_MESSAGE_TOO_LONG' => 'La consulta no puede superar 300 caracteres.',
        'NUMA_NOT_AVAILABLE' => 'Numa no está disponible en este momento.',
        'NUMA_INTERNAL_ERROR' => 'No hemos podido procesar la consulta.',
    ][$code] ?? 'No hemos podido procesar la consulta.';
}

function bh_numa_error(string $code, int $statusCode): void
{
    bh_json_error($code, bh_numa_error_message($code), $statusCode);
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
    $requestToken = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (!$sessionToken || !$requestToken) {
        return false;
    }

    // hash_equals → comparación segura (evita ataques de timing)
    return hash_equals((string) $sessionToken, (string) $requestToken);
}

function bh_is_ajax_request(?string $route = null): bool
{
    $route = trim((string) ($route ?? ($_GET['r'] ?? '')), '/');
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

    if (str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest') {
        return true;
    }

    if ($route !== '' && bh_route_response_type(bh_route_definition($route)) === 'json') {
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

function bh_configurar_phpmailer_smtp($mail): void
{
    $smtpUser = (string) ($_ENV['SMTP_USER'] ?? '');
    $smtpHost = (string) ($_ENV['SMTP_HOST'] ?? '');
    $smtpPort = (string) ($_ENV['SMTP_PORT'] ?? '');
    $smtpSecure = (string) ($_ENV['SMTP_SECURE'] ?? '');
    $smtpFrom = (string) ($_ENV['SMTP_FROM'] ?? '');
    $smtpFromName = (string) ($_ENV['SMTP_FROM_NAME'] ?? '');

    $smtpHost = $smtpHost !== '' ? $smtpHost : 'smtp.gmail.com';
    $smtpPort = $smtpPort !== '' ? (int) $smtpPort : 587;
    $smtpSecure = $smtpSecure !== '' ? $smtpSecure : 'tls';
    $smtpFrom = $smtpFrom !== '' ? $smtpFrom : $smtpUser;
    $smtpFromName = $smtpFromName !== '' ? $smtpFromName : 'BeneHom';

    $mail->isSMTP();
    $mail->Host       = $smtpHost;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUser;
    $mail->Password   = (string) ($_ENV['SMTP_PASS'] ?? '');
    $mail->SMTPSecure = $smtpSecure;
    $mail->Port       = $smtpPort;

    $mail->setFrom($smtpFrom, $smtpFromName);
}

/**
 * Envía email de recuperación de contraseña
 * Local: log
 * Producción: PHPMailer()
 */
function enviarEmailReset(string $email, string $resetLink): bool
{
    $appEnv = $_ENV['APP_ENV'] ?? 'local';

    // Construimos URL absoluta
    $resetLink = rtrim($_ENV['APP_URL'], '/') . $resetLink;

    $subject = 'Recuperación de contraseña - BeneHom';
    $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

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

            bh_configurar_phpmailer_smtp($mail);
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = $subject;

            $mail->Body = "
            <p>Hola,</p>
            <p>Has solicitado restablecer tu contraseña.</p>
            <p><strong>Enlace (válido 30 minutos):</strong></p>
            <p>
                <a href='{$safeLink}'>{$safeLink}</a>
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

            bh_configurar_phpmailer_smtp($mail);
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
