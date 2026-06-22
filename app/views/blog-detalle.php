<?php
require_once APP_PATH . '/views/partials/head.php';

$bhArticuloSlug = (string) ($articulo['slug'] ?? '');

bh_document_begin([
    'title' => (string) ($articulo['titulo'] ?? 'Artículo del blog'),
    'description' => (string) ($articulo['resumen'] ?? 'Artículo educativo de BeneHom sobre economía familiar y decisiones financieras del hogar.'),
    'canonical' => bh_url('index.php?r=blog/detalle&slug=' . rawurlencode($bhArticuloSlug)),
    'og_type' => 'article',
    'robots' => 'index',
]);
?>
    <?php
    require_once APP_PATH . '/views/partials/app-navigation.php';
    bh_mobile_nav();

    $formatearFechaBlog = static function (string $fecha): string {
        return date('d/m/Y', strtotime($fecha));
    };
    ?>

    <div class="bh-app-shell">
        <?php bh_sidebar(); ?>

        <main id="contenido" class="bh-main bh-blog-detail-page">
            <article class="bh-blog-reading-shell">
                <header class="bh-blog-detail-hero">
                    <a class="bh-blog-back-link" href="index.php?r=blog/index">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i>
                        Volver al blog
                    </a>
                    <div class="bh-blog-detail-heading">
                        <span class="bh-blog-kicker">
                            <i class="bi <?= htmlspecialchars($articulo['icono'] ?? 'bi-journal-text', ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                            <?= htmlspecialchars($articulo['categoria'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <h1><?= htmlspecialchars($articulo['titulo'], ENT_QUOTES, 'UTF-8') ?></h1>
                        <p><?= htmlspecialchars($articulo['resumen'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="bh-blog-detail-meta" aria-label="Datos del artículo">
                        <p>
                            <span>Publicado</span>
                            <strong><?= htmlspecialchars($formatearFechaBlog($articulo['fecha']), ENT_QUOTES, 'UTF-8') ?></strong>
                        </p>
                        <p>
                            <span>Tiempo de lectura</span>
                            <strong><?= intval($articulo['lectura_min']) ?> min</strong>
                        </p>
                    </div>
                </header>

                <div class="bh-blog-reading-layout">
                    <aside class="bh-blog-article-map" aria-label="Contenido del artículo">
                        <span>En esta guía</span>
                        <ol>
                            <?php foreach (($articulo['contenido'] ?? []) as $seccion): ?>
                                <li><?= htmlspecialchars($seccion['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </aside>

                    <div class="bh-blog-article-content">
                        <?php foreach (($articulo['contenido'] ?? []) as $seccion): ?>
                            <section>
                                <h2><?= htmlspecialchars($seccion['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>
                                <?php foreach (($seccion['parrafos'] ?? []) as $parrafo): ?>
                                    <p><?= htmlspecialchars($parrafo, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endforeach; ?>
                            </section>
                        <?php endforeach; ?>

                        <aside class="bh-blog-product-note" aria-label="Cómo aplicar este artículo en BeneHom">
                            <div class="bh-blog-product-note-icon" aria-hidden="true">
                                <i class="bi bi-lightbulb"></i>
                            </div>
                            <div>
                                <h2>Cómo usarlo en BeneHom</h2>
                                <p><?= htmlspecialchars($articulo['conexion'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </aside>
                    </div>
                </div>
            </article>

            <?php if (!empty($articulosRelacionados)): ?>
                <section class="bh-blog-related" aria-labelledby="blog-related-title">
                    <div class="bh-blog-related-header">
                        <h2 id="blog-related-title">Seguir aprendiendo</h2>
                        <p>Otros conceptos que ayudan a leer mejor tus decisiones financieras.</p>
                    </div>
                    <div class="bh-blog-related-grid">
                        <?php foreach (array_slice($articulosRelacionados, 0, 2) as $relacionado): ?>
                            <a class="bh-card bh-blog-related-card" href="index.php?r=blog/detalle&amp;slug=<?= urlencode($relacionado['slug']) ?>">
                                <span class="bh-badge bh-badge-neutral"><?= htmlspecialchars($relacionado['categoria'], ENT_QUOTES, 'UTF-8') ?></span>
                                <strong><?= htmlspecialchars($relacionado['titulo'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <small><?= intval($relacionado['lectura_min']) ?> min de lectura</small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <?php bh_mobile_menu(); ?>
<?php
bh_document_end([
    'include_bootstrap_js' => true,
]);
