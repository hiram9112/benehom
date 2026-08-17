<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/NumaUso.php';

final class NumaPublicUso
{
    public function __construct(
        private readonly ?PDO $connection = null,
        private readonly ?DateTimeImmutable $now = null,
    ) {
    }

    /**
     * @return array{daily_used:int,daily_limit:int,daily_remaining:int,monthly_used:int,monthly_limit:int,monthly_remaining:int}
     */
    public function estado(string $visitorHash): array
    {
        $this->expirarReservasVencidas();

        $dailyLimit = $this->dailyLimit();
        $monthlyLimit = $this->monthlyLimit();
        $today = $this->today();
        [$monthStart, $nextMonthStart] = $this->monthRange($today);
        $dailyUsed = $this->confirmedDay($visitorHash, $today);
        $monthlyUsed = $this->confirmedMonth($visitorHash, $monthStart, $nextMonthStart);
        $dailyPending = $this->pendingDay($visitorHash, $today);
        $monthlyPending = $this->pendingMonth($visitorHash, $monthStart, $nextMonthStart);

        return [
            'daily_used' => $dailyUsed,
            'daily_limit' => $dailyLimit,
            'daily_remaining' => max(0, $dailyLimit - $dailyUsed - $dailyPending),
            'monthly_used' => $monthlyUsed,
            'monthly_limit' => $monthlyLimit,
            'monthly_remaining' => max(0, $monthlyLimit - $monthlyUsed - $monthlyPending),
        ];
    }

    public function reservar(string $visitorHash): string
    {
        $db = $this->db();
        $retries = 0;

        while (true) {
            $started = !$db->inTransaction();
            if ($started) {
                $db->beginTransaction();
            }

            try {
                $this->expirarReservasVencidas(false);
                $today = $this->today();
                [$monthStart, $nextMonthStart] = $this->monthRange($today);
                $this->ensureUsageRow($visitorHash, $monthStart);
                if ($today !== $monthStart) {
                    $this->ensureUsageRow($visitorHash, $today);
                }

                $usageRows = $this->lockUsageMonth($visitorHash, $monthStart, $nextMonthStart);
                $reservationRows = $this->lockReservationsMonth($visitorHash, $monthStart, $nextMonthStart);
                $now = $this->now()->format('Y-m-d H:i:s');
                $dailyUsed = 0;
                $monthlyUsed = 0;

                foreach ($usageRows as $row) {
                    $confirmed = (int) $row['cantidad_confirmada'];
                    $monthlyUsed += $confirmed;
                    if ((string) $row['fecha'] === $today) {
                        $dailyUsed += $confirmed;
                    }
                }

                $dailyPending = 0;
                $monthlyPending = 0;
                foreach ($reservationRows as $row) {
                    if ((string) $row['estado'] !== 'pendiente' || (string) $row['expires_at'] <= $now) {
                        continue;
                    }

                    $monthlyPending++;
                    if ((string) $row['fecha'] === $today) {
                        $dailyPending++;
                    }
                }

                if ($dailyUsed + $dailyPending >= $this->dailyLimit()) {
                    throw new NumaUsoLimiteAlcanzado('NUMA_DAILY_LIMIT_REACHED');
                }
                if ($monthlyUsed + $monthlyPending >= $this->monthlyLimit()) {
                    throw new NumaUsoLimiteAlcanzado('NUMA_MONTHLY_LIMIT_REACHED');
                }

                $id = $this->uuidV4();
                $statement = $db->prepare(
                    'INSERT INTO numa_reservas_publicas (id, visitante_hash, fecha, estado, expires_at)
                     VALUES (:id, :visitante_hash, :fecha, :estado, :expires_at)'
                );
                $statement->execute([
                    ':id' => $id,
                    ':visitante_hash' => $visitorHash,
                    ':fecha' => $today,
                    ':estado' => 'pendiente',
                    ':expires_at' => $this->now()->modify('+' . $this->reservationTtl() . ' seconds')->format('Y-m-d H:i:s'),
                ]);

                if ($started) {
                    $db->commit();
                }

                return $id;
            } catch (Throwable $exception) {
                if ($started && $db->inTransaction()) {
                    $db->rollBack();
                }
                if ($started && $retries === 0 && $this->isDeadlock($exception)) {
                    $retries++;
                    continue;
                }
                throw $exception;
            }
        }
    }

