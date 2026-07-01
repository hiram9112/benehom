<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/controllers/GastoController.php';
require_once APP_PATH . '/controllers/IngresoController.php';

final class MesValidacionTest extends IntegrationTestCase
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

    public function testAgregarGastoEsencialRechazaMesInvalido(): void
    {
        $usuario = $this->crearUsuario('mes-esencial-invalid.integration@example.test');

        $casosInvalidos = [
            'vacio' => '',
            'mes 13' => '2026-13',
            'mes 00' => '2026-00',
            'formato corto' => '2026-1',
            'separador incorrecto' => '2026/05',
            'sql injection' => "2026'; DROP TABLE--",
            'null simulado' => 'null',
            'solo texto' => 'mayo',
        ];

        foreach ($casosInvalidos as $caso => $valor) {
            $respuesta = $this->invocar(\GastoController::class, 'agregarGastoEsencialAjax', [
                'categoria_gasto_esencial' => 'alquiler_hipoteca',
                'cantidad_gasto_esencial' => '100',
                'mes_seleccionado' => $valor,
            ], $usuario['id']);

            self::assertFalse($respuesta['ok'] ?? true, "Caso '$caso' con mes '$valor' debería ser rechazado.");
            self::assertSame('Mes no válido', $respuesta['msg'] ?? null, "Caso '$caso': mensaje incorrecto.");
        }
    }

    public function testAgregarGastoEsencialSinMesRechaza(): void
    {
        $usuario = $this->crearUsuario('mes-esencial-sin.integration@example.test');

        $respuesta = $this->invocar(\GastoController::class, 'agregarGastoEsencialAjax', [
            'categoria_gasto_esencial' => 'alquiler_hipoteca',
            'cantidad_gasto_esencial' => '100',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Mes no válido', $respuesta['msg']);
    }

    public function testAgregarGastoEsencialConMesValidoGuarda(): void
    {
        $usuario = $this->crearUsuario('mes-esencial-valido.integration@example.test');

        $respuesta = $this->invocar(\GastoController::class, 'agregarGastoEsencialAjax', [
            'categoria_gasto_esencial' => 'alquiler_hipoteca',
            'cantidad_gasto_esencial' => '100',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertTrue($respuesta['ok'], 'Gasto esencial con mes válido debería guardarse: ' . ($respuesta['msg'] ?? ''));
        self::assertSame('alquiler_hipoteca', $respuesta['gasto_esencial']['categoria']);
        self::assertSame('100', $respuesta['gasto_esencial']['cantidad']);
    }

    public function testAgregarGastoFlexibleRechazaMesInvalido(): void
    {
        $usuario = $this->crearUsuario('mes-flexible-invalid.integration@example.test');

        $respuesta = $this->invocar(\GastoController::class, 'agregarGastoFlexibleAjax', [
            'categoria_gasto_flexible' => 'ocio_entretenimiento',
            'cantidad_gasto_flexible' => '50',
            'mes_seleccionado' => '2026-13',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Mes no válido', $respuesta['msg']);
    }

    public function testAgregarGastoFlexibleConMesValidoGuarda(): void
    {
        $usuario = $this->crearUsuario('mes-flexible-valido.integration@example.test');

        $respuesta = $this->invocar(\GastoController::class, 'agregarGastoFlexibleAjax', [
            'categoria_gasto_flexible' => 'ocio_entretenimiento',
            'cantidad_gasto_flexible' => '50',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertTrue($respuesta['ok'], 'Gasto flexible con mes válido debería guardarse: ' . ($respuesta['msg'] ?? ''));
    }

    public function testAgregarIngresoRechazaMesInvalido(): void
    {
        $usuario = $this->crearUsuario('mes-ingreso-invalid.integration@example.test');

        $casosInvalidos = [
            'vacio' => '',
            'mes 13' => '2026-13',
            'formato corto' => '26-05',
            'separador' => '2026.05',
            'sql injection' => "2026-05' OR '1",
        ];

        foreach ($casosInvalidos as $caso => $valor) {
            $respuesta = $this->invocar(\IngresoController::class, 'agregarAjax', [
                'categoria_ingreso' => 'salario',
                'cantidad_ingreso' => '1500',
                'mes_seleccionado' => $valor,
            ], $usuario['id']);

            self::assertFalse($respuesta['ok'] ?? true, "Caso '$caso' con mes '$valor' debería ser rechazado.");
            self::assertSame('Mes no válido', $respuesta['msg'] ?? null, "Caso '$caso': mensaje incorrecto.");
        }
    }

    public function testAgregarIngresoConMesValidoGuarda(): void
    {
        $usuario = $this->crearUsuario('mes-ingreso-valido.integration@example.test');

        $respuesta = $this->invocar(\IngresoController::class, 'agregarAjax', [
            'categoria_ingreso' => 'salario',
            'cantidad_ingreso' => '1500',
            'mes_seleccionado' => '2026-05',
        ], $usuario['id']);

        self::assertTrue($respuesta['ok'], 'Ingreso con mes válido debería guardarse: ' . ($respuesta['msg'] ?? ''));
        self::assertSame('salario', $respuesta['ingreso']['categoria']);
    }
}
