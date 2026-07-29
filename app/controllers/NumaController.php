<?php

declare(strict_types=1);

require_once APP_PATH . '/models/NumaUso.php';
require_once APP_PATH . '/models/Database.php';
require_once APP_PATH . '/services/NumaClassification.php';
require_once APP_PATH . '/services/GeminiNumaProvider.php';
require_once APP_PATH . '/services/GeminiEmbeddingProvider.php';
require_once APP_PATH . '/services/NumaKnowledge.php';

class NumaController
{
    private const NO_KNOWLEDGE_MESSAGE = 'No encuentro información suficiente sobre esa función dentro de BeneHom.';

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

        $localRejection = $this->localScopeClassifier()->classify($message);

        if ($localRejection !== null) {
            bh_json_success([
                'message' => $localRejection->message(),
            ]);
            return;
        }

        try {
            $numaUso = $this->numaUso();
            $reservationId = $numaUso->reservar((int) $_SESSION['usuario_id']);
        } catch (NumaUsoLimiteAlcanzado $exception) {
            bh_numa_error($exception->limitCode(), 429);
            return;
        } catch (Throwable) {
            bh_numa_error('NUMA_USAGE_ERROR', 503);
            return;
        }

        $providerFailure = null;

        try {
            $classification = $this->providerScopeClassifier()->classify($message);
        } catch (Throwable $exception) {
            $providerFailure = $exception;
        }

        try {
            $confirmed = $numaUso->confirmar($reservationId);
        } catch (Throwable) {
            bh_numa_error('NUMA_USAGE_ERROR', 503);
            return;
        }

        if (!$confirmed) {
            bh_numa_error('NUMA_USAGE_ERROR', 503);
            return;
        }

        if ($providerFailure instanceof NumaGlobalLimiteAlcanzado) {
            bh_numa_error('NUMA_GLOBAL_LIMIT_REACHED', 503);
            return;
        }

        if ($providerFailure instanceof NumaProviderException) {
            bh_numa_error($providerFailure->providerError()->safeCode(), 503);
            return;
        }

        if ($providerFailure !== null) {
            bh_numa_error('NUMA_PROVIDER_INVALID_RESPONSE', 503);
            return;
        }

        if (!$classification->allowed()) {
            bh_json_success([
                'message' => NumaFixedScopeResponse::forIntent($classification->intent(), $classification->reason()),
            ]);
            return;
        }

        try {
            $knowledgeResults = $this->knowledgeResults($classification, $message);
        } catch (NumaProviderException $exception) {
            bh_numa_error($exception->providerError()->safeCode(), 503);
            return;
        } catch (Throwable) {
            bh_numa_error('NUMA_PROVIDER_INVALID_RESPONSE', 503);
            return;
        }

        if ($knowledgeResults === []) {
            bh_json_success([
                'message' => self::NO_KNOWLEDGE_MESSAGE,
            ]);
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

    protected function localScopeClassifier(): NumaLocalScopeClassifier
    {
        return new NumaLocalScopeClassifier();
    }

    protected function providerScopeClassifier(): NumaProviderScopeClassifier
    {
        return new NumaProviderScopeClassifier(NumaProviderFactory::fromEnvironment());
    }

    /**
     * @return array<int, NumaKnowledgeSearchResult>
     */
    protected function knowledgeResults(NumaClassification $classification, string $message): array
    {
        $knowledgeQuery = $classification->knowledgeQuery() ?? $message;

        return $this->knowledgeSearcher()->search($knowledgeQuery);
    }

    protected function knowledgeSearcher(): NumaKnowledgeSearcher
    {
        return new NumaKnowledgeSearcher(
            Database::getConnection(),
            NumaEmbeddingProviderFactory::fromEnvironment(),
            bh_env_int('NUMA_EMBEDDING_DIMENSIONS', 768),
            bh_env_int('NUMA_MAX_RAG_RESULTS', 3),
            (float) bh_env_value('NUMA_RAG_MIN_SIMILARITY', '0.65')
        );
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
