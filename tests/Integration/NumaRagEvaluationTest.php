<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/models/ArticuloBlog.php';
require_once APP_PATH . '/services/NumaEmbeddingProvider.php';
require_once APP_PATH . '/services/NumaClassification.php';
require_once APP_PATH . '/services/NumaKnowledge.php';

final class NumaRagEvaluationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->db->exec('DELETE FROM numa_conocimiento');
    }

    public function testConjuntoVersionadoCubreLaEvaluacionDeLaTarea126(): void
    {
        $evaluation = $this->evaluation();
        $cases = $evaluation['cases'];
        $types = array_unique(array_column($cases, 'type'));
        sort($types);

        self::assertSame('2026-08-13.2', $evaluation['version']);
        self::assertSame(0.35, $evaluation['ci_min_similarity']);
        self::assertSame(0.67, $evaluation['production_min_similarity']);
        self::assertSame('calibrated_real_gemini_2026-08-13', $evaluation['calibration_status']);
        self::assertContains('positive_document', $types);
        self::assertContains('positive_blog', $types);
        self::assertContains('alternative_formulation', $types);
        self::assertContains('combined', $types);
        self::assertContains('out_of_scope', $types);
        self::assertContains('no_result', $types);
        self::assertContains('overlap_functional_priority', $types);

        $documents = [];
        $blogArticles = [];
        foreach ($cases as $case) {
            foreach (($case['expected_documents'] ?? $case['expected_top_documents'] ?? []) as $document) {
                if (str_starts_with((string) $document, 'blog/')) {
                    $blogArticles[$document] = true;
                } elseif (str_ends_with((string) $document, '.md')) {
                    $documents[$document] = true;
                }
            }
        }

        $documents = array_keys($documents);
        sort($documents, SORT_STRING);

        self::assertSame([
            'ahorro.md',
            'cuenta.md',
            'dashboard.md',
            'gastos.md',
            'introduccion.md',
            'metas.md',
            'movimientos.md',
            'preguntas-frecuentes.md',
            'proyecciones.md',
        ], $documents);
        self::assertGreaterThanOrEqual(6, count($blogArticles));

        foreach ($cases as $case) {
            self::assertContains($case['split'] ?? null, ['calibration', 'validation'], $case['id']);

            if ($case['type'] === 'out_of_scope') {
                self::assertArrayNotHasKey('knowledge_query', $case, $case['id']);
                self::assertArrayHasKey('expected_intent', $case, $case['id']);
                self::assertArrayHasKey('expected_reason', $case, $case['id']);
                continue;
            }

            self::assertArrayHasKey('knowledge_query', $case, $case['id']);
        }
    }

    public function testEvaluacionRagContraCorpusRealConjunto(): void
    {
        $evaluation = $this->evaluation();
        $provider = new LexicalNumaEvaluationEmbeddingProvider();
        $indexer = new \NumaKnowledgeIndexer(
            $this->db,
            $provider,
            new \NumaKnowledgeFragmenter(maxContentChars: \NumaKnowledgeFragmenter::MAX_CONTENT_CHARS),
            $provider->dimensions()
        );
        $summary = $indexer->indexCorpus(
            BASE_PATH . '/knowledge/numa',
            \ArticuloBlog::publicadosParaRag()
        );

        self::assertSame(23, $summary->documents);
        self::assertGreaterThan(9, $summary->fragments);

        $searcher = new \NumaKnowledgeSearcher(
            $this->db,
            $provider,
            $provider->dimensions(),
            \NumaKnowledgeSearcher::MAX_RESULTS,
            $evaluation['ci_min_similarity']
        );
        $classifier = new \NumaLocalScopeClassifier();

        foreach ($evaluation['cases'] as $case) {
            $question = (string) $case['question'];

            if ($case['type'] === 'out_of_scope') {
                $taskCount = count($provider->tasks);
                $rejection = $classifier->classify($question);

                self::assertNotNull($rejection, $case['id']);
                self::assertSame($case['expected_intent'], $rejection->classification()->intent(), $case['id']);
                self::assertSame($case['expected_reason'], $rejection->classification()->reason(), $case['id']);
                self::assertCount($taskCount, $provider->tasks, $case['id']);
                continue;
            }

            self::assertNull($classifier->classify($question), $case['id']);
            $results = $searcher->search((string) $case['knowledge_query']);

            if ($case['type'] === 'no_result') {
                self::assertSame([], $results, $case['id']);
                continue;
            }

            self::assertNotSame([], $results, $case['id']);
            self::assertLessThanOrEqual(3, count($results), $case['id']);

            $documents = array_map(
                static fn (\NumaKnowledgeSearchResult $result): string => $result->document(),
                $results
            );

            if (isset($case['expected_top_documents'])) {
                self::assertContains($documents[0], $case['expected_top_documents'], $case['id']);
            }

            if (isset($case['expected_documents'])) {
                self::assertNotSame([], array_intersect($case['expected_documents'], $documents), $case['id']);
            }

            if (isset($case['expected_fragment_ids'])) {
                $fragmentIds = array_map(
                    static fn (\NumaKnowledgeSearchResult $result): string => $result->fragmentId(),
                    $results
                );
                self::assertNotSame([], array_intersect($case['expected_fragment_ids'], $fragmentIds), $case['id']);
            }
        }

        self::assertContains('document', $provider->tasks);
        self::assertContains('query', $provider->tasks);
    }

    /**
     * @return array{version:string, corpus:string, ci_min_similarity:float, production_min_similarity:?float, calibration_status:string, calibration_basis:string, cases:array<int, array<string, mixed>>}
     */
    private function evaluation(): array
    {
        $evaluation = require BASE_PATH . '/resources/numa/evaluacion-rag.php';

        self::assertIsArray($evaluation);

        return $evaluation;
    }
}

