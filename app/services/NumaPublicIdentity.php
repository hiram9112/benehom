<?php

declare(strict_types=1);

final class NumaPublicIdentity
{
    public const COOKIE_NAME = 'bh_numa_anon';
    private const TOKEN_BYTES = 32;
    private const COOKIE_LIFETIME_SECONDS = 2592000;

    private ?string $token = null;

    public function token(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $candidate = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (is_string($candidate) && preg_match('/^[a-f0-9]{64}$/', $candidate) === 1) {
            return $this->token = $candidate;
        }

        $this->token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $this->setCookie($this->token);

        return $this->token;
    }

    public function visitorHash(): string
    {
        return $this->hash($this->token());
    }

    public function hash(string $value): string
    {
        $key = trim((string) bh_env_value('NUMA_PUBLIC_HASH_KEY', ''));
        if ($key === '') {
            throw new RuntimeException('Falta la clave de seudonimizacion publica de Numa.');
        }

        return hash_hmac('sha256', $value, $key);
    }

    private function setCookie(string $token): void
    {
        setcookie(self::COOKIE_NAME, $token, [
            'expires' => time() + self::COOKIE_LIFETIME_SECONDS,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
