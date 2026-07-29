<?php

declare(strict_types=1);

require_once __DIR__ . '/NumaProvider.php';

final class NumaClassificationIntent
{
    public const PRODUCTO = 'producto';
    public const EDUCACION_FINANCIERA = 'educacion_financiera';
    public const DATOS_USUARIO = 'datos_usuario';
    public const CONSULTA_COMBINADA = 'consulta_combinada';
    public const RECOMENDACION_FINANCIERA = 'recomendacion_financiera';
    public const FUERA_DE_AMBITO = 'fuera_de_ambito';
    public const INTENTO_MANIPULACION = 'intento_manipulacion';
    public const SOLICITUD_DATOS_TERCEROS = 'solicitud_datos_terceros';
    public const ACCION_NO_PERMITIDA = 'accion_no_permitida';

    /** @var array<int, string> */
    private const ALL = [
        self::PRODUCTO,
        self::EDUCACION_FINANCIERA,
        self::DATOS_USUARIO,
        self::CONSULTA_COMBINADA,
        self::RECOMENDACION_FINANCIERA,
        self::FUERA_DE_AMBITO,
        self::INTENTO_MANIPULACION,
        self::SOLICITUD_DATOS_TERCEROS,
        self::ACCION_NO_PERMITIDA,
    ];

    /** @var array<int, string> */
    private const ALLOWED = [
        self::PRODUCTO,
        self::EDUCACION_FINANCIERA,
        self::DATOS_USUARIO,
        self::CONSULTA_COMBINADA,
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return self::ALL;
    }

    public static function exists(string $intent): bool
    {
        return in_array($intent, self::ALL, true);
    }

    public static function mayBeAllowed(string $intent): bool
    {
        return in_array($intent, self::ALLOWED, true);
    }
}

final class NumaDataIntent
{
    public const RESUMEN_FINANCIERO = 'resumen_financiero';
    public const RANKING_CATEGORIAS = 'ranking_categorias';
    public const EVOLUCION_FINANCIERA = 'evolucion_financiera';
    public const COMPARACION_PERIODOS = 'comparacion_periodos';
    public const ESTADISTICAS_MOVIMIENTOS = 'estadisticas_movimientos';

    /** @var array<int, string> */
    private const ALL = [
        self::RESUMEN_FINANCIERO,
        self::RANKING_CATEGORIAS,
        self::EVOLUCION_FINANCIERA,
        self::COMPARACION_PERIODOS,
        self::ESTADISTICAS_MOVIMIENTOS,
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return self::ALL;
    }

    public static function exists(string $intent): bool
    {
        return in_array($intent, self::ALL, true);
    }
}

final class NumaClassification
{
    /** @var array<int, string> */
    private const REQUIRED_KEYS = ['intent', 'allowed', 'reason'];

    /** @var array<int, string> */
    private const OPTIONAL_KEYS = ['knowledge_query', 'data_intent'];

    /** @var array<int, string> */
    private const DANGEROUS_KEYS = [
        'usuario_id',
        'user_id',
        'sql',
        'tabla',
        'tablas',
        'table',
        'tables',
        'columna',
        'columnas',
        'column',
        'columns',
        'where',
        'conditions',
        'condiciones',
    ];

