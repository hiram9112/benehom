<?php
require_once APP_PATH . '/views/partials/head.php';

$bhDashboardHeadExtra = <<<'HTML'
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" integrity="sha384-RkASv+6KfBMW9eknReJIJ6b3UnjKOKC5bOUaNgIY778NFbQ8MtWq9Lr/khUgqtTt" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/plugins/monthSelect/style.css" integrity="sha384-iENKmnGeeAGTWfH/ajxq1dMSwLjASdk1v+taA112fikKow0tdV9cbUJcAiBEfHhG" crossorigin="anonymous">
HTML;

bh_document_begin([
    'title' => 'Dashboard financiero',
    'description' => 'Panel privado de BeneHom para revisar ingresos, gastos esenciales, gastos flexibles, ahorro posible y ahorro real del hogar.',
    'canonical' => bh_url('index.php?r=dashboard/index'),
    'robots' => 'noindex',
    'head_extra' => $bhDashboardHeadExtra,
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
$categoriasGasto = gastoCategorias();
$labelsCategoriasGasto = gastoCategoriaLabels();
$categoriasIngreso = ingresoCategorias();
$labelsCategoriasIngreso = ingresoCategoriaLabels();
$categoriasMovimiento = array_merge(['ingreso' => $categoriasIngreso], $categoriasGasto);
$labelsCategorias = array_merge($labelsCategoriasGasto, $labelsCategoriasIngreso);
$mesSeleccionado = $mesSeleccionado ?? ($_GET['mes'] ?? date('Y-m'));
?>



<!--Contenedor Principal-->
<div class="bh-app-shell">
    <?php bh_sidebar(); ?>

    <!-- Contenedor principal -->
    <main id="contenido" class="bh-main">
        <div class="bh-dashboard-layout">
            <header class="bh-card bh-dashboard-hero" aria-labelledby="historiaMesTitulo">
                <h1 class="visually-hidden">Dashboard financiero</h1>

                <div class="bh-dashboard-hero-top">
                    <h2 id="historiaMesTitulo">La historia del mes
                        <button type="button" class="bh-btn bh-btn-icon bh-btn-ghost info-btn" data-bs-toggle="modal" data-bs-target="#infoHistoriaMes" aria-label="Información sobre el gráfico de la historia del mes">
                            <i class="ti ti-info-circle" aria-hidden="true"></i>
                        </button>
                    </h2>

                    <!--Selector de mes-->
                    <div id="selector_mes">
                        <form method="GET" action="index.php" class="bh-form bh-month-form bh-month-pill" aria-label="Seleccionar mes del dashboard">
                            <input type="hidden" name="r" value="dashboard/index">

                            <button type="button" class="bh-month-nav" data-month-shift="-1" aria-label="Ver mes anterior">
                                <i class="ti ti-chevron-left" aria-hidden="true"></i>
                            </button>

                            <input
                                type="text"
                                id="mes"
                                name="mes"
                                class="bh-input bh-month-input"
                                value="<?= htmlspecialchars($mesSeleccionado, ENT_QUOTES, 'UTF-8') ?>">

                            <button type="button" class="bh-month-nav" data-month-shift="1" aria-label="Ver mes siguiente">
                                <i class="ti ti-chevron-right" aria-hidden="true"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="bh-dashboard-hero-body">
                    <figure class="bh-dashboard-waterfall bh-hero-chart">
                        <canvas id="graficoHistoriaMes" role="img" aria-label="Gráfico de cascada con ingresos, gastos esenciales, ahorro posible, gastos flexibles y ahorro real" aria-describedby="graficoHistoriaMesResumen"></canvas>
                        <div class="bh-hero-chart-empty" id="graficoHistoriaMesEmpty" hidden>
                            <p id="graficoHistoriaMesEmptyTitulo">Aún no hay movimientos en <span id="graficoHistoriaMesEmptyMes">este mes</span></p>
                            <button type="button" class="bh-btn bh-btn-primary bh-hero-chart-empty-cta" data-hero-empty-cta>+ Añadir primer ingreso</button>
                        </div>
                        <figcaption id="graficoHistoriaMesResumen" class="visually-hidden" aria-live="polite">La cascada se actualizará con los datos del mes seleccionado.</figcaption>
                    </figure>

                    <div class="bh-hero-info">
                        <div class="bh-dashboard-balance bh-hero-balance" id="resumen_ahorro_card" aria-live="polite">
                            <span class="bh-hero-balance-label">Balance del mes</span>
                            <strong id="resumen_ahorro_real" class="bh-amount">0,00 €</strong>
                            <span class="bh-hero-balance-subtitle" id="resumen_ahorro_mes">Ahorro real de este mes</span>
                        </div>

                        <div class="bh-dashboard-kpis bh-hero-insights" aria-live="polite">
                            <section class="bh-hero-insight-group" aria-labelledby="heroIngresosTitulo">
                                <h3 id="heroIngresosTitulo">Sobre tus ingresos</h3>

                                        <div class="bh-dashboard-kpi bh-hero-insight-row" id="resumen_ingresos_ahorrados_card">
                                            <span class="bh-hero-insight-label"><span class="bh-hero-insight-dot is-saving" aria-hidden="true"></span><span class="bh-hero-insight-copy"><span class="bh-hero-insight-label-text">Porcentaje de ahorro real</span><span class="bh-hero-insight-detail" id="resumen_ingresos_ahorrados_detalle"></span></span></span>
                                            <strong id="resumen_ingresos_ahorrados" class="bh-amount">0 %</strong>
                                        </div>

                                        <div class="bh-dashboard-kpi bh-hero-insight-row is-flexible" id="resumen_gastos_flexibles_peso_card">
                                            <span class="bh-hero-insight-label"><span class="bh-hero-insight-dot is-flexible" aria-hidden="true"></span><span class="bh-hero-insight-copy"><span class="bh-hero-insight-label-text">Porcentaje de gasto flexible</span><span class="bh-hero-insight-detail" id="resumen_gastos_flexibles_peso_detalle"></span></span></span>
                                            <strong id="resumen_gastos_flexibles_peso" class="bh-amount">0 %</strong>
                                        </div>
                            </section>

                            <section class="bh-hero-insight-group" aria-labelledby="heroComparativaTitulo">
                                <h3 id="heroComparativaTitulo">Comparado con <span id="resumen_variacion_esenciales_mes">mes anterior</span></h3>
                                <span id="resumen_variacion_flexibles_mes" class="visually-hidden">mes anterior</span>

                                    <div class="bh-dashboard-kpi bh-hero-insight-row bh-hero-insight-delta is-essential" id="resumen_variacion_esenciales_card" hidden>
                                        <span class="bh-hero-insight-label"><span class="bh-hero-insight-dot is-essential" aria-hidden="true"></span><span class="bh-hero-insight-copy"><span class="bh-hero-insight-label-text">Variación de gastos esenciales</span><span class="bh-hero-insight-detail" id="resumen_variacion_esenciales_detalle"></span></span></span>
                                        <strong id="resumen_variacion_esenciales" class="bh-amount">0 %</strong>
                                    </div>

                                <div class="bh-dashboard-kpi bh-hero-insight-row bh-hero-insight-delta is-flexible" id="resumen_variacion_flexibles_card" hidden>
                                    <span class="bh-hero-insight-label"><span class="bh-hero-insight-dot is-flexible" aria-hidden="true"></span><span class="bh-hero-insight-copy"><span class="bh-hero-insight-label-text">Variación de gastos flexibles</span><span class="bh-hero-insight-detail" id="resumen_variacion_flexibles_detalle"></span></span></span>
                                    <strong id="resumen_variacion_flexibles" class="bh-amount">0 %</strong>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </header>

            <div class="bh-content-grid bh-dashboard-grid">



                <!--Panel central-->
                <section class="bh-dashboard-finance-stack">
                    <div class="bh-card bh-card-finance bh-month-movements-card" aria-labelledby="movimientosMesTitulo">
                        <div class="bh-card-header bh-month-movements-header">
                            <h3 id="movimientosMesTitulo" class="titulo">Movimientos del mes</h3>
                        </div>

                        <div class="bh-card-body">
                            <form id="formMovimientoMes" class="bh-form bh-movement-inline-form" data-movement-form>
                                <?= csrf_field() ?>
                                <input type="hidden" name="mes_seleccionado" value="<?= htmlspecialchars($mesSeleccionado, ENT_QUOTES, 'UTF-8') ?>">

                                <div class="bh-movement-fields-row">
                                    <div class="bh-field">
                                        <label class="bh-label" for="movimiento_tipo">Tipo de movimiento</label>
                                        <div class="bh-select-shell">
                                            <select id="movimiento_tipo" name="tipo_movimiento" class="bh-select" data-movement-type required>
                                                <option value="" selected disabled>Selecciona un tipo</option>
                                                <option value="ingreso">Ingreso</option>
                                                <option value="esencial">Gasto esencial</option>
                                                <option value="flexible">Gasto flexible</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="bh-category-picker" data-movement-category-picker>
                                        <div class="bh-field">
                                            <label class="bh-label" for="movimiento_area">Área</label>
                                            <div class="bh-select-shell">
                                                <select id="movimiento_area" class="bh-select" data-area-select required disabled>
                                                    <option value="" selected disabled>Selecciona un área</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="bh-field">
                                            <label class="bh-label" for="movimiento_concepto">Concepto</label>
                                            <div class="bh-select-shell">
                                                <select id="movimiento_concepto" class="bh-select" data-concept-select required disabled>
                                                    <option value="" selected disabled>Selecciona un concepto</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bh-field">
                                        <label class="bh-label" for="movimiento_cantidad">Cantidad (€)</label>
                                        <input type="number" name="cantidad_movimiento" id="movimiento_cantidad" class="bh-input" step="0.01" inputmode="decimal" placeholder="0,00" required>
                                    </div>

                                    <button type="submit" id="movimiento_submit" class="bh-btn bh-btn-primary" data-movement-submit>+ Añadir</button>
                                </div>
                            </form>

                            <div class="bh-movement-groups" aria-label="Movimientos registrados en el mes">
                                <section class="bh-movement-group is-income" aria-labelledby="movimientosIngresosTitulo">
                                    <div class="bh-movement-group-head">
                                        <h4 id="movimientosIngresosTitulo">Ingresos
                                            <button type="button" class="bh-btn bh-btn-icon bh-btn-ghost info-btn" data-bs-toggle="modal" data-bs-target="#infoIngresos" aria-label="Información sobre ingresos">
                                                <i class="ti ti-info-circle" aria-hidden="true"></i>
                                            </button>
                                        </h4>
                                    </div>

                                    <div id="lista_ingresos" class="lista-ingresos">
                                        <?php if (!empty($ingresos)): ?>
                                            <ul>
                                                <?php foreach ($ingresos as $ingreso): ?>
                                                    <li id="ingreso-<?= $ingreso['id'] ?>" class="bh-movement-item" data-id="<?= $ingreso['id'] ?>" data-cantidad="<?= htmlspecialchars((string) $ingreso['cantidad'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <div class="bh-movement-main">
                                                            <span class="bh-movement-label categoria_ingreso_individual"><?= htmlspecialchars(formatearCategoria($ingreso['categoria'])) ?></span>
                                                        </div>
                                                        <div class="bh-movement-side">
                                                            <span class="bh-movement-sign is-income" aria-hidden="true">+</span><span class="bh-movement-amount cantidad_ingreso bh-amount" data-id="<?= $ingreso['id'] ?>"><?= bh_format_amount($ingreso['cantidad']) ?></span><span class="bh-money-symbol">€</span>
                                                            <button class="bh-btn bh-btn-icon bh-btn-ghost eliminar_ingreso" data-id="<?= $ingreso['id'] ?>" aria-label="Eliminar ingreso <?= htmlspecialchars(formatearCategoria($ingreso['categoria']), ENT_QUOTES, 'UTF-8') ?>">
                                                                <i class="ti ti-trash" aria-hidden="true"></i>
                                                            </button>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <div class="bh-empty-state bh-dashboard-list-empty-state"><h3 class="bh-empty-state-title">Sin ingresos aún</h3></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="bh-movement-group-action">
                                        <button type="button" class="bh-context-link" data-movimiento-atajo="ingreso">+ Añadir ingreso</button>
                                    </div>
                                    <p id="total_ingresos_texto" class="bh-checkpoint-row is-income"></p>
                                </section>

                                <section class="bh-movement-group is-essential" aria-labelledby="movimientosEsencialesTitulo">
                                    <div class="bh-movement-group-head">
                                        <h4 id="movimientosEsencialesTitulo">Gastos esenciales
                                            <button type="button" class="bh-btn bh-btn-icon bh-btn-ghost info-btn" data-bs-toggle="modal" data-bs-target="#infoGastosEsenciales" aria-label="Información sobre gastos esenciales">
                                                <i class="ti ti-info-circle" aria-hidden="true"></i>
                                            </button>
                                        </h4>
                                    </div>

                                    <div id="lista_gastos_esenciales" class="lista-gastos">
                                        <?php if (!empty($gastosEsenciales)): ?>
                                            <ul>
                                                <?php foreach ($gastosEsenciales as $gastoEsencial): ?>
                                                    <li id="gasto-<?= $gastoEsencial['id'] ?>" class="bh-movement-item" data-id="<?= $gastoEsencial['id'] ?>" data-cantidad="<?= htmlspecialchars((string) $gastoEsencial['cantidad'], ENT_QUOTES, 'UTF-8') ?>" data-tipo="<?= $gastoEsencial['tipo'] ?>">
                                                        <div class="bh-movement-main">
                                                            <span class="bh-movement-label categoria_gasto_esencial"><?= htmlspecialchars(formatearCategoria($gastoEsencial['categoria'])) ?></span>
                                                        </div>
                                                        <div class="bh-movement-side">
                                                            <span class="bh-movement-sign is-expense" aria-hidden="true">−</span><span class="bh-movement-amount cantidad_gasto_esencial cantidad_gasto bh-amount" data-id="<?= $gastoEsencial['id'] ?>"><?= bh_format_amount($gastoEsencial['cantidad']) ?></span><span class="bh-money-symbol">€</span>
                                                            <button class="bh-btn bh-btn-icon bh-btn-ghost eliminar_gasto" data-id="<?= $gastoEsencial['id'] ?>" aria-label="Eliminar gasto esencial <?= htmlspecialchars(formatearCategoria($gastoEsencial['categoria']), ENT_QUOTES, 'UTF-8') ?>">
                                                                <i class="ti ti-trash" aria-hidden="true"></i>
                                                            </button>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <div class="bh-empty-state bh-dashboard-list-empty-state"><h3 class="bh-empty-state-title">Sin gastos aún</h3></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="bh-movement-group-action">
                                        <button type="button" class="bh-context-link" data-movimiento-atajo="esencial">+ Añadir gasto esencial</button>
                                    </div>
                                    <div class="bh-checkpoint-stack">
                                        <p id="total_gastos_esenciales_texto" class="bh-checkpoint-row is-essential"></p>
                                        <p id="ahorro_posible_texto" class="bh-checkpoint-row is-saving"></p>
                                    </div>
                                </section>

                                <section class="bh-movement-group is-flexible" aria-labelledby="movimientosFlexiblesTitulo">
                                    <div class="bh-movement-group-head">
                                        <h4 id="movimientosFlexiblesTitulo">Gastos flexibles
                                            <button type="button" class="bh-btn bh-btn-icon bh-btn-ghost info-btn" data-bs-toggle="modal" data-bs-target="#infoGastosFlexibles" aria-label="Información sobre gastos flexibles">
                                                <i class="ti ti-info-circle" aria-hidden="true"></i>
                                            </button>
                                        </h4>
                                    </div>

                                    <div id="lista_gastos_flexibles" class="lista-gastos">
                                        <?php if (!empty($gastosFlexibles)): ?>
                                            <ul>
                                                <?php foreach ($gastosFlexibles as $gastoFlexible): ?>
                                                    <li id="gasto-<?= $gastoFlexible['id'] ?>" class="bh-movement-item" data-id="<?= $gastoFlexible['id'] ?>" data-cantidad="<?= htmlspecialchars((string) $gastoFlexible['cantidad'], ENT_QUOTES, 'UTF-8') ?>" data-tipo="<?= $gastoFlexible['tipo'] ?>">
                                                        <div class="bh-movement-main">
                                                            <span class="bh-movement-label categoria_gasto_flexible"><?= htmlspecialchars(formatearCategoria($gastoFlexible['categoria'])) ?></span>
                                                        </div>
                                                        <div class="bh-movement-side">
                                                            <span class="bh-movement-sign is-expense" aria-hidden="true">−</span><span class="bh-movement-amount cantidad_gasto_flexible cantidad_gasto bh-amount" data-id="<?= $gastoFlexible['id'] ?>"><?= bh_format_amount($gastoFlexible['cantidad']) ?></span><span class="bh-money-symbol">€</span>
                                                            <button class="bh-btn bh-btn-icon bh-btn-ghost eliminar_gasto" data-id="<?= $gastoFlexible['id'] ?>" aria-label="Eliminar gasto flexible <?= htmlspecialchars(formatearCategoria($gastoFlexible['categoria']), ENT_QUOTES, 'UTF-8') ?>">
                                                                <i class="ti ti-trash" aria-hidden="true"></i>
                                                            </button>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <div class="bh-empty-state bh-dashboard-list-empty-state"><h3 class="bh-empty-state-title">Sin gastos aún</h3></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="bh-movement-group-action">
                                        <button type="button" class="bh-context-link" data-movimiento-atajo="flexible">+ Añadir gasto flexible</button>
                                    </div>
                                    <div class="bh-checkpoint-stack">
                                        <p id="total_gastos_flexibles_texto" class="bh-checkpoint-row is-flexible"></p>
                                        <p id="ahorro_real_texto" class="bh-checkpoint-row is-saving"></p>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </section>

                <!--Panel lateral derecho-->
                <aside class="bh-dashboard-aside">
                    <!--Gráfico top de gastos flexibles-->
                    <div class="bh-card bh-card-chart bh-card-habits-scale">
                        <div class="bh-card-chart-header">
                            <h2>
                                Top de gastos flexibles
                                <button type="button"
                                    class="bh-btn bh-btn-icon bh-btn-ghost info-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#infoEscalaHabitosMedia"
                                    data-escala-habitos-info
                                    aria-label="Información sobre el top de gastos flexibles">
                                    <i class="ti ti-info-circle" aria-hidden="true"></i>
                                </button>
                            </h2>

                            <div class="bh-segmented" role="group" aria-label="Seleccionar vista del gráfico">
                                <button type="button"
                                    id="btnEscalaHabitosMes"
                                    class="bh-segmented-button is-active"
                                    data-escala-habitos="mes"
                                    aria-pressed="true">
                                    Media mensual
                                </button>
                                <button type="button"
                                    id="btnEscalaHabitosAnio"
                                    class="bh-segmented-button"
                                    data-escala-habitos="anio"
                                    aria-pressed="false">
                                    Proyección anual
                                </button>
                            </div>
                        </div>

                        <div class="contenedor-grafico bh-scale-chart-container">
                            <canvas id="graficoEscalaHabitos" role="img" tabindex="0" aria-label="Gráfico de barras del top de gastos flexibles. Usa las flechas para recorrer las barras y Enter para abrir la instantánea de inversión." aria-describedby="graficoEscalaHabitosResumen"></canvas>
                            <span class="bh-chart-empty-chip" id="chipEscalaHabitosVacio" hidden>Se dibujará con tus primeros gastos flexibles</span>
                        </div>

                        <p id="graficoEscalaHabitosResumen" class="visually-hidden" aria-live="polite">El gráfico se actualizará con las principales categorías de gasto flexible.</p>
                        <p class="bh-chart-hint"><i class="ti ti-hand-finger" aria-hidden="true"></i> Toca una barra y descubre qué pasaría si invirtieras ese dinero en lugar de gastarlo</p>
                    </div>

                    <!--Gráfico Ahorros 6m-->
                    <div class="bh-card bh-card-chart">
                        <h2>
                            Evolución del Ahorro
                            <button type="button"
                                class="bh-btn bh-btn-icon bh-btn-ghost info-btn"
                                data-bs-toggle="modal"
                                data-bs-target="#infoEvolucionAhorro"
                                aria-label="Información sobre evolución del ahorro">
                                <i class="ti ti-info-circle" aria-hidden="true"></i>
                            </button>
                        </h2>
                        <div class="contenedor-grafico">
                            <canvas id="graficoAhorros6m" role="img" aria-label="Gráfico de barras de ahorro posible y ahorro real de los últimos meses" aria-describedby="graficoAhorros6mResumen"></canvas>
                            <span class="bh-chart-empty-chip" id="chipAhorros6mVacio" hidden>Se dibujará con tu primer mes de movimientos</span>
                        </div>
                        <p id="graficoAhorros6mResumen" class="visually-hidden" aria-live="polite">El gráfico se actualizará con la evolución de ahorro posible y ahorro real.</p>
                    </div>


                    <!--Gráfico evolución de gastos-->
                    <div class="bh-card bh-card-chart">
                        <div class="bh-card-chart-header">
                            <h2>
                                Evolución de gastos
                                <button type="button"
                                    class="bh-btn bh-btn-icon bh-btn-ghost info-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#infoEvolucionFlexibles"
                                    data-evolucion-gastos-info
                                    aria-label="Información sobre la evolución de gastos">
                                    <i class="ti ti-info-circle" aria-hidden="true"></i>
                                </button>
                            </h2>

                            <div class="bh-segmented" role="group" aria-label="Seleccionar tipo de gasto">
                                <button type="button"
                                    id="btnEvolucionFlexibles"
                                    class="bh-segmented-button is-active"
                                    data-evolucion-gastos-tab="flexible"
                                    aria-controls="panelEvolucionFlexibles"
                                    aria-pressed="true">
                                    Flexibles
                                </button>
                                <button type="button"
                                    id="btnEvolucionEsenciales"
                                    class="bh-segmented-button"
                                    data-evolucion-gastos-tab="esencial"
                                    aria-controls="panelEvolucionEsenciales"
                                    aria-pressed="false">
                                    Esenciales
                                </button>
                            </div>
                        </div>

                        <div id="panelEvolucionFlexibles" class="bh-chart-panel" role="region" aria-labelledby="btnEvolucionFlexibles">
                            <div class="contenedor-grafico">
                                <canvas id="graficoGastosFlexibles6m" role="img" aria-label="Gráfico de línea de gastos flexibles de los últimos meses" aria-describedby="graficoGastosFlexibles6mResumen"></canvas>
                                <span class="bh-chart-empty-chip" id="chipGastosFlexVacio" hidden>Se dibujará con tus primeros gastos</span>
                            </div>
                            <p id="graficoGastosFlexibles6mResumen" class="visually-hidden" aria-live="polite">El gráfico se actualizará con la evolución de gastos flexibles.</p>
                        </div>

                        <div id="panelEvolucionEsenciales" class="bh-chart-panel" role="region" aria-labelledby="btnEvolucionEsenciales" hidden>
                            <div class="contenedor-grafico">
                                <canvas id="graficoGastosEsenciales6m" role="img" aria-label="Gráfico de línea de gastos esenciales de los últimos meses" aria-describedby="graficoGastosEsenciales6mResumen"></canvas>
                                <span class="bh-chart-empty-chip" id="chipGastosEsenVacio" hidden>Se dibujará con tus primeros gastos</span>
                            </div>
                            <p id="graficoGastosEsenciales6mResumen" class="visually-hidden" aria-live="polite">El gráfico se actualizará con la evolución de gastos esenciales.</p>
                        </div>
                    </div>

                </aside>
            </div>
        </div>
    </main>
</div>

<?php bh_mobile_menu(); ?>

<!--Modal de ingresos-->
<?php bh_info_modal('infoIngresos', '¿Qué son los ingresos?', <<<'HTML'
<p>Los ingresos representan el dinero que entra en tu hogar durante el mes.</p>
<ul>
    <li>Salarios o nóminas</li>
    <li>Ingresos extra o puntuales</li>
    <li>Ingresos por inversiones</li>
</ul>
<p>Registrar correctamente los ingresos es clave para preparar de forma correcta el presupuesto familiar</p>
HTML); ?>

<!--Modal de gastos esenciales-->
<?php bh_info_modal('infoGastosEsenciales', '¿Qué son los gastos esenciales?', <<<'HTML'
<p>Los gastos esenciales son los pagos que sostienen el funcionamiento del hogar
    y cubren necesidades básicas.</p>
<p>Suelen repetirse cada mes y te ayudan a entender cuánto dinero necesitas
    para vivir con estabilidad.</p>
<p>Separarlos del resto de gastos permite calcular tu margen real
    antes de revisar decisiones de consumo.</p>
HTML); ?>

<!--Modal de gastos flexibles-->

<?php bh_info_modal('infoGastosFlexibles', '¿Qué son los gastos flexibles?', <<<'HTML'
<p>Los gastos flexibles son pagos vinculados a hábitos, preferencias y decisiones de consumo.
    No son negativos por sí mismos, pero suelen ofrecer más margen de revisión.
</p>
<p>Identificarlos permite entender con claridad
    <strong>qué parte de nuestro presupuesto es realmente flexible</strong>.
    Muchos pueden reducirse, pausarse o reorganizarse sin afectar a los gastos esenciales del hogar.
</p>
<p>
    Revisarlos ayuda a medir el impacto real que pequeños cambios en nuestros hábitos
    pueden tener sobre el <strong>ahorro real</strong> y el
    <strong>bienestar financiero</strong>.
    En muchos casos, el efecto puede notarse de un mes a otro.
</p>
HTML); ?>

<!--Modal Gráfico de la historia del mes-->
<?php bh_info_modal('infoHistoriaMes', '¿Cómo leer el gráfico de la historia del mes?', <<<'HTML'
<p>Este gráfico muestra, de un vistazo, <strong>qué ha pasado con tu dinero este mes,</strong> funciona como una <strong>cascada</strong>, así puedes seguir el camino completo del dinero.</p>
<ul>
    <li><strong>Ingresos</strong>: es el punto de partida, todo el dinero que ha entrado en el hogar durante el mes.</li>
    <li><strong>Gastos esenciales</strong>: se restan a continuación, son los pagos necesarios para vivir (vivienda, suministros, seguros…).</li>
    <li><strong>Ahorro posible</strong>: es el dinero que <em>podrías</em> haber ahorrado si solo hubieras tenido gastos esenciales. Cuanto más alto, más margen tienes.</li>
    <li><strong>Gastos flexibles</strong>: se restan después, son los gastos ligados a tus hábitos y decisiones de consumo.</li>
    <li><strong>Ahorro real</strong>: es la barra final, lo que realmente te queda (o te falta) al terminar el mes.</li>
</ul>
<p>Si la última barra está <strong>por encima de cero</strong>, has conseguido ahorrar. Si está <strong>por debajo</strong>, has gastado más de lo que has ingresado y has tenido que recurrir a ahorros anteriores.</p>
HTML); ?>

<!--Modal Gráfico de evolución del ahorro-->
<?php bh_info_modal('infoEvolucionAhorro', '¿Cómo interpretar la evolución del ahorro?', <<<'HTML'
<p>Este gráfico es uno de los más importantes de la aplicación.
    Muestra cómo ha evolucionado tu ahorro mes a mes y te ayuda
    a entender si tu economía es sostenible en el tiempo.
</p>
<p>El <strong>ahorro posible</strong> representa cuánto dinero
    podrías haber ahorrado en un mes según tus ingresos y gastos esenciales.
</p>
<p>El <strong>ahorro real</strong> muestra lo que finalmente ocurrió
    después de incluir los gastos flexibles: lo que realmente se ahorró
    o se perdió.
</p>
<p>Cuando el <strong>ahorro real es negativo</strong>, significa que ese mes se ha <strong>gastado
        más dinero del que se ingresó</strong>, utilizando ahorros anteriores.
</p>
<p>Si esta situación se repite durante varios meses, es una señal de alerta:
    el nivel de gasto actual <strong>no es sostenible</strong> , a largo plazo los ahorros
    pueden agotarse.
</p>
<p>Puedes pasar el cursor sobre cada barra para ver el valor exacto
    correspondiente a cada mes.
</p>
HTML); ?>

<!--Modal Gráfico de evolución de gastos esenciales-->
<?php bh_info_modal('infoEvolucionEsenciales', '¿Qué muestra este gráfico?', <<<'HTML'
<p>
    Este gráfico muestra la <strong>evolución de tus gastos esenciales</strong>
    durante los últimos 6 meses.
</p>
<p>
    Los gastos esenciales suelen ser <strong>estables en el tiempo</strong>,
    ya que corresponden a pagos necesarios como vivienda, suministros o seguros.
</p>
<p>
    Si las <strong>cantidades se mantienen constantes</strong>,
    significa que los gastos esenciales del hogar están bajo control.
</p>
<p>
    Si los valores <strong>empiezan a subir o a bajar de forma notable</strong>,
    es una señal de que <strong>algo está cambiando</strong> y conviene revisarlo.
</p>
<p>
    En algunos casos, este gráfico ayuda a detectar
    <strong>gastos que quizá no pertenezcan a los gastos esenciales del hogar</strong>
    o que estén <strong>mal clasificados</strong>.
</p>
<p>
    Identificar estas variaciones a tiempo te permite
    <strong>corregir errores</strong> y tener una visión más clara
    de cuánto te cuesta mantener tu hogar cada mes.
</p>
<p>
    Al pasar el cursor sobre el gráfico puedes ver
    el importe exacto de cada mes.
</p>
HTML); ?>



<!--Modal Gráfico de evolución de gastos flexibles-->

<?php bh_info_modal('infoEvolucionFlexibles', '¿Qué muestra este gráfico?', <<<'HTML'
<p>
    Este gráfico muestra la <strong>evolución de tus gastos flexibles</strong>
    durante los últimos 6 meses. Representa de forma visual <strong>cómo se están
        comportando tus hábitos de consumo</strong>
    con el paso del tiempo.
</p>
<p>
    Si los valores <strong>van aumentando mes a mes</strong>, significa que cada vez
    estás gastando más, esto provoca que <strong>cada vez puedas ahorrar menos</strong> o incluso que
    tengas que <strong>usar tus ahorros para llegar a fin de mes</strong>.
</p>
<p>
    Si esta situación se mantiene, es una señal clara de que
    <strong>necesitas hacer algunos cambios</strong>, ya que a largo plazo
    <strong>no es sostenible</strong>.
</p>
<p>
    Si los gastos <strong>van disminuyendo con el paso del tiempo</strong>, vas por buen camino:
    estás recuperando margen y <strong>mejorando tu ahorro real</strong>.
</p>
<p>
    Si los gastos se <strong>mantienen estables</strong>, tu situación no cambia:
    no mejoras ni empeoras. Ante cualquier imprevisto, podrías verte obligado a usar ahorros
    que quizá <strong>no estás consiguiendo generar</strong>.
</p>
<p>
    Puedes pasar el cursor sobre cada punto para ver el
    importe exacto de cada mes.
</p>
HTML); ?>

<!--Modal Gráfico top 5 de gastos flexibles: vista media mensual-->
<?php bh_info_modal('infoEscalaHabitosMedia', '¿Qué muestra la media mensual?', <<<'HTML'
<p>Estás viendo lo que te cuesta cada uno de estos hábitos en un <strong>mes típico</strong>.</p>
<p><strong>No es el gasto de este mes</strong>: es el promedio de tus últimos meses con gastos (hasta 6). Si solo llevas un mes registrando, la cifra coincide con ese mes y se irá afinando a medida que añadas más datos.</p>
<p>Es tu punto de partida: conocer el tamaño real de cada hábito antes de decidir si quieres cambiarlo.</p>
HTML); ?>

<!--Modal Gráfico top 5 de gastos flexibles: vista proyección anual-->
<?php bh_info_modal('infoEscalaHabitosProyeccion', '¿Qué muestra la proyección anual?', <<<'HTML'
<p>Estás viendo una <strong>estimación de futuro</strong>: lo que te costará cada hábito durante los próximos 12 meses <strong>si mantienes tu ritmo de gasto actual</strong>.</p>
<p><strong>No es lo que has gastado este año</strong>: se calcula multiplicando tu media mensual por 12.</p>
<p>Un gasto que parece pequeño mes a mes puede convertirse en una cifra importante al cabo de un año. Verlo a tiempo te permite decidir antes de que ocurra.</p>
HTML); ?>

<!--Modal de instantánea de inversión efímera-->
<?php
bh_modal([
    'id'         => 'modalInstantaneaInversion',
    'title'      => 'Si invirtieras tu gasto de esta categoría',
    'titleId'    => 'modalInstantaneaTitulo',
    'eyebrow'    => 'Simulación educativa',
    'subtitleId' => 'modalInstantaneaSubtitulo',
    'size'       => 'lg',
    'variant'    => 'focus',
    'footer'     => null,
    'body'       => <<<'HTML'
<div class="bh-investment-snapshot-controls">
    <div>
        <span class="bh-control-label">Aportación</span>
        <div class="bh-segmented" role="group" aria-label="Seleccionar aportación simulada">
            <button type="button" class="bh-segmented-button is-active" data-instantanea-aportacion="todo" aria-pressed="true">Todo</button>
            <button type="button" class="bh-segmented-button" data-instantanea-aportacion="mitad" aria-pressed="false">La mitad</button>
        </div>
    </div>

    <div>
        <span class="bh-control-label">Rentabilidad orientativa</span>
        <div class="bh-segmented" role="group" aria-label="Seleccionar rentabilidad orientativa">
            <button type="button" class="bh-segmented-button is-active" data-instantanea-rentabilidad="3" aria-pressed="true">3%</button>
            <button type="button" class="bh-segmented-button" data-instantanea-rentabilidad="6" aria-pressed="false">6%</button>
            <button type="button" class="bh-segmented-button" data-instantanea-rentabilidad="9" aria-pressed="false">9%</button>
        </div>
    </div>
</div>

<div class="bh-investment-snapshot-results" aria-live="polite">
    <div class="bh-investment-snapshot-head">
        <span>Plazo</span>
        <span>Total acumulado</span>
        <span>Beneficio generado</span>
    </div>
    <div class="bh-investment-snapshot-row">
        <span class="bh-investment-snapshot-years">5 años</span>
        <strong id="instantaneaValor5">0 €</strong>
        <span><span id="instantaneaGenerado5" class="bh-investment-generated">0 €</span></span>
    </div>
    <div class="bh-investment-snapshot-row">
        <span class="bh-investment-snapshot-years">10 años</span>
        <strong id="instantaneaValor10">0 €</strong>
        <span><span id="instantaneaGenerado10" class="bh-investment-generated">0 €</span></span>
    </div>
    <div class="bh-investment-snapshot-row">
        <span class="bh-investment-snapshot-years">15 años</span>
        <strong id="instantaneaValor15">0 €</strong>
        <span><span id="instantaneaGenerado15" class="bh-investment-generated">0 €</span></span>
    </div>
</div>

<p class="bh-investment-disclaimer">
    Estimación orientativa con fines educativos, no es una recomendación de inversión, no representa ningún producto concreto y nada se guarda ni modifica los datos reales.
</p>
HTML,
]);
?>


<!-- Modal de confirmación genérico -->
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

// Modal informativo genérico (éxito / error)
bh_modal([
    'id'      => 'modalInfo',
    'title'   => 'Información',
    'titleId' => 'modalInfoTitulo',
    'bodyId'  => 'modalInfoTexto',
    'body'    => '',
    'footer'  => null,
]);
?>

<?php ob_start(); ?>
<!--Añadimos chart.js-->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.min.js" integrity="sha384-XcdcwHqIPULERb2yDEM4R0XaQKU3YnDsrTmjACBZyfdVVqjh6xQ4/DCMd7XLcA6Y" crossorigin="anonymous"></script>
<script src="<?= bh_asset('js/money-format.js') ?>"></script>
<script src="<?= bh_asset('js/chart-theme.js') ?>"></script>

<!--Cargamos token crsf-->
<script<?= bh_nonce_attr() ?>>
    window.CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>";
    window.BH_GASTO_CATEGORIAS = <?= json_encode($categoriasGasto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    window.BH_MOVIMIENTO_CATEGORIAS = <?= json_encode($categoriasMovimiento, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    window.BH_GASTO_CATEGORIA_LABELS = <?= json_encode($labelsCategorias, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    </script>


    <!-- Flatpickr: librería base -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js" integrity="sha384-5JqMv4L/Xa0hfvtF06qboNdhvuYXUku9ZrhZh3bSk8VXF0A/RuSLHpLsSV9Zqhl6" crossorigin="anonymous"></script>

    <!-- Flatpickr plugin: selección de mes/año -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/plugins/monthSelect/index.js" integrity="sha384-6p33UqcS/7ZxiJzlAi3gfOsrVSlBlFNr/6gfN12AC0ETbTmPgMGSfHuN+H0QcWoO" crossorigin="anonymous"></script>

    <!-- Locale ------ español-->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/es.js" integrity="sha384-j/aEP2b+3OKmGqank2qCSosSrlrF9jpIpdgApXq2ryJYBpLSbEi63/PDdL+rKmcQ" crossorigin="anonymous"></script>
    <!--Enlazamos con nuestros arvhicos js-->
    <script src="<?= bh_asset('js/validaciones.js') ?>"></script>
    <script src="<?= bh_asset('js/dashboard-graficos.js') ?>"></script>
    <script src="<?= bh_asset('js/dashboard-edicion.js') ?>"></script>
    <script src="<?= bh_asset('js/dashboard-categorias.js') ?>"></script>
    <script src="<?= bh_asset('js/dashboard-formularios.js') ?>"></script>
    <script src="<?= bh_asset('js/dashboard-utils.js') ?>"></script>
    <script src="<?= bh_asset('js/dashboard-dom.js') ?>"></script>
    <script src="<?= bh_asset('js/dashboard.js') ?>"></script>
    <?php
    $bhDashboardBodyEndExtra = ob_get_clean();

    bh_document_end([
        'include_bootstrap_js' => true,
        'include_flash_js' => true,
        'body_end_extra' => $bhDashboardBodyEndExtra,
    ]);
