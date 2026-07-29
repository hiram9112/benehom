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