final class LexicalNumaEvaluationEmbeddingProvider implements \NumaEmbeddingTaskProviderInterface
{
    /** @var array<int, string> */
    public array $tasks = [];

    private const FEATURES = [
        ['benehom'],
        ['aplicacion', 'economia familiar'],
        ['numa', 'guia inteligente'],
        ['sesion', 'conversacion nueva', 'memoria conversacional', 'transcript'],
        ['privacidad', 'datos privados', 'datos personales'],
        ['dashboard', 'panel principal'],
        ['selector', 'cambiar mes', 'mes concreto', 'periodo'],
        ['historia mes', 'cascada', 'recorrido dinero'],
        ['indicadores', 'porcentaje ahorro real', 'variacion gastos'],
        ['graficos', 'evolucion ahorro', 'top'],
        ['movimiento', 'movimientos', 'entrada', 'salida'],
        ['anadir', 'anado', 'agregar', 'registrar'],
        ['ingreso', 'ingresos'],
        ['gasto', 'gastos'],
        ['esencial', 'esenciales', 'necesario', 'necesarios', 'necesidad', 'necesidades'],
        ['flexible', 'flexibles', 'revisable', 'revisables', 'ocio', 'suscripcion', 'gustos'],
        ['editar', 'corregir', 'eliminar', 'borrar'],
        ['ahorro posible', 'margen teorico', 'podria ahorrar'],
        ['ahorro real', 'dinero disponible', 'ahorrar de verdad'],
        ['diferencia entre ahorro posible', 'diferencia entre ahorro posible y ahorro real'],
        ['diferencia', 'distancia'],
        ['ahorro negativo', 'negativo'],
        ['metas', 'meta ahorro', 'objetivo ahorro', 'objetivos'],
        ['aportacion', 'fecha objetivo', 'plazo'],
        ['simulacion', 'simulado', 'simula'],
        ['proyeccion', 'proyecciones', 'escenario'],
        ['interes compuesto', 'reinversion', 'capital', 'rendimientos'],
        ['inflacion', 'capacidad compra', 'precios', 'presupuesto'],
        ['hipoteca', 'cuota', 'prestamo'],
        ['contrasena', 'password'],
        ['descargar datos', 'descargar', 'copia datos', 'exportacion'],
        ['legal', 'privacidad', 'terminos', 'aviso'],
        ['fuentes', 'fuentes documentales', 'rag'],
        ['benehom recomienda inversiones', 'no recomienda productos financieros'],
        ['presupuesto familiar', 'presupuesto sano'],
        ['50 30 20', 'regla 50 30 20', 'necesidades gustos ahorro'],
        ['ahorrar dinero', 'ahorrar cada mes', 'habitos ahorro', 'cuesta ahorrar'],
        ['vivir por debajo', 'posibilidades', 'margen'],
        ['fijo', 'fijos', 'variable', 'variables'],
        ['gastos hormiga', 'hormiga', 'gastos pequenos', 'pequenos desembolsos'],
        ['fondo emergencia', 'colchon', 'imprevistos'],
        ['invertir', 'inversion', 'inversiones', 'acciones', 'fondos', 'recomienda inversiones'],
        ['hipoteca fija', 'hipoteca variable', 'hipoteca mixta'],
        ['cuota inicial', 'banco', 'interes'],
    ];

    public function dimensions(): int
    {
        return count(self::FEATURES);
    }

    /** @return array<int, float> */
    public function embed(string $text): array
    {
        return $this->embedDocument($text);
    }

    /** @return array<int, float> */
    public function embedDocument(string $text): array
    {
        return $this->embedWithTask('document', $text);
    }

    /** @return array<int, float> */
    public function embedQuery(string $text): array
    {
        return $this->embedWithTask('query', $text);
    }

    public function signature(): \NumaEmbeddingSignature
    {
        return new \NumaEmbeddingSignature('fake', 'lexical-corpus-evaluation', 'RETRIEVAL_DOCUMENT', $this->dimensions(), '1');
    }

    /** @return array<int, float> */
    private function embedWithTask(string $task, string $text): array
    {
        $this->tasks[] = $task;
        $normalized = $this->normalize($text);
        $vector = [];

        foreach (self::FEATURES as $feature) {
            $score = 0.0;
            foreach ($feature as $term) {
                if (str_contains($normalized, $this->normalize($term))) {
                    $score += 1.0;
                }
            }

            $vector[] = $score;
        }

        return $vector;
    }

    private function normalize(string $text): string
    {
        $text = strtolower(strtr($text, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
        ]));
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;

        return ' ' . trim(preg_replace('/\s+/', ' ', $text) ?? $text) . ' ';
    }
}
