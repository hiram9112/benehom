<?php

declare(strict_types=1);

require_once __DIR__ . '/NumaProvider.php';
require_once __DIR__ . '/NumaEmbeddingProvider.php';
require_once dirname(__DIR__) . '/helpers/utils.php';

final class GeminiEmbeddingProvider implements NumaEmbeddingProviderInterface
{
    private const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';
    private const DEFAULT_DIMENSIONS = 768;
    private const TASK_TYPE = 'SEMANTIC_SIMILARITY';

    /** @var callable */
    private $transport;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $dimensions = self::DEFAULT_DIMENSIONS,
        private readonly int $timeoutSeconds = 10,
        ?callable $transport = null,
        private readonly string $baseUrl = self::API_BASE_URL,
    ) {
        if (trim($apiKey) === '' || trim($model) === '' || $dimensions <= 0) {
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
            $transport
        );
    }

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        if (trim($text) === '') {
            throw new InvalidArgumentException('El texto para embeddings de Numa no puede estar vacio.');
        }

        $payload = [
            'taskType' => self::TASK_TYPE,
            'output_dimensionality' => $this->dimensions,
            'content' => [
                'parts' => [[
                    'text' => $text,
                ]],
            ],
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

        if ($status < 200 || $status >= 300) {
            throw $this->httpError($status, $responseBody);
        }

        return $this->parseEmbedding($responseBody);
    }

    /**
     * @param array<int, string> $headers
     * @return array{status:int, body:string}
     */
    public function curlTransport(string $url, array $headers, string $body, int $timeoutSeconds): array
    {
        if (!function_exists('curl_init')) {
            throw self::configurationError();
        }

        $handle = curl_init($url);

        if ($handle === false) {
            throw self::unavailableError();
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => max(1, min(5, $timeoutSeconds)),
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $responseBody = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($handle);
        unset($handle);

        if ($responseBody === false) {
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
            'body' => (string) $responseBody,
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

        return $values;
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

            $embedding[] = (float) $value;
        }

        return $embedding;
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
