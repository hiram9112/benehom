<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Proyecciones - BeneHom</title>
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
    require_once APP_PATH . '/views/partials/flash-messages.php';
    bh_flash_messages();
    bh_mobile_nav();

    $formatearCantidadProyecciones = static function ($cantidad): string {
        return number_format((float) $cantidad, 0, ',', '.');
    };

    $formatearEuros = static function ($cantidad) use ($formatearCantidadProyecciones): string {
        return $formatearCantidadProyecciones($cantidad) . ' €';
    };

    $formatearPorcentaje = static function ($cantidad) use ($formatearCantidadProyecciones): string {
        return $formatearCantidadProyecciones($cantidad) . '%';
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

    $formatearOpcionConImporte = static function ($texto, $importe, $prefijo = '') use ($formatearCantidadProyecciones): string {
        $texto = trim((string) $texto);
        $importeTexto = $prefijo . $formatearCantidadProyecciones($importe) . ' €';

        return $texto . ' --> ' . $importeTexto;
    };

    $frecuenciasReinversion = [
        'mensual' => 'Mensual',
        'trimestral' => 'Trimestral',
        'semestral' => 'Semestral',
        'anual' => 'Anual',
    ];
    ?>

    <!--Contenedor Principal-->
    <div class="bh-app-shell">
        <?php bh_sidebar(); ?>

        <!--Panel Central-->
        <main class="bh-main">
            <section class="bh-projections-hero mb-4" aria-labelledby="proyecciones-titulo">
                <article class="bh-card bh-card-finance bh-projections-intro-card">
                    <div class="bh-card-body">
                        <p class="bh-projections-kicker">Planificación y proyecciones</p>
                        <h1 id="proyecciones-titulo">Proyecciones</h1>
                        <p>
                            Crea metas de ahorro, proyecta reducciones de gastos flexibles, explora escenarios de inversión educativa,
                            calcula el impacto de la inflación o estima cuotas hipotecarias. Todo sin modificar tus datos reales.
                        </p>
                        <p>
                            BeneHom usa como referencia el ahorro mensual del mes seleccionado en el Dashboard. Puedes editar
                            esa cantidad para probar escenarios sin alterar tus ingresos, gastos ni el resumen del mes.
                        </p>
                        <p class="mb-0">
                            Los resultados son estimaciones orientativas, no garantías ni recomendaciones financieras.
                        </p>
                    </div>
                </article>

                <article class="bh-card bh-projections-savings-card" aria-labelledby="ahorro-mensual-disponible-label">
                    <div class="bh-card-body">
                        <p id="ahorro-mensual-disponible-label" class="bh-projections-savings-label">Ahorro mensual disponible</p>
                        <div class="bh-projections-savings-value">
                            <span
                                id="ahorro_mensual_disponible"
                                class="bh-projections-savings-amount"
                                role="button"
                                tabindex="0"
                                aria-label="Editar ahorro mensual disponible"
                                data-value="<?= htmlspecialchars((string) $ahorroMensualDisponible, ENT_QUOTES, 'UTF-8') ?>">
                                <?= $formatearCantidadProyecciones($ahorroMensualDisponible) ?>
                            </span>
                            <span class="bh-projections-currency">€</span>
                        </div>
                        <p class="bh-projections-edit-hint">Haz clic en el importe para editarlo.</p>

                        <div class="bh-projections-savings-breakdown">
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
                    El ahorro asignado a metas supera el ahorro mensual disponible. Puedes ajustar el importe para proyectar
                    sin cambiar tus datos reales.
                </div>
            <?php endif; ?>

            <section aria-labelledby="metas-ahorro-titulo" class="bh-projections-goals">
                <div class="bh-projections-goals-grid">
                    <article class="bh-card bh-meta-list-card">
                        <div class="bh-card-header bh-projections-module-header">
                            <div>
                                <h2 id="metas-ahorro-titulo">Metas de ahorro</h2>
                                <p>
                                    Crea metas proyectadas para estimar plazos o calcular cuánto necesitarías aportar al mes.
                                    No representan dinero apartado realmente. Cada meta consume parte de la capacidad mensual configurada.
                                </p>
                            </div>
                            <div class="bh-projections-module-actions">
                                <span class="bh-badge bh-badge-saving"><?= count($metasAhorroPreparadas) ?> <?= count($metasAhorroPreparadas) === 1 ? 'activa' : 'activas' ?></span>
                                <button type="button" class="bh-btn bh-btn-primary" data-bs-toggle="offcanvas" data-bs-target="#crearMetaAhorroPanel" aria-controls="crearMetaAhorroPanel">
                                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                                    Nueva proyección
                                </button>
                            </div>
                        </div>
                        <div class="bh-card-body">
                            <?php if (empty($metasAhorroPreparadas)): ?>
                                <div class="bh-empty-state bh-meta-empty-state">
                                    <div class="bh-empty-state-icon" aria-hidden="true">
                                        <i class="bi bi-journal-plus"></i>
                                    </div>
                                    <h4 class="bh-empty-state-title">Aún no tienes metas guardadas</h4>
                                    <p class="bh-empty-state-text">
                                        Define un objetivo, indica cuánto puedes aportar al mes y BeneHom estima el plazo para alcanzarlo.
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
                                                        <span class="bh-meta-projection-badge" data-projection-badge hidden>
                                                            Proyección
                                                            <button type="button" class="bh-meta-projection-clear" data-projection-clear aria-label="Limpiar proyección">
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
                                                    <strong data-projection-value="aportacion"><?= $formatearEuros($meta['aportacion_mensual']) ?></strong>
                                                </p>
                                                <p>
                                                    <span>Plazo estimado</span>
                                                    <strong>
                                                        <span data-projection-value="plazo"><?= htmlspecialchars($formatearPlazo($meta['plazo_meses_estimado']), ENT_QUOTES, 'UTF-8') ?></span>
                                                        <span class="bh-meta-projection-improvement" data-projection-value="mejora" hidden></span>
                                                    </strong>
                                                </p>
                                                <p>
                                                    <span>Finalización estimada</span>
                                                    <strong data-projection-value="fecha"><?= htmlspecialchars($formatearFecha($meta['fecha_finalizacion_estimada']), ENT_QUOTES, 'UTF-8') ?></strong>
                                                </p>
                                            </div>

                                            <p class="bh-meta-estimation-copy mb-0">
                                                Resultado orientativo: no garantiza que la meta se alcance en esa fecha ni modifica tus datos reales.
                                            </p>

                                            <div class="bh-meta-flex-projection" aria-label="Proyección de reducción de gastos flexibles">
                                                <div class="bh-meta-projection-header">
                                                    <h5 class="bh-meta-projection-title">Proyectar reducción de gastos flexible</h5>
                                                    <button type="button"
                                                        class="bh-btn bh-btn-icon bh-btn-ghost info-btn"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#infoProyeccionGastosFlexibles"
                                                        aria-label="Información sobre proyección de gastos flexibles">
                                                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                                                    </button>
                                                </div>

                                                <?php if (empty($gastosFlexiblesPorCategoria)): ?>
                                                    <p class="bh-field-help mb-0">No hay gastos flexibles registrados en el mes seleccionado para proyectar una reducción.</p>
                                                <?php else: ?>
                                                    <div class="bh-category-picker bh-meta-projection-controls" data-projection-picker>
                                                        <div class="bh-field">
                                                            <label class="bh-label" for="meta_proyeccion_categoria_<?= $metaId ?>">Categoría flexible</label>
                                                            <div class="bh-select-shell">
                                                                <select class="bh-select" id="meta_proyeccion_categoria_<?= $metaId ?>" data-projection-category>
                                                                    <option value="">Sin proyección</option>
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
                                                            <label class="bh-label" for="meta_proyeccion_porcentaje_<?= $metaId ?>">Reducción proyectada</label>
                                                            <div class="bh-select-shell">
                                                                <select class="bh-select" id="meta_proyeccion_porcentaje_<?= $metaId ?>" data-projection-percent disabled>
                                                                    <option value="">Elige primero una categoría</option>
                                                                    <option value="25" data-percent-label="25%">25%</option>
                                                                    <option value="50" data-percent-label="50%">50%</option>
                                                                    <option value="75" data-percent-label="75%">75%</option>
                                                                    <option value="100" data-percent-label="100%">100%</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <p class="bh-meta-projection-result" data-projection-message hidden></p>
                                                <?php endif; ?>
                                            </div>

                                            <div class="bh-meta-actions">
                                                <form method="POST" action="index.php?r=proyecciones/eliminarMetaAhorro" class="bh-meta-delete-form">
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

            <section aria-labelledby="inversion-educativa-titulo" class="bh-projections-investments">
                <div class="bh-projections-investments-grid">
                    <article class="bh-card bh-investment-list-card">
                        <div class="bh-card-header bh-projections-module-header">
                            <div>
                                <h2 id="inversion-educativa-titulo">Escenarios de inversión educativa</h2>
                                <p>
                                    Guarda hipótesis para entender cómo influye el interés compuesto y la frecuencia de reinversión
                                    de beneficios. La rentabilidad indicada es anual, educativa y no representa una recomendación financiera.
                                </p>
                            </div>
                            <div class="bh-projections-module-actions">
                                <span class="bh-badge bh-badge-saving"><?= count($escenariosInversionPreparados) ?> <?= count($escenariosInversionPreparados) === 1 ? 'guardado' : 'guardados' ?></span>
                                <button type="button" class="bh-btn bh-btn-primary" data-bs-toggle="offcanvas" data-bs-target="#crearEscenarioInversionPanel" aria-controls="crearEscenarioInversionPanel">
                                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                                    Nueva proyección
                                </button>
                            </div>
                        </div>
                        <div class="bh-card-body">
                            <?php if (empty($escenariosInversionPreparados)): ?>
                                <div class="bh-empty-state bh-meta-empty-state">
                                    <div class="bh-empty-state-icon" aria-hidden="true">
                                        <i class="bi bi-graph-up"></i>
                                    </div>
                                    <h4 class="bh-empty-state-title">Aún no tienes escenarios de inversión</h4>
                                    <p class="bh-empty-state-text">
                                        Crea una hipótesis para visualizar cómo el interés compuesto y la frecuencia de reinversión afectan al valor final estimado.
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
                                                        <span data-editable-text><?= $formatearPorcentaje($escenario['rentabilidad_anual']) ?></span>
                                                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                                                    </strong>
                                                </p>
                                            </div>

                                            <p class="bh-meta-estimation-copy mb-0">
                                                Cuanto antes se reinvierten los beneficios, antes forman parte del capital y mayor puede ser el efecto compuesto estimado.
                                            </p>

                                            <form method="POST" action="index.php?r=proyecciones/eliminarEscenarioInversion" class="bh-meta-delete-form bh-investment-delete-form">
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

            <?php if (!empty($avisoProyeccionesInflacion)): ?>
                <div class="bh-alert bh-alert-warning mb-4">
                    <?= htmlspecialchars($avisoProyeccionesInflacion, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <section aria-labelledby="inflacion-temporal-titulo" class="bh-projections-inflation">
                <div class="bh-projections-inflation-grid">
                    <article class="bh-card bh-inflation-list-card">
                        <div class="bh-card-header bh-projections-module-header">
                            <div>
                                <h2 id="inflacion-temporal-titulo">Calculadora de inflación</h2>
                                <p>
                                    Proyecta cómo una inflación anual estimada podría afectar al poder adquisitivo
                                    de una cantidad. La inflación no reduce el número de euros, sino lo que esos euros pueden comprar.
                                </p>
                            </div>
                            <div class="bh-projections-module-actions">
                                <span class="bh-badge bh-badge-saving"><?= count($proyeccionesInflacionPreparadas) ?> <?= count($proyeccionesInflacionPreparadas) === 1 ? 'guardada' : 'guardadas' ?></span>
                                <button type="button" class="bh-btn bh-btn-primary" data-bs-toggle="offcanvas" data-bs-target="#crearInflacionProyeccionPanel" aria-controls="crearInflacionProyeccionPanel">
                                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                                    Nueva proyección
                                </button>
                            </div>
                        </div>
                        <div class="bh-card-body">
                            <?php if (empty($proyeccionesInflacionPreparadas)): ?>
                                <div class="bh-empty-state bh-meta-empty-state">
                                    <div class="bh-empty-state-icon" aria-hidden="true">
                                        <i class="bi bi-cash-stack"></i>
                                    </div>
                                    <h4 class="bh-empty-state-title">Aún no tienes proyecciones de inflación</h4>
                                    <p class="bh-empty-state-text">
                                        Crea una proyección para ver cómo una inflación anual estimada reduce lo que tus euros pueden comprar con el tiempo.
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="bh-inflation-list">
                                    <?php foreach ($proyeccionesInflacionPreparadas as $proyeccion): ?>
                                        <?php $proyeccionId = intval($proyeccion['id']); ?>
                                        <article class="bh-inflation-card" data-inflacion-card data-inflacion-id="<?= $proyeccionId ?>">
                                            <div class="bh-meta-card-main">
                                                <div>
                                                    <h4><?= htmlspecialchars($proyeccion['nombre'], ENT_QUOTES, 'UTF-8') ?></h4>
                                                </div>
                                                <span class="bh-badge bh-badge-saving">Estimación</span>
                                            </div>

                                            <div class="bh-meta-metrics bh-inflation-metrics">
                                                <p>
                                                    <span>Cantidad inicial</span>
                                                    <strong
                                                        class="bh-editable-value"
                                                        data-inflacion-field="cantidad_inicial"
                                                        data-inflacion-value="cantidad_inicial"
                                                        data-value="<?= htmlspecialchars((string) $proyeccion['cantidad_inicial'], ENT_QUOTES, 'UTF-8') ?>"
                                                        title="Haz clic para editar"
                                                        role="button"
                                                        tabindex="0"
                                                        aria-label="Editar cantidad inicial">
                                                        <span data-editable-text><?= $formatearEuros($proyeccion['cantidad_inicial']) ?></span>
                                                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                                                    </strong>
                                                </p>
                                                <p>
                                                    <span>Inflación anual</span>
                                                    <strong
                                                        class="bh-editable-value"
                                                        data-inflacion-field="inflacion_anual"
                                                        data-inflacion-value="inflacion_anual"
                                                        data-value="<?= htmlspecialchars((string) $proyeccion['inflacion_anual'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-suffix="%"
                                                        title="Haz clic para editar"
                                                        role="button"
                                                        tabindex="0"
                                                        aria-label="Editar inflación anual">
                                                        <span data-editable-text><?= $formatearPorcentaje($proyeccion['inflacion_anual']) ?></span>
                                                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                                                    </strong>
                                                </p>
                                                <p>
                                                    <span>Plazo en años</span>
                                                    <strong
                                                        class="bh-editable-value"
                                                        data-inflacion-field="plazo_anios"
                                                        data-inflacion-value="plazo_anios"
                                                        data-value="<?= htmlspecialchars((string) $proyeccion['plazo_anios'], ENT_QUOTES, 'UTF-8') ?>"
                                                        title="Haz clic para editar"
                                                        role="button"
                                                        tabindex="0"
                                                        aria-label="Editar plazo en años">
                                                        <span data-editable-text><?= intval($proyeccion['plazo_anios']) ?> <?= intval($proyeccion['plazo_anios']) === 1 ? 'año' : 'años' ?></span>
                                                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                                                    </strong>
                                                </p>
                                                <p>
                                                    <span>Poder adquisitivo final</span>
                                                    <strong data-inflacion-value="poder_adquisitivo_final"><?= $formatearEuros($proyeccion['poder_adquisitivo_final']) ?></strong>
                                                </p>
                                                <p>
                                                    <span>Pérdida estimada</span>
                                                    <strong data-inflacion-value="perdida_estimada"><?= $formatearEuros($proyeccion['perdida_estimada']) ?></strong>
                                                </p>
                                                <p>
                                                    <span>Cantidad futura necesaria</span>
                                                    <strong data-inflacion-value="cantidad_futura_necesaria"><?= $formatearEuros($proyeccion['cantidad_futura_necesaria']) ?></strong>
                                                </p>
                                                <p>
                                                    <span>Diferencia necesaria</span>
                                                    <strong data-inflacion-value="diferencia_necesaria"><?= $formatearEuros($proyeccion['diferencia_necesaria']) ?></strong>
                                                </p>
                                            </div>

                                            <p class="bh-meta-estimation-copy mb-0">
                                                La inflación no reduce el número de euros, sino lo que esos euros pueden comprar. Este cálculo es una estimación educativa.
                                            </p>

                                            <form method="POST" action="index.php?r=proyecciones/eliminarInflacionProyeccion" class="bh-meta-delete-form bh-inflation-delete-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $proyeccionId ?>">
                                                <button type="submit" class="bh-btn bh-btn-danger" data-confirm="Eliminar esta proyección de inflación no modificará ningún dato real. ¿Quieres continuar?">
                                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                                    Eliminar proyección
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

            <?php if (!empty($avisoCalculadorasHipoteca)): ?>
                <div class="bh-alert bh-alert-warning mb-4">
                    <?= htmlspecialchars($avisoCalculadorasHipoteca, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <section aria-labelledby="hipoteca-calculadora-titulo" class="bh-projections-mortgage">
                <div class="bh-projections-mortgage-grid">
                    <article class="bh-card bh-mortgage-list-card">
                        <div class="bh-card-header bh-projections-module-header">
                            <div>
                                <h2 id="hipoteca-calculadora-titulo">Calculadora de hipoteca</h2>
                                <p>
                                    Proyecta cuotas mensuales, intereses totales y coste total de un préstamo hipotecario
                                    según el importe, el interés anual y el plazo. No representa una oferta vinculante ni una recomendación financiera.
                                </p>
                            </div>
                            <div class="bh-projections-module-actions">
                                <span class="bh-badge bh-badge-saving"><?= count($calculadorasHipotecaPreparadas) ?> <?= count($calculadorasHipotecaPreparadas) === 1 ? 'guardada' : 'guardadas' ?></span>
                                <button type="button" class="bh-btn bh-btn-primary" data-bs-toggle="offcanvas" data-bs-target="#crearCalculadoraHipotecaPanel" aria-controls="crearCalculadoraHipotecaPanel">
                                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                                    Nueva proyección
                                </button>
                            </div>
                        </div>
                        <div class="bh-card-body">
                            <?php if (empty($calculadorasHipotecaPreparadas)): ?>
                                <div class="bh-empty-state bh-meta-empty-state">
                                    <div class="bh-empty-state-icon" aria-hidden="true">
                                        <i class="bi bi-house"></i>
                                    </div>
                                    <h4 class="bh-empty-state-title">Aún no tienes calculadoras de hipoteca</h4>
                                    <p class="bh-empty-state-text">
                                        Proyecta cuotas mensuales, intereses totales y coste total de un préstamo hipotecario según importe, interés y plazo.
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="bh-mortgage-list">
                                    <?php foreach ($calculadorasHipotecaPreparadas as $calculadora): ?>
                                        <?php $calculadoraId = intval($calculadora['id']); ?>
                                        <article class="bh-mortgage-card" data-hipoteca-card data-hipoteca-id="<?= $calculadoraId ?>">
                                            <div class="bh-meta-card-main">
                                                <div>
                                                    <h4><?= htmlspecialchars($calculadora['nombre'], ENT_QUOTES, 'UTF-8') ?></h4>
                                                </div>
                                                <span class="bh-badge bh-badge-saving">Estimación</span>
                                            </div>

                                            <div class="bh-meta-metrics bh-mortgage-metrics">
                                                <p>
                                                    <span>Importe del préstamo</span>
                                                    <strong
                                                        class="bh-editable-value"
                                                        data-hipoteca-field="importe_prestamo"
                                                        data-hipoteca-value="importe_prestamo"
                                                        data-value="<?= htmlspecialchars((string) $calculadora['importe_prestamo'], ENT_QUOTES, 'UTF-8') ?>"
                                                        title="Haz clic para editar"
                                                        role="button"
                                                        tabindex="0"
                                                        aria-label="Editar importe del préstamo">
                                                        <span data-editable-text><?= $formatearEuros($calculadora['importe_prestamo']) ?></span>
                                                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                                                    </strong>
                                                </p>
                                                <p>
                                                    <span>Interés anual</span>
                                                    <strong
                                                        class="bh-editable-value"
                                                        data-hipoteca-field="interes_anual"
                                                        data-hipoteca-value="interes_anual"
                                                        data-value="<?= htmlspecialchars((string) $calculadora['interes_anual'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-suffix="%"
                                                        title="Haz clic para editar"
                                                        role="button"
                                                        tabindex="0"
                                                        aria-label="Editar interés anual">
                                                        <span data-editable-text><?= $formatearPorcentaje($calculadora['interes_anual']) ?></span>
                                                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                                                    </strong>
                                                </p>
                                                <p>
                                                    <span>Plazo en años</span>
                                                    <strong
                                                        class="bh-editable-value"
                                                        data-hipoteca-field="plazo_anios"
                                                        data-hipoteca-value="plazo_anios"
                                                        data-value="<?= htmlspecialchars((string) $calculadora['plazo_anios'], ENT_QUOTES, 'UTF-8') ?>"
                                                        title="Haz clic para editar"
                                                        role="button"
                                                        tabindex="0"
                                                        aria-label="Editar plazo en años">
                                                        <span data-editable-text><?= intval($calculadora['plazo_anios']) ?> <?= intval($calculadora['plazo_anios']) === 1 ? 'año' : 'años' ?></span>
                                                        <i class="bi bi-pencil bh-editable-icon" aria-hidden="true"></i>
                                                    </strong>
                                                </p>
                                                <p>
                                                    <span>Cuota mensual</span>
                                                    <strong data-hipoteca-value="cuota_mensual"><?= $formatearEuros($calculadora['cuota_mensual']) ?></strong>
                                                </p>
                                                <p>
                                                    <span>Total intereses</span>
                                                    <strong data-hipoteca-value="total_intereses"><?= $formatearEuros($calculadora['total_intereses']) ?></strong>
                                                </p>
                                                <p>
                                                    <span>Total pagado</span>
                                                    <strong data-hipoteca-value="total_pagado"><?= $formatearEuros($calculadora['total_pagado']) ?></strong>
                                                </p>
                                            </div>

                                            <p class="bh-meta-estimation-copy mb-0">
                                                Este cálculo es una estimación educativa. No representa una oferta vinculante ni una recomendación financiera. Consulta siempre condiciones reales con tu entidad.
                                            </p>

                                            <form method="POST" action="index.php?r=proyecciones/eliminarCalculadoraHipoteca" class="bh-meta-delete-form bh-mortgage-delete-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $calculadoraId ?>">
                                                <button type="submit" class="bh-btn bh-btn-danger" data-confirm="Eliminar esta calculadora de hipoteca no modificará ningún dato real. ¿Quieres continuar?">
                                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                                    Eliminar calculadora
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

    <div class="offcanvas offcanvas-end bh-projections-offcanvas" tabindex="-1" id="crearMetaAhorroPanel" aria-labelledby="crearMetaAhorroPanelLabel">
        <div class="offcanvas-header">
            <div>
                <p class="bh-projections-kicker mb-1">Meta proyectada</p>
                <h5 class="offcanvas-title" id="crearMetaAhorroPanelLabel">Nueva meta de ahorro</h5>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="index.php?r=proyecciones/crearMetaAhorro" class="bh-form js-meta-form">
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
                    Crear proyección
                </button>
            </form>
        </div>
    </div>

    <div class="offcanvas offcanvas-end bh-projections-offcanvas" tabindex="-1" id="crearEscenarioInversionPanel" aria-labelledby="crearEscenarioInversionPanelLabel">
        <div class="offcanvas-header">
            <div>
                <p class="bh-projections-kicker mb-1">Hipótesis educativa</p>
                <h5 class="offcanvas-title" id="crearEscenarioInversionPanelLabel">Nuevo escenario de inversión</h5>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="index.php?r=proyecciones/crearEscenarioInversion" class="bh-form">
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
                    Crear proyección
                </button>
            </form>
        </div>
    </div>

    <div class="offcanvas offcanvas-end bh-projections-offcanvas" tabindex="-1" id="crearInflacionProyeccionPanel" aria-labelledby="crearInflacionProyeccionPanelLabel">
        <div class="offcanvas-header">
            <div>
                <p class="bh-projections-kicker mb-1">Proyección educativa</p>
                <h5 class="offcanvas-title" id="crearInflacionProyeccionPanelLabel">Nueva proyección de inflación</h5>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="index.php?r=proyecciones/crearInflacionProyeccion" class="bh-form">
                <?= csrf_field() ?>

                <div class="bh-field">
                    <label class="bh-label" for="inflacion_nombre">Nombre</label>
                    <input class="bh-input" type="text" id="inflacion_nombre" name="nombre" maxlength="100" required placeholder="Ej. Escenario de inflación 1">
                </div>

                <div class="bh-field-row">
                    <div class="bh-field">
                        <label class="bh-label" for="inflacion_cantidad_inicial">Cantidad inicial</label>
                        <input class="bh-input" type="number" id="inflacion_cantidad_inicial" name="cantidad_inicial" min="0.01" step="0.01" inputmode="decimal" required>
                    </div>
                    <div class="bh-field">
                        <label class="bh-label" for="inflacion_inflacion_anual">Inflación anual estimada (%)</label>
                        <input class="bh-input" type="number" id="inflacion_inflacion_anual" name="inflacion_anual" min="0" step="0.01" inputmode="decimal" required>
                    </div>
                </div>

                <div class="bh-field">
                    <label class="bh-label" for="inflacion_plazo_anios">Plazo en años</label>
                    <input class="bh-input" type="number" id="inflacion_plazo_anios" name="plazo_anios" min="1" step="1" required>
                </div>

                <button type="submit" class="bh-btn bh-btn-primary">
                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                    Crear proyección
                </button>
            </form>
        </div>
    </div>

    <div class="offcanvas offcanvas-end bh-projections-offcanvas" tabindex="-1" id="crearCalculadoraHipotecaPanel" aria-labelledby="crearCalculadoraHipotecaPanelLabel">
        <div class="offcanvas-header">
            <div>
                <p class="bh-projections-kicker mb-1">Proyección educativa</p>
                <h5 class="offcanvas-title" id="crearCalculadoraHipotecaPanelLabel">Nueva calculadora de hipoteca</h5>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="index.php?r=proyecciones/crearCalculadoraHipoteca" class="bh-form">
                <?= csrf_field() ?>

                <div class="bh-field">
                    <label class="bh-label" for="hipoteca_nombre">Nombre</label>
                    <input class="bh-input" type="text" id="hipoteca_nombre" name="nombre" maxlength="100" required placeholder="Ej. Mi hipoteca">
                </div>

                <div class="bh-field-row">
                    <div class="bh-field">
                        <label class="bh-label" for="hipoteca_importe_prestamo">Importe del préstamo</label>
                        <input class="bh-input" type="number" id="hipoteca_importe_prestamo" name="importe_prestamo" min="0.01" step="0.01" inputmode="decimal" required>
                    </div>
                    <div class="bh-field">
                        <label class="bh-label" for="hipoteca_interes_anual">Interés anual (%)</label>
                        <input class="bh-input" type="number" id="hipoteca_interes_anual" name="interes_anual" min="0" step="0.01" inputmode="decimal" required>
                    </div>
                </div>

                <div class="bh-field">
                    <label class="bh-label" for="hipoteca_plazo_anios">Plazo en años</label>
                    <input class="bh-input" type="number" id="hipoteca_plazo_anios" name="plazo_anios" min="1" step="1" required>
                </div>

                <button type="submit" class="bh-btn bh-btn-primary">
                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                    Crear proyección
                </button>
            </form>
        </div>
    </div>

    <div class="modal fade" id="infoProyeccionGastosFlexibles" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Proyectar reducción de gastos flexible</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <p>
                        Elige una categoría flexible registrada en el mes seleccionado y un porcentaje de reducción.
                        BeneHom calcula cuánto podrías aportar de forma adicional a esa meta.
                    </p>
                    <p>
                        La comparación actualiza solo la card: muestra la aportación proyectada, el nuevo plazo estimado
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
    <script src="<?= BASE_URL ?>js/flash.js"></script>
    <script src="<?= BASE_URL ?>js/proyecciones.js?v=<?= time() ?>"></script>
</body>

</html>
