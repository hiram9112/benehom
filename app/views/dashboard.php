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
$labelsCategorias = array_merge($labelsCategoriasGasto, $categoriasIngreso);
$mesSeleccionado = $mesSeleccionado ?? ($_GET['mes'] ?? date('Y-m'));
?>



<!--Contenedor Principal-->
<div class="bh-app-shell">
    <?php bh_sidebar(); ?>

    <!-- Contenedor principal -->
    <main id="contenido" class="bh-main">
        <div class="bh-dashboard-layout">
            <header class="bh-page-header bh-dashboard-summary">
                <div class="bh-dashboard-period">
                    <h1>Resumen mensual</h1>

                    <!--Selector de mes-->
                    <div id="selector_mes">
                        <form method="GET" action="index.php" class="bh-form bh-month-form">
                            <input type="hidden" name="r" value="dashboard/index">

                            <input
                                type="text"
                                id="mes"
                                name="mes"
                                class="bh-input bh-month-input"
                                value="<?= htmlspecialchars($mesSeleccionado, ENT_QUOTES, 'UTF-8') ?>">
                        </form>
                    </div>
                </div>

                <div class="bh-summary-metrics" aria-live="polite">
                    <div class="bh-summary-metric bh-summary-balance" id="resumen_ahorro_card">
                        <div class="bh-summary-metric-heading">
                            <span>Balance del mes</span>
                        </div>
                        <strong id="resumen_ahorro_real">0€</strong>
                    </div>

                    <button type="button" class="bh-btn bh-btn-secondary bh-summary-mobile-trigger d-md-none" data-bs-toggle="offcanvas" data-bs-target="#resumenMensualPanel" aria-controls="resumenMensualPanel">
                        <span>Ver resumen mensual</span>
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </button>

                    <div class="bh-summary-details" data-summary-details>
                        <div class="bh-summary-metric bh-summary-flip-card" role="button" tabindex="0" aria-expanded="false" aria-label="Ver explicación sobre ingresos ahorrados" data-summary-flip>
                            <div class="bh-summary-card-inner">
                                <div class="bh-summary-card-face bh-summary-card-front">
                                    <div class="bh-summary-metric-heading">
                                        <span>Ingresos ahorrados</span>
                                    </div>
                                    <strong id="resumen_ingresos_ahorrados">0%</strong>
                                    <small>Clic para ver detalles</small>
                                </div>
                                <div class="bh-summary-card-face bh-summary-card-back">
                                    <p>Indica qué parte de lo que entra en casa termina quedándose como ahorro real. Te ayuda a ver si tus gastos dejan margen al final del mes.</p>
                                </div>
                            </div>
                        </div>

                        <div class="bh-summary-metric bh-summary-flip-card" role="button" tabindex="0" aria-expanded="false" aria-label="Ver explicación sobre gastos flexibles" data-summary-flip>
                            <div class="bh-summary-card-inner">
                                <div class="bh-summary-card-face bh-summary-card-front">
                                    <div class="bh-summary-metric-heading">
                                        <span>Gastos flexibles</span>
                                    </div>
                                    <strong id="resumen_gastos_flexibles_peso">0%</strong>
                                    <small>Clic para ver detalles</small>
                                </div>
                                <div class="bh-summary-card-face bh-summary-card-back">
                                    <p>Muestra cuánto ingreso es destinado a decisiones de consumo. Cuanto más alto sea, más oportunidades tienes para ajustar sin tocar gastos básicos.</p>
                                </div>
                            </div>
                        </div>

                        <div class="bh-summary-metric bh-summary-flip-card" role="button" tabindex="0" aria-expanded="false" aria-label="Ver explicación sobre variación de gastos esenciales" data-summary-flip>
                            <div class="bh-summary-card-inner">
                                <div class="bh-summary-card-face bh-summary-card-front">
                                    <div class="bh-summary-metric-heading">
                                        <span>Variación esenciales</span>
                                    </div>
                                    <strong id="resumen_variacion_esenciales">0%</strong>
                                    <small>Clic para ver detalles</small>
                                </div>
                                <div class="bh-summary-card-face bh-summary-card-back">
                                    <p>Los gastos esenciales normalmente deberían mantenerse estables. Si suben varios meses seguidos, revisa con detenimiento facturas y clasificaciones.</p>
                                </div>
                            </div>
                        </div>

                        <div class="bh-summary-metric bh-summary-flip-card" role="button" tabindex="0" aria-expanded="false" aria-label="Ver explicación sobre variación de gastos flexibles" data-summary-flip>
                            <div class="bh-summary-card-inner">
                                <div class="bh-summary-card-face bh-summary-card-front">
                                    <div class="bh-summary-metric-heading">
                                        <span>Variación flexibles</span>
                                    </div>
                                    <strong id="resumen_variacion_flexibles">0%</strong>
                                    <small>Clic para ver detalles</small>
                                </div>
                                <div class="bh-summary-card-face bh-summary-card-back">
                                    <p>Señala si tus hábitos de consumo suben o bajan frente al mes anterior. Es una pista rápida para saber si ganas o pierdes margen de ahorro.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <span hidden data-summary-inline-anchor></span>
                </div>
            </header>

            <div class="bh-content-grid bh-dashboard-grid">



                <!--Panel central-->
                <section class="bh-dashboard-finance-stack">
                    <!-- Ingresos-->
                    <div class="bh-card bh-card-finance">
                        <div class="bh-card-header">
                            <h2 class="titulo">
                                Ingresos
                                <button type="button"
                                    class="bh-btn bh-btn-icon bh-btn-ghost info-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#infoIngresos"
                                    aria-label="Información sobre ingresos">
                                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                                </button>

                            </h2>
                        </div>
                        <div class="bh-card-body">
                            <form id="formIngresos" class="bh-form bh-guided-form">
                                <?= csrf_field() ?>

                                <div class="bh-field">
                                    <label class="bh-label" for="categoria_ingreso">Categoría</label>
                                    <div class="bh-select-shell">
                                        <select name="categoria_ingreso" id="categoria_ingreso" class="bh-select" required>
                                            <option value="" selected disabled>Selecciona un tipo de ingreso</option>
                                            <?php foreach ($categoriasIngreso as $valorCategoriaIngreso => $labelCategoriaIngreso): ?>
                                                <option value="<?= htmlspecialchars($valorCategoriaIngreso, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($labelCategoriaIngreso, ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="bh-field">
                                    <label class="bh-label" for="cantidad_ingreso">Cantidad(€)</label>
                                    <input type="number" name="cantidad_ingreso" id="cantidad_ingreso" class="bh-input" step="0.01" inputmode="decimal" required>
                                </div>

                                <!--Enviamos el valor del mes seleccionado , esto será especialmente útil cuando el usuario queira insertar valores en meses pasados-->
                                <input type="hidden" name="mes_seleccionado" value="<?= htmlspecialchars($mesSeleccionado, ENT_QUOTES, 'UTF-8') ?>">

                                <button type="submit" class="bh-btn bh-btn-primary">Añadir ingreso</button>
                            </form>

                            <!--Contenedor para manejar de manera dinámica los ingresos utilizando AJAX y PHP-->
                            <div id="lista_ingresos" class="lista-ingresos">
                                <?php if (!empty($ingresos)): ?>
                                    <ul>
                                        <?php foreach ($ingresos as $ingreso): ?>
                                            <!--Agregamos manejo de id de forma dinámica para usarlo en ajax-->
                                            <li id="ingreso-<?= $ingreso['id'] ?>" class="bh-movement-item"
                                                data-id="<?= $ingreso['id'] ?>"
                                                data-cantidad="<?= htmlspecialchars((string) $ingreso['cantidad'], ENT_QUOTES, 'UTF-8') ?>">
                                                <div class="bh-movement-main">
                                                    <span class="bh-movement-label categoria_ingreso_individual"><?= htmlspecialchars(formatearCategoria($ingreso['categoria'])) ?></span>
                                                </div>
                                                <div class="bh-movement-side">
                                                    <span class="bh-movement-amount cantidad_ingreso" data-id="<?= $ingreso['id'] ?>"><?= formatearCantidadPHP($ingreso['cantidad']) ?></span><span class="bh-money-symbol">€</span>
                                                    <button class="bh-btn bh-btn-icon bh-btn-ghost eliminar_ingreso" data-id="<?= $ingreso['id'] ?>" aria-label="Eliminar ingreso <?= htmlspecialchars(formatearCategoria($ingreso['categoria']), ENT_QUOTES, 'UTF-8') ?>">
                                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                                    </button>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="bh-empty-state bh-dashboard-list-empty-state">
                                        <div class="bh-empty-state-icon" aria-hidden="true">
                                            <i class="bi bi-wallet2" aria-hidden="true"></i>
                                        </div>
                                        <h3 class="bh-empty-state-title">Aún no has registrado ingresos</h3>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!--Usaremos este elemento para mostrar de manera dinámica el total de ingresos -->
                            <p id="total_ingresos_texto" class="mt-2 fw-bold total-texto total-ingreso"></p>
                        </div>

                    </div>



                    <!--Gastos esenciales-->
                    <div class="bh-card bh-card-finance">
                        <div class="bh-card-header">
                            <h2 class="titulo ">Gastos esenciales
                                <button type="button"
                                    class="bh-btn bh-btn-icon bh-btn-ghost info-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#infoGastosEsenciales"
                                    aria-label="Información sobre gastos esenciales">
                                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                                </button>
                            </h2>
                        </div>
                        <div class="bh-card-body">
                            <form id="formGastosEsenciales" class="bh-form bh-guided-form">
                                <?= csrf_field() ?>

                                <div class="bh-category-picker" data-category-picker data-category-type="esencial">
                                    <div class="bh-field">
                                        <label class="bh-label" for="area_gasto_esencial">Área del gasto</label>
                                        <div class="bh-select-shell">
                                            <select id="area_gasto_esencial" class="bh-select" data-area-select required>
                                                <option value="" selected disabled>Selecciona un área</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="bh-field">
                                        <label class="bh-label" for="categoria_gasto_esencial">Concepto</label>
                                        <div class="bh-select-shell">
                                            <select name="categoria_gasto_esencial" id="categoria_gasto_esencial" class="bh-select" data-concept-select required disabled>
                                                <option value="" selected>Elige primero un área</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="bh-field">
                                    <label class="bh-label" for="cantidad_gasto_esencial">Cantidad(€)</label>
                                    <input type="number" name="cantidad_gasto_esencial" id="cantidad_gasto_esencial" class="bh-input"
                                        step="0.01" inputmode="decimal" required>
                                </div>

                                <!--Enviamos el valor del mes seleccionado , esto será especialmente útil cuando el usuario queira insertar valores en meses pasados-->
                                <input type="hidden" name="mes_seleccionado" value="<?= htmlspecialchars($mesSeleccionado, ENT_QUOTES, 'UTF-8') ?>">

                                <button type="submit" class="bh-btn bh-btn-primary">Añadir gasto</button>


                            </form>


                            <!--Contenedor para manejar de manera dinámica los gastos esenciales utilizando AJAX y PHP-->
                            <div id="lista_gastos_esenciales" class="lista-gastos">
                                <?php if (!empty($gastosEsenciales)): ?>
                                    <ul>
                                        <?php foreach ($gastosEsenciales as $gastoEsencial): ?>
                                            <!--Agregamos manejo de id de forma dinámica para usarlo en ajax-->
                                            <li id="gasto-<?= $gastoEsencial['id'] ?>" class="bh-movement-item"
                                                data-id="<?= $gastoEsencial['id'] ?>"
                                                data-cantidad="<?= htmlspecialchars((string) $gastoEsencial['cantidad'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-tipo="<?= $gastoEsencial['tipo'] ?>">
                                                <div class="bh-movement-main">
                                                    <span class="bh-movement-label categoria_gasto_esencial"><?= htmlspecialchars(formatearCategoria($gastoEsencial['categoria'])) ?></span>
                                                </div>
                                                <div class="bh-movement-side">
                                                    <span class="bh-movement-amount cantidad_gasto_esencial cantidad_gasto" data-id="<?= $gastoEsencial['id'] ?>"><?= formatearCantidadPHP($gastoEsencial['cantidad']) ?></span><span class="bh-money-symbol">€</span>
                                                    <button class="bh-btn bh-btn-icon bh-btn-ghost eliminar_gasto" data-id="<?= $gastoEsencial['id'] ?>" aria-label="Eliminar gasto esencial <?= htmlspecialchars(formatearCategoria($gastoEsencial['categoria']), ENT_QUOTES, 'UTF-8') ?>">
                                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                                    </button>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="bh-empty-state bh-dashboard-list-empty-state">
                                        <div class="bh-empty-state-icon" aria-hidden="true">
                                            <i class="bi bi-house-heart" aria-hidden="true"></i>
                                        </div>
                                        <h3 class="bh-empty-state-title">Aún no has registrado gastos esenciales</h3>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="bh-totals-inline-row">
                                <!--Usaremos este elemento para mostrar de manera dinámica el total de gastos esenciales -->
                                <p id="total_gastos_esenciales_texto" class="mt-2 fw-bold total-texto total-gasto"></p>

                                <!--Usaremos este elemento para mostrar de manera dinámica el ahorro posible  -->
                                <div class="bh-total-info-row">
                                    <p id="ahorro_posible_texto" class="mt-1 fw-bold texto-resumen total-texto total-ahorro"></p>
                                    <button type="button" class="bh-btn bh-btn-icon bh-btn-ghost info-btn" data-bs-toggle="modal" data-bs-target="#infoAhorroPosible" aria-label="Información sobre ahorro posible">
                                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>


                        </div>

                    </div>



                    <!--Gastos flexibles-->
                    <div class="bh-card bh-card-finance">
                        <div class="bh-card-header">
                            <h2 class="titulo">
                                Gastos flexibles
                                <button type="button"
                                    class="bh-btn bh-btn-icon bh-btn-ghost info-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#infoGastosFlexibles"
                                    aria-label="Información sobre gastos flexibles">
                                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                                </button>
                            </h2>
                        </div>
                        <div class="bh-card-body">
                            <form id="formGastosFlexibles" class="bh-form bh-guided-form">
                                <?= csrf_field() ?>

                                <div class="bh-category-picker" data-category-picker data-category-type="flexible">
                                    <div class="bh-field">
                                        <label class="bh-label" for="area_gasto_flexible">Área del gasto</label>
                                        <div class="bh-select-shell">
                                            <select id="area_gasto_flexible" class="bh-select" data-area-select required>
                                                <option value="" selected disabled>Selecciona un área</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="bh-field">
                                        <label class="bh-label" for="categoria_gasto_flexible">Concepto</label>
                                        <div class="bh-select-shell">
                                            <select name="categoria_gasto_flexible" id="categoria_gasto_flexible" class="bh-select" data-concept-select required disabled>
                                                <option value="" selected>Elige primero un área</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="bh-field">
                                    <label class="bh-label" for="cantidad_gasto_flexible">Cantidad(€)</label>
                                    <input type="number" name="cantidad_gasto_flexible" id="cantidad_gasto_flexible" class="bh-input"
                                        step="0.01" inputmode="decimal" required>
                                </div>

                                <!--Enviamos el valor del mes seleccionado , esto será especialmente útil cuando el usuario queira insertar valores en meses pasados-->
                                <input type="hidden" name="mes_seleccionado" value="<?= htmlspecialchars($mesSeleccionado, ENT_QUOTES, 'UTF-8') ?>">

                                <button type="submit" class="bh-btn bh-btn-primary">Añadir gasto</button>


                            </form>

                            <!--Contenedor para manejar de manera dinámica los gastos flexibles utilizando AJAX y PHP-->
                            <div id="lista_gastos_flexibles" class="lista-gastos">
                                <?php if (!empty($gastosFlexibles)): ?>
                                    <ul>
                                        <?php foreach ($gastosFlexibles as $gastoFlexible): ?>
                                            <!--Agregamos manejo de id de forma dinámica para usarlo en ajax-->
                                            <li id="gasto-<?= $gastoFlexible['id'] ?>" class="bh-movement-item"
                                                data-id="<?= $gastoFlexible['id'] ?>"
                                                data-cantidad="<?= htmlspecialchars((string) $gastoFlexible['cantidad'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-tipo="<?= $gastoFlexible['tipo'] ?>">
                                                <div class="bh-movement-main">
                                                    <span class="bh-movement-label categoria_gasto_flexible"><?= htmlspecialchars(formatearCategoria($gastoFlexible['categoria'])) ?></span>
                                                </div>
                                                <div class="bh-movement-side">
                                                    <span class="bh-movement-amount cantidad_gasto_flexible cantidad_gasto" data-id="<?= $gastoFlexible['id'] ?>"><?= formatearCantidadPHP($gastoFlexible['cantidad']) ?></span><span class="bh-money-symbol">€</span>
                                                    <button class="bh-btn bh-btn-icon bh-btn-ghost eliminar_gasto" data-id="<?= $gastoFlexible['id'] ?>" aria-label="Eliminar gasto flexible <?= htmlspecialchars(formatearCategoria($gastoFlexible['categoria']), ENT_QUOTES, 'UTF-8') ?>">
                                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                                    </button>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="bh-empty-state bh-dashboard-list-empty-state">
                                        <div class="bh-empty-state-icon" aria-hidden="true">
                                            <i class="bi bi-basket2" aria-hidden="true"></i>
                                        </div>
                                        <h3 class="bh-empty-state-title">Aún no has registrado gastos flexibles</h3>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="bh-totals-inline-row">
                                <!--Usaremos este elemento para mostrar de manera dinámica el total de gastos flexibles -->
                                <p id="total_gastos_flexibles_texto" class="mt-2 fw-bold total-texto total-gasto"></p>
                                <!--Usaremos este elemento para mostrar de manera dinámica el ahorro real -->
                                <div class="bh-total-info-row">
                                    <p id="ahorro_real_texto" class="mt-1 fw-bold texto-resumen total-texto total-ahorro"></p>
                                    <button type="button" class="bh-btn bh-btn-icon bh-btn-ghost info-btn" data-bs-toggle="modal" data-bs-target="#infoAhorroReal" aria-label="Información sobre ahorro real">
                                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>

                </section>

                <!--Panel lateral derecho-->
                <aside class="bh-dashboard-aside">

                    <!--Gráfico presupuesto mensual-->
                    <div class="bh-card bh-card-chart">
                        <h2>
                            Presupuesto mensual
                            <button type="button"
                                class="bh-btn bh-btn-icon bh-btn-ghost info-btn"
                                data-bs-toggle="modal"
                                data-bs-target="#infoPresupuestoMensual"
                                aria-label="Información sobre presupuesto mensual">
                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                            </button>
                        </h2>
                        <div class="contenedor-grafico">
                            <canvas id="graficoPresupuestoMensual" role="img" aria-label="Gráfico de barras del presupuesto mensual" aria-describedby="graficoPresupuestoMensualResumen"></canvas>
                        </div>
                        <p id="graficoPresupuestoMensualResumen" class="visually-hidden" aria-live="polite">El gráfico se actualizará con ingresos, gastos totales y ahorro real.</p>
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
                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                            </button>
                        </h2>
                        <div class="contenedor-grafico">
                            <canvas id="graficoAhorros6m" role="img" aria-label="Gráfico de barras de ahorro posible y ahorro real de los últimos meses" aria-describedby="graficoAhorros6mResumen"></canvas>
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
                                    <i class="bi bi-info-circle" aria-hidden="true"></i>
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
                            </div>
                            <p id="graficoGastosFlexibles6mResumen" class="visually-hidden" aria-live="polite">El gráfico se actualizará con la evolución de gastos flexibles.</p>
                        </div>

                        <div id="panelEvolucionEsenciales" class="bh-chart-panel" role="region" aria-labelledby="btnEvolucionEsenciales" hidden>
                            <div class="contenedor-grafico">
                                <canvas id="graficoGastosEsenciales6m" role="img" aria-label="Gráfico de línea de gastos esenciales de los últimos meses" aria-describedby="graficoGastosEsenciales6mResumen"></canvas>
                            </div>
                            <p id="graficoGastosEsenciales6mResumen" class="visually-hidden" aria-live="polite">El gráfico se actualizará con la evolución de gastos esenciales.</p>
                        </div>
                    </div>

                    <!--Gráfico top 5 de gastos flexibles-->
                    <div class="bh-card bh-card-chart bh-card-habits-scale">
                        <div class="bh-card-chart-header">
                            <h2>
                                Top de gastos flexibles
                                <button type="button"
                                    class="bh-btn bh-btn-icon bh-btn-ghost info-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#infoEscalaHabitosMedia"
                                    data-escala-habitos-info
                                    aria-label="Información sobre el top 5 de gastos flexibles">
                                    <i class="bi bi-info-circle" aria-hidden="true"></i>
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
                        </div>

                        <p id="graficoEscalaHabitosResumen" class="visually-hidden" aria-live="polite">El gráfico se actualizará con las principales categorías de gasto flexible.</p>
                        <p class="bh-chart-hint"><i class="bi bi-hand-index-thumb" aria-hidden="true"></i> Toca una barra y descubre qué pasaría si invirtieras ese dinero en lugar de gastarlo</p>
                    </div>
                </aside>
            </div>
        </div>
    </main>
