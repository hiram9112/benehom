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

interface NumaEmbeddingProviderUsageInterface extends NumaEmbeddingProviderInterface
{
    public function tokenUsage(): NumaTokenUsage;
}

final class NumaMeteredEmbeddingProvider implements NumaEmbeddingProviderInterface
{
    public function __construct(
        private readonly NumaEmbeddingProviderInterface $provider,
        private readonly NumaProviderConsumptionInterface $consumption,
    ) {
    }

    public function embed(string $text): array
    {
        if (trim($text) === '') {
            throw new InvalidArgumentException('El texto para embeddings de Numa no puede estar vacio.');
        }

        $this->consumption->iniciarLlamada();
        $embedding = $this->provider->embed($text);
        $usage = $this->provider instanceof NumaEmbeddingProviderUsageInterface
            ? $this->provider->tokenUsage()
            : NumaTokenUsage::unknown();
        $this->consumption->registrarTokens($usage);

        return $embedding;
    }

    public function signature(): NumaEmbeddingSignature
    {
        return $this->provider->signature();
    }
}
