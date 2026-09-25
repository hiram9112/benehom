<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NavigationUrlTest extends TestCase
{
    #[DataProvider('pageRoutes')]
    public function testGeneraUrlsLimpiasParaPaginasNavegables(string $route, string $path): void
    {
        self::assertSame($path, parse_url(\bh_page_url($route), PHP_URL_PATH));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function pageRoutes(): array
    {
        return [
            'inicio' => ['home/index', '/'],
            'login' => ['auth/login', '/iniciar-sesion'],
            'registro' => ['registro/registrarUsuario', '/registro'],
            'recuperacion de contrasena' => ['password/mostrarFormularioOlvido', '/recuperar-contrasena'],
            'restablecimiento de contrasena' => ['password/reset', '/restablecer-contrasena'],
            'verificacion de email' => ['verificacion/verificar', '/verificar-email'],
            'reenvio de verificacion' => ['verificacion/mostrarFormularioReenvio', '/reenviar-verificacion'],
            'dashboard' => ['dashboard/index', '/dashboard'],
            'proyecciones' => ['proyecciones/index', '/proyecciones'],
            'cuenta' => ['cuenta/index', '/cuenta'],
        ];
    }

    public function testDashboardConservaSoloElMesFuncional(): void
    {
        $url = \bh_page_url('dashboard/index', [
            'mes' => '2026-05',
            'r' => 'auth/login',
            'filtro' => 'no-soportado',
        ]);

        self::assertSame('/dashboard', parse_url($url, PHP_URL_PATH));
        self::assertSame('mes=2026-05', parse_url($url, PHP_URL_QUERY));
    }

    public function testDashboardDescartaMesInvalido(): void
    {
        self::assertNull(parse_url(\bh_page_url('dashboard/index', ['mes' => '2026-13']), PHP_URL_QUERY));
    }

    public function testEnlaceDeRecuperacionAbsolutoNoDuplicaElDominioEnLocal(): void
    {
        $originalEnv = $_ENV;
        $originalServer = $_SERVER;
        $originalErrorLog = ini_get('error_log');
        $logPath = tempnam(sys_get_temp_dir(), 'benehom-reset-link-');

        self::assertNotFalse($logPath);

        try {
            $_ENV['APP_ENV'] = 'local';
            $_ENV['APP_URL'] = 'https://benehom.es';
            $_SERVER['HTTP_HOST'] = 'benehom.local';
            $_SERVER['HTTPS'] = 'off';
            ini_set('error_log', $logPath);

            $token = str_repeat('a', 64);
            $resetLink = \bh_page_url('password/reset', ['token' => $token]);

            self::assertSame(
                'http://benehom.local/restablecer-contrasena?token=' . $token,
                $resetLink
            );
            self::assertTrue(\enviarEmailReset('test@example.test', $resetLink));

            $log = (string) file_get_contents($logPath);
            self::assertStringContainsString('[DEV][RESET LINK] ' . $resetLink, $log);
            self::assertStringNotContainsString('https://benehom.eshttp://benehom.local', $log);

            $_ENV['APP_ENV'] = 'production';

            self::assertSame(
                'https://benehom.es/restablecer-contrasena?token=' . $token,
                \bh_page_url('password/reset', ['token' => $token])
            );
        } finally {
            $_ENV = $originalEnv;
            $_SERVER = $originalServer;
            ini_set('error_log', $originalErrorLog === false ? '' : $originalErrorLog);
            unlink($logPath);
        }
    }

    #[DataProvider('tokenPageRoutes')]
    public function testConservaElTokenYDescartaParametrosDeRutaExternos(string $route, string $path): void
    {
        $token = str_repeat('a', 64);
        $url = \bh_page_url($route, [
            'token' => $token,
            'r' => 'auth/login',
        ]);

        self::assertSame($path, parse_url($url, PHP_URL_PATH));
        self::assertSame('token=' . $token, parse_url($url, PHP_URL_QUERY));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function tokenPageRoutes(): array
    {
        return [
            'restablecimiento de contrasena' => ['password/reset', '/restablecer-contrasena'],
            'verificacion de email' => ['verificacion/verificar', '/verificar-email'],
        ];
    }

    public function testNavegacionPrincipalUsaLosAliasesLimpios(): void
    {
        require_once APP_PATH . '/views/partials/app-navigation.php';

        $items = [];

        foreach (\bh_navigation_items() as $item) {
            $items[$item['route']] = parse_url($item['href'], PHP_URL_PATH);
        }

        self::assertSame('/dashboard', $items['dashboard/index']);
        self::assertSame('/proyecciones', $items['proyecciones/index']);
        self::assertSame('/cuenta', $items['cuenta/index']);
        self::assertSame('/blog', $items['blog/index']);
    }

    public function testRechazaRutasFueraDeLasPaginasNavegables(): void
    {
        $this->expectException(InvalidArgumentException::class);

        \bh_page_url('numa/status');
    }

    public function testHtaccessDeclaraSoloLosAliasesNuevosEsperados(): void
    {
        $htaccess = $this->htaccess();
        $expectedRules = [
            'RewriteRule ^$ index.php?r=home/index [L,QSD]',
            'RewriteRule ^iniciar-sesion/?$ index.php?r=auth/login [L,QSD]',
            'RewriteRule ^registro/?$ index.php?r=registro/registrarUsuario [L,QSD]',
            'RewriteRule ^recuperar-contrasena/?$ index.php?r=password/mostrarFormularioOlvido [L,QSD]',
            'RewriteRule ^restablecer-contrasena/?$ index.php?r=password/reset&token=%1 [L,QSD]',
            'RewriteRule ^restablecer-contrasena/?$ index.php?r=password/reset [L,QSD]',
            'RewriteRule ^verificar-email/?$ index.php?r=verificacion/verificar&token=%1 [L,QSD]',
            'RewriteRule ^verificar-email/?$ index.php?r=verificacion/verificar [L,QSD]',
            'RewriteRule ^reenviar-verificacion/?$ index.php?r=verificacion/mostrarFormularioReenvio [L,QSD]',
            'RewriteRule ^dashboard/?$ index.php?r=dashboard/index&mes=%1 [L,QSD]',
            'RewriteRule ^dashboard/?$ index.php?r=dashboard/index [L,QSD]',
            'RewriteRule ^proyecciones/?$ index.php?r=proyecciones/index [L,QSD]',
            'RewriteRule ^cuenta/?$ index.php?r=cuenta/index [L,QSD]',
        ];

        foreach ($expectedRules as $rule) {
            self::assertStringContainsString($rule, $htaccess);
        }

        self::assertStringContainsString(
            'RewriteCond %{QUERY_STRING} (?:^|&)mes=([0-9]{4}-(?:0[1-9]|1[0-2]))(?:&|$)',
            $htaccess
        );
        self::assertSame(2, substr_count($htaccess, 'token=([a-f0-9]{64})'));
        self::assertStringNotContainsString('index.php?r=numa/', $this->newAliasSection($htaccess));
        self::assertStringNotContainsString('Ajax', $this->newAliasSection($htaccess));
    }

    public function testAliasesNuevosNoAnexanUnaRutaRecibidaPorQuery(): void
    {
        $htaccess = $this->htaccess();
        $aliases = $this->newAliasSection($htaccess);

        self::assertStringNotContainsString('QSA', $aliases);
        self::assertSame(13, substr_count($aliases, 'RewriteCond %{REQUEST_METHOD} ^(?:GET|HEAD)$'));
        self::assertLessThan(
            strpos($htaccess, 'RewriteCond %{REQUEST_FILENAME} -f'),
            strpos($htaccess, 'RewriteRule ^$ index.php?r=home/index [L,QSD]')
        );
    }

    public function testBlogMantieneSusReglasActuales(): void
    {
        $htaccess = $this->htaccess();

        self::assertStringContainsString('RewriteRule ^blog/?$ index.php?r=blog/index [L,QSA]', $htaccess);
        self::assertStringContainsString(
            'RewriteRule ^blog/([A-Za-z0-9-]+)/?$ index.php?r=blog/detalle&slug=$1 [L,QSA,B]',
            $htaccess
        );
    }

    public function testBarraFinalRedirigeSoloLosAliasesNuevosAntesDeResolverlos(): void
    {
        $htaccess = $this->htaccess();
        // Sin '?' ni QSD en la redirección: Apache conserva la query (incluido mes).
        // El destino vuelve a pasar por las reglas que fijan r y validan mes.
        $redirect = 'RewriteCond %{REQUEST_METHOD} ^(?:GET|HEAD)$' . "\n"
            . 'RewriteCond %{REQUEST_URI} ^(/[^/].*)/$' . "\n"
            . 'RewriteRule ^(iniciar-sesion|registro|recuperar-contrasena|reenviar-verificacion|dashboard|proyecciones|cuenta)/$ %1 [R=301,L]';

        self::assertStringContainsString($redirect, $htaccess);
        self::assertLessThan(
            strpos($htaccess, 'RewriteRule ^iniciar-sesion/?$'),
            strpos($htaccess, $redirect)
        );

        // Ningún alias interno debe ejecutar antes su controlador en la URL con barra.
        foreach (['registro', 'recuperar-contrasena', 'reenviar-verificacion', 'dashboard', 'proyecciones', 'cuenta'] as $path) {
            self::assertLessThan(
                strpos($htaccess, 'RewriteRule ^' . $path . '/?$'),
                strpos($htaccess, $redirect)
            );
        }

        self::assertStringNotContainsString('restablecer-contrasena', $redirect);
        self::assertStringNotContainsString('verificar-email', $redirect);
    }

    public function testFormulariosPostMantienenLosEndpointsInternos(): void
    {
        $login = (string) file_get_contents(APP_PATH . '/views/auth/login.php');
        $register = (string) file_get_contents(APP_PATH . '/views/auth/register.php');
        $forgotPassword = (string) file_get_contents(APP_PATH . '/views/auth/forgot_password.php');
        $resendVerification = (string) file_get_contents(APP_PATH . '/views/auth/resend_verification.php');
        $resetPassword = (string) file_get_contents(APP_PATH . '/views/auth/reset_password.php');

        self::assertStringContainsString('action="<?= BASE_URL ?>index.php?r=auth/login"', $login);
        self::assertStringContainsString(
            'action="<?= BASE_URL ?>index.php?r=registro/registrarUsuario"',
            $register
        );
        self::assertStringContainsString(
            'action="<?= BASE_URL ?>index.php?r=password/procesarFormularioOlvido"',
            $forgotPassword
        );
        self::assertStringContainsString(
            'action="<?= BASE_URL ?>index.php?r=verificacion/reenviar"',
            $resendVerification
        );
        self::assertStringContainsString(
            'action="<?= BASE_URL ?>index.php?r=password/procesarReset"',
            $resetPassword
        );
    }

    public function testSelectorDeMesUsaDashboardLimpioSinParametroDeRuta(): void
    {
        $dashboard = (string) file_get_contents(APP_PATH . '/views/dashboard.php');

        self::assertStringContainsString('action="<?= bh_page_url(\'dashboard/index\') ?>"', $dashboard);
        self::assertStringNotContainsString('name="r" value="dashboard/index"', $dashboard);
        self::assertStringContainsString('name="mes"', $dashboard);
    }

    public function testEnlacesDeCorreoYRedireccionesHtmlUsanPaginasLimpias(): void
    {
        $password = (string) file_get_contents(APP_PATH . '/controllers/PasswordController.php');
        $verification = (string) file_get_contents(APP_PATH . '/controllers/VerificacionController.php');
        $registration = (string) file_get_contents(APP_PATH . '/controllers/RegistroController.php');
        $dashboard = (string) file_get_contents(APP_PATH . '/controllers/DashboardController.php');
        $projections = (string) file_get_contents(APP_PATH . '/controllers/ProyeccionesController.php');
        $account = (string) file_get_contents(APP_PATH . '/controllers/CuentaController.php');
        $frontController = (string) file_get_contents(BASE_PATH . '/public/index.php');

        self::assertStringContainsString("bh_page_url('password/reset', ['token' => \$token])", $password);
        self::assertStringContainsString("bh_page_url('verificacion/verificar', ['token' => \$token])", $verification);
        self::assertStringContainsString("bh_page_url('verificacion/verificar', ['token' => \$token])", $registration);

        foreach ([$password, $verification, $registration, $dashboard, $projections, $account, $frontController] as $source) {
            self::assertStringNotContainsString('index.php?r=', $source);
            self::assertStringNotContainsString("Location: ?r=", $source);
        }
    }

    public function testCanonicalsYNavegacionPosteriorUsanAliasesLimpios(): void
    {
        $authLayout = (string) file_get_contents(APP_PATH . '/views/partials/auth-layout.php');
        $dashboard = (string) file_get_contents(APP_PATH . '/views/dashboard.php');
        $projections = (string) file_get_contents(APP_PATH . '/views/proyecciones.php');
        $account = (string) file_get_contents(APP_PATH . '/views/cuenta.php');
        $numa = (string) file_get_contents(APP_PATH . '/views/partials/numa-launcher.php');
        $numaClient = (string) file_get_contents(BASE_PATH . '/public/js/numa-chat.js');
        $dashboardClient = (string) file_get_contents(BASE_PATH . '/public/js/dashboard-graficos.js');

        foreach ([$authLayout, $dashboard, $projections, $account] as $source) {
            self::assertStringNotContainsString("canonical' => bh_url('index.php?r=", $source);
        }

        self::assertStringContainsString("'canonical' => bh_page_url(bh_current_auth_route())", $authLayout);
        self::assertStringContainsString("'canonical' => bh_page_url('dashboard/index')", $dashboard);
        self::assertStringContainsString("'canonical' => bh_page_url('proyecciones/index')", $projections);
        self::assertStringContainsString("'canonical' => bh_page_url('cuenta/index')", $account);
        self::assertStringContainsString("data-numa-login-url=\"<?= htmlspecialchars(bh_page_url('auth/login')", $numa);
        self::assertStringContainsString("window.location.assign(loginUrl || '/iniciar-sesion')", $numaClient);
        self::assertStringContainsString("document.querySelector('.bh-month-form').action + '?mes='", $dashboardClient);
    }

    private function htaccess(): string
    {
        $contents = file_get_contents(BASE_PATH . '/public/.htaccess');

        self::assertIsString($contents);

        return $contents;
    }

    private function newAliasSection(string $htaccess): string
    {
        $start = strpos($htaccess, 'RewriteRule ^$ index.php?r=home/index [L,QSD]');
        $end = strpos($htaccess, 'RewriteRule ^blog/?$', $start === false ? 0 : $start);

        self::assertNotFalse($start);
        self::assertNotFalse($end);

        return substr($htaccess, $start, $end - $start);
    }
}
