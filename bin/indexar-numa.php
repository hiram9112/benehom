#!/usr/bin/env php
<?php

declare(strict_types=1);

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));
defined('APP_PATH') || define('APP_PATH', BASE_PATH . '/app');
defined('CONFIG_PATH') || define('CONFIG_PATH', BASE_PATH . '/config');
defined('BASE_URL') || define('BASE_URL', '/');

date_default_timezone_set('Europe/Madrid');

$envPath = BASE_PATH . '/.env';
if (is_file($envPath) && is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }
}

require_once APP_PATH . '/helpers/utils.php';
require_once APP_PATH . '/models/Database.php';
require_once APP_PATH . '/models/NumaConsumoGlobal.php';
require_once APP_PATH . '/services/NumaEmbeddingProvider.php';
require_once APP_PATH . '/services/NumaKnowledge.php';
require_once APP_PATH . '/services/GeminiEmbeddingProvider.php';

try {
    $dimensions = bh_env_int('NUMA_EMBEDDING_DIMENSIONS', 768);
    $embeddingProvider = new NumaMeteredEmbeddingProvider(
        NumaEmbeddingProviderFactory::fromEnvironment(),
        NumaConsumoGlobal::forEmbedding(Database::getConnection())
    );
    $indexer = new NumaKnowledgeIndexer(
        Database::getConnection(),
        $embeddingProvider,
        new NumaKnowledgeFragmenter(maxContentChars: bh_env_int('NUMA_MAX_RAG_CHUNK_CHARS', 900)),
        $dimensions
    );

    $summary = $indexer->indexDirectory(BASE_PATH . '/knowledge/numa');

    fwrite(STDOUT, "Indexacion de Numa completada.\n");
    fwrite(STDOUT, 'Documentos leidos: ' . $summary->documents . "\n");
    fwrite(STDOUT, 'Fragmentos: ' . $summary->fragments . "\n");
    fwrite(STDOUT, 'Creados: ' . $summary->created . "\n");
    fwrite(STDOUT, 'Actualizados: ' . $summary->updated . "\n");
    fwrite(STDOUT, 'Sin cambios: ' . $summary->unchanged . "\n");
    fwrite(STDOUT, 'Obsoletos eliminados: ' . $summary->deleted . "\n");
    fwrite(STDOUT, 'Embeddings generados: ' . $summary->embeddingsGenerated . "\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'No se pudo indexar el conocimiento de Numa: ' . $exception->getMessage() . "\n");
    exit(1);
}
