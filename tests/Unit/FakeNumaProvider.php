<?php

declare(strict_types=1);

require_once APP_PATH . '/services/NumaProvider.php';

final class FakeNumaProvider implements NumaProviderInterface
{
    public const VALID_RESPONSE = 'valid_response';
    public const STRUCTURED_RESPONSE = 'structured_response';
    public const TOOL_REQUEST = 'tool_request';
    public const TIMEOUT = 'timeout';
    public const AUTHENTICATION_ERROR = 'authentication_error';
    public const QUOTA_EXCEEDED = 'quota_exceeded';
    public const RATE_LIMITED = 'rate_limited';
    public const TRANSIENT_ERROR = 'transient_error';
    public const INVALID_JSON = 'invalid_json';
    public const EMPTY_RESPONSE = 'empty_response';

    private ?NumaRequest $lastRequest = null;

    /** @var array<int, NumaRequest> */
    private array $requests = [];

    public function __construct(
        private readonly string $scenario = self::VALID_RESPONSE,
        private readonly ?NumaResponse $response = null,
    ) {
        if (!in_array($scenario, self::scenarios(), true)) {
            throw new InvalidArgumentException('Escenario de fake Numa no soportado.');
        }
    }

    public static function validResponse(
        string $message = 'Respuesta valida de Numa.',
        ?NumaTokenUsage $tokenUsage = null,
    ): self {
        return new self(self::VALID_RESPONSE, new NumaResponse($message, null, null, $tokenUsage));
    }

    /**
     * @param array<string, mixed> $structuredData
     */
    public static function structuredResponse(
        array $structuredData,
        string $message = 'Respuesta estructurada de Numa.',
        ?NumaTokenUsage $tokenUsage = null,
    ): self {
        return new self(self::STRUCTURED_RESPONSE, new NumaResponse($message, $structuredData, null, $tokenUsage));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public static function toolRequest(
        string $toolName = 'obtener_resumen_financiero',
        array $arguments = [],
        ?NumaTokenUsage $tokenUsage = null,
    ): self {
        return new self(self::TOOL_REQUEST, new NumaResponse(
            'Necesito consultar datos agregados.',
            null,
            new NumaToolRequest($toolName, $arguments),
            $tokenUsage
        ));
    }

    public static function withTokenUsage(int $inputTokens = 120, int $outputTokens = 35): self
    {
        return self::validResponse('Respuesta con uso de tokens.', new NumaTokenUsage($inputTokens, $outputTokens));
    }

    public static function withoutTokenUsage(): self
    {
        return self::validResponse('Respuesta sin uso fiable de tokens.');
    }

    public static function timeout(): self
    {
        return new self(self::TIMEOUT);
    }

    public static function authenticationError(): self
    {
        return new self(self::AUTHENTICATION_ERROR);
    }

    public static function quotaExceeded(): self
    {
        return new self(self::QUOTA_EXCEEDED);
    }

    public static function rateLimited(): self
    {
        return new self(self::RATE_LIMITED);
    }

    public static function transientError(): self
    {
        return new self(self::TRANSIENT_ERROR);
    }

    public static function invalidJson(): self
    {
        return new self(self::INVALID_JSON);
    }

    public static function emptyResponse(): self
    {
        return new self(self::EMPTY_RESPONSE);
    }

    public function respond(NumaRequest $request): NumaResponse
    {
        $this->lastRequest = $request;
        $this->requests[] = $request;

        $response = match ($this->scenario) {
            self::VALID_RESPONSE,
            self::STRUCTURED_RESPONSE,
            self::TOOL_REQUEST => $this->response ?? new NumaResponse('Respuesta valida de Numa.'),
            self::TIMEOUT => throw new NumaProviderException(new NumaProviderError(
                NumaProviderError::TIMEOUT,
                'NUMA_PROVIDER_TIMEOUT',
                true
            )),
            self::AUTHENTICATION_ERROR => throw new NumaProviderException(new NumaProviderError(
                NumaProviderError::AUTHENTICATION,
                'NUMA_PROVIDER_AUTH_ERROR'
            )),
            self::QUOTA_EXCEEDED => throw new NumaProviderException(new NumaProviderError(
                NumaProviderError::QUOTA,
                'NUMA_PROVIDER_QUOTA_EXCEEDED'
            )),
            self::RATE_LIMITED => throw new NumaProviderException(new NumaProviderError(
                NumaProviderError::RATE_LIMIT,
                'NUMA_PROVIDER_RATE_LIMITED'
            )),
            self::TRANSIENT_ERROR => throw new NumaProviderException(new NumaProviderError(
                NumaProviderError::TRANSIENT,
                'NUMA_PROVIDER_UNAVAILABLE',
                true
            )),
            self::INVALID_JSON,
            self::EMPTY_RESPONSE => throw new NumaProviderException(new NumaProviderError(
                NumaProviderError::INVALID_RESPONSE,
                'NUMA_PROVIDER_INVALID_RESPONSE'
            )),
        };

        return $this->functionalDecisionResponse($request, $response);
    }

    public function lastRequest(): ?NumaRequest
    {
        return $this->lastRequest;
    }

    /**
     * @return array<int, NumaRequest>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    /**
     * @return array<int, string>
     */
    private static function scenarios(): array
    {
        return [
            self::VALID_RESPONSE,
            self::STRUCTURED_RESPONSE,
            self::TOOL_REQUEST,
            self::TIMEOUT,
            self::AUTHENTICATION_ERROR,
            self::QUOTA_EXCEEDED,
            self::RATE_LIMITED,
            self::TRANSIENT_ERROR,
            self::INVALID_JSON,
            self::EMPTY_RESPONSE,
        ];
    }

    private function functionalDecisionResponse(NumaRequest $request, NumaResponse $response): NumaResponse
    {
        $data = $response->structuredData();
        if ($request->responseSchema() === null || $data === null || array_key_exists('needs_clarification', $data)) {
            return $response;
        }

        return new NumaResponse($response->message(), [
            'intent' => $data['intent'] ?? null,
            'allowed' => $data['allowed'] ?? null,
            'reason' => $data['reason'] ?? null,
            'needs_clarification' => false,
            'knowledge_query' => $data['knowledge_query'] ?? null,
            'tool' => null,
        ], $response->toolRequest(), $response->tokenUsage());
    }
}
