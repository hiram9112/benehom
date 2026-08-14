<?php

declare(strict_types=1);

require_once __DIR__ . '/NumaProvider.php';
require_once dirname(__DIR__) . '/helpers/utils.php';
require_once dirname(__DIR__) . '/models/NumaConsumoGlobal.php';

final class GeminiNumaProvider implements NumaProviderInterface
{
    private const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';
    private const OUTPUT_TOKEN_HARD_LIMIT = 220;

    /** @var callable */
    private $transport;

    /** @var array<int, array{content:array<string,mixed>,name:string,id:string|null}> */
    private array $functionCallTurns = [];

    /** @var array<int, string> */
    private array $declaredFunctionNames = [];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $maxOutputTokens = self::OUTPUT_TOKEN_HARD_LIMIT,
        private readonly int $timeoutSeconds = 10,
        private readonly int $maxTransientRetries = 1,
        ?callable $transport = null,
        private readonly string $baseUrl = self::API_BASE_URL,
        private readonly ?NumaProviderConsumptionInterface $consumption = null,
    ) {
        if (trim($apiKey) === '' || trim($model) === '') {
            throw self::configurationError();
        }

        $this->transport = $transport ?? [$this, 'curlTransport'];
    }

    public static function fromEnvironment(
        ?callable $transport = null,
        ?NumaProviderConsumptionInterface $consumption = null,
    ): self
    {
        return new self(
            (string) bh_env_value('NUMA_API_KEY', ''),
            (string) bh_env_value('NUMA_MODEL', 'gemini-3.1-flash-lite'),
            bh_env_int('NUMA_MAX_OUTPUT_TOKENS', self::OUTPUT_TOKEN_HARD_LIMIT),
            bh_env_int('NUMA_PROVIDER_TIMEOUT_SECONDS', 10),
            bh_env_int('NUMA_MAX_TRANSIENT_RETRIES', 1),
            $transport,
            self::API_BASE_URL,
            $consumption
        );
    }

    public function respond(NumaRequest $request): NumaResponse
    {
        $payload = $this->buildPayload($request);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $url = rtrim($this->baseUrl, '/') . '/models/' . rawurlencode($this->model) . ':generateContent';
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8',
            'x-goog-api-key: ' . $this->apiKey,
        ];

        $attempts = 0;
        $maxAttempts = 1 + min(max($this->maxTransientRetries, 0), 1);

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

            if ($status < 200 || $status >= 300) {
                $exception = $this->httpError($status, $responseBody);

                if ($this->shouldRetry($exception, $attempts, $maxAttempts)) {
                    continue;
                }

                throw $exception;
            }

            $response = $this->parseResponse($responseBody);
            $this->consumption?->registrarTokens($response->tokenUsage());

            return $response;
        } while ($attempts < $maxAttempts);

        throw self::unavailableError();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(NumaRequest $request): array
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
            if (count($toolResults) > count($this->functionCallTurns)) {
                throw self::invalidResponseError();
            }

            foreach (array_values($toolResults) as $index => $result) {
                $turn = $this->functionCallTurns[$index];
                $functionResponse = [
                    'name' => $turn['name'],
                    'response' => [
                        'result' => $result,
                    ],
                ];

                if ($turn['id'] !== null) {
                    $functionResponse['id'] = $turn['id'];
                }

                $contents[] = $turn['content'];
                $contents[] = [
                    'role' => 'user',
                    'parts' => [[
                        'functionResponse' => $functionResponse,
                    ]],
                ];
            }
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => min(max($this->maxOutputTokens, 1), self::OUTPUT_TOKEN_HARD_LIMIT),
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

        $functionDeclarations = $this->functionDeclarations($request);
        $this->declaredFunctionNames = array_map(
            static fn (array $declaration): string => (string) $declaration['name'],
            $functionDeclarations
        );

        if ($functionDeclarations !== []) {
            $payload['tools'] = [[
                'functionDeclarations' => $functionDeclarations,
            ]];
        }

        return $payload;
    }

    private function buildUserText(NumaRequest $request, bool $excludeToolResults = false): string
    {
        $parts = [];
        $context = $excludeToolResults ? $this->contextWithoutToolResults($request->context()) : $request->context();

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
        if (!function_exists('curl_init')) {
            throw self::configurationError();
        }

        $handle = curl_init($url);

        if ($handle === false) {
            throw self::unavailableError();
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => max(1, min(5, $timeoutSeconds)),
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $responseBody = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($handle);
        unset($handle);

        if ($responseBody === false) {
            if ($errno === CURLE_OPERATION_TIMEDOUT || $errno === CURLE_COULDNT_CONNECT) {
                throw self::timeoutError(true);
            }

            throw self::transientError();
        }

        return [
            'status' => $status,
            'body' => (string) $responseBody,
        ];
    }

    private function parseResponse(string $responseBody): NumaResponse
    {
        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw self::invalidResponseError($exception);
        }

        if (!is_array($decoded)) {
            throw self::invalidResponseError();
        }

        $toolRequest = $this->extractToolRequest($decoded);

        if ($toolRequest !== null) {
            return new NumaResponse(
                $this->extractText($decoded) ?: 'Solicitud de tool de Numa.',
                null,
                $toolRequest,
                $this->tokenUsage($decoded)
            );
        }

        $message = $this->extractText($decoded);

        if ($message === '') {
            throw self::invalidResponseError();
        }

        $this->functionCallTurns = [];

        return new NumaResponse(
            $message,
            $this->structuredData($message),
            null,
            $this->tokenUsage($decoded)
        );
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractToolRequest(array $decoded): ?NumaToolRequest
    {
        $content = $decoded['candidates'][0]['content'] ?? null;
        $parts = is_array($content) ? ($content['parts'] ?? null) : null;

        if (!is_array($content) || !is_array($parts)) {
            return null;
        }

        $functionCallPart = null;
        foreach ($parts as $part) {
            if (!is_array($part) || !isset($part['functionCall'])) {
                continue;
            }

            if ($functionCallPart !== null || !is_array($part['functionCall'])) {
                throw self::invalidResponseError();
            }

            $functionCallPart = $part;
        }

        if ($functionCallPart === null) {
            return null;
        }

        $functionCall = $functionCallPart['functionCall'];
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

        $modelContent = $content;
        $modelContent['role'] = 'model';

        $this->functionCallTurns[] = [
            'content' => $modelContent,
            'name' => $name,
            'id' => $id,
        ];

        return new NumaToolRequest($name, $args);
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractText(array $decoded): string
    {
        $parts = $decoded['candidates'][0]['content']['parts'] ?? null;

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
            $schema = $definition['schema'] ?? null;
            $required = $definition['required'] ?? [];

            if (!is_string($description) || !is_array($schema) || !is_array($required)) {
                throw self::invalidResponseError();
            }

            $declarations[] = [
                'name' => $name,
                'description' => $description,
                'parameters' => $this->normalizeFunctionSchema($schema, $required),
            ];
        }

        return $declarations;
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<int, mixed> $required
     * @return array<string, mixed>
     */
    private function normalizeFunctionSchema(array $schema, array $required): array
    {
        $normalized = [
            'type' => isset($schema['type']) && is_string($schema['type']) ? $schema['type'] : 'object',
        ];

        $properties = $schema['properties'] ?? [];
        if (is_array($properties) && $properties !== []) {
            $normalized['properties'] = [];

            foreach ($properties as $name => $property) {
                if (!is_string($name) || !is_array($property)) {
                    continue;
                }

                $normalized['properties'][$name] = $this->normalizeFunctionSchemaProperty($property);
            }
        }

        $required = array_values(array_filter($required, static fn (mixed $name): bool => is_string($name) && trim($name) !== ''));
        if ($required !== []) {
            $normalized['required'] = $required;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $property
     * @return array<string, mixed>
     */
    private function normalizeFunctionSchemaProperty(array $property): array
    {
        $normalized = [];

        foreach (['type', 'format', 'description'] as $key) {
            if (isset($property[$key]) && is_string($property[$key])) {
                $normalized[$key] = $property[$key];
            }
        }

        if (isset($property['enum']) && is_array($property['enum'])) {
            $normalized['enum'] = array_values($property['enum']);
        }

        if (isset($property['items']) && is_array($property['items'])) {
            $normalized['items'] = $this->normalizeFunctionSchemaProperty($property['items']);
        }

        return $normalized !== [] ? $normalized : ['type' => 'string'];
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
    private function contextWithoutToolResults(array $context): array
    {
        return array_values(array_filter(
            $context,
            static fn (mixed $contextItem): bool => !is_array($contextItem) || ($contextItem['type'] ?? null) !== 'financial_tool_results'
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

        if (!is_int($inputTokens) || !is_int($outputTokens)) {
            return NumaTokenUsage::unknown();
        }

        return new NumaTokenUsage($inputTokens, $outputTokens);
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
    ): NumaProviderInterface
    {
        $provider = strtolower((string) bh_env_value('NUMA_PROVIDER', 'gemini'));

        if ($provider !== 'gemini') {
            throw new NumaProviderException(new NumaProviderError(
                NumaProviderError::CONFIGURATION,
                'NUMA_CONFIGURATION_ERROR'
            ));
        }

        return NumaSystemInstructionProvider::fromBasePrompt(new NumaProviderBoundary(
            GeminiNumaProvider::fromEnvironment(
                $transport,
                $consumption ?? NumaConsumoGlobal::forLlm()
            )
        ));
    }
}
