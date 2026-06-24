<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CategoriasWhitelistTest extends TestCase
{
    public function testAceptaCategoriasDeGastoDelCatalogo(): void
    {
        foreach (\gastoCategorias() as $tipo => $grupos) {
            foreach ($grupos as $grupo) {
                foreach (array_keys($grupo['items']) as $categoria) {
                    self::assertTrue(
                        \gastoCategoriaPermitida($tipo, $categoria),
                        "La categoria {$categoria} deberia estar permitida para {$tipo}."
                    );
                }
            }
        }
    }

    public function testRechazaCategoriasDeGastoDesconocidasOTipoEquivocado(): void
    {
        self::assertFalse(\gastoCategoriaPermitida('esencial', 'categoria_desconocida'));
        self::assertFalse(\gastoCategoriaPermitida('flexible', 'alquiler_hipoteca'));
        self::assertFalse(\gastoCategoriaPermitida('esencial', 'ocio_entretenimiento'));
        self::assertFalse(\gastoCategoriaPermitida('esencial', 'salario'));
    }

    public function testAceptaCategoriasDeIngresoDelCatalogo(): void
    {
        foreach (array_keys(\ingresoCategorias()) as $categoria) {
            self::assertTrue(
                \ingresoCategoriaPermitida($categoria),
                "La categoria {$categoria} deberia estar permitida como ingreso."
            );
        }
    }

    public function testRechazaCategoriasDeIngresoDesconocidasOGastos(): void
    {
        self::assertFalse(\ingresoCategoriaPermitida('categoria_desconocida'));
        self::assertFalse(\ingresoCategoriaPermitida('alquiler_hipoteca'));
        self::assertFalse(\ingresoCategoriaPermitida('ocio_entretenimiento'));
    }
}
