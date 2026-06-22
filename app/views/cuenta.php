<?php
require_once APP_PATH . '/views/partials/head.php';

bh_document_begin([
    'title' => 'Cuenta',
    'description' => 'Área privada de BeneHom para gestionar los datos de cuenta, contraseña y eliminación de perfil.',
    'canonical' => bh_url('index.php?r=cuenta/index'),
    'robots' => 'noindex',
]);
?>

    <?php
    require_once APP_PATH . '/views/partials/flash-messages.php';
    bh_flash_messages();
    ?>

    <?php
    require_once APP_PATH . '/views/partials/app-navigation.php';
    require_once APP_PATH . '/views/partials/modals.php';
    bh_mobile_nav();
    ?>

    <div class="bh-app-shell">
        <?php bh_sidebar(); ?>

        <main id="contenido" class="bh-main bh-main-contained">

            <?php
            // Datos de perfil para la cabecera de identidad
            $nombreUsuario = $nombreUsuario ?? ($_SESSION['usuario'] ?? 'Usuario');
            $emailUsuario  = $emailUsuario ?? '';
            $fechaRegistro = $fechaRegistro ?? null;

            $inicial = mb_strtoupper(mb_substr(trim($nombreUsuario), 0, 1, 'UTF-8'), 'UTF-8');
            if ($inicial === '') {
                $inicial = '?';
            }

            $mesesEs = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            $miembroDesde = null;
            if (!empty($fechaRegistro)) {
                $ts = strtotime((string) $fechaRegistro);
                if ($ts !== false) {
                    $miembroDesde = $mesesEs[(int) date('n', $ts)] . ' de ' . date('Y', $ts);
                }
            }
            ?>

            <!-- Identidad del perfil -->
            <section class="bh-card bh-account-identity mb-4" aria-labelledby="accountName">
                <div class="bh-card-body bh-account-identity-body">
                    <div class="bh-account-avatar" aria-hidden="true"><?= htmlspecialchars($inicial, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="bh-account-identity-info">
                        <p class="bh-account-kicker">Tu cuenta</p>
                        <h1 id="accountName"><?= htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') ?></h1>
                        <?php if ($emailUsuario !== ''): ?>
                            <p class="bh-account-meta">
                                <i class="bi bi-envelope" aria-hidden="true"></i>
                                <span><?= htmlspecialchars($emailUsuario, ENT_QUOTES, 'UTF-8') ?></span>
                            </p>
                        <?php endif; ?>
                        <?php if ($miembroDesde !== null): ?>
                            <p class="bh-account-meta">
                                <i class="bi bi-calendar3" aria-hidden="true"></i>
                                <span>Miembro desde <?= htmlspecialchars($miembroDesde, ENT_QUOTES, 'UTF-8') ?></span>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Cambiar contraseña -->
            <div class="bh-card mb-4">
                <div class="bh-card-header">
                    <h4 class="m-0">Cambiar contraseña</h4>
                </div>
                <div class="bh-card-body">
                    <form method="POST" action="index.php?r=cuenta/cambiarPassword" class="bh-form" id="formCambiarPassword">
                        <?= csrf_field() ?>

                        <div class="bh-field">
                            <label for="password_actual" class="bh-label">Contraseña actual</label>
                            <div class="bh-password-field">
                                <input type="password" id="password_actual" name="password_actual" class="bh-input" autocomplete="current-password" required>
                                <button class="bh-btn bh-btn-icon bh-btn-ghost bh-password-toggle" type="button"
                                    data-bh-password-toggle="password_actual" aria-label="Mostrar contraseña" aria-pressed="false">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <div class="bh-field">
                            <label for="password_nueva" class="bh-label">Contraseña nueva</label>
                            <div class="bh-password-field">
                                <input type="password" id="password_nueva" name="password_nueva" class="bh-input"
                                    autocomplete="new-password" aria-describedby="passwordRequisitos" required>
                                <button class="bh-btn bh-btn-icon bh-btn-ghost bh-password-toggle" type="button"
                                    data-bh-password-toggle="password_nueva" aria-label="Mostrar contraseña" aria-pressed="false">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <ul class="bh-password-requirements" id="passwordRequisitos">
                                <li data-req="length"><i class="bi bi-circle" aria-hidden="true"></i><span>Al menos 8 caracteres</span></li>
                                <li data-req="upper"><i class="bi bi-circle" aria-hidden="true"></i><span>Una letra mayúscula</span></li>
                                <li data-req="lower"><i class="bi bi-circle" aria-hidden="true"></i><span>Una letra minúscula</span></li>
                                <li data-req="number"><i class="bi bi-circle" aria-hidden="true"></i><span>Un número</span></li>
                            </ul>
                        </div>

                        <div class="bh-field">
                            <label for="password_confirmacion_nueva" class="bh-label">Confirmar contraseña nueva</label>
                            <div class="bh-password-field">
                                <input type="password" id="password_confirmacion_nueva" name="password_confirmacion_nueva" class="bh-input"
                                    autocomplete="new-password" aria-describedby="passwordMatchError" required>
                                <button class="bh-btn bh-btn-icon bh-btn-ghost bh-password-toggle" type="button"
                                    data-bh-password-toggle="password_confirmacion_nueva" aria-label="Mostrar contraseña" aria-pressed="false">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <p class="bh-field-error" id="passwordMatchError" role="alert" hidden>Las contraseñas no coinciden.</p>
                        </div>

                        <div class="bh-field">
                            <button type="submit" class="bh-btn bh-btn-primary">Cambiar contraseña</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Exportar datos (portabilidad RGPD) -->
            <div class="bh-card mb-4">
                <div class="bh-card-header">
                    <h4 class="m-0">Tus datos</h4>
                </div>
                <div class="bh-card-body">
                    <p>Descarga una copia de toda tu información en BeneHom (perfil, ingresos, gastos, metas y proyecciones) en un archivo JSON que podrás guardar o llevar a otra herramienta.</p>
                    <form method="POST" action="index.php?r=cuenta/exportarDatos" class="bh-form">
                        <?= csrf_field() ?>
                        <div class="bh-field">
                            <button type="submit" class="bh-btn bh-btn-secondary">
                                <i class="bi bi-download" aria-hidden="true"></i>
                                Descargar mis datos (JSON)
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Documentación legal -->
            <div class="bh-card mb-4">
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
                        <li class="mb-2">
                            <a href="index.php?r=legal/terminos" target="_blank">
                                <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                                Términos y Condiciones de Uso
                            </a>
                        </li>
                        <li>
                            <a href="index.php?r=legal/aviso" target="_blank">
                                <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                                Aviso Legal
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Zona peligrosa: eliminar cuenta -->
            <section class="bh-card bh-card-danger" aria-labelledby="dangerZoneTitle">
                <div class="bh-card-header bh-card-danger-header">
                    <h4 class="m-0" id="dangerZoneTitle">
                        <i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
                        Acción irreversible
                    </h4>
                </div>
                <div class="bh-card-body">
                    <h5 class="bh-account-danger-subtitle">Eliminar cuenta</h5>
                    <p class="bh-account-danger-text">Se eliminarán de forma permanente tu cuenta y todos los datos asociados (ingresos, gastos, metas y proyecciones).</p>

                    <form id="formEliminarCuenta" method="POST" action="index.php?r=cuenta/eliminarCuenta" class="bh-form">
                        <?= csrf_field() ?>

                        <div class="bh-field">
                            <label for="password_confirmacion" class="bh-label">Introduce tu contraseña para confirmar</label>
                            <div class="bh-password-field">
                                <input type="password" id="password_confirmacion" name="password_confirmacion" class="bh-input" autocomplete="current-password" required>
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
            </section>

        </main>
    </div>

    <?php bh_mobile_menu(); ?>

    <!-- Modal de confirmación -->
    <?php
    bh_modal([
        'id'      => 'modalConfirmacion',
        'title'   => 'Confirmar acción',
        'titleId' => 'modalConfirmacionTitulo',
        'bodyId'  => 'modalConfirmacionTexto',
        'body'    => '¿Estás seguro?',
        'footer'  => '<button type="button" class="bh-btn bh-btn-secondary" data-bs-dismiss="modal">Cancelar</button>'
            . '<button type="button" class="bh-btn bh-btn-danger" id="modalConfirmacionAceptar">Aceptar</button>',
    ]);
    ?>
<?php ob_start(); ?>
    <script src="<?= BASE_URL ?>js/password-toggle.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>js/cuenta.js?v=<?= time() ?>"></script>
<?php
$bhCuentaBodyEndExtra = ob_get_clean();

bh_document_end([
    'include_bootstrap_js' => true,
    'include_flash_js' => true,
    'body_end_extra' => $bhCuentaBodyEndExtra,
]);
