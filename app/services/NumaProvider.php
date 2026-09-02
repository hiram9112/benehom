<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/utils.php';
require_once __DIR__ . '/NumaConfiguration.php';

interface NumaProviderInterface
{
    public function respond(NumaRequest $request): NumaResponse;
}

interface NumaProviderConsumptionInterface
{
    public function iniciarLlamada(): void;

    public function registrarTokens(NumaTokenUsage $usage): void;
}

interface NumaProviderDeferredConsumptionInterface extends NumaProviderConsumptionInterface
{
    public function prepararLlamada(): mixed;

    public function confirmarLlamada(mixed $reservation): void;

    public function cancelarLlamada(mixed $reservation): void;

    public function conexionTransaccional(): PDO;
}

/** Control compartido por todas las llamadas externas de una interacción. */
interface NumaInteractionBudgetInterface
{
    public function timeoutForCall(int $configuredTimeoutSeconds): int;

    public function allowTransientRetry(): bool;
}

final class NumaProviderConsumptionChain implements NumaProviderConsumptionInterface, NumaInteractionBudgetInterface
{
    /** @var list<NumaProviderConsumptionInterface> */
    private readonly array $consumers;

    public function __construct(NumaProviderConsumptionInterface ...$consumers)
    {
        if ($consumers === []) {
            throw new InvalidArgumentException('La cadena de consumo de Numa requiere al menos un consumidor.');
        }

        foreach ($consumers as $consumer) {
            if (!$consumer instanceof NumaProviderDeferredConsumptionInterface) {
                throw new InvalidArgumentException('La cadena de consumo de Numa requiere consumidores diferidos.');
            }
        }

        $this->consumers = $consumers;
    }

    public function iniciarLlamada(): void
    {
        /** @var list<array{consumer:NumaProviderDeferredConsumptionInterface,reservation:mixed}> $prepared */
        $prepared = [];
        $connection = null;
        $transactionStarted = false;

        try {
            foreach ($this->consumers as $consumer) {
                /** @var NumaProviderDeferredConsumptionInterface $consumer */
                $consumerConnection = $consumer->conexionTransaccional();

                if ($connection !== null && $connection !== $consumerConnection) {
                    throw new RuntimeException('Los consumos de Numa deben compartir una conexion transaccional.');
                }

                if ($consumerConnection->inTransaction()) {
                    throw new RuntimeException('No se pudo iniciar la transaccion de consumo de Numa.');
                }

                $connection = $consumerConnection;
                $prepared[] = [
                    'consumer' => $consumer,
                    'reservation' => $consumer->prepararLlamada(),
                ];
            }

            if ($connection === null) {
                throw new RuntimeException('No se pudo iniciar la transaccion de consumo de Numa.');
            }

            $connection->beginTransaction();
            $transactionStarted = true;

            foreach (array_reverse($prepared) as $entry) {
                $entry['consumer']->confirmarLlamada($entry['reservation']);
            }

            $connection->commit();
            $transactionStarted = false;
        } catch (Throwable $exception) {
            if ($transactionStarted && $connection !== null && $connection->inTransaction()) {
                $connection->rollBack();
            }

            foreach (array_reverse($prepared) as $entry) {
                try {
                    $entry['consumer']->cancelarLlamada($entry['reservation']);
                } catch (Throwable) {
                    // El fallo principal decide la respuesta; cancelar es solo limpieza defensiva.
                }
            }

            throw $exception;
        }
    }

    public function registrarTokens(NumaTokenUsage $usage): void
    {
        foreach ($this->consumers as $consumer) {
            $consumer->registrarTokens($usage);
        }
    }

    public function timeoutForCall(int $configuredTimeoutSeconds): int
    {
        return $this->interactionBudget()?->timeoutForCall($configuredTimeoutSeconds)
            ?? max(1, $configuredTimeoutSeconds);
    }

    public function allowTransientRetry(): bool
    {
        return $this->interactionBudget()?->allowTransientRetry() ?? false;
    }

    private function interactionBudget(): ?NumaInteractionBudgetInterface
    {
        foreach ($this->consumers as $consumer) {
            if ($consumer instanceof NumaInteractionBudgetInterface) {
                return $consumer;
            }
        }

        return null;
    }
}

