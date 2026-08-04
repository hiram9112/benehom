<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/views/partials/numa-launcher.php';

final class NumaLauncherTest extends TestCase
{
    private bool $hadNumaEnabled;
    private ?string $numaEnabled;

    protected function setUp(): void
    {
        $this->hadNumaEnabled = array_key_exists('NUMA_ENABLED', $_ENV);
        $this->numaEnabled = $this->hadNumaEnabled ? (string) $_ENV['NUMA_ENABLED'] : null;
    }

    protected function tearDown(): void
    {
        if ($this->hadNumaEnabled) {
            $_ENV['NUMA_ENABLED'] = $this->numaEnabled;
        } else {
            unset($_ENV['NUMA_ENABLED']);
        }

        parent::tearDown();
    }

    public function testRenderizaBotonAccesibleDisponible(): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';

        $html = $this->renderLauncher();

        self::assertStringContainsString('type="button"', $html);
        self::assertStringContainsString('aria-label="Abrir Numa"', $html);
        self::assertStringContainsString('aria-expanded="false"', $html);
        self::assertStringContainsString('class="bh-numa-launcher is-available"', $html);
        self::assertStringContainsString('data-tooltip="Abrir Numa"', $html);
        self::assertStringContainsString('data-available="true"', $html);
        self::assertStringContainsString('ti ti-message-circle', $html);
        self::assertStringContainsString('bh-numa-launcher-dot', $html);
        self::assertStringNotContainsString('>Numa<', $html);
        self::assertStringNotContainsString('Disponible', $html);
        self::assertStringContainsString('aria-hidden="true"', $html);
    }

    public function testRenderizaEstadoNoDisponibleSinDesactivarElBoton(): void
    {
        $_ENV['NUMA_ENABLED'] = 'false';

        $html = $this->renderLauncher();

        self::assertStringContainsString('class="bh-numa-launcher is-unavailable"', $html);
        self::assertStringContainsString('data-available="false"', $html);
        self::assertStringNotContainsString('No disponible', $html);
        self::assertStringNotContainsString(' disabled', $html);
    }

    public function testSoloLasVistasPrivadasIncluyenElBoton(): void
    {
        foreach (['dashboard.php', 'proyecciones.php', 'cuenta.php'] as $view) {
            $source = file_get_contents(APP_PATH . '/views/' . $view);

            self::assertIsString($source);
            self::assertStringContainsString('bh_numa_launcher();', $source, $view);
        }

        foreach (['home.php', 'blog.php', 'blog-detalle.php'] as $view) {
            $source = file_get_contents(APP_PATH . '/views/' . $view);

            self::assertIsString($source);
            self::assertStringNotContainsString('bh_numa_launcher();', $source, $view);
        }
    }

    public function testEstilosFijanElBotonYLoAdaptanEnResponsive(): void
    {
        $css = file_get_contents(BASE_PATH . '/public/css/src/numa.css');

        self::assertIsString($css);
        self::assertStringContainsString('position: fixed', $css);
        self::assertStringContainsString('width: 52px', $css);
        self::assertStringContainsString('height: 52px', $css);
        self::assertStringContainsString('width: 56px', $css);
        self::assertStringContainsString('height: 56px', $css);
        self::assertStringContainsString('right: max(16px, env(safe-area-inset-right))', $css);
        self::assertStringContainsString('bottom: max(16px, env(safe-area-inset-bottom))', $css);
        self::assertStringContainsString('content: attr(data-tooltip)', $css);
        self::assertStringContainsString(':focus-visible', $css);
        self::assertStringContainsString(':disabled', $css);
        self::assertStringContainsString(':disabled .bh-numa-launcher-dot', $css);
        self::assertStringContainsString('env(safe-area-inset-bottom)', $css);
        self::assertStringContainsString('@media (min-width: 768px)', $css);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
    }

    private function renderLauncher(): string
    {
        ob_start();
        \bh_numa_launcher();

        return (string) ob_get_clean();
    }
}
