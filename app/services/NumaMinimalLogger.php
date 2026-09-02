<?php

declare(strict_types=1);

/** Registra el desenlace técnico de Numa sin contenido de la conversación. */
final class NumaMinimalLogger
{
    private const ALLOWED_STAGES = [
        'request',
        'availability',
        'scope',
        'classification',
        'knowledge',
        'response',
    ];

    /** @var Closure(string):void */
    private readonly Closure $writer;

    /** @var list<string> */
    private array $executedTools = [];

    public function __construct(
        ?Closure $writer = null,
        private readonly bool $detailedDiagnostics = false,
    ) {
        $this->correlationId = bin2hex(random_bytes(16));
        $this->writer = $writer ?? $this->defaultWriter();
    }

    private readonly string $correlationId;

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function recordExecutedTool(string $tool): void
    {
        if ($this->detailedDiagnostics && preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $tool) === 1) {
            $this->executedTools[] = $tool;
        }
    }

    /** @return Closure(string):void */
    private function defaultWriter(): Closure
    {
        if (($_ENV['APP_ENV'] ?? '') === 'testing') {
            return static function (string $_entry): void {
            };
        }

        return static function (string $entry): void {
            error_log($entry);
        };
    }

    public function record(
        string $stage,
        int $startedAt,
        int $calls,
        ?int $tokens,
        bool $successful,
        ?string $errorCode = null,
    ): void {
        try {
            $payload = [
                'correlation_id' => $this->correlationId,
                'stage' => $this->safeStage($stage),
                'duration_ms' => max(0, (int) floor((hrtime(true) - $startedAt) / 1_000_000)),
                'calls' => max(0, $calls),
                'tokens' => $tokens === null ? null : max(0, $tokens),
                ...($this->detailedDiagnostics ? ['tools' => $this->executedTools] : []),
                'outcome' => $successful ? 'success' : 'error',
                'error_code' => $successful ? null : $this->safeErrorCode($errorCode),
            ];

            ($this->writer)('numa ' . json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (Throwable) {
            // El log no debe alterar el resultado de una interacción de Numa.
        }
    }

    private function safeErrorCode(?string $errorCode): string
    {
        if ($errorCode !== null && preg_match('/\ANUMA_[A-Z0-9_]{1,59}\z/', $errorCode) === 1) {
            return $errorCode;
        }

        return 'NUMA_INTERNAL_ERROR';
    }

    private function safeStage(string $stage): string
    {
        return in_array($stage, self::ALLOWED_STAGES, true) ? $stage : 'request';
    }
}
