<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/utils.php';

final class N8nWebhookService
{
    private const TIMEOUT_SECONDS = 3;

    /** @param (Closure(string, array<int, string>, string, int): int)|null $transport */
    public function __construct(private readonly ?Closure $transport = null)
    {
    }

    public function notifyVerifiedUser(int $userId, string $name): bool
    {
        return $this->send('N8N_USER_REGISTERED_WEBHOOK_URL', [
            'user_id' => $userId,
            'name' => $name,
        ]);
    }

    public function notifyNumaUsageAlert(): bool
    {
        return $this->send('N8N_NUMA_USAGE_WEBHOOK_URL', []);
    }

    /** @param array<string, int|string> $payload */
    private function send(string $urlEnvKey, array $payload): bool
    {
        $url = bh_env_value($urlEnvKey, '') ?? '';
        $secret = bh_env_value('N8N_WEBHOOK_SECRET', '') ?? '';
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($url === '' || $secret === '' || !is_string($scheme) || strtolower($scheme) !== 'https') {
            return false;
        }

        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
            $headers = [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-BeneHom-Webhook-Secret: ' . $secret,
            ];
            $status = $this->transport !== null
                ? ($this->transport)($url, $headers, $body, self::TIMEOUT_SECONDS)
                : $this->curlTransport($url, $headers, $body, self::TIMEOUT_SECONDS);

            return $status >= 200 && $status < 300;
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<int, string> $headers */
    private function curlTransport(string $url, array $headers, string $body, int $timeoutSeconds): int
    {
        if (bh_env_value('APP_ENV') === 'testing' || !function_exists('curl_init')) {
            throw new RuntimeException('El transporte de webhooks no esta disponible.');
        }

        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException('No se pudo iniciar el transporte de webhooks.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $completed = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        if ($completed === false) {
            throw new RuntimeException('Fallo el transporte de webhooks.');
        }

        return $status;
    }
}
