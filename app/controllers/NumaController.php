<?php

declare(strict_types=1);

require_once APP_PATH . '/models/NumaUso.php';
require_once APP_PATH . '/models/NumaPublicUso.php';
require_once APP_PATH . '/models/Database.php';
require_once APP_PATH . '/models/IntentoAcceso.php';
require_once APP_PATH . '/services/NumaClassification.php';
require_once APP_PATH . '/services/NumaConversation.php';
require_once APP_PATH . '/services/GeminiNumaProvider.php';
require_once APP_PATH . '/services/GeminiEmbeddingProvider.php';
require_once APP_PATH . '/services/NumaFinancialTools.php';
require_once APP_PATH . '/services/NumaKnowledge.php';
require_once APP_PATH . '/services/NumaService.php';
require_once APP_PATH . '/services/NumaPublicIdentity.php';

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
        'mode',
        'public',
        'visitor_id',
        'visitante_id',
        'anonymous_id',
        'anon_id',
        'permissions',
        'permisos',
    ];

    private const ALLOWED_CLIENT_KEYS = ['message'];

    private const CHAT_RATE_LIMIT_ACTION = 'numa_chat';
    private const PUBLIC_CHAT_RATE_LIMIT_ACTION = 'numa_public_chat_ip';
    private const CHAT_REQUEST_SESSION_KEY = 'numa_chat_request';
    private const PUBLIC_CHAT_REQUEST_SESSION_KEY = 'numa_public_chat_request';
    private const CHAT_REQUEST_EXPIRY_MARGIN_SECONDS = 5;

    private const STATUS_REASON_DISABLED = 'disabled';
    private const STATUS_REASON_CONFIGURATION_INCOMPLETE = 'configuration_incomplete';
    private const STATUS_REASON_TEMPORARILY_UNAVAILABLE = 'temporarily_unavailable';
    private const STATUS_REASON_USER_LIMIT = 'user_limit';
    private const STATUS_REASON_GLOBAL_LIMIT = 'global_limit';
    private const STATUS_REASON_VISITOR_LIMIT = 'visitor_limit';
    private const STATUS_REASON_PUBLIC_GLOBAL_LIMIT = 'public_global_limit';

    private const AVAILABILITY_AVAILABLE = 'available';
    private const AVAILABILITY_NEAR_LIMIT = 'near_limit';
    private const AVAILABILITY_LIMIT_REACHED = 'limit_reached';
    private const AVAILABILITY_UNAVAILABLE = 'unavailable';
    private const AVAILABILITY_CONFIGURATION_REQUIRED = 'configuration_required';

    private const REQUIRED_STATUS_TABLES = [
        'numa_uso',
        'numa_reservas',
        'numa_uso_proveedor',
        'numa_conocimiento',
    ];

    private const REQUIRED_PUBLIC_STATUS_TABLES = [
        'numa_uso_publico',
        'numa_reservas_publicas',
        'numa_uso_proveedor',
        'numa_conocimiento',
    ];

    private bool $requestBodyTooLarge = false;

    private ?NumaPublicIdentity $resolvedPublicIdentity = null;

    public function chat(): void
    {
        if (!$this->beginJsonRequest('POST')) {
            return;
        }

        if (empty($_SESSION['usuario_id'])) {
            bh_json_error('UNAUTHENTICATED', bh_router_error_message('UNAUTHENTICATED'), 401);
            return;
        }

        $message = $this->validatedMessage();
        if ($message === null) {
            return;
        }

        if (!bh_env_bool('NUMA_ENABLED', false)) {
            bh_numa_error('NUMA_NOT_AVAILABLE', 503);
            return;
        }

        $authenticatedUserId = (int) $_SESSION['usuario_id'];

        if ($this->isChatRateLimited($authenticatedUserId)) {
            bh_numa_error('NUMA_RATE_LIMITED', 429);
            return;
        }

        $chatRequest = null;

        try {
            $conversation = $this->conversation($authenticatedUserId);
            $conversationVersion = $conversation->version();
            $chatRequest = $this->startChatRequest($authenticatedUserId, $conversationVersion);

            if ($chatRequest === null) {
                bh_numa_error('NUMA_REQUEST_IN_PROGRESS', 409);
                return;
            }

            // Copy the session-backed context before releasing PHP's session lock for the slow request.
            $context = $conversation->context();
            $result = $this->answerWithReleasedSession(
                fn (): NumaServiceResult => $this->numaService()->answer(
                    $authenticatedUserId,
                    $message,
                    $context,
                ),
            );

            if (!$this->sessionStillOwnedBy($authenticatedUserId, $conversationVersion)) {
                bh_json_error('UNAUTHENTICATED', bh_router_error_message('UNAUTHENTICATED'), 401);
                return;
            }

            $data = $this->responseData($result);
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
            $this->respondServiceError($exception);
        } catch (NumaProviderException $exception) {
            bh_numa_error($exception->providerError()->safeCode(), 503);
        } catch (Throwable) {
            bh_numa_error('NUMA_INTERNAL_ERROR', 503);
        } finally {
            if ($chatRequest !== null) {
                $this->clearChatRequest($chatRequest);
            }
        }
    }

    public function status(): void
    {
        if (!$this->beginJsonRequest('GET')) {
            return;
        }

        if (empty($_SESSION['usuario_id'])) {
            bh_json_error('UNAUTHENTICATED', bh_router_error_message('UNAUTHENTICATED'), 401);
            return;
        }

        $authenticatedUserId = (int) $_SESSION['usuario_id'];

        $this->respondWithStatus(
            $this->effectiveStatus($authenticatedUserId),
            $this->conversation($authenticatedUserId)->transcript(),
        );
    }

    public function newConversation(): void
    {
        if (!$this->beginJsonRequest('POST')) {
            return;
        }

        if (empty($_SESSION['usuario_id'])) {
            bh_json_error('UNAUTHENTICATED', bh_router_error_message('UNAUTHENTICATED'), 401);
            return;
        }

        $authenticatedUserId = (int) $_SESSION['usuario_id'];

        $this->resetConversation(
            function () use ($authenticatedUserId): void {
                $this->conversation($authenticatedUserId)->clear();
            },
            fn (): array => $this->effectiveStatus($authenticatedUserId),
        );
    }

    public function publicChat(): void
    {
        if (!$this->beginJsonRequest('POST')) {
            return;
        }

        try {
            $visitorHash = $this->publicIdentity()->visitorHash();
        } catch (Throwable) {
            bh_numa_error('NUMA_NOT_AVAILABLE', 503);
            return;
        }

        $message = $this->validatedMessage();
        if ($message === null) {
            return;
        }

        if (!$this->isPublicChatEnabled() || $this->isPublicChatRateLimited()) {
            bh_numa_error($this->isPublicChatEnabled() ? 'NUMA_RATE_LIMITED' : 'NUMA_NOT_AVAILABLE', $this->isPublicChatEnabled() ? 429 : 503);
            return;
        }

        $chatRequest = null;

        try {
            $conversation = NumaConversation::forVisitor($visitorHash);
            $conversationVersion = $conversation->publicVersion();
            $chatRequest = $this->startPublicChatRequest($visitorHash, $conversationVersion);
            if ($chatRequest === null) {
                bh_numa_error('NUMA_REQUEST_IN_PROGRESS', 409);
                return;
            }

            $context = $conversation->publicContext();
            $result = $this->answerWithReleasedSession(
                fn (): NumaServiceResult => $this->answerPublic($visitorHash, $message, $context),
            );

            if (!$this->publicSessionStillOwnedBy($visitorHash, $conversationVersion)) {
                bh_numa_error('NUMA_NOT_AVAILABLE', 503);
                return;
            }

            $data = $this->responseData($result);
            $conversation->appendPublicExchange(
                $message,
                (string) $data['message'],
                $result->sources(),
                null,
                $result->contextual(),
            );
            $data['conversation'] = $conversation->publicTranscript();
            bh_json_success($data);
        } catch (NumaServiceException $exception) {
            $this->respondServiceError($exception);
        } catch (Throwable) {
            bh_numa_error('NUMA_INTERNAL_ERROR', 503);
        } finally {
            if ($chatRequest !== null) {
                $this->clearPublicChatRequest($chatRequest);
            }
        }
    }

    public function publicStatus(): void
    {
        if (!$this->beginJsonRequest('GET')) {
            return;
        }

        try {
            $visitorHash = $this->publicIdentity()->visitorHash();
        } catch (Throwable) {
            $this->respondWithStatus(
                $this->statusData(false, self::STATUS_REASON_CONFIGURATION_INCOMPLETE),
                [],
            );
            return;
        }

        $this->respondWithStatus(
            $this->effectivePublicStatus($visitorHash),
            NumaConversation::forVisitor($visitorHash)->publicTranscript(),
        );
    }

    public function publicNewConversation(): void
    {
        if (!$this->beginJsonRequest('POST')) {
            return;
        }

        try {
            $visitorHash = $this->publicIdentity()->visitorHash();
        } catch (Throwable) {
            bh_numa_error('NUMA_NOT_AVAILABLE', 503);
            return;
        }

        $this->resetConversation(
            static function () use ($visitorHash): void {
                NumaConversation::forVisitor($visitorHash)->clearPublic();
            },
            fn (): array => $this->effectivePublicStatus($visitorHash),
        );
    }

    protected function numaUso(): NumaUso
    {
        return new NumaUso();
    }

    protected function publicNumaUso(): NumaPublicUso
    {
        return new NumaPublicUso();
    }

    protected function publicIdentity(): NumaPublicIdentity
    {
        return $this->resolvedPublicIdentity ??= new NumaPublicIdentity();
    }

    protected function localScopeClassifier(): NumaLocalScopeClassifier
    {
        return new NumaLocalScopeClassifier();
    }

    protected function conversation(?int $authenticatedUserId = null): NumaConversation
    {
        return new NumaConversation($authenticatedUserId);
    }

    private function beginJsonRequest(string $method): bool
    {
        bh_json_no_store_private();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
            bh_json_error('METHOD_NOT_ALLOWED', bh_router_error_message('METHOD_NOT_ALLOWED'), 405);
            return false;
        }

        return true;
    }

    /** @param array<int, array<string, mixed>> $conversation */
    private function respondWithStatus(array $status, array $conversation): void
    {
        bh_json_success([
            'availability' => $this->availabilityState($status),
            'conversation' => $conversation,
        ]);
    }

    /**
     * @param callable():void $clearConversation
     * @param callable():array<string,mixed> $statusState
     */
    private function resetConversation(callable $clearConversation, callable $statusState): void
    {
        if (!csrf_validate()) {
            bh_numa_error('NUMA_INVALID_CSRF', 403);
            return;
        }

        $clearConversation();

        try {
            $status = $statusState();
        } catch (Throwable) {
            $status = $this->statusData(false, self::STATUS_REASON_TEMPORARILY_UNAVAILABLE);
        }

        $this->respondWithStatus($status, []);
    }

    /** @return array<string,mixed> */
    private function responseData(NumaServiceResult $result): array
    {
        $data = $result->toArray();
        $usage = $data['usage'] ?? null;
        unset($data['usage']);
        $data['availability'] = $this->availabilityFromUsage(is_array($usage) ? $usage : null);

        return $data;
    }

    private function respondServiceError(NumaServiceException $exception): void
    {
        $code = match ($exception->safeCode()) {
            'NUMA_DAILY_LIMIT_REACHED', 'NUMA_MONTHLY_LIMIT_REACHED' => 'NUMA_LIMIT_REACHED',
            'NUMA_GLOBAL_LIMIT_REACHED', 'NUMA_PUBLIC_GLOBAL_LIMIT_REACHED' => 'NUMA_NOT_AVAILABLE',
            default => $exception->safeCode(),
        };

        bh_numa_error($code, $exception->statusCode());
    }

    /** @param callable():NumaServiceResult $answer */
    private function answerWithReleasedSession(callable $answer): NumaServiceResult
    {
        $sessionReleased = $this->releaseSessionForProvider();

        try {
            return $answer();
        } finally {
            $this->reopenSessionAfterProvider($sessionReleased);
        }
    }

    private function sessionStillOwnedBy(int $authenticatedUserId, int $conversationVersion): bool
    {
        $currentUserId = $_SESSION['usuario_id'] ?? null;

        return (is_int($currentUserId) || (is_string($currentUserId) && ctype_digit($currentUserId)))
            && (int) $currentUserId === $authenticatedUserId
            && $this->conversation($authenticatedUserId)->version() === $conversationVersion;
    }

    private function publicSessionStillOwnedBy(string $visitorHash, int $conversationVersion): bool
    {
        try {
            $identity = $this->publicIdentity();
            $currentVisitorHash = $identity->createdDuringRequest()
                ? $visitorHash
                : (new NumaPublicIdentity())->visitorHash();

            return hash_equals($visitorHash, $currentVisitorHash)
                && NumaConversation::forVisitor($visitorHash)->publicVersion() === $conversationVersion;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{timestamp:int,usuario_id:int,conversation_version:int}|null
     */
    private function startChatRequest(int $authenticatedUserId, int $conversationVersion): ?array
    {
        $activeRequest = $this->activeChatRequest();
        if ($activeRequest !== null && !$this->chatRequestExpired($activeRequest)) {
            return null;
        }

        $request = [
            'timestamp' => time(),
            'usuario_id' => $authenticatedUserId,
            'conversation_version' => $conversationVersion,
        ];
        $_SESSION[self::CHAT_REQUEST_SESSION_KEY] = $request;

        return $request;
    }

    /**
     * @return array{timestamp:int,usuario_id:int,conversation_version:int}|null
     */
    private function activeChatRequest(): ?array
    {
        $request = $_SESSION[self::CHAT_REQUEST_SESSION_KEY] ?? null;
        if (!is_array($request)) {
            return null;
        }

        $timestamp = $request['timestamp'] ?? null;
        $userId = $request['usuario_id'] ?? null;
        $conversationVersion = $request['conversation_version'] ?? null;

        if ((!is_int($timestamp) && !(is_string($timestamp) && ctype_digit($timestamp)))
            || (!is_int($userId) && !(is_string($userId) && ctype_digit($userId)))
            || (!is_int($conversationVersion) && !(is_string($conversationVersion) && ctype_digit($conversationVersion)))
        ) {
            unset($_SESSION[self::CHAT_REQUEST_SESSION_KEY]);
            return null;
        }

        return [
            'timestamp' => (int) $timestamp,
            'usuario_id' => (int) $userId,
            'conversation_version' => (int) $conversationVersion,
        ];
    }

    /** @param array{timestamp:int,usuario_id:int,conversation_version:int} $request */
    private function chatRequestExpired(array $request): bool
    {
        $deadline = max(1, bh_env_int('NUMA_REQUEST_TIMEOUT_SECONDS', 25));

        return time() > $request['timestamp'] + $deadline + self::CHAT_REQUEST_EXPIRY_MARGIN_SECONDS;
    }

    /** @param array{timestamp:int,usuario_id:int,conversation_version:int} $request */
    private function clearChatRequest(array $request): void
    {
        if ($this->activeChatRequest() === $request) {
            unset($_SESSION[self::CHAT_REQUEST_SESSION_KEY]);
        }
    }

    /** @return array{timestamp:int,visitante_hash:string,conversation_version:int}|null */
    private function startPublicChatRequest(string $visitorHash, int $conversationVersion): ?array
    {
        $activeRequest = $this->activePublicChatRequest();
        if ($activeRequest !== null && !$this->publicChatRequestExpired($activeRequest)) {
            return null;
        }

        $request = [
            'timestamp' => time(),
            'visitante_hash' => $visitorHash,
            'conversation_version' => $conversationVersion,
        ];
        $_SESSION[self::PUBLIC_CHAT_REQUEST_SESSION_KEY] = $request;

        return $request;
    }

    /** @return array{timestamp:int,visitante_hash:string,conversation_version:int}|null */
    private function activePublicChatRequest(): ?array
    {
        $request = $_SESSION[self::PUBLIC_CHAT_REQUEST_SESSION_KEY] ?? null;
        if (!is_array($request)) {
            return null;
        }

        $timestamp = $request['timestamp'] ?? null;
        $visitorHash = $request['visitante_hash'] ?? null;
        $conversationVersion = $request['conversation_version'] ?? null;
        if ((!is_int($timestamp) && !(is_string($timestamp) && ctype_digit($timestamp)))
            || !is_string($visitorHash) || preg_match('/^[a-f0-9]{64}$/', $visitorHash) !== 1
            || (!is_int($conversationVersion) && !(is_string($conversationVersion) && ctype_digit($conversationVersion)))) {
            unset($_SESSION[self::PUBLIC_CHAT_REQUEST_SESSION_KEY]);
            return null;
        }

        return [
            'timestamp' => (int) $timestamp,
            'visitante_hash' => $visitorHash,
            'conversation_version' => (int) $conversationVersion,
        ];
    }

    /** @param array{timestamp:int,visitante_hash:string,conversation_version:int} $request */
    private function publicChatRequestExpired(array $request): bool
    {
        $deadline = max(1, bh_env_int('NUMA_REQUEST_TIMEOUT_SECONDS', 25));

        return time() > $request['timestamp'] + $deadline + self::CHAT_REQUEST_EXPIRY_MARGIN_SECONDS;
    }

    /** @param array{timestamp:int,visitante_hash:string,conversation_version:int} $request */
    private function clearPublicChatRequest(array $request): void
    {
        if ($this->activePublicChatRequest() === $request) {
            unset($_SESSION[self::PUBLIC_CHAT_REQUEST_SESSION_KEY]);
        }
    }

    protected function releaseSessionForProvider(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        session_write_close();

        return session_status() !== PHP_SESSION_ACTIVE;
    }

    protected function reopenSessionAfterProvider(bool $sessionReleased): void
    {
        if ($sessionReleased && session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
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

    protected function periodResolver(): NumaPeriodResolver
    {
        return new NumaPeriodResolver();
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
            fn (?NumaProviderConsumptionInterface $consumption = null): NumaProviderInterface => $this->provider($consumption),
            fn (NumaClassification $classification, string $message, ?NumaProviderConsumptionInterface $consumption = null): array => $this->knowledgeResults($classification, $message, $consumption),
            fn (): NumaFinancialToolRegistryInterface => $this->financialTools(),
            fn (): NumaGlobalAvailabilityInterface => $this->globalAvailability(),
            $this->periodResolver(),
        );
    }

    protected function publicNumaService(): NumaService
    {
        return new NumaService(
            $this->publicNumaUso(),
            $this->localScopeClassifier(),
            fn (?NumaProviderConsumptionInterface $consumption = null): NumaProviderInterface => $this->publicProvider($consumption),
            fn (NumaClassification $classification, string $message, ?NumaProviderConsumptionInterface $consumption = null): array => $this->publicKnowledgeResults($classification, $message, $consumption),
            fn (): NumaFinancialToolRegistryInterface => $this->financialTools(),
            fn (): NumaGlobalAvailabilityInterface => $this->globalAvailability(),
            $this->periodResolver(),
        );
    }

    /**
     * @param array<int, array{role:string,message:string,period?:array<string,string>}> $context
     */
    protected function answerPublic(string $visitorHash, string $message, array $context): NumaServiceResult
    {
        return $this->publicNumaService()->answerPublic($visitorHash, $message, $context);
    }

    protected function publicProvider(?NumaProviderConsumptionInterface $consumption = null): NumaProviderInterface
    {
        if ($consumption === null) {
            return NumaProviderFactory::fromEnvironment();
        }

        return NumaProviderFactory::fromEnvironment(
            consumption: new NumaProviderConsumptionChain($consumption, NumaConsumoGlobal::forPublicLlm())
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

    /** @return array<int, NumaKnowledgeSearchResult> */
    protected function publicKnowledgeResults(
        NumaClassification $classification,
        string $message,
        ?NumaProviderConsumptionInterface $consumption = null,
    ): array {
        $knowledgeQuery = $classification->knowledgeQuery() ?? $message;

        return $this->publicKnowledgeSearcher($consumption)->search($knowledgeQuery);
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
            (float) bh_env_value('NUMA_RAG_MIN_SIMILARITY', '0.67'),
            $embeddingProvider->signature()
        );
    }

    protected function publicKnowledgeSearcher(?NumaProviderConsumptionInterface $consumption = null): NumaKnowledgeSearcher
    {
        $embeddingProvider = NumaEmbeddingProviderFactory::fromEnvironment();

        if ($consumption !== null) {
            $embeddingProvider = new NumaMeteredEmbeddingProvider(
                $embeddingProvider,
                new NumaProviderConsumptionChain($consumption, NumaConsumoGlobal::forPublicEmbedding())
            );
        }

        return new NumaKnowledgeSearcher(
            Database::getConnection(),
            $embeddingProvider,
            bh_env_int('NUMA_EMBEDDING_DIMENSIONS', 768),
            bh_env_int('NUMA_MAX_RAG_RESULTS', 3),
            (float) bh_env_value('NUMA_RAG_MIN_SIMILARITY', '0.67'),
            $embeddingProvider->signature()
        );
    }

    /**
     * @return array{
     *   available:bool,
     *   reason:'disabled'|'configuration_incomplete'|'temporarily_unavailable'|'user_limit'|'global_limit'|null,
     *   usage:array{daily_used:int,daily_limit:int,daily_remaining:int,monthly_used:int,monthly_limit:int,monthly_remaining:int}|null
     * }
     */
    protected function effectiveStatus(int $authenticatedUserId): array
    {
        // A disabled installation must remain inspectable without Gemini keys or Numa tables.
        if (!bh_env_bool('NUMA_ENABLED', false)) {
            return $this->statusData(false, self::STATUS_REASON_DISABLED);
        }

        $activeRequest = $this->activeChatRequest();
        if ($activeRequest !== null && !$this->chatRequestExpired($activeRequest)) {
            return $this->statusData(false, self::STATUS_REASON_TEMPORARILY_UNAVAILABLE);
        }

        try {
            $signature = $this->statusEmbeddingSignature();
        } catch (Throwable) {
            return $this->statusData(false, self::STATUS_REASON_CONFIGURATION_INCOMPLETE);
        }

        $usage = null;

        try {
            $connection = $this->statusConnection();
            $this->assertStatusTables($connection);

            if (!$this->hasCompatibleKnowledgeIndex($connection, $signature)) {
                return $this->statusData(false, self::STATUS_REASON_TEMPORARILY_UNAVAILABLE);
            }

            $usage = $this->numaUso()->estado($authenticatedUserId);

            if ($usage['daily_remaining'] <= 0 || $usage['monthly_remaining'] <= 0) {
                return $this->statusData(false, self::STATUS_REASON_USER_LIMIT, $usage);
            }

            $this->globalAvailability()->assertAvailable();
        } catch (NumaGlobalLimiteAlcanzado) {
            return $this->statusData(false, self::STATUS_REASON_GLOBAL_LIMIT, $usage);
        } catch (Throwable) {
            return $this->statusData(false, self::STATUS_REASON_TEMPORARILY_UNAVAILABLE, $usage);
        }

        return $this->statusData(true, null, $usage);
    }

    /**
     * @return array{available:bool,reason:string|null,usage:array{daily_used:int,daily_limit:int,daily_remaining:int,monthly_used:int,monthly_limit:int,monthly_remaining:int}|null}
     */
    protected function effectivePublicStatus(string $visitorHash): array
    {
        if (!bh_env_bool('NUMA_ENABLED', false) || !bh_env_bool('NUMA_PUBLIC_ENABLED', false)) {
            return $this->statusData(false, self::STATUS_REASON_DISABLED);
        }

        $activeRequest = $this->activePublicChatRequest();
        if ($activeRequest !== null && !$this->publicChatRequestExpired($activeRequest)) {
            return $this->statusData(false, self::STATUS_REASON_TEMPORARILY_UNAVAILABLE);
        }

        $usage = null;

        try {
            $signature = $this->statusEmbeddingSignature();
            $connection = $this->statusConnection();
            $this->assertPublicStatusTables($connection);
            if (!$this->hasCompatibleKnowledgeIndex($connection, $signature)) {
                return $this->statusData(false, self::STATUS_REASON_TEMPORARILY_UNAVAILABLE);
            }

            $usage = $this->publicNumaUso()->estado($visitorHash);
            if ($usage['daily_remaining'] <= 0 || $usage['monthly_remaining'] <= 0) {
                return $this->statusData(false, self::STATUS_REASON_VISITOR_LIMIT, $usage);
            }

            $global = new NumaConsumoGlobal();
            $global->estadoGlobal();
            if ($global->llamadasPublicasDia() >= max(0, bh_env_int('NUMA_PUBLIC_GLOBAL_DAILY_CALL_LIMIT', 40))
                || $global->llamadasPublicasMes() >= max(0, bh_env_int('NUMA_PUBLIC_GLOBAL_MONTHLY_CALL_LIMIT', 400))) {
                return $this->statusData(false, self::STATUS_REASON_PUBLIC_GLOBAL_LIMIT, $usage);
            }

            $this->globalAvailability()->assertAvailable();
        } catch (NumaGlobalLimiteAlcanzado) {
            return $this->statusData(false, self::STATUS_REASON_GLOBAL_LIMIT, $usage ?? null);
        } catch (Throwable) {
            return $this->statusData(false, self::STATUS_REASON_TEMPORARILY_UNAVAILABLE, $usage ?? null);
        }

        return $this->statusData(true, null, $usage);
    }

    protected function statusConnection(): PDO
    {
        return Database::getConnection();
    }

    protected function statusEmbeddingSignature(): NumaEmbeddingSignature
    {
        // Constructing the adapters validates local configuration without sending a request.
        $this->provider();

        return NumaEmbeddingProviderFactory::fromEnvironment()->signature();
    }

    /**
     * @param array{daily_used:int,daily_limit:int,daily_remaining:int,monthly_used:int,monthly_limit:int,monthly_remaining:int}|null $usage
     * @return array{
     *   available:bool,
     *   reason:'disabled'|'configuration_incomplete'|'temporarily_unavailable'|'user_limit'|'global_limit'|null,
     *   usage:array{daily_used:int,daily_limit:int,daily_remaining:int,monthly_used:int,monthly_limit:int,monthly_remaining:int}|null
     * }
     */
    private function statusData(bool $available, ?string $reason, ?array $usage = null): array
    {
        return [
            'available' => $available,
            'reason' => $reason,
            'usage' => $usage,
        ];
    }

    /** @param array{available:bool,reason:string|null,usage:array<string,int>|null} $status */
    private function availabilityState(array $status): string
    {
        if (($status['available'] ?? false) !== true) {
            return match ($status['reason'] ?? null) {
                self::STATUS_REASON_CONFIGURATION_INCOMPLETE => self::AVAILABILITY_CONFIGURATION_REQUIRED,
                self::STATUS_REASON_USER_LIMIT, self::STATUS_REASON_VISITOR_LIMIT => self::AVAILABILITY_LIMIT_REACHED,
                default => self::AVAILABILITY_UNAVAILABLE,
            };
        }

        return $this->availabilityFromUsage(is_array($status['usage'] ?? null) ? $status['usage'] : null);
    }

    /** @param array<string,int>|null $usage */
    private function availabilityFromUsage(?array $usage): string
    {
        if ($usage === null) {
            return self::AVAILABILITY_AVAILABLE;
        }

        $dailyRemaining = $usage['daily_remaining'] ?? null;
        $monthlyRemaining = $usage['monthly_remaining'] ?? null;

        if ((is_int($dailyRemaining) && $dailyRemaining <= 0)
            || (is_int($monthlyRemaining) && $monthlyRemaining <= 0)) {
            return self::AVAILABILITY_LIMIT_REACHED;
        }

        if ((is_int($dailyRemaining) && $dailyRemaining === 1)
            || (is_int($monthlyRemaining) && $monthlyRemaining === 1)) {
            return self::AVAILABILITY_NEAR_LIMIT;
        }

        return self::AVAILABILITY_AVAILABLE;
    }

    private function assertStatusTables(PDO $connection): void
    {
        foreach (self::REQUIRED_STATUS_TABLES as $table) {
            $statement = $connection->query('SELECT 1 FROM ' . $table . ' LIMIT 1');

            if ($statement === false) {
                throw new RuntimeException('No se pudo comprobar una tabla de Numa.');
            }
        }
    }

    private function assertPublicStatusTables(PDO $connection): void
    {
        foreach (self::REQUIRED_PUBLIC_STATUS_TABLES as $table) {
            $statement = $connection->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
            if ($statement === false) {
                throw new RuntimeException('No se pudo comprobar una tabla de Numa.');
            }
        }
    }

    private function hasCompatibleKnowledgeIndex(PDO $connection, NumaEmbeddingSignature $signature): bool
    {
        $statement = $connection->prepare(
            'SELECT 1 FROM numa_conocimiento
             WHERE dimensiones = :dimensiones AND firma_embedding = :firma_embedding
             LIMIT 1'
        );
        $statement->execute([
            ':dimensiones' => $signature->dimensions(),
            ':firma_embedding' => $signature->value(),
        ]);

        return $statement->fetchColumn() !== false;
    }

    protected function rawBody(): string
    {
        $stream = fopen('php://input', 'rb');

        if ($stream === false) {
            return '';
        }

        $body = stream_get_contents($stream, bh_numa_max_request_body_bytes() + 1);
        fclose($stream);

        return is_string($body) ? $body : '';
    }

    private function requestPayload(): ?array
    {
        if ($this->declaredBodySizeExceedsLimit()) {
            $this->requestBodyTooLarge = true;
            return null;
        }

        $rawBody = $this->rawBody();

        if (strlen($rawBody) > bh_numa_max_request_body_bytes()) {
            $this->requestBodyTooLarge = true;
            return null;
        }

        if (trim($rawBody) === '') {
            return null;
        }

        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

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

        $mediaType = trim(explode(';', $contentType, 2)[0]);

        return $mediaType === 'application/json';
    }

    private function declaredBodySizeExceedsLimit(): bool
    {
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? null;

        if ($contentLength === null || $contentLength === '') {
            return false;
        }

        if (!is_string($contentLength) && !is_int($contentLength)) {
            return true;
        }

        $contentLength = (string) $contentLength;

        if (!ctype_digit($contentLength)) {
            return true;
        }

        return (int) $contentLength > bh_numa_max_request_body_bytes();
    }

    protected function isChatRateLimited(int $authenticatedUserId): bool
    {
        $key = IntentoAcceso::claveHash('numa:' . $authenticatedUserId);
        $windowSeconds = max(1, bh_env_int('NUMA_CHAT_BURST_WINDOW_SECONDS', 60));
        $blockSeconds = max(1, bh_env_int('NUMA_CHAT_BURST_BLOCK_SECONDS', 60));
        $maxRequests = max(1, bh_env_int('NUMA_CHAT_BURST_MAX_REQUESTS', 5));

        if (IntentoAcceso::estaBloqueado(self::CHAT_RATE_LIMIT_ACTION, $key)) {
            return true;
        }

        // registrarFallo bloquea el intento que alcanza el umbral; se conserva la ráfaga configurada.
        return IntentoAcceso::registrarFallo(
            self::CHAT_RATE_LIMIT_ACTION,
            $key,
            $maxRequests + 1,
            $windowSeconds,
            $blockSeconds,
        );
    }

    protected function isPublicChatRateLimited(): bool
    {
        try {
            $address = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            $key = $this->publicIdentity()->hash($address);
        } catch (Throwable) {
            return true;
        }

        $windowSeconds = max(1, bh_env_int('NUMA_CHAT_BURST_WINDOW_SECONDS', 60));
        $blockSeconds = max(1, bh_env_int('NUMA_CHAT_BURST_BLOCK_SECONDS', 60));
        $maxRequests = max(1, bh_env_int('NUMA_CHAT_BURST_MAX_REQUESTS', 5));

        if (IntentoAcceso::estaBloqueado(self::PUBLIC_CHAT_RATE_LIMIT_ACTION, $key)) {
            return true;
        }

        return IntentoAcceso::registrarFallo(
            self::PUBLIC_CHAT_RATE_LIMIT_ACTION,
            $key,
            $maxRequests + 1,
            $windowSeconds,
            $blockSeconds,
        );
    }

    private function isPublicChatEnabled(): bool
    {
        return bh_env_bool('NUMA_ENABLED', false) && bh_env_bool('NUMA_PUBLIC_ENABLED', false);
    }

    private function validatedMessage(): ?string
    {
        $this->requestBodyTooLarge = false;

        if (!$this->hasJsonContentType()) {
            bh_numa_error('NUMA_INVALID_MESSAGE', 400);
            return null;
        }

        if (!csrf_validate()) {
            bh_numa_error('NUMA_INVALID_CSRF', 403);
            return null;
        }

        $payload = $this->requestPayload();
        if ($payload === null) {
            bh_numa_error($this->requestBodyTooLarge ? 'NUMA_REQUEST_TOO_LARGE' : 'NUMA_INVALID_MESSAGE', $this->requestBodyTooLarge ? 413 : 400);
            return null;
        }

        foreach (self::DISALLOWED_CLIENT_KEYS as $key) {
            if (array_key_exists($key, $payload)) {
                bh_numa_error('NUMA_INVALID_MESSAGE', 400);
                return null;
            }
        }

        foreach (array_keys($payload) as $key) {
            if (!is_string($key) || !in_array($key, self::ALLOWED_CLIENT_KEYS, true)) {
                bh_numa_error('NUMA_INVALID_MESSAGE', 400);
                return null;
            }
        }

        $message = $payload['message'] ?? null;
        if (!is_string($message) || trim($message) === '') {
            bh_numa_error('NUMA_INVALID_MESSAGE', 400);
            return null;
        }

        if ($this->textLength($message) > bh_numa_max_message_length()) {
            bh_numa_error('NUMA_MESSAGE_TOO_LONG', 422);
            return null;
        }

        return $message;
    }

    private function textLength(string $text): int
    {
        if (function_exists('mb_convert_encoding')) {
            return intdiv(strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
        }

        $characters = preg_match_all('/./us', $text);
        $supplementaryCharacters = preg_match_all('/[\x{10000}-\x{10FFFF}]/u', $text);

        return $characters !== false && $supplementaryCharacters !== false
            ? $characters + $supplementaryCharacters
            : strlen($text);
    }
}
