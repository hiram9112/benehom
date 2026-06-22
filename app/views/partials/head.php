<?php

function bh_document_meta_content(string $robots): string
{
    return $robots === 'noindex' ? 'noindex, nofollow' : 'index, follow';
}

function bh_document_title(string $title): string
{
    $title = trim($title);

    if ($title === '') {
        return 'BeneHom';
    }

    return $title . ' | BeneHom';
}

function bh_render_json_ld($jsonLd): void
{
    if (empty($jsonLd)) {
        return;
    }

    $items = array_is_list($jsonLd) ? $jsonLd : [$jsonLd];

    foreach ($items as $item) {
        if (!is_array($item) || empty($item)) {
            continue;
        }

        $json = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        if ($json === false) {
            continue;
        }
        ?>
    <script type="application/ld+json"><?= $json ?></script>
        <?php
    }
}

function bh_document_begin(array $opciones = []): void
{
    $title = (string) ($opciones['title'] ?? '');
    $documentTitle = bh_document_title($title);
    $description = (string) ($opciones['description'] ?? 'BeneHom ayuda a organizar ingresos, gastos, ahorro real y metas económicas del hogar con claridad.');
    $canonical = (string) ($opciones['canonical'] ?? bh_url());
    $ogType = (string) ($opciones['og_type'] ?? 'website');
    $ogTitle = (string) ($opciones['og_title'] ?? $documentTitle);
    $ogDescription = (string) ($opciones['og_description'] ?? $description);
    $ogImage = (string) ($opciones['og_image'] ?? bh_url('img/og-image.png'));
    $twitterCard = (string) ($opciones['twitter_card'] ?? 'summary_large_image');
    $twitterTitle = (string) ($opciones['twitter_title'] ?? $ogTitle);
    $twitterDescription = (string) ($opciones['twitter_description'] ?? $ogDescription);
    $articlePublishedTime = trim((string) ($opciones['article_published_time'] ?? ''));
    $articleModifiedTime = trim((string) ($opciones['article_modified_time'] ?? ''));
    $articleSection = trim((string) ($opciones['article_section'] ?? ''));
    $robots = (string) ($opciones['robots'] ?? 'index');
    $bodyClass = trim((string) ($opciones['body_class'] ?? ''));
    $headExtra = (string) ($opciones['head_extra'] ?? '');
    ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="<?= htmlspecialchars(bh_document_meta_content($robots), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>">

    <meta property="og:title" content="<?= htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="<?= htmlspecialchars($ogType, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:locale" content="es_ES">
<?php if ($ogType === 'article' && $articlePublishedTime !== ''): ?>
    <meta property="article:published_time" content="<?= htmlspecialchars($articlePublishedTime, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<?php if ($ogType === 'article' && $articleModifiedTime !== ''): ?>
    <meta property="article:modified_time" content="<?= htmlspecialchars($articleModifiedTime, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<?php if ($ogType === 'article' && $articleSection !== ''): ?>
    <meta property="article:section" content="<?= htmlspecialchars($articleSection, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>

    <meta name="twitter:card" content="<?= htmlspecialchars($twitterCard, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($twitterTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($twitterDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>">

    <title><?= htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="icon" type="image/png" href="<?= BASE_URL ?>img/og-image.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/custom.css">
<?php if ($headExtra !== ''): ?>
<?= $headExtra ?>
<?php endif; ?>
<?php bh_render_json_ld($opciones['json_ld'] ?? []); ?>
</head>

<body<?= $bodyClass !== '' ? ' class="' . htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
    <a class="bh-skip-link" href="#contenido">Saltar al contenido</a>
    <?php
}

function bh_document_end(array $opciones = []): void
{
    $includeBootstrapJs = (bool) ($opciones['include_bootstrap_js'] ?? false);
    $includeFlashJs = (bool) ($opciones['include_flash_js'] ?? false);
    $bodyEndExtra = (string) ($opciones['body_end_extra'] ?? '');
    ?>
<?php if ($includeBootstrapJs): ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>
<?php if ($includeFlashJs): ?>
    <script src="<?= BASE_URL ?>js/flash.js"></script>
<?php endif; ?>
<?php if ($bodyEndExtra !== ''): ?>
<?= $bodyEndExtra ?>
<?php endif; ?>
</body>

</html>
    <?php
}
