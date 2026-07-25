<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once dirname(__DIR__) . '/services/NumaProvider.php';
require_once dirname(__DIR__) . '/helpers/utils.php';

final class NumaGlobalLimiteAlcanzado extends RuntimeException
{
}

final class NumaConsumoGlobal implements NumaProviderConsumptionInterface
{
    public function __construct(
        private readonly ?PDO $connection = null,
        private readonly ?DateTimeImmutable $now = null,
    ) {
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
        $db = $this->db();
        $started = !$db->inTransaction();

        if ($started) {
            $db->beginTransaction();
        }

        try {
            $today = $this->today();
            [$monthStart, $nextMonthStart] = $this->monthRange($today);
            $this->ensureRow($today);
            $this->lockRow($today);

            [$dailyCalls, $dailyTokens] = $this->rowTotals($today);
            $monthlyCalls = $this->llamadasMes($monthStart, $nextMonthStart);
            $monthlyTokens = $this->tokensMes($monthStart, $nextMonthStart);

            $limiteAlcanzado = $dailyCalls >= $this->dailyCallLimit()
                || $monthlyCalls >= $this->monthlyCallLimit()
                || $dailyTokens >= $this->dailyTokenLimit()
                || $monthlyTokens >= $this->monthlyTokenLimit();

            if ($limiteAlcanzado) {
                if ($started) {
                    $db->commit();
                }

                throw new NumaGlobalLimiteAlcanzado('NUMA_GLOBAL_LIMIT_REACHED');
            }

            $stmt = $db->prepare(
                'UPDATE numa_uso_proveedor SET llamadas = llamadas + 1 WHERE fecha = :fecha'
            );
            $stmt->execute([':fecha' => $today]);

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
     * Registra los tokens fiables informados por el proveedor tras una llamada.
     * Si el proveedor no reporta tokens fiables no se estima ni se actualiza
     * el contador. El conteo de la llamada se mantiene aunque la llamada haya
     * fallado, ya que iniciarLlamada() lo incremento previamente.
     */
    public function registrarTokens(NumaTokenUsage $usage): void
    {
        $input = $usage->inputTokens();
        $output = $usage->outputTokens();

        if ($input === null && $output === null) {
            return;
        }

        $today = $this->today();
        $this->ensureRow($today);

        $stmt = $this->db()->prepare(
            'UPDATE numa_uso_proveedor
             SET input_tokens = input_tokens + :input,
                 output_tokens = output_tokens + :output
             WHERE fecha = :fecha'
        );
        $stmt->execute([
            ':input' => $input ?? 0,
            ':output' => $output ?? 0,
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
            'INSERT INTO numa_uso_proveedor (fecha, llamadas, input_tokens, output_tokens)
             VALUES (:fecha, 0, 0, 0)
             ON DUPLICATE KEY UPDATE fecha = fecha'
        );
        $stmt->execute([':fecha' => $fecha]);
    }

    private function lockRow(string $fecha): void
    {
        $stmt = $this->db()->prepare(
            'SELECT fecha FROM numa_uso_proveedor WHERE fecha = :fecha FOR UPDATE'
        );
        $stmt->execute([':fecha' => $fecha]);
        $stmt->fetchColumn();
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
}
