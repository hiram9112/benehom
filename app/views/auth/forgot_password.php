<?php
require_once APP_PATH . '/views/partials/auth-layout.php';

bh_auth_begin(
    'Recuperación de contraseña',
    'Recupera tu contraseña',
    'Introduce tu correo y te enviaremos un enlace si la cuenta existe.'
);
?>

<?php bh_auth_flash_messages(); ?>

<form method="POST" action="?r=password/procesarFormularioOlvido" class="bh-form bh-auth-form">
    <?= csrf_field(); ?>

    <div class="bh-field">
        <label for="email" class="bh-label">Correo electrónico:</label>
        <input type="email" name="email" id="email" class="bh-input" placeholder="Correo electrónico" required>
    </div>

    <button id="btn-forgot" type="submit" class="bh-btn bh-btn-primary w-100">
        Enviar enlace de recuperación
    </button>
</form>

<div class="bh-auth-links">
    <p><a href="?r=auth/login">Volver a iniciar sesión</a></p>
</div>

<?php bh_auth_end(); ?>
