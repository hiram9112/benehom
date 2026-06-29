<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/controllers/AuthController.php';

final class EmailVerificationTest extends IntegrationTestCase
{
    public function testTokenVerificacionValidoSeRecupera(): void
    {
        $usuario = $this->crearUsuario('verify-valido.integration@example.test');
        $token = 'token-verificacion-valido';
        $tokenHash = hash('sha256', $token);

        self::assertTrue(\Usuario::guardarTokenVerificacion($usuario['id'], $tokenHash, '2099-01-01 10:00:00'));

        $recuperado = \Usuario::obtenerUsuarioPorTokenVerificacion($tokenHash);

        self::assertIsArray($recuperado);
        self::assertSame($usuario['id'], $recuperado['id']);
        self::assertSame($tokenHash, $recuperado['email_verification_token_hash']);
        self::assertNotSame($token, $recuperado['email_verification_token_hash']);
    }

    public function testTokenVerificacionExpiradoNoSeRecupera(): void
    {
        $usuario = $this->crearUsuario('verify-expirado.integration@example.test');
        $tokenHash = hash('sha256', 'token-verificacion-expirado');

        self::assertTrue(\Usuario::guardarTokenVerificacion($usuario['id'], $tokenHash, '2000-01-01 10:00:00'));

        self::assertFalse(\Usuario::obtenerUsuarioPorTokenVerificacion($tokenHash));
    }

    public function testMarcarEmailVerificadoActivaCuentaYLimpiaToken(): void
    {
        $usuario = $this->crearUsuario('verify-marcar.integration@example.test');
        $tokenHash = hash('sha256', 'token-verificacion-marcar');

        self::assertTrue(\Usuario::guardarTokenVerificacion($usuario['id'], $tokenHash, '2099-01-01 10:00:00'));
        self::assertTrue(\Usuario::marcarEmailVerificado($usuario['id']));

        $actualizado = \Usuario::obtenerUsuario('verify-marcar.integration@example.test');

        self::assertIsArray($actualizado);
        self::assertNotEmpty($actualizado['email_verificado_en']);
        self::assertNull($actualizado['email_verification_token_hash']);
        self::assertNull($actualizado['email_verification_expires_at']);
        self::assertFalse(\Usuario::obtenerUsuarioPorTokenVerificacion($tokenHash));
    }

    public function testLoginQuedaBloqueadoMientrasEmailNoEsteVerificado(): void
    {
        $usuario = $this->crearUsuario('verify-login.integration@example.test', 'Password-verificada-123');
        $auth = new class extends \AuthController {
            public function puedeIniciarSesion(array $user): bool
            {
                return $this->emailVerificadoParaLogin($user);
            }
        };

        self::assertTrue(password_verify('Password-verificada-123', $usuario['password']));
        self::assertNull($usuario['email_verificado_en']);
        self::assertFalse($auth->puedeIniciarSesion($usuario));

        self::assertTrue(\Usuario::marcarEmailVerificado($usuario['id']));

        $verificado = \Usuario::obtenerUsuario('verify-login.integration@example.test');

        self::assertIsArray($verificado);
        self::assertTrue($auth->puedeIniciarSesion($verificado));
    }
}
