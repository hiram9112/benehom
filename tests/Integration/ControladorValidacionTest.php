<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/controllers/GastoController.php';
require_once APP_PATH . '/controllers/IngresoController.php';

final class ControladorValidacionTest extends IntegrationTestCase
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

    /**
     * @param class-string $controlador
     * @return array{ok:bool, msg?:string}
     */
    private function invocar(string $controlador, string $metodo, array $post, ?int $usuarioId): array
    {
        $_POST = $post;
        $_SESSION['usuario_id'] = $usuarioId;

        $instancia = new $controlador();

        ob_start();
        $instancia->{$metodo}();
        $salida = ob_get_clean();

        $decoded = json_decode((string) $salida, true);

        return is_array($decoded) ? $decoded : ['ok' => false, 'msg' => 'Respuesta no JSON: ' . (string) $salida];
    }

    public function testAgregarGastoEsencialSinSesionDevuelveSesionNoValida(): void
    {
        $respuesta = $this->invocar(\GastoController::class, 'agregarGastoEsencialAjax', [
            'categoria_gasto_esencial' => 'alquiler_hipoteca',
            'cantidad_gasto_esencial' => '100',
            'mes_seleccionado' => '2026-05',
        ], null);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Sesión no válida', $respuesta['msg']);
    }

    public function testAgregarGastoEsencialRechazaCantidadNegativa(): void
    {
        $usuario = $this->crearUsuario('ctrl-esencial-neg.integration@example.test');

        $respuesta = $this->invocar(\GastoController::class, 'agregarGastoEsencialAjax', [
            'categoria_gasto_esencial' => 'alquiler_hipoteca',
            'cantidad_gasto_esencial' => '-50',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Datos inválidos', $respuesta['msg']);
    }

    public function testAgregarGastoEsencialRechazaCantidadCero(): void
    {
        $usuario = $this->crearUsuario('ctrl-esencial-zero.integration@example.test');

        $respuesta = $this->invocar(\GastoController::class, 'agregarGastoEsencialAjax', [
            'categoria_gasto_esencial' => 'alquiler_hipoteca',
            'cantidad_gasto_esencial' => '0',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Datos inválidos', $respuesta['msg']);
    }

    public function testAgregarGastoEsencialRechazaCantidadVacia(): void
    {
        $usuario = $this->crearUsuario('ctrl-esencial-empty.integration@example.test');

        $respuesta = $this->invocar(\GastoController::class, 'agregarGastoEsencialAjax', [
            'categoria_gasto_esencial' => 'alquiler_hipoteca',
            'cantidad_gasto_esencial' => '',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Datos inválidos', $respuesta['msg']);
    }

    public function testAgregarGastoEsencialRechazaCantidadNoNumerica(): void
    {
        $usuario = $this->crearUsuario('ctrl-esencial-nan.integration@example.test');

        $respuesta = $this->invocar(\GastoController::class, 'agregarGastoEsencialAjax', [
            'categoria_gasto_esencial' => 'alquiler_hipoteca',
            'cantidad_gasto_esencial' => 'abc',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Datos inválidos', $respuesta['msg']);
    }

    public function testAgregarGastoEsencialRechazaCategoriaFlexible(): void
    {
        $usuario = $this->crearUsuario('ctrl-esencial-tipoflex.integration@example.test');

        $respuesta = $this->invocar(\GastoController::class, 'agregarGastoEsencialAjax', [
            'categoria_gasto_esencial' => 'ocio_entretenimiento',
            'cantidad_gasto_esencial' => '50',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Selecciona una categoría válida.', $respuesta['msg']);
    }

    public function testAgregarGastoEsencialRechazaCategoriaVacia(): void
    {
        $usuario = $this->crearUsuario('ctrl-esencial-catvacia.integration@example.test');

        $respuesta = $this->invocar(\GastoController::class, 'agregarGastoEsencialAjax', [
            'categoria_gasto_esencial' => '',
            'cantidad_gasto_esencial' => '50',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Datos inválidos', $respuesta['msg']);
    }

    public function testAgregarGastoFlexibleRechazaCategoriaEsencial(): void
    {
        $usuario = $this->crearUsuario('ctrl-flexible-tipoesenc.integration@example.test');

        $respuesta = $this->invocar(\GastoController::class, 'agregarGastoFlexibleAjax', [
            'categoria_gasto_flexible' => 'alquiler_hipoteca',
            'cantidad_gasto_flexible' => '50',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Selecciona una categoría válida.', $respuesta['msg']);
    }

    public function testAgregarIngresoRechazaCantidadNegativa(): void
    {
        $usuario = $this->crearUsuario('ctrl-ingreso-neg.integration@example.test');

        $respuesta = $this->invocar(\IngresoController::class, 'agregarAjax', [
            'categoria_ingreso' => 'salario',
            'cantidad_ingreso' => '-100',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Datos inválidos', $respuesta['msg']);
    }

    public function testAgregarIngresoRechazaCantidadCero(): void
    {
        $usuario = $this->crearUsuario('ctrl-ingreso-zero.integration@example.test');

        $respuesta = $this->invocar(\IngresoController::class, 'agregarAjax', [
            'categoria_ingreso' => 'salario',
            'cantidad_ingreso' => '0',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Datos inválidos', $respuesta['msg']);
    }

    public function testAgregarIngresoRechazaCantidadVacia(): void
    {
        $usuario = $this->crearUsuario('ctrl-ingreso-empty.integration@example.test');

        $respuesta = $this->invocar(\IngresoController::class, 'agregarAjax', [
            'categoria_ingreso' => 'salario',
            'cantidad_ingreso' => '',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Datos inválidos', $respuesta['msg']);
    }

    public function testAgregarIngresoRechazaCantidadNoNumerica(): void
    {
        $usuario = $this->crearUsuario('ctrl-ingreso-nan.integration@example.test');

        $respuesta = $this->invocar(\IngresoController::class, 'agregarAjax', [
            'categoria_ingreso' => 'salario',
            'cantidad_ingreso' => '1,2,3',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Datos inválidos', $respuesta['msg']);
    }

    public function testAgregarIngresoRechazaCategoriaDeGasto(): void
    {
        $usuario = $this->crearUsuario('ctrl-ingreso-catgasto.integration@example.test');

        $respuesta = $this->invocar(\IngresoController::class, 'agregarAjax', [
            'categoria_ingreso' => 'alquiler_hipoteca',
            'cantidad_ingreso' => '1500',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Categoría de ingreso no válida', $respuesta['msg']);
    }

    public function testAgregarIngresoSinSesionDevuelveSesionNoValida(): void
    {
        $respuesta = $this->invocar(\IngresoController::class, 'agregarAjax', [
            'categoria_ingreso' => 'salario',
            'cantidad_ingreso' => '1500',
            'mes_seleccionado' => '2026-05',
        ], null);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Sesión no válida', $respuesta['msg']);
    }

    public function testEditarGastoRechazaCantidadNegativa(): void
    {
        $usuario = $this->crearUsuario('ctrl-edit-gasto-neg.integration@example.test');
        $gastoId = \Gasto::agregarGasto($usuario['id'], 'esencial', 'alquiler_hipoteca', 700, '2026-05-01');

        self::assertNotFalse($gastoId);

        $respuesta = $this->invocar(\GastoController::class, 'editarGastoAjax', [
            'id' => (string) $gastoId,
            'cantidad' => '-1',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Datos inválidos', $respuesta['msg']);
    }

    public function testEditarGastoRechazaIdInvalido(): void
    {
        $usuario = $this->crearUsuario('ctrl-edit-gasto-idbad.integration@example.test');

        $respuesta = $this->invocar(\GastoController::class, 'editarGastoAjax', [
            'id' => '0',
            'cantidad' => '100',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Datos inválidos', $respuesta['msg']);
    }

    public function testEditarGastoSinSesionDevuelveSesionNoValida(): void
    {
        $respuesta = $this->invocar(\GastoController::class, 'editarGastoAjax', [
            'id' => '1',
            'cantidad' => '100',
        ], null);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Sesión no válida', $respuesta['msg']);
    }

    public function testEditarIngresoRechazaCantidadNegativa(): void
    {
        $usuario = $this->crearUsuario('ctrl-edit-ingreso-neg.integration@example.test');
        $ingresoId = \Ingreso::agregarIngreso($usuario['id'], 'salario', 1500, '2026-05-01');

        self::assertNotFalse($ingresoId);

        $respuesta = $this->invocar(\IngresoController::class, 'editarAjax', [
            'id' => (string) $ingresoId,
            'cantidad' => '-1',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Datos inválidos', $respuesta['msg']);
    }

    public function testEditarIngresoSinSesionDevuelveSesionNoValida(): void
    {
        $respuesta = $this->invocar(\IngresoController::class, 'editarAjax', [
            'id' => '1',
            'cantidad' => '100',
        ], null);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Sesión no válida', $respuesta['msg']);
    }

    public function testEditarGastoAjaxRechazaMovimientoDeOtroUsuario(): void
    {
        $duenio = $this->crearUsuario('idor-eg-o@example.test');
        $atacante = $this->crearUsuario('idor-eg-a@example.test');
        $gastoId = \Gasto::agregarGasto($duenio['id'], 'esencial', 'alquiler_hipoteca', 700, '2026-05-01');

        self::assertNotFalse($gastoId);

        $respuesta = $this->invocar(\GastoController::class, 'editarGastoAjax', [
            'id' => (string) $gastoId,
            'cantidad' => '1',
        ], $atacante['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('No se encontró el gasto o no tienes permiso para actualizarlo', $respuesta['msg']);

        $gastos = \Gasto::obtenerTodosPorUsuario($duenio['id']);
        self::assertCount(1, $gastos);
        self::assertSame('700.00', $gastos[0]['cantidad']);
    }

    public function testEditarIngresoAjaxRechazaMovimientoDeOtroUsuario(): void
    {
        $duenio = $this->crearUsuario('idor-ei-o@example.test');
        $atacante = $this->crearUsuario('idor-ei-a@example.test');
        $ingresoId = \Ingreso::agregarIngreso($duenio['id'], 'salario', 1500, '2026-05-01');

        self::assertNotFalse($ingresoId);

        $respuesta = $this->invocar(\IngresoController::class, 'editarAjax', [
            'id' => (string) $ingresoId,
            'cantidad' => '1',
        ], $atacante['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('No se encontró el ingreso o no tienes permiso para actualizarlo', $respuesta['msg']);

        $ingresos = \Ingreso::obtenerTodosPorUsuario($duenio['id']);
        self::assertCount(1, $ingresos);
        self::assertSame('1500.00', $ingresos[0]['cantidad']);
    }

    public function testEliminarGastoSinSesionDevuelveSesionNoValida(): void
    {
        $respuesta = $this->invocar(\GastoController::class, 'eliminarGastoAjax', [
            'id' => '1',
        ], null);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Sesión no válida', $respuesta['msg']);
    }

    public function testEliminarIngresoSinSesionDevuelveSesionNoValida(): void
    {
        $respuesta = $this->invocar(\IngresoController::class, 'eliminarAjax', [
            'id' => '1',
        ], null);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Sesión no válida', $respuesta['msg']);
    }

    public function testEliminarGastoAjaxRechazaMovimientoDeOtroUsuario(): void
    {
        $duenio = $this->crearUsuario('idor-dg-o@example.test');
        $atacante = $this->crearUsuario('idor-dg-a@example.test');
        $gastoId = \Gasto::agregarGasto($duenio['id'], 'flexible', 'ocio_entretenimiento', 50, '2026-05-01');

        self::assertNotFalse($gastoId);

        $respuesta = $this->invocar(\GastoController::class, 'eliminarGastoAjax', [
            'id' => (string) $gastoId,
        ], $atacante['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('No se encontró el gasto o no tienes permiso para eliminarlo', $respuesta['msg']);
        self::assertCount(1, \Gasto::obtenerTodosPorUsuario($duenio['id']));
    }

    public function testEliminarIngresoAjaxRechazaMovimientoDeOtroUsuario(): void
    {
        $duenio = $this->crearUsuario('idor-di-o@example.test');
        $atacante = $this->crearUsuario('idor-di-a@example.test');
        $ingresoId = \Ingreso::agregarIngreso($duenio['id'], 'salario', 1500, '2026-05-01');

        self::assertNotFalse($ingresoId);

        $respuesta = $this->invocar(\IngresoController::class, 'eliminarAjax', [
            'id' => (string) $ingresoId,
        ], $atacante['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('No se encontró el ingreso o no tienes permiso para eliminarlo', $respuesta['msg']);
        self::assertCount(1, \Ingreso::obtenerTodosPorUsuario($duenio['id']));
    }

    public function testEliminarGastoRechazaIdCero(): void
    {
        $usuario = $this->crearUsuario('ctrl-del-gasto-zero.integration@example.test');

        $respuesta = $this->invocar(\GastoController::class, 'eliminarGastoAjax', [
            'id' => '0',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('ID no recibido', $respuesta['msg']);
    }

    public function testEliminarIngresoRechazaIdCero(): void
    {
        $usuario = $this->crearUsuario('ctrl-del-ingreso-zero.integration@example.test');

        $respuesta = $this->invocar(\IngresoController::class, 'eliminarAjax', [
            'id' => '0',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('ID no recibido', $respuesta['msg']);
    }
}
