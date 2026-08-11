<?php

declare(strict_types=1);

final class NumaConversation
{
    private const SESSION_KEY = 'numa_conversation';
    private const MAX_VISIBLE_ENTRIES = 100;

    /**
     * @return array<int, array{role:string,message:string,period:array<string,string>|null}>
     */
    public function transcript(): array
    {
        return array_map(static function (array $entry): array {
            return [
                'role' => $entry['role'],
                'message' => $entry['message'],
                'period' => $entry['period'],
            ];
        }, $this->entries());
    }

    /**
     * @return array<int, array{role:string,message:string}>
     */
    public function context(): array
    {
        $context = [];

        foreach ($this->entries() as $entry) {
            if (!$entry['include_in_context']) {
                continue;
            }

            $context[] = [
                'role' => $entry['role'],
                'message' => $entry['message'],
            ];
        }

        return $context;
    }

    /**
     * @param array<int, array{title:string,section:string,url:string}> $sources
     * @param array<string, string>|null $period
     */
    public function appendExchange(
        string $userMessage,
        string $assistantMessage,
        array $sources = [],
        ?array $period = null,
        bool $includeInContext = true,
    ): void {
        $entries = $this->entries();
        $entries[] = $this->entry('user', $userMessage, [], null, $includeInContext);
        $entries[] = $this->entry('assistant', $assistantMessage, $sources, $period, $includeInContext);

        while (count($entries) > self::MAX_VISIBLE_ENTRIES) {
            $displayOnlyPair = $this->firstDisplayOnlyPair($entries);
            if ($displayOnlyPair === null) {
                break;
            }

            array_splice($entries, $displayOnlyPair, 2);
        }

        $_SESSION[self::SESSION_KEY] = ['entries' => $entries];
    }

    public function clear(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * @return array<int, array{role:string,message:string,sources:array<int,array{title:string,section:string,url:string}>,period:array<string,string>|null,include_in_context:bool}>
     */
    private function entries(): array
    {
        $stored = $_SESSION[self::SESSION_KEY]['entries'] ?? null;
        if (!is_array($stored) || !array_is_list($stored)) {
            return [];
        }

        $entries = [];
        foreach ($stored as $entry) {
            $normalized = $this->normalizeEntry($entry);
            if ($normalized !== null) {
                $entries[] = $normalized;
            }
        }

        return $entries;
    }

    /**
     * @param mixed $entry
     * @return array{role:string,message:string,sources:array<int,array{title:string,section:string,url:string}>,period:array<string,string>|null,include_in_context:bool}|null
     */
    private function normalizeEntry($entry): ?array
    {
        if (!is_array($entry)) {
            return null;
        }

        $role = $entry['role'] ?? null;
        $message = $entry['message'] ?? null;
        if (!is_string($role) || !in_array($role, ['user', 'assistant'], true) || !is_string($message) || trim($message) === '') {
            return null;
        }

        return $this->entry(
            $role,
            $message,
            is_array($entry['sources'] ?? null) ? $entry['sources'] : [],
            is_array($entry['period'] ?? null) ? $entry['period'] : null,
            ($entry['include_in_context'] ?? false) === true,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $sources
     * @param array<string, mixed>|null $period
     * @return array{role:string,message:string,sources:array<int,array{title:string,section:string,url:string}>,period:array<string,string>|null,include_in_context:bool}
     */
    private function entry(
        string $role,
        string $message,
        array $sources,
        ?array $period,
        bool $includeInContext,
    ): array {
        $safeSources = [];
        foreach (array_slice($sources, 0, 3) as $source) {
            if (!is_array($source)) {
                continue;
            }

            $title = $source['title'] ?? null;
            $section = $source['section'] ?? null;
            $url = $source['url'] ?? null;
            if (is_string($title) && is_string($section) && is_string($url)) {
                $safeSources[] = ['title' => $title, 'section' => $section, 'url' => $url];
            }
        }

        $safePeriod = null;
        if (is_string($period['start'] ?? null) && is_string($period['end'] ?? null)) {
            $safePeriod = ['start' => $period['start'], 'end' => $period['end']];
        }

        return [
            'role' => $role,
            'message' => trim($message),
            'sources' => $safeSources,
            'period' => $safePeriod,
            'include_in_context' => $includeInContext,
        ];
    }

    /**
     * @param array<int, array{include_in_context:bool}> $entries
     */
    private function firstDisplayOnlyPair(array $entries): ?int
    {
        for ($index = 0, $last = count($entries) - 1; $index < $last; $index++) {
            if (!$entries[$index]['include_in_context'] && !$entries[$index + 1]['include_in_context']) {
                return $index;
            }
        }

        return null;
    }
}
