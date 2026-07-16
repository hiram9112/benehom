<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FormatoTest extends TestCase
{
    public function testFormateaCategoriaDesdeGastosIngresosYFallback(): void
    {
        self::assertSame('Alquiler o hipoteca', \formatearCategoria('alquiler_hipoteca'));
        self::assertSame('Nómina', \formatearCategoria('nomina'));
        self::assertSame('Salario o nómina', \formatearCategoria('salario'));
        self::assertSame('Categoria Personalizada', \formatearCategoria('categoria_personalizada'));
    }

    public function testFormateaCantidadConDosDecimalesYComa(): void
    {
        self::assertSame('1234,50', \formatearCantidadPHP(1234.5));
        self::assertSame('0,00', \formatearCantidadPHP(0));
        self::assertSame('-42,35', \formatearCantidadPHP(-42.345));
    }
}
