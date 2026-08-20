<?php

declare(strict_types=1);

require_once __DIR__ . '/NumaProvider.php';
require_once __DIR__ . '/NumaEmbeddingProvider.php';

/** Proveedor exclusivo de testing; nunca realiza peticiones HTTP. */
final class NumaTestingProvider implements NumaProviderInterface
{
    private const SUCCESS = 'success';
    private const ERROR = 'error';
    private const TIMEOUT = 'timeout';
    private const LIMIT = 'limit';
    private const REQUEST_SCENARIO_HEADER = 'HTTP_X_NUMA_TESTING_SCENARIO';

    /** @var list<string> */
    private const SCENARIOS = [self::SUCCESS, self::ERROR, self::TIMEOUT, self::LIMIT];

    public function __construct(
        private readonly string $scenario,
        private readonly ?NumaProviderConsumptionInterface $consumption = null,
    ) {
        self::assertTestingEnvironment();

        if (!in_array($scenario, self::SCENARIOS, true)) {
            throw self::configurationError();
        }
    }

    public static function fromEnvironment(?NumaProviderConsumptionInterface $consumption = null): self
    {
        self::assertTestingEnvironment();

        return new self(
            self::scenarioFromRequest(),
            $consumption,
        );
    }

    public function respond(NumaRequest $request): NumaResponse
    {
        $this->consumption?->iniciarLlamada();

        if ($this->scenario === self::SUCCESS) {
            $usage = new NumaTokenUsage(12, 8);
            $this->consumption?->registrarTokens($usage);

            if ($request->responseSchema() !== null) {
                return new NumaResponse('Decisión de prueba.', [
                    'intent' => 'producto',
                    'allowed' => true,
                    'reason' => 'product_help',
                    'needs_clarification' => false,
                    'knowledge_query' => $request->message(),
                    'tool' => null,
                ], null, $usage);
            }

            return new NumaResponse('Respuesta de prueba de Numa.', null, null, $usage);
        }

        throw match ($this->scenario) {
            self::ERROR => new NumaProviderException(new NumaProviderError(
                NumaProviderError::UNAVAILABLE,
                'NUMA_PROVIDER_UNAVAILABLE',
            )),
            self::TIMEOUT => new NumaProviderException(new NumaProviderError(
                NumaProviderError::TIMEOUT,
                'NUMA_PROVIDER_TIMEOUT',
            )),
            self::LIMIT => new NumaProviderException(new NumaProviderError(
                NumaProviderError::RATE_LIMIT,
                'NUMA_PROVIDER_RATE_LIMITED',
            )),
        };
    }

    private static function assertTestingEnvironment(): void
    {
        if (bh_env_value('APP_ENV') !== 'testing') {
            throw self::configurationError();
        }
    }

    private static function scenarioFromRequest(): string
    {
        if (!array_key_exists(self::REQUEST_SCENARIO_HEADER, $_SERVER)) {
            return self::SUCCESS;
        }

        $scenario = $_SERVER[self::REQUEST_SCENARIO_HEADER];
        if (!is_string($scenario)) {
            throw self::configurationError();
        }

        return strtolower(trim($scenario));
    }

    private static function configurationError(): NumaProviderException
    {
        return new NumaProviderException(new NumaProviderError(
            NumaProviderError::CONFIGURATION,
            'NUMA_CONFIGURATION_ERROR',
        ));
    }
}

final class NumaTestingEmbeddingProvider implements NumaEmbeddingTaskProviderInterface
{
    public function __construct(private readonly int $dimensions = 768)
    {
        if ($dimensions < 1 || bh_env_value('APP_ENV') !== 'testing') {
            throw new NumaProviderException(new NumaProviderError(
                NumaProviderError::CONFIGURATION,
                'NUMA_CONFIGURATION_ERROR',
            ));
        }
    }

    public static function fromEnvironment(): self
    {
        return new self(bh_env_int('NUMA_EMBEDDING_DIMENSIONS', 768));
    }

    public function embed(string $text): array
    {
        return $this->vector($text);
    }

    public function embedDocument(string $text): array
    {
        return $this->vector($text);
    }

    public function embedQuery(string $text): array
    {
        return $this->vector($text);
    }

    public function signature(): NumaEmbeddingSignature
    {
        return new NumaEmbeddingSignature('testing', 'numa-testing-embedding', 'RETRIEVAL_DOCUMENT', $this->dimensions, '1');
    }

    /** @return array<int, float> */
    private function vector(string $text): array
    {
        if (trim($text) === '') {
            throw new InvalidArgumentException('El texto para embeddings de Numa no puede estar vacio.');
        }

        $vector = array_fill(0, $this->dimensions, 0.0);
        $vector[hexdec(substr(hash('sha256', $text), 0, 8)) % $this->dimensions] = 1.0;

        return $vector;
    }
}
