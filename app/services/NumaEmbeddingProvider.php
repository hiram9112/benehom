<?php

declare(strict_types=1);

require_once __DIR__ . '/NumaProvider.php';

final class NumaEmbeddingSignature
{
    public function __construct(
        private readonly string $provider,
        private readonly string $model,
        private readonly string $taskType,
        private readonly int $dimensions,
        private readonly string $formatVersion,
    ) {
        if (
            trim($provider) === ''
            || trim($model) === ''
            || trim($taskType) === ''
            || $dimensions <= 0
            || trim($formatVersion) === ''
        ) {
            throw new InvalidArgumentException('Firma de embedding de Numa invalida.');
        }
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function value(): string
    {
        return json_encode([
            'provider' => $this->provider,
            'model' => $this->model,
            'task_type' => $this->taskType,
            'dimensions' => $this->dimensions,
            'format_version' => $this->formatVersion,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}

interface NumaEmbeddingProviderInterface
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text): array;

    public function signature(): NumaEmbeddingSignature;
}

interface NumaEmbeddingTaskProviderInterface extends NumaEmbeddingProviderInterface
{
    /**
     * @return array<int, float>
     */
    public function embedDocument(string $text): array;

    /**
     * @return array<int, float>
     */
    public function embedQuery(string $text): array;
}

interface NumaEmbeddingProviderUsageInterface extends NumaEmbeddingProviderInterface
{
    public function tokenUsage(): NumaTokenUsage;
}

interface NumaEmbeddingTimeoutProviderInterface extends NumaEmbeddingProviderInterface
{
    public function withTimeoutSeconds(int $timeoutSeconds): NumaEmbeddingProviderInterface;
}

final class NumaMeteredEmbeddingProvider implements NumaEmbeddingTaskProviderInterface
{
    public function __construct(
        private readonly NumaEmbeddingProviderInterface $provider,
        private readonly NumaProviderConsumptionInterface $consumption,
    ) {
    }

    public function embed(string $text): array
    {
        return $this->embedWith($text, 'embed');
    }

    public function embedDocument(string $text): array
    {
        return $this->embedWith($text, 'embedDocument');
    }

    public function embedQuery(string $text): array
    {
        return $this->embedWith($text, 'embedQuery');
    }

    /**
     * @return array<int, float>
     */
    private function embedWith(string $text, string $method): array
    {
        if (trim($text) === '') {
            throw new InvalidArgumentException('El texto para embeddings de Numa no puede estar vacio.');
        }

        $provider = $this->provider;
        if ($this->consumption instanceof NumaInteractionBudgetInterface) {
            $timeoutSeconds = $this->consumption->timeoutForCall(10);
            if ($provider instanceof NumaEmbeddingTimeoutProviderInterface) {
                $provider = $provider->withTimeoutSeconds($timeoutSeconds);
            }
        }

        $this->consumption->iniciarLlamada();
        $embedding = $provider instanceof NumaEmbeddingTaskProviderInterface
            ? $provider->{$method}($text)
            : $provider->embed($text);
        $usage = $provider instanceof NumaEmbeddingProviderUsageInterface
            ? $provider->tokenUsage()
            : NumaTokenUsage::unknown();
        $this->consumption->registrarTokens($usage);

        return $embedding;
    }

    public function signature(): NumaEmbeddingSignature
    {
        return $this->provider->signature();
    }
}