    public function confirmar(string $reservationId): bool
    {
        return $this->closeReservation($reservationId, true);
    }

    public function revertir(string $reservationId): bool
    {
        return $this->closeReservation($reservationId, false);
    }

    public function expirarReservasVencidas(bool $ownTransaction = true): int
    {
        $db = $this->db();
        $started = $ownTransaction && !$db->inTransaction();
        if ($started) {
            $db->beginTransaction();
        }

        try {
            $statement = $db->prepare(
                "UPDATE numa_reservas_publicas SET estado = 'expirada'
                 WHERE estado = 'pendiente' AND expires_at <= :now"
            );
            $statement->execute([':now' => $this->now()->format('Y-m-d H:i:s')]);
            if ($started) {
                $db->commit();
            }

            return $statement->rowCount();
        } catch (Throwable $exception) {
            if ($started && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function conexionTransaccional(): PDO
    {
        return $this->db();
    }

    private function closeReservation(string $reservationId, bool $confirm): bool
    {
        $db = $this->db();
        $started = !$db->inTransaction();
        if ($started) {
            $db->beginTransaction();
        }

        try {
            $reservation = $this->lockReservation($reservationId);
            if ($reservation === null || $reservation['estado'] !== 'pendiente') {
                if ($started) {
                    $db->commit();
                }
                return false;
            }
            if (strtotime((string) $reservation['expires_at']) <= $this->now()->getTimestamp()) {
                $this->markReservation($reservationId, 'expirada');
                if ($started) {
                    $db->commit();
                }
                return false;
            }

            if ($confirm) {
                $this->ensureUsageRow((string) $reservation['visitante_hash'], (string) $reservation['fecha']);
                $this->lockUsageDay((string) $reservation['visitante_hash'], (string) $reservation['fecha']);
                $statement = $db->prepare(
                    'UPDATE numa_uso_publico
                     SET cantidad_confirmada = cantidad_confirmada + 1
                     WHERE visitante_hash = :visitante_hash AND fecha = :fecha'
                );
                $statement->execute([
                    ':visitante_hash' => $reservation['visitante_hash'],
                    ':fecha' => $reservation['fecha'],
                ]);
                $this->markReservation($reservationId, 'confirmada');
            } else {
                $this->markReservation($reservationId, 'revertida');
            }

            if ($started) {
                $db->commit();
            }
            return true;
        } catch (Throwable $exception) {
            if ($started && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function confirmedDay(string $visitorHash, string $date): int
    {
        $statement = $this->db()->prepare(
            'SELECT COALESCE(SUM(cantidad_confirmada), 0) FROM numa_uso_publico
             WHERE visitante_hash = :visitante_hash AND fecha = :fecha'
        );
        $statement->execute([':visitante_hash' => $visitorHash, ':fecha' => $date]);
        return (int) $statement->fetchColumn();
    }

    private function confirmedMonth(string $visitorHash, string $start, string $next): int
    {
        $statement = $this->db()->prepare(
            'SELECT COALESCE(SUM(cantidad_confirmada), 0) FROM numa_uso_publico
             WHERE visitante_hash = :visitante_hash AND fecha >= :start AND fecha < :next'
        );
        $statement->execute([':visitante_hash' => $visitorHash, ':start' => $start, ':next' => $next]);
        return (int) $statement->fetchColumn();
    }

    private function pendingDay(string $visitorHash, string $date): int
    {
        return $this->pendingCount($visitorHash, 'AND fecha = :start', [':start' => $date]);
    }

    private function pendingMonth(string $visitorHash, string $start, string $next): int
    {
        return $this->pendingCount($visitorHash, 'AND fecha >= :start AND fecha < :next', [':start' => $start, ':next' => $next]);
    }

    /** @param array<string, string> $period */
    private function pendingCount(string $visitorHash, string $periodSql, array $period): int
    {
        $statement = $this->db()->prepare(
            "SELECT COUNT(*) FROM numa_reservas_publicas
             WHERE visitante_hash = :visitante_hash $periodSql
               AND estado = 'pendiente' AND expires_at > :now"
        );
        $statement->execute([
            ':visitante_hash' => $visitorHash,
            ':now' => $this->now()->format('Y-m-d H:i:s'),
            ...$period,
        ]);
        return (int) $statement->fetchColumn();
    }

    private function ensureUsageRow(string $visitorHash, string $date): void
    {
        $statement = $this->db()->prepare(
            'INSERT INTO numa_uso_publico (visitante_hash, fecha, cantidad_confirmada)
             VALUES (:visitante_hash, :fecha, 0)
             ON DUPLICATE KEY UPDATE visitante_hash = visitante_hash'
        );
        $statement->execute([':visitante_hash' => $visitorHash, ':fecha' => $date]);
    }

    /** @return list<array{fecha:string,cantidad_confirmada:int|string}> */
    private function lockUsageMonth(string $visitorHash, string $start, string $next): array
    {
        $statement = $this->db()->prepare(
            'SELECT fecha, cantidad_confirmada FROM numa_uso_publico
             WHERE visitante_hash = :visitante_hash AND fecha >= :start AND fecha < :next
             ORDER BY fecha FOR UPDATE'
        );
        $statement->execute([':visitante_hash' => $visitorHash, ':start' => $start, ':next' => $next]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function lockUsageDay(string $visitorHash, string $date): void
    {
        $statement = $this->db()->prepare(
            'SELECT id FROM numa_uso_publico WHERE visitante_hash = :visitante_hash AND fecha = :fecha FOR UPDATE'
        );
        $statement->execute([':visitante_hash' => $visitorHash, ':fecha' => $date]);
        $statement->fetchColumn();
    }

    /** @return list<array{fecha:string,estado:string,expires_at:string}> */
    private function lockReservationsMonth(string $visitorHash, string $start, string $next): array
    {
        $statement = $this->db()->prepare(
            'SELECT fecha, estado, expires_at FROM numa_reservas_publicas
             WHERE visitante_hash = :visitante_hash AND fecha >= :start AND fecha < :next
             ORDER BY fecha, id FOR UPDATE'
        );
        $statement->execute([':visitante_hash' => $visitorHash, ':start' => $start, ':next' => $next]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{visitante_hash:string,fecha:string,estado:string,expires_at:string}|null */
    private function lockReservation(string $id): ?array
    {
        $statement = $this->db()->prepare(
            'SELECT visitante_hash, fecha, estado, expires_at FROM numa_reservas_publicas WHERE id = :id FOR UPDATE'
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function markReservation(string $id, string $state): void
    {
        $statement = $this->db()->prepare('UPDATE numa_reservas_publicas SET estado = :estado WHERE id = :id');
        $statement->execute([':estado' => $state, ':id' => $id]);
    }

    private function dailyLimit(): int
    {
        return max(0, bh_env_int('NUMA_PUBLIC_DAILY_LIMIT', 5));
    }

    private function monthlyLimit(): int
    {
        return max(0, bh_env_int('NUMA_PUBLIC_MONTHLY_LIMIT', 20));
    }

    private function reservationTtl(): int
    {
        return max(1, bh_env_int('NUMA_RESERVATION_TTL_SECONDS', 120));
    }

    private function db(): PDO
    {
        return $this->connection ?? Database::getConnection();
    }

    private function today(): string
    {
        return $this->now()->format('Y-m-d');
    }

    /** @return array{0:string,1:string} */
    private function monthRange(string $date): array
    {
        $start = new DateTimeImmutable(substr($date, 0, 7) . '-01 00:00:00');
        return [$start->format('Y-m-d'), $start->modify('first day of next month')->format('Y-m-d')];
    }

    private function now(): DateTimeImmutable
    {
        return $this->now ?? new DateTimeImmutable('now');
    }

    private function isDeadlock(Throwable $exception): bool
    {
        return $exception instanceof PDOException && (int) ($exception->errorInfo[1] ?? 0) === 1213;
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
