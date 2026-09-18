<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/NumaFinancialTools.php';

final class NumaPeriodResolverTest extends TestCase
{
    public function testExponeLaFechaActualConLaZonaDeNegocio(): void
    {
        $resolver = new \NumaPeriodResolver(
            new DateTimeImmutable('2026-01-01 00:30:00', new DateTimeZone('UTC'))
        );

        self::assertSame('2026-01-01', $resolver->currentDate());
    }

    public function testNormalizaRangosExplicitosAMesesNaturales(): void
    {
        $resolver = new \NumaPeriodResolver();

        self::assertSame(
            ['inicio' => '2026-02-01', 'fin' => '2026-04-30'],
            $resolver->normalize('2026-02-18', '2026-04-02')
        );
    }

    public function testConvierteUnMesDeReferenciaEnFechasNaturales(): void
    {
        $resolver = new \NumaPeriodResolver();

        self::assertSame(
            ['inicio' => '2026-02-01', 'fin' => '2026-02-28'],
            $resolver->referencePeriodForMonth('2026-02'),
        );
    }

    public function testRechazaUnMesDeReferenciaInvalido(): void
    {
        $resolver = new \NumaPeriodResolver();

        $this->expectException(\InvalidArgumentException::class);
        $resolver->referencePeriodForMonth('2026-13');
    }

}
