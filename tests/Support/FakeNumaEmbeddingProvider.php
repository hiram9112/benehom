<?php

declare(strict_types=1);

namespace Tests\Support;

require_once APP_PATH . '/services/NumaEmbeddingProvider.php';

final class FakeNumaEmbeddingProvider implements \NumaEmbeddingTaskProviderInterface
{
    public int $calls = 0;

    /** @var array<int, string> */
    public array $texts = [];

    /** @var array<int, string> */
    public array $tasks = [];

    /**
     * @param array<string, array<int, float>> $vectors
     */
    public function __construct(
        private readonly int $dimensions = 768,
        private readonly array $vectors = [],
        private readonly string $provider = 'fake',
        private readonly string $model = 'deterministic',
        private readonly string $taskType = 'RETRIEVAL_DOCUMENT',
        private readonly string $formatVersion = '1',
    ) {
        if ($dimensions <= 0) {
            throw new \InvalidArgumentException('La dimension del fake de embeddings de Numa es invalida.');
        }
    }

    /**
     * @param array<string, array<int, float>> $vectors
     */
    public static function withVectors(array $vectors, int $dimensions): self
    {
        return new self($dimensions, $vectors);
    }

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        return $this->embedDocument($text);
    }

    /**
     * @return array<int, float>
     */
    public function embedDocument(string $text): array
    {
        return $this->embedWithTask('document', $text);
    }

    /**
     * @return array<int, float>
     */
    public function embedQuery(string $text): array
    {
        return $this->embedWithTask('query', $text);
    }

    /**
     * @return array<int, float>
     */
    private function embedWithTask(string $task, string $text): array
    {
        $this->calls++;
        $this->texts[] = $text;
        $this->tasks[] = $task;

        if (isset($this->vectors[$text])) {
            return $this->vectors[$text];
        }

        $hash = hash('sha256', $text, true);
        $vector = [];

        for ($index = 0; $index < $this->dimensions; $index++) {
            $byte = ord($hash[$index % strlen($hash)]);
            $vector[] = round(($byte / 127.5) - 1.0, 6);
        }

        return $vector;
    }

    public function signature(): \NumaEmbeddingSignature
    {
        return new \NumaEmbeddingSignature(
            $this->provider,
            $this->model,
            $this->taskType,
            $this->dimensions,
            $this->formatVersion
        );
    }
}
