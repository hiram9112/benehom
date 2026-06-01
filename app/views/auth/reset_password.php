<?php
require_once APP_PATH . '/views/partials/auth-layout.php';

bh_auth_begin(
    'Restablecer contraseña',
    'Restablece tu contraseña',
    'Elige una contraseña nueva para volver a entrar en BeneHom.'
);
?>

<?php bh_auth_flash_messages(); ?>

<form method="POST" action="?r=password/procesarReset" class="bh-auth-form">
    <?= csrf_field() ?>

    <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token'], ENT_QUOTES, 'UTF-8') ?>">

    <div class="mb-3">
        <label for="password" class="form-label">Nueva contraseña</label>
        <div class="input-group">
            <input type="password" id="password" name="password" class="form-control" required>
            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password', this)" aria-label="Mostrar u ocultar contraseña">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>

    <div class="mb-3">
        <label for="password_confirm" class="form-label">Confirmar contraseña</label>
        <div class="input-group">
            <input type="password" id="password_confirm" name="password_confirm" class="form-control" required>
            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password_confirm', this)" aria-label="Mostrar u ocultar contraseña">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>

    <button type="submit" class="bh-btn bh-btn-primary w-100" id="btn-forgot">Cambiar contraseña</button>
</form>

<script>
    function togglePassword(inputId, button) {
        const input = document.getElementById(inputId);
        const icon = button.querySelector('i');

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
</script>

<?php bh_auth_end(); ?>