final class NumaRequest
{
    public const FUNCTION_CALLING_ANY = 'ANY';
    public const FUNCTION_CALLING_AUTO = 'AUTO';
    public const FUNCTION_CALLING_NONE = 'NONE';

    /**
     * @param array<int, array<string, mixed>> $context
     * @param array<int, string> $availableTools
     * @param array<int, array{role:string,message:string,period?:array<string,string>}> $history
     * @param array<string, mixed>|null $responseSchema
     */
    public function __construct(
        private readonly string $message,
        private readonly string $systemInstruction = '',
        private readonly array $context = [],
        private readonly array $availableTools = [],
        private readonly array $history = [],
        private readonly ?array $responseSchema = null,
        private readonly ?string $functionCallingMode = null,
        private readonly ?int $maxOutputTokens = null,
    ) {
        foreach ($history as $entry) {
            if (!is_array($entry)
                || !in_array($entry['role'] ?? null, ['user', 'assistant'], true)
                || !is_string($entry['message'] ?? null)
                || trim($entry['message']) === ''
            ) {
                throw new InvalidArgumentException('Historial conversacional de Numa invalido.');
            }
        }

        if ($functionCallingMode !== null
            && (!in_array($functionCallingMode, [
                self::FUNCTION_CALLING_ANY,
                self::FUNCTION_CALLING_AUTO,
                self::FUNCTION_CALLING_NONE,
            ], true) || $availableTools === [])
        ) {
            throw new InvalidArgumentException('Modo de function calling de Numa invalido.');
        }

        if ($maxOutputTokens !== null && $maxOutputTokens < 1) {
            throw new InvalidArgumentException('Presupuesto de salida de Numa invalido.');
        }
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

    /**
     * @return array<int, array{role:string,message:string,period?:array<string,string>}>
     */
    public function history(): array
    {
        return $this->history;
    }

    /** @return array<string, mixed>|null */
    public function responseSchema(): ?array
    {
        return $this->responseSchema;
    }

    public function functionCallingMode(): ?string
    {
        return $this->functionCallingMode;
    }

    public function maxOutputTokens(): ?int
    {
        return $this->maxOutputTokens;
    }
}

final class NumaInputLimitExceeded extends RuntimeException
{
}

final class NumaInputBudget
{
    private const APPROX_CHARS_PER_TOKEN = 4;
    private const STRUCTURAL_OVERHEAD_CHARS = 300;

    public static function assertFits(NumaRequest $request): void
    {
        $maxTokens = max(1, bh_env_int('NUMA_MAX_INPUT_TOKENS', 16000));
        $maxChars = $maxTokens * self::APPROX_CHARS_PER_TOKEN;
        $estimatedChars = self::STRUCTURAL_OVERHEAD_CHARS
            + self::length($request->systemInstruction())
            + self::length($request->message())
            + self::jsonLength($request->context())
            + self::jsonLength($request->availableTools())
            + self::jsonLength($request->history());

        if ($estimatedChars > $maxChars) {
            throw new NumaInputLimitExceeded('NUMA_CONVERSATION_TOO_LONG');
        }
    }

