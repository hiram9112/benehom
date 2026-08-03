<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/NumaUso.php';
require_once __DIR__ . '/../models/NumaConsumoGlobal.php';
require_once __DIR__ . '/NumaClassification.php';
require_once __DIR__ . '/NumaFinancialTools.php';
require_once __DIR__ . '/NumaKnowledge.php';
require_once __DIR__ . '/NumaProvider.php';

interface NumaGlobalAvailabilityInterface
{
    public function assertAvailable(): void;
}

final class NumaGlobalAvailability implements NumaGlobalAvailabilityInterface
{
    public function __construct(private readonly NumaConsumoGlobal $consumoGlobal = new NumaConsumoGlobal())
    {
    }

    public function assertAvailable(): void
    {
        $status = $this->consumoGlobal->estadoGlobal();

        if ($status['daily_calls'] >= $status['daily_calls_limit']
            || $status['monthly_calls'] >= $status['monthly_calls_limit']
            || $status['daily_tokens'] >= $status['daily_tokens_limit']
            || $status['monthly_tokens'] >= $status['monthly_tokens_limit']
        ) {
            throw new NumaGlobalLimiteAlcanzado('NUMA_GLOBAL_LIMIT_REACHED');
        }
    }
}

final class NumaServiceException extends RuntimeException
{
    public function __construct(
        private readonly string $safeCode,
        private readonly int $statusCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct($safeCode, 0, $previous);
    }

    public function safeCode(): string
    {
        return $this->safeCode;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}

final class NumaServiceResult
{
    /**
     * @param array<int, array{title:string,section:string,url:string}> $sources
     * @param array<string, mixed>|null $period
     * @param array{daily_used:int,daily_limit:int,daily_remaining:int,monthly_used:int,monthly_limit:int,monthly_remaining:int} $usage
     */
    public function __construct(
        private readonly string $message,
        private readonly array $sources,
        private readonly ?array $period,
        private readonly array $usage,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'sources' => $this->sources,
            'period' => $this->period,
            'usage' => $this->usage,
        ];
    }
}

final class NumaReservationGuard
{
    private bool $closed = false;

    public function __construct(
        private readonly NumaUso $usage,
        private readonly string $reservationId,
    ) {
    }

    public function confirm(): void
    {
        if ($this->closed) {
            return;
        }

        try {
            $confirmed = $this->usage->confirmar($this->reservationId);
        } catch (Throwable $exception) {
            throw new NumaServiceException('NUMA_USAGE_ERROR', 503, $exception);
        }

        if (!$confirmed) {
            throw new NumaServiceException('NUMA_USAGE_ERROR', 503);
        }

        $this->closed = true;
    }

    public function revert(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        try {
            $this->usage->revertir($this->reservationId);
        } catch (Throwable) {
            // El error principal no debe quedar oculto por un fallo al revertir.
        }
    }
}

final class NumaService
{
    private const NO_KNOWLEDGE_MESSAGE = 'No encuentro información suficiente sobre esa función dentro de BeneHom.';

    public function __construct(
        private readonly NumaUso $usage,
        private readonly NumaLocalScopeClassifier $localScopeClassifier,
        private readonly NumaProviderScopeClassifier $providerScopeClassifier,
        private readonly NumaProviderInterface $provider,
        callable $knowledgeSearch,
        private readonly NumaFinancialToolRegistryInterface $financialTools,
        private readonly NumaGlobalAvailabilityInterface $globalAvailability,
    ) {
        $this->knowledgeSearch = Closure::fromCallable($knowledgeSearch);
    }

    /** @var Closure(NumaClassification, string): array<int, NumaKnowledgeSearchResult> */
    private readonly Closure $knowledgeSearch;

