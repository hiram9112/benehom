<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/NumaKnowledge.php';

final class NumaKnowledgeFragmenterTest extends TestCase
{
    private const INDEXED_AT = '2026-07-29T10:30:00+00:00';

    /** @var array<int, string> */
    private array $tempDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirectories as $directory) {
            foreach (glob($directory . '/*') ?: [] as $path) {
                is_file($path) && unlink($path);
            }

            is_dir($directory) && rmdir($directory);
        }

        $this->tempDirectories = [];
    }

    public function testFragmentaDocumentoPorEncabezadosConMetadatos(): void
    {
        $fragmenter = new \NumaKnowledgeFragmenter();
        $fragments = $fragmenter->fragmentFile(
            BASE_PATH . '/knowledge/numa/movimientos.md',
            new DateTimeImmutable(self::INDEXED_AT)
        );

        self::assertCount(6, $fragments);

        $first = $fragments[0];
        self::assertSame('knowledge:movimientos:que-son-los-movimientos', $first->id());
        self::assertSame('movimientos.md', $first->document());
        self::assertSame('Movimientos', $first->title());
        self::assertSame('Que son los movimientos', $first->section());
        self::assertSame('/dashboard', $first->route());
        self::assertStringStartsWith("Movimientos\nQue son los movimientos\n\n", $first->content());
        self::assertSame(hash('sha256', json_encode([
            'source' => 'knowledge',
            'source_identity' => $first->document(),
            'document' => $first->document(),
            'title' => $first->title(),
            'section' => $first->section(),
            'route' => $first->route(),
            'content' => $first->content(),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), $first->hash());
        self::assertSame(self::INDEXED_AT, $first->indexedAt()->format(DATE_ATOM));
    }

    public function testFragmentaArticuloConNamespaceYPartesEstables(): void
    {
        $fragments = (new \NumaKnowledgeFragmenter(maxContentChars: 200))->fragmentArticles([
            [
                'slug' => 'guia-del-blog',
                'titulo' => 'Guia del blog',
                'resumen' => 'Resumen',
                'intencion_busqueda' => 'intencion',
                'conexion' => 'conexion',
                'contenido' => [[
                    'titulo' => 'Inicio',
                    'parrafos' => ['Primer bloque suficientemente breve.', 'Segundo bloque suficientemente breve.'],
                ]],
            ],
        ], new DateTimeImmutable(self::INDEXED_AT));

        self::assertSame(['blog:guia-del-blog:inicio:part-1'], array_map(
            static fn (\NumaKnowledgeFragment $fragment): string => $fragment->id(),
            $fragments
        ));
        self::assertStringContainsString('Resumen: Resumen', $fragments[0]->content());
        self::assertStringContainsString('Intencion de busqueda: intencion', $fragments[0]->content());
        self::assertStringContainsString('Conexion con BeneHom: conexion', $fragments[0]->content());
    }

    public function testRechazaArticuloSinCamposPublicosDeContexto(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new \NumaKnowledgeFragmenter())->fragmentArticles([
            [
                'slug' => 'guia-del-blog',
                'titulo' => 'Guia del blog',
                'resumen' => '',
                'intencion_busqueda' => 'intencion',
                'conexion' => 'conexion',
                'contenido' => [[
                    'titulo' => 'Inicio',
                    'parrafos' => ['Contenido publico.'],
                ]],
            ],
        ], new DateTimeImmutable(self::INDEXED_AT));
    }

    public function testFragmentaTodaLaBaseDeConocimientoConIdsEstables(): void
    {
        $fragmenter = new \NumaKnowledgeFragmenter();

        $firstRun = $fragmenter->fragmentDirectory(
            BASE_PATH . '/knowledge/numa',
            new DateTimeImmutable(self::INDEXED_AT)
        );
        $secondRun = $fragmenter->fragmentDirectory(
            BASE_PATH . '/knowledge/numa',
            new DateTimeImmutable(self::INDEXED_AT)
        );

        self::assertNotEmpty($firstRun);
        self::assertSame(
            array_map(static fn (\NumaKnowledgeFragment $fragment): string => $fragment->id(), $firstRun),
            array_map(static fn (\NumaKnowledgeFragment $fragment): string => $fragment->id(), $secondRun)
        );
        self::assertSame(
            array_map(static fn (\NumaKnowledgeFragment $fragment): string => $fragment->hash(), $firstRun),
            array_map(static fn (\NumaKnowledgeFragment $fragment): string => $fragment->hash(), $secondRun)
        );
    }

    public function testRespetaLimiteDeNovecientosCaracteresSinCortarBloques(): void
    {
        $document = <<<MD
# Guia de prueba

## Seccion larga

Primer bloque con una definicion completa sobre BeneHom y una explicacion autosuficiente para conservar contexto al recuperar documentacion.

Segundo bloque que debe pasar a otro fragmento porque el limite configurado no permite unirlo al bloque anterior sin superar el maximo.
MD;

        $path = $this->writeTempDocument('guia.md', $document);
        $fragmenter = new \NumaKnowledgeFragmenter(['guia.md' => '/dashboard'], 220);
        $fragments = $fragmenter->fragmentFile($path, new DateTimeImmutable(self::INDEXED_AT));

        self::assertCount(2, $fragments);
        self::assertSame('knowledge:guia:seccion-larga-1', $fragments[0]->id());
        self::assertSame('knowledge:guia:seccion-larga-2', $fragments[1]->id());

        foreach ($fragments as $fragment) {
            self::assertLessThanOrEqual(220, $this->length($fragment->content()));
            self::assertStringStartsWith("Guia de prueba\nSeccion larga\n\n", $fragment->content());
        }

        self::assertStringContainsString('Primer bloque con una definicion completa', $fragments[0]->content());
        self::assertStringContainsString('Segundo bloque que debe pasar a otro fragmento', $fragments[1]->content());
    }

    public function testIncluyeSubencabezadosEnLaSeccion(): void
    {
        $document = <<<MD
# Proyecciones

## Simulaciones

Contenido general.

### Inflacion

La inflacion reduce la capacidad de compra en una simulacion educativa.
MD;

        $path = $this->writeTempDocument('proyecciones.md', $document);
        $fragments = (new \NumaKnowledgeFragmenter(['proyecciones.md' => '/proyecciones']))
            ->fragmentFile($path, new DateTimeImmutable(self::INDEXED_AT));

        self::assertCount(2, $fragments);
        self::assertSame('Simulaciones', $fragments[0]->section());
        self::assertSame('Simulaciones > Inflacion', $fragments[1]->section());
        self::assertSame('knowledge:proyecciones:simulaciones-inflacion', $fragments[1]->id());
    }

    public function testToArrayExponeContratoDeFragmento(): void
    {
        $fragments = (new \NumaKnowledgeFragmenter())->fragmentFile(
            BASE_PATH . '/knowledge/numa/ahorro.md',
            new DateTimeImmutable(self::INDEXED_AT)
        );

        self::assertSame([
            'id' => 'knowledge:ahorro:ahorro-posible',
            'document' => 'ahorro.md',
            'title' => 'Ahorro',
            'section' => 'Ahorro posible',
            'route' => '/dashboard',
            'content' => $fragments[0]->content(),
            'hash' => $fragments[0]->hash(),
            'indexed_at' => self::INDEXED_AT,
        ], $fragments[0]->toArray());
    }

    public function testRechazaDocumentoSinRutaRelacionada(): void
    {
        $path = $this->writeTempDocument('privado.md', "# Privado\n\n## Interno\n\nNo debe indexarse.");

        $this->expectException(InvalidArgumentException::class);

        (new \NumaKnowledgeFragmenter())->fragmentFile($path, new DateTimeImmutable(self::INDEXED_AT));
    }

    public function testRechazaDocumentoSinTituloPrincipal(): void
    {
        $path = $this->writeTempDocument('guia.md', "## Seccion\n\nContenido.");

        $this->expectException(InvalidArgumentException::class);

        (new \NumaKnowledgeFragmenter(['guia.md' => '/dashboard']))
            ->fragmentFile($path, new DateTimeImmutable(self::INDEXED_AT));
    }

    public function testRechazaBloqueQueSuperaElLimiteSinCortarlo(): void
    {
        $path = $this->writeTempDocument('guia.md', "# Guia\n\n## Seccion\n\n" . str_repeat('a', 220));

        $this->expectException(InvalidArgumentException::class);

        (new \NumaKnowledgeFragmenter(['guia.md' => '/dashboard'], 200))
            ->fragmentFile($path, new DateTimeImmutable(self::INDEXED_AT));
    }

    private function writeTempDocument(string $name, string $contents): string
    {
        $directory = sys_get_temp_dir() . '/benehom_numa_' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($directory));
        $this->tempDirectories[] = $directory;

        $path = $directory . '/' . $name;
        self::assertNotFalse(file_put_contents($path, $contents));

        return $path;
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
