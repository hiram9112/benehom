<?php

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
