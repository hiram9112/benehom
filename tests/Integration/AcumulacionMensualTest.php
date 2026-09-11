<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/controllers/GastoController.php';
require_once APP_PATH . '/controllers/IngresoController.php';

final class AcumulacionMensualTest extends IntegrationTestCase
{
    private string $metodoOriginal;
    private array $postBackup = [];
    private array $sessionBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->metodoOriginal = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->postBackup = $_POST;
        $this->sessionBackup = is_array($_SESSION ?? null) ? $_SESSION : [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [];
        $_SESSION = ['usuario_id' => null];
    }

    protected function tearDown(): void
    {
        $_POST = $this->postBackup;
        $_SESSION = $this->sessionBackup;
        $_SERVER['REQUEST_METHOD'] = $this->metodoOriginal;

        parent::tearDown();
    }

    private function invocar(string $controlador, string $metodo, array $post, int $usuarioId): array
    {
        $_POST = $post;
        $_SESSION['usuario_id'] = $usuarioId;
        $instancia = new $controlador();

        ob_start();
        $instancia->{$metodo}();
        $salida = ob_get_clean();
        $respuesta = json_decode((string) $salida, true);

        return is_array($respuesta) ? $respuesta : ['ok' => false];
    }

    public function testIngresoNuevoSeCreaSinConfirmacion(): void
    {
        $usuario = $this->crearUsuario('acum-ingreso-nuevo@test.local');

        $respuesta = $this->invocar(\IngresoController::class, 'agregarAjax', [
            'categoria_ingreso' => 'salario',
            'cantidad_ingreso' => '1500',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertTrue($respuesta['ok']);
        self::assertArrayNotHasKey('requiere_confirmacion', $respuesta);
        self::assertCount(1, \Ingreso::obtenerPorMes($usuario['id'], '2026-05-01', '2026-05-31'));
    }

    public function testIngresoExistenteRequiereConfirmacionSinModificarlo(): void
    {
        $usuario = $this->crearUsuario('acum-ingreso-confirmar@test.local');
        \Ingreso::agregarIngreso($usuario['id'], 'salario', '1500', '2026-05-01');

        $respuesta = $this->invocar(\IngresoController::class, 'agregarAjax', [
            'categoria_ingreso' => 'salario',
            'cantidad_ingreso' => '200',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertTrue($respuesta['requiere_confirmacion']);
        self::assertSame('salario', $respuesta['confirmacion']['categoria']);
        self::assertSame('1500.00', $respuesta['confirmacion']['cantidad_actual']);
        self::assertSame('2026-05', $respuesta['confirmacion']['mes']);
        self::assertSame('200', $respuesta['confirmacion']['cantidad_nueva']);

        $ingresos = \Ingreso::obtenerPorMes($usuario['id'], '2026-05-01', '2026-05-31');
        self::assertCount(1, $ingresos);
        self::assertSame('1500.00', $ingresos[0]['cantidad']);
    }

    public function testConfirmarIngresoAcumulaEnLaFilaMensualExistente(): void
    {
        $usuario = $this->crearUsuario('acum-ingreso-ok@test.local');
        \Ingreso::agregarIngreso($usuario['id'], 'salario', '1500', '2026-05-01');

        $respuesta = $this->invocar(\IngresoController::class, 'agregarAjax', [
            'categoria_ingreso' => 'salario',
            'cantidad_ingreso' => '200',
            'mes_seleccionado' => '2026-05',
            'confirmar_acumulacion' => '1',
        ], $usuario['id']);

        self::assertTrue($respuesta['ok']);
        self::assertTrue($respuesta['acumulado']);
        self::assertSame('1700.00', $respuesta['ingreso']['cantidad']);

        $ingresos = \Ingreso::obtenerPorMes($usuario['id'], '2026-05-01', '2026-05-31');
        self::assertCount(1, $ingresos);
        self::assertSame('1700.00', $ingresos[0]['cantidad']);
    }

    public function testIngresoEnOtroMesSeMantieneSeparado(): void
    {
        $usuario = $this->crearUsuario('acum-ingreso-meses@test.local');
        \Ingreso::agregarIngreso($usuario['id'], 'salario', '1500', '2026-04-01');

        $respuesta = $this->invocar(\IngresoController::class, 'agregarAjax', [
            'categoria_ingreso' => 'salario',
            'cantidad_ingreso' => '1600',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertTrue($respuesta['ok']);
        self::assertCount(1, \Ingreso::obtenerPorMes($usuario['id'], '2026-04-01', '2026-04-30'));
        self::assertCount(1, \Ingreso::obtenerPorMes($usuario['id'], '2026-05-01', '2026-05-31'));
    }

    public function testGastoEsencialExistenteRequiereConfirmacionYSeAcumula(): void
    {
        $usuario = $this->crearUsuario('acum-gasto-esencial@test.local');
        \Gasto::agregarGasto($usuario['id'], 'esencial', 'alquiler_hipoteca', '800', '2026-05-01');

        $pendiente = $this->invocar(\GastoController::class, 'agregarGastoEsencialAjax', [
            'categoria_gasto_esencial' => 'alquiler_hipoteca',
            'cantidad_gasto_esencial' => '50',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertTrue($pendiente['requiere_confirmacion']);
        self::assertSame('800.00', $pendiente['confirmacion']['cantidad_actual']);

        $respuesta = $this->invocar(\GastoController::class, 'agregarGastoEsencialAjax', [
            'categoria_gasto_esencial' => 'alquiler_hipoteca',
            'cantidad_gasto_esencial' => '50',
            'mes_seleccionado' => '2026-05',
            'confirmar_acumulacion' => '1',
        ], $usuario['id']);

        self::assertTrue($respuesta['ok']);
        self::assertSame('850.00', $respuesta['gasto_esencial']['cantidad']);
        self::assertCount(1, \Gasto::obtenerPorMes($usuario['id'], 'esencial', '2026-05-01', '2026-05-31'));
    }

    public function testGastoFlexibleNoSeMezclaConGastoEsencial(): void
    {
        $usuario = $this->crearUsuario('acum-gasto-tipo@test.local');
        \Gasto::agregarGasto($usuario['id'], 'esencial', 'misma_categoria', '800', '2026-05-01');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'misma_categoria', '50', '2026-05-01');

        $gasto = \Gasto::acumularGastoMensual($usuario['id'], 'flexible', 'misma_categoria', '20', '2026-05-01');

        self::assertIsArray($gasto);
        self::assertSame('70.00', $gasto['cantidad']);
        self::assertSame('800.00', \Gasto::obtenerGastoMensual($usuario['id'], 'esencial', 'misma_categoria', '2026-05-01')['cantidad']);
    }

    public function testConfirmacionNoPermiteAcumularElIngresoDeOtroUsuario(): void
    {
        $duenio = $this->crearUsuario('acum-duenio@test.local');
        $atacante = $this->crearUsuario('acum-atacante@test.local');
        \Ingreso::agregarIngreso($duenio['id'], 'salario', '1500', '2026-05-01');

        $respuesta = $this->invocar(\IngresoController::class, 'agregarAjax', [
            'categoria_ingreso' => 'salario',
            'cantidad_ingreso' => '200',
            'mes_seleccionado' => '2026-05',
            'confirmar_acumulacion' => '1',
        ], $atacante['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('1500.00', \Ingreso::obtenerTodosPorUsuario($duenio['id'])[0]['cantidad']);
        self::assertCount(0, \Ingreso::obtenerTodosPorUsuario($atacante['id']));
    }

    public function testConfirmacionVuelveAValidarLaIdentidadMensual(): void
    {
        $usuario = $this->crearUsuario('acum-revalidar@test.local');
        $ingresoId = \Ingreso::agregarIngreso($usuario['id'], 'salario', '1500', '2026-05-01');

        $pendiente = $this->invocar(\IngresoController::class, 'agregarAjax', [
            'categoria_ingreso' => 'salario',
            'cantidad_ingreso' => '200',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertTrue($pendiente['requiere_confirmacion']);
        self::assertTrue(\Ingreso::eliminarIngreso((int) $ingresoId, $usuario['id']));

        $respuesta = $this->invocar(\IngresoController::class, 'agregarAjax', [
            'categoria_ingreso' => 'salario',
            'cantidad_ingreso' => '200',
            'mes_seleccionado' => '2026-05',
            'confirmar_acumulacion' => '1',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertCount(0, \Ingreso::obtenerPorMes($usuario['id'], '2026-05-01', '2026-05-31'));
    }
}
