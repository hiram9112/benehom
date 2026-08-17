<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once dirname(__DIR__) . '/services/NumaProvider.php';
require_once dirname(__DIR__) . '/helpers/utils.php';

final class NumaGlobalLimiteAlcanzado extends RuntimeException
{
}

final class NumaConsumoGlobal implements NumaProviderDeferredConsumptionInterface
{
    private const CALL_TYPE_LLM = 'llm';
    private const CALL_TYPE_EMBEDDING = 'embedding';
    private const EMBEDDING_MAX_INPUT_TOKENS = 2048;

    /** @var list<array{fecha:string,input:int,output:int}> */
    private array $pendingTokenReservations = [];

    public function __construct(
        private readonly ?PDO $connection = null,
        private readonly ?DateTimeImmutable $now = null,
        private readonly string $callType = self::CALL_TYPE_LLM,
        private readonly bool $public = false,
    ) {
        if (!in_array($callType, [self::CALL_TYPE_LLM, self::CALL_TYPE_EMBEDDING], true)) {
            throw new InvalidArgumentException('Tipo de llamada global de Numa no soportado.');
        }
    }

    public static function forLlm(?PDO $connection = null, ?DateTimeImmutable $now = null): self
    {
        return new self($connection, $now, self::CALL_TYPE_LLM);
    }

    public static function forEmbedding(?PDO $connection = null, ?DateTimeImmutable $now = null): self
    {
        return new self($connection, $now, self::CALL_TYPE_EMBEDDING);
    }

    public static function forPublicLlm(?PDO $connection = null, ?DateTimeImmutable $now = null): self
    {
        return new self($connection, $now, self::CALL_TYPE_LLM, true);
    }

    public static function forPublicEmbedding(?PDO $connection = null, ?DateTimeImmutable $now = null): self
    {
        return new self($connection, $now, self::CALL_TYPE_EMBEDDING, true);
    }

    /**
     * @return array{
     *   daily_calls:int,daily_calls_limit:int,
     *   monthly_calls:int,monthly_calls_limit:int,
     *   daily_tokens:int,daily_tokens_limit:int,
     *   monthly_tokens:int,monthly_tokens_limit:int
     * }
     */
    public function estadoGlobal(): array
    {
        $today = $this->today();
        [$monthStart, $nextMonthStart] = $this->monthRange($today);

        return [
            'daily_calls' => $this->llamadasDia($today),
            'daily_calls_limit' => $this->dailyCallLimit(),
            'monthly_calls' => $this->llamadasMes($monthStart, $nextMonthStart),
            'monthly_calls_limit' => $this->monthlyCallLimit(),
            'daily_tokens' => $this->tokensDia($today),
            'daily_tokens_limit' => $this->dailyTokenLimit(),
            'monthly_tokens' => $this->tokensMes($monthStart, $nextMonthStart),
            'monthly_tokens_limit' => $this->monthlyTokenLimit(),
        ];
    }

    /**
     * Comprueba atomicamente los limites globales antes de una llamada real al
     * proveedor y, si procede, incrementa el contador de llamadas del dia. No
     * llama al proveedor: es responsabilidad del orquestador.
     */
    public function iniciarLlamada(): void
    {
        $reservation = $this->prepararLlamada();
        $this->confirmarLlamada($reservation);
    }

    /** @return array{input:int,output:int} */
    public function prepararLlamada(): array
    {
        return $this->estimatedTokens();
    }

    public function confirmarLlamada(mixed $reservation): void
    {
        if (!is_array($reservation)
            || !isset($reservation['input'], $reservation['output'])
            || !is_int($reservation['input'])
            || !is_int($reservation['output'])
            || $reservation['input'] < 0
            || $reservation['output'] < 0
        ) {
            throw new NumaGlobalLimiteAlcanzado('NUMA_GLOBAL_LIMIT_REACHED');
        }

        $this->incrementarLlamadaSiCabe($reservation['input'], $reservation['output']);
    }

