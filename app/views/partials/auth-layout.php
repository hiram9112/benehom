<?php

require_once APP_PATH . '/views/partials/head.php';

function bh_auth_begin(string $pageTitle, string $heading, string $lead = '', bool $includeBootstrapJs = false): void
{
    $GLOBALS['bh_auth_include_bootstrap_js'] = $includeBootstrapJs;

    bh_document_begin([
        'title' => $pageTitle,
        'description' => 'Accede a BeneHom para organizar ingresos, gastos, ahorro real y metas económicas del hogar.',
        'canonical' => bh_url('index.php?r=' . bh_current_auth_route()),
        'robots' => 'noindex',
        'body_class' => 'bh-auth-body',
    ]);
    ?>
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

function bh_current_auth_route(): string
{
    return isset($_GET['r']) ? trim((string) $_GET['r'], '/') : 'auth/login';
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
    <?php

    bh_document_end([
        'include_bootstrap_js' => (bool) $includeBootstrapJs,
        'include_flash_js' => true,
        'body_end_extra' => '    <script src="' . BASE_URL . 'js/password-toggle.js?v=' . time() . '"></script>' . PHP_EOL,
    ]);
}
