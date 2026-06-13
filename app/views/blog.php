<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Blog educativo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/custom.css">
</head>

<body>
    <?php
    require_once APP_PATH . '/views/partials/flash-messages.php';
    bh_flash_messages();

    require_once APP_PATH . '/views/partials/app-navigation.php';
    bh_mobile_nav();

    $formatearFechaBlog = static function (string $fecha): string {
        return date('d/m/Y', strtotime($fecha));
    };

    $slugDestacado = (string) ($articuloDestacado['slug'] ?? '');
    $articulosListado = array_values(array_filter($articulos, static function (array $articulo) use ($slugDestacado): bool {
        return $slugDestacado === '' || ($articulo['slug'] ?? '') !== $slugDestacado;
    }));
    ?>

    <div class="bh-app-shell">
        <?php bh_sidebar(); ?>

        <main class="bh-main bh-blog-page">
            <header class="bh-card bh-blog-hero" aria-labelledby="blog-title">
                <div class="bh-blog-hero-copy">
                    <h1 id="blog-title">Aprende a manejar el dinero de tu hogar, sin complicaciones</h1>
                    <p>
                        Guías claras y prácticas sobre lo que de verdad te preguntas: cómo hacer un presupuesto, cuánto puedes ahorrar,
                        en qué se va el dinero, qué es el interés compuesto o cuánta hipoteca puedes pagar. Sin jerga y sin promesas:
                        solo ideas para decidir con más calma y construir margen.
                    </p>
                </div>
                <div class="bh-blog-hero-visual" aria-hidden="true">
                    <img src="<?= BASE_URL ?>img/blog-image.png" alt="">
                </div>
            </header>

            <?php if (empty($articulos)): ?>
                <section class="bh-empty-state" aria-labelledby="blog-empty-title">
                    <div class="bh-empty-state-icon" aria-hidden="true">
                        <i class="bi bi-journal-text"></i>
                    </div>
                    <h2 id="blog-empty-title" class="bh-empty-state-title">Aún no hay artículos publicados</h2>
                    <p class="bh-empty-state-text">
                        Las guías sobre presupuesto, ahorro, gastos y proyecciones estarán disponibles aquí para ayudarte a tomar mejores decisiones con el dinero de tu hogar.
                    </p>
                </section>
            <?php else: ?>
                <section class="bh-card bh-blog-library" aria-labelledby="blog-library-title">
                    <div class="bh-blog-section-heading">
                        <div>
                            <h2 id="blog-library-title">Explora por tema</h2>
                            <p class="bh-blog-result-count" data-blog-count aria-live="polite">
                                <span data-blog-count-value><?= count($articulos) ?></span> artículos<span data-blog-count-temas> · <?= count($categorias) ?> temas</span>
                            </p>
                        </div>
                        <div class="bh-blog-search">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input type="search" class="bh-blog-search-input" data-blog-search placeholder="Buscar por título o tema" aria-label="Buscar artículos por título o tema">
                        </div>
                    </div>

                    <?php if (!empty($categorias)): ?>
                        <div class="bh-blog-filter-list" role="group" aria-label="Filtrar artículos por tema">
                            <button type="button" class="bh-blog-filter is-active" data-blog-filter="" aria-pressed="true">Todos</button>
                            <?php foreach ($categorias as $categoria): ?>
                                <button type="button" class="bh-blog-filter" data-blog-filter="<?= htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8') ?>" aria-pressed="false"><?= htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8') ?></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($articuloDestacado)): ?>
                        <article class="bh-card bh-blog-featured-card bh-blog-filterable" aria-label="Artículo destacado" data-blog-featured data-categoria="<?= htmlspecialchars($articuloDestacado['categoria'], ENT_QUOTES, 'UTF-8') ?>" data-busqueda="<?= htmlspecialchars($articuloDestacado['titulo'] . ' ' . $articuloDestacado['resumen'], ENT_QUOTES, 'UTF-8') ?>">
                            <div class="bh-blog-featured-marker" aria-hidden="true">
                                <i class="bi <?= htmlspecialchars($articuloDestacado['icono'] ?? 'bi-journal-text', ENT_QUOTES, 'UTF-8') ?>"></i>
                            </div>
                            <div class="bh-blog-featured-copy">
                                <span class="bh-badge bh-badge-saving">Destacado · <?= htmlspecialchars($articuloDestacado['categoria'], ENT_QUOTES, 'UTF-8') ?></span>
                                <h2><?= htmlspecialchars($articuloDestacado['titulo'], ENT_QUOTES, 'UTF-8') ?></h2>
                                <p><?= htmlspecialchars($articuloDestacado['resumen'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <div class="bh-blog-featured-aside">
                                <div class="bh-blog-meta-list">
                                    <p>
                                        <span>Publicado</span>
                                        <strong><?= htmlspecialchars($formatearFechaBlog($articuloDestacado['fecha']), ENT_QUOTES, 'UTF-8') ?></strong>
                                    </p>
                                    <p>
                                        <span>Lectura</span>
                                        <strong><?= intval($articuloDestacado['lectura_min']) ?> min</strong>
                                    </p>
                                </div>
                                <a class="bh-btn bh-btn-primary bh-blog-card-action" href="index.php?r=blog/detalle&amp;slug=<?= urlencode($articuloDestacado['slug']) ?>">
                                    Leer destacado
                                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                </a>
                            </div>
                        </article>
                    <?php endif; ?>

                    <div class="bh-blog-article-grid" aria-label="Artículos educativos">
                        <?php foreach ($articulosListado as $articulo): ?>
                            <article class="bh-blog-article-card bh-blog-filterable" data-categoria="<?= htmlspecialchars($articulo['categoria'], ENT_QUOTES, 'UTF-8') ?>" data-busqueda="<?= htmlspecialchars($articulo['titulo'] . ' ' . $articulo['resumen'], ENT_QUOTES, 'UTF-8') ?>">
                                <div class="bh-blog-article-marker" aria-hidden="true">
                                    <i class="bi <?= htmlspecialchars($articulo['icono'] ?? 'bi-journal-text', ENT_QUOTES, 'UTF-8') ?>"></i>
                                </div>
                                <div class="bh-blog-article-copy">
                                    <span class="bh-badge bh-badge-neutral"><?= htmlspecialchars($articulo['categoria'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <h2><?= htmlspecialchars($articulo['titulo'], ENT_QUOTES, 'UTF-8') ?></h2>
                                    <p><?= htmlspecialchars($articulo['resumen'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div class="bh-blog-article-aside">
                                    <div class="bh-blog-meta-list">
                                        <p>
                                            <span>Publicado</span>
                                            <strong><?= htmlspecialchars($formatearFechaBlog($articulo['fecha']), ENT_QUOTES, 'UTF-8') ?></strong>
                                        </p>
                                        <p>
                                            <span>Lectura</span>
                                            <strong><?= intval($articulo['lectura_min']) ?> min</strong>
                                        </p>
                                    </div>
                                    <a class="bh-btn bh-btn-primary bh-blog-card-action" href="index.php?r=blog/detalle&amp;slug=<?= urlencode($articulo['slug']) ?>">
                                        Leer artículo
                                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <p class="bh-blog-no-results" data-blog-empty hidden role="status">
                        No hay artículos que coincidan con tu búsqueda. Prueba con otro tema o término.
                    </p>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <?php bh_mobile_menu(); ?>

    <script src="<?= BASE_URL ?>js/flash.js"></script>
    <script src="<?= BASE_URL ?>js/blog-filtros.js"></script>
</body>

</html>
