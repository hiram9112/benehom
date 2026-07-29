<?php

declare(strict_types=1);

final class NumaKnowledgeFragment
{
    public function __construct(
        private readonly string $id,
        private readonly string $document,
        private readonly string $title,
        private readonly string $section,
        private readonly string $route,
        private readonly string $content,
        private readonly string $hash,
        private readonly DateTimeImmutable $indexedAt,
    ) {
        foreach ([$id, $document, $title, $section, $route, $content, $hash] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('El fragmento de conocimiento de Numa esta incompleto.');
            }
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function document(): string
    {
        return $this->document;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function section(): string
    {
        return $this->section;
    }

    public function route(): string
    {
        return $this->route;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function hash(): string
    {
        return $this->hash;
    }

    public function indexedAt(): DateTimeImmutable
    {
        return $this->indexedAt;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'document' => $this->document,
            'title' => $this->title,
            'section' => $this->section,
            'route' => $this->route,
            'content' => $this->content,
            'hash' => $this->hash,
            'indexed_at' => $this->indexedAt->format(DateTimeInterface::ATOM),
        ];
    }
}

final class NumaKnowledgeFragmenter
{
    public const MAX_CONTENT_CHARS = 900;

    /** @var array<string, string> */
    private const DEFAULT_ROUTE_MAP = [
        'introduccion.md' => '/',
        'dashboard.md' => '/dashboard',
        'movimientos.md' => '/dashboard',
        'gastos.md' => '/dashboard',
        'ahorro.md' => '/dashboard',
        'metas.md' => '/proyecciones',
        'proyecciones.md' => '/proyecciones',
        'cuenta.md' => '/cuenta',
        'preguntas-frecuentes.md' => '/dashboard',
    ];

    /** @var array<string, string> */
    private readonly array $routeMap;

    /**
     * @param array<string, string> $routeMap
     */
    public function __construct(
        array $routeMap = self::DEFAULT_ROUTE_MAP,
        private readonly int $maxContentChars = self::MAX_CONTENT_CHARS,
    ) {
        if ($maxContentChars < 200) {
            throw new InvalidArgumentException('El limite de caracteres de fragmentos de Numa es demasiado bajo.');
        }

        foreach ($routeMap as $document => $route) {
            if (trim((string) $document) === '' || trim($route) === '' || $route[0] !== '/') {
                throw new InvalidArgumentException('Mapa de rutas de conocimiento de Numa invalido.');
            }
        }

        $this->routeMap = $routeMap;
    }

    /**
     * @return array<int, NumaKnowledgeFragment>
     */
    public function fragmentFile(string $path, ?DateTimeImmutable $indexedAt = null): array
    {
        if (!is_readable($path)) {
            throw new InvalidArgumentException('Documento de conocimiento de Numa no legible.');
        }

        $contents = file_get_contents($path);

        if (!is_string($contents) || trim($contents) === '') {
            throw new InvalidArgumentException('Documento de conocimiento de Numa vacio.');
        }

        $document = basename($path);
        $route = $this->routeFor($document);
        $indexedAt ??= new DateTimeImmutable('now');
        $parsed = $this->parseMarkdown($contents);

        return $this->buildFragments(
            $document,
            $parsed['title'],
            $route,
            $parsed['sections'],
            $indexedAt
        );
    }

    /**
     * @return array<int, NumaKnowledgeFragment>
     */
    public function fragmentDirectory(string $directory, ?DateTimeImmutable $indexedAt = null): array
    {
        if (!is_dir($directory) || !is_readable($directory)) {
            throw new InvalidArgumentException('Directorio de conocimiento de Numa no legible.');
        }

        $paths = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.md') ?: [];
        sort($paths, SORT_STRING);

        $fragments = [];
        foreach ($paths as $path) {
            array_push($fragments, ...$this->fragmentFile($path, $indexedAt));
        }

        return $fragments;
    }

    private function routeFor(string $document): string
    {
        if (!isset($this->routeMap[$document])) {
            throw new InvalidArgumentException('Documento de conocimiento de Numa sin ruta relacionada.');
        }

        return $this->routeMap[$document];
    }

    /**
     * @return array{title: string, sections: array<int, array{section: string, body: string}>}
     */
    private function parseMarkdown(string $contents): array
    {
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);
        $lines = explode("\n", $contents);
        $title = null;
        $headingPath = [];
        $currentSection = null;
        $currentBody = [];
        $sections = [];

        foreach ($lines as $line) {
            if (preg_match('/^(#{1,6})\s+(.+)\s*$/u', trim($line), $matches) === 1) {
                $level = strlen($matches[1]);
                $heading = trim($matches[2]);

                if ($heading === '') {
                    throw new InvalidArgumentException('Encabezado de conocimiento de Numa vacio.');
                }

                if ($level === 1) {
                    if ($title !== null) {
                        throw new InvalidArgumentException('Documento de conocimiento de Numa con mas de un titulo principal.');
                    }

                    $title = $heading;
                    continue;
                }

                if ($title === null) {
                    throw new InvalidArgumentException('Documento de conocimiento de Numa sin titulo principal.');
                }

                $this->pushSection($sections, $currentSection, $currentBody);

                $headingPath = array_slice($headingPath, 0, $level - 2);
                $headingPath[$level - 2] = $heading;
                $currentSection = implode(' > ', $headingPath);
                $currentBody = [];

                continue;
            }

            if ($title !== null && $currentSection !== null) {
                $currentBody[] = rtrim($line);
            }
        }

        $this->pushSection($sections, $currentSection, $currentBody);

        if ($title === null) {
            throw new InvalidArgumentException('Documento de conocimiento de Numa sin titulo principal.');
        }

        if ($sections === []) {
            throw new InvalidArgumentException('Documento de conocimiento de Numa sin secciones fragmentables.');
        }

        return ['title' => $title, 'sections' => $sections];
    }

    /**
     * @param array<int, array{section: string, body: string}> $sections
     * @param array<int, string> $bodyLines
     */
    private function pushSection(array &$sections, ?string $section, array $bodyLines): void
    {
        if ($section === null) {
            return;
        }

        $body = $this->normalizeText(implode("\n", $bodyLines));

        if ($body === '') {
            throw new InvalidArgumentException('Seccion de conocimiento de Numa sin contenido.');
        }

        $sections[] = ['section' => $section, 'body' => $body];
    }

    /**
     * @param array<int, array{section: string, body: string}> $sections
     * @return array<int, NumaKnowledgeFragment>
     */
    private function buildFragments(
        string $document,
        string $title,
        string $route,
        array $sections,
        DateTimeImmutable $indexedAt,
    ): array {
        $fragments = [];

        foreach ($sections as $sectionData) {
            $parts = $this->splitBody($title, $sectionData['section'], $sectionData['body']);
            $sectionSlug = $this->slug($sectionData['section']);

            foreach ($parts as $index => $content) {
                $suffix = count($parts) === 1 ? '' : '-' . ($index + 1);
                $id = $this->slug(pathinfo($document, PATHINFO_FILENAME)) . ':' . $sectionSlug . $suffix;

                $fragments[] = new NumaKnowledgeFragment(
                    $id,
                    $document,
                    $title,
                    $sectionData['section'],
                    $route,
                    $content,
                    hash('sha256', $content),
                    $indexedAt
                );
            }
        }

        return $fragments;
    }

    /**
     * @return array<int, string>
     */
    private function splitBody(string $title, string $section, string $body): array
    {
        $blocks = preg_split('/\n{2,}/u', $body) ?: [];
        $parts = [];
        $current = '';

        foreach ($blocks as $block) {
            $block = $this->normalizeText($block);

            if ($block === '') {
                continue;
            }

            $candidateBody = $current === '' ? $block : $current . "\n\n" . $block;
            $candidateContent = $this->formatContent($title, $section, $candidateBody);

            if ($this->length($candidateContent) <= $this->maxContentChars) {
                $current = $candidateBody;
                continue;
            }

            if ($current !== '') {
                $parts[] = $this->formatContent($title, $section, $current);
                $current = '';
            }

            $singleBlockContent = $this->formatContent($title, $section, $block);
            if ($this->length($singleBlockContent) > $this->maxContentChars) {
                throw new InvalidArgumentException('Bloque de conocimiento de Numa supera el limite de 900 caracteres.');
            }

            $current = $block;
        }

        if ($current !== '') {
            $parts[] = $this->formatContent($title, $section, $current);
        }

        if ($parts === []) {
            throw new InvalidArgumentException('Seccion de conocimiento de Numa sin contenido fragmentable.');
        }

        return $parts;
    }

    private function formatContent(string $title, string $section, string $body): string
    {
        return $this->normalizeText($title . "\n" . $section . "\n\n" . $body);
    }

    private function normalizeText(string $text): string
    {
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function slug(string $value): string
    {
        $value = $this->removeAccents($value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
        $value = trim($value, '-');

        if ($value === '') {
            throw new InvalidArgumentException('Identificador de fragmento de Numa invalido.');
        }

        return $value;
    }

    private function removeAccents(string $value): string
    {
        return strtr($value, [
            'á' => 'a',
            'à' => 'a',
            'ä' => 'a',
            'â' => 'a',
            'Á' => 'a',
            'À' => 'a',
            'Ä' => 'a',
            'Â' => 'a',
            'é' => 'e',
            'è' => 'e',
            'ë' => 'e',
            'ê' => 'e',
            'É' => 'e',
            'È' => 'e',
            'Ë' => 'e',
            'Ê' => 'e',
            'í' => 'i',
            'ì' => 'i',
            'ï' => 'i',
            'î' => 'i',
            'Í' => 'i',
            'Ì' => 'i',
            'Ï' => 'i',
            'Î' => 'i',
            'ó' => 'o',
            'ò' => 'o',
            'ö' => 'o',
            'ô' => 'o',
            'Ó' => 'o',
            'Ò' => 'o',
            'Ö' => 'o',
            'Ô' => 'o',
            'ú' => 'u',
            'ù' => 'u',
            'ü' => 'u',
            'û' => 'u',
            'Ú' => 'u',
            'Ù' => 'u',
            'Ü' => 'u',
            'Û' => 'u',
            'ñ' => 'n',
            'Ñ' => 'n',
        ]);
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}

final class NumaKnowledgeIndexSummary
{
    public function __construct(
        public readonly int $documents,
        public readonly int $fragments,
        public readonly int $created,
        public readonly int $updated,
        public readonly int $unchanged,
        public readonly int $deleted,
        public readonly int $embeddingsGenerated,
    ) {
    }
}

final class NumaKnowledgeSearchResult
{
    public function __construct(
        private readonly string $fragmentId,
        private readonly string $document,
        private readonly string $title,
        private readonly string $section,
        private readonly string $route,
        private readonly string $content,
        private readonly float $similarity,
    ) {
        foreach ([$fragmentId, $document, $title, $section, $route, $content] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Resultado de conocimiento de Numa incompleto.');
            }
        }

        if ($route[0] !== '/') {
            throw new InvalidArgumentException('Ruta de conocimiento de Numa invalida.');
        }
    }

    public function fragmentId(): string
    {
        return $this->fragmentId;
    }

    public function document(): string
    {
        return $this->document;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function section(): string
    {
        return $this->section;
    }

    public function route(): string
    {
        return $this->route;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function similarity(): float
    {
        return $this->similarity;
    }

    /**
     * @return array<string, string|float>
     */
    public function toArray(): array
    {
        return [
            'fragment_id' => $this->fragmentId,
            'document' => $this->document,
            'title' => $this->title,
            'section' => $this->section,
            'route' => $this->route,
            'content' => $this->content,
            'similarity' => $this->similarity,
        ];
    }
}

final class NumaVectorSimilarity
{
    /**
     * @param array<int, float|int> $left
     * @param array<int, float|int> $right
     */
    public static function cosine(array $left, array $right): float
    {
        if ($left === [] || count($left) !== count($right)) {
            throw new InvalidArgumentException('Los vectores de Numa deben tener las mismas dimensiones.');
        }

        $dotProduct = 0.0;
        $leftMagnitude = 0.0;
        $rightMagnitude = 0.0;

        $leftValues = array_values($left);
        $rightValues = array_values($right);

        foreach ($leftValues as $index => $leftValue) {
            $rightValue = $rightValues[$index];

            if ((!is_float($leftValue) && !is_int($leftValue)) || (!is_float($rightValue) && !is_int($rightValue))) {
                throw new InvalidArgumentException('Los vectores de Numa solo pueden contener numeros.');
            }

            $leftFloat = (float) $leftValue;
            $rightFloat = (float) $rightValue;

            $dotProduct += $leftFloat * $rightFloat;
            $leftMagnitude += $leftFloat * $leftFloat;
            $rightMagnitude += $rightFloat * $rightFloat;
        }

        if ($leftMagnitude <= 0.0 || $rightMagnitude <= 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($leftMagnitude) * sqrt($rightMagnitude));
    }
}

final class NumaKnowledgeSearcher
{
    public function __construct(
        private readonly PDO $connection,
        private readonly NumaEmbeddingProviderInterface $embeddingProvider,
        private readonly int $dimensions = 768,
        private readonly int $maxResults = 3,
        private readonly float $minSimilarity = 0.65,
    ) {
        if ($dimensions <= 0 || $maxResults <= 0 || $minSimilarity < -1.0 || $minSimilarity > 1.0) {
            throw new InvalidArgumentException('Configuracion de busqueda semantica de Numa invalida.');
        }
    }

    /**
     * @return array<int, NumaKnowledgeSearchResult>
     */
    public function search(string $knowledgeQuery): array
    {
        $knowledgeQuery = $this->normalizeKnowledgeQuery($knowledgeQuery);
        $queryEmbedding = $this->embeddingProvider->embed($knowledgeQuery);
        $this->validateEmbedding($queryEmbedding);

        $resultsByHash = [];

        foreach ($this->candidates() as $candidate) {
            $embedding = $this->decodeEmbedding((string) $candidate['embedding']);
            $similarity = NumaVectorSimilarity::cosine($queryEmbedding, $embedding);

            if ($similarity < $this->minSimilarity) {
                continue;
            }

            $hash = (string) $candidate['hash'];
            $result = new NumaKnowledgeSearchResult(
                (string) $candidate['fragmento_id'],
                (string) $candidate['documento'],
                (string) $candidate['titulo'],
                (string) $candidate['seccion'],
                (string) $candidate['ruta'],
                (string) $candidate['contenido'],
                $similarity
            );

            if (!isset($resultsByHash[$hash]) || $similarity > $resultsByHash[$hash]->similarity()) {
                $resultsByHash[$hash] = $result;
            }
        }

        $results = array_values($resultsByHash);
        usort($results, static function (NumaKnowledgeSearchResult $left, NumaKnowledgeSearchResult $right): int {
            $similarityOrder = $right->similarity() <=> $left->similarity();

            if ($similarityOrder !== 0) {
                return $similarityOrder;
            }

            return [$left->document(), $left->section(), $left->fragmentId()]
                <=> [$right->document(), $right->section(), $right->fragmentId()];
        });

        return array_slice($results, 0, $this->maxResults);
    }

    private function normalizeKnowledgeQuery(string $knowledgeQuery): string
    {
        $this->rejectStructuredPrivatePayload($knowledgeQuery);

        $documentaryParts = $this->documentaryParts($knowledgeQuery);
        if ($documentaryParts !== []) {
            $knowledgeQuery = implode(' ', $documentaryParts);
        } elseif ($this->containsPrivateData($knowledgeQuery)) {
            throw new InvalidArgumentException('La consulta documental de Numa no puede incluir datos privados.');
        }

        $knowledgeQuery = $this->removePrivateData($knowledgeQuery);
        $knowledgeQuery = preg_replace('/\s+/u', ' ', $knowledgeQuery) ?? $knowledgeQuery;
        $knowledgeQuery = trim($knowledgeQuery, " \t\n\r\0\x0B¿?¡!.,;:");

        if ($knowledgeQuery === '') {
            throw new InvalidArgumentException('La consulta documental de Numa no puede estar vacia.');
        }

        return $knowledgeQuery;
    }

    private function rejectStructuredPrivatePayload(string $knowledgeQuery): void
    {
        if (preg_match('/[{\[]/u', $knowledgeQuery) !== 1) {
            return;
        }

        if (preg_match('/"(?:tool|tools|resultado|result|ingresos|gastos|importe|usuario_id|user_id|periodo|categorias?)"\s*:/iu', $knowledgeQuery) === 1) {
            throw new InvalidArgumentException('La consulta documental de Numa no puede incluir resultados de tools.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function documentaryParts(string $knowledgeQuery): array
    {
        $knowledgeQuery = preg_replace('/\s+/u', ' ', $knowledgeQuery) ?? $knowledgeQuery;
        $segments = preg_split(
            '/(?:[.?!;]+|\s+y\s+(?=(?:cu[aá]nt|cu[aá]l|en\s+qu[eé]|compara|comparar|expl[ií]came|explicame|qu[eé]\s+(?:es|son)|que\s+(?:es|son)|c[oó]mo|como|diferencia|significa|gast[eé]|gaste|ingres[eé]|ingrese|ahorr[eé]|ahorre|mis?\s+)))/iu',
            $knowledgeQuery
        ) ?: [];

        $parts = [];
        foreach ($segments as $segment) {
            $segment = trim((string) $segment, " \t\n\r\0\x0B¿?¡!.,;:");

            if ($segment === '') {
                continue;
            }

            if (!$this->isDocumentarySegment($segment)) {
                continue;
            }

            $parts[] = $this->removePrivateAnalysisTail($segment);
        }

        return array_values(array_filter($parts, static fn (string $part): bool => trim($part) !== ''));
    }

    private function isDocumentarySegment(string $segment): bool
    {
        return preg_match('/\b(?:qu[eé]\s+(?:es|son|significa)|que\s+(?:es|son|significa)|significa|diferencia|c[oó]mo\s+(?:funciona|anadir|añadir|agregar|crear|usar|editar|eliminar|registrar)|como\s+(?:funciona|anadir|agregar|crear|usar|editar|eliminar|registrar)|expl[ií]came|explicame|benehom|dashboard|movimientos?|gastos?\s+(?:esenciales?|flexibles?)|ahorro\s+(?:posible|real)|metas?|proyecciones?|cuenta)\b/iu', $segment) === 1;
    }

    private function containsPrivateData(string $knowledgeQuery): bool
    {
        return preg_match('/\b(?:usuario_id|user_id|usuario|user)\s*[:=#-]?\s*\d+\b/iu', $knowledgeQuery) === 1
            || preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu', $knowledgeQuery) === 1
            || preg_match('/\b\d+(?:[.,]\d+)?\s*(?:€|eur|euros?|d[oó]lares?|usd)\b/iu', $knowledgeQuery) === 1
            || preg_match('/\b(?:cu[aá]nt[oa]s?|cu[aá]l(?:es)?|en\s+qu[eé]\s+categor[ií]a|promedio|total|ranking|compara)\b.*\b(?:gast[eé]|gaste|gastado|gastos?|ingres[eé]|ingrese|ingresos?|ahorr[eé]|ahorre|ahorro|movimientos?|categor[ií]a|mes|a[nñ]o|semana)\b/iu', $knowledgeQuery) === 1
            || preg_match('/\b(?:mi|mis|nuestro|nuestra|nuestros|nuestras)\s+(?:gastos|ingresos|movimientos|datos|categor[ií]as|ahorros?)\b/iu', $knowledgeQuery) === 1;
    }

    private function removePrivateAnalysisTail(string $segment): string
    {
        $segment = preg_replace('/\s+(?:y|,|;)\s+(?:cu[aá]nt[oa]s?|cu[aá]l(?:es)?|en\s+qu[eé]|compara|comparar|dime\s+cu[aá]nto|gast[eé]|gaste|ingres[eé]|ingrese|ahorr[eé]|ahorre|pagu[eé]|pague|mis?\s+(?:gastos|ingresos|movimientos|datos|categor[ií]as)).*$/iu', '', $segment) ?? $segment;

        return trim($segment, " \t\n\r\0\x0B¿?¡!.,;:");
    }

    private function removePrivateData(string $knowledgeQuery): string
    {
        $patterns = [
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu',
            '/\b(?:usuario_id|user_id|usuario|user|id)\s*[:=#-]?\s*\d+\b/iu',
            '/\b\d{4}-\d{2}-\d{2}\b/u',
            '/\b\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}\b/u',
            '/\b\d+(?:[.,]\d+)?\s*(?:€|eur|euros?|d[oó]lares?|usd)\b/iu',
            '/\b(?:me llamo|mi nombre es|soy)\s+\p{Lu}[\p{L}\' -]{1,80}(?=$|[.,;?])/u',
            '/\b(?:nombre|titular|persona)\s*[:=]?\s+\p{Lu}[\p{L}\' -]{1,80}(?=$|[.,;?])/u',
            '/\b(?:mi|mis|m[ií]o|m[ií]a|m[ií]os|m[ií]as|nuestro|nuestra|nuestros|nuestras)\b/iu',
            '/\b(?:este|esta|ese|esa|aquel|aquella|el)\s+(?:mes|a[nñ]o|semana|trimestre)\b/iu',
            '/\bmes\s+anterior\b/iu',
        ];

        foreach ($patterns as $pattern) {
            $knowledgeQuery = preg_replace($pattern, ' ', $knowledgeQuery) ?? $knowledgeQuery;
        }

        return $knowledgeQuery;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function candidates(): array
    {
        // La base inicial es pequena; si crece de forma significativa, esta carga en memoria debera revisarse.
        $stmt = $this->connection->prepare(
            'SELECT fragmento_id, documento, titulo, seccion, ruta, contenido, hash, embedding
             FROM numa_conocimiento
             WHERE dimensiones = :dimensiones'
        );
        $stmt->execute([':dimensiones' => $this->dimensions]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, float>
     */
    private function decodeEmbedding(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Embedding de conocimiento de Numa no legible.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Embedding de conocimiento de Numa invalido.');
        }

        $embedding = [];
        foreach ($decoded as $value) {
            if (!is_float($value) && !is_int($value)) {
                throw new RuntimeException('Embedding de conocimiento de Numa invalido.');
            }

            $embedding[] = (float) $value;
        }

        $this->validateEmbedding($embedding);

        return $embedding;
    }

    /**
     * @param array<int, float|int> $embedding
     */
    private function validateEmbedding(array $embedding): void
    {
        if (count($embedding) !== $this->dimensions) {
            throw new RuntimeException('Embedding de conocimiento de Numa con dimensiones invalidas.');
        }

        foreach ($embedding as $value) {
            if (!is_float($value) && !is_int($value)) {
                throw new RuntimeException('Embedding de conocimiento de Numa invalido.');
            }
        }
    }
}

final class NumaKnowledgeIndexer
{
    public function __construct(
        private readonly PDO $connection,
        private readonly NumaEmbeddingProviderInterface $embeddingProvider,
        private readonly NumaKnowledgeFragmenter $fragmenter = new NumaKnowledgeFragmenter(),
        private readonly int $dimensions = 768,
    ) {
        if ($dimensions <= 0) {
            throw new InvalidArgumentException('La dimension de embeddings de Numa es invalida.');
        }
    }

    public function indexDirectory(string $directory, ?DateTimeImmutable $indexedAt = null): NumaKnowledgeIndexSummary
    {
        $indexedAt ??= new DateTimeImmutable('now');
        $documents = $this->markdownDocuments($directory);
        $fragments = $this->fragmenter->fragmentDirectory($directory, $indexedAt);

        if ($fragments === []) {
            throw new RuntimeException('No hay fragmentos de conocimiento de Numa para indexar.');
        }

        $this->ensureUniqueFragmentIds($fragments);

        $existing = $this->existingFragments();
        $records = [];
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $embeddingsGenerated = 0;

        foreach ($fragments as $fragment) {
            $current = $existing[$fragment->id()] ?? null;
            $mustGenerateEmbedding = $current === null
                || $current['hash'] !== $fragment->hash()
                || $current['dimensiones'] !== $this->dimensions;

            if (!$mustGenerateEmbedding) {
                $unchanged++;
                continue;
            }

            $embedding = $this->embeddingProvider->embed($fragment->content());
            $this->validateEmbedding($embedding);

            $records[] = [
                'fragment' => $fragment,
                'embedding' => $embedding,
            ];
            $embeddingsGenerated++;

            if ($current === null) {
                $created++;
            } else {
                $updated++;
            }
        }

        $deleted = $this->obsoleteCount(array_map(
            static fn (NumaKnowledgeFragment $fragment): string => $fragment->id(),
            $fragments
        ));

        $this->persist($records, $fragments);

        return new NumaKnowledgeIndexSummary(
            count($documents),
            count($fragments),
            $created,
            $updated,
            $unchanged,
            $deleted,
            $embeddingsGenerated
        );
    }

    /**
     * @return array<int, string>
     */
    private function markdownDocuments(string $directory): array
    {
        if (!is_dir($directory) || !is_readable($directory)) {
            throw new InvalidArgumentException('Directorio de conocimiento de Numa no legible.');
        }

        $paths = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.md') ?: [];
        sort($paths, SORT_STRING);

        return $paths;
    }

    /**
     * @param array<int, NumaKnowledgeFragment> $fragments
     */
    private function ensureUniqueFragmentIds(array $fragments): void
    {
        $seen = [];

        foreach ($fragments as $fragment) {
            if (isset($seen[$fragment->id()])) {
                throw new RuntimeException('Identificador de fragmento de Numa duplicado.');
            }

            $seen[$fragment->id()] = true;
        }
    }

    /**
     * @return array<string, array{hash:string, dimensiones:int}>
     */
    private function existingFragments(): array
    {
        $stmt = $this->connection->query('SELECT fragmento_id, hash, dimensiones FROM numa_conocimiento');

        if ($stmt === false) {
            throw new RuntimeException('No se pudo leer el indice de conocimiento de Numa.');
        }

        $rows = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!isset($row['fragmento_id'], $row['hash'], $row['dimensiones'])) {
                continue;
            }

            $rows[(string) $row['fragmento_id']] = [
                'hash' => (string) $row['hash'],
                'dimensiones' => (int) $row['dimensiones'],
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, float> $embedding
     */
    private function validateEmbedding(array $embedding): void
    {
        if (count($embedding) !== $this->dimensions) {
            throw new RuntimeException('Embedding de conocimiento de Numa con dimensiones invalidas.');
        }

        foreach ($embedding as $value) {
            if (!is_float($value) && !is_int($value)) {
                throw new RuntimeException('Embedding de conocimiento de Numa invalido.');
            }
        }
    }

    /**
     * @param array<int, string> $currentIds
     */
    private function obsoleteCount(array $currentIds): int
    {
        if ($currentIds === []) {
            $stmt = $this->connection->query('SELECT COUNT(*) FROM numa_conocimiento');

            return $stmt === false ? 0 : (int) $stmt->fetchColumn();
        }

        $placeholders = implode(',', array_fill(0, count($currentIds), '?'));
        $stmt = $this->connection->prepare(
            'SELECT COUNT(*) FROM numa_conocimiento WHERE fragmento_id NOT IN (' . $placeholders . ')'
        );
        $stmt->execute(array_values($currentIds));

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<int, array{fragment:NumaKnowledgeFragment, embedding:array<int, float>}> $records
     * @param array<int, NumaKnowledgeFragment> $fragments
     */
    private function persist(array $records, array $fragments): void
    {
        $started = !$this->connection->inTransaction();

        if ($started) {
            $this->connection->beginTransaction();
        }

        try {
            foreach ($records as $record) {
                $this->upsertFragment($record['fragment'], $record['embedding']);
            }

            $this->deleteObsolete(array_map(
                static fn (NumaKnowledgeFragment $fragment): string => $fragment->id(),
                $fragments
            ));

            if ($started) {
                $this->connection->commit();
            }
        } catch (Throwable $exception) {
            if ($started && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<int, float> $embedding
     */
    private function upsertFragment(NumaKnowledgeFragment $fragment, array $embedding): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO numa_conocimiento
                (fragmento_id, documento, titulo, seccion, ruta, contenido, hash, embedding, dimensiones, indexed_at)
             VALUES
                (:fragmento_id, :documento, :titulo, :seccion, :ruta, :contenido, :hash, :embedding, :dimensiones, :indexed_at)
             ON DUPLICATE KEY UPDATE
                documento = VALUES(documento),
                titulo = VALUES(titulo),
                seccion = VALUES(seccion),
                ruta = VALUES(ruta),
                contenido = VALUES(contenido),
                hash = VALUES(hash),
                embedding = VALUES(embedding),
                dimensiones = VALUES(dimensiones),
                indexed_at = VALUES(indexed_at)'
        );
        $stmt->execute([
            ':fragmento_id' => $fragment->id(),
            ':documento' => $fragment->document(),
            ':titulo' => $fragment->title(),
            ':seccion' => $fragment->section(),
            ':ruta' => $fragment->route(),
            ':contenido' => $fragment->content(),
            ':hash' => $fragment->hash(),
            ':embedding' => json_encode(array_values($embedding), JSON_THROW_ON_ERROR),
            ':dimensiones' => $this->dimensions,
            ':indexed_at' => $fragment->indexedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<int, string> $currentIds
     */
    private function deleteObsolete(array $currentIds): void
    {
        if ($currentIds === []) {
            $this->connection->exec('DELETE FROM numa_conocimiento');

            return;
        }

        $placeholders = implode(',', array_fill(0, count($currentIds), '?'));
        $stmt = $this->connection->prepare(
            'DELETE FROM numa_conocimiento WHERE fragmento_id NOT IN (' . $placeholders . ')'
        );
        $stmt->execute(array_values($currentIds));
    }
}