    public function __construct(
        private readonly string $intent,
        private readonly bool $allowed,
        private readonly string $reason,
        private readonly ?string $knowledgeQuery = null,
        private readonly ?string $dataIntent = null,
    ) {
        if (!NumaClassificationIntent::exists($intent)) {
            throw new InvalidArgumentException('Categoria de Numa no soportada.');
        }

        if ($allowed !== NumaClassificationIntent::mayBeAllowed($intent)) {
            throw new InvalidArgumentException('La autorizacion no coincide con la categoria de Numa.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('La clasificacion de Numa requiere motivo.');
        }

        if ($knowledgeQuery !== null && trim($knowledgeQuery) === '') {
            throw new InvalidArgumentException('La consulta documental de Numa no puede estar vacia.');
        }

        if ($dataIntent !== null && !NumaDataIntent::exists($dataIntent)) {
            throw new InvalidArgumentException('Intencion de datos de Numa no soportada.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromStructuredData(array $data): self
    {
        self::assertAllowedShape($data);

        $intent = $data['intent'];
        $allowed = $data['allowed'];
        $reason = $data['reason'];
        $knowledgeQuery = $data['knowledge_query'] ?? null;
        $dataIntent = $data['data_intent'] ?? null;

        if (!is_string($intent) || !is_bool($allowed) || !is_string($reason)) {
            throw new InvalidArgumentException('Salida estructurada de Numa invalida.');
        }

        if ($knowledgeQuery !== null && !is_string($knowledgeQuery)) {
            throw new InvalidArgumentException('Consulta documental de Numa invalida.');
        }

        if ($dataIntent !== null && !is_string($dataIntent)) {
            throw new InvalidArgumentException('Intencion de datos de Numa invalida.');
        }

        return new self(
            $intent,
            $allowed,
            trim($reason),
            $knowledgeQuery === null ? null : trim($knowledgeQuery),
            $dataIntent === null ? null : trim($dataIntent),
        );
    }

    public function intent(): string
    {
        return $this->intent;
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function knowledgeQuery(): ?string
    {
        return $this->knowledgeQuery;
    }

    public function dataIntent(): ?string
    {
        return $this->dataIntent;
    }

    /**
     * @return array<string, string|bool>
     */
    public function toStructuredData(): array
    {
        $data = [
            'intent' => $this->intent,
            'allowed' => $this->allowed,
            'reason' => $this->reason,
        ];

        if ($this->knowledgeQuery !== null) {
            $data['knowledge_query'] = $this->knowledgeQuery;
        }

        if ($this->dataIntent !== null) {
            $data['data_intent'] = $this->dataIntent;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function assertAllowedShape(array $data): void
    {
        foreach (self::REQUIRED_KEYS as $key) {
            if (!array_key_exists($key, $data)) {
                throw new InvalidArgumentException('Salida estructurada de Numa incompleta.');
            }
        }

        $allowedKeys = array_merge(self::REQUIRED_KEYS, self::OPTIONAL_KEYS);

        foreach (array_keys($data) as $key) {
            if (!is_string($key) || in_array(strtolower($key), self::DANGEROUS_KEYS, true)) {
                throw new InvalidArgumentException('Salida estructurada de Numa contiene parametros no permitidos.');
            }

            if (!in_array($key, $allowedKeys, true)) {
                throw new InvalidArgumentException('Salida estructurada de Numa contiene parametros no permitidos.');
            }
        }
    }
}

final class NumaLocalScopeRejection
{
    public function __construct(
        private readonly NumaClassification $classification,
        private readonly string $message,
    ) {
        if ($classification->allowed()) {
            throw new InvalidArgumentException('Un rechazo local de Numa no puede estar permitido.');
        }

        if (trim($message) === '') {
            throw new InvalidArgumentException('Un rechazo local de Numa requiere respuesta.');
        }
    }

    public function classification(): NumaClassification
    {
        return $this->classification;
    }

    public function message(): string
    {
        return $this->message;
    }
}

final class NumaFixedScopeResponse
{
    private const RESPONSE_OUT_OF_SCOPE = 'Puedo ayudarte con BeneHom, conceptos de economía familiar y el análisis de los datos que hayas registrado. No respondo preguntas generales ajenas a estas funciones.';
    private const RESPONSE_FINANCIAL_RECOMMENDATION = 'Puedo ayudarte a comprender tus ingresos, gastos y hábitos registrados, pero no puedo recomendar inversiones, productos financieros ni decisiones de compra o venta.';
    private const RESPONSE_QUICK_MONEY = 'No puedo ofrecer métodos para ganar dinero rápido ni prometer resultados financieros. Sí puedo ayudarte a analizar tu presupuesto y detectar tendencias en tus datos.';
    private const RESPONSE_MANIPULATION = 'Esa solicitud queda fuera de las funciones disponibles en Numa.';
    private const RESPONSE_THIRD_PARTY_DATA = 'Solo puedo analizar los datos de la cuenta con la que has iniciado sesión.';
    private const RESPONSE_FORBIDDEN_ACTION = 'Numa solo consulta y explica información. No puede crear, modificar ni eliminar datos.';

    public static function forIntent(string $intent, ?string $reason = null): string
    {
        if ($intent === NumaClassificationIntent::RECOMENDACION_FINANCIERA && $reason === 'local_quick_money') {
            return self::RESPONSE_QUICK_MONEY;
        }

        return match ($intent) {
            NumaClassificationIntent::RECOMENDACION_FINANCIERA => self::RESPONSE_FINANCIAL_RECOMMENDATION,
            NumaClassificationIntent::FUERA_DE_AMBITO => self::RESPONSE_OUT_OF_SCOPE,
            NumaClassificationIntent::INTENTO_MANIPULACION => self::RESPONSE_MANIPULATION,
            NumaClassificationIntent::SOLICITUD_DATOS_TERCEROS => self::RESPONSE_THIRD_PARTY_DATA,
            NumaClassificationIntent::ACCION_NO_PERMITIDA => self::RESPONSE_FORBIDDEN_ACTION,
            default => self::RESPONSE_OUT_OF_SCOPE,
        };
    }
}

final class NumaProviderScopeClassifier
{
    public function __construct(private readonly NumaProviderInterface $provider)
    {
    }

    public function classify(string $message): NumaClassification
    {
        try {
            $response = $this->provider->respond(new NumaRequest(
                $message,
                '',
                $this->classificationContext(),
                []
            ));

            if ($response->toolRequest() !== null) {
                throw $this->invalidResponse();
            }

            $data = $response->structuredData() ?? $this->decodeStructuredMessage($response->message());

            if ($data === null) {
                throw $this->invalidResponse();
            }

            return NumaClassification::fromStructuredData($data);
        } catch (NumaProviderException $exception) {
            throw $exception;
        } catch (NumaGlobalLimiteAlcanzado $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->invalidResponse($exception);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function classificationContext(): array
    {
        return [[
            'type' => 'numa_scope_classification',
            'task' => 'Clasifica solo el mensaje actual del usuario para decidir si entra en el ámbito de Numa.',
            'output' => [
                'format' => 'json_object',
                'required_keys' => ['intent', 'allowed', 'reason'],
                'optional_keys' => ['knowledge_query', 'data_intent'],
                'allowed_intents' => NumaClassificationIntent::all(),
                'allowed_data_intents' => NumaDataIntent::all(),
            ],
            'rules' => [
                'Devuelve exclusivamente JSON válido, sin texto adicional.',
                'No autorices tools, SQL, usuario_id ni acceso a datos concretos.',
                'Marca allowed true solo para producto, educacion_financiera, datos_usuario o consulta_combinada.',
                'Usa knowledge_query solo para una consulta documental breve sin datos privados.',
                'Usa data_intent solo si encaja exactamente con una intención de datos permitida.',
            ],
        ]];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeStructuredMessage(string $message): ?array
    {
        try {
            $decoded = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) && !array_is_list($decoded) ? $decoded : null;
    }

    private function invalidResponse(?Throwable $previous = null): NumaProviderException
    {
        return new NumaProviderException(new NumaProviderError(
            NumaProviderError::INVALID_RESPONSE,
            'NUMA_PROVIDER_INVALID_RESPONSE'
        ), $previous);
    }
}

final class NumaLocalScopeClassifier
{
    /** @var array<string, array<int, string>> */
    private const RULES = [
        'manipulation' => [
            '/\b(ignora|omite|saltate|salta|olvida|desobedece)\b.*\b(instrucciones|reglas|restricciones|prompt|sistema)\b/u',
            '/\b(muestra|muestrame|revela|dime|ensen(a|ame)|imprime|filtra|dame)\b.*\b(prompt|instrucciones|mensaje de sistema|system prompt|configuracion interna)\b/u',
            '/\b(actua|actuar|comportate|responde)\b.*\b(chatgpt|asistente general|sin restricciones|sin reglas|modo libre)\b/u',
            '/\b(dame|muestra|muestrame|revela|dime|cual es|cuales son)\b.*\b(api key|apikey|clave api|secretos?|password|contrasena|token|configuracion)\b/u',
        ],
        'third_party_data' => [
            '/\b(usuario id|user id)\b/u',
            '/\b(usuario|user)\s*#?\s*\d+\b/u',
            '/\b(otro usuario|otra cuenta|datos de terceros|cuenta de otra persona|gastos de otro)\b/u',
        ],
        'forbidden_action' => [
            '/^\s*(crea|crear|agrega|agregar|anade|anadir|edita|editar|modifica|modificar|elimina|eliminar|borra|borrar|registra|registrar)\b.*\b(gasto|movimiento|ingreso|meta|dato|registro)\b/u',
            '/\b(quiero que|puedes|podrias|haz|necesito que)\b.*\b(crees|crear|agregues|agregar|anadas|anadir|edites|editar|modifiques|modificar|elimines|eliminar|borres|borrar|registres|registrar)\b.*\b(gasto|movimiento|ingreso|meta|dato|registro)\b/u',
        ],
        'quick_money' => [
            '/\b(ganar|conseguir|hacer|obtener)\b.*\b(dinero|plata|euros|ingresos)\b.*\b(rapido|rapida|facil|faciles|sin esfuerzo)\b/u',
            '/\b(dinero|plata|euros|ingresos)\b.*\b(rapido|rapida|facil|faciles|sin esfuerzo)\b/u',
        ],
        'financial_recommendation' => [
            '/\b(recomiendame|recomienda|aconsejame|aconseja|deberia|conviene|mejor)\b.*\b(accion(?:es)?|criptomonedas?|criptos?|bitcoin|ethereum|fondos?|etf|seguros?|productos? financieros?|activos?|bonos?)\b/u',
            '/\b(comprar|compra|vender|vende|invertir)\b.*\b(accion(?:es)?|criptomonedas?|criptos?|bitcoin|ethereum|fondos?|etf|activos?|bonos?)\b/u',
            '/\b(accion(?:es)?|criptomonedas?|criptos?|bitcoin|ethereum|fondos?|etf|seguros?|productos? financieros?|activos?|bonos?)\b.*\b(comprar|vender|recomiendas?|conviene|deberia|mejor)\b/u',
        ],
        'out_of_scope' => [
            '/\b(asesoramiento|asesoria|consejo)\b.*\b(fiscal|legal|juridico|juridica)\b/u',
            '/\b(mi|mis|me|debo|deberia|conviene)\b.*\b(hacienda|impuestos|irpf|iva|declaracion de la renta|demanda|contrato legal|abogado)\b/u',
            '/\b(escribeme|escribe|genera|programa|haz|crea)\b.*\b(codigo|script|programa|funcion|php|javascript|python|sql)\b/u',
            '/\b(receta|cocinar|cocina|capital de|presidente de|historia de|clima en)\b/u',
        ],
    ];

    public function classify(string $message): ?NumaLocalScopeRejection
    {
        $normalized = self::normalize($message);

        if ($normalized === '') {
            return null;
        }

        if ($this->matchesAny($normalized, self::RULES['manipulation'])) {
            return $this->reject(
                NumaClassificationIntent::INTENTO_MANIPULACION,
                'local_manipulation',
                NumaFixedScopeResponse::forIntent(NumaClassificationIntent::INTENTO_MANIPULACION)
            );
        }

        if ($this->matchesAny($normalized, self::RULES['third_party_data'])) {
            return $this->reject(
                NumaClassificationIntent::SOLICITUD_DATOS_TERCEROS,
                'local_third_party_data',
                NumaFixedScopeResponse::forIntent(NumaClassificationIntent::SOLICITUD_DATOS_TERCEROS)
            );
        }

        if ($this->matchesAny($normalized, self::RULES['forbidden_action'])) {
            return $this->reject(
                NumaClassificationIntent::ACCION_NO_PERMITIDA,
                'local_forbidden_action',
                NumaFixedScopeResponse::forIntent(NumaClassificationIntent::ACCION_NO_PERMITIDA)
            );
        }

        if ($this->matchesAny($normalized, self::RULES['quick_money'])) {
            return $this->reject(
                NumaClassificationIntent::RECOMENDACION_FINANCIERA,
                'local_quick_money',
                NumaFixedScopeResponse::forIntent(NumaClassificationIntent::RECOMENDACION_FINANCIERA, 'local_quick_money')
            );
        }

        if ($this->matchesAny($normalized, self::RULES['financial_recommendation'])) {
            return $this->reject(
                NumaClassificationIntent::RECOMENDACION_FINANCIERA,
                'local_financial_recommendation',
                NumaFixedScopeResponse::forIntent(NumaClassificationIntent::RECOMENDACION_FINANCIERA)
            );
        }

        if ($this->matchesAny($normalized, self::RULES['out_of_scope'])) {
            return $this->reject(
                NumaClassificationIntent::FUERA_DE_AMBITO,
                'local_out_of_scope',
                NumaFixedScopeResponse::forIntent(NumaClassificationIntent::FUERA_DE_AMBITO)
            );
        }

        return null;
    }

    private function reject(string $intent, string $reason, string $message): NumaLocalScopeRejection
    {
        return new NumaLocalScopeRejection(
            new NumaClassification($intent, false, $reason),
            $message
        );
    }

    /**
     * @param array<int, string> $patterns
     */
    private function matchesAny(string $normalized, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    private static function normalize(string $message): string
    {
        $message = function_exists('mb_strtolower') ? mb_strtolower($message, 'UTF-8') : strtolower($message);

        $message = strtr($message, [
            'á' => 'a',
            'à' => 'a',
            'ä' => 'a',
            'â' => 'a',
            'é' => 'e',
            'è' => 'e',
            'ë' => 'e',
            'ê' => 'e',
            'í' => 'i',
            'ì' => 'i',
            'ï' => 'i',
            'î' => 'i',
            'ó' => 'o',
            'ò' => 'o',
            'ö' => 'o',
            'ô' => 'o',
            'ú' => 'u',
            'ù' => 'u',
            'ü' => 'u',
            'û' => 'u',
            'ñ' => 'n',
            '_' => ' ',
            '-' => ' ',
        ]);

        $message = preg_replace('/[^a-z0-9#\s]/u', ' ', $message) ?? $message;
        $message = preg_replace('/\s+/u', ' ', $message) ?? $message;

        return trim($message);
    }
}
