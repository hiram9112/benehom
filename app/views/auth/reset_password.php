<?php
require_once APP_PATH . '/views/partials/auth-layout.php';

bh_auth_begin(
    'Restablecer contraseña',
    'Restablece tu contraseña',
    'Elige una contraseña nueva para volver a entrar en BeneHom.'
);
?>

<?php bh_auth_flash_messages(); ?>

<form method="POST" action="?r=password/procesarReset" class="bh-form bh-auth-form">
    <?= csrf_field() ?>

    <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token'], ENT_QUOTES, 'UTF-8') ?>">

    <div class="bh-field">
        <label for="password" class="bh-label">Nueva contraseña</label>
        <div class="bh-password-field">
            <input type="password" id="password" name="password" class="bh-input" required>
            <button class="bh-btn bh-btn-icon bh-btn-ghost bh-password-toggle" type="button" data-bh-password-toggle="password" aria-label="Mostrar contraseña" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="bh-field">
        <label for="password_confirm" class="bh-label">Confirmar contraseña</label>
        <div class="bh-password-field">
            <input type="password" id="password_confirm" name="password_confirm" class="bh-input" required>
            <button class="bh-btn bh-btn-icon bh-btn-ghost bh-password-toggle" type="button" data-bh-password-toggle="password_confirm" aria-label="Mostrar contraseña" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <button type="submit" class="bh-btn bh-btn-primary w-100" id="btn-forgot">Cambiar contraseña</button>
</form>

<?php bh_auth_end(); ?>
