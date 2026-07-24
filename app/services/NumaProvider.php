<?php

declare(strict_types=1);

interface NumaProviderInterface
{
    public function respond(NumaRequest $request): NumaResponse;
}

final class NumaRequest
{
    /**
     * @param array<int, array<string, mixed>> $context
     * @param array<int, string> $availableTools
     */
    public function __construct(
        private readonly string $message,
        private readonly string $systemInstruction = '',
        private readonly array $context = [],
        private readonly array $availableTools = [],
    ) {
    }

    public function message(): string
    {
        return $this->message;
    }

    public function systemInstruction(): string
    {
        return $this->systemInstruction;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * @return array<int, string>
     */
    public function availableTools(): array
    {
        return $this->availableTools;
    }
}

final class NumaResponse
{
    /**
     * @param array<string, mixed>|null $structuredData
     */
    public function __construct(
        private readonly string $message,
        private readonly ?array $structuredData = null,
        private readonly ?NumaToolRequest $toolRequest = null,
        private readonly ?NumaTokenUsage $tokenUsage = null,
    ) {
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function structuredData(): ?array
    {
        return $this->structuredData;
    }

    public function toolRequest(): ?NumaToolRequest
    {
        return $this->toolRequest;
    }

    public function tokenUsage(): NumaTokenUsage
    {
        return $this->tokenUsage ?? NumaTokenUsage::unknown();
    }
}

final class NumaTokenUsage
{
    public function __construct(
        private readonly ?int $inputTokens,
        private readonly ?int $outputTokens,
    ) {
        if ($inputTokens !== null && $inputTokens < 0) {
            throw new InvalidArgumentException('El uso de tokens de entrada no puede ser negativo.');
        }

        if ($outputTokens !== null && $outputTokens < 0) {
            throw new InvalidArgumentException('El uso de tokens de salida no puede ser negativo.');
        }
    }

    public static function unknown(): self
    {
        return new self(null, null);
    }

    public function inputTokens(): ?int
    {
        return $this->inputTokens;
    }

    public function outputTokens(): ?int
    {
        return $this->outputTokens;
    }

    public function totalTokens(): ?int
    {
        if (!$this->hasReliableTokens()) {
            return null;
        }

        return $this->inputTokens + $this->outputTokens;
    }

    public function hasReliableTokens(): bool
    {
        return $this->inputTokens !== null && $this->outputTokens !== null;
    }
}

final class NumaToolRequest
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        private readonly string $name,
        private readonly array $arguments = [],
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('La solicitud de tool debe tener nombre.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }
}

final class NumaProviderError
{
    public const AUTHENTICATION = 'authentication';
    public const QUOTA = 'quota';
    public const RATE_LIMIT = 'rate_limit';
    public const TIMEOUT = 'timeout';
    public const TRANSIENT = 'transient';
    public const INVALID_RESPONSE = 'invalid_response';
    public const CONFIGURATION = 'configuration';
    public const UNAVAILABLE = 'unavailable';

    private const ALLOWED_TYPES = [
        self::AUTHENTICATION,
        self::QUOTA,
        self::RATE_LIMIT,
        self::TIMEOUT,
        self::TRANSIENT,
        self::INVALID_RESPONSE,
        self::CONFIGURATION,
        self::UNAVAILABLE,
    ];

    public function __construct(
        private readonly string $type,
        private readonly string $safeCode,
        private readonly bool $retryable = false,
    ) {
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('Tipo de error de proveedor no soportado.');
        }

        if (trim($safeCode) === '') {
            throw new InvalidArgumentException('El error de proveedor debe tener un codigo seguro.');
        }
    }

    public function type(): string
    {
        return $this->type;
    }

    public function safeCode(): string
    {
        return $this->safeCode;
    }

    public function retryable(): bool
    {
        return $this->retryable;
    }
}

final class NumaProviderException extends RuntimeException
{
    public function __construct(
        private readonly NumaProviderError $providerError,
        ?Throwable $previous = null,
    ) {
        parent::__construct($providerError->safeCode(), 0, $previous);
    }

    public function providerError(): NumaProviderError
    {
        return $this->providerError;
    }
}