    private static function jsonLength(array $value): int
    {
        return self::length(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}

final class NumaResponse
{
    /**
     * @param array<string, mixed>|null $structuredData
     * @param list<NumaToolRequest> $toolRequests
     */
    public function __construct(
        private readonly string $message,
        private readonly ?array $structuredData = null,
        private readonly ?NumaToolRequest $toolRequest = null,
        private readonly ?NumaTokenUsage $tokenUsage = null,
        private readonly array $toolRequests = [],
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

    /** @return list<NumaToolRequest> */
    public function toolRequests(): array
    {
        return $this->toolRequests !== []
            ? $this->toolRequests
            : ($this->toolRequest === null ? [] : [$this->toolRequest]);
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
        private readonly ?int $billableTokens = null,
    ) {
        if ($inputTokens !== null && $inputTokens < 0) {
            throw new InvalidArgumentException('El uso de tokens de entrada no puede ser negativo.');
        }

        if ($outputTokens !== null && $outputTokens < 0) {
            throw new InvalidArgumentException('El uso de tokens de salida no puede ser negativo.');
        }

        if ($billableTokens !== null && $billableTokens < 0) {
            throw new InvalidArgumentException('El uso facturable de tokens no puede ser negativo.');
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
        if ($this->billableTokens !== null) {
            return $this->billableTokens;
        }

        if (!$this->hasReliableTokens()) {
            return null;
        }

        return $this->inputTokens + $this->outputTokens;
    }

    public function billableTokens(): ?int
    {
        return $this->billableTokens;
    }

    public function hasReliableTokens(): bool
    {
        return $this->billableTokens !== null || ($this->inputTokens !== null && $this->outputTokens !== null);
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
    public const OUTPUT_LIMIT = 'output_limit';
    public const CONFIGURATION = 'configuration';
    public const UNAVAILABLE = 'unavailable';

    private const ALLOWED_TYPES = [
        self::AUTHENTICATION,
        self::QUOTA,
        self::RATE_LIMIT,
        self::TIMEOUT,
        self::TRANSIENT,
        self::INVALID_RESPONSE,
        self::OUTPUT_LIMIT,
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

final class NumaProviderBoundary implements NumaProviderInterface
{
    /** @var array<string, array<string, mixed>> */
    private const FINANCIAL_RESULT_SCHEMAS = [
        'obtener_resumen_financiero' => [
            'tool' => true,
            'periodo' => ['inicio' => true, 'fin' => true],
            'ingresos' => true,
            'gastos' => true,
            'gastos_esenciales' => true,
            'gastos_flexibles' => true,
            'ahorro_posible' => true,
            'ahorro_real' => true,
        ],
        'obtener_ranking_categorias' => [
            'tool' => true,
            'periodo' => ['inicio' => true, 'fin' => true],
            'metrica' => true,
            'limite' => true,
            'resultado_acotado' => true,
            'categorias' => [[
                'categoria' => true,
                'label' => true,
                'total' => true,
                'porcentaje' => true,
            ]],
        ],
        'obtener_evolucion_financiera' => [
            'tool' => true,
            'periodo' => ['inicio' => true, 'fin' => true],
            'metrica' => true,
            'agrupacion' => true,
            'limite' => true,
            'resultado_acotado' => true,
            'periodo_solicitado' => ['inicio' => true, 'fin' => true],
            'mes_mayor_valor' => ['mes' => true, 'valor' => true],
            'evolucion' => [[
                'mes' => true,
                'categoria' => true,
                'label' => true,
                'tipo' => true,
                'valor' => true,
            ]],
        ],
        'comparar_periodos' => [
            'tool' => true,
            'metrica' => true,
            'categoria' => true,
            'periodo_a' => ['inicio' => true, 'fin' => true],
            'periodo_b' => ['inicio' => true, 'fin' => true],
            'valor_a' => true,
            'valor_b' => true,
            'diferencia_absoluta' => true,
            'diferencia_porcentual' => true,
        ],
        'obtener_estadisticas_movimientos' => [
            'tool' => true,
            'periodo' => ['inicio' => true, 'fin' => true],
            'metrica' => true,
            'categoria' => true,
            'promedio' => true,
            'maximo' => true,
            'minimo' => true,
            'total' => true,
            'cantidad_movimientos' => true,
            'promedio_mensual' => true,
            'meses_con_datos' => true,
        ],
        'obtener_movimientos' => [
            'tool' => true,
            'periodo' => ['inicio' => true, 'fin' => true],
            'tipo_movimiento' => true,
            'tipo_gasto' => true,
            'grupo' => true,
            'categoria' => true,
            'orden' => true,
            'direccion' => true,
            'limite' => true,
            'cantidad_total' => true,
            'importe_total' => true,
            'seleccion_acotada' => true,
            'resultado_acotado' => true,
            'movimientos' => [[
                'fecha' => true,
                'cantidad' => true,
                'tipo_movimiento' => true,
                'tipo_gasto' => true,
                'categoria' => true,
                'label' => true,
            ]],
        ],
    ];

    /** @var array<int, string> */
    private const FORBIDDEN_KEYS = [
        'usuario_id',
        'user_id',
        'id',
        'ids',
        'email',
        'correo',
        'mail',
        'username',
        'nombre_usuario',
        'usuario_nombre',
        'sql',
        'tabla',
        'tablas',
        'table',
        'tables',
        'columna',
        'columnas',
        'column',
        'columns',
        'meta',
        'metas',
        'escenario',
        'escenarios',
        'inflacion',
        'hipoteca',
        'hipotecas',
        'nota',
        'notas',
    ];

    public function __construct(private readonly NumaProviderInterface $provider)
    {
    }

    public function respond(NumaRequest $request): NumaResponse
    {
        $this->assertBoundary($request);

        return $this->provider->respond($request);
    }

    private function assertBoundary(NumaRequest $request): void
    {
        $this->assertFinancialToolResults($request->context());
        $this->rejectForbiddenKeys($request->context());
    }

    /**
     * @param array<int, array<string, mixed>> $context
     */
    private function assertFinancialToolResults(array $context): void
    {
        foreach ($context as $contextItem) {
            if (($contextItem['type'] ?? null) !== 'financial_tool_results') {
                continue;
            }

            $items = $contextItem['items'] ?? null;
            if (!is_array($items)) {
                throw $this->boundaryViolation();
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    throw $this->boundaryViolation();
                }

                $toolName = $item['tool'] ?? null;
                if (!is_string($toolName) || !isset(self::FINANCIAL_RESULT_SCHEMAS[$toolName])) {
                    throw $this->boundaryViolation();
                }

                $this->assertAllowedShape($item, self::FINANCIAL_RESULT_SCHEMAS[$toolName]);
            }
        }
    }

    /**
     * @param array<string, mixed> $value
     * @param array<string, mixed> $schema
     */
    private function assertAllowedShape(array $value, array $schema): void
    {
        foreach ($value as $key => $item) {
            if (!is_string($key) || !array_key_exists($key, $schema)) {
                throw $this->boundaryViolation();
            }

            $allowed = $schema[$key];
            if ($allowed === true) {
                if (is_array($item)) {
                    throw $this->boundaryViolation();
                }

                continue;
            }

            if ($this->isListSchema($allowed)) {
                if (!is_array($item) || !array_is_list($item)) {
                    throw $this->boundaryViolation();
                }

                foreach ($item as $listItem) {
                    if (!is_array($listItem)) {
                        throw $this->boundaryViolation();
                    }

                    $this->assertAllowedShape($listItem, $allowed[0]);
                }

                continue;
            }

            if (!is_array($allowed) || !is_array($item) || array_is_list($item)) {
                throw $this->boundaryViolation();
            }

            $this->assertAllowedShape($item, $allowed);
        }
    }

    /** @param mixed $schema */
    private function isListSchema($schema): bool
    {
        return is_array($schema)
            && array_is_list($schema)
            && count($schema) === 1
            && is_array($schema[0]);
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $value
     */
    private function rejectForbiddenKeys(array $value): void
    {
        foreach ($value as $key => $item) {
            if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN_KEYS, true)) {
                throw $this->boundaryViolation();
            }

            if (is_array($item)) {
                $this->rejectForbiddenKeys($item);
            }
        }
    }

    private function boundaryViolation(): NumaProviderException
    {
        return new NumaProviderException(new NumaProviderError(
            NumaProviderError::INVALID_RESPONSE,
            'NUMA_PROVIDER_INVALID_RESPONSE'
        ));
    }
}

final class NumaSystemInstructionProvider implements NumaProviderInterface
{
    public function __construct(
        private readonly NumaProviderInterface $provider,
        private readonly string $systemInstruction,
    ) {
        if (trim($systemInstruction) === '') {
            throw new NumaProviderException(new NumaProviderError(
                NumaProviderError::CONFIGURATION,
                'NUMA_CONFIGURATION_ERROR'
            ));
        }
    }

    public static function fromBasePrompt(NumaProviderInterface $provider): self
    {
        $path = self::basePromptPath();
        $contents = is_readable($path) ? file_get_contents($path) : false;

        if (!is_string($contents) || trim($contents) === '') {
            throw new NumaProviderException(new NumaProviderError(
                NumaProviderError::CONFIGURATION,
                'NUMA_CONFIGURATION_ERROR'
            ));
        }

        return new self($provider, trim($contents));
    }

    public function respond(NumaRequest $request): NumaResponse
    {
        $controlledRequest = new NumaRequest(
            $request->message(),
            $this->systemInstruction,
            $request->context(),
            $request->availableTools(),
            $request->history(),
            $request->responseSchema(),
            $request->functionCallingMode(),
            $request->maxOutputTokens(),
        );
        NumaInputBudget::assertFits($controlledRequest);

        return $this->provider->respond($controlledRequest);
    }

    private static function basePromptPath(): string
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

        return $basePath . '/resources/numa/prompts/base.md';
    }
}
