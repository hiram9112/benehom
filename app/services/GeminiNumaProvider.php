<?php

declare(strict_types=1);

require_once __DIR__ . '/NumaProvider.php';
require_once __DIR__ . '/NumaConfiguration.php';
require_once dirname(__DIR__) . '/helpers/utils.php';
require_once dirname(__DIR__) . '/models/NumaConsumoGlobal.php';

final class GeminiNumaProvider implements NumaProviderInterface
{
    private const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';
    private const OUTPUT_BYTES_PER_TOKEN_HARD_LIMIT = 16;

    /** @var callable */
    private $transport;

    /** @var list<array{content:array<string,mixed>,calls:list<array{name:string,id:string|null}>}> */
    private array $functionCallTurns = [];

    /** @var Closure(array<string, mixed>):void */
    private readonly Closure $diagnosticLogger;

    private readonly string $correlationId;

    private int $responseTurn = 0;

    /** @var array<int, string> */
    private array $declaredFunctionNames = [];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $maxOutputTokens = NumaConfiguration::DEFAULT_MAX_OUTPUT_TOKENS,
        private readonly int $timeoutSeconds = 10,
        private readonly int $maxTransientRetries = 1,
        ?callable $transport = null,
        private readonly string $baseUrl = self::API_BASE_URL,
        private readonly ?NumaProviderConsumptionInterface $consumption = null,
        private readonly int $maxResponseBodyBytes = 65536,
        ?callable $diagnosticLogger = null,
        ?string $correlationId = null,
    ) {
        if (trim($apiKey) === '' || trim($model) === '' || $maxResponseBodyBytes <= 0) {
            throw self::configurationError();
        }

        $this->transport = $transport ?? [$this, 'curlTransport'];
        $this->diagnosticLogger = Closure::fromCallable($diagnosticLogger ?? static function (array $diagnostic): void {
            error_log('numa_provider_response_diagnostic ' . json_encode($diagnostic, JSON_THROW_ON_ERROR));
        });
        $this->correlationId = $correlationId !== null && preg_match('/\A[a-f0-9]{32}\z/', $correlationId) === 1
            ? $correlationId
            : bin2hex(random_bytes(16));
    }

    public static function fromEnvironment(
        ?callable $transport = null,
        ?NumaProviderConsumptionInterface $consumption = null,
        bool $publicMode = false,
        ?string $correlationId = null,
    ): self
    {
        try {
            NumaConfiguration::assertRuntime($publicMode);
        } catch (NumaConfigurationException) {
            throw self::configurationError();
        }

        return new self(
            (string) bh_env_value('NUMA_API_KEY', ''),
            (string) bh_env_value('NUMA_MODEL', 'gemini-3.1-flash-lite'),
            NumaConfiguration::maxOutputTokens(),
            bh_env_int('NUMA_PROVIDER_TIMEOUT_SECONDS', 10),
            bh_env_int('NUMA_MAX_TRANSIENT_RETRIES', 1),
            $transport,
            self::API_BASE_URL,
            $consumption,
            bh_numa_max_provider_response_body_bytes(),
            correlationId: $correlationId,
        );
    }

    public function respond(NumaRequest $request): NumaResponse
    {
        $outputTokenLimit = $this->outputTokenLimit($request);
        $payload = $this->buildPayload($request, $outputTokenLimit);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $url = rtrim($this->baseUrl, '/') . '/models/' . rawurlencode($this->model) . ':generateContent';
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8',
            'x-goog-api-key: ' . $this->apiKey,
        ];

        $attempts = 0;
        $maxAttempts = 1 + ($this->financialToolResults($request) === []
            ? min(max($this->maxTransientRetries, 0), 1)
            : 0);

        do {
            ++$attempts;
            // Calcula el timeout antes de reservar consumo: si el deadline ya venció,
            // no se confirma una unidad para una llamada que no llegará a empezar.
            $timeoutSeconds = $this->safeTimeoutSeconds();
            $this->consumption?->iniciarLlamada();

            try {
                $result = ($this->transport)(
                    $url,
                    $headers,
                    $body,
                    $timeoutSeconds
                );
            } catch (NumaProviderException $exception) {
                if ($this->shouldRetry($exception, $attempts, $maxAttempts)) {
                    continue;
                }

                throw $exception;
            } catch (Throwable $exception) {
                $providerException = self::transientError($exception);

                if ($this->shouldRetry($providerException, $attempts, $maxAttempts)) {
                    continue;
                }

                throw $providerException;
            }

            $status = isset($result['status']) && is_int($result['status']) ? $result['status'] : 0;
            $responseBody = isset($result['body']) && is_string($result['body']) ? $result['body'] : '';
            $this->assertResponseBodySize($responseBody);

            if ($status < 200 || $status >= 300) {
                $exception = $this->httpError($status, $responseBody);

                if ($this->shouldRetry($exception, $attempts, $maxAttempts)) {
                    continue;
                }

                throw $exception;
            }

            $response = $this->parseResponse($responseBody, $outputTokenLimit);
            $this->consumption?->registrarTokens($response->tokenUsage());

            return $response;
        } while ($attempts < $maxAttempts);

        throw self::unavailableError();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(NumaRequest $request, int $outputTokenLimit): array
    {
        $contents = [];
        foreach ($request->history() as $entry) {
            $text = $entry['message'];
            if (isset($entry['period']['start'], $entry['period']['end'])
                && is_string($entry['period']['start'])
                && is_string($entry['period']['end'])
            ) {
                $text .= "\n[Periodo resuelto por BeneHom: {$entry['period']['start']} a {$entry['period']['end']}]";
            }

            $contents[] = [
                'role' => $entry['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $text]],
            ];
        }

        $toolResults = $this->financialToolResults($request);

        $contents[] = [
            'role' => 'user',
            'parts' => [[
                'text' => $this->buildUserText($request, $toolResults !== [] && $this->functionCallTurns !== []),
            ]],
        ];

        if ($toolResults !== [] && $this->functionCallTurns !== []) {
            $expectedToolResults = array_sum(array_map(
                static fn (array $turn): int => count($turn['calls']),
                $this->functionCallTurns,
            ));
            if (count($toolResults) !== $expectedToolResults) {
                throw self::invalidResponseError();
            }

            $resultIndex = 0;
            foreach ($this->functionCallTurns as $turn) {
                $functionResponses = [];
                foreach ($turn['calls'] as $call) {
                    if (!array_key_exists($resultIndex, $toolResults)) {
                        break 2;
                    }

                    $functionResponse = [
                        'name' => $call['name'],
                        'response' => [
                            'result' => $toolResults[$resultIndex],
                        ],
                    ];

                    if ($call['id'] !== null) {
                        $functionResponse['id'] = $call['id'];
                    }

                    $functionResponses[] = ['functionResponse' => $functionResponse];
                    ++$resultIndex;
                }

                $contents[] = $turn['content'];
                $contents[] = [
                    'role' => 'user',
                    'parts' => $functionResponses,
                ];
            }
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => $outputTokenLimit,
                'thinkingConfig' => [
                    'thinkingLevel' => 'low',
                ],
            ],
        ];

        if ($request->responseSchema() !== null) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
            $payload['generationConfig']['responseSchema'] = $request->responseSchema();
        }

        if (trim($request->systemInstruction()) !== '') {
            $payload['system_instruction'] = [
                'parts' => [[
                    'text' => $request->systemInstruction(),
                ]],
            ];
        }

        $functionDeclarations = array_map(
            static fn (array $declaration): array => self::geminiFunctionDeclaration($declaration),
            $this->functionDeclarations($request),
        );
        $this->declaredFunctionNames = array_map(
            static fn (array $declaration): string => (string) $declaration['name'],
            $functionDeclarations
        );

        if ($functionDeclarations !== []) {
            $payload['tools'] = [[
                'functionDeclarations' => $functionDeclarations,
            ]];
            $payload['toolConfig'] = [
                'functionCallingConfig' => [
                    'mode' => $request->functionCallingMode() ?? NumaRequest::FUNCTION_CALLING_AUTO,
                ],
            ];
        }

        return $payload;
    }

    private function buildUserText(NumaRequest $request, bool $excludeToolResults = false): string
    {
        $parts = [];
        $context = $this->contextForUserText($request->context(), $excludeToolResults);

        if ($context !== []) {
            $parts[] = 'Contexto controlado de BeneHom:';
            $parts[] = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $parts[] = 'Mensaje actual del usuario:';
        $parts[] = $request->message();

        return implode("\n", $parts);
    }

    private function safeTimeoutSeconds(): int
    {
        if ($this->consumption instanceof NumaInteractionBudgetInterface) {
            return $this->consumption->timeoutForCall($this->timeoutSeconds);
        }

        return max(1, min($this->timeoutSeconds, 10));
    }

    /**
     * @param array<int, string> $headers
     * @return array{status:int, body:string}
     */
    public function curlTransport(string $url, array $headers, string $body, int $timeoutSeconds): array
    {
        if (bh_env_value('APP_ENV') === 'testing') {
            throw self::configurationError();
        }

        if (!function_exists('curl_init')) {
            throw self::configurationError();
        }

        $handle = curl_init($url);

        if ($handle === false) {
            throw self::unavailableError();
        }

        $responseBody = '';
        $responseTooLarge = false;
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => max(1, min(5, $timeoutSeconds)),
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => function ($curlHandle, string $chunk) use (&$responseBody, &$responseTooLarge): int {
                if (strlen($responseBody) + strlen($chunk) > $this->maxResponseBodyBytes) {
                    $responseTooLarge = true;

                    return 0;
                }

                $responseBody .= $chunk;

                return strlen($chunk);
            },
        ]);

        $completed = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($handle);
        curl_close($handle);

        if ($responseTooLarge) {
            throw self::invalidResponseError();
        }

        if ($completed === false) {
            if ($errno === CURLE_OPERATION_TIMEDOUT || $errno === CURLE_COULDNT_CONNECT) {
                throw self::timeoutError(true);
            }

            throw self::transientError();
        }

        return [
            'status' => $status,
            'body' => $responseBody,
        ];
    }

    private function parseResponse(string $responseBody, int $outputTokenLimit): NumaResponse
    {
        $this->assertResponseBodySize($responseBody);

        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw self::invalidResponseError($exception);
        }

        if (!is_array($decoded)) {
            throw self::invalidResponseError();
        }

        $candidate = $this->candidate($decoded);
        $usage = $this->tokenUsage($decoded);
        $this->assertOutputWithinLimits($candidate, $usage, $outputTokenLimit);
        $this->recordResponseDiagnostics($candidate);
        $toolRequests = $this->extractToolRequests($candidate);
        $message = $this->extractText($candidate);

        if ($toolRequests !== []) {
            // Una llamada a function no puede venir acompañada de una respuesta final.
            if ($message !== '') {
                throw self::invalidResponseError();
            }

            return new NumaResponse(
                'Solicitud de tool de Numa.',
                null,
                $toolRequests[0],
                $usage,
                $toolRequests,
            );
        }

        if ($message === '' || $this->containsUnsafeText($message)) {
            throw self::invalidResponseError();
        }

        $this->functionCallTurns = [];

        return new NumaResponse(
            $message,
            $this->structuredData($message),
            null,
            $usage
        );
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function extractToolRequests(array $candidate): array
    {
        $content = $candidate['content'] ?? null;
        $parts = is_array($content) ? ($content['parts'] ?? null) : null;

        if (!is_array($content) || !is_array($parts)) {
            return [];
        }

        $functionCalls = [];
        foreach ($parts as $part) {
            if (!is_array($part) || !array_key_exists('functionCall', $part)) {
                continue;
            }

            if (!is_array($part['functionCall'])) {
                throw self::invalidResponseError();
            }

            $functionCalls[] = $part['functionCall'];
        }

        if ($functionCalls === []) {
            return [];
        }

        $toolRequests = [];
        $turnCalls = [];
        foreach ($functionCalls as $functionCall) {
            $name = $functionCall['name'] ?? null;

            if (!is_string($name) || trim($name) === '') {
                throw self::invalidResponseError();
            }

            $name = trim($name);
            if ($this->declaredFunctionNames === [] || !in_array($name, $this->declaredFunctionNames, true)) {
                throw self::invalidResponseError();
            }

            $args = $functionCall['args'] ?? [];
            if (!is_array($args) || ($args !== [] && array_is_list($args))) {
                throw self::invalidResponseError();
            }

            $id = $functionCall['id'] ?? null;
            if ($id !== null && !is_string($id)) {
                throw self::invalidResponseError();
            }

            $toolRequests[] = new NumaToolRequest($name, $args);
            $turnCalls[] = [
                'name' => $name,
                'id' => $id,
            ];
        }

        $modelContent = $content;
        $modelContent['role'] = 'model';

        $this->functionCallTurns[] = [
            'content' => $modelContent,
            'calls' => $turnCalls,
        ];

        return $toolRequests;
    }

    /** @param array<string, mixed> $candidate */
    private function recordResponseDiagnostics(array $candidate): void
    {
        ++$this->responseTurn;

        if (!bh_env_bool('NUMA_PROVIDER_RESPONSE_DIAGNOSTICS', false)) {
            return;
        }

        $parts = $candidate['content']['parts'] ?? null;
        if (!is_array($parts)) {
            return;
        }

        $functionCalls = [];
        $partShapes = [];
        foreach ($parts as $part) {
            if (!is_array($part)) {
                $partShapes[] = ['invalid'];
                continue;
            }

            $partShapes[] = array_values(array_filter(array_keys($part), 'is_string'));
            $functionCall = $part['functionCall'] ?? null;
            if (!is_array($functionCall)) {
                continue;
            }

            $functionCalls[] = [
                'name' => is_string($functionCall['name'] ?? null) ? $functionCall['name'] : null,
                'id' => is_string($functionCall['id'] ?? null) ? $functionCall['id'] : null,
            ];
        }

        try {
            ($this->diagnosticLogger)([
                'correlation_id' => $this->correlationId,
                'provider_turn' => $this->responseTurn,
                'function_call_count' => count($functionCalls),
                'function_calls' => $functionCalls,
                'finish_reason' => is_string($candidate['finishReason'] ?? null) ? $candidate['finishReason'] : null,
                'part_shapes' => $partShapes,
            ]);
        } catch (Throwable) {
            // El diagnóstico no puede alterar el resultado del proveedor.
        }
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function extractText(array $candidate): string
    {
        $parts = $candidate['content']['parts'] ?? null;

        if (!is_array($parts)) {
            return '';
        }

        $textParts = [];

        foreach ($parts as $part) {
            if (!is_array($part) || ($part['thought'] ?? false) === true || !isset($part['text']) || !is_string($part['text'])) {
                continue;
            }

            $textParts[] = $part['text'];
        }

        return trim(implode("\n", $textParts));
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array<string, mixed>
     */
    private function candidate(array $decoded): array
    {
        $feedback = $decoded['promptFeedback'] ?? null;
        if (is_array($feedback) && isset($feedback['blockReason']) && $feedback['blockReason'] !== '') {
            throw self::invalidResponseError();
        }

        $candidates = $decoded['candidates'] ?? null;
        if (!is_array($candidates) || !array_is_list($candidates) || count($candidates) !== 1 || !is_array($candidates[0])) {
            throw self::invalidResponseError();
        }

        $candidate = $candidates[0];
        $finishReason = $candidate['finishReason'] ?? null;
        if ($finishReason === 'MAX_TOKENS') {
            throw self::outputLimitError();
        }

        if ($finishReason !== 'STOP') {
            throw self::invalidResponseError();
        }

        $ratings = $candidate['safetyRatings'] ?? [];
        if (!is_array($ratings)) {
            throw self::invalidResponseError();
        }

        foreach ($ratings as $rating) {
            if (!is_array($rating)) {
                throw self::invalidResponseError();
            }

            if (($rating['blocked'] ?? false) === true) {
                throw self::invalidResponseError();
            }
        }

        return $candidate;
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function assertOutputWithinLimits(array $candidate, NumaTokenUsage $usage, int $outputTokenLimit): void
    {
        $content = $candidate['content'] ?? null;
        if (!is_array($content)) {
            throw self::invalidResponseError();
        }

        try {
            $contentBytes = strlen(json_encode($content, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw self::invalidResponseError($exception);
        }

        if ($contentBytes > $this->maxOutputBytes($outputTokenLimit)
            || ($usage->outputTokens() !== null && $usage->outputTokens() > $outputTokenLimit)
        ) {
            throw self::invalidResponseError();
        }
    }

    private function maxOutputTokens(): int
    {
        return max($this->maxOutputTokens, 1);
    }

    private function outputTokenLimit(NumaRequest $request): int
    {
        return $request->maxOutputTokens() ?? $this->maxOutputTokens();
    }

    private function maxOutputBytes(int $outputTokenLimit): int
    {
        return $outputTokenLimit * self::OUTPUT_BYTES_PER_TOKEN_HARD_LIMIT;
    }

    private function containsUnsafeText(string $message): bool
    {
        return preg_match(
            '/\b(recomiendo|recomendar[ií]a|aconsejo|aconsejar[ií]a|deber[ií]as|conviene)\b.*\b(comprar|vender|invertir|acciones?|criptomonedas?|criptos?|fondos?|etf|bonos?|seguros?)\b'
            . '|\b(compra|vende|invierte)\b.*\b(acciones?|criptomonedas?|criptos?|fondos?|etf|bonos?|seguros?)\b'
            . '|\b(instrucciones (?:internas|del sistema)|mensaje de sistema|system prompt|prompt interno|prompt del sistema|api[_ -]?key|clave (?:de )?api|secretos?|token de acceso|contrasena|password)\b/iu',
            $message
        ) === 1;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function structuredData(string $message): ?array
    {
        try {
            $decoded = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) && !array_is_list($decoded) ? $decoded : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function functionDeclarations(NumaRequest $request): array
    {
        $availableTools = $request->availableTools();
        if ($availableTools === []) {
            return [];
        }

        $definitionsByName = [];
        foreach ($request->context() as $contextItem) {
            if (!is_array($contextItem) || ($contextItem['type'] ?? null) !== 'available_financial_tools') {
                continue;
            }

            $items = $contextItem['items'] ?? null;
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (!is_array($item) || !isset($item['name']) || !is_string($item['name'])) {
                    continue;
                }

                $definitionsByName[$item['name']] = $item;
            }
        }

        $declarations = [];
        foreach ($availableTools as $name) {
            if (!is_string($name) || !isset($definitionsByName[$name])) {
                throw self::invalidResponseError();
            }

            $definition = $definitionsByName[$name];
            $description = $definition['description'] ?? null;
            $parameters = $definition['parameters'] ?? null;

            if (!is_string($description) || trim($description) === '' || !is_array($parameters)) {
                throw self::invalidResponseError();
            }

            $declarations[] = [
                'name' => $name,
                'description' => $description,
                'parameters' => $parameters,
            ];
        }

        return $declarations;
    }

    /**
     * Gemini function declarations use an OpenAPI subset that excludes
     * additionalProperties. The internal contract remains closed and is still
     * enforced before a tool can execute.
     *
     * @param array<string, mixed> $declaration
     * @return array<string, mixed>
     */
    private static function geminiFunctionDeclaration(array $declaration): array
    {
        $parameters = $declaration['parameters'] ?? null;

        if (is_array($parameters)) {
            $declaration['parameters'] = self::geminiParameterSchema($parameters);
        }

        return $declaration;
    }

    /**
     * @param array<array-key, mixed> $schema
     * @return array<array-key, mixed>
     */
    private static function geminiParameterSchema(array $schema): array
    {
        unset($schema['additionalProperties']);

        foreach ($schema as $key => $value) {
            if (is_array($value)) {
                $schema[$key] = self::geminiParameterSchema($value);
            }
        }

        return $schema;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function financialToolResults(NumaRequest $request): array
    {
        foreach ($request->context() as $contextItem) {
            if (!is_array($contextItem) || ($contextItem['type'] ?? null) !== 'financial_tool_results') {
                continue;
            }

            $items = $contextItem['items'] ?? null;
            if (is_array($items)) {
                return array_values(array_filter($items, static fn (mixed $item): bool => is_array($item)));
            }
        }

        return [];
    }

    /**
     * @param array<int, array<string, mixed>> $context
     * @return array<int, array<string, mixed>>
     */
    private function contextForUserText(array $context, bool $excludeToolResults): array
    {
        return array_values(array_filter(
            $context,
            static function (mixed $contextItem) use ($excludeToolResults): bool {
                if (!is_array($contextItem)) {
                    return true;
                }

                $type = $contextItem['type'] ?? null;

                return $type !== 'available_financial_tools'
                    && (!$excludeToolResults || $type !== 'financial_tool_results');
            }
        ));
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function tokenUsage(array $decoded): NumaTokenUsage
    {
        $usage = $decoded['usageMetadata'] ?? null;

        if (!is_array($usage)) {
            return NumaTokenUsage::unknown();
        }

        $inputTokens = $usage['promptTokenCount'] ?? null;
        $outputTokens = $usage['candidatesTokenCount'] ?? null;
        $totalTokens = $usage['totalTokenCount'] ?? null;

        if (is_int($totalTokens)) {
            return new NumaTokenUsage(
                is_int($inputTokens) ? $inputTokens : null,
                is_int($outputTokens) ? $outputTokens : null,
                $totalTokens
            );
        }

        if (!is_int($inputTokens) && !is_int($outputTokens)) {
            return NumaTokenUsage::unknown();
        }

        return new NumaTokenUsage(
            is_int($inputTokens) ? $inputTokens : null,
            is_int($outputTokens) ? $outputTokens : null
        );
    }

    private function httpError(int $status, string $responseBody): NumaProviderException
    {
        if ($status === 401 || $status === 403) {
            return new NumaProviderException(new NumaProviderError(
                NumaProviderError::AUTHENTICATION,
                'NUMA_PROVIDER_AUTH_ERROR'
            ));
        }

        if ($status === 429) {
            return $this->quotaExceeded($responseBody)
                ? new NumaProviderException(new NumaProviderError(NumaProviderError::QUOTA, 'NUMA_PROVIDER_QUOTA_EXCEEDED'))
                : new NumaProviderException(new NumaProviderError(NumaProviderError::RATE_LIMIT, 'NUMA_PROVIDER_RATE_LIMITED'));
        }

        if ($status === 400 || $status === 404) {
            return self::configurationError();
        }

        if ($status === 408 || ($status >= 500 && $status <= 599)) {
            return new NumaProviderException(new NumaProviderError(
                NumaProviderError::TRANSIENT,
                'NUMA_PROVIDER_UNAVAILABLE',
                true
            ));
        }

        return self::unavailableError();
    }

    private function quotaExceeded(string $responseBody): bool
    {
        return preg_match('/quota|resource_exhausted|billing|exceeded/i', $responseBody) === 1;
    }

    private function shouldRetry(NumaProviderException $exception, int $attempts, int $maxAttempts): bool
    {
        if ($attempts >= $maxAttempts || !$exception->providerError()->retryable()) {
            return false;
        }

        return !($this->consumption instanceof NumaInteractionBudgetInterface)
            || $this->consumption->allowTransientRetry();
    }

    private static function configurationError(): NumaProviderException
    {
        return new NumaProviderException(new NumaProviderError(
            NumaProviderError::CONFIGURATION,
            'NUMA_CONFIGURATION_ERROR'
        ));
    }

    private static function invalidResponseError(?Throwable $previous = null): NumaProviderException
    {
        return new NumaProviderException(new NumaProviderError(
            NumaProviderError::INVALID_RESPONSE,
            'NUMA_PROVIDER_INVALID_RESPONSE'
        ), $previous);
    }

    private static function outputLimitError(): NumaProviderException
    {
        return new NumaProviderException(new NumaProviderError(
            NumaProviderError::OUTPUT_LIMIT,
            'NUMA_PROVIDER_MAX_TOKENS'
        ));
    }

    private function assertResponseBodySize(string $responseBody): void
    {
        if (strlen($responseBody) > $this->maxResponseBodyBytes) {
            throw self::invalidResponseError();
        }
    }

    private static function timeoutError(bool $retryable = false): NumaProviderException
    {
        return new NumaProviderException(new NumaProviderError(
            NumaProviderError::TIMEOUT,
            'NUMA_PROVIDER_TIMEOUT',
            $retryable
        ));
    }

    private static function transientError(?Throwable $previous = null): NumaProviderException
    {
        return new NumaProviderException(new NumaProviderError(
            NumaProviderError::TRANSIENT,
            'NUMA_PROVIDER_UNAVAILABLE',
            true
        ), $previous);
    }

    private static function unavailableError(): NumaProviderException
    {
        return new NumaProviderException(new NumaProviderError(
            NumaProviderError::UNAVAILABLE,
            'NUMA_PROVIDER_UNAVAILABLE'
        ));
    }
}

final class NumaProviderFactory
{
    public static function fromEnvironment(
        ?callable $transport = null,
        ?NumaProviderConsumptionInterface $consumption = null,
        bool $publicMode = false,
        ?string $correlationId = null,
    ): NumaProviderInterface
    {
        $provider = strtolower((string) bh_env_value('NUMA_PROVIDER', 'gemini'));

        if ($provider === 'fake') {
            require_once __DIR__ . '/NumaTestingProvider.php';

            return NumaSystemInstructionProvider::fromBasePrompt(new NumaProviderBoundary(
                NumaTestingProvider::fromEnvironment($consumption ?? NumaConsumoGlobal::forLlm())
            ));
        }

        if ($provider !== 'gemini') {
            throw new NumaProviderException(new NumaProviderError(
                NumaProviderError::CONFIGURATION,
                'NUMA_CONFIGURATION_ERROR'
            ));
        }

        return NumaSystemInstructionProvider::fromBasePrompt(new NumaProviderBoundary(
            GeminiNumaProvider::fromEnvironment(
                $transport,
                $consumption ?? NumaConsumoGlobal::forLlm(),
                $publicMode,
                $correlationId,
            )
        ));
    }
}
