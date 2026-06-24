<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/models/MetaAhorro.php';

final class OwnershipScopingTest extends IntegrationTestCase
{
    public function testUsuarioNoPuedeLeerNiBorrarMetaDeOtroUsuario(): void
    {
        $duenio = $this->crearUsuario('owner.integration@example.test');
        $otroUsuario = $this->crearUsuario('attacker.integration@example.test');

        $metaId = \MetaAhorro::crear($duenio['id'], 'Colchon de emergencia', 3000, 150, '2027-01-01');

        self::assertNotFalse($metaId);
        self::assertNull(\MetaAhorro::obtenerPorIdYUsuario((int) $metaId, $otroUsuario['id']));

        self::assertTrue(\MetaAhorro::eliminarPorUsuario((int) $metaId, $otroUsuario['id']));

        $metaDelDuenio = \MetaAhorro::obtenerPorIdYUsuario((int) $metaId, $duenio['id']);

        self::assertIsArray($metaDelDuenio);
        self::assertSame('Colchon de emergencia', $metaDelDuenio['nombre']);

        self::assertTrue(\MetaAhorro::eliminarPorUsuario((int) $metaId, $duenio['id']));
        self::assertNull(\MetaAhorro::obtenerPorIdYUsuario((int) $metaId, $duenio['id']));
    }
}
