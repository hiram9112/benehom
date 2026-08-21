<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/NumaConfiguration.php';

final class NumaConfigurationTest extends TestCase
{
    /** @var array<string, string> */
    private array $previousEnvironment = [];

    protected function setUp(): void
    {
        foreach ($_ENV as $key => $value) {
            if (str_starts_with($key, 'NUMA_')) {
                $this->previousEnvironment[$key] = (string) $value;
                unset($_ENV[$key]);
            }
        }

        $_ENV['APP_ENV'] = 'testing';
        $_ENV['NUMA_API_KEY'] = 'test-api-key';
    }

    protected function tearDown(): void
    {
        foreach (array_keys($_ENV) as $key) {
            if (str_starts_with($key, 'NUMA_')) {
                unset($_ENV[$key]);
            }
        }

        $_ENV = array_replace($_ENV, $this->previousEnvironment);
        parent::tearDown();
    }

    public function testAceptaLaConfiguracionEfectivaDeRuntime(): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';

        \NumaConfiguration::assertRuntime();

        self::addToAssertionCount(1);
    }

    public function testRechazaUnBodyQueNoPuedeContenerElMensajeUnicodeMaximo(): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';
        $_ENV['NUMA_MAX_REQUEST_BODY_BYTES'] = '300';

        $this->expectException(\NumaConfigurationException::class);
        $this->expectExceptionMessage('NUMA_MAX_REQUEST_BODY_BYTES');

        \NumaConfiguration::assertRuntime();
    }

    public function testExigeSecretoDeSeudonimizacionEnModoPublico(): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';
        $_ENV['NUMA_PUBLIC_ENABLED'] = 'true';

        $this->expectException(\NumaConfigurationException::class);
        $this->expectExceptionMessage('NUMA_PUBLIC_HASH_KEY');

        \NumaConfiguration::assertRuntime(true);
    }

    public function testLaIndexacionNoExigeActivarElChatNiUnModeloGenerativo(): void
    {
        unset($_ENV['NUMA_API_KEY']);
        $_ENV['NUMA_EMBEDDING_PROVIDER'] = 'fake';

        \NumaConfiguration::assertIndexing();

        self::addToAssertionCount(1);
    }

    public function testAceptaElBypassLocalYUsuariosExentosConfigurados(): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';
        $_ENV['NUMA_BYPASS_LIMITS'] = 'true';
        $_ENV['NUMA_LIMIT_EXEMPT_USER_IDS'] = '12,34';

        \NumaConfiguration::assertRuntime();

        self::addToAssertionCount(1);
    }

    public function testRechazaUnaListaDeUsuariosExentosInvalida(): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';
        $_ENV['NUMA_LIMIT_EXEMPT_USER_IDS'] = '12, propietario';

        $this->expectException(\NumaConfigurationException::class);
        $this->expectExceptionMessage('NUMA_LIMIT_EXEMPT_USER_IDS');

        \NumaConfiguration::assertRuntime();
    }

    public function testElHtaccessRaizProtegeLosRecursosInternosDeNuma(): void
    {
        $configuration = (string) file_get_contents(BASE_PATH . '/.htaccess');

        foreach (['bin', 'knowledge', 'resources', 'tests', '\\.git'] as $directory) {
            self::assertStringContainsString($directory, $configuration);
        }
    }
}
