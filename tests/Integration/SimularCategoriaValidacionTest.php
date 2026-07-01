<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/models/Gasto.php';
require_once APP_PATH . '/models/Ingreso.php';
require_once APP_PATH . '/models/MetaAhorro.php';
require_once APP_PATH . '/models/EscenarioInversion.php';
require_once APP_PATH . '/models/InflacionProyeccion.php';
require_once APP_PATH . '/models/CalculadoraHipoteca.php';
require_once APP_PATH . '/controllers/ProyeccionesController.php';

final class SimularCategoriaValidacionTest extends IntegrationTestCase
{
    private string $metodoOriginal;
    private array $postBackup = [];
    private array $sessionBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->metodoOriginal = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->postBackup = $_POST;
        $this->sessionBackup = $_SESSION;

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
     * @return array{ok:bool, msg?:string, data?:array<string,mixed>}
     */
    private function invocar(array $post, ?int $usuarioId): array
    {
        $_POST = $post;
        $_SESSION['usuario_id'] = $usuarioId;

        $instancia = new \ProyeccionesController();

        ob_start();
        $instancia->simularCategoriaAjax();
        $salida = ob_get_clean();

        $decoded = json_decode((string) $salida, true);

        return is_array($decoded) ? $decoded : ['ok' => false, 'msg' => 'Respuesta no JSON: ' . (string) $salida];
    }

    public function testSinSesionDevuelveSesionNoValida(): void
    {
        $respuesta = $this->invocar([
            'categoria' => 'ocio_entretenimiento',
            'mes' => '2026-05',
        ], null);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Sesión no válida', $respuesta['msg']);
    }

    public function testMesInvalidoDevuelveMesNoValido(): void
    {
        $usuario = $this->crearUsuario('sim-mes-invalid.integration@example.test');

        $casosInvalidos = [
            'vacio' => '',
            'mes 13' => '2026-13',
            'formato corto' => '2026-5',
            'separador' => '2026/05',
            'con hora' => '2026-05-15',
        ];

        foreach ($casosInvalidos as $caso => $valor) {
            $respuesta = $this->invocar([
                'categoria' => 'ocio_entretenimiento',
                'mes' => $valor,
            ], $usuario['id']);

            self::assertFalse($respuesta['ok'] ?? true, "Caso '$caso' con mes '$valor' debería ser rechazado.");
            self::assertSame('Mes no válido', $respuesta['msg'] ?? null, "Caso '$caso': mensaje incorrecto.");
        }
    }

