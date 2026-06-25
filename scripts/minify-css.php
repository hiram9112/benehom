<?php

declare(strict_types=1);

use MatthiasMullie\Minify;

$basePath = dirname(__DIR__);
$autoloadPath = $basePath . '/vendor/autoload.php';
$sourceDir = $basePath . '/public/css/src';
$outputFile = $basePath . '/public/css/app.min.css';

$cssFiles = [
    'base.css',
    'layout.css',
    'components.css',
    'dashboard.css',
    'proyecciones.css',
    'auth.css',
    'home.css',
    'blog.css',
    'cuenta.css',
    'legal.css',
    'responsive.css',
];

if (!is_file($autoloadPath)) {
    fwrite(STDERR, "Composer autoload not found. Run composer install first.\n");
    exit(1);
}

require $autoloadPath;

$minifier = new Minify\CSS();

foreach ($cssFiles as $cssFile) {
    $sourceFile = $sourceDir . '/' . $cssFile;

    if (!is_file($sourceFile)) {
        fwrite(STDERR, "Missing CSS source file: {$sourceFile}\n");
        exit(1);
    }

    $minifier->add($sourceFile);
}

try {
    $minifiedCss = $minifier->minify();
} catch (Throwable $exception) {
    fwrite(STDERR, "CSS minification failed: {$exception->getMessage()}\n");
    exit(1);
}

$header = "/* Generated file. Do not edit manually. Run composer build:css. */\n";
$temporaryFile = $outputFile . '.tmp';

if (file_put_contents($temporaryFile, $header . $minifiedCss . PHP_EOL) === false) {
    fwrite(STDERR, "Unable to write temporary CSS file: {$temporaryFile}\n");
    exit(1);
}

if (!rename($temporaryFile, $outputFile)) {
    @unlink($temporaryFile);
    fwrite(STDERR, "Unable to replace CSS output file: {$outputFile}\n");
    exit(1);
}

fwrite(STDOUT, "Generated public/css/app.min.css from " . count($cssFiles) . " source files.\n");
