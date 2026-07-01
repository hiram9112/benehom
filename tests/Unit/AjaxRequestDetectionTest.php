<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AjaxRequestDetectionTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_GET['r'], $_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH']);

        parent::tearDown();
    }

    public function testDetectaRutasAjaxPorSufijo(): void
    {
        $this->assertTrue(bh_is_ajax_request('ingreso/agregarAjax'));
        $this->assertTrue(bh_is_ajax_request('proyecciones/eliminarMetaAhorroAjax'));
    }

    public function testDetectaEndpointsDeGraficosComoAjax(): void
    {
        $this->assertTrue(bh_is_ajax_request('graficos/estadoGeneral'));
        $this->assertTrue(bh_is_ajax_request('graficos/topCategorias'));
    }

    public function testDetectaAjaxPorCabeceras(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $this->assertTrue(bh_is_ajax_request('cuenta/exportar'));

        unset($_SERVER['HTTP_ACCEPT']);
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $this->assertTrue(bh_is_ajax_request('cuenta/exportar'));
    }

    public function testNoMarcaFormulariosNormalesComoAjax(): void
    {
        $this->assertFalse(bh_is_ajax_request('auth/login'));
        $this->assertFalse(bh_is_ajax_request('proyecciones/crearMetaAhorro'));
    }
}
