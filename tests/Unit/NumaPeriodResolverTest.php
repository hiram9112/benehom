<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/NumaFinancialTools.php';

final class NumaPeriodResolverTest extends TestCase
{
    public function testResuelvePeriodosRelativosConLaZonaDeNegocio(): void
    {
        $resolver = new \NumaPeriodResolver(
            new DateTimeImmutable('2026-01-01 00:30:00', new DateTimeZone('UTC'))
        );

        self::assertSame('2026-01-01', $resolver->currentDate());
        self::assertSame(['inicio' => '2026-01-01', 'fin' => '2026-01-31'], $resolver->resolve('mes_actual'));
        self::assertSame(['inicio' => '2025-12-01', 'fin' => '2025-12-31'], $resolver->resolve('mes_anterior'));
        self::assertSame(['inicio' => '2026-01-01', 'fin' => '2026-12-31'], $resolver->resolve('anio_actual'));
        self::assertSame(['inicio' => '2025-01-01', 'fin' => '2025-12-31'], $resolver->resolve('anio_anterior'));
        self::assertSame(['inicio' => '2026-07-01', 'fin' => '2026-07-31'], $resolver->resolve('julio'));
    }

    public function testNormalizaRangosExplicitosAMesesNaturales(): void
    {
        $resolver = new \NumaPeriodResolver();

        self::assertSame(
            ['inicio' => '2026-02-01', 'fin' => '2026-04-30'],
            $resolver->normalize('2026-02-18', '2026-04-02')
        );
    }

    public function testResuelveMesAnteriorDesdeElPeriodoDeSeguimientoYNoDesdeGemini(): void
    {
        $resolver = new \NumaPeriodResolver(
            new DateTimeImmutable('2026-08-12', new DateTimeZone('Europe/Madrid'))
        );

        self::assertSame(
            ['inicio' => '2026-06-01', 'fin' => '2026-06-30'],
            $resolver->resolveForFollowUp('mes_anterior', [
                'start' => '2026-07-01',
                'end' => '2026-07-31',
            ])
        );
    }
}
