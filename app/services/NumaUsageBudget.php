<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/NumaUso.php';
require_once __DIR__ . '/../models/NumaPublicUso.php';

interface NumaUsageBudgetInterface
{
    /** @return array{daily_used:int,daily_limit:int,daily_remaining:int,monthly_used:int,monthly_limit:int,monthly_remaining:int} */
    public function estado(): array;

    public function reservar(): string;

    public function confirmar(string $reservationId): bool;

    public function revertir(string $reservationId): bool;

    public function conexionTransaccional(): PDO;
}

final class NumaPrivateUsageBudget implements NumaUsageBudgetInterface
{
    public function __construct(private readonly NumaUso $usage, private readonly int $userId)
    {
    }

    public function estado(): array
    {
        return $this->usage->estado($this->userId);
    }

    public function reservar(): string
    {
        return $this->usage->reservar($this->userId);
    }

    public function confirmar(string $reservationId): bool
    {
        return $this->usage->confirmar($reservationId);
    }

    public function revertir(string $reservationId): bool
    {
        return $this->usage->revertir($reservationId);
    }

    public function conexionTransaccional(): PDO
    {
        return $this->usage->conexionTransaccional();
    }
}

final class NumaPublicUsageBudget implements NumaUsageBudgetInterface
{
    public function __construct(private readonly NumaPublicUso $usage, private readonly string $visitorHash)
    {
    }

    public function estado(): array
    {
        return $this->usage->estado($this->visitorHash);
    }

    public function reservar(): string
    {
        return $this->usage->reservar($this->visitorHash);
    }

    public function confirmar(string $reservationId): bool
    {
        return $this->usage->confirmar($reservationId);
    }

    public function revertir(string $reservationId): bool
    {
        return $this->usage->revertir($reservationId);
    }

    public function conexionTransaccional(): PDO
    {
        return $this->usage->conexionTransaccional();
    }
}
