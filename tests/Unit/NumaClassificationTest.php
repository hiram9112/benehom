<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/NumaClassification.php';

final class NumaClassificationTest extends TestCase
{
    public function testDefineCategoriasDeFaseCuatroUno(): void
    {
        self::assertSame([
            'producto',
            'educacion_financiera',
            'datos_usuario',
            'consulta_combinada',
            'recomendacion_financiera',
            'fuera_de_ambito',
            'intento_manipulacion',
            'solicitud_datos_terceros',
            'accion_no_permitida',
        ], \NumaClassificationIntent::all());
    }

    public function testAceptaSalidaEstructuradaMinima(): void
    {
        $classification = \NumaClassification::fromStructuredData([
            'intent' => 'producto',
            'allowed' => true,
            'reason' => 'product_help',
        ]);

        self::assertSame('producto', $classification->intent());
        self::assertTrue($classification->allowed());
        self::assertSame('product_help', $classification->reason());
        self::assertNull($classification->knowledgeQuery());
        self::assertNull($classification->dataIntent());
        self::assertSame([
            'intent' => 'producto',
            'allowed' => true,
            'reason' => 'product_help',
        ], $classification->toStructuredData());
    }

    public function testAceptaConsultaDocumentalEIntencionDeDatosControlada(): void
    {
        $classification = \NumaClassification::fromStructuredData([
            'intent' => 'consulta_combinada',
            'allowed' => true,
            'reason' => 'combined_help',
            'knowledge_query' => 'gastos flexibles en BeneHom',
            'data_intent' => 'ranking_categorias',
        ]);

        self::assertSame('gastos flexibles en BeneHom', $classification->knowledgeQuery());
        self::assertSame('ranking_categorias', $classification->dataIntent());
        self::assertSame([
            'intent' => 'consulta_combinada',
            'allowed' => true,
            'reason' => 'combined_help',
            'knowledge_query' => 'gastos flexibles en BeneHom',
            'data_intent' => 'ranking_categorias',
        ], $classification->toStructuredData());
    }

    public function testRechazaCategoriaDesconocida(): void
    {
        $this->expectException(InvalidArgumentException::class);

        \NumaClassification::fromStructuredData([
            'intent' => 'generalista',
            'allowed' => true,
            'reason' => 'unknown',
        ]);
    }

    public function testRechazaSalidaSinMotivo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        \NumaClassification::fromStructuredData([
            'intent' => 'producto',
            'allowed' => true,
        ]);
    }

    public function testRechazaAutorizacionIncoherenteConCategoria(): void
    {
        $this->expectException(InvalidArgumentException::class);

        \NumaClassification::fromStructuredData([
            'intent' => 'recomendacion_financiera',
            'allowed' => true,
            'reason' => 'investment_advice',
        ]);
    }

    public function testRechazaParametrosPeligrosos(): void
    {
        $this->expectException(InvalidArgumentException::class);

        \NumaClassification::fromStructuredData([
            'intent' => 'datos_usuario',
            'allowed' => true,
            'reason' => 'user_data',
            'usuario_id' => 1,
        ]);
    }

    public function testRechazaIntencionDeDatosNoControlada(): void
    {
        $this->expectException(InvalidArgumentException::class);

        \NumaClassification::fromStructuredData([
            'intent' => 'datos_usuario',
            'allowed' => true,
            'reason' => 'user_data',
            'data_intent' => 'sql_libre',
        ]);
    }
}
