<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testAceptaElPresupuestoDeSalidaDocumentado(): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';
        $_ENV['NUMA_MAX_OUTPUT_TOKENS'] = '1000';

        \NumaConfiguration::assertRuntime();

        self::addToAssertionCount(1);
    }

    public function testElPresupuestoDeSalidaNoTieneUnTopeInterno(): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';
        $_ENV['NUMA_MAX_OUTPUT_TOKENS'] = '1001';

        \NumaConfiguration::assertRuntime();

        self::addToAssertionCount(1);
    }

    public function testAceptaElMaximoDeLlamadasPorInteraccionNecesarioParaCincoTools(): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';
        $_ENV['NUMA_MAX_PROVIDER_CALLS'] = '9';
        $_ENV['NUMA_MAX_TOOL_CALLS'] = '5';

        \NumaConfiguration::assertRuntime();

        self::addToAssertionCount(1);
    }

    public function testRechazaMasDeNueveLlamadasPorInteraccion(): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';
        $_ENV['NUMA_MAX_PROVIDER_CALLS'] = '10';

        $this->expectException(\NumaConfigurationException::class);
        $this->expectExceptionMessage('NUMA_MAX_PROVIDER_CALLS');

        \NumaConfiguration::assertRuntime();
    }

    public function testRechazaMasDeCincoToolsPorInteraccion(): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';
        $_ENV['NUMA_MAX_TOOL_CALLS'] = '6';

        $this->expectException(\NumaConfigurationException::class);
        $this->expectExceptionMessage('NUMA_MAX_TOOL_CALLS');

        \NumaConfiguration::assertRuntime();
    }

    #[DataProvider('limitesDeFragmentosValidos')]
    public function testAceptaLosLimitesEstructuralesDeFragmentos(string $maxChunkChars): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';
        $_ENV['NUMA_MAX_RAG_CHUNK_CHARS'] = $maxChunkChars;

        \NumaConfiguration::assertRuntime();

        self::addToAssertionCount(1);
    }

    /** @return array<string, array{string}> */
    public static function limitesDeFragmentosValidos(): array
    {
        return [
            'minimo del fragmentador' => [(string) \NumaKnowledgeFragmenter::MIN_CONTENT_CHARS],
            'maximo del fragmentador' => [(string) \NumaKnowledgeFragmenter::MAX_CONTENT_CHARS],
        ];
    }

    #[DataProvider('limitesDeFragmentosInvalidos')]
    public function testRechazaLimitesDeFragmentosFueraDelContrato(string $maxChunkChars): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';
        $_ENV['NUMA_MAX_RAG_CHUNK_CHARS'] = $maxChunkChars;

        $this->expectException(\NumaConfigurationException::class);
        $this->expectExceptionMessage('NUMA_MAX_RAG_CHUNK_CHARS');

        \NumaConfiguration::assertRuntime();
    }

    /** @return array<string, array{string}> */
    public static function limitesDeFragmentosInvalidos(): array
    {
        return [
            'inferior al minimo del fragmentador' => [(string) (\NumaKnowledgeFragmenter::MIN_CONTENT_CHARS - 1)],
            'superior al maximo del fragmentador' => [(string) (\NumaKnowledgeFragmenter::MAX_CONTENT_CHARS + 1)],
        ];
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

    public function testRuntimePrivadoNoExigeConfiguracionPublica(): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';
        $_ENV['NUMA_PUBLIC_ENABLED'] = 'true';
        $_ENV['NUMA_PUBLIC_DAILY_LIMIT'] = '0';

        \NumaConfiguration::assertRuntime();

        self::addToAssertionCount(1);
    }

    #[DataProvider('configuracionPublicaInvalida')]
    public function testRechazaConfiguracionPublicaInvalida(string $key, array $configuration): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';
        $_ENV['NUMA_PUBLIC_ENABLED'] = 'true';
        $_ENV['NUMA_PUBLIC_HASH_KEY'] = str_repeat('a', 32);
        $_ENV['NUMA_PUBLIC_DAILY_LIMIT'] = '15';
        $_ENV['NUMA_PUBLIC_MONTHLY_LIMIT'] = '60';
        $_ENV['NUMA_PUBLIC_GLOBAL_DAILY_CALL_LIMIT'] = '40';
        $_ENV['NUMA_PUBLIC_GLOBAL_MONTHLY_CALL_LIMIT'] = '400';

        foreach ($configuration as $name => $value) {
            $_ENV[$name] = $value;
        }

        $this->expectException(\NumaConfigurationException::class);
        $this->expectExceptionMessage($key);

        \NumaConfiguration::assertRuntime(true);
    }

    /** @return array<string, array{string, array<string, string>}> */
    public static function configuracionPublicaInvalida(): array
    {
        return [
            'hash corto' => ['NUMA_PUBLIC_HASH_KEY', ['NUMA_PUBLIC_HASH_KEY' => str_repeat('a', 31)]],
            'limite diario invalido' => ['NUMA_PUBLIC_DAILY_LIMIT', ['NUMA_PUBLIC_DAILY_LIMIT' => '0']],
            'limite mensual inferior al diario' => [
                'NUMA_PUBLIC_MONTHLY_LIMIT',
                ['NUMA_PUBLIC_DAILY_LIMIT' => '15', 'NUMA_PUBLIC_MONTHLY_LIMIT' => '14'],
            ],
            'limite global diario invalido' => [
                'NUMA_PUBLIC_GLOBAL_DAILY_CALL_LIMIT',
                ['NUMA_PUBLIC_GLOBAL_DAILY_CALL_LIMIT' => '0'],
            ],
            'limite global mensual inferior al diario' => [
                'NUMA_PUBLIC_GLOBAL_MONTHLY_CALL_LIMIT',
                [
                    'NUMA_PUBLIC_GLOBAL_DAILY_CALL_LIMIT' => '40',
                    'NUMA_PUBLIC_GLOBAL_MONTHLY_CALL_LIMIT' => '39',
                ],
            ],
        ];
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
