<?php

declare(strict_types=1);

interface NumaEmbeddingProviderInterface
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text): array;
}
