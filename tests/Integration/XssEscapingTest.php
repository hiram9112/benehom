<?php

declare(strict_types=1);

namespace Tests\Integration;

final class XssEscapingTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        parent::tearDown();
    }

    public function testCuentaEscapaNombreYEmailPersistidos(): void
    {
        $_SESSION['usuario_id'] = 1;
        $_SESSION['usuario'] = 'Usuario test';

        $nombreUsuario = '<script>alert("xss")</script><strong>Nombre</strong>';
        $emailUsuario = 'xss"><img src=x onerror=alert(1)>@example.test';
        $fechaRegistro = '2026-07-01 10:00:00';

        ob_start();
        require APP_PATH . '/views/cuenta.php';
        $html = (string) ob_get_clean();

        self::assertStringNotContainsString('<script>alert("xss")</script>', $html);
        self::assertStringNotContainsString('<strong>Nombre</strong>', $html);
        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;&lt;strong&gt;Nombre&lt;/strong&gt;', $html);
        self::assertStringContainsString('xss&quot;&gt;&lt;img src=x onerror=alert(1)&gt;@example.test', $html);
    }
}
