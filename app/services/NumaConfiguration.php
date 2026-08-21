<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/utils.php';

final class NumaConfigurationException extends RuntimeException
{
}

/** Validates the effective Numa configuration before a paid flow can start. */
final class NumaConfiguration
{
    public static function assertRuntime(bool $publicMode = false): void
    {
        self::assertSharedLimits();
        self::assertProvider('NUMA_PROVIDER');
        self::assertRequiredString('NUMA_MODEL', 'gemini-3.1-flash-lite');
        self::assertEmbeddingProvider();
        self::assertRequiredString('NUMA_EMBEDDING_MODEL', 'gemini-embedding-001');

        if (self::providerValue('NUMA_PROVIDER', 'gemini') === 'gemini'
            || self::providerValue('NUMA_EMBEDDING_PROVIDER', 'gemini') === 'gemini') {
            self::assertRequiredString('NUMA_API_KEY');
        }

        if ($publicMode) {
            self::assertBoolean('NUMA_PUBLIC_ENABLED', false);
            self::assertRequiredString('NUMA_PUBLIC_HASH_KEY', minLength: 32);
            self::assertInteger('NUMA_PUBLIC_DAILY_LIMIT', 5, 1);
            self::assertInteger('NUMA_PUBLIC_MONTHLY_LIMIT', 20, self::integerValue('NUMA_PUBLIC_DAILY_LIMIT', 5));
            self::assertInteger('NUMA_PUBLIC_GLOBAL_DAILY_CALL_LIMIT', 40, 1);
            self::assertInteger(
                'NUMA_PUBLIC_GLOBAL_MONTHLY_CALL_LIMIT',
                400,
                self::integerValue('NUMA_PUBLIC_GLOBAL_DAILY_CALL_LIMIT', 40),
            );
        }
    }

    public static function assertIndexing(): void
    {
        self::assertSharedLimits();
        self::assertEmbeddingProvider();
        self::assertRequiredString('NUMA_EMBEDDING_MODEL', 'gemini-embedding-001');

        if (self::providerValue('NUMA_EMBEDDING_PROVIDER', 'gemini') === 'gemini') {
            self::assertRequiredString('NUMA_API_KEY');
        }
    }

    public static function assertRagEvaluation(): void
    {
        self::assertIndexing();

        if (self::providerValue('NUMA_EMBEDDING_PROVIDER', 'gemini') !== 'gemini') {
            self::invalid('NUMA_EMBEDDING_PROVIDER');
        }

        foreach (['NUMA_RAG_EVALUATION_DB_HOST', 'NUMA_RAG_EVALUATION_DB_NAME', 'NUMA_RAG_EVALUATION_DB_USER'] as $key) {
            self::assertRequiredString($key);
        }

        self::assertInteger('NUMA_RAG_EVALUATION_DB_PORT', 3306, 1, 65535);
    }

