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

    public function testResuelveRangoExplicitoDeMesesConElAnoIndicado(): void
    {
        $resolver = new \NumaPeriodResolver(
            new DateTimeImmutable('2027-08-12', new DateTimeZone('Europe/Madrid'))
        );

        self::assertSame(
            [['inicio' => '2026-01-01', 'fin' => '2026-06-30']],
            $resolver->periodsMentionedInMessage('¿Cuál fue mi promedio mensual de gastos entre enero y junio de 2026?')
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

    public function testResuelveExpresionesRelativasDesdeElMesDeReferenciaDelDashboard(): void
    {
        $resolver = new \NumaPeriodResolver(
            new DateTimeImmutable('2026-09-12', new DateTimeZone('Europe/Madrid'))
        );
        $referencePeriod = $resolver->referencePeriodForMonth('2026-06');
        $reference = ['start' => $referencePeriod['inicio'], 'end' => $referencePeriod['fin']];

        self::assertSame(
            [['inicio' => '2026-06-01', 'fin' => '2026-06-30']],
            $resolver->periodsMentionedInMessage('¿Cuánto gasté este mismo mes?', $reference)
        );
        self::assertSame(
            [['inicio' => '2026-05-01', 'fin' => '2026-05-31']],
            $resolver->periodsMentionedInMessage('¿Y el mes pasado?', $reference)
        );
        self::assertSame(
            [['inicio' => '2026-01-01', 'fin' => '2026-06-30']],
            $resolver->periodsMentionedInMessage('¿Cuál fue el total de los últimos 6 meses?', $reference)
        );
    }

    public function testMantieneLosPeriodosExplicitosYUsaElSistemaSinContexto(): void
    {
        $resolver = new \NumaPeriodResolver(
            new DateTimeImmutable('2026-09-12', new DateTimeZone('Europe/Madrid'))
        );
        $reference = ['start' => '2026-06-01', 'end' => '2026-06-30'];

        self::assertSame(
            [['inicio' => '2026-01-01', 'fin' => '2026-01-31']],
            $resolver->periodsMentionedInMessage('¿Cuánto gasté en enero de 2026?', $reference)
        );
        self::assertSame(
            [['inicio' => '2026-01-01', 'fin' => '2026-01-31']],
            $resolver->periodsMentionedInMessage('¿Cuánto gasté en enero?', $reference)
        );
        self::assertSame(
            [['inicio' => '2026-09-01', 'fin' => '2026-09-30']],
            $resolver->periodsMentionedInMessage('¿Cuánto gasté este mes?')
        );
    }

    public function testRechazaUnMesDeReferenciaInvalido(): void
    {
        $resolver = new \NumaPeriodResolver();

        $this->expectException(\InvalidArgumentException::class);
        $resolver->referencePeriodForMonth('2026-13');
    }

    public function testSoloMarcaReferenciasTemporalesIncompletasComoAmbiguas(): void
    {
        $resolver = new \NumaPeriodResolver();

        self::assertTrue($resolver->hasAmbiguousPeriodMention('¿Cuánto gasté el otro mes?'));
        self::assertTrue($resolver->hasAmbiguousPeriodMention('¿Cuánto gasté desde ese mes?'));
        self::assertFalse($resolver->hasAmbiguousPeriodMention('¿Qué diferencia hay entre gastos esenciales y flexibles?'));
        self::assertFalse($resolver->hasAmbiguousPeriodMention('¿Cómo añado un gasto desde el formulario?'));
        self::assertFalse($resolver->hasAmbiguousPeriodMention('¿Qué ocurre hasta que confirmo el formulario?'));
    }
}
