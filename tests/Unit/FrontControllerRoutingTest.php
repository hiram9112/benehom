<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FrontControllerRoutingTest extends TestCase
{
    public function testRutaWebValidaSeDespachaDesdeRouterReal(): void
    {
        $response = $this->runFrontController([
            'method' => 'GET',
            'get' => ['r' => 'home/index'],
        ]);

        self::assertSame(200, $response['status']);
        self::assertStringContainsString('<!DOCTYPE html>', $response['body']);
        self::assertStringContainsString('BeneHom', $response['body']);
    }

    public function testRutaNoRegistradaDevuelveErrorHtmlDesdeRouterReal(): void
    {
        $response = $this->runFrontController([
            'method' => 'GET',
            'get' => ['r' => 'no/existe'],
            'accept' => 'text/html',
        ]);

        self::assertSame(404, $response['status']);
        self::assertStringContainsString('<!DOCTYPE html>', $response['body']);
        self::assertStringContainsString('Página no encontrada', $response['body']);
    }

    public function testRutaNoRegistradaConAcceptJsonDevuelveErrorJsonDesdeRouterReal(): void
    {
        $response = $this->runFrontController([
            'method' => 'GET',
            'get' => ['r' => 'no/existe'],
            'accept' => 'application/json',
        ]);

        self::assertSame(404, $response['status']);
        self::assertJsonError($response['body'], 'NOT_FOUND');
    }

    public function testMetodoIncorrectoDevuelve405JsonDesdeRouterReal(): void
    {
        $response = $this->runFrontController([
            'method' => 'POST',
            'get' => ['r' => 'numa/status'],
            'accept' => 'application/json',
            'session' => ['usuario_id' => 123],
        ]);

        self::assertSame(405, $response['status']);
        self::assertJsonError($response['body'], 'METHOD_NOT_ALLOWED');
    }

    public function testRutaJsonAutenticadaSinSesionDevuelve401DesdeRouterReal(): void
    {
        $response = $this->runFrontController([
            'method' => 'GET',
            'get' => ['r' => 'numa/status'],
            'accept' => 'application/json',
        ]);

        self::assertSame(401, $response['status']);
        self::assertJsonError($response['body'], 'UNAUTHENTICATED');
    }

    public function testRutaNumaStatusAutenticadaDevuelveJsonDesdeRouterReal(): void
    {
        $response = $this->runFrontController([
            'method' => 'GET',
            'get' => ['r' => 'numa/status'],
            'accept' => 'application/json',
            'session' => ['usuario_id' => 123],
            'env' => ['NUMA_ENABLED' => 'false'],
        ]);

        self::assertSame(200, $response['status']);

        $decoded = json_decode($response['body'], true);

        self::assertIsArray($decoded);
        self::assertTrue($decoded['ok']);
        self::assertSame(['available' => false, 'usage' => null], $decoded['data']);
    }

    public function testCsrfGlobalInvalidoDevuelveErrorJsonGeneralDesdeRouterReal(): void
    {
        $response = $this->runFrontController([
            'method' => 'POST',
            'get' => ['r' => 'ingreso/agregarAjax'],
            'accept' => 'application/json',
            'post' => ['mes_seleccionado' => '2026-05'],
            'session' => ['usuario_id' => 123, 'csrf_token' => 'csrf-token'],
        ]);

        self::assertSame(403, $response['status']);
        self::assertJsonError($response['body'], 'INVALID_CSRF');
    }

    public function testRutaAjaxValidaDespachaControladorDesdeRouterReal(): void
    {
        $response = $this->runFrontController([
            'method' => 'POST',
            'get' => ['r' => 'ingreso/agregarAjax'],
            'accept' => 'application/json',
            'post' => [
                '_csrf' => 'csrf-token',
                'categoria_ingreso' => 'salario',
                'cantidad_ingreso' => '100',
                'mes_seleccionado' => 'mes-invalido',
            ],
            'session' => ['usuario_id' => 123, 'csrf_token' => 'csrf-token'],
        ]);

        self::assertSame(200, $response['status']);

        $decoded = json_decode($response['body'], true);

        self::assertIsArray($decoded);
        self::assertFalse($decoded['ok']);
        self::assertSame('Mes no válido', $decoded['msg']);
    }

    public function testMetodoDeControladorNoRegistradoNoSeEjecutaDesdeRouterReal(): void
    {
        $response = $this->runFrontController([
            'method' => 'GET',
            'get' => ['r' => 'auth/emailVerificadoParaLogin'],
            'accept' => 'application/json',
        ]);

        self::assertSame(404, $response['status']);
        self::assertJsonError($response['body'], 'NOT_FOUND');
    }

    /**
     * @param array<string, mixed> $options
     * @return array{status:int, headers:array<int, string>, body:string, stderr:string, exit_code:int}
     */
    private function runFrontController(array $options): array
    {
        $payload = base64_encode(json_encode($options, JSON_THROW_ON_ERROR));
        $code = <<<'PHP'
$basePath = getcwd();
$config = json_decode(base64_decode($argv[1]), true);

if (!is_array($config)) {
    fwrite(STDERR, 'Invalid front controller test config');
    exit(1);
}

$_GET = $config['get'] ?? [];
$_POST = $config['post'] ?? [];
$_COOKIE = [];

foreach (($config['env'] ?? []) as $key => $value) {
    $_ENV[(string) $key] = (string) $value;
    putenv((string) $key . '=' . (string) $value);
}

$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'testing';
$_ENV['APP_URL'] = $_ENV['APP_URL'] ?? 'http://localhost';
putenv('APP_ENV=' . $_ENV['APP_ENV']);
putenv('APP_URL=' . $_ENV['APP_URL']);

$query = http_build_query($_GET);
$requestUri = '/index.php' . ($query !== '' ? '?' . $query : '');

$_SERVER = array_replace($_SERVER, [
    'DOCUMENT_ROOT' => $basePath . '/public',
    'HTTPS' => 'off',
    'HTTP_HOST' => 'localhost',
    'PHP_SELF' => '/index.php',
    'REMOTE_ADDR' => '127.0.0.1',
    'REQUEST_METHOD' => (string) ($config['method'] ?? 'GET'),
    'REQUEST_URI' => $requestUri,
    'SCRIPT_FILENAME' => $basePath . '/public/index.php',
    'SCRIPT_NAME' => '/index.php',
    'SERVER_NAME' => 'localhost',
    'SERVER_PORT' => '80',
    'SERVER_PROTOCOL' => 'HTTP/1.1',
]);

if (isset($config['accept'])) {
    $_SERVER['HTTP_ACCEPT'] = (string) $config['accept'];
} else {
    unset($_SERVER['HTTP_ACCEPT']);
}

if (isset($config['content_type'])) {
    $_SERVER['CONTENT_TYPE'] = (string) $config['content_type'];
} else {
    unset($_SERVER['CONTENT_TYPE'], $_SERVER['HTTP_CONTENT_TYPE']);
}

$sessionId = 'bhrouter' . substr(hash('sha256', (string) random_int(1, PHP_INT_MAX)), 0, 24);
session_id($sessionId);
session_start();
$_SESSION = $config['session'] ?? [];
session_write_close();

$sessionFile = rtrim(session_save_path() !== '' ? session_save_path() : sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR . 'sess_' . $sessionId;

register_shutdown_function(static function () use ($sessionFile): void {
    $status = http_response_code();

    if ($status === false || $status === 0) {
        $status = 200;
    }

    fwrite(STDERR, "\n__BH_META__" . json_encode([
        'status' => $status,
        'headers' => headers_list(),
    ]) . "__BH_END__\n");

    if (is_file($sessionFile)) {
        @unlink($sessionFile);
    }
});

require $basePath . '/public/index.php';
PHP;

        $process = proc_open(
            [PHP_BINARY, '-d', 'variables_order=EGPCS', '-r', $code, $payload],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            BASE_PATH
        );

        self::assertIsResource($process);

        fclose($pipes[0]);

        $body = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        self::assertMatchesRegularExpression('/__BH_META__(.*?)__BH_END__/s', $stderr, $stderr);
        preg_match('/__BH_META__(.*?)__BH_END__/s', $stderr, $matches);

        $meta = json_decode($matches[1], true);

        self::assertIsArray($meta);

        return [
            'status' => (int) ($meta['status'] ?? 0),
            'headers' => is_array($meta['headers'] ?? null) ? $meta['headers'] : [],
            'body' => $body === false ? '' : $body,
            'stderr' => $stderr === false ? '' : $stderr,
            'exit_code' => $exitCode,
        ];
    }

    private static function assertJsonError(string $body, string $expectedCode): void
    {
        $decoded = json_decode($body, true);

        self::assertIsArray($decoded, $body);
        self::assertFalse($decoded['ok']);
        self::assertSame($expectedCode, $decoded['error']['code']);
    }
}
