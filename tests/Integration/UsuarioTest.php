<?php

declare(strict_types=1);

namespace Tests\Integration;

final class UsuarioTest extends IntegrationTestCase
{
    public function testRegistrarGuardaHashVerificableYObtenerUsuarioRecuperaPorEmail(): void
    {
        $password = 'Password-segura-123';

        self::assertTrue(\Usuario::registrar('Demo', 'demo.integration@example.test', $password));

        $usuario = \Usuario::obtenerUsuario('demo.integration@example.test');

        self::assertIsArray($usuario);
        self::assertSame('Demo', $usuario['usuario']);
        self::assertSame('demo.integration@example.test', $usuario['email']);
        self::assertNotSame($password, $usuario['password']);
        self::assertTrue(password_verify($password, $usuario['password']));
    }

    public function testRegistrarEmailDuplicadoDevuelveFalseSinExcepcion(): void
    {
        self::assertTrue(\Usuario::registrar('Demo', 'duplicado.integration@example.test', 'Password-1'));

        self::assertFalse(\Usuario::registrar('Demo 2', 'duplicado.integration@example.test', 'Password-2'));
    }
}
