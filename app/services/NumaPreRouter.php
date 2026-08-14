<?php

declare(strict_types=1);

require_once __DIR__ . '/NumaClassification.php';

final class NumaPreRoute
{
    public const PRODUCTO = 'producto';
    public const DATOS_FINANCIEROS = 'datos_financieros';
    public const CONSULTA_COMBINADA = 'consulta_combinada';
    public const AMBIGUA = 'ambigua';
    public const RECHAZO_LOCAL = 'rechazo_local';

    /** @var array<int, string> */
    private const ALL = [
        self::PRODUCTO,
        self::DATOS_FINANCIEROS,
        self::CONSULTA_COMBINADA,
        self::AMBIGUA,
        self::RECHAZO_LOCAL,
    ];

    public function __construct(
        private readonly string $route,
        private readonly int $plannedPaidCalls,
        private readonly ?NumaLocalScopeRejection $localRejection = null,
    ) {
        if (!in_array($route, self::ALL, true)) {
            throw new InvalidArgumentException('Recorrido previo de Numa no soportado.');
        }

        if ($plannedPaidCalls < 0 || $plannedPaidCalls > 3) {
            throw new InvalidArgumentException('Presupuesto previo de Numa no valido.');
        }

        if (($route === self::RECHAZO_LOCAL) !== ($localRejection !== null)) {
            throw new InvalidArgumentException('El rechazo local debe conservar su clasificacion.');
        }
    }

    public function route(): string
    {
        return $this->route;
    }

    public function plannedPaidCalls(): int
    {
        return $this->plannedPaidCalls;
    }

    public function localRejection(): ?NumaLocalScopeRejection
    {
        return $this->localRejection;
    }
}

final class NumaPreRouter
{
    /** @var array<int, string> */
    private const DOCUMENTATION_PATTERNS = [
        '/\b(como|donde)\s+(anado|agrego|registro|creo|funciona)\b/u',
        '/\b(que es|que son|que significa|diferencia entre|para que sirve|como funciona)\b/u',
        '/\b(benehom|ahorro posible|ahorro real|gasto flexible|gasto esencial)\b/u',
    ];

    /** @var array<int, string> */
    private const FINANCIAL_DATA_PATTERNS = [
        '/\b(cuanto|cuantos|cual|cuales|en que mes|que mes|donde)\b.*\b(gaste|gastado|gasto|ingrese|ingresado|ingreso|movimientos?)\b/u',
        '/\b(gaste|gastado|gasto|ingrese|ingresado|ingreso)\b.*\b(este mes|mes pasado|mes anterior|este ano|ano pasado|mas|menos|promedio|total)\b/u',
        '/\b(mes|categoria|gastos?|ingresos?|movimientos?)\b.*\b(mas|menos|promedio|total|compar[ae])\b/u',
        '/\b(mis|mi)\s+(gastos?|ingresos?|movimientos?|ahorro real)\b/u',
    ];

    public function __construct(private readonly NumaLocalScopeClassifier $localScopeClassifier = new NumaLocalScopeClassifier())
    {
    }

    public function route(string $message, bool $hasConversationContext = false): NumaPreRoute
    {
        $localRejection = $this->localScopeClassifier->classify($message, $hasConversationContext);
        if ($localRejection !== null) {
            return new NumaPreRoute(NumaPreRoute::RECHAZO_LOCAL, 0, $localRejection);
        }

        $normalized = self::normalize($message);
        $looksDocumentary = $this->matchesAny($normalized, self::DOCUMENTATION_PATTERNS);
        $looksFinancial = $this->matchesAny($normalized, self::FINANCIAL_DATA_PATTERNS);

        if ($looksDocumentary && $looksFinancial) {
            return new NumaPreRoute(NumaPreRoute::CONSULTA_COMBINADA, 3);
        }

        if ($looksDocumentary) {
            return new NumaPreRoute(NumaPreRoute::PRODUCTO, 2);
        }

        if ($looksFinancial) {
            return new NumaPreRoute(NumaPreRoute::DATOS_FINANCIEROS, 2);
        }

        return new NumaPreRoute(NumaPreRoute::AMBIGUA, 3);
    }

    /**
     * @param array<int, string> $patterns
     */
    private function matchesAny(string $message, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message) === 1) {
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
        ]);
        $message = preg_replace('/[^a-z0-9\s]/u', ' ', $message) ?? $message;
        $message = preg_replace('/\s+/u', ' ', $message) ?? $message;

        return trim($message);
    }
}