    public function cancelarLlamada(mixed $reservation): void
    {
        if (!is_array($reservation) || $this->pendingTokenReservations === []) {
            return;
        }

        $last = $this->pendingTokenReservations[array_key_last($this->pendingTokenReservations)];
        if (($last['input'] ?? null) === ($reservation['input'] ?? null)
            && ($last['output'] ?? null) === ($reservation['output'] ?? null)
        ) {
            array_pop($this->pendingTokenReservations);
        }
    }

    public function conexionTransaccional(): PDO
    {
        return $this->db();
    }

    private function incrementarLlamadaSiCabe(int $reservedInputTokens, int $reservedOutputTokens): void
    {
        $db = $this->db();
        $started = !$db->inTransaction();

        if ($started) {
            $db->beginTransaction();
        }

        try {
            $today = $this->today();
            [$monthStart, $nextMonthStart] = $this->monthRange($today);
            $this->ensureRow($monthStart);
            $this->lockMonthRange($monthStart, $nextMonthStart);

            if ($today !== $monthStart) {
                $this->ensureRow($today);
            }

            [$dailyCalls, $dailyTokens] = $this->rowTotals($today);
            $monthlyCalls = $this->llamadasMes($monthStart, $nextMonthStart);
            $monthlyTokens = $this->tokensMes($monthStart, $nextMonthStart);
            $reservedTokens = $reservedInputTokens + $reservedOutputTokens;

            $limiteAlcanzado = $dailyCalls + 1 > $this->dailyCallLimit()
                || $monthlyCalls + 1 > $this->monthlyCallLimit()
                || $dailyTokens + $reservedTokens > $this->dailyTokenLimit()
                || $monthlyTokens + $reservedTokens > $this->monthlyTokenLimit();

            if ($limiteAlcanzado) {
                if ($started) {
                    $db->commit();
                }

                throw new NumaGlobalLimiteAlcanzado('NUMA_GLOBAL_LIMIT_REACHED');
            }

            if ($this->public
                && ($this->llamadasPublicasDia($today) + 1 > $this->publicDailyCallLimit()
                    || $this->llamadasPublicasMes($monthStart, $nextMonthStart) + 1 > $this->publicMonthlyCallLimit())
            ) {
                if ($started) {
                    $db->commit();
                }

                throw new NumaGlobalLimiteAlcanzado('NUMA_PUBLIC_GLOBAL_LIMIT_REACHED');
            }

            $stmt = $db->prepare(
                'UPDATE numa_uso_proveedor
                 SET llamadas = llamadas + 1,
                     llamadas_publicas = llamadas_publicas + :publicas,
                     input_tokens = input_tokens + :input,
                     output_tokens = output_tokens + :output
                 WHERE fecha = :fecha'
            );
            $stmt->execute([
                ':publicas' => $this->public ? 1 : 0,
                ':input' => $reservedInputTokens,
                ':output' => $reservedOutputTokens,
                ':fecha' => $today,
            ]);

            $this->pendingTokenReservations[] = [
                'fecha' => $today,
                'input' => $reservedInputTokens,
                'output' => $reservedOutputTokens,
            ];

            if ($started) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if ($started && $db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Reemplaza la reserva conservadora de tokens por el uso fiable informado.
     * Si no hay uso fiable o la llamada fallo antes de informar usage, se
     * mantiene la reserva conservadora registrada antes del transporte externo.
     */
    public function registrarTokens(NumaTokenUsage $usage): void
    {
        if (!$usage->hasReliableTokens()) {
            return;
        }

        $reservation = array_pop($this->pendingTokenReservations);
        $today = $reservation['fecha'] ?? $this->today();
        $reservedInput = $reservation['input'] ?? 0;
        $reservedOutput = $reservation['output'] ?? 0;
        [$actualInput, $actualOutput] = $this->actualTokens($usage, $reservedInput, $reservedOutput);

        $this->ensureRow($today);

        $stmt = $this->db()->prepare(
            'UPDATE numa_uso_proveedor
             SET input_tokens = input_tokens + :input_delta,
                 output_tokens = output_tokens + :output_delta
             WHERE fecha = :fecha'
        );
        $stmt->execute([
            ':input_delta' => $actualInput - $reservedInput,
            ':output_delta' => $actualOutput - $reservedOutput,
            ':fecha' => $today,
        ]);
    }

    public function llamadasDia(?string $fecha = null): int
    {
        $stmt = $this->db()->prepare(
            'SELECT COALESCE(llamadas, 0) FROM numa_uso_proveedor WHERE fecha = :fecha'
        );
        $stmt->execute([':fecha' => $fecha ?? $this->today()]);
        $value = $stmt->fetchColumn();

        return is_numeric($value) ? (int) $value : 0;
    }

    public function llamadasMes(?string $monthStart = null, ?string $nextMonthStart = null): int
    {
        $today = $this->today();
        [$defaultStart, $defaultNext] = $this->monthRange($today);

        $stmt = $this->db()->prepare(
            'SELECT COALESCE(SUM(llamadas), 0)
             FROM numa_uso_proveedor
             WHERE fecha >= :month_start AND fecha < :next_month_start'
        );
        $stmt->execute([
            ':month_start' => $monthStart ?? $defaultStart,
            ':next_month_start' => $nextMonthStart ?? $defaultNext,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function llamadasPublicasDia(?string $fecha = null): int
    {
        $stmt = $this->db()->prepare(
            'SELECT COALESCE(llamadas_publicas, 0) FROM numa_uso_proveedor WHERE fecha = :fecha'
        );
        $stmt->execute([':fecha' => $fecha ?? $this->today()]);
        $value = $stmt->fetchColumn();

        return is_numeric($value) ? (int) $value : 0;
    }

    public function llamadasPublicasMes(?string $monthStart = null, ?string $nextMonthStart = null): int
    {
        $today = $this->today();
        [$defaultStart, $defaultNext] = $this->monthRange($today);
        $stmt = $this->db()->prepare(
            'SELECT COALESCE(SUM(llamadas_publicas), 0) FROM numa_uso_proveedor
             WHERE fecha >= :month_start AND fecha < :next_month_start'
        );
        $stmt->execute([
            ':month_start' => $monthStart ?? $defaultStart,
            ':next_month_start' => $nextMonthStart ?? $defaultNext,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function tokensDia(?string $fecha = null): int
    {
        $stmt = $this->db()->prepare(
            'SELECT COALESCE(input_tokens + output_tokens, 0)
             FROM numa_uso_proveedor
             WHERE fecha = :fecha'
        );
        $stmt->execute([':fecha' => $fecha ?? $this->today()]);
        $value = $stmt->fetchColumn();

        return is_numeric($value) ? (int) $value : 0;
    }

    public function tokensMes(?string $monthStart = null, ?string $nextMonthStart = null): int
    {
        $today = $this->today();
        [$defaultStart, $defaultNext] = $this->monthRange($today);

        $stmt = $this->db()->prepare(
            'SELECT COALESCE(SUM(input_tokens + output_tokens), 0)
             FROM numa_uso_proveedor
             WHERE fecha >= :month_start AND fecha < :next_month_start'
        );
        $stmt->execute([
            ':month_start' => $monthStart ?? $defaultStart,
            ':next_month_start' => $nextMonthStart ?? $defaultNext,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function db(): PDO
    {
        return $this->connection ?? Database::getConnection();
    }

    private function dailyCallLimit(): int
    {
        return max(0, bh_env_int('NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT', 100));
    }

    private function monthlyCallLimit(): int
    {
        return max(0, bh_env_int('NUMA_GLOBAL_MONTHLY_PROVIDER_CALL_LIMIT', 1000));
    }

    private function publicDailyCallLimit(): int
    {
        return max(0, bh_env_int('NUMA_PUBLIC_GLOBAL_DAILY_CALL_LIMIT', 40));
    }

    private function publicMonthlyCallLimit(): int
    {
        return max(0, bh_env_int('NUMA_PUBLIC_GLOBAL_MONTHLY_CALL_LIMIT', 400));
    }

    private function dailyTokenLimit(): int
    {
        return max(0, bh_env_int('NUMA_GLOBAL_DAILY_TOKEN_LIMIT', 50000));
    }

    private function monthlyTokenLimit(): int
    {
        return max(0, bh_env_int('NUMA_GLOBAL_MONTHLY_TOKEN_LIMIT', 300000));
    }

    private function ensureRow(string $fecha): void
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO numa_uso_proveedor (fecha, llamadas, llamadas_publicas, input_tokens, output_tokens)
             VALUES (:fecha, 0, 0, 0, 0)
             ON DUPLICATE KEY UPDATE fecha = fecha'
        );
        $stmt->execute([':fecha' => $fecha]);
    }

    private function lockMonthRange(string $monthStart, string $nextMonthStart): void
    {
        $stmt = $this->db()->prepare(
            'SELECT fecha
             FROM numa_uso_proveedor
             WHERE fecha >= :month_start AND fecha < :next_month_start
             ORDER BY fecha
             FOR UPDATE'
        );
        $stmt->execute([
            ':month_start' => $monthStart,
            ':next_month_start' => $nextMonthStart,
        ]);
        $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @return array{0:int,1:int} [llamadas, tokens (input + output)]
     */
    private function rowTotals(string $fecha): array
    {
        $stmt = $this->db()->prepare(
            'SELECT llamadas, input_tokens, output_tokens
             FROM numa_uso_proveedor
             WHERE fecha = :fecha'
        );
        $stmt->execute([':fecha' => $fecha]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return [0, 0];
        }

        return [
            (int) $row['llamadas'],
            (int) $row['input_tokens'] + (int) $row['output_tokens'],
        ];
    }

    private function today(): string
    {
        return $this->now()->format('Y-m-d');
    }

    /**
     * @return array{0:string,1:string}
     */
    private function monthRange(string $date): array
    {
        $start = new DateTimeImmutable(substr($date, 0, 7) . '-01 00:00:00');

        return [$start->format('Y-m-d'), $start->modify('first day of next month')->format('Y-m-d')];
    }

    private function now(): DateTimeImmutable
    {
        return $this->now ?? new DateTimeImmutable('now');
    }

    /** @return array{input:int,output:int} */
    private function estimatedTokens(): array
    {
        if ($this->callType === self::CALL_TYPE_EMBEDDING) {
            return [
                'input' => max(1, $this->embeddingInputTokenEstimate()),
                'output' => 0,
            ];
        }

        return [
            'input' => $this->maxInputTokens(),
            'output' => $this->maxOutputTokens(),
        ];
    }

    /** @return array{0:int,1:int} */
    private function actualTokens(NumaTokenUsage $usage, int $reservedInput, int $reservedOutput): array
    {
        $billableTokens = $usage->billableTokens();

        if ($billableTokens !== null) {
            return [$billableTokens, 0];
        }

        return [
            $usage->inputTokens() ?? $reservedInput,
            $usage->outputTokens() ?? $reservedOutput,
        ];
    }

    private function maxInputTokens(): int
    {
        return max(1, bh_env_int('NUMA_MAX_INPUT_TOKENS', 5000));
    }

    private function maxOutputTokens(): int
    {
        return max(1, min(bh_env_int('NUMA_MAX_OUTPUT_TOKENS', 220), 220));
    }

    private function embeddingInputTokenEstimate(): int
    {
        return self::EMBEDDING_MAX_INPUT_TOKENS;
    }
}
