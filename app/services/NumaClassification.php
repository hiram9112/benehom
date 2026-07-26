<?php

declare(strict_types=1);

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
