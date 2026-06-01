<?php
require_once APP_PATH . '/views/partials/auth-layout.php';

bh_auth_begin(
    'Iniciar sesión',
    'Inicia sesión',
    'Entra para revisar tu mes, tus gastos y tu ahorro real con calma.',
    true
);
?>

<?php bh_auth_flash_messages(); ?>

<form method="post" action="" class="bh-form bh-auth-form">
    <?= csrf_field() ?>

    <div class="bh-field">
        <label for="email" class="bh-label">Email:</label>
        <input type="email" name="email" id="email" class="bh-input" required>
    </div>

    <div class="bh-field">
        <label for="password" class="bh-label">Contraseña:</label>
        <div class="bh-password-field">
            <input type="password" name="password" id="password" class="bh-input" required>
            <button class="bh-btn bh-btn-icon bh-btn-ghost bh-password-toggle" type="button" data-bh-password-toggle="password" aria-label="Mostrar contraseña" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <button type="submit" id="btn-login" class="bh-btn bh-btn-primary w-100">Iniciar sesión</button>
</form>

<div class="bh-auth-links">
    <p>¿No tienes cuenta? <a href="?r=registro/registrarUsuario">Regístrate aquí</a></p>
    <p><a href="?r=password/mostrarFormularioOlvido">¿Olvidaste la contraseña?</a></p>
    <p>
        <a href="#" data-bs-toggle="modal" data-bs-target="#infoApp">
            ¿Qué es BeneHom?
        </a>
    </p>
</div>

<!-- Modal informativo sobre BeneHom -->
<div class="modal fade" id="infoApp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">¿Qué es BeneHom?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <p><strong>BeneHom</strong> es una herramienta de gestión de la economía familiar diseñada para ayudarte a comprender con claridad cómo se mueve el dinero en tu hogar.</p>

                <h6 class="mt-3">¿Qué permite?</h6>
                <ul>
                    <li>Registrar ingresos y clasificarlos correctamente</li>
                    <li>Diferenciar gastos esenciales y gastos flexibles</li>
                    <li>Visualizar tu capacidad real de ahorro vs lo que ahorras realmente</li>
                    <li>Detectar patrones de comportamiento que pueden generar problemas económico para el hogar.</li>
                </ul>

                <h6 class="mt-3">¿Cuál es su objetivo?</h6>
                <p>
                    Fomentar una gestión consciente y sostenible del dinero,
                    mostrando que pequeños ajustes en los gastos flexibles
                    pueden generar un impacto significativo en la estabilidad
                    económica familiar a largo plazo.
                </p>
            </div>

            <div class="modal-footer">
                <button class="bh-btn bh-btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php bh_auth_end(); ?>
