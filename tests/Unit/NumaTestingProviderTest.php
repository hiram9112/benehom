<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/NumaTestingProvider.php';
require_once APP_PATH . '/services/GeminiNumaProvider.php';

final class NumaTestingProviderTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $environment = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['APP_ENV', 'NUMA_PROVIDER'] as $key) {
            $this->environment[$key] = $_ENV[$key] ?? null;
        }

        $this->environment['HTTP_X_NUMA_TESTING_SCENARIO'] = $_SERVER['HTTP_X_NUMA_TESTING_SCENARIO'] ?? null;
        $_ENV['APP_ENV'] = 'testing';
        unset($_SERVER['HTTP_X_NUMA_TESTING_SCENARIO']);
    }

    protected function tearDown(): void
    {
        foreach ($this->environment as $key => $value) {
            if ($key === 'HTTP_X_NUMA_TESTING_SCENARIO') {
                if ($value === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $value;
                }

                continue;
            }

            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }

        parent::tearDown();
    }

    public function testSimulaRespuestaCorrectaConConsumoControlado(): void
    {
        $consumption = new NumaTestingConsumptionFake();
        $response = (new \NumaTestingProvider('success', $consumption))->respond(new \NumaRequest('Pregunta'));

        self::assertSame('Respuesta de prueba de Numa.', $response->message());
        self::assertSame(1, $consumption->startedCalls);
        self::assertSame(12, $consumption->registeredUsage?->inputTokens());
        self::assertSame(8, $consumption->registeredUsage?->outputTokens());
    }

    #[DataProvider('providerErrorScenarios')]
    public function testSimulaErroresDeProveedor(string $scenario, string $type, string $code): void
    {
        $this->expectException(\NumaProviderException::class);
        $this->expectExceptionMessage($code);

        try {
            (new \NumaTestingProvider($scenario))->respond(new \NumaRequest('Pregunta'));
        } catch (\NumaProviderException $exception) {
            self::assertSame($type, $exception->providerError()->type());
            throw $exception;
        }
    }

    /** @return array<string, array{string, string, string}> */
    public static function providerErrorScenarios(): array
    {
        return [
            'error' => ['error', \NumaProviderError::UNAVAILABLE, 'NUMA_PROVIDER_UNAVAILABLE'],
            'timeout' => ['timeout', \NumaProviderError::TIMEOUT, 'NUMA_PROVIDER_TIMEOUT'],
            'limit' => ['limit', \NumaProviderError::RATE_LIMIT, 'NUMA_PROVIDER_RATE_LIMITED'],
        ];
    }

    public function testLaFactoriaSeleccionaElEscenarioDelRequestDeTesting(): void
    {
        $_ENV['NUMA_PROVIDER'] = 'fake';
        $_SERVER['HTTP_X_NUMA_TESTING_SCENARIO'] = 'timeout';
        $consumption = new NumaTestingConsumptionFake();

        $this->expectException(\NumaProviderException::class);
        $this->expectExceptionMessage('NUMA_PROVIDER_TIMEOUT');

        try {
            \NumaProviderFactory::fromEnvironment(consumption: $consumption)
                ->respond(new \NumaRequest('Pregunta'));
        } finally {
            self::assertSame(1, $consumption->startedCalls);
        }
    }

    public function testRechazaElProveedorFalsoFueraDeTesting(): void
    {
        $_ENV['APP_ENV'] = 'local';

        $this->expectException(\NumaProviderException::class);
        $this->expectExceptionMessage('NUMA_CONFIGURATION_ERROR');

        new \NumaTestingProvider('success');
    }
}

final class NumaTestingConsumptionFake implements \NumaProviderConsumptionInterface
{
    public int $startedCalls = 0;

    public ?\NumaTokenUsage $registeredUsage = null;

    public function iniciarLlamada(): void
    {
        ++$this->startedCalls;
    }

    public function registrarTokens(\NumaTokenUsage $usage): void
    {
        $this->registeredUsage = $usage;
    }
}
