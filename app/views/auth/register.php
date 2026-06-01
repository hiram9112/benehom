<?php
require_once APP_PATH . '/views/partials/auth-layout.php';

bh_auth_begin(
    'Registro de usuario',
    'Crea tu cuenta',
    'Empieza a ordenar tus ingresos, gastos y metas económicas desde un solo lugar.'
);
?>

<?php bh_auth_flash_messages(); ?>

<form method="post" action="?r=registro/registrarUsuario" class="bh-auth-form">
    <?= csrf_field() ?>

    <div class="mb-3">
        <label for="usuario" class="form-label">Usuario:</label>
        <input type="text" class="form-control" name="usuario" id="usuario" required>
    </div>

    <div class="mb-3">
        <label for="email" class="form-label">Correo electrónico:</label>
        <input type="email" class="form-control" name="email" id="email" required>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Contraseña:</label>
        <div class="input-group">
            <input type="password" class="form-control" name="password" id="password" required>
            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password', this)" aria-label="Mostrar u ocultar contraseña">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>

    <div class="mb-3">
        <label for="password_confirm" class="form-label">Confirmar contraseña:</label>
        <div class="input-group">
            <input type="password" class="form-control" name="password_confirm" id="password_confirm" required>
            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password_confirm', this)" aria-label="Mostrar u ocultar contraseña">
                <i class="bi bi-eye"></i>
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