    public function testCategoriaVaciaDevuelveCategoriaNoValida(): void
    {
        $usuario = $this->crearUsuario('sim-catvacia.integration@example.test');

        $respuesta = $this->invocar([
            'categoria' => '',
            'mes' => '2026-05',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Categoría no válida', $respuesta['msg']);
    }

    public function testCategoriaEsencialRechazadaEnSimulacionFlexible(): void
    {
        $usuario = $this->crearUsuario('sim-catesencial.integration@example.test');

        $respuesta = $this->invocar([
            'categoria' => 'alquiler_hipoteca',
            'mes' => '2026-05',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Categoría no válida', $respuesta['msg']);
    }

    public function testCategoriaDeIngresoRechazadaEnSimulacion(): void
    {
        $usuario = $this->crearUsuario('sim-catingreso.integration@example.test');

        $respuesta = $this->invocar([
            'categoria' => 'salario',
            'mes' => '2026-05',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Categoría no válida', $respuesta['msg']);
    }

    public function testCategoriaFlexibleDesconocidaRechazada(): void
    {
        $usuario = $this->crearUsuario('sim-catdesc.integration@example.test');

        $respuesta = $this->invocar([
            'categoria' => 'categoria_inexistente',
            'mes' => '2026-05',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Categoría no válida', $respuesta['msg']);
    }

    public function testSinDatosEnVentanaDevuelveSinSuficientes(): void
    {
        $usuario = $this->crearUsuario('sim-sindatos.integration@example.test');

        $respuesta = $this->invocar([
            'categoria' => 'ocio_entretenimiento',
            'mes' => '2026-05',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('No hay gastos suficientes en esta categoría para simular.', $respuesta['msg']);
    }

    public function testCasoFelizDevuelve12CifrasCorrectas(): void
    {
        $usuario = $this->crearUsuario('sim-feliz.integration@example.test');

        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 30, '2026-04-10');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 60, '2026-05-10');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 90, '2026-06-10');

        $respuesta = $this->invocar([
            'categoria' => 'ocio_entretenimiento',
            'mes' => '2026-06',
        ], $usuario['id']);

        self::assertTrue($respuesta['ok'], 'Simulación con datos debería funcionar: ' . ($respuesta['msg'] ?? ''));
        self::assertSame('ocio_entretenimiento', $respuesta['data']['categoria']);
        self::assertSame(3, $respuesta['data']['mesesUsados']);
        self::assertEquals(60.0, $respuesta['data']['mediaMensual']);
        self::assertSame('2026-01', $respuesta['data']['fechaInicio']);
        self::assertSame('2026-06', $respuesta['data']['fechaFin']);

        $escenarios = $respuesta['data']['escenarios'];
        self::assertArrayHasKey('todo', $escenarios);
        self::assertArrayHasKey('mitad', $escenarios);
        self::assertArrayHasKey('3', $escenarios['todo']);
        self::assertArrayHasKey('6', $escenarios['todo']);
        self::assertArrayHasKey('9', $escenarios['todo']);
        self::assertArrayHasKey('5', $escenarios['todo']['3']);
        self::assertArrayHasKey('10', $escenarios['todo']['3']);
        self::assertArrayHasKey('15', $escenarios['todo']['3']);
        self::assertArrayHasKey('5', $escenarios['mitad']['6']);
        self::assertArrayHasKey('10', $escenarios['mitad']['6']);
        self::assertArrayHasKey('15', $escenarios['mitad']['6']);

        self::assertEquals(60.0, $escenarios['todo']['3']['5']['aportacionMensual']);
        self::assertEquals(30.0, $escenarios['mitad']['3']['5']['aportacionMensual']);
        self::assertArrayHasKey('valorFinalEstimado', $escenarios['todo']['3']['5']);
        self::assertArrayHasKey('eurosGenerados', $escenarios['todo']['3']['5']);
        self::assertArrayHasKey('totalAportado', $escenarios['todo']['3']['5']);
    }

    public function testCasoFelizSinRentabilidadYPlazoCorto(): void
    {
        $usuario = $this->crearUsuario('sim-sinrentab.integration@example.test');

        \Gasto::agregarGasto($usuario['id'], 'flexible', 'restaurantes_bares_cafeterias', 100, '2026-05-10');

        $respuesta = $this->invocar([
            'categoria' => 'restaurantes_bares_cafeterias',
            'mes' => '2026-05',
        ], $usuario['id']);

        self::assertTrue($respuesta['ok'], 'Simulación con un mes de datos debería funcionar: ' . ($respuesta['msg'] ?? ''));
        self::assertSame(1, $respuesta['data']['mesesUsados']);
        self::assertEquals(100.0, $respuesta['data']['mediaMensual']);
    }

    public function testDatosDeOtroUsuarioNoAfectanMedia(): void
    {
        $duenio = $this->crearUsuario('sim-aislamiento.integration@example.test');
        $otro = $this->crearUsuario('sim-otro.integration@example.test');

        \Gasto::agregarGasto($duenio['id'], 'flexible', 'ocio_entretenimiento', 60, '2026-05-10');
        \Gasto::agregarGasto($otro['id'], 'flexible', 'ocio_entretenimiento', 999, '2026-04-10');
        \Gasto::agregarGasto($otro['id'], 'flexible', 'ocio_entretenimiento', 999, '2026-06-10');

        $respuesta = $this->invocar([
            'categoria' => 'ocio_entretenimiento',
            'mes' => '2026-06',
        ], $duenio['id']);

        self::assertTrue($respuesta['ok'], 'Simulación del dueño debería funcionar: ' . ($respuesta['msg'] ?? ''));
        self::assertSame(1, $respuesta['data']['mesesUsados']);
        self::assertEquals(60.0, $respuesta['data']['mediaMensual']);
    }
}
