<?php
require_once APP_PATH . '/views/partials/auth-layout.php';

bh_auth_begin(
    'Reenviar verificación de email',
    'Verifica tu correo',
    'Introduce tu email y, si la cuenta está pendiente de verificación, te enviaremos un nuevo enlace.'
);
?>

<?php bh_auth_flash_messages(); ?>

<form method="POST" action="?r=verificacion/reenviar" class="bh-form bh-auth-form">
    <?= csrf_field(); ?>

    <div class="bh-field">
        <label for="email" class="bh-label">Correo electrónico:</label>
        <input type="email" name="email" id="email" class="bh-input" placeholder="Correo electrónico" autocomplete="email" inputmode="email" required>
    </div>

    <button type="submit" class="bh-btn bh-btn-primary w-100">
        Enviar enlace de verificación
    </button>
</form>

<div class="bh-auth-links">
    <p><a href="?r=auth/login">Volver a iniciar sesión</a></p>
</div>

<?php bh_auth_end(); ?>
