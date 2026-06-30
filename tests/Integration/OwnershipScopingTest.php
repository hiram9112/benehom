<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/models/MetaAhorro.php';
require_once APP_PATH . '/models/Gasto.php';
require_once APP_PATH . '/models/Ingreso.php';

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

    public function testUsuarioNoPuedeEliminarGastoDeOtroUsuario(): void
    {
        $duenio = $this->crearUsuario('gasto-owner.integration@example.test');
        $otroUsuario = $this->crearUsuario('gasto-attacker.integration@example.test');

        $gastoId = \Gasto::agregarGasto($duenio['id'], 'flexible', 'ocio_entretenimiento', 50, '2026-02-10');

        self::assertNotFalse($gastoId);

        // El atacante NO puede eliminar el gasto ajeno
        self::assertFalse(\Gasto::eliminarGasto((int) $gastoId, $otroUsuario['id']));

        // El gasto sigue existiendo y pertenece al duenio
        $gastos = \Gasto::obtenerTodosPorUsuario($duenio['id']);
        self::assertCount(1, $gastos);

        // El duenio SI puede eliminar su propio gasto
        self::assertTrue(\Gasto::eliminarGasto((int) $gastoId, $duenio['id']));
        self::assertCount(0, \Gasto::obtenerTodosPorUsuario($duenio['id']));
    }

    public function testUsuarioNoPuedeActualizarGastoDeOtroUsuario(): void
    {
        $duenio = $this->crearUsuario('gasto-edit-owner.integration@example.test');
        $otroUsuario = $this->crearUsuario('gasto-edit-attacker.integration@example.test');

        $gastoId = \Gasto::agregarGasto($duenio['id'], 'esencial', 'alquiler_hipoteca', 700, '2026-02-01');

        self::assertNotFalse($gastoId);

        // El atacante NO puede actualizar el gasto ajeno
        self::assertFalse(\Gasto::actualizarGasto((int) $gastoId, $otroUsuario['id'], 1));

        // La cantidad del duenio permanece intacta
        $gastos = \Gasto::obtenerTodosPorUsuario($duenio['id']);
        self::assertCount(1, $gastos);
        self::assertSame('700.00', $gastos[0]['cantidad']);

        // El duenio SI puede actualizar su propio gasto
        self::assertTrue(\Gasto::actualizarGasto((int) $gastoId, $duenio['id'], 750));
        $gastos = \Gasto::obtenerTodosPorUsuario($duenio['id']);
        self::assertSame('750.00', $gastos[0]['cantidad']);
    }

    public function testUsuarioNoPuedeEliminarIngresoDeOtroUsuario(): void
    {
        $duenio = $this->crearUsuario('ingreso-owner.integration@example.test');
        $otroUsuario = $this->crearUsuario('ingreso-attacker.integration@example.test');

        $ingresoId = \Ingreso::agregarIngreso($duenio['id'], 'salario', 1500, '2026-02-15');

        self::assertNotFalse($ingresoId);

        // El atacante NO puede eliminar el ingreso ajeno
        self::assertFalse(\Ingreso::eliminarIngreso((int) $ingresoId, $otroUsuario['id']));

        // El ingreso sigue existiendo y pertenece al duenio
        self::assertCount(1, \Ingreso::obtenerTodosPorUsuario($duenio['id']));

        // El duenio SI puede eliminar su propio ingreso
        self::assertTrue(\Ingreso::eliminarIngreso((int) $ingresoId, $duenio['id']));
        self::assertCount(0, \Ingreso::obtenerTodosPorUsuario($duenio['id']));
    }

    public function testUsuarioNoPuedeActualizarIngresoDeOtroUsuario(): void
    {
        $duenio = $this->crearUsuario('ingreso-edit-owner.integration@example.test');
        $otroUsuario = $this->crearUsuario('ingreso-edit-attacker.integration@example.test');

        $ingresoId = \Ingreso::agregarIngreso($duenio['id'], 'salario', 1500, '2026-02-15');

        self::assertNotFalse($ingresoId);

        // El atacante NO puede actualizar el ingreso ajeno
        self::assertFalse(\Ingreso::actualizarIngreso((int) $ingresoId, $otroUsuario['id'], 1));

        // La cantidad del duenio permanece intacta
        $ingresos = \Ingreso::obtenerTodosPorUsuario($duenio['id']);
        self::assertCount(1, $ingresos);
        self::assertSame('1500.00', $ingresos[0]['cantidad']);

        // El duenio SI puede actualizar su propio ingreso
        self::assertTrue(\Ingreso::actualizarIngreso((int) $ingresoId, $duenio['id'], 1600));
        $ingresos = \Ingreso::obtenerTodosPorUsuario($duenio['id']);
        self::assertSame('1600.00', $ingresos[0]['cantidad']);
    }
}
