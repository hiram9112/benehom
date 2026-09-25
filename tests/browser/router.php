<?php

declare(strict_types=1);

$documentRoot = dirname(__DIR__, 2) . '/public';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';

if ($requestPath !== '/' && is_file($documentRoot . $requestPath)) {
    return false;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = trim($requestPath, '/');
$cleanRoutes = [
    '' => 'home/index',
    'iniciar-sesion' => 'auth/login',
    'registro' => 'registro/registrarUsuario',
    'recuperar-contrasena' => 'password/mostrarFormularioOlvido',
    'restablecer-contrasena' => 'password/reset',
    'verificar-email' => 'verificacion/verificar',
    'reenviar-verificacion' => 'verificacion/mostrarFormularioReenvio',
    'dashboard' => 'dashboard/index',
    'proyecciones' => 'proyecciones/index',
    'cuenta' => 'cuenta/index',
];

if ($requestPath !== '/'
    && in_array($method, ['GET', 'HEAD'], true)
    && str_ends_with($requestPath, '/')
    && isset($cleanRoutes[$path])) {
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    header('Location: /' . $path . ($query !== '' ? '?' . $query : ''), true, 301);
    exit;
}

$route = null;
$params = [];

if (isset($cleanRoutes[$path]) && ($path === '' || in_array($method, ['GET', 'HEAD'], true))) {
    $route = $cleanRoutes[$path];

    if ($path === 'dashboard' && preg_match('/^[0-9]{4}-(?:0[1-9]|1[0-2])$/', (string) ($_GET['mes'] ?? ''))) {
        $params['mes'] = $_GET['mes'];
    }

    if (in_array($path, ['restablecer-contrasena', 'verificar-email'], true)
        && preg_match('/^[a-f0-9]{64}$/', (string) ($_GET['token'] ?? ''))) {
        $params['token'] = $_GET['token'];
    }
} elseif ($path === 'blog') {
    $route = 'blog/index';
    $params = $_GET;
} elseif (preg_match('/^blog\/([A-Za-z0-9-]+)$/', $path, $matches)) {
    $route = 'blog/detalle';
    $params = array_merge($_GET, ['slug' => $matches[1]]);
} elseif (isset([
    'privacidad' => true,
    'terminos' => true,
    'aviso' => true,
][$path])) {
    $route = 'legal/' . $path;
    $params = $_GET;
} elseif ($path === 'sitemap.xml') {
    $route = 'seo/sitemap';
    $params = $_GET;
}

if ($route === null) {
    return false;
}

$_GET = array_merge(['r' => $route], $params);
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $documentRoot . '/index.php';

require $documentRoot . '/index.php';
