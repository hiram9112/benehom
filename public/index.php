<?php


// Activamos el modo estricto de tipos
declare(strict_types=1);

// Configuración de cookies de sesión
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

session_set_cookie_params([
    'lifetime' => 0,          // Sesión hasta cerrar navegador
    'path' => '/',
    'domain' => '',
    'secure' => $secure,      // true solo si hay HTTPS
    'httponly' => true,       // JS no puede acceder
    'samesite' => 'Lax',      // Protección básica CSRF
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

if (isset($_SESSION['usuario_id']) && $sessionIdleTimeout > 0) {
    $lastActivity = (int) ($_SESSION['last_activity'] ?? time());

    if ($lastActivity < (time() - $sessionIdleTimeout)) {
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
        session_start();

        $_SESSION['mensaje_error'] = 'Tu sesión ha caducado por inactividad. Vuelve a iniciar sesión.';
        header("Location: " . BASE_URL . "index.php?r=auth/login");
        exit;
    }

    $_SESSION['last_activity'] = time();
}


//*************************************************SEGURIDAD GLOBAL (CSRF)


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $route = isset($_GET['r']) ? trim((string) $_GET['r'], '/') : '';

        if (bh_is_ajax_request($route)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'msg' => 'Solicitud no válida. Recarga la página e inténtalo de nuevo.']);
            exit;
        }

        bh_render_error_page(
            403,
            'Solicitud no válida',
            'Por seguridad no hemos podido completar la acción. Recarga la página e inténtalo de nuevo.',
            'Ir al inicio de sesión',
            BASE_URL . 'index.php?r=auth/login'
        );
    }
}




//*************************************************ROUTING
 

// Ruta solicitada
$route = isset($_GET['r']) ? trim($_GET['r'], "/") : 'home/index';

// Rutas públicas (únicas sin sesión)
$rutasPublicas = [
    'home/index',
    'auth/login',
    'registro/registrarUsuario',
    'password/mostrarFormularioOlvido',
    'password/procesarFormularioOlvido',
    'password/reset',
    'password/procesarReset',
    'verificacion/verificar',
    'verificacion/mostrarFormularioReenvio',
    'verificacion/reenviar',
    'blog/index',
    'blog/detalle',
    'seo/sitemap',
    'legal/privacidad',
    'legal/terminos',
    'legal/aviso'
];

$usuarioLogueado = isset($_SESSION['usuario_id']);



//*************************************************RESOLUCIÓN DE CONTROLADOR


// Controlador y acción
$routeParts = explode('/', $route, 2);

if (count($routeParts) !== 2 || $routeParts[0] === '' || $routeParts[1] === '') {
    bh_render_error_page(
        404,
        'Página no encontrada',
        'No hemos encontrado la página que buscas. Puede que el enlace haya cambiado o esté incompleto.',
        'Volver al inicio',
        BASE_URL . 'index.php?r=home/index'
    );
}

[$controllerName, $actionName] = $routeParts;

$controllerClass = ucfirst($controllerName) . 'Controller';
$controllerFile  = APP_PATH . '/controllers/' . $controllerClass . '.php';

// Comprobamos existencia del controlador
if (!file_exists($controllerFile)) {
    bh_render_error_page(
        404,
        'Página no encontrada',
        'No hemos encontrado la página que buscas. Puede que el enlace haya cambiado o esté incompleto.',
        'Volver al inicio',
        BASE_URL . 'index.php?r=home/index'
    );
}

require_once $controllerFile;

// Comprobamos existencia de la clase
if (!class_exists($controllerClass)) {
    bh_render_error_page(
        404,
        'Página no encontrada',
        'No hemos encontrado la página que buscas. Puede que el enlace haya cambiado o esté incompleto.',
        'Volver al inicio',
        BASE_URL . 'index.php?r=home/index'
    );
}

$controller = new $controllerClass();

// Comprobamos existencia del método
if (!method_exists($controller, $actionName)) {
    bh_render_error_page(
        404,
        'Página no encontrada',
        'No hemos encontrado la página que buscas. Puede que el enlace haya cambiado o esté incompleto.',
        'Volver al inicio',
        BASE_URL . 'index.php?r=home/index'
    );
}




// Si NO está logueado y la ruta NO es pública → login
if (!$usuarioLogueado && !in_array($route, $rutasPublicas, true)) {
    if (bh_is_ajax_request($route)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'Tu sesión ha caducado. Vuelve a iniciar sesión.']);
        exit;
    }

    $_SESSION['mensaje_error'] = 'Inicia sesión para acceder a esa sección.';
    header("Location: " . BASE_URL . "index.php?r=auth/login");
    exit;

}


//*************************************************DESPACHO A CONTROLADOR

// Todo OK → ejecutamos acción
$controller->$actionName();





