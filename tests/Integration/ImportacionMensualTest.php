<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/controllers/DashboardController.php';
require_once APP_PATH . '/services/ImportacionMensual.php';

final class ImportacionMensualTest extends IntegrationTestCase
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
     * @return array{ok:bool, msg?:string, resumen?:array<string,mixed>, codigo?:string}
     */
    private function invocarImportacion(array $post, ?int $usuarioId): array
    {
        $_POST = $post;
        $_SESSION['usuario_id'] = $usuarioId;

        $instancia = new \DashboardController();

        ob_start();
        $instancia->importarMesAnteriorAjax();
        $salida = ob_get_clean();

        $decoded = json_decode((string) $salida, true);

        return is_array($decoded) ? $decoded : ['ok' => false, 'msg' => 'Respuesta no JSON: ' . (string) $salida];
    }

    private function crearMovimientosMesAnterior(int $usuarioId, string $mesAnterior): void
    {
        $fechaAnterior = $mesAnterior . '-01';

        \Ingreso::agregarIngreso($usuarioId, 'salario', '2000', $fechaAnterior);
        \Ingreso::agregarIngreso($usuarioId, 'otros_ingresos', '500', $fechaAnterior);
        \Gasto::agregarGasto($usuarioId, 'esencial', 'alquiler_hipoteca', '800', $fechaAnterior);
        \Gasto::agregarGasto($usuarioId, 'esencial', 'alimentacion_hogar', '300', $fechaAnterior);
        \Gasto::agregarGasto($usuarioId, 'flexible', 'ocio_entretenimiento', '150', $fechaAnterior);
    }

    public function testImportacionExitosaConIngresosYEsenciales(): void
    {
        $usuario = $this->crearUsuario('import-exitoso.integration@example.test');

        $this->crearMovimientosMesAnterior($usuario['id'], '2026-05');

        $respuesta = $this->invocarImportacion([
            'mes_destino' => '2026-06',
        ], $usuario['id']);

        self::assertTrue($respuesta['ok'], 'Importación debería ser exitosa: ' . ($respuesta['msg'] ?? ''));
        self::assertArrayHasKey('resumen', $respuesta);
        self::assertSame(2, $respuesta['resumen']['ingresos']);
        self::assertSame(2, $respuesta['resumen']['esenciales']);
        self::assertSame('2026-05', $respuesta['resumen']['mes_anterior']);

        $fechaInicio = '2026-06-01';
        $fechaFin = '2026-06-30';
        $ingresos = \Ingreso::obtenerPorMes($usuario['id'], $fechaInicio, $fechaFin);
        $esenciales = \Gasto::obtenerPorMes($usuario['id'], 'esencial', $fechaInicio, $fechaFin);
        $flexibles = \Gasto::obtenerPorMes($usuario['id'], 'flexible', $fechaInicio, $fechaFin);

        self::assertCount(2, $ingresos, 'Deberían importarse 2 ingresos');
        self::assertCount(2, $esenciales, 'Deberían importarse 2 gastos esenciales');
        self::assertCount(0, $flexibles, 'No deberían importarse gastos flexibles');
    }

    public function testImportacionExcluyeGastosFlexibles(): void
    {
        $usuario = $this->crearUsuario('import-sin-flexibles.integration@example.test');

        $this->crearMovimientosMesAnterior($usuario['id'], '2026-05');

        $respuesta = $this->invocarImportacion([
            'mes_destino' => '2026-06',
        ], $usuario['id']);

        self::assertTrue($respuesta['ok']);

        $fechaInicio = '2026-06-01';
        $fechaFin = '2026-06-30';
        $flexibles = \Gasto::obtenerPorMes($usuario['id'], 'flexible', $fechaInicio, $fechaFin);

        self::assertCount(0, $flexibles, 'Los gastos flexibles no deben importarse');
    }

    public function testImportacionDiciembreAEnero(): void
    {
        $usuario = $this->crearUsuario('import-dic-ene.integration@example.test');

        $this->crearMovimientosMesAnterior($usuario['id'], '2025-12');

        $respuesta = $this->invocarImportacion([
            'mes_destino' => '2026-01',
        ], $usuario['id']);

        self::assertTrue($respuesta['ok'], 'Importación de diciembre a enero debería ser exitosa');
        self::assertSame('2025-12', $respuesta['resumen']['mes_anterior']);
    }

    public function testImportacionRechazaMesInvalido(): void
    {
        $usuario = $this->crearUsuario('import-mes-invalido.integration@example.test');

        $casosInvalidos = [
            'vacio' => '',
            'mes 13' => '2026-13',
            'mes 00' => '2026-00',
            'formato corto' => '2026-1',
            'separador incorrecto' => '2026/05',
        ];

        foreach ($casosInvalidos as $caso => $valor) {
            $respuesta = $this->invocarImportacion([
                'mes_destino' => $valor,
            ], $usuario['id']);

            self::assertFalse($respuesta['ok'], "Caso '$caso' con mes '$valor' debería ser rechazado");
            self::assertSame('Mes no válido', $respuesta['msg']);
        }
    }

    public function testImportacionRechazaSinSesion(): void
    {
        $respuesta = $this->invocarImportacion([
            'mes_destino' => '2026-06',
        ], null);

        self::assertFalse($respuesta['ok']);
        self::assertSame('Sesión no válida', $respuesta['msg']);
    }

    public function testImportacionRechazaDestinoNoVacio(): void
    {
        $usuario = $this->crearUsuario('import-destino-no-vacio.integration@example.test');

        $this->crearMovimientosMesAnterior($usuario['id'], '2026-05');

        \Ingreso::agregarIngreso($usuario['id'], 'salario', '1000', '2026-06-01');

        $respuesta = $this->invocarImportacion([
            'mes_destino' => '2026-06',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('destino_no_vacio', $respuesta['codigo']);
        self::assertStringContainsString('ya contiene movimientos', $respuesta['msg']);
    }

    public function testImportacionRechazaSinDatosAnteriores(): void
    {
        $usuario = $this->crearUsuario('import-sin-datos.integration@example.test');

        $respuesta = $this->invocarImportacion([
            'mes_destino' => '2026-06',
        ], $usuario['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('sin_datos_anteriores', $respuesta['codigo']);
        self::assertStringContainsString('No hay ingresos ni gastos esenciales', $respuesta['msg']);
    }

    public function testImportacionAislamientoEntreUsuarios(): void
    {
        $usuario1 = $this->crearUsuario('import-usuario1.integration@example.test');
        $usuario2 = $this->crearUsuario('import-usuario2.integration@example.test');

        $this->crearMovimientosMesAnterior($usuario1['id'], '2026-05');

        $respuesta = $this->invocarImportacion([
            'mes_destino' => '2026-06',
        ], $usuario2['id']);

        self::assertFalse($respuesta['ok']);
        self::assertSame('sin_datos_anteriores', $respuesta['codigo']);

        $fechaInicio = '2026-06-01';
        $fechaFin = '2026-06-30';
        $ingresosUsuario2 = \Ingreso::obtenerPorMes($usuario2['id'], $fechaInicio, $fechaFin);

        self::assertCount(0, $ingresosUsuario2, 'El usuario 2 no debería tener movimientos del usuario 1');
    }

    public function testImportacionSegundoIntentoRechazaDestinoNoVacio(): void
    {
        $usuario = $this->crearUsuario('import-segundo-intento.integration@example.test');

        $this->crearMovimientosMesAnterior($usuario['id'], '2026-05');

        $primera = $this->invocarImportacion([
            'mes_destino' => '2026-06',
        ], $usuario['id']);

        self::assertTrue($primera['ok'], 'Primera importación debería ser exitosa');

        $segunda = $this->invocarImportacion([
            'mes_destino' => '2026-06',
        ], $usuario['id']);

        self::assertFalse($segunda['ok'], 'Segunda importación debería ser rechazada');
        self::assertSame('destino_no_vacio', $segunda['codigo']);
    }

    public function testCalcularMesAnterior(): void
    {
        self::assertSame('2026-05', \ImportacionMensual::calcularMesAnterior('2026-06'));
        self::assertSame('2025-12', \ImportacionMensual::calcularMesAnterior('2026-01'));
        self::assertSame('2024-11', \ImportacionMensual::calcularMesAnterior('2024-12'));
    }
}
