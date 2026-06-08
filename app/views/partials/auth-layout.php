<?php

function bh_auth_begin(string $pageTitle, string $heading, string $lead = '', bool $includeBootstrapJs = false): void
{
    $GLOBALS['bh_auth_include_bootstrap_js'] = $includeBootstrapJs;
    ?>
    <!DOCTYPE html>
    <html lang="es">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Accede a BeneHom para organizar ingresos, gastos, ahorro real y metas económicas del hogar.">
        <meta property="og:title" content="BeneHom">
        <meta property="og:description" content="BeneHom te ayuda a organizar ingresos y gastos del hogar, analizar tu ahorro real y tomar decisiones financieras más conscientes.">
        <meta property="og:url" content="https://benehom.es">
        <meta property="og:type" content="website">
        <meta property="og:image" content="https://benehom.es/img/og-image.png">
        <meta property="og:locale" content="es_ES">

        <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | BeneHom</title>

        <link rel="icon" type="image/png" href="<?= BASE_URL ?>img/og-image.png">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
        <link rel="stylesheet" href="<?= BASE_URL ?>css/custom.css">
    </head>

    <body class="bh-auth-body">
        <a class="bh-skip-link" href="#contenido">Saltar al contenido</a>
        <main id="contenido" class="bh-auth-shell">
            <section class="bh-card bh-card-form bh-auth-card" aria-labelledby="auth-title">
                <header class="bh-auth-header">
                    <a class="bh-auth-brand" href="<?= BASE_URL ?>index.php" aria-label="BeneHom inicio">
                        <img src="<?= BASE_URL ?>img/logo-benehom.png" alt="BeneHom" width="120" height="80">
                    </a>
                    <div>
                        <h1 id="auth-title" class="bh-auth-title"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h1>
                        <?php if ($lead !== ''): ?>
                            <p class="bh-auth-lead"><?= htmlspecialchars($lead, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                </header>
    <?php
}

function bh_auth_flash_messages(): void
{
    require_once APP_PATH . '/views/partials/flash-messages.php';
    bh_flash_messages();
}

function bh_auth_end(): void
{
    $includeBootstrapJs = $GLOBALS['bh_auth_include_bootstrap_js'] ?? false;
    ?>
            </section>
        </main>

        <?php if ($includeBootstrapJs): ?>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <?php endif; ?>
        <script src="<?= BASE_URL ?>js/flash.js"></script>
        <script src="<?= BASE_URL ?>js/password-toggle.js?v=<?= time() ?>"></script>
    </body>

    </html>
    <?php
}
