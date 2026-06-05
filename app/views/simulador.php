<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Simulador</title>
    <!--Bootstrap CSS-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!--Bootstrap Iconos-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!--Bootstrap JS(componentes interactivos)-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!--Conectamos con archivo CSS propio-->
    <link rel="stylesheet" href="<?= BASE_URL ?>css/custom.css">
</head>

<body>
    <?php
    require_once APP_PATH . '/views/partials/app-navigation.php';
    bh_mobile_nav();

    $formatearEuros = static function ($cantidad): string {
        return formatearCantidadPHP($cantidad) . ' €';
    };

    $formatearFecha = static function ($fecha): string {
        if (empty($fecha)) {
            return 'Sin fecha estimada';
        }

        return date('d/m/Y', strtotime($fecha));
    };

    $formatearPlazo = static function ($meses): string {
        if ($meses === null) {
            return 'No calculable';
        }

        $meses = intval($meses);
        $anios = intdiv($meses, 12);
        $restoMeses = $meses % 12;

        if ($anios === 0) {
            return $meses . ($meses === 1 ? ' mes' : ' meses');
        }

        if ($restoMeses === 0) {
            return $anios . ($anios === 1 ? ' año' : ' años');
        }

        return $anios . ($anios === 1 ? ' año' : ' años') . ' y ' . $restoMeses . ($restoMeses === 1 ? ' mes' : ' meses');
    };
    ?>

    <?php if (isset($_SESSION['mensaje_exitoso'])): ?>
        <p class="bh-alert bh-alert-success text-center m-3" role="status">
            <?= htmlspecialchars($_SESSION['mensaje_exitoso'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <?php unset($_SESSION['mensaje_exitoso']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['mensaje_error'])): ?>
        <p class="bh-alert bh-alert-error text-center m-3" role="alert">
            <?= htmlspecialchars($_SESSION['mensaje_error'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <?php unset($_SESSION['mensaje_error']); ?>
    <?php endif; ?>

    <!--Contenedor Principal-->
    <div class="bh-app-shell">
        <?php bh_sidebar(); ?>

        <!--Panel Central-->
        <main class="bh-main">
            <section class="bh-simulator-hero mb-4" aria-labelledby="simulador-titulo">
                <article class="bh-card bh-card-finance bh-simulator-intro-card">
                    <div class="bh-card-body">
                        <p class="bh-simulator-kicker">Herramienta educativa</p>
                        <h1 id="simulador-titulo">Simulador</h1>
                        <p>
                            Visualiza cómo decisiones de ahorro, inversión e inflación pueden influir en objetivos
                            financieros futuros sin convertir BeneHom en una contabilidad paralela.
                        </p>
                        <p>
                            Tomamos como referencia el ahorro mensual del mes seleccionado en Dashboard. Puedes editar
                            esa cantidad manualmente para probar escenarios sin modificar tus ingresos, gastos ni el Dashboard.
                        </p>
                        <p class="mb-0">
                            Los resultados son estimaciones orientativas, no garantías ni recomendaciones financieras.
                        </p>
                    </div>
                </article>

                <article class="bh-card bh-simulator-savings-card" aria-labelledby="ahorro-mensual-disponible-label">
                    <div class="bh-card-body">
                        <p id="ahorro-mensual-disponible-label" class="bh-simulator-savings-label">Ahorro mensual disponible</p>
                        <div class="bh-simulator-savings-value">
                            <span
                                id="ahorro_mensual_disponible"
                                class="bh-simulator-savings-amount"
                                role="button"
                                tabindex="0"
                                aria-label="Editar ahorro mensual disponible"
                                data-value="<?= htmlspecialchars((string) $ahorroMensualDisponible, ENT_QUOTES, 'UTF-8') ?>">
                                <?= formatearCantidadPHP($ahorroMensualDisponible) ?>
                            </span>
                            <span class="bh-simulator-currency">€</span>
                        </div>
                        <p class="bh-simulator-edit-hint">Haz clic en el importe para editarlo.</p>

                        <div class="bh-simulator-savings-breakdown">
                            <p>
                                <span>Asignado a metas</span>
                                <strong id="ahorro_asignado_metas"><?= $formatearEuros($ahorroAsignadoMetas) ?></strong>
                            </p>
                            <p>
                                <span>Disponible</span>
                                <strong id="ahorro_disponible_metas"><?= $formatearEuros($ahorroDisponibleMetas) ?></strong>
                            </p>
                        </div>
                    </div>
                </article>
            </section>

            <?php if (!empty($avisoAhorroAsignado)): ?>
                <div class="bh-alert bh-alert-warning mb-4">
                    <?= htmlspecialchars($avisoAhorroAsignado, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($ahorroAsignadoSuperaDisponible): ?>
                <div class="bh-alert bh-alert-warning mb-4">
                    El ahorro asignado a metas supera el ahorro mensual disponible. Puedes ajustar el importe para simular
                    sin cambiar tus datos reales.
                </div>
            <?php endif; ?>

            <section aria-labelledby="metas-ahorro-titulo" class="bh-simulator-goals">
                <div class="bh-page-header">
                    <div>
                        <p class="bh-simulator-kicker">Escenarios guardados</p>
                        <h2 id="metas-ahorro-titulo">Metas de ahorro</h2>
                        <p>
                            Crea metas simuladas para estimar plazos o calcular cuánto necesitarías aportar al mes.
                            No representan dinero apartado realmente.
                        </p>
                    </div>
                </div>

                <div class="bh-simulator-goals-grid">
                    <article class="bh-card bh-meta-form-card">
                        <div class="bh-card-header">
                            <h3 class="titulo m-0">
                                <i class="bi bi-piggy-bank me-2" aria-hidden="true"></i>
                                Nueva meta de ahorro
                            </h3>
                        </div>
                        <div class="bh-card-body">
                            <form method="POST" action="index.php?r=simulador/crearMetaAhorro" class="bh-form js-meta-form">
                                <?= csrf_field() ?>

                                <div class="bh-field-row">
                                    <div class="bh-field">
                                        <label class="bh-label" for="meta_nombre">Nombre</label>
                                        <input class="bh-input" type="text" id="meta_nombre" name="nombre" maxlength="100" required placeholder="Ej. Fondo para vacaciones">
                                    </div>
                                    <div class="bh-field">
                                        <label class="bh-label" for="meta_categoria">Categoría</label>
                                        <input class="bh-input" type="text" id="meta_categoria" name="categoria" maxlength="60" required placeholder="Ej. Viajes, hogar, estudios">
                                    </div>
                                </div>

                                <div class="bh-field">
                                    <label class="bh-label" for="meta_importe_objetivo">Importe objetivo</label>
                                    <input class="bh-input" type="number" id="meta_importe_objetivo" name="importe_objetivo" min="0.01" step="0.01" inputmode="decimal" required>
                                </div>

                                <fieldset class="bh-meta-mode-fieldset">
                                    <legend class="bh-label">Modo de cálculo</legend>
                                    <label class="bh-meta-mode-option">
                                        <input type="radio" name="modo_calculo" value="aportacion" checked>
                                        <span>Por aportación mensual</span>
                                    </label>
                                    <label class="bh-meta-mode-option">
                                        <input type="radio" name="modo_calculo" value="fecha">
                                        <span>Por fecha objetivo</span>
                                    </label>
                                </fieldset>

                                <div class="bh-field" data-mode-group="aportacion">
                                    <label class="bh-label" for="meta_aportacion_mensual">Aportación mensual</label>
                                    <input class="bh-input" type="number" id="meta_aportacion_mensual" name="aportacion_mensual" min="0.01" step="0.01" inputmode="decimal">
                                    <p class="bh-field-help">Debe caber dentro de tu capacidad disponible para nuevas metas.</p>
                                </div>

                                <div class="bh-field" data-mode-group="fecha" hidden>
                                    <label class="bh-label" for="meta_fecha_objetivo">Fecha objetivo</label>
                                    <input class="bh-input" type="date" id="meta_fecha_objetivo" name="fecha_objetivo">
                                    <p class="bh-field-help">Calcularemos la aportación mensual necesaria hasta esa fecha.</p>
                                </div>

                                <div class="bh-meta-form-note">
                                    Disponible para nuevas metas: <strong id="meta_capacidad_disponible"><?= $formatearEuros($ahorroDisponibleMetas) ?></strong>.
                                </div>

                                <button type="submit" class="bh-btn bh-btn-primary">
                                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                                    Guardar meta
                                </button>
                            </form>
                        </div>
                    </article>

                    <article class="bh-card bh-meta-list-card">
                        <div class="bh-card-header bh-meta-list-header">
                            <div>
                                <h3 class="titulo m-0">Tus metas activas</h3>
                                <p class="mb-0">Cada meta consume parte de la capacidad mensual configurada.</p>
                            </div>
                            <span class="bh-badge bh-badge-saving"><?= count($metasAhorroPreparadas) ?> activas</span>
                        </div>
                        <div class="bh-card-body">
                            <?php if (empty($metasAhorroPreparadas)): ?>
                                <div class="bh-empty-state bh-meta-empty-state">
                                    <div class="bh-empty-state-icon" aria-hidden="true">
                                        <i class="bi bi-journal-plus"></i>
                                    </div>
                                    <h4 class="bh-empty-state-title">Aún no tienes metas guardadas</h4>
                                    <p class="bh-empty-state-text">
                                        Crea una primera meta para comparar plazos de ahorro con tu capacidad mensual actual.
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="bh-meta-list">
                                    <?php foreach ($metasAhorroPreparadas as $meta): ?>
                                        <?php
                                        $metaId = intval($meta['id']);
                                        $modoMeta = $meta['modo_calculo'];
                                        ?>
                                        <article class="bh-meta-card">
                                            <div class="bh-meta-card-main">
                                                <div>
                                                    <p class="bh-meta-category"><?= htmlspecialchars($meta['categoria'], ENT_QUOTES, 'UTF-8') ?></p>
                                                    <h4><?= htmlspecialchars($meta['nombre'], ENT_QUOTES, 'UTF-8') ?></h4>
                                                </div>
                                                <span class="bh-badge bh-badge-saving">Estimación</span>
                                            </div>

                                            <div class="bh-meta-metrics">
                                                <p>
                                                    <span>Objetivo</span>
                                                    <strong><?= $formatearEuros($meta['importe_objetivo']) ?></strong>
                                                </p>
                                                <p>
                                                    <span>Aportación mensual</span>
                                                    <strong><?= $formatearEuros($meta['aportacion_mensual']) ?></strong>
                                                </p>
                                                <p>
                                                    <span>Plazo estimado</span>
                                                    <strong><?= htmlspecialchars($formatearPlazo($meta['plazo_meses_estimado']), ENT_QUOTES, 'UTF-8') ?></strong>
                                                </p>
                                                <p>
                                                    <span>Finalización estimada</span>
                                                    <strong><?= htmlspecialchars($formatearFecha($meta['fecha_finalizacion_estimada']), ENT_QUOTES, 'UTF-8') ?></strong>
                                                </p>
                                            </div>

                                            <p class="bh-meta-estimation-copy mb-0">
                                                Resultado orientativo: no garantiza que la meta se alcance en esa fecha ni modifica tus datos reales.
                                            </p>

                                            <details class="bh-meta-edit-details">
                                                <summary>Editar meta</summary>
                                                <form method="POST" action="index.php?r=simulador/actualizarMetaAhorro" class="bh-form js-meta-form bh-meta-edit-form">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= $metaId ?>">

                                                    <div class="bh-field-row">
                                                        <div class="bh-field">
                                                            <label class="bh-label" for="meta_nombre_<?= $metaId ?>">Nombre</label>
                                                            <input class="bh-input" type="text" id="meta_nombre_<?= $metaId ?>" name="nombre" maxlength="100" required value="<?= htmlspecialchars($meta['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                                                        </div>
                                                        <div class="bh-field">
                                                            <label class="bh-label" for="meta_categoria_<?= $metaId ?>">Categoría</label>
                                                            <input class="bh-input" type="text" id="meta_categoria_<?= $metaId ?>" name="categoria" maxlength="60" required value="<?= htmlspecialchars($meta['categoria'], ENT_QUOTES, 'UTF-8') ?>">
                                                        </div>
                                                    </div>

                                                    <div class="bh-field">
                                                        <label class="bh-label" for="meta_importe_<?= $metaId ?>">Importe objetivo</label>
                                                        <input class="bh-input" type="number" id="meta_importe_<?= $metaId ?>" name="importe_objetivo" min="0.01" step="0.01" inputmode="decimal" required value="<?= htmlspecialchars((string) $meta['importe_objetivo'], ENT_QUOTES, 'UTF-8') ?>">
                                                    </div>

                                                    <fieldset class="bh-meta-mode-fieldset">
                                                        <legend class="bh-label">Modo de cálculo</legend>
                                                        <label class="bh-meta-mode-option">
                                                            <input type="radio" name="modo_calculo" value="aportacion" <?= $modoMeta === 'aportacion' ? 'checked' : '' ?>>
                                                            <span>Por aportación mensual</span>
                                                        </label>
                                                        <label class="bh-meta-mode-option">
                                                            <input type="radio" name="modo_calculo" value="fecha" <?= $modoMeta === 'fecha' ? 'checked' : '' ?>>
                                                            <span>Por fecha objetivo</span>
                                                        </label>
                                                    </fieldset>

                                                    <div class="bh-field" data-mode-group="aportacion" <?= $modoMeta === 'fecha' ? 'hidden' : '' ?>>
                                                        <label class="bh-label" for="meta_aportacion_<?= $metaId ?>">Aportación mensual</label>
                                                        <input class="bh-input" type="number" id="meta_aportacion_<?= $metaId ?>" name="aportacion_mensual" min="0.01" step="0.01" inputmode="decimal" value="<?= htmlspecialchars((string) $meta['aportacion_mensual'], ENT_QUOTES, 'UTF-8') ?>">
                                                    </div>

                                                    <div class="bh-field" data-mode-group="fecha" <?= $modoMeta === 'aportacion' ? 'hidden' : '' ?>>
                                                        <label class="bh-label" for="meta_fecha_<?= $metaId ?>">Fecha objetivo</label>
                                                        <input class="bh-input" type="date" id="meta_fecha_<?= $metaId ?>" name="fecha_objetivo" value="<?= htmlspecialchars((string) ($meta['fecha_objetivo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    </div>

                                                    <button type="submit" class="bh-btn bh-btn-secondary">Guardar cambios</button>
                                                </form>
                                            </details>

                                            <form method="POST" action="index.php?r=simulador/eliminarMetaAhorro" class="bh-meta-delete-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $metaId ?>">
                                                <button type="submit" class="bh-btn bh-btn-danger" data-confirm="Eliminar esta meta retirará su aportación de la capacidad usada. ¿Quieres continuar?">
                                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                                    Eliminar meta
                                                </button>
                                            </form>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            </section>
        </main>
    </div>

    <?php bh_mobile_menu(); ?>
    <script>
        window.CSRF_TOKEN = "<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>";
    </script>
    <script src="<?= BASE_URL ?>js/simulador.js?v=<?= time() ?>"></script>
</body>

</html>