    public function answer(int $authenticatedUserId, string $message): NumaServiceResult
    {
        if (!bh_env_bool('NUMA_ENABLED', false)) {
            throw new NumaServiceException('NUMA_NOT_AVAILABLE', 503);
        }

        $localRejection = $this->localScopeClassifier->classify($message);
        if ($localRejection !== null) {
            return $this->result($authenticatedUserId, $localRejection->message());
        }

        try {
            $this->globalAvailability->assertAvailable();
        } catch (NumaGlobalLimiteAlcanzado $exception) {
            throw new NumaServiceException('NUMA_GLOBAL_LIMIT_REACHED', 503, $exception);
        } catch (Throwable $exception) {
            throw new NumaServiceException('NUMA_PROVIDER_UNAVAILABLE', 503, $exception);
        }

        try {
            $reservation = new NumaReservationGuard($this->usage, $this->usage->reservar($authenticatedUserId));
        } catch (NumaUsoLimiteAlcanzado $exception) {
            throw new NumaServiceException($exception->limitCode(), 429, $exception);
        } catch (Throwable $exception) {
            throw new NumaServiceException('NUMA_USAGE_ERROR', 503, $exception);
        }

        try {
            $classification = $this->providerScopeClassifier->classify($message);

            if (!$classification->allowed()) {
                $fixedMessage = NumaFixedScopeResponse::forIntent($classification->intent(), $classification->reason());
                $reservation->confirm();

                return $this->result($authenticatedUserId, $fixedMessage);
            }

            $knowledgeResults = $this->knowledgeResults($classification, $message);
            if ($this->needsKnowledge($classification) && $knowledgeResults === []) {
                $reservation->confirm();

                return $this->result($authenticatedUserId, self::NO_KNOWLEDGE_MESSAGE);
            }

            [$finalMessage, $toolResults] = $this->generateFinalResponse(
                $authenticatedUserId,
                $message,
                $classification,
                $knowledgeResults
            );

            $reservation->confirm();

            return $this->result(
                $authenticatedUserId,
                $finalMessage,
                $this->sources($knowledgeResults),
                $this->periodFromToolResults($toolResults)
            );
        } catch (NumaServiceException $exception) {
            $reservation->revert();
            throw $exception;
        } catch (NumaGlobalLimiteAlcanzado $exception) {
            $reservation->revert();
            throw new NumaServiceException('NUMA_GLOBAL_LIMIT_REACHED', 503, $exception);
        } catch (NumaProviderException $exception) {
            $reservation->revert();
            throw new NumaServiceException($exception->providerError()->safeCode(), 503, $exception);
        } catch (NumaFinancialToolLimitExceeded|InvalidArgumentException $exception) {
            $reservation->revert();
            throw new NumaServiceException('NUMA_PROVIDER_INVALID_RESPONSE', 503, $exception);
        } catch (Throwable $exception) {
            $reservation->revert();
            throw new NumaServiceException('NUMA_PROVIDER_INVALID_RESPONSE', 503, $exception);
        }
    }

    /**
     * @return array<int, NumaKnowledgeSearchResult>
     */
    private function knowledgeResults(NumaClassification $classification, string $message): array
    {
        if (!$this->needsKnowledge($classification)) {
            return [];
        }

        return ($this->knowledgeSearch)($classification, $message);
    }

    /**
     * @param array<int, NumaKnowledgeSearchResult> $knowledgeResults
     * @return array{0:string,1:array<int,array<string,mixed>>}
     */
    private function generateFinalResponse(
        int $authenticatedUserId,
        string $message,
        NumaClassification $classification,
        array $knowledgeResults,
    ): array {
        $availableTools = $this->availableToolNames($classification);
        $toolResults = [];
        $maxProviderCalls = max(1, bh_env_int('NUMA_MAX_PROVIDER_CALLS', 3));
        $remainingFinalCalls = max(1, $maxProviderCalls - 1);

        for ($call = 0; $call < $remainingFinalCalls; $call++) {
            $response = $this->provider->respond(new NumaRequest(
                $message,
                '',
                $this->finalContext($classification, $knowledgeResults, $availableTools, $toolResults),
                $availableTools
            ));

            $toolRequest = $response->toolRequest();
            if ($toolRequest === null) {
                $finalMessage = trim($response->message());

                if ($finalMessage === '') {
                    throw new NumaProviderException(new NumaProviderError(
                        NumaProviderError::INVALID_RESPONSE,
                        'NUMA_PROVIDER_INVALID_RESPONSE'
                    ));
                }

                return [$finalMessage, $toolResults];
            }

            if (!in_array($toolRequest->name(), $availableTools, true)) {
                throw new InvalidArgumentException('Tool de Numa no permitida para esta consulta.');
            }

            $toolResults[] = $this->financialTools->execute(
                $toolRequest->name(),
                $authenticatedUserId,
                $toolRequest->arguments()
            );
        }

        throw new NumaProviderException(new NumaProviderError(
            NumaProviderError::INVALID_RESPONSE,
            'NUMA_PROVIDER_INVALID_RESPONSE'
        ));
    }

