<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AssetHelperTest extends TestCase
{
    private ?string $originalAppEnv = null;

    protected function setUp(): void
    {
        $this->originalAppEnv = $_ENV['APP_ENV'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->originalAppEnv === null) {
            unset($_ENV['APP_ENV']);

            return;
        }

        $_ENV['APP_ENV'] = $this->originalAppEnv;
    }

    public function testAssetVersionaConFilemtimeCuandoExiste(): void
    {
        $mtime = filemtime(BASE_PATH . '/public/js/flash.js');

        self::assertSame('/js/flash.js?v=' . $mtime, \bh_asset('js/flash.js'));
    }

    public function testAssetSinArchivoDevuelveUrlSinVersion(): void
    {
        self::assertSame('/js/no-existe.js', \bh_asset('js/no-existe.js'));
    }

    public function testCssTagsLocalCargaFuentesSeparadas(): void
    {
        $_ENV['APP_ENV'] = 'local';

        $html = \bh_css_tags();

        self::assertStringContainsString('/css/src/vendor/lenis.css?v=', $html);
        self::assertStringContainsString('/css/src/base.css?v=', $html);
        self::assertStringContainsString('/css/src/responsive.css?v=', $html);
        self::assertStringNotContainsString('/css/app.min.css', $html);
        self::assertSame(12, substr_count($html, '<link rel="stylesheet"'));
    }

    public function testCssTagsProductionCargaCssMinificado(): void
    {
        $_ENV['APP_ENV'] = 'production';

        $html = \bh_css_tags();

        self::assertStringContainsString('/css/app.min.css?v=', $html);
        self::assertStringNotContainsString('/css/src/base.css', $html);
        self::assertSame(1, substr_count($html, '<link rel="stylesheet"'));
    }
}
