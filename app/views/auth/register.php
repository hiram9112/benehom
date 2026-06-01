<?php
require_once APP_PATH . '/views/partials/auth-layout.php';

bh_auth_begin(
    'Registro de usuario',
    'Crea tu cuenta',
    'Empieza a ordenar tus ingresos, gastos y metas económicas desde un solo lugar.'
);
?>

<?php bh_auth_flash_messages(); ?>

<form method="post" action="?r=registro/registrarUsuario" class="bh-form bh-auth-form">
    <?= csrf_field() ?>

    <div class="bh-field">
        <label for="usuario" class="bh-label">Usuario:</label>
        <input type="text" class="bh-input" name="usuario" id="usuario" required>
    </div>

    <div class="bh-field">
        <label for="email" class="bh-label">Correo electrónico:</label>
        <input type="email" class="bh-input" name="email" id="email" required>
    </div>

    <div class="bh-field">
        <label for="password" class="bh-label">Contraseña:</label>
        <div class="bh-password-field">
            <input type="password" class="bh-input" name="password" id="password" required>
            <button class="bh-btn bh-btn-icon bh-btn-ghost bh-password-toggle" type="button" data-bh-password-toggle="password" aria-label="Mostrar contraseña" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="bh-field">
        <label for="password_confirm" class="bh-label">Confirmar contraseña:</label>
        <div class="bh-password-field">
            <input type="password" class="bh-input" name="password_confirm" id="password_confirm" required>
            <button class="bh-btn bh-btn-icon bh-btn-ghost bh-password-toggle" type="button" data-bh-password-toggle="password_confirm" aria-label="Mostrar contraseña" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="form-check mt-3">
        <input class="form-check-input" type="checkbox" name="acepta_terminos" id="acepta_terminos" required>
        <label class="form-check-label" for="acepta_terminos">
            Acepto la
            <a href="<?= BASE_URL ?>index.php?r=legal/privacidad" target="_blank">Política de Privacidad</a>
            y los
            <a href="<?= BASE_URL ?>index.php?r=legal/terminos" target="_blank">Términos y Condiciones</a>.
        </label>
    </div>

    <button type="submit" id="btn-register" class="bh-btn bh-btn-primary w-100">Registrarse</button>
</form>

<div class="bh-auth-links">
    <p>¿Ya tienes cuenta? <a href="?r=auth/login">Inicia sesión aquí</a></p>
</div>

<?php bh_auth_end(); ?>
