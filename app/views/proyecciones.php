<?php
require_once APP_PATH . '/views/partials/head.php';

bh_document_begin([
    'title' => 'Proyecciones financieras',
    'description' => 'Simulador privado de BeneHom para explorar metas, ahorro, inversión, inflación e hipoteca con fines educativos.',
    'canonical' => bh_url('index.php?r=proyecciones/index'),
    'robots' => 'noindex',
]);
?>
    <?php
    require_once APP_PATH . '/views/partials/app-navigation.php';
    require_once APP_PATH . '/views/partials/flash-messages.php';
    require_once APP_PATH . '/views/partials/proyecciones-cards.php';
    require_once APP_PATH . '/views/partials/modals.php';
    bh_flash_messages();
    bh_mobile_nav();

    $frecuenciasReinversion = [
        'mensual' => 'Mensual',
        'trimestral' => 'Trimestral',
        'semestral' => 'Semestral',
        'anual' => 'Anual',
    ];

    // Etiqueta legible del mes de referencia para la sugerencia de ahorro real.
    $mesesEs = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $mesReferencia = $mesSeleccionado ?? date('Y-m');
    $partesMesReferencia = explode('-', (string) $mesReferencia);
    $mesSugerenciaLabel = (count($partesMesReferencia) === 2 && isset($mesesEs[(int) $partesMesReferencia[1]]))
        ? $mesesEs[(int) $partesMesReferencia[1]] . ' ' . $partesMesReferencia[0]
        : $mesReferencia;
    ?>

    <!--Contenedor Principal-->
    <div class="bh-app-shell">
        <?php bh_sidebar(); ?>

        <!--Panel Central-->
        <main id="contenido" class="bh-main">
            <section class="bh-projections-hero mb-4" aria-labelledby="proyecciones-titulo">
                <article class="bh-card bh-card-finance bh-projections-intro-card">
                    <div class="bh-card-body">

                        <p class="bh-projections-kicker">Simulador educativo</p>
                        <h1 id="proyecciones-titulo">Descubre cómo tus decisiones de hoy moldean tu futuro financiero</h1>
                        <p class="bh-projections-intro-lead">
                            Anticípate antes de decidir: simula metas de ahorro, inversiones, inflación o cuotas
                            hipotecarias y observa el impacto que tendrían a largo plazo, sin modificar tus datos
                            reales ni arriesgar tu dinero.
                        </p>
                        <p>
                            Indica tu ahorro mensual simulado como límite realista y repártelo entre tus metas e
                            inversiones. Así pruebas distintas situaciones del día a día y comparas escenarios con
                            tranquilidad.
                        </p>
                        <p class="bh-projections-intro-note mb-0">
                            Los resultados son estimaciones orientativas y educativas: no garantizan rentabilidades
                            ni son recomendaciones financieras.
                        </p>
                    </div>
                </article>

                <article class="bh-card bh-projections-savings-card" aria-labelledby="ahorro-mensual-disponible-label">
                    <div class="bh-card-body">
                        <p id="ahorro-mensual-disponible-label" class="bh-projections-savings-label">Ahorro mensual simulado</p>
                        <div class="bh-projections-savings-value">
                            <span
                                id="ahorro_mensual_disponible"
                                class="bh-projections-savings-amount"
                                role="button"
                                tabindex="0"
                                aria-label="Editar ahorro mensual simulado"
                                data-value="<?= htmlspecialchars((string) $ahorroMensualDisponible, ENT_QUOTES, 'UTF-8') ?>">
                                <?= bh_proy_formatear_cantidad($ahorroMensualDisponible) ?>
                            </span>
                            <span class="bh-projections-currency">€</span>
                        </div>
                        <p class="bh-projections-edit-hint">Haz clic en el importe para indicar tu ahorro mensual simulado.</p>

                        <div class="bh-projections-savings-breakdown">
                            <p>
                                <span>Asignado a metas e inversiones</span>
                                <strong id="ahorro_asignado_metas"><?= bh_proy_formatear_euros($ahorroAsignadoMetas) ?></strong>
                            </p>
                            <p>
                                <span>Disponible</span>
                                <strong id="ahorro_disponible_metas"><?= bh_proy_formatear_euros($ahorroDisponibleMetas) ?></strong>
                            </p>
                        </div>

                        <div class="bh-projections-savings-suggestion">
                            <span class="bh-projections-savings-suggestion-label">Ahorro real de <?= htmlspecialchars($mesSugerenciaLabel, ENT_QUOTES, 'UTF-8') ?>:</span>
                            <?php if ($ahorroRealMesSugerencia >= 0): ?>
                                <span class="bh-projections-savings-suggestion-value"><?= bh_proy_formatear_euros($ahorroRealMesSugerencia) ?></span>
                            <?php else: ?>
                                <span class="bh-projections-savings-suggestion-value">Este mes no generas ahorro</span>
                                <span class="bh-projections-savings-suggestion-note">Gastas más de lo que ingresas.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            </section>

            <section aria-labelledby="metas-ahorro-titulo" class="bh-projections-goals">
                <div class="bh-projections-goals-grid">
                    <article class="bh-card bh-meta-list-card">
                        <div class="bh-card-header bh-projections-module-header">
                            <div class="bh-projections-module-text">
                                <div class="bh-projections-module-heading">
                                    <h2 id="metas-ahorro-titulo">Metas de ahorro</h2>
                                    <span class="bh-badge bh-badge-saving" data-section-count="meta" data-count-one="activa" data-count-many="activas"><?= count($metasAhorroPreparadas) ?> <?= count($metasAhorroPreparadas) === 1 ? 'activa' : 'activas' ?></span>
                                </div>
                                <p class="bh-projections-module-desc">
                                    Crea metas proyectadas para estimar plazos o calcular cuánto necesitarías aportar al mes.
                                    No representan dinero apartado realmente. Cada meta consume parte de la capacidad mensual configurada.
                                </p>
                            </div>
                            <button type="button" class="bh-btn bh-btn-primary" data-bs-toggle="offcanvas" data-bs-target="#crearMetaAhorroPanel" aria-controls="crearMetaAhorroPanel">
                                <i class="bi bi-plus-circle" aria-hidden="true"></i>
                                Nueva proyección
                            </button>
                        </div>
                        <div class="bh-card-body">
                            <div class="bh-meta-list" data-section-list="meta">
                                <?php foreach ($metasAhorroPreparadas as $meta): ?>
                                    <?= bh_render_meta_card($meta, $gastosFlexiblesPorCategoria) ?>
                                <?php endforeach; ?>
                            </div>
                            <div class="bh-empty-state bh-meta-empty-state" data-section-empty="meta" <?= empty($metasAhorroPreparadas) ? '' : ' hidden' ?>>
                                <div class="bh-empty-state-icon" aria-hidden="true">
                                    <i class="bi bi-journal-plus" aria-hidden="true"></i>
                                </div>
                                <h3 class="bh-empty-state-title">Aún no tienes metas guardadas</h3>
                                <p class="bh-empty-state-text">
                                    Define un objetivo, indica cuánto puedes aportar al mes o estable un plazo y BeneHom hará los cálculos necesarios.
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section aria-labelledby="inversion-educativa-titulo" class="bh-projections-investments">
                <div class="bh-projections-investments-grid">
                    <article class="bh-card bh-investment-list-card">
                        <div class="bh-card-header bh-projections-module-header">
                            <div class="bh-projections-module-text">
                                <div class="bh-projections-module-heading">
                                    <h2 id="inversion-educativa-titulo">Escenarios de inversión</h2>
                                    <span class="bh-badge bh-badge-saving" data-section-count="inversion" data-count-one="guardado" data-count-many="guardados"><?= count($escenariosInversionPreparados) ?> <?= count($escenariosInversionPreparados) === 1 ? 'guardado' : 'guardados' ?></span>
                                </div>
                                <p class="bh-projections-module-desc">
                                    Guarda escenarios para entender cómo influye el interés compuesto y la frecuencia de reinversión
                                    de beneficios. La rentabilidad indicada es anual, educativa y no representa una recomendación financiera.
                                </p>
                            </div>
                            <button type="button" class="bh-btn bh-btn-primary" data-bs-toggle="offcanvas" data-bs-target="#crearEscenarioInversionPanel" aria-controls="crearEscenarioInversionPanel">
                                <i class="bi bi-plus-circle" aria-hidden="true"></i>
                                Nueva proyección
                            </button>
                        </div>
                        <div class="bh-card-body">
                            <div class="bh-investment-list" data-section-list="inversion">
                                <?php foreach ($escenariosInversionPreparados as $escenario): ?>
                                    <?= bh_render_escenario_inversion_card($escenario) ?>
                                <?php endforeach; ?>
                            </div>
                            <div class="bh-empty-state bh-meta-empty-state" data-section-empty="inversion" <?= empty($escenariosInversionPreparados) ? '' : ' hidden' ?>>
                                <div class="bh-empty-state-icon" aria-hidden="true">
                                    <i class="bi bi-graph-up" aria-hidden="true"></i>
                                </div>
                                <h3 class="bh-empty-state-title">Aún no tienes escenarios de inversión</h3>
                                <p class="bh-empty-state-text">
                                    Crea un escenario para visualizar cómo el interés compuesto y la frecuencia de reinversión afectan al valor final estimado.
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section aria-labelledby="inflacion-temporal-titulo" class="bh-projections-inflation">
                <div class="bh-projections-inflation-grid">
                    <article class="bh-card bh-inflation-list-card">
                        <div class="bh-card-header bh-projections-module-header">
                            <div class="bh-projections-module-text">
                                <div class="bh-projections-module-heading">
                                    <h2 id="inflacion-temporal-titulo">Calculadora de inflación</h2>
                                    <span class="bh-badge bh-badge-saving" data-section-count="inflacion" data-count-one="guardada" data-count-many="guardadas"><?= count($proyeccionesInflacionPreparadas) ?> <?= count($proyeccionesInflacionPreparadas) === 1 ? 'guardada' : 'guardadas' ?></span>
                                </div>
                                <p class="bh-projections-module-desc">
                                    Calcula cómo una inflación anual estimada puede reducir el valor real de tus ahorros con el tiempo.
                                    La inflación no cambia la cantidad que tienes, pero hace que puedas comprar menos cosas con ese dinero.
                                </p>
                            </div>
                            <button type="button" class="bh-btn bh-btn-primary" data-bs-toggle="offcanvas" data-bs-target="#crearInflacionProyeccionPanel" aria-controls="crearInflacionProyeccionPanel">
                                <i class="bi bi-plus-circle" aria-hidden="true"></i>
                                Nueva proyección
                            </button>
                        </div>
                        <div class="bh-card-body">
                            <div class="bh-inflation-list" data-section-list="inflacion">
                                <?php foreach ($proyeccionesInflacionPreparadas as $proyeccion): ?>
                                    <?= bh_render_inflacion_card($proyeccion) ?>
                                <?php endforeach; ?>
                            </div>
                            <div class="bh-empty-state bh-meta-empty-state" data-section-empty="inflacion" <?= empty($proyeccionesInflacionPreparadas) ? '' : ' hidden' ?>>
                                <div class="bh-empty-state-icon" aria-hidden="true">
                                    <i class="bi bi-cash-stack" aria-hidden="true"></i>
                                </div>
                                <h3 class="bh-empty-state-title">Aún no tienes proyecciones de inflación</h3>
                                <p class="bh-empty-state-text">
                                    Crea una proyección para ver cómo una inflación anual estimada reduce lo que tu dinero pueden comprar con el tiempo.
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section aria-labelledby="hipoteca-calculadora-titulo" class="bh-projections-mortgage">
                <div class="bh-projections-mortgage-grid">
                    <article class="bh-card bh-mortgage-list-card">
                        <div class="bh-card-header bh-projections-module-header">
                            <div class="bh-projections-module-text">
                                <div class="bh-projections-module-heading">
                                    <h2 id="hipoteca-calculadora-titulo">Calculadora de hipoteca</h2>
                                    <span class="bh-badge bh-badge-saving" data-section-count="hipoteca" data-count-one="guardada" data-count-many="guardadas"><?= count($calculadorasHipotecaPreparadas) ?> <?= count($calculadorasHipotecaPreparadas) === 1 ? 'guardada' : 'guardadas' ?></span>
                                </div>
                                <p class="bh-projections-module-desc">
                                    Proyecta cuotas mensuales, intereses totales y coste total de un préstamo hipotecario
                                    según el importe, el interés anual y el plazo. No representa una oferta vinculante ni una recomendación financiera.
                                </p>
                            </div>
                            <button type="button" class="bh-btn bh-btn-primary" data-bs-toggle="offcanvas" data-bs-target="#crearCalculadoraHipotecaPanel" aria-controls="crearCalculadoraHipotecaPanel">
                                <i class="bi bi-plus-circle" aria-hidden="true"></i>
                                Nueva proyección
                            </button>
                        </div>
                        <div class="bh-card-body">
                            <div class="bh-mortgage-list" data-section-list="hipoteca">
                                <?php foreach ($calculadorasHipotecaPreparadas as $calculadora): ?>
                                    <?= bh_render_calculadora_hipoteca_card($calculadora) ?>
                                <?php endforeach; ?>
                            </div>
                            <div class="bh-empty-state bh-meta-empty-state" data-section-empty="hipoteca" <?= empty($calculadorasHipotecaPreparadas) ? '' : ' hidden' ?>>
                                <div class="bh-empty-state-icon" aria-hidden="true">
                                    <i class="bi bi-house" aria-hidden="true"></i>
                                </div>
                                <h3 class="bh-empty-state-title">Aún no tienes calculadoras de hipoteca</h3>
                                <p class="bh-empty-state-text">
                                    Proyecta cuotas mensuales, intereses totales y coste total de un préstamo hipotecario según importe, interés y plazo.
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </main>
    </div>

    <div class="offcanvas offcanvas-end bh-projections-offcanvas" tabindex="-1" id="crearMetaAhorroPanel" aria-labelledby="crearMetaAhorroPanelLabel">
        <div class="offcanvas-header">
            <div>
                <p class="bh-projections-kicker mb-1">Simulación educativa</p>
                <h2 class="offcanvas-title" id="crearMetaAhorroPanelLabel">Nueva meta de ahorro</h2>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="index.php?r=proyecciones/crearMetaAhorro" class="bh-form js-meta-form" data-ajax-create data-section="meta" data-ajax-action="index.php?r=proyecciones/crearMetaAhorroAjax">
                <?= csrf_field() ?>

                <div class="bh-field-row">
                    <div class="bh-field">
                        <label class="bh-label" for="meta_nombre">Nombre</label>
                        <input class="bh-input" type="text" id="meta_nombre" name="nombre" maxlength="100" required placeholder="Ej. Fondo de emergencias">
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
                    <p class="bh-field-help">Debe caber dentro de tu ahorro disponible para asignar.</p>
                </div>

                <div class="bh-field" data-mode-group="fecha" hidden>
                    <label class="bh-label" for="meta_fecha_objetivo">Fecha objetivo</label>
                    <input class="bh-input" type="date" id="meta_fecha_objetivo" name="fecha_objetivo">
                    <p class="bh-field-help">Calcularemos la aportación mensual necesaria hasta esa fecha.</p>
                </div>

                <div class="bh-meta-form-note">
                    Disponible para asignar: <strong id="meta_capacidad_disponible"><?= bh_proy_formatear_euros($ahorroDisponibleMetas) ?></strong>.
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
                <p class="bh-projections-kicker mb-1">Simulación educativa</p>
                <h2 class="offcanvas-title" id="crearEscenarioInversionPanelLabel">Nuevo escenario de inversión</h2>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="index.php?r=proyecciones/crearEscenarioInversion" class="bh-form" data-ajax-create data-section="inversion" data-ajax-action="index.php?r=proyecciones/crearEscenarioInversionAjax">
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
                        <span class="bh-field-label-row">
                            <label class="bh-label" for="inversion_rentabilidad_anual">Rentabilidad anual media estimada (%)</label>
                            <button type="button" class="bh-metric-info-btn"
                                data-bs-toggle="modal" data-bs-target="#infoRentabilidadMediaInversion"
                                aria-label="Cómo funciona la rentabilidad anual media estimada">
                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                            </button>
                        </span>
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
                                <option value="<?= htmlspecialchars($valorFrecuencia, ENT_QUOTES, 'UTF-8') ?>" <?= $valorFrecuencia === 'anual' ? ' selected' : '' ?>>
                                    <?= htmlspecialchars($labelFrecuencia, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="bh-field-help">
                        La frecuencia indica cada cuánto se suman al capital: mensual, trimestral, semestral o anual. Cuanto más frecuente sea la reinversión, mayor puede ser el efecto del interés compuesto.
                    </p>
                </div>

                <div class="bh-meta-form-note">
                    Disponible para asignar: <strong id="inversion_capacidad_disponible"><?= bh_proy_formatear_euros($ahorroDisponibleMetas) ?></strong>.
                    La aportación mensual se reparte con tus metas.
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
                <p class="bh-projections-kicker mb-1">Simulación educativa</p>
                <h2 class="offcanvas-title" id="crearInflacionProyeccionPanelLabel">Nueva proyección de inflación</h2>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="index.php?r=proyecciones/crearInflacionProyeccion" class="bh-form" data-ajax-create data-section="inflacion" data-ajax-action="index.php?r=proyecciones/crearInflacionProyeccionAjax">
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
                        <p class="bh-field-help">
                            Consulta este dato en el instituto de estadística oficial de tu país (en España, el INE) o en otra fuente oficial.
                        </p>
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
                <p class="bh-projections-kicker mb-1">Simulación educativa</p>
                <h2 class="offcanvas-title" id="crearCalculadoraHipotecaPanelLabel">Nueva simulación de hipoteca</h2>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body">
            <form method="POST" action="index.php?r=proyecciones/crearCalculadoraHipoteca" class="bh-form" data-ajax-create data-section="hipoteca" data-ajax-action="index.php?r=proyecciones/crearCalculadoraHipotecaAjax">
                <?= csrf_field() ?>

                <div class="bh-field-label-row bh-amortizacion-titular">
                    <span class="bh-amortizacion-titular-text">Sistema de amortización francés</span>
                    <button type="button" class="bh-metric-info-btn"
                        data-bs-toggle="modal" data-bs-target="#infoAmortizacionFrancesa"
                        aria-label="Cómo funciona el sistema de amortización francés">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="bh-field">
                    <label class="bh-label" for="hipoteca_nombre">Nombre</label>
                    <input class="bh-input" type="text" id="hipoteca_nombre" name="nombre" maxlength="100" required placeholder="Ej. Nombre del banco">
                </div>

                <div class="bh-field">
                    <label class="bh-label" for="hipoteca_precio_inmueble">Precio del inmueble</label>
                    <input class="bh-input" type="number" id="hipoteca_precio_inmueble" name="precio_inmueble" min="0.01" step="0.01" inputmode="decimal" required>
                </div>

                <div class="bh-field">
                    <label class="bh-label" for="hipoteca_porcentaje_financiacion">Porcentaje financiado (%)</label>
                    <input class="bh-input" type="number" id="hipoteca_porcentaje_financiacion" name="porcentaje_financiacion" min="0.01" max="100" step="0.01" inputmode="decimal" required placeholder="Ej. 80%">
                    <p class="bh-field-help">
                        Cada banco financia un porcentaje distinto del precio. Es orientativo: lo habitual ronda el 80%, ajústalo según la oferta real que te ofrezcan.
                    </p>
                </div>

                <div class="bh-field-row">
                    <div class="bh-field">
                        <label class="bh-label" for="hipoteca_interes_anual">Interés anual (%)</label>
                        <input class="bh-input" type="number" id="hipoteca_interes_anual" name="interes_anual" min="0" step="0.01" inputmode="decimal" required>
                    </div>
                    <div class="bh-field">
                        <label class="bh-label" for="hipoteca_plazo_anios">Plazo en años</label>
                        <input class="bh-input" type="number" id="hipoteca_plazo_anios" name="plazo_anios" min="1" step="1" required>
                    </div>
                </div>

                <div class="bh-meta-form-note">
                    <span class="bh-hipoteca-note">
                        Esta simulación no incluye impuestos ni gastos de compra, como notaría, registro o gestoría. Estos gastos suelen estar entre el 10% y el 12% del precio, aunque pueden variar según el país, la comunidad o región, la vivienda y cada caso concreto.
                    </span>
                </div>

                <label class="bh-hipoteca-confirm">
                    <input type="checkbox" class="bh-hipoteca-confirm-input" data-hipoteca-leido>
                    <span>Entiendo que la simulación <strong>no incluye impuestos ni gastos de compra</strong>, que se pagan aparte.</span>
                </label>

                <button type="submit" class="bh-btn bh-btn-primary is-disabled" aria-disabled="true">
                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                    Crear proyección
                </button>
            </form>
        </div>
    </div>

    <?php
    bh_info_modal('infoProyeccionGastosFlexibles', 'Proyectar reducción de gastos flexible', <<<'HTML'
<p>
    Elige una categoría flexible registrada en el mes seleccionado y un porcentaje de reducción.
    BeneHom calcula cuánto podrías aportar de forma adicional a esa meta.
</p>
<p>
    La comparación actualiza solo la card: muestra la aportación proyectada, el nuevo plazo estimado
    y la fecha aproximada. No modifica tus gastos reales ni cambia la meta guardada.
</p>
HTML);

    bh_info_modal('infoRentabilidadMediaInversion', 'Qué es la rentabilidad anual media estimada', <<<'HTML'
<p>
    Es una <strong>media orientativa</strong>, no un valor fijo que se repita igual cada año.
    La rentabilidad real cambia continuamente: algunos años puede subir y otros puede bajar.
</p>
<p>
    Por ejemplo, una misma inversión podría tener un año de <strong>+8 %</strong>,
    otro de <strong>+14 %</strong>, otro de <strong>−5 %</strong> y otro de
    <strong>−12 %</strong>. Aun con años en negativo, la media de varios años puede dar
    una idea aproximada de cómo podría evolucionar a largo plazo.
</p>
<p>
    <strong>Estos números son solo ejemplos con fines educativos y no representan
        una promesa ni una garantía de resultados.</strong> La rentabilidad real depende
    del mercado y puede ser muy distinta, incluso negativa.
</p>
HTML);

    bh_info_modal('infoAhorrosNecesariosHipoteca', 'Qué son los ahorros necesarios', <<<'HTML'
<p>
    Son la diferencia entre el precio del inmueble y el importe que te financia la
    hipoteca: el dinero que tendrías que aportar tú para llegar al precio total.
</p>
<p>
    Importante: esta cantidad <strong>no incluye los impuestos ni los gastos de compra</strong>
    (notaría, registro, gestoría…), que suelen suponer entre un 10% y un 12% del precio
    adicional. Para comprar la vivienda necesitarías sumar también ese dinero.
</p>
<p>
    Además, esos impuestos y gastos <strong>cambian según el país, la comunidad o la
        región</strong> donde compres, y también según el tipo de vivienda. Infórmate de los
    importes concretos de tu zona antes de tomar cualquier decisión.
</p>
<p>
    <strong>Esta simulación es orientativa y con fines educativos: no es una oferta
        ni una recomendación financiera.</strong>
</p>
HTML);

    bh_info_modal('infoAmortizacionFrancesa', 'Cómo funciona el sistema de amortización francés', <<<'HTML'
<p>
    Esta calculadora usa el <strong>sistema de amortización francés</strong>, el método
    más habitual en las hipotecas en España. Con él pagas <strong>la misma cuota todos
    los meses</strong> durante todo el plazo del préstamo.
</p>
<p>Cada cuota se reparte en dos partes:</p>
<ul>
    <li><strong>Intereses</strong>: lo que cobra el banco por prestarte el dinero.</li>
    <li><strong>Capital</strong>: la parte que reduce de verdad lo que debes.</li>
</ul>
<p>
    Aunque la cuota no cambia, ese reparto sí: al principio pagas <strong>más intereses
    y menos capital</strong> y, con el tiempo, se invierte: vas amortizando
    <strong>más capital y pagando menos intereses</strong>.
</p>
<p>
    <strong>Más adelante añadiremos otros sistemas de amortización.</strong> De momento,
    todas las simulaciones usan el sistema francés.
</p>
<p>Información orientativa con fines educativos.</p>
HTML);
    ?>

    <?php
    bh_mobile_menu();

    $avisoCapacidadSuperada = 'El ahorro asignado a tus proyecciones supera el ahorro mensual simulado que has indicado. Puedes ajustar el importe para seguir simulando sin cambiar tus datos reales; recuerda que para destinar ahorro real a tus metas o inversiones necesitarás aumentar tus ingresos o reducir tus gastos.';

    $bhAvisos = [];
    foreach ([$avisoAhorroAsignado, $avisoGastosFlexibles, $avisoEscenariosInversion, $avisoProyeccionesInflacion, $avisoCalculadorasHipoteca] as $avisoError) {
        if (!empty($avisoError)) {
            $bhAvisos[] = ['texto' => $avisoError, 'tipo' => 'error'];
        }
    }
    ?>
<?php ob_start(); ?>
    <script<?= bh_nonce_attr() ?>>
        window.CSRF_TOKEN = "<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>";
        window.BH_PROYECCIONES_AVISOS = <?= json_encode($bhAvisos, JSON_UNESCAPED_UNICODE) ?>;
        window.BH_AVISO_AHORRO_SUPERA = <?= json_encode($avisoCapacidadSuperada, JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="<?= bh_asset('js/proyecciones.js') ?>"></script>
<?php
$bhProyeccionesBodyEndExtra = ob_get_clean();

bh_document_end([
    'include_bootstrap_js' => true,
    'include_flash_js' => true,
    'body_end_extra' => $bhProyeccionesBodyEndExtra,
]);
