<?php

declare(strict_types=1);

require_once APP_PATH . '/models/NumaUso.php';
require_once APP_PATH . '/models/Database.php';
require_once APP_PATH . '/services/NumaClassification.php';
require_once APP_PATH . '/services/NumaConversation.php';
require_once APP_PATH . '/services/GeminiNumaProvider.php';
require_once APP_PATH . '/services/GeminiEmbeddingProvider.php';
require_once APP_PATH . '/services/NumaFinancialTools.php';
require_once APP_PATH . '/services/NumaKnowledge.php';
require_once APP_PATH . '/services/NumaService.php';

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

    private const ALLOWED_CLIENT_KEYS = ['message'];

    public function chat(): void
    {
        bh_json_no_store_private();

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

        foreach (array_keys($payload) as $key) {
            if (!is_string($key) || !in_array($key, self::ALLOWED_CLIENT_KEYS, true)) {
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

        if (!bh_env_bool('NUMA_ENABLED', false)) {
            bh_numa_error('NUMA_NOT_AVAILABLE', 503);
            return;
        }

        $authenticatedUserId = (int) $_SESSION['usuario_id'];

        try {
            $conversation = $this->conversation($authenticatedUserId);
            $result = $this->numaService()->answer(
                $authenticatedUserId,
                $message,
                $conversation->context(),
            );

            if (!$this->sessionStillOwnedBy($authenticatedUserId)) {
                $conversation->clear();
                bh_json_error('UNAUTHENTICATED', bh_router_error_message('UNAUTHENTICATED'), 401);
                return;
            }

            $data = $result->toArray();
            $conversation->appendExchange(
                $message,
                (string) $data['message'],
                $result->sources(),
                is_array($data['period'] ?? null) ? $data['period'] : null,
                $result->contextual(),
            );
            $data['conversation'] = $conversation->transcript();

            bh_json_success($data);
        } catch (NumaServiceException $exception) {
            bh_numa_error(
                $exception->safeCode(),
                $exception->statusCode(),
                $exception->errorData() !== [] ? $exception->errorData() : null
            );
        } catch (NumaProviderException $exception) {
            bh_numa_error($exception->providerError()->safeCode(), 503);
        } catch (Throwable) {
            bh_numa_error('NUMA_INTERNAL_ERROR', 503);
        }
    }

    public function status(): void
    {
        bh_json_no_store_private();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            bh_json_error('METHOD_NOT_ALLOWED', bh_router_error_message('METHOD_NOT_ALLOWED'), 405);
            return;
        }

        if (empty($_SESSION['usuario_id'])) {
            bh_json_error('UNAUTHENTICATED', bh_router_error_message('UNAUTHENTICATED'), 401);
            return;
        }

        $authenticatedUserId = (int) $_SESSION['usuario_id'];

        try {
            $usage = $this->numaUso()->estado($authenticatedUserId);

            bh_json_success([
                'available' => bh_env_bool('NUMA_ENABLED', false),
                'usage' => $usage,
                'conversation' => $this->conversation($authenticatedUserId)->transcript(),
            ]);
        } catch (Throwable) {
            bh_numa_error('NUMA_USAGE_ERROR', 503);
        }
    }

    public function newConversation(): void
    {
        bh_json_no_store_private();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            bh_json_error('METHOD_NOT_ALLOWED', bh_router_error_message('METHOD_NOT_ALLOWED'), 405);
            return;
        }

        if (empty($_SESSION['usuario_id'])) {
            bh_json_error('UNAUTHENTICATED', bh_router_error_message('UNAUTHENTICATED'), 401);
            return;
        }

        $authenticatedUserId = (int) $_SESSION['usuario_id'];

        if (!csrf_validate()) {
            bh_numa_error('NUMA_INVALID_CSRF', 403);
            return;
        }

        try {
            $this->conversation($authenticatedUserId)->clear();
            bh_json_success([
                'available' => bh_env_bool('NUMA_ENABLED', false),
                'usage' => $this->numaUso()->estado($authenticatedUserId),
                'conversation' => [],
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

    protected function conversation(?int $authenticatedUserId = null): NumaConversation
    {
        return new NumaConversation($authenticatedUserId);
    }

    private function sessionStillOwnedBy(int $authenticatedUserId): bool
    {
        $currentUserId = $_SESSION['usuario_id'] ?? null;

        return (is_int($currentUserId) || (is_string($currentUserId) && ctype_digit($currentUserId)))
            && (int) $currentUserId === $authenticatedUserId;
    }

    protected function providerScopeClassifier(): NumaProviderScopeClassifier
    {
        return new NumaProviderScopeClassifier($this->provider());
    }

    protected function provider(?NumaProviderConsumptionInterface $consumption = null): NumaProviderInterface
    {
        if ($consumption === null) {
            return NumaProviderFactory::fromEnvironment();
        }

        return NumaProviderFactory::fromEnvironment(
            consumption: new NumaProviderConsumptionChain($consumption, NumaConsumoGlobal::forLlm())
        );
    }

    protected function financialTools(): NumaFinancialToolRegistryInterface
    {
        return new NumaFinancialToolRegistry();
    }

    protected function globalAvailability(): NumaGlobalAvailabilityInterface
    {
        return new NumaGlobalAvailability();
    }

    protected function numaService(): NumaService
    {
        return new NumaService(
            $this->numaUso(),
            $this->localScopeClassifier(),
            fn (): NumaProviderScopeClassifier => $this->providerScopeClassifier(),
            fn (?NumaProviderConsumptionInterface $consumption = null): NumaProviderInterface => $this->provider($consumption),
            fn (NumaClassification $classification, string $message, ?NumaProviderConsumptionInterface $consumption = null): array => $this->knowledgeResults($classification, $message, $consumption),
            fn (): NumaFinancialToolRegistryInterface => $this->financialTools(),
            fn (): NumaGlobalAvailabilityInterface => $this->globalAvailability()
        );
    }

    /**
     * @return array<int, NumaKnowledgeSearchResult>
     */
    protected function knowledgeResults(
        NumaClassification $classification,
        string $message,
        ?NumaProviderConsumptionInterface $consumption = null,
    ): array
    {
        $knowledgeQuery = $classification->knowledgeQuery() ?? $message;

        return $this->knowledgeSearcher($consumption)->search($knowledgeQuery);
    }

    protected function knowledgeSearcher(?NumaProviderConsumptionInterface $consumption = null): NumaKnowledgeSearcher
    {
        $embeddingProvider = NumaEmbeddingProviderFactory::fromEnvironment();

        if ($consumption !== null) {
            $embeddingProvider = new NumaMeteredEmbeddingProvider(
                $embeddingProvider,
                new NumaProviderConsumptionChain($consumption, NumaConsumoGlobal::forEmbedding())
            );
        }

        return new NumaKnowledgeSearcher(
            Database::getConnection(),
            $embeddingProvider,
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