    private static function assertSharedLimits(): void
    {
        self::assertBoolean('NUMA_ENABLED', false);
        self::assertBoolean('NUMA_PUBLIC_ENABLED', false);
        self::assertInteger('NUMA_MAX_MESSAGE_LENGTH', 300, 1, 300);
        self::assertInteger(
            'NUMA_MAX_REQUEST_BODY_BYTES',
            2048,
            // Four UTF-8 bytes per character plus the JSON envelope.
            (self::integerValue('NUMA_MAX_MESSAGE_LENGTH', 300) * 4) + 64,
        );
        self::assertInteger('NUMA_MAX_PROVIDER_RESPONSE_BODY_BYTES', 65536, 1024);
        self::assertInteger('NUMA_CHAT_BURST_MAX_REQUESTS', 5, 1);
        self::assertInteger('NUMA_CHAT_BURST_WINDOW_SECONDS', 60, 1);
        self::assertInteger('NUMA_CHAT_BURST_BLOCK_SECONDS', 60, 1);
        self::assertBoolean('NUMA_BYPASS_LIMITS', false);
        self::assertUserIdList('NUMA_LIMIT_EXEMPT_USER_IDS');
        self::assertInteger('NUMA_DAILY_LIMIT', 5, 1);
        self::assertInteger('NUMA_MONTHLY_LIMIT', 20, self::integerValue('NUMA_DAILY_LIMIT', 5));
        self::assertInteger('NUMA_RESERVATION_TTL_SECONDS', 120, 1);
        self::assertInteger('NUMA_MAX_INPUT_TOKENS', 5000, 1, 5000);
        self::assertInteger('NUMA_MAX_OUTPUT_TOKENS', 220, 1, 220);
        self::assertInteger('NUMA_MAX_PROVIDER_CALLS', 3, 1, 3);
        self::assertInteger('NUMA_PROVIDER_TIMEOUT_SECONDS', 10, 1, 10);
        self::assertInteger('NUMA_REQUEST_TIMEOUT_SECONDS', 25, 1, 25);
        self::assertInteger('NUMA_MAX_TRANSIENT_RETRIES', 1, 0, 1);
        self::assertInteger('NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT', 100, 1);
        self::assertInteger(
            'NUMA_GLOBAL_MONTHLY_PROVIDER_CALL_LIMIT',
            1000,
            self::integerValue('NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT', 100),
        );
        self::assertInteger('NUMA_GLOBAL_DAILY_TOKEN_LIMIT', 50000, 1);
        self::assertInteger(
            'NUMA_GLOBAL_MONTHLY_TOKEN_LIMIT',
            300000,
            self::integerValue('NUMA_GLOBAL_DAILY_TOKEN_LIMIT', 50000),
        );
        self::assertInteger('NUMA_EMBEDDING_DIMENSIONS', 768, 768, 768);
        self::assertInteger('NUMA_MAX_RAG_RESULTS', 3, 1, 3);
        self::assertInteger('NUMA_MAX_RAG_CHUNK_CHARS', 900, 1, 900);
        self::assertFloat('NUMA_RAG_MIN_SIMILARITY', 0.67, 0.0, 1.0);
        self::assertInteger('NUMA_MAX_TOOL_CALLS', 2, 1, 2);
        self::assertInteger('NUMA_MAX_TOOL_RESULT_CHARS', 1600, 1, 1600);
        self::assertInteger('NUMA_MAX_TOOL_RANGE_DAYS', 731, 1);
    }

    private static function assertEmbeddingProvider(): void
    {
        self::assertProvider('NUMA_EMBEDDING_PROVIDER');
    }

    private static function assertProvider(string $key): void
    {
        $provider = self::providerValue($key, 'gemini');
        if ($provider === 'gemini') {
            return;
        }

        if ($provider === 'fake' && strtolower((string) bh_env_value('APP_ENV', 'local')) === 'testing') {
            return;
        }

        self::invalid($key);
    }

    private static function assertBoolean(string $key, bool $default): void
    {
        $value = self::value($key, $default ? 'true' : 'false');
        if (!in_array(strtolower($value), ['0', '1', 'false', 'true', 'no', 'yes', 'off', 'on'], true)) {
            self::invalid($key);
        }
    }

    private static function assertInteger(string $key, int $default, int $minimum, ?int $maximum = null): void
    {
        $value = self::value($key, (string) $default);
        if (preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value) !== 1) {
            self::invalid($key);
        }

        $integer = (int) $value;
        if ($integer < $minimum || ($maximum !== null && $integer > $maximum)) {
            self::invalid($key);
        }
    }

    private static function assertFloat(string $key, float $default, float $minimum, float $maximum): void
    {
        $value = self::value($key, (string) $default);
        if (!is_numeric($value)) {
            self::invalid($key);
        }

        $number = (float) $value;
        if (!is_finite($number) || $number < $minimum || $number > $maximum) {
            self::invalid($key);
        }
    }

    private static function assertUserIdList(string $key): void
    {
        $value = self::value($key, '');
        if ($value === '') {
            return;
        }

        foreach (explode(',', $value) as $userId) {
            if (preg_match('/\A[1-9][0-9]*\z/', $userId) !== 1) {
                self::invalid($key);
            }
        }
    }

    private static function assertRequiredString(string $key, ?string $default = null, int $minLength = 1): void
    {
        $value = self::value($key, $default);
        if (strlen($value) < $minLength) {
            self::invalid($key);
        }
    }

    private static function integerValue(string $key, int $default): int
    {
        return (int) self::value($key, (string) $default);
    }

    private static function providerValue(string $key, string $default): string
    {
        return strtolower(self::value($key, $default));
    }

    private static function value(string $key, ?string $default): string
    {
        $value = bh_env_value($key, $default);

        return $value ?? '';
    }

    private static function invalid(string $key): never
    {
        throw new NumaConfigurationException('Configuracion de Numa invalida: ' . $key);
    }
}