</div>

<div class="offcanvas offcanvas-end bh-summary-offcanvas d-md-none" tabindex="-1" id="resumenMensualPanel" aria-labelledby="resumenMensualPanelTitle">
    <div class="offcanvas-header">
        <div>
            <h2 class="offcanvas-title" id="resumenMensualPanelTitle">Resumen mensual</h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar resumen mensual"></button>
    </div>
    <div class="offcanvas-body">
        <div data-summary-offcanvas-slot></div>
    </div>
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

<!--Modal de ahorro posible-->
<?php bh_info_modal('infoAhorroPosible', '¿Qué es el ahorro posible?', <<<'HTML'
<p>Es una referencia: muestra cuánto podrías ahorrar si solo tuvieras ingresos y gastos esenciales.</p>
<p>Tener 0 gastos flexibles no suele ser realista, pero ayuda a ver tu potencial de ahorro y el impacto de tus decisiones de consumo.</p>
HTML); ?>

<!--Modal de ahorro real-->
<?php bh_info_modal('infoAhorroReal', '¿Qué es el ahorro real?', <<<'HTML'
<p>Es lo que realmente queda al final del mes después de ingresos, gastos esenciales y gastos flexibles.</p>
<p>Muestra el resultado final de tus decisiones financieras del mes.</p>
HTML); ?>

