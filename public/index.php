<?php 
//Activamos el modo estricto de tipos para que php no intente convertir datos de manera automática y evitar resultados inesperados, ademas iniciamos sesion.
declare(strict_types=1);

//Configuramos cookie 
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

session_set_cookie_params([
    'lifetime' => 0,          // Sesión hasta cerrar navegador
    'path' => '/',
    'domain' => '',
    'secure' => $secure,      // true solo si hay HTTPS
    'httponly' => true,       // JS no puede acceder
    'samesite' => 'Lax',      // Protección básica CSRF
]);

//Iniciamos sesión PHP
session_start();

//Mostraremos los errores mientras desarrollamos.
$appEnv = $_ENV['APP_ENV'] ?? 'local';

if ($appEnv === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}


// Creamos varias constantes para almacenar las rutas mas importantes del proyecto y facilitar el trabajo con ellas.

define('BASE_PATH',dirname(__DIR__));
define('APP_PATH', BASE_PATH.'/app');
define('CONFIG_PATH',BASE_PATH.'/config');

// Detectamos dinámicamente la ruta base del proyecto
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$baseUrl = rtrim(dirname($scriptName), '/') . '/';

define('BASE_URL', $baseUrl);

// Cargar variables de entorno desde .env
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



//Cargamos la función auxiliar para formatear categorias
require_once APP_PATH."/helpers/utils.php";

//Guardamos la ruta a la que quiere acceder el usuario
$route=isset($_GET['r'])? trim($_GET['r'],"/"):'auth/login';

// Rutas públicas (únicas sin sesión)
$rutasPublicas = [
    'auth/login',
    'registro/registrarUsuario'
];

$usuarioLogueado = isset($_SESSION['usuario_id']);

// Si NO está logueado y la ruta NO es pública → login
if (!$usuarioLogueado && !in_array($route, $rutasPublicas, true)) {
    header("Location: " . BASE_URL . "index.php?r=auth/login");
    exit;
}


//Almacenamos el controlador asociado a la ruta y el metodo que usaremos
list($controllerName,$actionName)=explode('/',$route);

//Hacemos la modificación necesaria para almacenar el nombre del archivo controlador y la ruta hacia él.
$controllerClass=ucfirst($controllerName).'Controller';
$controllerFile=APP_PATH.'/controllers/'.$controllerClass.'.php';



// Enviamos la solicitud para abrir el controlador solicitado y ejecutar el método

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

    




