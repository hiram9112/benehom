<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class NumaUsoLimiteAlcanzado extends RuntimeException
{
    public function __construct(private readonly string $limitCode)
    {
        parent::__construct($limitCode);
    }

    public function limitCode(): string
    {
        return $this->limitCode;
    }
}

class NumaUso
{
    public function __construct(
        private readonly ?PDO $connection = null,
        private readonly ?DateTimeImmutable $now = null,
    ) {
    }

    /**
     * @return array{daily_used:int,daily_limit:int,daily_remaining:int,monthly_used:int,monthly_limit:int,monthly_remaining:int}
     */
    public function estado(int $usuarioId): array
    {
        $this->expirarReservasVencidas();

        $dailyLimit = $this->dailyLimit();
        $monthlyLimit = $this->monthlyLimit();
        $today = $this->today();
        [$monthStart, $nextMonthStart] = $this->monthRange($today);

        $dailyUsed = $this->consultasConfirmadasDia($usuarioId, $today);
        $monthlyUsed = $this->consultasConfirmadasMes($usuarioId, $monthStart, $nextMonthStart);
        $dailyPending = $this->reservasPendientesActivasDia($usuarioId, $today);
        $monthlyPending = $this->reservasPendientesActivasMes($usuarioId, $monthStart, $nextMonthStart);

        return [
            'daily_used' => $dailyUsed,
            'daily_limit' => $dailyLimit,
            'daily_remaining' => max(0, $dailyLimit - $dailyUsed - $dailyPending),
            'monthly_used' => $monthlyUsed,
            'monthly_limit' => $monthlyLimit,
            'monthly_remaining' => max(0, $monthlyLimit - $monthlyUsed - $monthlyPending),
        ];
    }