<!--Modal Gráfico de presupuesto mensual-->
<?php bh_info_modal('infoPresupuestoMensual', '¿Cómo interpretar el presupuesto mensual?', <<<'HTML'
<p>Este gráfico muestra un resumen claro de tu economía en el mes actual.</p>
<p><strong>Compara tus ingresos con el total de gastos</strong> y te indica cuánto dinero estás
    ahorrando o perdiendo al final del mes.
</p>
<p>Si el <strong>ahorro es positivo</strong>, significa que <strong>gastas menos de lo que ingresas</strong>.
    Si es <strong>negativo</strong>, estás en déficit: estás <strong>gastando más dinero del que entra</strong>.
</p>
<p>Este gráfico te permite detectar desequilibrios rápidamente
    y tomar decisiones para mejorar tu situación financiera.
</p>
<p>Puedes pasar el cursor sobre cada barra para ver el valor exacto.</p>
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
    'variant'    => 'branded',
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
]);
?>

<?php ob_start(); ?>
<!--Añadimos chart.js-->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.min.js" integrity="sha384-XcdcwHqIPULERb2yDEM4R0XaQKU3YnDsrTmjACBZyfdVVqjh6xQ4/DCMd7XLcA6Y" crossorigin="anonymous"></script>

<!--Cargamos token crsf-->
<script<?= bh_nonce_attr() ?>>
    window.CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>";
    window.BH_GASTO_CATEGORIAS = <?= json_encode($categoriasGasto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
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
