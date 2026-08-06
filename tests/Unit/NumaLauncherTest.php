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
        self::assertStringNotContainsString('data-numa-state=', $html);
        self::assertStringContainsString('data-tooltip="Abrir Numa"', $html);
        self::assertStringContainsString('data-available="true"', $html);
        self::assertStringContainsString('class="bh-numa-launcher-character"', $html);
        self::assertStringContainsString('/img/numa/numa-static-master.png?v=', $html);
        self::assertStringContainsString('/img/numa/runtime/numa-body.webp?v=', $html);
        self::assertStringContainsString('/img/numa/runtime/blink/numa-face-00.webp?v=', $html);
        self::assertStringContainsString('/img/numa/runtime/wave/numa-arm-00.webp?v=', $html);
        self::assertStringContainsString('data-numa-animated', $html);
        self::assertStringContainsString('data-numa-body-src=', $html);
        self::assertStringContainsString('data-numa-face-frames=', $html);
        self::assertStringContainsString('data-numa-arm-frames=', $html);
        self::assertStringNotContainsString('/img/numa/numa-base-master.png?v=', $html);
        self::assertStringNotContainsString('numa-face.svg', $html);
        self::assertStringNotContainsString('class="numa-face"', $html);
        self::assertStringNotContainsString('ti ti-message-circle', $html);
        self::assertStringNotContainsString('>Numa<', $html);
        self::assertStringNotContainsString('Disponible', $html);
        self::assertStringContainsString('aria-hidden="true"', $html);
    }

    public function testRenderizaEstadoNoDisponibleSinDesactivarElBoton(): void
    {
        $_ENV['NUMA_ENABLED'] = 'false';

        $html = $this->renderLauncher();

        self::assertStringContainsString('class="bh-numa-launcher is-unavailable"', $html);
        self::assertStringNotContainsString('data-numa-state=', $html);
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
        self::assertStringContainsString('width: 88px', $css);
        self::assertStringContainsString('height: 88px', $css);
        self::assertStringContainsString('width: 96px', $css);
        self::assertStringContainsString('height: 96px', $css);
        self::assertStringContainsString('right: max(16px, env(safe-area-inset-right))', $css);
        self::assertStringContainsString('bottom: max(16px, env(safe-area-inset-bottom))', $css);
        self::assertStringContainsString('border: 0', $css);
        self::assertStringContainsString('background: transparent', $css);
        self::assertStringContainsString('overflow: visible', $css);
        self::assertStringContainsString('content: attr(data-tooltip)', $css);
        self::assertStringContainsString(':focus-visible', $css);
        self::assertStringContainsString('outline: 3px solid var(--bh-focus-color)', $css);
        self::assertStringContainsString(':disabled', $css);
        self::assertStringNotContainsString('border-radius: 50%', $css);
        self::assertStringNotContainsString('overflow: hidden', $css);
        self::assertStringContainsString('env(safe-area-inset-bottom)', $css);
        self::assertStringContainsString('@media (min-width: 768px)', $css);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
    }

    public function testRenderizaPersonajeEstaticoDecorativo(): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';

        $html = $this->renderLauncher();

        foreach ([
            'numa-face.svg',
            'data-numa-hybrid',
            'bh-numa-launcher-hybrid',
            'bh-numa-launcher-base',
            'numa-brow-left',
            'numa-brow-right',
            'numa-eye-left',
            'numa-eye-right',
            'numa-pupil-left',
            'numa-pupil-right',
            'numa-highlight-left',
            'numa-highlight-right',
            'numa-eyelid-left',
            'numa-eyelid-right',
            'numa-mouth',
        ] as $faceLayer) {
            self::assertStringNotContainsString($faceLayer, $html, $faceLayer);
        }

        self::assertStringContainsString('alt=""', $html);
        self::assertStringContainsString('aria-hidden="true"', $html);

        $css = file_get_contents(BASE_PATH . '/public/css/src/numa.css');

        self::assertIsString($css);
        self::assertStringNotContainsString('.bh-numa-launcher-base', $css);
        self::assertStringNotContainsString('.bh-numa-face', $css);
        self::assertStringContainsString('inset: 0', $css);
        self::assertStringContainsString('width: 100%', $css);
        self::assertStringContainsString('height: 100%', $css);
        self::assertStringContainsString('pointer-events: none', $css);
        self::assertStringContainsString('.bh-numa-launcher-animated', $css);
        self::assertStringContainsString('.bh-numa-launcher-layer', $css);
    }

    public function testUsaAssetsOptimizadosConFallbackEstatico(): void
    {
        $html = $this->renderLauncher();

        self::assertStringContainsString('/img/numa/numa-static.webp?v=', $html);
        self::assertStringContainsString('/img/numa/numa-static-master.png?v=', $html);
        self::assertStringContainsString('/img/numa/runtime/numa-body.webp?v=', $html);
        self::assertStringContainsString('/img/numa/runtime/blink/numa-face-01.webp?v=', $html);
        self::assertStringContainsString('/img/numa/runtime/blink/numa-face-02.webp?v=', $html);
        self::assertStringContainsString('/img/numa/runtime/wave/numa-arm-20.webp?v=', $html);
        self::assertStringNotContainsString('/img/numa/runtime/numa-face-', $html);
        self::assertStringNotContainsString('/img/numa/runtime/numa-arm-', $html);
        self::assertStringNotContainsString('/img/numa/numa-base.webp?v=', $html);
        self::assertStringNotContainsString('/img/numa/numa-base-master.png?v=', $html);
        self::assertStringContainsString('data-numa-static', $html);
        self::assertStringContainsString('data-numa-animated', $html);
        self::assertStringNotContainsString('data-numa-hybrid', $html);
        self::assertStringContainsString('/js/vendor/gsap/gsap.min.js?v=', $html);
        self::assertStringContainsString('/js/numa-character.js?v=', $html);
    }

    public function testAssetsOptimizadosConservanDimensionesYTransparencia(): void
    {
        foreach (['numa-static'] as $asset) {
            $masterPath = BASE_PATH . '/public/img/numa/' . $asset . '-master.png';
            $optimizedPath = BASE_PATH . '/public/img/numa/' . $asset . '.webp';
            $masterSize = getimagesize($masterPath);
            $optimizedSize = getimagesize($optimizedPath);

            self::assertIsArray($masterSize, $masterPath);
            self::assertIsArray($optimizedSize, $optimizedPath);
            self::assertSame([1024, 1024], [$masterSize[0], $masterSize[1]], $masterPath);
            self::assertSame([$masterSize[0], $masterSize[1]], [$optimizedSize[0], $optimizedSize[1]], $optimizedPath);
            self::assertSame('image/webp', $optimizedSize['mime'], $optimizedPath);

            $optimizedImage = imagecreatefromwebp($optimizedPath);
            self::assertNotFalse($optimizedImage, $optimizedPath);
            self::assertGreaterThan(0, (imagecolorat($optimizedImage, 0, 0) >> 24) & 0x7F, $optimizedPath);
            imagedestroy($optimizedImage);
        }

        $runtimeAssets = array_merge(
            ['numa-body.webp', 'blink/numa-face-00.webp', 'blink/numa-face-01.webp', 'blink/numa-face-02.webp'],
            array_map(static fn (int $frame): string => sprintf('wave/numa-arm-%02d.webp', $frame), range(0, 20))
        );

        foreach ($runtimeAssets as $asset) {
            $runtimePath = BASE_PATH . '/public/img/numa/runtime/' . $asset;
            $runtimeSize = getimagesize($runtimePath);

            self::assertIsArray($runtimeSize, $runtimePath);
            self::assertSame([384, 384], [$runtimeSize[0], $runtimeSize[1]], $runtimePath);
            self::assertSame('image/webp', $runtimeSize['mime'], $runtimePath);

            $runtimeImage = imagecreatefromwebp($runtimePath);
            self::assertNotFalse($runtimeImage, $runtimePath);
            self::assertGreaterThan(0, (imagecolorat($runtimeImage, 0, 0) >> 24) & 0x7F, $runtimePath);
            self::assertLessThan(90000, filesize($runtimePath), $runtimePath);
            imagedestroy($runtimeImage);
        }
    }

    public function testMaestrosDefinitivosDelPersonajeExisten(): void
    {
        $masters = array_merge(
            [BASE_PATH . '/public/img/numa/masters/numa-body-master.png'],
            array_map(static fn (int $frame): string => sprintf(BASE_PATH . '/public/img/numa/masters/blink/numa-blink-%02d-master.png', $frame), [0, 1, 2]),
            array_map(static fn (int $frame): string => sprintf(BASE_PATH . '/public/img/numa/masters/wave/numa-wave-%02d-master.png', $frame), range(0, 20))
        );

        foreach ($masters as $masterPath) {
            $masterSize = getimagesize($masterPath);

            self::assertIsArray($masterSize, $masterPath);
            self::assertSame([1024, 1024], [$masterSize[0], $masterSize[1]], $masterPath);
            self::assertSame('image/png', $masterSize['mime'], $masterPath);

            $masterImage = imagecreatefrompng($masterPath);
            self::assertNotFalse($masterImage, $masterPath);
            self::assertGreaterThan(0, (imagecolorat($masterImage, 0, 0) >> 24) & 0x7F, $masterPath);
            imagedestroy($masterImage);
        }
    }

    public function testInicializadorPrecargaYActivaComposicionSinControlarElChat(): void
    {
        $javascript = file_get_contents(BASE_PATH . '/public/js/numa-character.js');

        self::assertIsString($javascript);
        self::assertStringContainsString("document.querySelectorAll('[data-numa-launcher]')", $javascript);
        self::assertStringContainsString('staticCharacter.hidden = false', $javascript);
        self::assertStringContainsString('Promise.all(requiredAssets.map(preloadImage))', $javascript);
        self::assertStringContainsString('animatedCharacter.hidden = false', $javascript);
        self::assertStringContainsString('staticCharacter.hidden = true', $javascript);
        self::assertStringContainsString("'(prefers-reduced-motion: reduce)'", $javascript);
        self::assertStringContainsString("launcher.addEventListener('pointerenter'", $javascript);
        self::assertStringContainsString("launcher.addEventListener('focus'", $javascript);
        self::assertStringContainsString('const AUTO_WAVE_MIN_DELAY = 10000', $javascript);
        self::assertStringContainsString('const AUTO_WAVE_MAX_DELAY = 15000', $javascript);
        self::assertStringContainsString('const WAVE_FRAME_DURATION = 0.022', $javascript);
        self::assertStringContainsString('playAutomaticWave(3)', $javascript);
        self::assertStringContainsString('appendFullWaveCycle(waveTimeline)', $javascript);
        self::assertStringContainsString('if (interactionActive() || !playAutomaticWave(3))', $javascript);
        self::assertStringContainsString('playArmTo(armFrames.length - 1, completeInteractionRaise)', $javascript);
        self::assertStringContainsString('playArmTo(0, completeInteractionLower)', $javascript);
        self::assertStringContainsString('if (!interactionActive()) {', $javascript);
        self::assertStringContainsString('lowerArmAfterInteraction();', $javascript);
        self::assertStringContainsString("launcher.matches(':focus-visible')", $javascript);
        self::assertStringContainsString('waveTimeline = killTimeline(waveTimeline)', $javascript);
        self::assertStringNotContainsString('addEventListener(\'click\'', $javascript);
        self::assertStringNotContainsString('playWave(1, true)', $javascript);
        self::assertStringNotContainsString('is-character-ready', $javascript);
        self::assertStringNotContainsString('[highFrames[0], highFrames[1], highFrames[0], highFrames[1]]', $javascript);
        self::assertStringNotContainsString('timeline.to({}, { duration: 0.1 })', $javascript);
        self::assertStringNotContainsString('appendArmFrames(timeline, upFrames, 0.032)', $javascript);
        self::assertStringNotContainsString('appendArmFrames(timeline, downFrames, 0.024)', $javascript);
    }

    public function testNoExponeLaApiVisualDescartadaNiEstadosDelChat(): void
    {
        $javascript = file_get_contents(BASE_PATH . '/public/js/numa-character.js');

        self::assertIsString($javascript);
        foreach ([
            'thinking',
            'answer',
            'limit-reached',
            'requiredFaceLayers',
            'data-numa-hybrid',
        ] as $state) {
            self::assertStringNotContainsString($state, $javascript, $state);
        }

        self::assertStringNotContainsString('window.BHNumaCharacter', $javascript);
        self::assertStringNotContainsString('setState', $javascript);
        self::assertStringNotContainsString('dataset.numaState', $javascript);
        self::assertStringNotContainsString('const controllers = new WeakMap()', $javascript);
    }

    public function testNoConservaCanalesNiTimeoutsDelControladorVisualAnterior(): void
    {
        $javascript = file_get_contents(BASE_PATH . '/public/js/numa-character.js');

        self::assertIsString($javascript);
        self::assertStringNotContainsString("const animationChannels = Object.freeze(['ambient', 'transition'])", $javascript);
        self::assertStringNotContainsString('window.clearTimeout(timers[channel])', $javascript);
        self::assertStringNotContainsString('animation.kill()', $javascript);
        self::assertStringNotContainsString('gsap.killTweensOf(animationTargets)', $javascript);
        self::assertStringNotContainsString("gsap.set(animationTargets, { clearProps: 'all' })", $javascript);
        self::assertStringNotContainsString('clearActivity();', $javascript);
        self::assertStringNotContainsString('thinking: 25000', $javascript);
        self::assertStringNotContainsString('answer: 1200', $javascript);
        self::assertStringNotContainsString('setState(stableState)', $javascript);
    }

    private function renderLauncher(): string
    {
        ob_start();
        \bh_numa_launcher();

        return (string) ob_get_clean();
    }
}
