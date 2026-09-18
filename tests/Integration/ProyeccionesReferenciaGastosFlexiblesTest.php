<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/models/Gasto.php';
require_once APP_PATH . '/controllers/ProyeccionesController.php';
require_once APP_PATH . '/views/partials/proyecciones-cards.php';

final class ProyeccionesReferenciaGastosFlexiblesTest extends IntegrationTestCase
{
    private function resolverReferencia(int $usuarioId, string $mesSeleccionado): array|false
    {
        $metodo = new \ReflectionMethod(\ProyeccionesController::class, 'gastosFlexiblesPorCategoriaReferencia');

        return $metodo->invoke(new \ProyeccionesController(), $usuarioId, $mesSeleccionado);
    }

    public function testUsaElMesAnteriorYExplicaAmbosMesesCuandoElSeleccionadoEstaVacio(): void
    {
        $usuario = $this->crearUsuario('proy-ref-agosto.integration@example.test');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'ocio_entretenimiento', 50, '2026-08-10');

        $referencia = $this->resolverReferencia($usuario['id'], '2026-09');

        self::assertSame('2026-09', $referencia['mes_seleccionado']);
        self::assertSame('2026-08', $referencia['mes_referencia']);
        self::assertTrue($referencia['usa_mes_anterior']);
        self::assertSame(
            'Como septiembre 2026 no tiene gastos flexibles registrados, esta simulación usa agosto 2026 como referencia.',
            \bh_proy_nota_referencia_gastos_flexibles($referencia['mes_seleccionado'], $referencia['mes_referencia'])
        );
    }

    public function testUsaElUltimoMesValidoTrasVariosMesesVacios(): void
    {
        $usuario = $this->crearUsuario('proy-ref-julio.integration@example.test');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'viajes_escapadas', 80, '2026-07-10');

        $referencia = $this->resolverReferencia($usuario['id'], '2026-09');

        self::assertSame('2026-09', $referencia['mes_seleccionado']);
        self::assertSame('2026-07', $referencia['mes_referencia']);
        self::assertTrue($referencia['usa_mes_anterior']);
    }

    public function testMantieneElMesSeleccionadoCuandoTieneGastosFlexibles(): void
    {
        $usuario = $this->crearUsuario('proy-ref-actual.integration@example.test');
        \Gasto::agregarGasto($usuario['id'], 'flexible', 'restaurantes_bares_cafeterias', 30, '2026-09-10');

        $referencia = $this->resolverReferencia($usuario['id'], '2026-09');

        self::assertSame('2026-09', $referencia['mes_referencia']);
        self::assertFalse($referencia['usa_mes_anterior']);
    }

    public function testMantieneElEstadoVacioCuandoNoHayNingunMesValido(): void
    {
        $usuario = $this->crearUsuario('proy-ref-vacio.integration@example.test');

        $referencia = $this->resolverReferencia($usuario['id'], '2026-09');

        self::assertSame([], $referencia['categorias']);
        self::assertNull($referencia['mes_referencia']);
        self::assertFalse($referencia['usa_mes_anterior']);
    }
}
