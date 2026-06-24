<?php

declare(strict_types=1);

namespace Tests\Integration;

final class PasswordResetTest extends IntegrationTestCase
{
    public function testTokenResetValidoSeRecupera(): void
    {
        $usuario = $this->crearUsuario('reset-valido.integration@example.test');
        $tokenHash = hash('sha256', 'token-valido');

        self::assertTrue(\Usuario::guardarTokenReset($usuario['id'], $tokenHash, '2099-01-01 10:00:00'));

        $recuperado = \Usuario::obtenerUsuarioPorTokenReset($tokenHash);

        self::assertIsArray($recuperado);
        self::assertSame($usuario['id'], $recuperado['id']);
        self::assertSame($tokenHash, $recuperado['reset_token_hash']);
    }

    public function testTokenResetExpiradoNoSeRecupera(): void
    {
        $usuario = $this->crearUsuario('reset-expirado.integration@example.test');
        $tokenHash = hash('sha256', 'token-expirado');

        self::assertTrue(\Usuario::guardarTokenReset($usuario['id'], $tokenHash, '2000-01-01 10:00:00'));

        self::assertFalse(\Usuario::obtenerUsuarioPorTokenReset($tokenHash));
    }

    public function testLimpiarTokenResetLoElimina(): void
    {
        $usuario = $this->crearUsuario('reset-limpiar.integration@example.test');
        $tokenHash = hash('sha256', 'token-a-limpiar');

        self::assertTrue(\Usuario::guardarTokenReset($usuario['id'], $tokenHash, '2099-01-01 10:00:00'));
        self::assertTrue(\Usuario::limpiarTokenReset($usuario['id']));

        $actualizado = \Usuario::obtenerUsuario('reset-limpiar.integration@example.test');

        self::assertIsArray($actualizado);
        self::assertNull($actualizado['reset_token_hash']);
        self::assertNull($actualizado['reset_token_expires_at']);
        self::assertFalse(\Usuario::obtenerUsuarioPorTokenReset($tokenHash));
    }
}
