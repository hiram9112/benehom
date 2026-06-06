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

    $formatearOpcionConImporte = static function ($texto, $importe, $prefijo = ''): string {
        $texto = trim((string) $texto);
        $importeTexto = $prefijo . formatearCantidadPHP($importe) . ' €';

        return $texto . ' --> ' . $importeTexto;
    };

    $frecuenciasReinversion = [
        'mensual' => 'Mensual',
        'trimestral' => 'Trimestral',
        'semestral' => 'Semestral',
        'anual' => 'Anual',
    ];
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

            <?php if (!empty($avisoGastosFlexibles)): ?>
                <div class="bh-alert bh-alert-warning mb-4">
                    <?= htmlspecialchars($avisoGastosFlexibles, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($avisoEscenariosInversion)): ?>
                <div class="bh-alert bh-alert-warning mb-4">
                    <?= htmlspecialchars($avisoEscenariosInversion, ENT_QUOTES, 'UTF-8') ?>
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
                                        <label class="bh-label" for="meta_importe_objetivo">Importe objetivo</label>
                                        <input class="bh-input" type="number" id="meta_importe_objetivo" name="importe_objetivo" min="0.01" step="0.01" inputmode="decimal" required>
                                    </div>
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
                                <p class="mb-0">Cada meta consume parte de la capacidad mensual configurada. Los valores con lápiz se pueden editar.</p>
                            </div>
                            <span class="bh-badge bh-badge-saving"><?= count($metasAhorroPreparadas) ?> <?= count($metasAhorroPreparadas) === 1 ? 'activa' : 'activas' ?></span>
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
                                        <article
                                            class="bh-meta-card"
                                            data-meta-card
                                            data-importe-objetivo="<?= htmlspecialchars((string) $meta['importe_objetivo'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-aportacion-original="<?= htmlspecialchars((string) $meta['aportacion_mensual'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-plazo-original="<?= htmlspecialchars((string) ($meta['plazo_meses_estimado'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                            <div class="bh-meta-card-main">
                                                <div>
                                                    <div class="bh-meta-title-row">
                                                        <h4><?= htmlspecialchars($meta['nombre'], ENT_QUOTES, 'UTF-8') ?></h4>
                                                        <span class="bh-meta-simulation-badge" data-simulation-badge hidden>
                                                            Simulación
                                                            <button type="button" class="bh-meta-simulation-clear" data-simulation-clear aria-label="Limpiar simulación">
                                                                &times;
                                                            </button>
                                                        </span>
                                                    </div>
                                                </div>
                                                <span class="bh-badge bh-badge-saving">Estimación</span>
                                            </div>

                                            <div class="bh-meta-metrics">
                                                <p>
                                                    <span>Objetivo</span>
                                                    <strong
                                                        class="bh-editable-value"
                                                        data-meta-target-amount
                                                        data-meta-id="<?= $metaId ?>"
                                                        data-value="<?= htmlspecialchars((string) $meta['importe_objetivo'], ENT_QUOTES, 'UTF-8') ?>"
                                                        title="Haz clic para editar"
                                                        role="button"
                                                        tabindex="0"
                                                        aria-label="Editar importe objetivo de la meta">
                                                        <span data-editable-text><?= $formatearEuros($meta['importe_objetivo']) ?></span>
                                                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                                                    </strong>
                                                </p>
                                                <p>
                                                    <span>Aportación mensual</span>
                                                    <strong data-simulation-value="aportacion"><?= $formatearEuros($meta['aportacion_mensual']) ?></strong>
                                                </p>
                                                <p>
                                                    <span>Plazo estimado</span>
                                                    <strong>
                                                        <span data-simulation-value="plazo"><?= htmlspecialchars($formatearPlazo($meta['plazo_meses_estimado']), ENT_QUOTES, 'UTF-8') ?></span>
                                                        <span class="bh-meta-simulation-improvement" data-simulation-value="mejora" hidden></span>
                                                    </strong>
                                                </p>
                                                <p>
                                                    <span>Finalización estimada</span>
                                                    <strong data-simulation-value="fecha"><?= htmlspecialchars($formatearFecha($meta['fecha_finalizacion_estimada']), ENT_QUOTES, 'UTF-8') ?></strong>
                                                </p>
                                            </div>

                                            <p class="bh-meta-estimation-copy mb-0">
                                                Resultado orientativo: no garantiza que la meta se alcance en esa fecha ni modifica tus datos reales.
                                            </p>

                                            <div class="bh-meta-flex-simulation" aria-label="Simulación de reducción de gastos flexibles">
                                                <div class="bh-meta-simulation-header">
                                                    <h5 class="bh-meta-simulation-title">Simular reducción de gastos flexible</h5>
                                                    <button type="button"
                                                        class="bh-btn bh-btn-icon bh-btn-ghost info-btn"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#infoSimulacionGastosFlexibles"
                                                        aria-label="Información sobre simulación de gastos flexibles">
                                                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                                                    </button>
                                                </div>

                                                <?php if (empty($gastosFlexiblesPorCategoria)): ?>
                                                    <p class="bh-field-help mb-0">No hay gastos flexibles registrados en el mes seleccionado para simular una reducción.</p>
                                                <?php else: ?>
                                                    <div class="bh-category-picker bh-meta-simulation-controls" data-simulation-picker>
                                                        <div class="bh-field">
                                                            <label class="bh-label" for="meta_sim_categoria_<?= $metaId ?>">Categoría flexible</label>
                                                            <div class="bh-select-shell">
                                                                <select class="bh-select" id="meta_sim_categoria_<?= $metaId ?>" data-simulation-category>
                                                                    <option value="">Sin simulación</option>
                                                                    <?php foreach ($gastosFlexiblesPorCategoria as $gastoFlexibleCategoria): ?>
                                                                        <?php
                                                                        $categoriaFlexible = (string) $gastoFlexibleCategoria['categoria'];
                                                                        $totalCategoriaFlexible = floatval($gastoFlexibleCategoria['total']);
                                                                        ?>
                                                                        <option
                                                                            value="<?= htmlspecialchars($categoriaFlexible, ENT_QUOTES, 'UTF-8') ?>"
                                                                            data-label="<?= htmlspecialchars(formatearCategoria($categoriaFlexible), ENT_QUOTES, 'UTF-8') ?>"
                                                                            data-total="<?= htmlspecialchars((string) $totalCategoriaFlexible, ENT_QUOTES, 'UTF-8') ?>">
                                                                            <?= htmlspecialchars($formatearOpcionConImporte(formatearCategoria($categoriaFlexible), $totalCategoriaFlexible), ENT_QUOTES, 'UTF-8') ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div class="bh-field">
                                                            <label class="bh-label" for="meta_sim_porcentaje_<?= $metaId ?>">Reducción simulada</label>
                                                            <div class="bh-select-shell">
                                                                <select class="bh-select" id="meta_sim_porcentaje_<?= $metaId ?>" data-simulation-percent disabled>
                                                                    <option value="">Elige primero una categoría</option>
                                                                    <option value="25" data-percent-label="25%">25%</option>
                                                                    <option value="50" data-percent-label="50%">50%</option>
                                                                    <option value="75" data-percent-label="75%">75%</option>
                                                                    <option value="100" data-percent-label="100%">100%</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <p class="bh-meta-simulation-result" data-simulation-message hidden></p>
                                                <?php endif; ?>
                                            </div>

                                            <div class="bh-meta-actions">
                                                <form method="POST" action="index.php?r=simulador/eliminarMetaAhorro" class="bh-meta-delete-form">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= $metaId ?>">
                                                    <button type="submit" class="bh-btn bh-btn-danger" data-confirm="Eliminar esta meta retirará su aportación de la capacidad usada. ¿Quieres continuar?">
                                                        <i class="bi bi-trash3" aria-hidden="true"></i>
                                                        Eliminar meta
                                                    </button>
                                                </form>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            </section>

            <section aria-labelledby="inversion-educativa-titulo" class="bh-simulator-investments">
                <div class="bh-page-header">
                    <div>
                        <p class="bh-simulator-kicker">Interés compuesto</p>
                        <h2 id="inversion-educativa-titulo">Escenarios de inversión educativa</h2>
                        <p>
                            Guarda hipótesis para entender cómo influye la frecuencia de reinversión de beneficios.
                            La rentabilidad indicada es anual y BeneHom la reparte según la frecuencia elegida.
                        </p>
                    </div>
                </div>

                <div class="bh-alert bh-alert-info mb-4">
                    Este módulo no es asesoramiento financiero. No recomienda productos, entidades, activos ni estrategias.
                    La rentabilidad usada es una hipótesis introducida por ti y el resultado es una estimación.
                </div>

                <div class="bh-simulator-investments-grid">
                    <article class="bh-card bh-investment-form-card">
                        <div class="bh-card-header">
                            <h3 class="titulo m-0">
                                <i class="bi bi-graph-up-arrow me-2" aria-hidden="true"></i>
                                Nuevo escenario de inversión
                            </h3>
                        </div>
                        <div class="bh-card-body">
                            <form method="POST" action="index.php?r=simulador/crearEscenarioInversion" class="bh-form">
                                <?= csrf_field() ?>

                                <div class="bh-field">
                                    <label class="bh-label" for="inversion_nombre">Nombre</label>
                                    <input class="bh-input" type="text" id="inversion_nombre" name="nombre" maxlength="100" required placeholder="Ejemplo: Escenario 1">
                                </div>

                                <div class="bh-field-row">
                                    <div class="bh-field">
                                        <label class="bh-label" for="inversion_capital_inicial">Capital inicial</label>
                                        <input class="bh-input" type="number" id="inversion_capital_inicial" name="capital_inicial" min="0" step="0.01" inputmode="decimal" required>
                                    </div>
                                    <div class="bh-field">
                                        <label class="bh-label" for="inversion_aportacion_mensual">Aportación mensual</label>
                                        <input class="bh-input" type="number" id="inversion_aportacion_mensual" name="aportacion_mensual" min="0" step="0.01" inputmode="decimal" required>
                                    </div>
                                </div>

                                <div class="bh-field-row">
                                    <div class="bh-field">
                                        <label class="bh-label" for="inversion_rentabilidad_anual">Rentabilidad anual estimada (%)</label>
                                        <input class="bh-input" type="number" id="inversion_rentabilidad_anual" name="rentabilidad_anual" min="0" step="0.01" inputmode="decimal" required>
                                    </div>
                                    <div class="bh-field">
                                        <label class="bh-label" for="inversion_plazo_anios">Plazo en años</label>
                                        <input class="bh-input" type="number" id="inversion_plazo_anios" name="plazo_anios" min="1" step="1" required>
                                    </div>
                                </div>

                                <div class="bh-field">
                                    <label class="bh-label" for="inversion_frecuencia_reinversion">Frecuencia de reinversión de beneficios</label>
                                    <div class="bh-select-shell">
                                        <select class="bh-select" id="inversion_frecuencia_reinversion" name="frecuencia_reinversion" required>
                                            <?php foreach ($frecuenciasReinversion as $valorFrecuencia => $labelFrecuencia): ?>
                                                <option value="<?= htmlspecialchars($valorFrecuencia, ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($labelFrecuencia, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <p class="bh-field-help">
                                        Ejemplo: con una rentabilidad anual del 5%, mensual aplica 5%/12 cada mes y trimestral aplica 5%/4 cada trimestre.
                                    </p>
                                </div>

                                <button type="submit" class="bh-btn bh-btn-primary">
                                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                                    Guardar escenario
                                </button>
                            </form>
                        </div>
                    </article>

                    <article class="bh-card bh-investment-list-card">
                        <div class="bh-card-header bh-meta-list-header">
                            <div>
                                <h3 class="titulo m-0">Tus escenarios</h3>
                                <p class="mb-0">Compara capital aportado, valor final estimado y rendimiento hipotético. Los valores con lápiz se pueden editar.</p>
                            </div>
                            <span class="bh-badge bh-badge-saving"><?= count($escenariosInversionPreparados) ?> <?= count($escenariosInversionPreparados) === 1 ? 'guardado' : 'guardados' ?></span>
                        </div>
                        <div class="bh-card-body">
                            <?php if (empty($escenariosInversionPreparados)): ?>
                                <div class="bh-empty-state bh-meta-empty-state">
                                    <div class="bh-empty-state-icon" aria-hidden="true">
                                        <i class="bi bi-graph-up"></i>
                                    </div>
                                    <h4 class="bh-empty-state-title">Aún no tienes escenarios de inversión</h4>
                                    <p class="bh-empty-state-text">
                                        Crea una primera hipótesis para visualizar el efecto del interés compuesto.
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="bh-investment-list">
                                    <?php foreach ($escenariosInversionPreparados as $escenario): ?>
                                        <?php $escenarioId = intval($escenario['id']); ?>
                                        <article class="bh-investment-card" data-investment-card data-investment-id="<?= $escenarioId ?>">
                                            <div class="bh-meta-card-main">
                                                <div>
                                                    <h4><?= htmlspecialchars($escenario['nombre'], ENT_QUOTES, 'UTF-8') ?></h4>
                                                    <p class="bh-investment-card-copy mb-0">
                                                        Reinversión <?= htmlspecialchars(strtolower($escenario['frecuencia_reinversion_label']), ENT_QUOTES, 'UTF-8') ?>:
                                                        <?= intval($escenario['periodos_por_anio']) ?> <?= intval($escenario['periodos_por_anio']) === 1 ? 'pago' : 'pagos' ?> al año.
                                                    </p>
                                                </div>
                                                <span class="bh-badge bh-badge-saving">Estimación</span>
                                            </div>

                                            <div class="bh-meta-metrics bh-investment-metrics">
                                                <p>
                                                    <span>Capital inicial</span>
                                                    <strong
                                                        class="bh-editable-value"
                                                        data-investment-field="capital_inicial"
                                                        data-investment-value="capital_inicial"
                                                        data-value="<?= htmlspecialchars((string) $escenario['capital_inicial'], ENT_QUOTES, 'UTF-8') ?>"
                                                        title="Haz clic para editar"
                                                        role="button"
                                                        tabindex="0"
                                                        aria-label="Editar capital inicial">
                                                        <span data-editable-text><?= $formatearEuros($escenario['capital_inicial']) ?></span>
                                                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                                                    </strong>
                                                </p>
                                                <p>
                                                    <span>Aportación mensual</span>
                                                    <strong
                                                        class="bh-editable-value"
                                                        data-investment-field="aportacion_mensual"
                                                        data-investment-value="aportacion_mensual"
                                                        data-value="<?= htmlspecialchars((string) $escenario['aportacion_mensual'], ENT_QUOTES, 'UTF-8') ?>"
                                                        title="Haz clic para editar"
                                                        role="button"
                                                        tabindex="0"
                                                        aria-label="Editar aportación mensual">
                                                        <span data-editable-text><?= $formatearEuros($escenario['aportacion_mensual']) ?></span>
                                                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                                                    </strong>
                                                </p>
                                                <p>
                                                    <span>Capital total aportado</span>
                                                    <strong data-investment-value="capital_total_aportado"><?= $formatearEuros($escenario['capital_total_aportado']) ?></strong>
                                                </p>
                                                <p>
                                                    <span>Valor final estimado</span>
                                                    <strong data-investment-value="valor_final_estimado"><?= $formatearEuros($escenario['valor_final_estimado']) ?></strong>
                                                </p>
                                                <p>
                                                    <span>Rendimiento estimado</span>
                                                    <strong data-investment-value="rendimiento_estimado"><?= $formatearEuros($escenario['rendimiento_estimado']) ?></strong>
                                                </p>
                                                <p>
                                                    <span>Rendimiento anual</span>
                                                    <strong
                                                        class="bh-editable-value"
                                                        data-investment-field="rentabilidad_anual"
                                                        data-investment-value="rentabilidad_anual"
                                                        data-value="<?= htmlspecialchars((string) $escenario['rentabilidad_anual'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-suffix="%"
                                                        title="Haz clic para editar"
                                                        role="button"
                                                        tabindex="0"
                                                        aria-label="Editar rendimiento anual">
                                                        <span data-editable-text><?= formatearCantidadPHP($escenario['rentabilidad_anual']) ?>%</span>
                                                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                                                    </strong>
                                                </p>
                                            </div>

                                            <p class="bh-meta-estimation-copy mb-0">
                                                Cuanto antes se reinvierten los beneficios, antes forman parte del capital y mayor puede ser el efecto compuesto estimado.
                                            </p>

                                            <form method="POST" action="index.php?r=simulador/eliminarEscenarioInversion" class="bh-meta-delete-form bh-investment-delete-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $escenarioId ?>">
                                                <button type="submit" class="bh-btn bh-btn-danger" data-confirm="Eliminar este escenario de inversión no modificará ningún dato real. ¿Quieres continuar?">
                                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                                    Eliminar escenario
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

    <div class="modal fade" id="infoSimulacionGastosFlexibles" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Simular reducción de gastos flexible</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <p>
                        Elige una categoría flexible registrada en el mes seleccionado y un porcentaje de reducción.
                        BeneHom calcula cuánto podrías aportar de forma adicional a esa meta.
                    </p>
                    <p>
                        La comparación actualiza solo la card: muestra la aportación simulada, el nuevo plazo estimado
                        y la fecha aproximada. No modifica tus gastos reales ni cambia la meta guardada.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <?php bh_mobile_menu(); ?>
    <script>
        window.CSRF_TOKEN = "<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>";
    </script>
    <script src="<?= BASE_URL ?>js/simulador.js?v=<?= time() ?>"></script>
</body>

</html>
