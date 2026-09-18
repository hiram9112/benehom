<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/models/ArticuloBlog.php';

final class ArticuloBlogRagTest extends TestCase
{
    public function testUnArticuloPublicadoNecesitaPertinenciaYAprobacionParaRag(): void
    {
        self::assertFalse(\ArticuloBlog::esElegibleParaRag(['estado' => 'publicado']));
        self::assertFalse(\ArticuloBlog::esElegibleParaRag([
            'estado' => 'publicado',
            'rag_pertinente' => true,
        ]));
        self::assertFalse(\ArticuloBlog::esElegibleParaRag([
            'estado' => 'publicado',
            'rag_aprobado' => true,
        ]));
        self::assertFalse(\ArticuloBlog::esElegibleParaRag([
            'estado' => 'borrador',
            'rag_pertinente' => true,
            'rag_aprobado' => true,
        ]));
        self::assertTrue(\ArticuloBlog::esElegibleParaRag([
            'estado' => 'publicado',
            'rag_pertinente' => true,
            'rag_aprobado' => true,
        ]));
    }

    public function testElSelectorDeRagSoloExponeCamposPublicosUtiles(): void
    {
        $articulos = \ArticuloBlog::publicadosParaRag();

        self::assertCount(14, $articulos);

        foreach ($articulos as $articulo) {
            self::assertSame(
                ['slug', 'titulo', 'resumen', 'intencion_busqueda', 'contenido', 'conexion'],
                array_keys($articulo)
            );
            self::assertNotSame('', $articulo['slug']);
            self::assertNotSame('', $articulo['titulo']);
            self::assertNotSame('', $articulo['resumen']);
            self::assertNotSame('', $articulo['intencion_busqueda']);
            self::assertNotSame('', $articulo['conexion']);
            self::assertNotSame([], $articulo['contenido']);
        }
    }
}