    private function needsKnowledge(NumaClassification $classification): bool
    {
        return in_array($classification->intent(), [
            NumaClassificationIntent::PRODUCTO,
            NumaClassificationIntent::EDUCACION_FINANCIERA,
            NumaClassificationIntent::CONSULTA_COMBINADA,
        ], true);
    }

    private function needsTools(NumaClassification $classification): bool
    {
        return in_array($classification->intent(), [
            NumaClassificationIntent::DATOS_USUARIO,
            NumaClassificationIntent::CONSULTA_COMBINADA,
        ], true);
    }

    /**
     * @return array<int, string>
     */
    private function availableToolNames(NumaClassification $classification): array
    {
        return $this->needsTools($classification) ? $this->financialTools->names() : [];
    }

    /**
     * @param array<int, NumaKnowledgeSearchResult> $knowledgeResults
     * @param array<int, string> $availableTools
     * @param array<int, array<string, mixed>> $toolResults
     * @return array<int, array<string, mixed>>
     */
    private function finalContext(
        NumaClassification $classification,
        array $knowledgeResults,
        array $availableTools,
        array $toolResults,
    ): array {
        $context = [[
            'type' => 'numa_final_response',
            'classification' => $classification->toStructuredData(),
            'rules' => [
                'Responde solo al mensaje actual y no asumas historial previo.',
                'Usa solo el contexto de BeneHom y los resultados de tools entregados por el backend.',
                'No inventes datos si falta informacion.',
                'Devuelve una respuesta breve en español para el usuario final.',
            ],
        ]];

        if ($knowledgeResults !== []) {
            $context[] = [
                'type' => 'knowledge_fragments',
                'items' => array_map(static fn (NumaKnowledgeSearchResult $result): array => [
                    'title' => $result->title(),
                    'section' => $result->section(),
                    'url' => $result->route(),
                    'content' => $result->content(),
                ], $knowledgeResults),
            ];
        }

        if ($availableTools !== []) {
            $context[] = [
                'type' => 'available_financial_tools',
                'items' => $this->toolDefinitionsForContext($availableTools),
            ];
        }

        if ($toolResults !== []) {
            $context[] = [
                'type' => 'financial_tool_results',
                'items' => $toolResults,
            ];
        }

        return $context;
    }

    /**
     * @param array<int, string> $availableTools
     * @return array<int, array<string, mixed>>
     */
    private function toolDefinitionsForContext(array $availableTools): array
    {
        $definitions = [];

        foreach ($availableTools as $name) {
            $definition = $this->financialTools->get($name);
            $definitions[] = [
                'name' => $definition->name(),
                'description' => $definition->description(),
                'schema' => $definition->parameterSchema(),
                'required' => $definition->requiredParameters(),
                'allowed_values' => $definition->allowedValues(),
                'result_limit' => $definition->resultLimit(),
            ];
        }

        return $definitions;
    }

    /**
     * @param array<int, NumaKnowledgeSearchResult> $knowledgeResults
     * @return array<int, array{title:string,section:string,url:string}>
     */
    private function sources(array $knowledgeResults): array
    {
        return array_map(static fn (NumaKnowledgeSearchResult $result): array => [
            'title' => $result->title(),
            'section' => $result->section(),
            'url' => $result->route(),
        ], $knowledgeResults);
    }

    /**
     * @param array<int, array<string, mixed>> $toolResults
     * @return array<string, mixed>|null
     */
    private function periodFromToolResults(array $toolResults): ?array
    {
        foreach ($toolResults as $result) {
            $period = $result['periodo'] ?? null;

            if (is_array($period) && isset($period['inicio'], $period['fin'])) {
                return [
                    'start' => (string) $period['inicio'],
                    'end' => (string) $period['fin'],
                ];
            }
        }

        return null;
    }

    /**
     * @param array<int, array{title:string,section:string,url:string}> $sources
     * @param array<string, mixed>|null $period
     */
    private function result(int $authenticatedUserId, string $message, array $sources = [], ?array $period = null): NumaServiceResult
    {
        return new NumaServiceResult($message, $sources, $period, $this->usage->estado($authenticatedUserId));
    }
}
