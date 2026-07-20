<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RoutingRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH']);

        parent::tearDown();
    }

    public function testTodasLasRutasPrincipalesEstanRegistradas(): void
    {
        $routes = \bh_routes();
        $expected = [
            'home/index',
            'auth/login',
            'auth/logout',
            'registro/registrarUsuario',
            'password/mostrarFormularioOlvido',
            'password/procesarFormularioOlvido',
            'password/reset',
            'password/procesarReset',
            'verificacion/verificar',
            'verificacion/mostrarFormularioReenvio',
            'verificacion/reenviar',
            'blog/index',
            'blog/detalle',
            'seo/sitemap',
            'legal/privacidad',
            'legal/terminos',
            'legal/aviso',
            'dashboard/index',
            'dashboard/importarMesAnteriorAjax',
            'graficos/estadoGeneral',
            'graficos/gastos6m',
            'graficos/ahorros6m',
            'graficos/topCategorias',
            'ingreso/agregarAjax',
            'ingreso/eliminarAjax',
            'ingreso/editarAjax',
            'gasto/agregarGastoEsencialAjax',
            'gasto/eliminarGastoAjax',
            'gasto/editarGastoAjax',
            'gasto/agregarGastoFlexibleAjax',
            'cuenta/index',
            'cuenta/cambiarPassword',
            'cuenta/eliminarCuenta',
            'cuenta/exportarDatos',
            'proyecciones/index',
            'proyecciones/simularCategoriaAjax',
            'proyecciones/crearEscenarioInversion',
            'proyecciones/actualizarEscenarioInversionAjax',
            'proyecciones/eliminarEscenarioInversion',
            'proyecciones/crearMetaAhorro',
            'proyecciones/eliminarMetaAhorro',
            'proyecciones/actualizarImporteMetaAjax',
            'proyecciones/actualizarAhorroMensualAjax',
            'proyecciones/crearInflacionProyeccion',
            'proyecciones/actualizarInflacionProyeccionAjax',
            'proyecciones/eliminarInflacionProyeccion',
            'proyecciones/crearCalculadoraHipoteca',
            'proyecciones/actualizarCalculadoraHipotecaAjax',
            'proyecciones/eliminarCalculadoraHipoteca',
            'proyecciones/crearMetaAhorroAjax',
            'proyecciones/eliminarMetaAhorroAjax',
            'proyecciones/crearEscenarioInversionAjax',
            'proyecciones/eliminarEscenarioInversionAjax',
            'proyecciones/crearInflacionProyeccionAjax',
            'proyecciones/eliminarInflacionProyeccionAjax',
            'proyecciones/crearCalculadoraHipotecaAjax',
            'proyecciones/eliminarCalculadoraHipotecaAjax',
            'numa/chat',
            'numa/status',
        ];

        foreach ($expected as $route) {
            self::assertArrayHasKey($route, $routes, $route);
        }
    }

    public function testCadaRutaDeclaraContratoMinimo(): void
    {
        foreach (\bh_routes() as $route => $definition) {
            self::assertIsString($definition['controller'] ?? null, $route);
            self::assertIsString($definition['action'] ?? null, $route);
            self::assertIsArray($definition['methods'] ?? null, $route);
            self::assertIsBool($definition['public'] ?? null, $route);
            self::assertContains($definition['response'] ?? null, ['html', 'json'], $route);
        }
    }

    public function testCadaAccionRegistradaEsInvocable(): void
    {
        foreach (\bh_routes() as $route => $definition) {
            $controllerClass = $definition['controller'];
            $controllerFile = APP_PATH . '/controllers/' . $controllerClass . '.php';

            self::assertFileExists($controllerFile, $route);
            require_once $controllerFile;

            self::assertTrue(\bh_controller_action_callable($controllerClass, $definition['action']), $route);
        }
    }

    public function testNoQuedanMetodosPublicosDeControladoresFueraDelRegistro(): void
    {
        $registered = [];

        foreach (\bh_routes() as $definition) {
            $registered[$definition['controller'] . '::' . $definition['action']] = true;
        }

        foreach (glob(APP_PATH . '/controllers/*Controller.php') ?: [] as $controllerFile) {
            require_once $controllerFile;

            $controllerClass = basename($controllerFile, '.php');
            $reflection = new \ReflectionClass($controllerClass);

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->class !== $controllerClass || str_starts_with($method->name, '__')) {
                    continue;
                }

                self::assertArrayHasKey($controllerClass . '::' . $method->name, $registered);
            }
        }
    }

    public function testRutaWebValida(): void
    {
        $route = \bh_route_definition('dashboard/index');

        self::assertSame('DashboardController', $route['controller']);
        self::assertSame('index', $route['action']);
        self::assertTrue(\bh_route_allows_method($route, 'GET'));
        self::assertFalse($route['public']);
        self::assertSame('html', \bh_route_response_type($route));
    }

    public function testRutaAjaxValida(): void
    {
        $route = \bh_route_definition('ingreso/agregarAjax');

        self::assertSame('IngresoController', $route['controller']);
        self::assertTrue(\bh_route_allows_method($route, 'POST'));
        self::assertSame('json', \bh_route_response_type($route));
    }

    public function testRutasNumaRegistradasComoJsonAutenticado(): void
    {
        $chat = \bh_route_definition('numa/chat');
        $status = \bh_route_definition('numa/status');

        self::assertSame('NumaController', $chat['controller']);
        self::assertSame('chat', $chat['action']);
        self::assertSame(['POST'], $chat['methods']);
        self::assertFalse($chat['public']);
        self::assertSame('json', $chat['response']);

        self::assertSame('NumaController', $status['controller']);
        self::assertSame('status', $status['action']);
        self::assertSame(['GET'], $status['methods']);
        self::assertFalse($status['public']);
        self::assertSame('json', $status['response']);
    }

    public function testRutaNoRegistrada(): void
    {
        self::assertNull(\bh_route_definition('no/existe'));
    }

    public function testMetodoHttpIncorrecto(): void
    {
        $route = \bh_route_definition('numa/status');

        self::assertFalse(\bh_route_allows_method($route, 'POST'));
    }

    public function testRutaJsonAutenticadaSinSesionSeDistinguePorContrato(): void
    {
        $route = \bh_route_definition('numa/status');

        self::assertFalse($route['public']);
        self::assertSame('json', \bh_route_response_type($route));
    }

    public function testMetodoPublicoNoRegistradoNoTieneRuta(): void
    {
        self::assertNull(\bh_route_definition('auth/emailVerificadoParaLogin'));
    }

    public function testMetodosProtegidosYPrivadosNoSonInvocables(): void
    {
        require_once APP_PATH . '/controllers/AuthController.php';
        require_once APP_PATH . '/controllers/SeoController.php';

        self::assertFalse(\bh_controller_action_callable(\AuthController::class, 'emailVerificadoParaLogin'));
        self::assertFalse(\bh_controller_action_callable(\SeoController::class, 'renderXml'));
    }

    public function testRutaInexistenteConAcceptJsonUsaJson(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        self::assertSame('json', \bh_route_response_type(null));
    }

    public function testErrorJsonNoIncluyeInformacionSensible(): void
    {
        ob_start();
        \bh_json_error('NOT_FOUND', \bh_router_error_message('NOT_FOUND'), 404);
        $output = (string) ob_get_clean();

        $decoded = json_decode($output, true);

        self::assertFalse($decoded['ok']);
        self::assertSame('NOT_FOUND', $decoded['error']['code']);
        self::assertStringNotContainsString(BASE_PATH, $output);
        self::assertStringNotContainsString('DB_PASS', $output);
    }
}
