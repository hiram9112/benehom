<?php

declare(strict_types=1);

require_once __DIR__ . '/NumaProvider.php';

interface NumaEmbeddingProviderInterface
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text): array;
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

        return $this->provider->embed($text);
    }
}
