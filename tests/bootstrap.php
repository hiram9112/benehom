<?php

declare(strict_types=1);

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));
defined('APP_PATH') || define('APP_PATH', BASE_PATH . '/app');
defined('CONFIG_PATH') || define('CONFIG_PATH', BASE_PATH . '/config');
defined('BASE_URL') || define('BASE_URL', '/');

date_default_timezone_set('Europe/Madrid');

$_SERVER['REQUEST_METHOD'] ??= 'GET';
$_SERVER['HTTP_HOST'] ??= 'localhost';

$autoloadPath = BASE_PATH . '/vendor/autoload.php';

if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

require_once APP_PATH . '/helpers/utils.php';
