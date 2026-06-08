<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Cuenta - BeneHom</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <link rel="stylesheet" href="<?= BASE_URL ?>css/custom.css">
</head>

<body>

    <?php if (isset($_SESSION['mensaje_exitoso'])): ?>
        <div class="bh-alert bh-alert-success text-center" role="status">
            <?= htmlspecialchars($_SESSION['mensaje_exitoso'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['mensaje_exitoso']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['mensaje_error'])): ?>
        <div class="bh-alert bh-alert-error text-center" role="alert">
            <?= htmlspecialchars($_SESSION['mensaje_error'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['mensaje_error']); ?>
    <?php endif; ?>

    <?php
    require_once APP_PATH . '/views/partials/app-navigation.php';
    bh_mobile_nav();
    ?>

    <div class="bh-app-shell">
        <?php bh_sidebar(); ?>

        <main class="bh-main bh-main-contained">

            <header class="bh-page-header">
                <div>
                    <h1>Cuenta</h1>
                    <p>Gestiona tu contraseña y los datos de tu perfil</p>
                </div>
            </header>

            <!-- Cambiar contraseña -->
            <div class="bh-card mb-4">
                <div class="bh-card-header">
                    <h4 class="m-0">Cambiar contraseña</h4>
                </div>
                <div class="bh-card-body">
                    <form method="POST" action="index.php?r=cuenta/cambiarPassword" class="bh-form">
                        <?= csrf_field() ?>

                        <div class="bh-field">
                            <label for="password_actual" class="bh-label">Contraseña actual</label>
                            <div class="bh-password-field">
                                <input type="password" id="password_actual" name="password_actual" class="bh-input" required>
                                <button class="bh-btn bh-btn-icon bh-btn-ghost bh-password-toggle" type="button"
                                    data-bh-password-toggle="password_actual" aria-label="Mostrar contraseña" aria-pressed="false">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <div class="bh-field">
                            <label for="password_nueva" class="bh-label">Contraseña nueva</label>
                            <div class="bh-password-field">
                                <input type="password" id="password_nueva" name="password_nueva" class="bh-input" required>
                                <button class="bh-btn bh-btn-icon bh-btn-ghost bh-password-toggle" type="button"
                                    data-bh-password-toggle="password_nueva" aria-label="Mostrar contraseña" aria-pressed="false">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <div class="bh-field">
                            <button type="submit" class="bh-btn bh-btn-primary">Cambiar contraseña</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Eliminar cuenta -->
            <div class="bh-card mb-4">
                <div class="bh-card-header">
                    <h4 class="m-0">Eliminar cuenta</h4>
                </div>
                <div class="bh-card-body">
                    <div class="bh-alert bh-alert-warning mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                        Esta acción es irreversible. Se eliminarán todos tus datos asociados.
                    </div>

                    <form id="formEliminarCuenta" method="POST" action="index.php?r=cuenta/eliminarCuenta" class="bh-form">
                        <?= csrf_field() ?>

                        <div class="bh-field">
                            <label for="password_confirmacion" class="bh-label">Introduce tu contraseña para confirmar</label>
                            <div class="bh-password-field">
                                <input type="password" id="password_confirmacion" name="password_confirmacion" class="bh-input" required>
                                <button class="bh-btn bh-btn-icon bh-btn-ghost bh-password-toggle" type="button"
                                    data-bh-password-toggle="password_confirmacion" aria-label="Mostrar contraseña" aria-pressed="false">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <div class="bh-field">
                            <button type="submit" class="bh-btn bh-btn-danger">Eliminar cuenta</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Documentación legal -->
            <div class="bh-card">
                <div class="bh-card-header">
                    <h4 class="m-0">Documentación legal</h4>
                </div>
                <div class="bh-card-body">
                    <p>Puedes consultar en cualquier momento la información relativa a la protección de datos y condiciones de uso de la aplicación.</p>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <a href="index.php?r=legal/privacidad" target="_blank">
                                <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                                Política de Privacidad
                            </a>
                        </li>
                        <li>
                            <a href="index.php?r=legal/terminos" target="_blank">
                                <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                                Términos y Condiciones de Uso
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

        </main>
    </div>

    <?php bh_mobile_menu(); ?>

    <!-- Modal de confirmación -->
    <div class="modal fade" id="modalConfirmacion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bh-modal">
                <div class="modal-header bh-modal-header">
                    <h5 class="modal-title bh-modal-title" id="modalConfirmacionTitulo">Confirmar acción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body bh-modal-body" id="modalConfirmacionTexto">
                    ¿Estás seguro?
                </div>
                <div class="modal-footer bh-modal-footer">
                    <button type="button" class="bh-btn bh-btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="bh-btn bh-btn-danger" id="modalConfirmacionAceptar">Aceptar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>js/password-toggle.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>js/cuenta.js?v=<?= time() ?>"></script>
</body>

</html>
