<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SessionTimeoutTest extends TestCase
{
    public function testTimeoutDesactivadoNoExpiraSesion(): void
    {
        self::assertFalse(\bh_session_idle_expired(1000, 0, 5000));
        self::assertFalse(\bh_session_idle_expired(1000, -1, 5000));
    }

    public function testSesionNoExpiraDentroDeLaVentana(): void
    {
        self::assertFalse(\bh_session_idle_expired(920, 100, 1000));
        self::assertFalse(\bh_session_idle_expired(900, 100, 1000));
    }

    public function testSesionExpiraFueraDeLaVentana(): void
    {
        self::assertTrue(\bh_session_idle_expired(899, 100, 1000));
    }
}
