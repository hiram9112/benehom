<?php
require_once APP_PATH . '/views/partials/auth-layout.php';

bh_auth_begin(
    'Iniciar sesión',
    'Inicia sesión',
    'Entra para revisar tu mes, tus gastos y tu ahorro real con calma.'
);
?>

<form method="post" action="<?= BASE_URL ?>index.php?r=auth/login" class="bh-form bh-auth-form">
    <?= csrf_field() ?>

    <div class="bh-field">
        <label for="email" class="bh-label">Correo electrónico:</label>
        <input type="email" name="email" id="email" class="bh-input" autocomplete="username" inputmode="email" required>
    </div>

    <div class="bh-field">
        <label for="password" class="bh-label">Contraseña:</label>
        <div class="bh-password-field">
            <input type="password" name="password" id="password" class="bh-input" autocomplete="current-password" required>
            <button class="bh-btn bh-btn-icon bh-btn-ghost bh-password-toggle" type="button" data-bh-password-toggle="password" aria-label="Mostrar contraseña" aria-pressed="false">
                <i class="ti ti-eye" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <button type="submit" id="btn-login" class="bh-btn bh-btn-primary w-100">Iniciar sesión</button>
</form>

<div class="bh-auth-links">
    <p>¿No tienes cuenta? <a href="<?= bh_page_url('registro/registrarUsuario') ?>">Regístrate aquí</a></p>
    <p><a href="<?= bh_page_url('password/mostrarFormularioOlvido') ?>">¿Olvidaste la contraseña?</a></p>
    <p><a href="<?= bh_page_url('verificacion/mostrarFormularioReenvio') ?>">Reenviar verificación de email</a></p>
</div>

<?php bh_auth_end(); ?>
