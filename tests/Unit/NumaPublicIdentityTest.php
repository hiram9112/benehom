<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/NumaPublicIdentity.php';

final class NumaPublicIdentityTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_COOKIE[\NumaPublicIdentity::COOKIE_NAME], $_ENV['NUMA_PUBLIC_HASH_KEY'], $_SERVER['HTTPS']);
        parent::tearDown();
    }

    public function testUsesOnlyAValidOpaqueCookieAndDerivesAnHmacHash(): void
    {
        $_ENV['NUMA_PUBLIC_HASH_KEY'] = 'test-public-hash-key';
        $_COOKIE[\NumaPublicIdentity::COOKIE_NAME] = str_repeat('a', 64);

        $identity = new \NumaPublicIdentity();

        self::assertSame(str_repeat('a', 64), $identity->token());
        self::assertSame(hash_hmac('sha256', str_repeat('a', 64), 'test-public-hash-key'), $identity->visitorHash());
        self::assertNotSame($identity->token(), $identity->visitorHash());
    }

    public function testInvalidCookieIsReplacedWithAFreshOpaqueToken(): void
    {
        $_ENV['NUMA_PUBLIC_HASH_KEY'] = 'test-public-hash-key';
        $_COOKIE[\NumaPublicIdentity::COOKIE_NAME] = 'invalid';

        $token = (new \NumaPublicIdentity())->token();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        self::assertNotSame('invalid', $token);
    }

    public function testIndicatesWhetherTheCookieWasCreatedDuringThisRequest(): void
    {
        $_ENV['NUMA_PUBLIC_HASH_KEY'] = 'test-public-hash-key';
        $_COOKIE[\NumaPublicIdentity::COOKIE_NAME] = str_repeat('a', 64);

        self::assertFalse((new \NumaPublicIdentity())->createdDuringRequest());

        unset($_COOKIE[\NumaPublicIdentity::COOKIE_NAME]);
        self::assertTrue((new \NumaPublicIdentity())->createdDuringRequest());
    }

    public function testHashKeyIsRequiredBeforePseudonymization(): void
    {
        $_COOKIE[\NumaPublicIdentity::COOKIE_NAME] = str_repeat('b', 64);

        $this->expectException(\RuntimeException::class);
        (new \NumaPublicIdentity())->visitorHash();
    }

    public function testCookieEmitidaTieneHttpOnlySameSiteLaxYSecureSoloSobreHttps(): void
    {
        $router = $this->writeRouter();
        $server = $this->startServer($router);

        try {
            $http = $this->requestSetCookie($server, false);
            $https = $this->requestSetCookie($server, true);

            self::assertStringContainsString('HttpOnly', $http);
            self::assertStringContainsString('SameSite=Lax', $http);
            self::assertStringNotContainsString('secure', $http);

            self::assertStringContainsString('HttpOnly', $https);
            self::assertStringContainsString('SameSite=Lax', $https);
            self::assertStringContainsString('secure', $https);
        } finally {
            $this->stopServer($server);
            @unlink($router);
        }
    }

    private function writeRouter(): string
    {
        $routerCode = <<<'PHP'
<?php
$secure = ($_GET['secure'] ?? '') === '1';
$_SERVER['HTTPS'] = $secure ? 'on' : 'off';
$_ENV['NUMA_PUBLIC_HASH_KEY'] = 'test-public-hash-key';
require __BASE__ . '/app/helpers/utils.php';
require __BASE__ . '/app/services/NumaPublicIdentity.php';
(new \NumaPublicIdentity())->token();
echo 'ok';
PHP;
        $routerCode = str_replace('__BASE__', var_export(BASE_PATH, true), $routerCode);

        $path = sys_get_temp_dir() . '/benehom-numa-cookie-' . bin2hex(random_bytes(8)) . '.php';
        file_put_contents($path, $routerCode);

        return $path;
    }

    /**
     * @return array{process:resource,pipes:array<int,resource>,port:int}
     */
    private function startServer(string $router): array
    {
        $port = $this->findFreePort();
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, $router],
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

        $this->waitForServer($port);

        return ['process' => $process, 'pipes' => $pipes, 'port' => $port];
    }

    /** @param array{process:resource,pipes:array<int,resource>,port:int} $server */
    private function stopServer(array $server): void
    {
        fclose($server['pipes'][1]);
        fclose($server['pipes'][2]);
        proc_terminate($server['process']);
        proc_close($server['process']);
    }

    /** @param array{process:resource,pipes:array<int,resource>,port:int} $server */
    private function requestSetCookie(array $server, bool $secure): string
    {
        $url = 'http://127.0.0.1:' . $server['port'] . '/?secure=' . ($secure ? '1' : '0');
        $context = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];

        self::assertSame('ok', $body, 'El servidor de prueba no devolvió la respuesta esperada.');

        foreach ($headers as $header) {
            if (stripos($header, 'Set-Cookie:') === 0) {
                return $header;
            }
        }

        self::fail('El servidor de prueba no emitió una cabecera Set-Cookie.');
    }

    private function findFreePort(): int
    {
        for ($i = 0; $i < 25; $i++) {
            $port = random_int(18000, 60000);
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($socket === false) {
                return $port;
            }

            fclose($socket);
        }

        self::fail('No se pudo encontrar un puerto libre para el servidor de prueba.');
    }

    private function waitForServer(int $port): void
    {
        $deadline = microtime(true) + 10;

        do {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(50000);
        } while (microtime(true) < $deadline);

        self::fail('El servidor PHP de prueba no arrancó a tiempo.');
    }
}
