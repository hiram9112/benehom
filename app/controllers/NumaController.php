<?php

declare(strict_types=1);

require_once APP_PATH . '/models/NumaUso.php';

class NumaController
{
    private const DISALLOWED_CLIENT_KEYS = [
        'usuario_id',
        'user_id',
        'provider',
        'proveedor',
        'model',
        'modelo',
        'instructions',
        'instrucciones',
        'system',
        'tools',
    ];

    public function chat(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            bh_json_error('METHOD_NOT_ALLOWED', bh_router_error_message('METHOD_NOT_ALLOWED'), 405);
            return;
        }

        if (empty($_SESSION['usuario_id'])) {
            bh_json_error('UNAUTHENTICATED', bh_router_error_message('UNAUTHENTICATED'), 401);
            return;
        }

        if (!$this->hasJsonContentType()) {
            bh_numa_error('NUMA_INVALID_MESSAGE', 400);
            return;
        }

        if (!csrf_validate()) {
            bh_numa_error('NUMA_INVALID_CSRF', 403);
            return;
        }

        $payload = $this->requestPayload();

        if ($payload === null) {
            bh_numa_error('NUMA_INVALID_MESSAGE', 400);
            return;
        }

        foreach (self::DISALLOWED_CLIENT_KEYS as $key) {
            if (array_key_exists($key, $payload)) {
                bh_numa_error('NUMA_INVALID_MESSAGE', 400);
                return;
            }
        }

        if (!array_key_exists('message', $payload)) {
            bh_numa_error('NUMA_INVALID_MESSAGE', 400);
            return;
        }

        $message = $payload['message'];

        if (!is_string($message) || trim($message) === '') {
            bh_numa_error('NUMA_INVALID_MESSAGE', 400);
            return;
        }

        $maxLength = bh_env_int('NUMA_MAX_MESSAGE_LENGTH', 300);

        if ($this->textLength($message) > $maxLength) {
            bh_numa_error('NUMA_MESSAGE_TOO_LONG', 422);
            return;
        }

        $available = bh_env_bool('NUMA_ENABLED', false);

        if (!$available) {
            bh_numa_error('NUMA_NOT_AVAILABLE', 503);
            return;
        }

        try {
            $numaUso = $this->numaUso();
            $reservationId = $numaUso->reservar((int) $_SESSION['usuario_id']);
            $numaUso->revertir($reservationId);
        } catch (NumaUsoLimiteAlcanzado $e) {
            bh_numa_error($e->limitCode(), 429);
            return;
        } catch (Throwable) {
            bh_numa_error('NUMA_USAGE_ERROR', 503);
            return;
        }

        bh_numa_error('NUMA_NOT_AVAILABLE', 503);
    }

    public function status(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            bh_json_error('METHOD_NOT_ALLOWED', bh_router_error_message('METHOD_NOT_ALLOWED'), 405);
            return;
        }

        if (empty($_SESSION['usuario_id'])) {
            bh_json_error('UNAUTHENTICATED', bh_router_error_message('UNAUTHENTICATED'), 401);
            return;
        }

        try {
            $usage = $this->numaUso()->estado((int) $_SESSION['usuario_id']);

            bh_json_success([
                'available' => bh_env_bool('NUMA_ENABLED', false),
                'usage' => $usage,
            ]);
        } catch (Throwable) {
            bh_numa_error('NUMA_USAGE_ERROR', 503);
        }
    }

    protected function numaUso(): NumaUso
    {
        return new NumaUso();
    }

    protected function rawBody(): string
    {
        return (string) file_get_contents('php://input');
    }

    private function requestPayload(): ?array
    {
        $rawBody = $this->rawBody();

        if (trim($rawBody) === '') {
            return null;
        }

        $decoded = json_decode($rawBody, true);

        if (!is_array($decoded)) {
            return null;
        }

        if ($decoded === []) {
            return preg_match('/^\s*\{\s*\}\s*$/', $rawBody) === 1 ? $decoded : null;
        }

        return array_is_list($decoded) ? null : $decoded;
    }

    private function hasJsonContentType(): bool
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));

        return str_contains($contentType, 'application/json');
    }

    private function textLength(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        return strlen($text);
    }
}