    public function reservar(int $usuarioId): string
    {
        $db = $this->db();
        $started = !$db->inTransaction();

        if ($started) {
            $db->beginTransaction();
        }

        try {
            $this->expirarReservasVencidas(false);

            $today = $this->today();
            [$monthStart, $nextMonthStart] = $this->monthRange($today);
            $this->ensureUsoDia($usuarioId, $today);
            $this->lockUsoDia($usuarioId, $today);

            $dailyUsed = $this->consultasConfirmadasDia($usuarioId, $today);
            $monthlyUsed = $this->consultasConfirmadasMes($usuarioId, $monthStart, $nextMonthStart);
            $dailyPending = $this->reservasPendientesActivasDia($usuarioId, $today);
            $monthlyPending = $this->reservasPendientesActivasMes($usuarioId, $monthStart, $nextMonthStart);

            if (($dailyUsed + $dailyPending) >= $this->dailyLimit()) {
                throw new NumaUsoLimiteAlcanzado('NUMA_DAILY_LIMIT_REACHED');
            }

            if (($monthlyUsed + $monthlyPending) >= $this->monthlyLimit()) {
                throw new NumaUsoLimiteAlcanzado('NUMA_MONTHLY_LIMIT_REACHED');
            }

            $reservationId = $this->uuidV4();
            $expiresAt = $this->now()->modify('+' . $this->reservationTtl() . ' seconds')->format('Y-m-d H:i:s');

            $stmt = $db->prepare(
                'INSERT INTO numa_reservas (id, usuario_id, fecha, estado, expires_at)
                 VALUES (:id, :usuario_id, :fecha, :estado, :expires_at)'
            );
            $stmt->execute([
                ':id' => $reservationId,
                ':usuario_id' => $usuarioId,
                ':fecha' => $today,
                ':estado' => 'pendiente',
                ':expires_at' => $expiresAt,
            ]);

            if ($started) {
                $db->commit();
            }

            return $reservationId;
        } catch (Throwable $e) {
            if ($started && $db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    public function confirmar(string $reservaId): bool
    {
        $db = $this->db();
        $started = !$db->inTransaction();

        if ($started) {
            $db->beginTransaction();
        }

        try {
            $reserva = $this->lockReserva($reservaId);

            if ($reserva === null || $reserva['estado'] !== 'pendiente') {
                if ($started) {
                    $db->commit();
                }

                return false;
            }

            if (strtotime((string) $reserva['expires_at']) <= $this->now()->getTimestamp()) {
                $this->marcarReserva($reservaId, 'expirada');

                if ($started) {
                    $db->commit();
                }

                return false;
            }

            $usuarioId = (int) $reserva['usuario_id'];
            $fecha = (string) $reserva['fecha'];
            $this->ensureUsoDia($usuarioId, $fecha);
            $this->lockUsoDia($usuarioId, $fecha);

            $stmt = $db->prepare(
                'UPDATE numa_uso
                 SET cantidad_confirmada = cantidad_confirmada + 1
                 WHERE usuario_id = :usuario_id AND fecha = :fecha'
            );
            $stmt->execute([':usuario_id' => $usuarioId, ':fecha' => $fecha]);
            $this->marcarReserva($reservaId, 'confirmada');

            if ($started) {
                $db->commit();
            }

            return true;
        } catch (Throwable $e) {
            if ($started && $db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    public function revertir(string $reservaId): bool
    {
        $db = $this->db();
        $started = !$db->inTransaction();

        if ($started) {
            $db->beginTransaction();
        }

        try {
            $reserva = $this->lockReserva($reservaId);

            if ($reserva === null || $reserva['estado'] !== 'pendiente') {
                if ($started) {
                    $db->commit();
                }

                return false;
            }

            $this->marcarReserva($reservaId, 'revertida');

            if ($started) {
                $db->commit();
            }

            return true;
        } catch (Throwable $e) {
            if ($started && $db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    public function expirarReservasVencidas(bool $ownTransaction = true): int
    {
        $db = $this->db();
        $started = $ownTransaction && !$db->inTransaction();

        if ($started) {
            $db->beginTransaction();
        }

        try {
            $stmt = $db->prepare(
                "UPDATE numa_reservas
                 SET estado = 'expirada'
                 WHERE estado = 'pendiente' AND expires_at <= :now"
            );
            $stmt->execute([':now' => $this->now()->format('Y-m-d H:i:s')]);
            $count = $stmt->rowCount();

            if ($started) {
                $db->commit();
            }

            return $count;
        } catch (Throwable $e) {
            if ($started && $db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    public function consultasConfirmadasDia(int $usuarioId, ?string $fecha = null): int
    {
        $stmt = $this->db()->prepare(
            'SELECT COALESCE(SUM(cantidad_confirmada), 0)
             FROM numa_uso
             WHERE usuario_id = :usuario_id AND fecha = :fecha'
        );
        $stmt->execute([':usuario_id' => $usuarioId, ':fecha' => $fecha ?? $this->today()]);

        return (int) $stmt->fetchColumn();
    }

    public function consultasConfirmadasMes(int $usuarioId, ?string $monthStart = null, ?string $nextMonthStart = null): int
    {
        $today = $this->today();
        [$defaultStart, $defaultNext] = $this->monthRange($today);

        $stmt = $this->db()->prepare(
            'SELECT COALESCE(SUM(cantidad_confirmada), 0)
             FROM numa_uso
             WHERE usuario_id = :usuario_id AND fecha >= :month_start AND fecha < :next_month_start'
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':month_start' => $monthStart ?? $defaultStart,
            ':next_month_start' => $nextMonthStart ?? $defaultNext,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function reservasPendientesActivasDia(int $usuarioId, ?string $fecha = null): int
    {
        $stmt = $this->db()->prepare(
            "SELECT COUNT(*)
             FROM numa_reservas
             WHERE usuario_id = :usuario_id
               AND fecha = :fecha
               AND estado = 'pendiente'
               AND expires_at > :now"
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':fecha' => $fecha ?? $this->today(),
            ':now' => $this->now()->format('Y-m-d H:i:s'),
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function reservasPendientesActivasMes(int $usuarioId, ?string $monthStart = null, ?string $nextMonthStart = null): int
    {
        $today = $this->today();
        [$defaultStart, $defaultNext] = $this->monthRange($today);

        $stmt = $this->db()->prepare(
            "SELECT COUNT(*)
             FROM numa_reservas
             WHERE usuario_id = :usuario_id
               AND fecha >= :month_start
               AND fecha < :next_month_start
               AND estado = 'pendiente'
               AND expires_at > :now"
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':month_start' => $monthStart ?? $defaultStart,
            ':next_month_start' => $nextMonthStart ?? $defaultNext,
            ':now' => $this->now()->format('Y-m-d H:i:s'),
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function db(): PDO
    {
        return $this->connection ?? Database::getConnection();
    }

    private function dailyLimit(): int
    {
        return max(0, bh_env_int('NUMA_DAILY_LIMIT', 5));
    }

    private function monthlyLimit(): int
    {
        return max(0, bh_env_int('NUMA_MONTHLY_LIMIT', 20));
    }

    private function reservationTtl(): int
    {
        return max(1, bh_env_int('NUMA_RESERVATION_TTL_SECONDS', 120));
    }

    private function ensureUsoDia(int $usuarioId, string $fecha): void
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO numa_uso (usuario_id, fecha, cantidad_confirmada)
             VALUES (:usuario_id, :fecha, 0)
             ON DUPLICATE KEY UPDATE usuario_id = usuario_id'
        );
        $stmt->execute([':usuario_id' => $usuarioId, ':fecha' => $fecha]);
    }

    private function lockUsoDia(int $usuarioId, string $fecha): void
    {
        $stmt = $this->db()->prepare(
            'SELECT id FROM numa_uso WHERE usuario_id = :usuario_id AND fecha = :fecha FOR UPDATE'
        );
        $stmt->execute([':usuario_id' => $usuarioId, ':fecha' => $fecha]);
        $stmt->fetchColumn();
    }

    /**
     * @return array{id:string,usuario_id:int,fecha:string,estado:string,expires_at:string}|null
     */
    private function lockReserva(string $reservaId): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, usuario_id, fecha, estado, expires_at FROM numa_reservas WHERE id = :id FOR UPDATE'
        );
        $stmt->execute([':id' => $reservaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function marcarReserva(string $reservaId, string $estado): void
    {
        $stmt = $this->db()->prepare('UPDATE numa_reservas SET estado = :estado WHERE id = :id');
        $stmt->execute([':estado' => $estado, ':id' => $reservaId]);
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

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20)
        );
    }
}
