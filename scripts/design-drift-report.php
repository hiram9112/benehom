<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$paths = [
    $basePath . '/app',
    $basePath . '/config',
    $basePath . '/public/css/src',
    $basePath . '/public/js',
];

$checks = [
    'hex_fuera_allowlist' => '/#[0-9a-fA-F]{3,8}\b/',
    'tokens_fantasia' => '/--bh-color-(cream|ivory|sage|sand|clay|cacao|stone)[\w-]*/',
    'expense_alias' => '/--bh-expense(?:-soft)?\b/',
    'accent_alias' => '/--bh-accent\b/',
    'rgba_brand_lime' => '/rgba\((22,\s*63,\s*127|62,\s*178,\s*37)\b/',
    'font_heading_alias' => '/--bh-font-heading\b/',
    'bootstrap_icons' => '/\bbi-[a-z0-9-]+\b|bootstrap-icons/i',
];

$allowlist = [
    'hex_fuera_allowlist' => [
        'public/css/src/base.css',
        'app/views/home.php',
        'app/views/partials/head.php',
        'public/js/chart-theme.js',
    ],
    'rgba_brand_lime' => [
        'public/css/src/base.css',
        'app/views/home.php',
    ],
];

function relativePath(string $basePath, string $path): string
{
    return ltrim(str_replace($basePath, '', $path), '/');
}

function iterFiles(array $paths): Generator
{
    foreach ($paths as $path) {
        if (!is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/\.(php|css|js)$/', $file->getFilename()) === 1) {
                yield $file->getPathname();
            }
        }
    }
}

$findings = [];

foreach (iterFiles($paths) as $file) {
    $relative = relativePath($basePath, $file);
    $lines = file($file, FILE_IGNORE_NEW_LINES);

    if ($lines === false) {
        continue;
    }

    foreach ($lines as $index => $line) {
        foreach ($checks as $name => $pattern) {
            if (in_array($relative, $allowlist[$name] ?? [], true)) {
                continue;
            }

            if (preg_match($pattern, $line) !== 1) {
                continue;
            }

            $findings[] = [
                'check' => $name,
                'file' => $relative,
                'line' => $index + 1,
                'content' => trim($line),
            ];
        }
    }
}

echo "Design drift report (bloqueante Sprint 24 G)\n";
echo str_repeat('=', 45) . "\n";

if ($findings === []) {
    echo "Sin hallazgos.\n";
    exit(0);
}

foreach ($findings as $finding) {
    echo '[' . $finding['check'] . '] ' . $finding['file'] . ':' . $finding['line'] . ' ' . $finding['content'] . "\n";
}

echo "\nTotal: " . count($findings) . " hallazgos bloqueantes.\n";
exit(1);
