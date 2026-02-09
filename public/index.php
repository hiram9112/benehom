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


//*************************************************SEGURIDAD GLOBAL (CSRF)


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {

        if ($_ENV['APP_ENV'] === 'production') {
            session_unset();
            session_destroy();

            // Iniciamos una nueva sesión 
            session_start();
            $_SESSION['mensaje_error'] =
                'Tu sesión ha caducado o la solicitud no es válida. Vuelve a iniciar sesión.';

            header("Location: " . BASE_URL . "index.php?r=auth/login");
            exit;
        } else {
            http_response_code(403);
            exit('403 Forbidden - CSRF inválido');
        }
    }
}




//*************************************************ROUTING
 

// Ruta solicitada
$route = isset($_GET['r']) ? trim($_GET['r'], "/") : 'auth/login';

// Rutas públicas (únicas sin sesión)
$rutasPublicas = [
    'auth/login',
    'registro/registrarUsuario',
    'password/mostrarFormularioOlvido',
    'password/procesarFormularioOlvido',
    'password/reset',
    'password/procesarReset'
];

$usuarioLogueado = isset($_SESSION['usuario_id']);




// Si NO está logueado y la ruta NO es pública → login
if (!$usuarioLogueado && !in_array($route, $rutasPublicas, true)) {
    header("Location: " . BASE_URL . "index.php?r=auth/login");
    exit;

}


//*************************************************DESPACHO A CONTROLADOR
 
// Controlador y acción
list($controllerName, $actionName) = explode('/', $route);

$controllerClass = ucfirst($controllerName) . 'Controller';
$controllerFile  = APP_PATH . '/controllers/' . $controllerClass . '.php';

// Comprobamos existencia del controlador
if (!file_exists($controllerFile)) {
    if ($appEnv === 'production') {
        header("Location: " . BASE_URL . "index.php?r=auth/login");
        exit;
    } else {
        echo "Error: controlador '{$controllerClass}' no existe";
        exit;
    }
}

require_once $controllerFile;

// Comprobamos existencia de la clase
if (!class_exists($controllerClass)) {
    if ($appEnv === 'production') {
        header("Location: " . BASE_URL . "index.php?r=auth/login");
        exit;
    } else {
        echo "Error: clase '{$controllerClass}' no encontrada";
        exit;
    }
}

$controller = new $controllerClass();

// Comprobamos existencia del método
if (!method_exists($controller, $actionName)) {
    if ($appEnv === 'production') {
        header("Location: " . BASE_URL . "index.php?r=auth/login");
        exit;
    } else {
        echo "Error: método '{$actionName}' no encontrado";
        exit;
    }
}

// Todo OK → ejecutamos acción
$controller->$actionName();





