<?php
require_once APP_PATH . '/views/partials/head.php';

$safeTitle = isset($title) ? (string) $title : 'Algo no ha ido bien';
$safeMessage = isset($message) ? (string) $message : 'No hemos podido completar esta solicitud.';
$safeActionLabel = isset($actionLabel) ? (string) $actionLabel : 'Volver al inicio';
$safeActionUrl = isset($actionUrl) ? (string) $actionUrl : BASE_URL . 'index.php?r=home/index';

bh_document_begin([
    'title' => $safeTitle,
    'description' => 'Respuesta de BeneHom ante una solicitud que no se ha podido completar.',
    'canonical' => bh_url('index.php'),
    'robots' => 'noindex',
    'body_class' => 'bh-auth-body',
]);
?>
    <main id="contenido" class="bh-auth-shell">
        <section class="bh-card bh-card-form bh-auth-card bh-error-card" aria-labelledby="error-title">
            <header class="bh-auth-header">
                <a class="bh-auth-brand" href="<?= BASE_URL ?>index.php" aria-label="BeneHom inicio">
                    <img src="<?= bh_asset('img/logo-benehom.png') ?>" alt="BeneHom" width="96" height="96">
                </a>
                <h1 id="error-title" class="bh-auth-title"><?= htmlspecialchars($safeTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="bh-auth-lead"><?= htmlspecialchars($safeMessage, ENT_QUOTES, 'UTF-8') ?></p>
            </header>

            <p class="bh-error-note">
                <i class="ti ti-shield-check" aria-hidden="true"></i>
                <span>Tu información sigue protegida. Puedes volver a un punto seguro y continuar cuando estés listo.</span>
            </p>

            <a class="bh-btn bh-btn-primary bh-error-action" href="<?= htmlspecialchars($safeActionUrl, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($safeActionLabel, ENT_QUOTES, 'UTF-8') ?>
            </a>
        </section>
    </main>
<?php bh_document_end(); ?>
