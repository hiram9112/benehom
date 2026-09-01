<?php

declare(strict_types=1);

namespace Tests\Integration;

use InvalidArgumentException;
use Tests\Support\FakeNumaEmbeddingProvider;

require_once APP_PATH . '/services/NumaEmbeddingProvider.php';
require_once APP_PATH . '/services/NumaKnowledge.php';

final class NumaKnowledgeSearchTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->db->exec('DELETE FROM numa_conocimiento');
    }

    public function testBuscaOrdenaFiltraDeduplicaYLimitaResultados(): void
    {
        $sameContent = "Movimientos\nAnadir movimientos\n\nPuedes registrar ingresos y gastos desde el panel.";
        $this->insertKnowledge('movimientos:anadir', 'movimientos.md', 'Movimientos', 'Anadir movimientos', $sameContent, [1.0, 0.0]);
        $this->insertKnowledge('movimientos:anadir-duplicado', 'movimientos.md', 'Movimientos', 'Anadir movimientos', $sameContent, [0.99, 0.1]);
        $this->insertKnowledge('ahorro:posible', 'ahorro.md', 'Ahorro', 'Ahorro posible', 'Ahorro posible de BeneHom.', [0.9, 0.435889894]);
        $this->insertKnowledge('gastos:flexibles', 'gastos.md', 'Gastos', 'Gastos flexibles', 'Gastos flexibles registrados.', [0.8, 0.6]);
        $this->insertKnowledge('dashboard:resumen', 'dashboard.md', 'Dashboard', 'Resumen', 'Resumen mensual del dashboard.', [0.7, 0.714142842]);
        $this->insertKnowledge('cuenta:perfil', 'cuenta.md', 'Cuenta', 'Perfil', 'Gestion de cuenta.', [0.6, 0.8]);

        $provider = FakeNumaEmbeddingProvider::withVectors([
            'como anadir movimiento' => [1.0, 0.0],
        ], 2);
        $searcher = new \NumaKnowledgeSearcher($this->db, $provider, 2, 3, 0.65);

        $results = $searcher->search("  como anadir\nmovimiento  ");

        self::assertSame(['como anadir movimiento'], $provider->texts);
        self::assertSame(['query'], $provider->tasks);
        self::assertCount(3, $results);
        self::assertSame('movimientos:anadir', $results[0]->fragmentId());
        self::assertSame('ahorro:posible', $results[1]->fragmentId());
        self::assertSame('gastos:flexibles', $results[2]->fragmentId());
        self::assertSame('/dashboard', $results[0]->route());
        self::assertArrayHasKey('similarity', $results[0]->toArray());
    }

    public function testNoMezclaVectoresConLaMismaDimensionPeroFirmaDistinta(): void
    {
        $this->insertKnowledge(
            'movimientos:incompatible',
            'movimientos.md',
            'Movimientos',
            'Anadir movimientos',
            'Contenido con otro modelo.',
            [1.0, 0.0],
            new \NumaEmbeddingSignature('fake', 'modelo-anterior', 'RETRIEVAL_DOCUMENT', 2, '1')
        );
        $provider = FakeNumaEmbeddingProvider::withVectors(['como anadir movimiento' => [1.0, 0.0]], 2);
        $searcher = new \NumaKnowledgeSearcher($this->db, $provider, 2, 3, 0.65);

        self::assertSame([], $searcher->search('como anadir movimiento'));
    }

    public function testDevuelveVacioCuandoNadaSuperaElUmbral(): void
    {
        $this->insertKnowledge('cuenta:perfil', 'cuenta.md', 'Cuenta', 'Perfil', 'Gestion de cuenta.', [0.0, 1.0]);

        $searcher = new \NumaKnowledgeSearcher(
            $this->db,
            FakeNumaEmbeddingProvider::withVectors(['consulta sin resultado' => [1.0, 0.0]], 2),
            2,
            3,
            0.65
        );

        self::assertSame([], $searcher->search('consulta sin resultado'));
    }

    public function testElUmbralPredeterminadoCalibradoEs067(): void
    {
        $this->insertKnowledge('umbral:aceptado', 'umbral.md', 'Umbral', 'Aceptado', 'Coincide.', [0.68, 0.733212111]);
        $this->insertKnowledge('umbral:rechazado', 'umbral.md', 'Umbral', 'Rechazado', 'No coincide.', [0.66, 0.751265598]);
        $provider = FakeNumaEmbeddingProvider::withVectors(['consulta de umbral' => [1.0, 0.0]], 2);

        $results = (new \NumaKnowledgeSearcher($this->db, $provider, 2))->search('consulta de umbral');

        self::assertSame(['umbral:aceptado'], array_map(
            static fn (\NumaKnowledgeSearchResult $result): string => $result->fragmentId(),
            $results,
        ));
    }

    public function testRechazaConsultaDocumentalVacia(): void
    {
        $searcher = new \NumaKnowledgeSearcher(
            $this->db,
            FakeNumaEmbeddingProvider::withVectors(['consulta' => [1.0, 0.0]], 2),
            2
        );

        $this->expectException(InvalidArgumentException::class);

        $searcher->search('   ');
    }

    public function testSeparaConsultaDocumentalAntesDeCrearEmbedding(): void
    {
        $this->insertKnowledge('gastos:flexibles', 'gastos.md', 'Gastos', 'Gastos flexibles', 'Gastos flexibles de BeneHom.', [1.0, 0.0]);
        $provider = FakeNumaEmbeddingProvider::withVectors([
            'Qué son los gastos flexibles' => [1.0, 0.0],
        ], 2);
        $searcher = new \NumaKnowledgeSearcher($this->db, $provider, 2, 3, 0.65);

        $searcher->search('Me llamo Laura Pérez. ¿Qué son los gastos flexibles y cuánto gasté 123,45 euros este mes? Mi correo es laura@example.com y mi usuario_id 42.');

        self::assertSame(['Qué son los gastos flexibles'], $provider->texts);
        self::assertSame(['query'], $provider->tasks);
    }

    public function testRechazaConsultaDocumentalConDatosPrivadosSinParteDocumental(): void
    {
        $provider = FakeNumaEmbeddingProvider::withVectors(['consulta' => [1.0, 0.0]], 2);
        $searcher = new \NumaKnowledgeSearcher($this->db, $provider, 2);

        $this->expectException(InvalidArgumentException::class);

        try {
            $searcher->search('¿Cuánto gasté en comida este mes? usuario_id 42');
        } finally {
            self::assertSame([], $provider->texts);
        }
    }

    public function testRechazaResultadosDeToolsAntesDeCrearEmbedding(): void
    {
        $provider = FakeNumaEmbeddingProvider::withVectors(['consulta' => [1.0, 0.0]], 2);
        $searcher = new \NumaKnowledgeSearcher($this->db, $provider, 2);

        $this->expectException(InvalidArgumentException::class);

        try {
            $searcher->search('{"tool":"obtener_resumen_financiero","ingresos":1200,"gastos":900}');
        } finally {
            self::assertSame([], $provider->texts);
        }
    }

    /**
     * @param array<int, float> $embedding
     */
    private function insertKnowledge(
        string $fragmentId,
        string $document,
        string $title,
        string $section,
        string $content,
        array $embedding,
        ?\NumaEmbeddingSignature $signature = null,
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO numa_conocimiento
                (fragmento_id, documento, titulo, seccion, ruta, contenido, hash, embedding, dimensiones, firma_embedding, indexed_at)
             VALUES
                (:fragmento_id, :documento, :titulo, :seccion, :ruta, :contenido, :hash, :embedding, :dimensiones, :firma_embedding, :indexed_at)'
        );
        $stmt->execute([
            ':fragmento_id' => $fragmentId,
            ':documento' => $document,
            ':titulo' => $title,
            ':seccion' => $section,
            ':ruta' => '/dashboard',
            ':contenido' => $content,
            ':hash' => hash('sha256', $content),
            ':embedding' => json_encode($embedding, JSON_THROW_ON_ERROR),
            ':dimensiones' => count($embedding),
            ':firma_embedding' => ($signature ?? new \NumaEmbeddingSignature('fake', 'deterministic', 'RETRIEVAL_DOCUMENT', count($embedding), '1'))->value(),
            ':indexed_at' => '2026-07-29 12:00:00',
        ]);
    }
}
