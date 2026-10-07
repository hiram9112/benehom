<?php
require_once APP_PATH . '/views/partials/head.php';

$createdMcpToken = $createdMcpToken ?? null;
$mcpTokens = $mcpTokens ?? [];
$hasCreatedMcpToken = is_string($createdMcpToken) && $createdMcpToken !== '';

bh_document_begin([
    'title' => 'Cuenta',
    'description' => 'Área privada de BeneHom para gestionar los datos de cuenta, contraseña y eliminación de perfil.',
    'canonical' => bh_page_url('cuenta/index'),
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
                                <i class="ti ti-mail" aria-hidden="true"></i>
                                <span><?= htmlspecialchars($emailUsuario, ENT_QUOTES, 'UTF-8') ?></span>
                            </p>
                        <?php endif; ?>
                        <?php if ($miembroDesde !== null): ?>
                            <p class="bh-account-meta">
                                <i class="ti ti-calendar" aria-hidden="true"></i>
                                <span>Miembro desde <?= htmlspecialchars($miembroDesde, ENT_QUOTES, 'UTF-8') ?></span>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Cambiar contraseña -->
            <div class="bh-card mb-4">
                <div class="bh-card-header">
                    <h2 class="m-0 bh-card-section-title">Cambiar contraseña</h2>
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
                                    <i class="ti ti-eye" aria-hidden="true"></i>
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
                                    <i class="ti ti-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <ul class="bh-password-requirements" id="passwordRequisitos" data-bh-password-requirements="password_nueva">
                                <li data-req="length"><i class="ti ti-circle" aria-hidden="true"></i><span>Al menos 8 caracteres</span></li>
                                <li data-req="upper"><i class="ti ti-circle" aria-hidden="true"></i><span>Una letra mayúscula</span></li>
                                <li data-req="lower"><i class="ti ti-circle" aria-hidden="true"></i><span>Una letra minúscula</span></li>
                                <li data-req="number"><i class="ti ti-circle" aria-hidden="true"></i><span>Un número</span></li>
                            </ul>
                        </div>

                        <div class="bh-field">
                            <label for="password_confirmacion_nueva" class="bh-label">Confirmar contraseña nueva</label>
                            <div class="bh-password-field">
                                <input type="password" id="password_confirmacion_nueva" name="password_confirmacion_nueva" class="bh-input"
                                    autocomplete="new-password" aria-describedby="passwordMatchError" required>
                                <button class="bh-btn bh-btn-icon bh-btn-ghost bh-password-toggle" type="button"
                                    data-bh-password-toggle="password_confirmacion_nueva" aria-label="Mostrar contraseña" aria-pressed="false">
                                    <i class="ti ti-eye" aria-hidden="true"></i>
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
                    <h2 class="m-0 bh-card-section-title">Tus datos</h2>
                </div>
                <div class="bh-card-body">
                    <p>Descarga una copia de toda tu información en BeneHom (perfil, ingresos, gastos, metas y proyecciones) en un archivo JSON que podrás guardar o llevar a otra herramienta.</p>
                    <form method="POST" action="index.php?r=cuenta/exportarDatos" class="bh-form">
                        <?= csrf_field() ?>
                        <div class="bh-field">
                            <button type="submit" class="bh-btn bh-btn-secondary">
                                <i class="ti ti-download" aria-hidden="true"></i>
                                Descargar mis datos (JSON)
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bh-card mb-4">
                <div class="bh-card-header">
                    <h2 class="m-0 bh-card-section-title" id="mcpAccessTitle">Acceso MCP</h2>
                </div>
                <div class="bh-card-body">
                    <p>Crea un token personal para conectar un cliente MCP a tus consultas financieras de solo lectura. No compartas el token.</p>

                    <form method="POST" action="index.php?r=cuenta/crearTokenMcp" data-mcp-token-form data-ajax-action="index.php?r=cuenta/crearTokenMcpAjax" class="bh-form mb-4">
                        <?= csrf_field() ?>
                        <div class="bh-field">
                            <label class="bh-label" for="mcp_token_nombre">Nombre del token</label>
                            <input class="bh-input" id="mcp_token_nombre" name="nombre" type="text" maxlength="100" required autocomplete="off" placeholder="Por ejemplo, Claude" aria-describedby="mcpTokenFormError">
                            <p class="bh-field-error" id="mcpTokenFormError" data-mcp-form-error role="alert" hidden></p>
                        </div>
                        <div class="bh-field">
                            <button class="bh-btn bh-btn-primary" type="submit" data-mcp-submit>Crear token MCP</button>
                        </div>
                    </form>

                    <section class="bh-mcp-token-created" id="mcpTokenCreated" data-mcp-token-created tabindex="-1" aria-labelledby="mcpTokenCreatedTitle" aria-describedby="mcpTokenCreatedDescription"<?= $hasCreatedMcpToken ? '' : ' hidden' ?>>
                        <div class="bh-mcp-token-created-header">
                            <i class="ti ti-key" aria-hidden="true"></i>
                            <div>
                                <h3 id="mcpTokenCreatedTitle">Token MCP creado</h3>
                                <p id="mcpTokenCreatedDescription"><strong>Cópialo y guárdalo ahora.</strong> Este es el único momento en que BeneHom muestra el secreto completo. Después no podremos recuperarlo ni volver a mostrártelo.</p>
                            </div>
                        </div>
                        <div class="bh-field">
                            <label class="bh-label" for="mcpTokenSecret">Tu token secreto</label>
                            <textarea class="bh-input bh-mcp-token-secret" id="mcpTokenSecret" data-mcp-token-secret rows="3" readonly autocomplete="off" spellcheck="false"><?= $hasCreatedMcpToken ? htmlspecialchars($createdMcpToken, ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                        </div>
                        <div class="bh-mcp-token-created-actions">
                            <button class="bh-btn bh-btn-secondary" type="button" data-mcp-copy hidden>
                                <i class="ti ti-copy" aria-hidden="true"></i>
                                <span data-mcp-copy-label>Copiar token</span>
                            </button>
                            <p class="bh-mcp-copy-status" data-mcp-copy-status role="status" aria-live="polite"></p>
                        </div>
                    </section>

                    <div class="bh-mcp-token-list">
                        <h3 class="bh-account-danger-subtitle">Tus tokens MCP</h3>
                        <p class="m-0" data-mcp-token-empty<?= $mcpTokens === [] ? '' : ' hidden' ?>>Aún no has creado ningún token MCP.</p>
                        <div class="table-responsive" data-mcp-token-table<?= $mcpTokens === [] ? ' hidden' : '' ?>>
                            <table class="table mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Nombre</th>
                                        <th scope="col">Creado</th>
                                        <th scope="col">Último uso</th>
                                        <th scope="col">Estado</th>
                                        <th scope="col"><span class="visually-hidden">Acción</span></th>
                                    </tr>
                                </thead>
                                <tbody id="mcpTokenRows" data-mcp-token-list>
                                    <?php foreach ($mcpTokens as $mcpToken): ?>
                                        <tr data-mcp-token-id="<?= (int) $mcpToken['id'] ?>">
                                            <td data-mcp-token-name><?= htmlspecialchars($mcpToken['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td data-mcp-token-created-at><?= htmlspecialchars($mcpToken['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td data-mcp-token-last-used><?= htmlspecialchars($mcpToken['last_used_at'] ?? 'Sin uso', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td data-mcp-token-status tabindex="-1" aria-live="polite"><?= $mcpToken['revoked_at'] === null ? 'Activo' : 'Revocado' ?></td>
                                            <td>
                                                <?php if ($mcpToken['revoked_at'] === null): ?>
                                                    <form method="POST" action="index.php?r=cuenta/revocarTokenMcp" data-mcp-revoke-form data-ajax-action="index.php?r=cuenta/revocarTokenMcpAjax">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="token_id" value="<?= (int) $mcpToken['id'] ?>">
                                                        <button class="bh-btn bh-btn-danger" type="submit" data-mcp-revoke-submit>Revocar</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="bh-mcp-token-list-actions">
                            <button class="bh-mcp-token-list-toggle" type="button" data-mcp-token-list-toggle aria-controls="mcpTokenRows" aria-expanded="false" hidden>
                                <span data-mcp-token-list-toggle-label>Ver más</span>
                                <i class="ti ti-chevron-down" data-mcp-token-list-toggle-icon aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <template data-mcp-token-row-template>
                        <tr data-mcp-token-id="">
                            <td data-mcp-token-name></td>
                            <td data-mcp-token-created-at>Ahora</td>
                            <td data-mcp-token-last-used>Sin uso</td>
                            <td data-mcp-token-status tabindex="-1" aria-live="polite">Activo</td>
                            <td>
                                <form method="POST" action="index.php?r=cuenta/revocarTokenMcp" data-mcp-revoke-form data-ajax-action="index.php?r=cuenta/revocarTokenMcpAjax">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="token_id" value="">
                                    <button class="bh-btn bh-btn-danger" type="submit" data-mcp-revoke-submit>Revocar</button>
                                </form>
                            </td>
                        </tr>
                    </template>
                </div>
            </div>

            <!-- Documentación legal -->
            <div class="bh-card mb-4">
                <div class="bh-card-header">
                    <h2 class="m-0 bh-card-section-title">Documentación legal</h2>
                </div>
                <div class="bh-card-body">
                    <p>Puedes consultar en cualquier momento la información relativa a la protección de datos y condiciones de uso de la aplicación.</p>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <a href="<?= htmlspecialchars(bh_public_page_url('privacidad'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                <i class="ti ti-file-text" aria-hidden="true"></i>
                                Política de Privacidad
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= htmlspecialchars(bh_public_page_url('terminos'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                <i class="ti ti-file-text" aria-hidden="true"></i>
                                Términos y Condiciones de Uso
                            </a>
                        </li>
                        <li>
                            <a href="<?= htmlspecialchars(bh_public_page_url('aviso'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                <i class="ti ti-file-text" aria-hidden="true"></i>
                                Aviso Legal
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Zona peligrosa: eliminar cuenta -->
            <section class="bh-card bh-card-danger" aria-labelledby="dangerZoneTitle">
                <div class="bh-card-header bh-card-danger-header">
                    <h2 class="m-0 bh-card-section-title" id="dangerZoneTitle">
                        <i class="ti ti-alert-octagon" aria-hidden="true"></i>
                        Acción irreversible
                    </h2>
                </div>
                <div class="bh-card-body">
                    <h3 class="bh-account-danger-subtitle">Eliminar cuenta</h3>
                    <p class="bh-account-danger-text">Se eliminarán de forma permanente tu cuenta y todos los datos asociados (ingresos, gastos, metas y proyecciones).</p>

                    <form id="formEliminarCuenta" method="POST" action="index.php?r=cuenta/eliminarCuenta" class="bh-form">
                        <?= csrf_field() ?>

                        <div class="bh-field">
                            <label for="password_confirmacion" class="bh-label">Introduce tu contraseña para confirmar</label>
                            <div class="bh-password-field">
                                <input type="password" id="password_confirmacion" name="password_confirmacion" class="bh-input" autocomplete="current-password" required>
                                <button class="bh-btn bh-btn-icon bh-btn-ghost bh-password-toggle" type="button"
                                    data-bh-password-toggle="password_confirmacion" aria-label="Mostrar contraseña" aria-pressed="false">
                                    <i class="ti ti-eye" aria-hidden="true"></i>
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
    <script src="<?= bh_asset('js/password-toggle.js') ?>"></script>
    <script src="<?= bh_asset('js/password-requirements.js') ?>"></script>
    <script src="<?= bh_asset('js/cuenta.js') ?>"></script>
<?php
$bhCuentaBodyEndExtra = ob_get_clean();

bh_document_end([
    'include_bootstrap_js' => true,
    'include_flash_js' => true,
    'body_end_extra' => $bhCuentaBodyEndExtra,
]);
