<?php

declare(strict_types=1);

require_once __DIR__ . '/NumaProvider.php';
require_once __DIR__ . '/NumaEmbeddingProvider.php';
require_once dirname(__DIR__) . '/helpers/utils.php';

final class GeminiEmbeddingProvider implements NumaEmbeddingProviderUsageInterface, NumaEmbeddingTaskProviderInterface, NumaEmbeddingTimeoutProviderInterface
{
    private const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';
    private const DEFAULT_DIMENSIONS = 768;
    private const DOCUMENT_TASK_TYPE = 'RETRIEVAL_DOCUMENT';
    private const QUERY_TASK_TYPE = 'RETRIEVAL_QUERY';
    private const FORMAT_VERSION = '3';
    private const FULL_DIMENSIONS = 3072;

    /** @var callable */
    private $transport;

    private ?NumaTokenUsage $lastTokenUsage = null;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $dimensions = self::DEFAULT_DIMENSIONS,
        private readonly int $timeoutSeconds = 10,
        ?callable $transport = null,
        private readonly string $baseUrl = self::API_BASE_URL,
        private readonly int $maxResponseBodyBytes = 65536,
    ) {
        if (trim($apiKey) === '' || trim($model) === '' || $dimensions <= 0 || $maxResponseBodyBytes <= 0) {
            throw self::configurationError();
        }

        $this->transport = $transport ?? [$this, 'curlTransport'];
    }

    public static function fromEnvironment(?callable $transport = null): self
    {
        return new self(
            (string) bh_env_value('NUMA_EMBEDDING_API_KEY', ''),
            (string) bh_env_value('NUMA_EMBEDDING_MODEL', 'gemini-embedding-001'),
            bh_env_int('NUMA_EMBEDDING_DIMENSIONS', self::DEFAULT_DIMENSIONS),
            bh_env_int('NUMA_PROVIDER_TIMEOUT_SECONDS', 10),
            $transport,
            self::API_BASE_URL,
            bh_numa_max_provider_response_body_bytes(),
        );
    }

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        return $this->embedDocument($text);
    }

    public function embedDocument(string $text): array
    {
        return $this->embedWithTaskType($text, self::DOCUMENT_TASK_TYPE);
    }

    public function embedQuery(string $text): array
    {
        return $this->embedWithTaskType($text, self::QUERY_TASK_TYPE);
    }

    /**
     * @return array<int, float>
     */
    private function embedWithTaskType(string $text, string $taskType): array
    {
        $this->lastTokenUsage = null;

        if (trim($text) === '') {
            throw new InvalidArgumentException('El texto para embeddings de Numa no puede estar vacio.');
        }

        $payload = [
            'content' => [
                'parts' => [[
                    'text' => $text,
                ]],
            ],
            'taskType' => $taskType,
            'outputDimensionality' => $this->dimensions,
        ];
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $url = rtrim($this->baseUrl, '/') . '/models/' . rawurlencode($this->model) . ':embedContent';
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8',
            'x-goog-api-key: ' . $this->apiKey,
        ];

        try {
            $result = ($this->transport)(
                $url,
                $headers,
                $body,
                $this->safeTimeoutSeconds()
            );
        } catch (NumaProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw self::unavailableError($exception);
        }

        $status = isset($result['status']) && is_int($result['status']) ? $result['status'] : 0;
        $responseBody = isset($result['body']) && is_string($result['body']) ? $result['body'] : '';
        $this->assertResponseBodySize($responseBody);

        if ($status < 200 || $status >= 300) {
            throw $this->httpError($status, $responseBody);
        }

        return $this->parseEmbedding($responseBody);
    }

    public function tokenUsage(): NumaTokenUsage
    {
        return $this->lastTokenUsage ?? NumaTokenUsage::unknown();
    }

    public function signature(): NumaEmbeddingSignature
    {
        return new NumaEmbeddingSignature(
            'gemini',
            $this->model,
            self::DOCUMENT_TASK_TYPE,
            $this->dimensions,
            self::FORMAT_VERSION
        );
    }

    public function withTimeoutSeconds(int $timeoutSeconds): NumaEmbeddingProviderInterface
    {
        return new self(
            $this->apiKey,
            $this->model,
            $this->dimensions,
            max(1, min($timeoutSeconds, 10)),
            $this->transport,
            $this->baseUrl,
            $this->maxResponseBodyBytes,
        );
    }

    /**
     * @param array<int, string> $headers
     * @return array{status:int, body:string}
     */
    public function curlTransport(string $url, array $headers, string $body, int $timeoutSeconds): array
    {
        if (bh_env_value('APP_ENV') === 'testing') {
            throw self::configurationError();
        }

        if (!function_exists('curl_init')) {
            throw self::configurationError();
        }

        $handle = curl_init($url);

        if ($handle === false) {
            throw self::unavailableError();
        }

        $responseBody = '';
        $responseTooLarge = false;
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => max(1, min(5, $timeoutSeconds)),
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => function ($curlHandle, string $chunk) use (&$responseBody, &$responseTooLarge): int {
                if (strlen($responseBody) + strlen($chunk) > $this->maxResponseBodyBytes) {
                    $responseTooLarge = true;

                    return 0;
                }

                $responseBody .= $chunk;

                return strlen($chunk);
            },
        ]);

        $completed = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($handle);
        curl_close($handle);

        if ($responseTooLarge) {
            throw self::invalidResponseError();
        }

        if ($completed === false) {
            if ($errno === CURLE_OPERATION_TIMEDOUT || $errno === CURLE_COULDNT_CONNECT) {
                throw new NumaProviderException(new NumaProviderError(
                    NumaProviderError::TIMEOUT,
                    'NUMA_PROVIDER_TIMEOUT'
                ));
            }

            throw self::unavailableError();
        }

        return [
            'status' => $status,
            'body' => $responseBody,
        ];
    }

    private function safeTimeoutSeconds(): int
    {
        return max(1, min($this->timeoutSeconds, 10));
    }

    /**
     * @return array<int, float>
     */
    private function parseEmbedding(string $responseBody): array
    {
        $this->assertResponseBodySize($responseBody);

        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw self::invalidResponseError($exception);
        }

        if (!is_array($decoded)) {
            throw self::invalidResponseError();
        }

        $values = $this->extractValues($decoded);

        if (count($values) !== $this->dimensions) {
            throw self::invalidResponseError();
        }

        $this->lastTokenUsage = $this->extractTokenUsage($decoded);

        return $this->normalizeReducedEmbedding($values);
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractTokenUsage(array $decoded): NumaTokenUsage
    {
        $usage = $decoded['usageMetadata'] ?? null;
        $promptTokenCount = is_array($usage)
            ? ($usage['promptTokenCount'] ?? $usage['totalTokenCount'] ?? null)
            : null;

        if (!is_int($promptTokenCount) || $promptTokenCount < 0) {
            return NumaTokenUsage::unknown();
        }

        return new NumaTokenUsage($promptTokenCount, 0);
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array<int, float>
     */
    private function extractValues(array $decoded): array
    {
        $values = $decoded['embedding']['values']
            ?? $decoded['embeddings'][0]['values']
            ?? $decoded['embeddings'][0]['value']
            ?? null;

        if (!is_array($values)) {
            throw self::invalidResponseError();
        }

        $embedding = [];

        foreach ($values as $value) {
            if (!is_int($value) && !is_float($value)) {
                throw self::invalidResponseError();
            }

            $value = (float) $value;

            if (!is_finite($value)) {
                throw self::invalidResponseError();
            }

            $embedding[] = $value;
        }

        return $embedding;
    }

    /**
     * gemini-embedding-001 requires client-side normalization for reduced vectors.
     *
     * @param array<int, float> $embedding
     * @return array<int, float>
     */
    private function normalizeReducedEmbedding(array $embedding): array
    {
        $magnitude = 0.0;

        foreach ($embedding as $value) {
            $magnitude += $value * $value;
        }

        if (!is_finite($magnitude) || $magnitude <= 0.0) {
            throw self::invalidResponseError();
        }

        if ($this->model !== 'gemini-embedding-001' || $this->dimensions === self::FULL_DIMENSIONS) {
            return $embedding;
        }

        $magnitude = sqrt($magnitude);

        return array_map(
            static fn (float $value): float => $value / $magnitude,
            $embedding
        );
    }

    private function httpError(int $status, string $responseBody): NumaProviderException
    {
        if ($status === 401 || $status === 403) {
            return new NumaProviderException(new NumaProviderError(
                NumaProviderError::AUTHENTICATION,
                'NUMA_PROVIDER_AUTH_ERROR'
            ));
        }

        if ($status === 429) {
            return $this->quotaExceeded($responseBody)
                ? new NumaProviderException(new NumaProviderError(NumaProviderError::QUOTA, 'NUMA_PROVIDER_QUOTA_EXCEEDED'))
                : new NumaProviderException(new NumaProviderError(NumaProviderError::RATE_LIMIT, 'NUMA_PROVIDER_RATE_LIMITED'));
        }

        if ($status === 400 || $status === 404) {
            return self::configurationError();
        }

        if ($status === 408) {
            return new NumaProviderException(new NumaProviderError(
                NumaProviderError::TIMEOUT,
                'NUMA_PROVIDER_TIMEOUT'
            ));
        }

        return self::unavailableError();
    }

    private function quotaExceeded(string $responseBody): bool
    {
        return preg_match('/quota|resource_exhausted|billing|exceeded/i', $responseBody) === 1;
    }

    private static function configurationError(): NumaProviderException
    {
        return new NumaProviderException(new NumaProviderError(
            NumaProviderError::CONFIGURATION,
            'NUMA_CONFIGURATION_ERROR'
        ));
    }

    private static function invalidResponseError(?Throwable $previous = null): NumaProviderException
    {
        return new NumaProviderException(new NumaProviderError(
            NumaProviderError::INVALID_RESPONSE,
            'NUMA_PROVIDER_INVALID_RESPONSE'
        ), $previous);
    }

    private function assertResponseBodySize(string $responseBody): void
    {
        if (strlen($responseBody) > $this->maxResponseBodyBytes) {
            throw self::invalidResponseError();
        }
    }

    private static function unavailableError(?Throwable $previous = null): NumaProviderException
    {
        return new NumaProviderException(new NumaProviderError(
            NumaProviderError::UNAVAILABLE,
            'NUMA_PROVIDER_UNAVAILABLE'
        ), $previous);
    }
}

final class NumaEmbeddingProviderFactory
{
    public static function fromEnvironment(?callable $transport = null): NumaEmbeddingProviderInterface
    {
        $provider = strtolower((string) bh_env_value('NUMA_EMBEDDING_PROVIDER', 'gemini'));

        if ($provider !== 'gemini') {
            throw new NumaProviderException(new NumaProviderError(
                NumaProviderError::CONFIGURATION,
                'NUMA_CONFIGURATION_ERROR'
            ));
        }

        return GeminiEmbeddingProvider::fromEnvironment($transport);
    }
}
