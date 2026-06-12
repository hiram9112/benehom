<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Gestor de Economía Familiar --Dashboard</title>
    <!--Bootstrap CSS-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!--Bootstrap Iconos-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!--Bootstrap JS(componentes interactivos)-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!--Conectamos con archivo CSS propio-->
    <link rel="stylesheet" href="<?= BASE_URL ?>css/custom.css">

    <!-- Flatpickr: selector de fecha -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- Flatpickr plugin: selección por mes/año -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">


</head>

<body>
    <?php
    require_once APP_PATH . '/views/partials/flash-messages.php';
    bh_flash_messages();
    ?>

    <?php
    require_once APP_PATH . '/views/partials/app-navigation.php';
    bh_mobile_nav();
    $categoriasGasto = gastoCategorias();
    $labelsCategoriasGasto = gastoCategoriaLabels();
    $mesSeleccionado = $mesSeleccionado ?? ($_GET['mes'] ?? date('Y-m'));
    ?>



    <!--Contenedor Principal-->
    <div class="bh-app-shell">
            <?php bh_sidebar(); ?>

            <!-- Contenedor principal -->
            <main class="bh-main">
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
                            <div class="bh-summary-metric" id="resumen_ahorro_card">
                                <div class="bh-summary-metric-heading">
                                    <span>Balance del mes</span>
                                </div>
                                <strong id="resumen_ahorro_real">0€</strong>
                            </div>

                            <div class="bh-summary-metric bh-summary-flip-card" role="button" tabindex="0" aria-expanded="false" aria-label="Ver explicación sobre ingresos ahorrados" data-summary-flip>
                                <div class="bh-summary-card-inner">
                                    <div class="bh-summary-card-face bh-summary-card-front">
                                        <div class="bh-summary-metric-heading">
                                            <span>Ingresos ahorrados</span>
                                        </div>
                                        <strong id="resumen_ingresos_ahorrados">0%</strong>
                                        <small>Clic para ver detalle</small>
                                    </div>
                                    <div class="bh-summary-card-face bh-summary-card-back">
                                        <p>Indica qué parte de lo que entra en casa termina quedándose como ahorro real. Te ayuda a ver si tus ingresos dejan margen al final del mes.</p>
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
                                        <small>Clic para ver detalle</small>
                                    </div>
                                    <div class="bh-summary-card-face bh-summary-card-back">
                                        <p>Muestra cuánto presupuesto depende de decisiones de consumo. Cuanto más alto sea, más oportunidades tienes para ajustar sin tocar gastos básicos.</p>
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
                                        <small>Clic para ver detalle</small>
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
                                        <small>Clic para ver detalle</small>
                                    </div>
                                    <div class="bh-summary-card-face bh-summary-card-back">
                                        <p>Señala si tus hábitos de consumo suben o bajan frente al mes anterior. Es una pista rápida para saber si ganas o pierdes margen de ahorro.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </header>

                    <div class="bh-content-grid bh-dashboard-grid">



                <!--Panel central-->
                <section class="bh-dashboard-finance-stack">
                    <!-- Ingresos-->
                    <div class="bh-card bh-card-finance">
                        <div class="bh-card-header">
                            <h3 class="titulo">
                                Ingresos
                                <button type="button"
                                    class="bh-btn bh-btn-icon bh-btn-ghost info-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#infoIngresos"
                                    aria-label="Información sobre ingresos">
                                    <i class="bi bi-info-circle"></i>
                                </button>

                            </h3>
                        </div>
                        <div class="bh-card-body">
                            <form id="formIngresos" class="bh-form bh-guided-form">
                                <?= csrf_field() ?>

                                <div class="bh-field">
                                    <label class="bh-label" for="categoria_ingreso">Categoría</label>
                                    <div class="bh-select-shell">
                                        <select name="categoria_ingreso" id="categoria_ingreso" class="bh-select" required>
                                            <option value="" selected disabled>Selecciona un tipo de ingreso</option>
                                            <option value="salario">Salario</option>
                                            <option value="inversiones">Inversiones</option>
                                            <option value="otros">Otros</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="bh-field">
                                    <label class="bh-label" for="cantidad_ingreso">Cantidad(€)</label>
                                    <input type="number" name="cantidad_ingreso" id="cantidad_ingreso" class="bh-input" step="0.01" required>
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
                                            <li id="ingreso-<?= $ingreso['id'] ?>" class="bh-movement-item">
                                                <div class="bh-movement-main">
                                                    <span class="bh-movement-label categoria_ingreso_individual"><?= htmlspecialchars(formatearCategoria($ingreso['categoria'])) ?></span>
                                                </div>
                                                <div class="bh-movement-side">
                                                    <span class="bh-movement-amount cantidad_ingreso" data-id="<?= $ingreso['id'] ?>"><?= formatearCantidadPHP($ingreso['cantidad']) ?></span><span class="bh-money-symbol">€</span>
                                                    <button class="bh-btn bh-btn-icon bh-btn-ghost eliminar_ingreso" data-id="<?= $ingreso['id'] ?>" aria-label="Eliminar ingreso">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="bh-empty-state bh-dashboard-empty-state">
                                        <span class="bh-empty-state-icon" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
                                        <h4 class="bh-empty-state-title">Sin ingresos este mes</h4>
                                        <p class="bh-empty-state-text">Añade tu primer ingreso para calcular el ahorro posible y el balance real del mes.</p>
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
                            <h3 class="titulo ">Gastos esenciales
                                <button type="button"
                                    class="bh-btn bh-btn-icon bh-btn-ghost info-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#infoGastosEsenciales"
                                    aria-label="Información sobre gastos esenciales">
                                    <i class="bi bi-info-circle"></i>
                                </button>
                            </h3>
                        </div>
                        <div class="bh-card-body">
                            <form id="formGastosEsenciales" class="bh-form bh-guided-form">
                                <?= csrf_field() ?>

                                <div class="bh-category-picker" data-category-picker data-category-type="obligatorio">
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
                                        step="0.01" required>
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
                                                data-tipo="<?= $gastoEsencial['tipo'] ?>">
                                                <div class="bh-movement-main">
                                                    <span class="bh-movement-label categoria_gasto_esencial"><?= htmlspecialchars(formatearCategoria($gastoEsencial['categoria'])) ?></span>
                                                </div>
                                                <div class="bh-movement-side">
                                                    <span class="bh-movement-amount cantidad_gasto_esencial cantidad_gasto" data-id="<?= $gastoEsencial['id'] ?>"><?= formatearCantidadPHP($gastoEsencial['cantidad']) ?></span><span class="bh-money-symbol">€</span>
                                                    <button class="bh-btn bh-btn-icon bh-btn-ghost eliminar_gasto" data-id="<?= $gastoEsencial['id'] ?>" aria-label="Eliminar gasto">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="bh-empty-state bh-dashboard-empty-state">
                                        <span class="bh-empty-state-icon" aria-hidden="true"><i class="bi bi-house-heart"></i></span>
                                        <h4 class="bh-empty-state-title">Sin gastos esenciales</h4>
                                        <p class="bh-empty-state-text">Registra vivienda, suministros o gastos necesarios para ver tu ahorro posible.</p>
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
                                        <i class="bi bi-info-circle"></i>
                                    </button>
                                </div>
                            </div>


                        </div>

                    </div>



                    <!--Gastos flexibles-->
                    <div class="bh-card bh-card-finance">
                        <div class="bh-card-header">
                            <h3 class="titulo">
                                Gastos flexibles
                                <button type="button"
                                    class="bh-btn bh-btn-icon bh-btn-ghost info-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#infoGastosFlexibles"
                                    aria-label="Información sobre gastos flexibles">
                                    <i class="bi bi-info-circle"></i>
                                </button>
                            </h3>
                        </div>
                        <div class="bh-card-body">
                            <form id="formGastosFlexibles" class="bh-form bh-guided-form">
                                <?= csrf_field() ?>

                                <div class="bh-category-picker" data-category-picker data-category-type="voluntario">
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
                                        step="0.01" required>
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
                                                data-tipo="<?= $gastoFlexible['tipo'] ?>">
                                                <div class="bh-movement-main">
                                                    <span class="bh-movement-label categoria_gasto_flexible"><?= htmlspecialchars(formatearCategoria($gastoFlexible['categoria'])) ?></span>
                                                </div>
                                                <div class="bh-movement-side">
                                                    <span class="bh-movement-amount cantidad_gasto_flexible cantidad_gasto" data-id="<?= $gastoFlexible['id'] ?>"><?= formatearCantidadPHP($gastoFlexible['cantidad']) ?></span><span class="bh-money-symbol">€</span>
                                                    <button class="bh-btn bh-btn-icon bh-btn-ghost eliminar_gasto" data-id="<?= $gastoFlexible['id'] ?>" aria-label="Eliminar gasto">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="bh-empty-state bh-dashboard-empty-state">
                                        <span class="bh-empty-state-icon" aria-hidden="true"><i class="bi bi-basket2"></i></span>
                                        <h4 class="bh-empty-state-title">Sin gastos flexibles</h4>
                                        <p class="bh-empty-state-text">Añade ocio, compras o decisiones variables para comparar ahorro posible y ahorro real.</p>
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
                                        <i class="bi bi-info-circle"></i>
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
                    <h5>
                        Presupuesto mensual
                        <button type="button"
                            class="bh-btn bh-btn-icon bh-btn-ghost info-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#infoPresupuestoMensual"
                            aria-label="Información sobre presupuesto mensual">
                            <i class="bi bi-info-circle"></i>
                        </button>
                    </h5>
                    <div class="contenedor-grafico">
                        <canvas id="graficoPresupuestoMensual"></canvas>
                    </div>
                </div>

                <!--Gráfico Ahorros 6m-->
                <div class="bh-card bh-card-chart">
                    <h5>
                        Evolución del Ahorro
                        <button type="button"
                            class="bh-btn bh-btn-icon bh-btn-ghost info-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#infoEvolucionAhorro"
                            aria-label="Información sobre evolución del ahorro">
                            <i class="bi bi-info-circle"></i>
                        </button>
                    </h5>
                    <div class="contenedor-grafico">
                        <canvas id="graficoAhorros6m"></canvas>
                    </div>
                </div>


                <!--Gráfico evolución de gastos-->
                <div class="bh-card bh-card-chart">
                    <div class="bh-card-chart-header">
                        <h5>
                            Evolución de gastos
                            <button type="button"
                                class="bh-btn bh-btn-icon bh-btn-ghost info-btn"
                                data-bs-toggle="modal"
                                data-bs-target="#infoEvolucionFlexibles"
                                data-evolucion-gastos-info
                                aria-label="Información sobre la evolución de gastos">
                                <i class="bi bi-info-circle"></i>
                            </button>
                        </h5>

                        <div class="bh-segmented" role="group" aria-label="Seleccionar tipo de gasto">
                            <button type="button"
                                id="btnEvolucionFlexibles"
                                class="bh-segmented-button is-active"
                                data-evolucion-gastos-tab="voluntario"
                                aria-controls="panelEvolucionFlexibles"
                                aria-pressed="true">
                                Flexibles
                            </button>
                            <button type="button"
                                id="btnEvolucionEsenciales"
                                class="bh-segmented-button"
                                data-evolucion-gastos-tab="obligatorio"
                                aria-controls="panelEvolucionEsenciales"
                                aria-pressed="false">
                                Esenciales
                            </button>
                        </div>
                    </div>

                    <div id="panelEvolucionFlexibles" class="bh-chart-panel" role="region" aria-labelledby="btnEvolucionFlexibles">
                        <div class="contenedor-grafico">
                            <canvas id="graficoGastosFlexibles6m"></canvas>
                        </div>
                    </div>

                    <div id="panelEvolucionEsenciales" class="bh-chart-panel" role="region" aria-labelledby="btnEvolucionEsenciales" hidden>
                        <div class="contenedor-grafico">
                            <canvas id="graficoGastosEsenciales6m"></canvas>
                        </div>
                    </div>
                </div>
                </aside>
                </div>
                </div>
            </main>
    </div>

    <?php bh_mobile_menu(); ?>

    <!--Modal de ingresos-->
    <div class="modal fade" id="infoIngresos" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">¿Qué son los ingresos?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p>Los ingresos representan el dinero que entra en tu hogar durante el mes.</p>

                    <ul>
                        <li>Salarios o nóminas</li>
                        <li>Ingresos extra o puntuales</li>
                        <li>Ingresos por inversiones</li>
                    </ul>

                    <p class="mt-2">Registrar correctamente los ingresos es clave para entender tu ahorro posible y tu ahorro real
                        y analizar si tus gastos están equilibrados.</p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!--Modal de gastos esenciales-->
    <div class="modal fade" id="infoGastosEsenciales" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">¿Qué son los gastos esenciales?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p>Los gastos esenciales son los pagos que sostienen el funcionamiento del hogar
                        y cubren necesidades básicas.</p>

                    <p>Suelen repetirse cada mes y te ayudan a entender cuánto dinero necesitas
                        para vivir con estabilidad.</p>

                    <p class="mt-2">Separarlos del resto de gastos permite calcular tu margen real
                        antes de revisar decisiones de consumo.</p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>

            </div>
        </div>
    </div>

    <!--Modal de gastos flexibles-->

    <div class="modal fade" id="infoGastosFlexibles" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">¿Qué son los gastos flexibles?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p>Los gastos flexibles son pagos vinculados a hábitos, preferencias y decisiones de consumo.
                        No son negativos por sí mismos, pero suelen ofrecer más margen de revisión.
                    </p>

                    <p>Identificarlos permite entender con claridad
                        <strong>qué parte de nuestro presupuesto es realmente flexible</strong>.
                        Muchos pueden reducirse, pausarse o reorganizarse sin afectar a los gastos esenciales del hogar.
                    </p>

                    <p class="mt-2">
                        Revisarlos ayuda a medir el impacto real que pequeños cambios en nuestros hábitos
                        pueden tener sobre el <strong>ahorro real</strong> y el
                        <strong>bienestar financiero</strong>.
                        En muchos casos, el efecto puede notarse de un mes a otro.
                    </p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>

            </div>
        </div>
    </div>

    <!--Modal de ahorro posible-->
    <div class="modal fade" id="infoAhorroPosible" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">¿Qué es el ahorro posible?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p>Es una referencia: muestra cuánto podrías ahorrar si solo tuvieras ingresos y gastos esenciales.</p>
                    <p>Tener 0 gastos flexibles no suele ser realista, pero ayuda a ver tu potencial de ahorro y el impacto de tus decisiones de consumo.</p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!--Modal de ahorro real-->
    <div class="modal fade" id="infoAhorroReal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">¿Qué es el ahorro real?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p>Es lo que realmente queda al final del mes después de ingresos, gastos esenciales y gastos flexibles.</p>
                    <p>Muestra el resultado final de tus decisiones financieras del mes.</p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!--Modal Gráfico de presupuesto mensual-->
    <div class="modal fade" id="infoPresupuestoMensual" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">¿Cómo interpretar el presupuesto mensual?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p>Este gráfico muestra un resumen claro de tu economía en el mes actual.</p>

                    <p><strong>Compara tus ingresos con el total de gastos</strong> y te indica cuánto dinero estás
                        ahorrando o perdiendo al final del mes.
                    </p>


                    <p>Los porcentajes te ayudan a entender qué parte de tus ingresos
                        se destina a gastos y qué parte se convierte en ahorro.
                    </p>

                    <p>Si el <strong>ahorro es positivo</strong>, significa que <strong>gastas menos de lo que ingresas</strong>.
                        Si es <strong>negativo</strong>, estás en déficit: estás <strong>gastando más dinero del que entra</strong>.
                    </p>

                    <p class="mt-2">Este gráfico te permite detectar desequilibrios rápidamente
                        y tomar decisiones para mejorar tu situación financiera.
                    </p>
                    <p class="mt-2">Puedes pasar el cursor sobre cada barra para ver el valor exacto.
                    </p>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>

            </div>
        </div>
    </div>

    <!--Modal Gráfico de evolución del ahorro-->
    <div class="modal fade" id="infoEvolucionAhorro" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">¿Cómo interpretar la evolución del ahorro?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
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

                    <p class="mt-2">Si esta situación se repite durante varios meses, es una señal de alerta:
                        el nivel de gasto actual <strong>no es sostenible</strong> , a largo plazo los ahorros
                        pueden agotarse.
                    </p>

                    <p class="mt-2">Puedes pasar el cursor sobre cada barra para ver el valor exacto
                        correspondiente a cada mes.
                    </p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>

            </div>
        </div>
    </div>

    <!--Modal Gráfico de evolución de gastos esenciales-->
    <div class="modal fade" id="infoEvolucionEsenciales" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">¿Qué muestra este gráfico?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
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

                    <p class="mt-2">
                        Identificar estas variaciones a tiempo te permite
                        <strong>corregir errores</strong> y tener una visión más real
                        de cuánto te cuesta mantener tu hogar cada mes.
                    </p>

                    <p class="mt-2">
                        Al pasar el cursor sobre el gráfico puedes ver
                        el importe exacto de cada mes.
                    </p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>

            </div>
        </div>
    </div>



    <!--Modal Gráfico de evolución de gastos flexibles-->

    <div class="modal fade" id="infoEvolucionFlexibles" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">¿Qué muestra este gráfico?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
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


                    <p class="mt-2">
                        Puedes pasar el cursor sobre cada punto para ver el
                        importe exacto de cada mes.
                    </p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Modal de confirmación genérico -->
    <div class="modal fade" id="modalConfirmacion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalConfirmacionTitulo">Confirmar acción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body" id="modalConfirmacionTexto">
                    ¿Estás seguro?
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-danger" id="modalConfirmacionAceptar">
                        Aceptar
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal informativo genérico (éxito / error) -->
    <div class="modal fade" id="modalInfo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalInfoTitulo">Información</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body" id="modalInfoTexto">
                    <!-- texto dinámico -->
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>

            </div>
        </div>
    </div>




    <!--Añadimos chart.js-->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!--Cargamos token crsf-->
    <script>
        window.CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>";
        window.BH_GASTO_CATEGORIAS = <?= json_encode($categoriasGasto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        window.BH_GASTO_CATEGORIA_LABELS = <?= json_encode($labelsCategoriasGasto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    </script>


    <!-- Flatpickr: librería base -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <!-- Flatpickr plugin: selección de mes/año -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>

    <!-- Locale ------ español-->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>



    <script src="<?= BASE_URL ?>js/flash.js"></script>

    <!--Enlazamos con nuestros arvhicos js-->
    <script src="<?= BASE_URL ?>js/validaciones.js"></script>
    <script src="<?= BASE_URL ?>js/dashboard-graficos.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>js/dashboard-edicion.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>js/dashboard-categorias.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>js/dashboard-formularios.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>js/dashboard-utils.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>js/dashboard-dom.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>js/dashboard.js?v=<?= time() ?>"></script>


</body>

</html>
