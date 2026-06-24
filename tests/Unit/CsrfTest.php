<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetGlobals();
    }

    protected function tearDown(): void
    {
        $this->resetGlobals();

        parent::tearDown();
    }

    public function testCsrfValidateRechazaTokenAusente(): void
    {
        $_SESSION['csrf_token'] = 'token-de-sesion';

        self::assertFalse(\csrf_validate());
    }

    public function testCsrfValidateRechazaTokenIncorrecto(): void
    {
        $_SESSION['csrf_token'] = 'token-de-sesion';
        $_POST['_csrf'] = 'token-incorrecto';

        self::assertFalse(\csrf_validate());
    }

    public function testCsrfValidateAceptaTokenCorrecto(): void
    {
        $_SESSION['csrf_token'] = 'token-de-sesion';
        $_POST['_csrf'] = 'token-de-sesion';

        self::assertTrue(\csrf_validate());
    }

    public function testCsrfTokenEsEstableDentroDeLaMismaSesion(): void
    {
        self::assertTrue(@session_start());

        $primerToken = \csrf_token();
        $segundoToken = \csrf_token();

        self::assertSame($primerToken, $segundoToken);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $primerToken);
    }

    private function resetGlobals(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        $_SESSION = [];
        $_POST = [];
    }
}
