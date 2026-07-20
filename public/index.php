<?php


// Activamos el modo estricto de tipos
declare(strict_types=1);

// Configuración de cookies de sesión
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

ini_set('session.use_strict_mode', '1');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Iniciamos sesión PHP
session_start();

//*************************************************CONSTANTES Y RUTAS BASE


define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');

// Detectamos dinámicamente la ruta base del proyecto
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$baseUrl = rtrim(dirname($scriptName), '/') . '/';

define('BASE_URL', $baseUrl);




//*************************************************VARIABLES DE ENTORNO (.env)

$envPath = BASE_PATH . '/.env';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $_ENV[$key] = $value;
    }
}



//************************************************ ENTORNO Y ERRORES*******************

date_default_timezone_set('Europe/Madrid');



// Mostraremos los errores según entorno
$appEnv = $_ENV['APP_ENV'] ?? 'local';

if ($appEnv === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}




//*************************************************HELPERS GLOBALES


require_once APP_PATH . "/helpers/utils.php";

bh_security_headers();

//***********************************************TIMEOUT DE SESIÓN POR INACTIVIDAD

$sessionIdleTimeout = (int) ($_ENV['SESSION_IDLE_TIMEOUT'] ?? 1800);
$sessionIdleMessage = 'Tu sesión se ha cerrado por inactividad. Vuelve a iniciar sesión para continuar.';

if (isset($_SESSION['usuario_id']) && $sessionIdleTimeout > 0) {
    $lastActivity = (int) ($_SESSION['last_activity'] ?? time());

    if (bh_session_idle_expired($lastActivity, $sessionIdleTimeout)) {
        $currentRoute = isset($_GET['r']) ? trim((string) $_GET['r'], '/') : 'home/index';
        $isAjaxTimeout = bh_is_ajax_request($currentRoute);

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        if ($isAjaxTimeout) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'msg' => $sessionIdleMessage]);
            exit;
        }

        session_start();

        $_SESSION['mensaje_error'] = $sessionIdleMessage;
        header("Location: " . BASE_URL . "index.php?r=auth/login");
        exit;
    }

    $_SESSION['last_activity'] = time();
}


//*************************************************ROUTING
 

// Ruta solicitada
$route = isset($_GET['r']) ? trim($_GET['r'], "/") : 'home/index';
$routeDefinition = bh_route_definition($route);
$responseType = bh_route_response_type($routeDefinition);
$usuarioLogueado = isset($_SESSION['usuario_id']);

if ($routeDefinition === null) {
    if ($responseType === 'json') {
        bh_json_error('NOT_FOUND', bh_router_error_message('NOT_FOUND'), 404);
        exit;
    }

    bh_render_error_page(404, bh_router_error_title('NOT_FOUND'), bh_router_error_message('NOT_FOUND'));
}

if (!bh_route_allows_method($routeDefinition, $_SERVER['REQUEST_METHOD'] ?? 'GET')) {
    if (!headers_sent()) {
        header('Allow: ' . implode(', ', $routeDefinition['methods']));
    }

    if ($responseType === 'json') {
        bh_json_error('METHOD_NOT_ALLOWED', bh_router_error_message('METHOD_NOT_ALLOWED'), 405);
        exit;
    }

    bh_render_error_page(405, bh_router_error_title('METHOD_NOT_ALLOWED'), bh_router_error_message('METHOD_NOT_ALLOWED'));
}

if (!$usuarioLogueado && !($routeDefinition['public'] ?? false)) {
    if ($responseType === 'json') {
        bh_json_error('UNAUTHENTICATED', bh_router_error_message('UNAUTHENTICATED'), 401);
        exit;
    }

    $_SESSION['mensaje_error'] = bh_router_error_message('UNAUTHENTICATED');
    header("Location: " . BASE_URL . "index.php?r=auth/login");
    exit;
}



//*************************************************SEGURIDAD GLOBAL (CSRF)


if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && bh_route_requires_global_csrf($routeDefinition) && !csrf_validate()) {
    if ($responseType === 'json') {
        bh_json_error('INVALID_CSRF', bh_router_error_message('INVALID_CSRF'), 403);
        exit;
    }

    bh_render_error_page(
        403,
        bh_router_error_title('INVALID_CSRF'),
        'Por seguridad no hemos podido completar la acción. Recarga la página e inténtalo de nuevo.',
        'Ir al inicio de sesión',
        BASE_URL . 'index.php?r=auth/login'
    );
}


//*************************************************RESOLUCIÓN DE CONTROLADOR


$controllerClass = $routeDefinition['controller'];
$actionName = $routeDefinition['action'];
$controllerFile  = APP_PATH . '/controllers/' . $controllerClass . '.php';

// Comprobamos existencia del controlador
if (!file_exists($controllerFile)) {
    if ($responseType === 'json') {
        bh_json_error('NOT_FOUND', bh_router_error_message('NOT_FOUND'), 404);
        exit;
    }

    bh_render_error_page(404, bh_router_error_title('NOT_FOUND'), bh_router_error_message('NOT_FOUND'));
}

require_once $controllerFile;

// Comprobamos clase y acción registrada invocable
if (!class_exists($controllerClass) || !bh_controller_action_callable($controllerClass, $actionName)) {
    if ($responseType === 'json') {
        bh_json_error('NOT_FOUND', bh_router_error_message('NOT_FOUND'), 404);
        exit;
    }

    bh_render_error_page(404, bh_router_error_title('NOT_FOUND'), bh_router_error_message('NOT_FOUND'));
}

$controller = new $controllerClass();

//*************************************************DESPACHO A CONTROLADOR

// Todo OK → ejecutamos acción
$controller->$actionName();





