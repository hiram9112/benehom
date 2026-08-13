<?php

declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use Tests\Support\FakeNumaEmbeddingProvider;

require_once APP_PATH . '/services/NumaEmbeddingProvider.php';
require_once APP_PATH . '/services/NumaKnowledge.php';
require_once APP_PATH . '/models/NumaConsumoGlobal.php';
require_once APP_PATH . '/models/ArticuloBlog.php';
require_once APP_PATH . '/services/GeminiEmbeddingProvider.php';

final class NumaKnowledgeIndexerTest extends IntegrationTestCase
{
    private const INDEXED_AT = '2026-07-29T12:00:00+00:00';

    /** @var array<int, string> */
    private array $tempDirectories = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->exec('DELETE FROM numa_conocimiento');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDirectories as $directory) {
            foreach (glob($directory . '/*') ?: [] as $path) {
                is_file($path) && unlink($path);
            }

            is_dir($directory) && rmdir($directory);
        }

        $this->tempDirectories = [];
        parent::tearDown();
    }

    public function testIndexacionInicialInsertaFragmentosYEmbeddings(): void
    {
        $directory = $this->knowledgeDirectory([
            'guia.md' => "# Guia\n\n## Inicio\n\nContenido publico de BeneHom.",
        ]);
        $provider = new FakeNumaEmbeddingProvider(4);
        $indexer = $this->indexer($provider, ['guia.md' => '/dashboard']);

        $summary = $indexer->indexDirectory($directory, new DateTimeImmutable(self::INDEXED_AT));

        self::assertSame(1, $summary->documents);
        self::assertSame(1, $summary->fragments);
        self::assertSame(1, $summary->created);
        self::assertSame(0, $summary->updated);
        self::assertSame(0, $summary->unchanged);
        self::assertSame(0, $summary->deleted);
        self::assertSame(1, $summary->embeddingsGenerated);
        self::assertSame(1, $provider->calls);
        self::assertSame(['document'], $provider->tasks);

        $row = $this->knowledgeRows()[0];
        self::assertSame('knowledge:guia:inicio', $row['fragmento_id']);
        self::assertSame('guia.md', $row['documento']);
        self::assertSame('/dashboard', $row['ruta']);
        self::assertSame(4, (int) $row['dimensiones']);
        self::assertSame($provider->signature()->value(), $row['firma_embedding']);
        self::assertCount(4, json_decode((string) $row['embedding'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function testReindexacionSinCambiosNoRegeneraEmbeddings(): void
    {
        $directory = $this->knowledgeDirectory([
            'guia.md' => "# Guia\n\n## Inicio\n\nContenido publico de BeneHom.",
        ]);
        $provider = new FakeNumaEmbeddingProvider(4);
        $indexer = $this->indexer($provider, ['guia.md' => '/dashboard']);

        $indexer->indexDirectory($directory, new DateTimeImmutable(self::INDEXED_AT));
        $provider->calls = 0;
        $summary = $indexer->indexDirectory($directory, new DateTimeImmutable(self::INDEXED_AT));

        self::assertSame(0, $summary->created);
        self::assertSame(0, $summary->updated);
        self::assertSame(1, $summary->unchanged);
        self::assertSame(0, $summary->embeddingsGenerated);
        self::assertSame(0, $provider->calls);
    }

    public function testActualizaHashYEliminaFragmentosObsoletos(): void
    {
        $directory = $this->knowledgeDirectory([
            'guia.md' => "# Guia\n\n## Inicio\n\nContenido inicial.\n\n## Extra\n\nContenido obsoleto.",
        ]);
        $provider = new FakeNumaEmbeddingProvider(4);
        $indexer = $this->indexer($provider, ['guia.md' => '/dashboard']);

        $indexer->indexDirectory($directory, new DateTimeImmutable(self::INDEXED_AT));
        $this->writeDocument($directory, 'guia.md', "# Guia\n\n## Inicio\n\nContenido actualizado.");
        $provider->calls = 0;

        $summary = $indexer->indexDirectory($directory, new DateTimeImmutable(self::INDEXED_AT));
        $rows = $this->knowledgeRows();

        self::assertSame(0, $summary->created);
        self::assertSame(1, $summary->updated);
        self::assertSame(0, $summary->unchanged);
        self::assertSame(1, $summary->deleted);
        self::assertSame(1, $summary->embeddingsGenerated);
        self::assertSame(1, $provider->calls);
        self::assertCount(1, $rows);
        self::assertSame('knowledge:guia:inicio', $rows[0]['fragmento_id']);
        self::assertStringContainsString('Contenido actualizado.', $rows[0]['contenido']);
    }

    public function testCambioDeFirmaConLasMismasDimensionesRegeneraEmbeddings(): void
    {
        $directory = $this->knowledgeDirectory([
            'guia.md' => "# Guia\n\n## Inicio\n\nContenido publico de BeneHom.",
        ]);
        $initialProvider = new FakeNumaEmbeddingProvider(4, [], 'fake', 'modelo-a');
        $this->indexer($initialProvider, ['guia.md' => '/dashboard'])
            ->indexDirectory($directory, new DateTimeImmutable(self::INDEXED_AT));

        $updatedProvider = new FakeNumaEmbeddingProvider(4, [], 'fake', 'modelo-b');
        $summary = $this->indexer($updatedProvider, ['guia.md' => '/dashboard'])
            ->indexDirectory($directory, new DateTimeImmutable(self::INDEXED_AT));

        self::assertSame(1, $summary->updated);
        self::assertSame(1, $summary->embeddingsGenerated);
        self::assertSame(1, $updatedProvider->calls);
        self::assertSame($updatedProvider->signature()->value(), $this->knowledgeRows()[0]['firma_embedding']);
    }

    public function testCambioDeTaskTypeRegeneraEmbedding(): void
    {
        $directory = $this->knowledgeDirectory([
            'guia.md' => "# Guia\n\n## Inicio\n\nContenido publico de BeneHom.",
        ]);
        $this->indexer(new FakeNumaEmbeddingProvider(4, [], 'fake', 'deterministic', 'SEMANTIC_SIMILARITY'), ['guia.md' => '/dashboard'])
            ->indexDirectory($directory, new DateTimeImmutable(self::INDEXED_AT));

        $provider = new FakeNumaEmbeddingProvider(4, [], 'fake', 'deterministic', 'RETRIEVAL_DOCUMENT');
        $summary = $this->indexer($provider, ['guia.md' => '/dashboard'])
            ->indexDirectory($directory, new DateTimeImmutable(self::INDEXED_AT));

        self::assertSame(1, $summary->updated);
        self::assertSame(1, $summary->embeddingsGenerated);
        self::assertSame(1, $provider->calls);
    }

    public function testCambioDeMetadatosRegeneraEmbedding(): void
    {
        $directory = $this->knowledgeDirectory([
            'guia.md' => "# Guia\n\n## Inicio\n\nContenido publico de BeneHom.",
        ]);
        $provider = new FakeNumaEmbeddingProvider(4);
        $this->indexer($provider, ['guia.md' => '/dashboard'])
            ->indexDirectory($directory, new DateTimeImmutable(self::INDEXED_AT));
        $provider->calls = 0;

        $summary = $this->indexer($provider, ['guia.md' => '/cuenta'])
            ->indexDirectory($directory, new DateTimeImmutable(self::INDEXED_AT));

        self::assertSame(1, $summary->updated);
        self::assertSame(1, $summary->embeddingsGenerated);
        self::assertSame(1, $provider->calls);
        self::assertSame('/cuenta', $this->knowledgeRows()[0]['ruta']);
    }

    public function testCorpusSeparaIdsDeConocimientoYBlogYActualizaMetadatosDeBlog(): void
    {
        $directory = $this->knowledgeDirectory([
            'guia.md' => "# Guia\n\n## Inicio\n\nContenido publico de BeneHom.",
        ]);
        $provider = new FakeNumaEmbeddingProvider(4);
        $indexer = $this->indexer($provider, ['guia.md' => '/dashboard']);
        $article = $this->blogArticle('guia', 'Titulo inicial', 'Inicio', 'Contenido publico de BeneHom.');

        $first = $indexer->indexCorpus($directory, [$article], new DateTimeImmutable(self::INDEXED_AT));
        $rows = $this->knowledgeRows();

        self::assertSame(2, $first->created);
        self::assertSame(['blog:guia:inicio:part-1', 'knowledge:guia:inicio'], array_column($rows, 'fragmento_id'));
        self::assertNotSame($rows[0]['hash'], $rows[1]['hash']);

        $provider->calls = 0;
        $article['titulo'] = 'Titulo actualizado';
        $updated = $indexer->indexCorpus($directory, [$article], new DateTimeImmutable(self::INDEXED_AT));

        self::assertSame(1, $updated->updated);
        self::assertSame(1, $updated->embeddingsGenerated);
        self::assertSame(1, $provider->calls);

        $provider->calls = 0;
        $article['contenido'][0]['titulo'] = 'Seccion actualizada';
        $updatedSection = $indexer->indexCorpus($directory, [$article], new DateTimeImmutable(self::INDEXED_AT));

        self::assertSame(1, $updatedSection->created);
        self::assertSame(1, $updatedSection->deleted);
        self::assertSame(1, $updatedSection->embeddingsGenerated);
        self::assertSame(1, $provider->calls);
    }

    public function testCambioDeSlugDelBlogEliminaLosFragmentosAnteriores(): void
    {
        $directory = $this->knowledgeDirectory([
            'guia.md' => "# Guia\n\n## Inicio\n\nContenido publico de BeneHom.",
        ]);
        $provider = new FakeNumaEmbeddingProvider(4);
        $indexer = $this->indexer($provider, ['guia.md' => '/dashboard']);

        $indexer->indexCorpus($directory, [
            $this->blogArticle('slug-anterior', 'Titulo', 'Inicio', 'Contenido del articulo.'),
        ], new DateTimeImmutable(self::INDEXED_AT));
        $provider->calls = 0;

        $summary = $indexer->indexCorpus($directory, [
            $this->blogArticle('slug-nuevo', 'Titulo', 'Inicio', 'Contenido del articulo.'),
        ], new DateTimeImmutable(self::INDEXED_AT));

        self::assertSame(1, $summary->created);
        self::assertSame(1, $summary->deleted);
        self::assertSame(1, $summary->unchanged);
        self::assertSame(1, $provider->calls);
        self::assertSame(['blog:slug-nuevo:inicio:part-1', 'knowledge:guia:inicio'], array_column(
            $this->knowledgeRows(),
            'fragmento_id'
        ));
    }

    public function testInsertarSeccionAlPrincipioConservaLosIdsDeLasSeccionesExistentes(): void
    {
        $directory = $this->knowledgeDirectory([
            'guia.md' => "# Guia\n\n## Inicio\n\nContenido publico de BeneHom.",
        ]);
        $provider = new FakeNumaEmbeddingProvider(4);
        $indexer = $this->indexer($provider, ['guia.md' => '/dashboard']);
        $article = $this->blogArticle('guia', 'Titulo', 'Seccion estable', 'Contenido de la seccion estable.');

        $indexer->indexCorpus($directory, [$article], new DateTimeImmutable(self::INDEXED_AT));
        $provider->calls = 0;
        array_unshift($article['contenido'], [
            'titulo' => 'Seccion nueva',
            'parrafos' => ['Contenido de la seccion nueva.'],
        ]);

        $summary = $indexer->indexCorpus($directory, [$article], new DateTimeImmutable(self::INDEXED_AT));

        self::assertSame(1, $summary->created);
        self::assertSame(2, $summary->unchanged);
        self::assertSame(0, $summary->updated);
        self::assertSame(1, $provider->calls);
        self::assertSame([
            'blog:guia:seccion-estable:part-1',
            'blog:guia:seccion-nueva:part-1',
            'knowledge:guia:inicio',
        ], array_column($this->knowledgeRows(), 'fragmento_id'));
    }

    public function testMarkdownYArticulosSeReconcilianJuntosSinBorrarLaOtraFuente(): void
    {
        $directory = $this->knowledgeDirectory([
            'guia.md' => "# Guia\n\n## Inicio\n\nContenido publico de BeneHom.",
        ]);
        $provider = new FakeNumaEmbeddingProvider(4);
        $indexer = $this->indexer($provider, ['guia.md' => '/dashboard']);

        $indexer->indexCorpus($directory, [
            $this->blogArticle('guia-blog', 'Titulo', 'Inicio', 'Contenido del articulo.'),
        ], new DateTimeImmutable(self::INDEXED_AT));
        $provider->calls = 0;
        $this->writeDocument($directory, 'guia.md', "# Guia\n\n## Inicio\n\nContenido publico de BeneHom actualizado.");

        $summary = $indexer->indexCorpus($directory, [
            $this->blogArticle('guia-blog', 'Titulo', 'Inicio', 'Contenido del articulo.'),
        ], new DateTimeImmutable(self::INDEXED_AT));

        self::assertSame(0, $summary->created);
        self::assertSame(1, $summary->updated);
        self::assertSame(1, $summary->unchanged);
        self::assertSame(0, $summary->deleted);
        self::assertSame([
            'blog:guia-blog:inicio:part-1',
            'knowledge:guia:inicio',
        ], array_column($this->knowledgeRows(), 'fragmento_id'));
    }

    public function testArticuloPublicadoMalformadoFallaYConservaIndiceAnterior(): void
    {
        $directory = $this->knowledgeDirectory([
            'guia.md' => "# Guia\n\n## Inicio\n\nContenido publico de BeneHom.",
        ]);
        $provider = new FakeNumaEmbeddingProvider(4);
        $indexer = $this->indexer($provider, ['guia.md' => '/dashboard']);
        $article = $this->blogArticle('guia-blog', 'Titulo', 'Inicio', 'Contenido del articulo.');
        $article['estado'] = 'publicado';
        $article['rag_pertinente'] = true;
        $article['rag_aprobado'] = true;

        $indexer->indexCorpus(
            $directory,
            \ArticuloBlog::seleccionarParaRag([$article]),
            new DateTimeImmutable(self::INDEXED_AT)
        );
        $rowsBefore = $this->knowledgeRows();
        unset($article['rag_aprobado']);

        try {
            $indexer->indexCorpus(
                $directory,
                \ArticuloBlog::seleccionarParaRag([$article]),
                new DateTimeImmutable(self::INDEXED_AT)
            );
            self::fail('La indexacion debe fallar si un articulo publicado llega malformado al selector RAG.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Articulo publicado del blog para RAG invalido.', $exception->getMessage());
        }

        self::assertSame($rowsBefore, $this->knowledgeRows());
    }

    public function testFalloDeEmbeddingNoDejaCambiosParciales(): void
    {
        $directory = $this->knowledgeDirectory([
            'guia.md' => "# Guia\n\n## Inicio\n\nContenido publico de BeneHom.",
        ]);
        $indexer = $this->indexer(new FailingEmbeddingProvider(), ['guia.md' => '/dashboard']);

        $this->expectException(\RuntimeException::class);

        try {
            $indexer->indexDirectory($directory, new DateTimeImmutable(self::INDEXED_AT));
        } finally {
            self::assertSame([], $this->knowledgeRows());
        }
    }

    public function testIndexacionMedidaReconciliaPromptTokenCountDelEmbedding(): void
    {
        $envBackup = [];
        foreach ($this->globalEnvKeys() as $key) {
            $envBackup[$key] = $_ENV[$key] ?? null;
        }

        $_ENV['NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT'] = '100';
        $_ENV['NUMA_GLOBAL_MONTHLY_PROVIDER_CALL_LIMIT'] = '1000';
        $_ENV['NUMA_GLOBAL_DAILY_TOKEN_LIMIT'] = '50000';
        $_ENV['NUMA_GLOBAL_MONTHLY_TOKEN_LIMIT'] = '300000';
        $_ENV['NUMA_MAX_RAG_CHUNK_CHARS'] = '900';

        try {
            $directory = $this->knowledgeDirectory([
                'guia.md' => "# Guia\n\n## Inicio\n\nContenido publico de BeneHom.",
            ]);
            $transportCalls = 0;
            $provider = new \GeminiEmbeddingProvider('key', 'model', 4, transport: static function () use (&$transportCalls): array {
                ++$transportCalls;

                return [
                    'status' => 200,
                    'body' => json_encode([
                        'embedding' => [
                            'values' => [0.1, 0.2, 0.3, 0.4],
                        ],
                        'usageMetadata' => [
                            'promptTokenCount' => 29,
                        ],
                    ], JSON_THROW_ON_ERROR),
                ];
            });
            $meteredProvider = new \NumaMeteredEmbeddingProvider(
                $provider,
                \NumaConsumoGlobal::forEmbedding($this->db, new DateTimeImmutable('2026-07-29 12:00:00'))
            );
            $indexer = $this->indexer($meteredProvider, ['guia.md' => '/dashboard']);

            $summary = $indexer->indexDirectory($directory, new DateTimeImmutable(self::INDEXED_AT));
            $row = $this->globalUsageRow('2026-07-29');

            self::assertSame(1, $summary->embeddingsGenerated);
            self::assertSame(1, $transportCalls);
            self::assertSame(1, $row['llamadas']);
            self::assertSame(29, $row['input_tokens']);
            self::assertSame(0, $row['output_tokens']);
        } finally {
            foreach ($envBackup as $key => $value) {
                if ($value === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $value;
                }
            }
        }
    }

    /**
     * @param array<string, string> $routeMap
     */
    private function indexer(\NumaEmbeddingProviderInterface $provider, array $routeMap): \NumaKnowledgeIndexer
    {
        return new \NumaKnowledgeIndexer(
            $this->db,
            $provider,
            new \NumaKnowledgeFragmenter($routeMap, 900),
            4
        );
    }

    /**
     * @param array<string, string> $documents
     */
    private function knowledgeDirectory(array $documents): string
    {
        $directory = sys_get_temp_dir() . '/benehom_numa_index_' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($directory));
        $this->tempDirectories[] = $directory;

        foreach ($documents as $name => $contents) {
            $this->writeDocument($directory, $name, $contents);
        }

        return $directory;
    }

    private function writeDocument(string $directory, string $name, string $contents): void
    {
        self::assertNotFalse(file_put_contents($directory . '/' . $name, $contents));
    }

    /**
     * @return array{slug:string, titulo:string, resumen:string, intencion_busqueda:string, contenido:array<int, array{titulo:string, parrafos:array<int, string>}>, conexion:string}
     */
    private function blogArticle(string $slug, string $title, string $section, string $content): array
    {
        return [
            'slug' => $slug,
            'titulo' => $title,
            'resumen' => 'Resumen publico',
            'intencion_busqueda' => 'Consulta publica',
            'contenido' => [[
                'titulo' => $section,
                'parrafos' => [$content],
            ]],
            'conexion' => 'Conexion publica',
        ];
    }

    /**
     * @return array{llamadas:int,input_tokens:int,output_tokens:int}
     */
    private function globalUsageRow(string $fecha): array
    {
        $stmt = $this->db->prepare(
            'SELECT llamadas, input_tokens, output_tokens FROM numa_uso_proveedor WHERE fecha = :fecha'
        );
        $stmt->execute([':fecha' => $fecha]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        self::assertIsArray($row);

        return [
            'llamadas' => (int) $row['llamadas'],
            'input_tokens' => (int) $row['input_tokens'],
            'output_tokens' => (int) $row['output_tokens'],
        ];
    }

    /** @return array<int, string> */
    private function globalEnvKeys(): array
    {
        return [
            'NUMA_GLOBAL_DAILY_PROVIDER_CALL_LIMIT',
            'NUMA_GLOBAL_MONTHLY_PROVIDER_CALL_LIMIT',
            'NUMA_GLOBAL_DAILY_TOKEN_LIMIT',
            'NUMA_GLOBAL_MONTHLY_TOKEN_LIMIT',
            'NUMA_MAX_RAG_CHUNK_CHARS',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function knowledgeRows(): array
    {
        $stmt = $this->db->query('SELECT * FROM numa_conocimiento ORDER BY fragmento_id');

        self::assertNotFalse($stmt);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

final class FailingEmbeddingProvider implements \NumaEmbeddingProviderInterface
{
    public function embed(string $text): array
    {
        throw new \RuntimeException('embedding_failed');
    }

    public function signature(): \NumaEmbeddingSignature
    {
        return new \NumaEmbeddingSignature('failing', 'failing', 'RETRIEVAL_DOCUMENT', 4, '1');
    }
}
