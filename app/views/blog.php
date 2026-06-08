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

    $articulosListado = $articulos;
    ?>

    <div class="bh-app-shell">
        <?php bh_sidebar(); ?>

        <main class="bh-main bh-blog-page">
            <header class="bh-card bh-blog-hero" aria-labelledby="blog-title">
                <div class="bh-blog-hero-copy">
                    <h1 id="blog-title">Entiende mejor el dinero que forma parte de tu día a día</h1>
                    <p>
                        Artículos claros sobre conceptos financieros cotidianos: inflación, hipotecas, activos financieros y otros temas
                        que influyen en cómo decides, ahorras y planificas. Comprenderlos ayuda a evitar errores habituales y a construir
                        una relación más consciente con tu economía.
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
                        Cuando añadas contenido educativo, aparecerá aquí para acompañar las decisiones financieras del hogar.
                    </p>
                </section>
            <?php else: ?>
                <section class="bh-card bh-blog-library" aria-labelledby="blog-library-title">
                    <div class="bh-blog-section-heading">
                        <div>
                            <h2 id="blog-library-title">Biblioteca educativa</h2>
                            <p>Contenido preparado para crecer sin cambiar la estructura cuando añadas nuevos artículos.</p>
                        </div>
                    </div>

                    <div class="bh-blog-article-grid" aria-label="Artículos educativos">
                        <?php foreach ($articulosListado as $articulo): ?>
                            <article class="bh-blog-article-card">
                                <div class="bh-blog-article-marker" aria-hidden="true">
                                    <i class="bi <?= htmlspecialchars($articulo['icono'] ?? 'bi-journal-text', ENT_QUOTES, 'UTF-8') ?>"></i>
                                </div>
                                <div class="bh-blog-article-copy">
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
                </section>
            <?php endif; ?>
        </main>
    </div>

    <?php bh_mobile_menu(); ?>

    <script src="<?= BASE_URL ?>js/flash.js"></script>
</body>

</html>
