<?php
$usuarioLogueado = isset($_SESSION['usuario_id']);
$panelUrl = BASE_URL . 'index.php?r=dashboard/index';
$loginUrl = BASE_URL . 'index.php?r=auth/login';
$registerUrl = BASE_URL . 'index.php?r=registro/registrarUsuario';
$primaryUrl = $usuarioLogueado ? $panelUrl : $registerUrl;
$primaryLabel = $usuarioLogueado ? 'Ir al panel' : 'Crear cuenta';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="BeneHom te ayuda a registrar tu economía mensual, distinguir gastos esenciales y flexibles, calcular tu ahorro real y guardar proyecciones financieras para decidir con más claridad.">
    <meta property="og:title" content="BeneHom | Economía familiar clara y proyecciones financieras">
    <meta property="og:description" content="Ordena tus ingresos y gastos reales, entiende tu ahorro mensual y prueba escenarios de ahorro, inflación, inversión o hipoteca sin tocar tus datos.">
    <meta property="og:url" content="https://benehom.es">
    <meta property="og:type" content="website">
    <meta property="og:image" content="https://benehom.es/img/og-image.png">
    <meta property="og:locale" content="es_ES">

    <title>BeneHom | Economía familiar clara y proyecciones financieras</title>

    <link rel="icon" type="image/png" href="<?= BASE_URL ?>img/og-image.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/custom.css">
</head>

<body class="bh-entry-body">
    <a class="bh-skip-link" href="#contenido">Saltar al contenido</a>

    <header class="bh-entry-nav" aria-label="Navegación principal">
        <a class="bh-entry-brand" href="<?= BASE_URL ?>index.php" aria-label="BeneHom inicio">
            <img src="<?= BASE_URL ?>img/logo-benehom.png" alt="BeneHom" width="120" height="80">
        </a>
        <nav class="bh-entry-actions" aria-label="Acciones de acceso">
            <?php if (!$usuarioLogueado): ?>
                <a class="bh-btn bh-btn-ghost" href="<?= $loginUrl ?>">Iniciar sesión</a>
                <a class="bh-btn bh-btn-primary" href="<?= $registerUrl ?>">Crear cuenta</a>
            <?php else: ?>
                <a class="bh-btn bh-btn-primary" href="<?= $panelUrl ?>">Ir al panel</a>
            <?php endif; ?>
        </nav>
    </header>

    <main id="contenido" class="bh-entry-main">
        <section class="bh-entry-hero bh-landing-hero" aria-labelledby="entry-title">
            <div class="bh-entry-copy">
                <p class="bh-entry-kicker">Economía familiar con proyecciones</p>
                <h1 id="entry-title" class="bh-landing-title">Ordena tu mes real y prueba escenarios antes de decidir.</h1>
                <p class="bh-entry-lead">BeneHom separa lo que ya ha pasado de lo que quieres probar: registra ingresos y gastos, entiende tu ahorro real y guarda proyecciones de metas, inflación, inversión o hipoteca sin modificar tus datos del mes.</p>
                <div class="bh-entry-cta" aria-label="Acciones principales">
                    <a class="bh-btn bh-btn-primary bh-entry-primary" href="<?= $primaryUrl ?>"><?= $primaryLabel ?></a>
                    <?php if (!$usuarioLogueado): ?>
                        <a class="bh-btn bh-btn-secondary" href="<?= $loginUrl ?>">Iniciar sesión</a>
                    <?php endif; ?>
                </div>
                <ul class="bh-entry-points bh-landing-points" aria-label="Beneficios principales">
                    <li>Dashboard mensual para registrar y leer tus datos reales.</li>
                    <li>Proyecciones separadas para probar hipótesis sin cambiar el mes.</li>
                    <li>Blog educativo para entender conceptos antes de aplicarlos.</li>
                </ul>
            </div>

            <div class="bh-landing-console" aria-label="Vista previa de BeneHom">
                <article class="bh-landing-panel is-real-data" aria-labelledby="landing-real-title">
                    <div class="bh-landing-panel-header">
                        <div>
                            <span>Datos reales</span>
                            <h2 id="landing-real-title">Resumen mensual</h2>
                        </div>
                        <strong>Junio</strong>
                    </div>
                    <div class="bh-landing-ledger">
                        <p><span>Ingresos</span><strong>2.450 €</strong></p>
                        <p><span>Gastos esenciales</span><strong>1.420 €</strong></p>
                        <p><span>Gastos flexibles</span><strong>445 €</strong></p>
                    </div>
                    <div class="bh-landing-balance">
                        <span>Ahorro real del mes</span>
                        <strong>585 €</strong>
                        <small>Resultado de ingresos menos todos los gastos registrados.</small>
                    </div>
                </article>

                <article class="bh-landing-panel is-projection" aria-labelledby="landing-projection-title">
                    <div class="bh-landing-panel-header">
                        <div>
                            <span>Hipótesis guardadas</span>
                            <h2 id="landing-projection-title">Proyecciones</h2>
                        </div>
                        <strong>Sin tocar datos</strong>
                    </div>
                    <div class="bh-landing-projection-list">
                        <p><span>Meta proyectada</span><strong>Aportar 180 €/mes</strong></p>
                        <p><span>Gasto flexible</span><strong>Probar reducción del 25%</strong></p>
                        <p><span>Hipoteca</span><strong>Estimar cuota mensual</strong></p>
                    </div>
                    <div class="bh-landing-note">
                        <span class="bh-insight-dot" aria-hidden="true"></span>
                        <p>Las proyecciones son orientativas y se guardan aparte del dashboard.</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="bh-entry-section bh-landing-system" aria-labelledby="entry-value-title">
            <div class="bh-entry-section-copy">
                <h2 id="entry-value-title">Primero entiende el mes. Después prueba posibilidades.</h2>
                <p>La herramienta está pensada para evitar mezclar realidad y deseo. El dashboard mide lo que ocurre en casa; Proyecciones permite explorar decisiones posibles con estimaciones separadas.</p>
            </div>
            <div class="bh-landing-flow" aria-label="Flujo principal de BeneHom">
                <article class="bh-entry-feature">
                    <span class="bh-entry-feature-icon" aria-hidden="true">1</span>
                    <h3>Registra lo que entra y sale</h3>
                    <p>Añade ingresos, gastos esenciales y gastos flexibles por mes para construir una foto clara del hogar.</p>
                </article>

                <article class="bh-entry-feature">
                    <span class="bh-entry-feature-icon is-saving" aria-hidden="true">2</span>
                    <h3>Compara ahorro posible y real</h3>
                    <p>Ve cuánto margen quedaría tras cubrir lo esencial y cuánto queda realmente al incluir decisiones flexibles.</p>
                </article>

                <article class="bh-entry-feature">
                    <span class="bh-entry-feature-icon is-learning" aria-hidden="true">3</span>
                    <h3>Observa la evolución</h3>
                    <p>Usa gráficos de ahorro y gastos para detectar cambios, meses atípicos o hábitos que se están moviendo.</p>
                </article>

                <article class="bh-entry-feature">
                    <span class="bh-entry-feature-icon is-flexible" aria-hidden="true">4</span>
                    <h3>Proyecta sin comprometerte</h3>
                    <p>Crea hipótesis de metas, reducción de gastos, inflación, inversión o hipoteca sin alterar el registro mensual.</p>
                </article>
            </div>
        </section>

        <section class="bh-landing-projections" aria-labelledby="entry-projections-title">
            <div class="bh-entry-section-copy">
                <p class="bh-entry-kicker">Proyecciones</p>
                <h2 id="entry-projections-title">Un laboratorio financiero para decisiones domésticas.</h2>
                <p>Las proyecciones no son promesas ni recomendaciones. Son una forma de poner números a preguntas habituales antes de mover dinero real.</p>
            </div>
            <div class="bh-landing-projection-map">
                <article>
                    <span>Meta de ahorro</span>
                    <h3>Calcula aportaciones o plazos estimados</h3>
                    <p>Crea una meta proyectada por aportación mensual o por fecha objetivo y revisa si cabe en tu capacidad disponible.</p>
                </article>
                <article>
                    <span>Gastos flexibles</span>
                    <h3>Prueba reducciones por categoría</h3>
                    <p>Selecciona una categoría flexible del mes y proyecta qué pasaría si redujeras una parte de ese gasto.</p>
                </article>
                <article>
                    <span>Inflación</span>
                    <h3>Mide pérdida de poder adquisitivo</h3>
                    <p>Comprueba cuánto podría cambiar el valor real de una cantidad con una inflación anual estimada.</p>
                </article>
                <article>
                    <span>Inversión educativa</span>
                    <h3>Entiende interés compuesto</h3>
                    <p>Guarda escenarios con capital inicial, aportación mensual, rentabilidad estimada y frecuencia de reinversión.</p>
                </article>
                <article>
                    <span>Hipoteca</span>
                    <h3>Estima cuota y coste total</h3>
                    <p>Calcula cuota mensual, intereses y total pagado según importe, plazo e interés anual.</p>
                </article>
            </div>
        </section>

        <section class="bh-blog-section" aria-labelledby="entry-blog-title">
            <div class="bh-entry-section-copy">
                <p class="bh-entry-kicker">Blog educativo</p>
                <h2 id="entry-blog-title">Aprende lo justo para interpretar mejor tus números.</h2>
                <p>Artículos breves sobre inflación, hipotecas, activos financieros y otros conceptos que aparecen cuando revisas tu economía o preparas una proyección.</p>
            </div>
            <div class="bh-blog-grid">
                <article class="bh-blog-card is-featured">
                    <span>Inflación</span>
                    <h3>Qué es la inflación y cómo afecta al presupuesto del hogar</h3>
                    <p>Aprende por qué suben los precios y cómo revisar tus gastos sin perder claridad mes a mes.</p>
                </article>
                <article class="bh-blog-card">
                    <span>Hipotecas</span>
                    <h3>Qué mirar antes de comprometer tu presupuesto</h3>
                    <p>La cuota importa, pero el plazo, el tipo de interés y el margen mensual también cuentan.</p>
                </article>
                <article class="bh-blog-card">
                    <span>Activos financieros</span>
                    <h3>Una explicación sencilla para empezar</h3>
                    <p>Renta fija, renta variable, fondos y liquidez explicados para entender riesgo y plazo.</p>
                </article>

            </div>
        </section>

        <section class="bh-goal-section bh-landing-proof" aria-labelledby="entry-goal-title">
            <div class="bh-goal-copy">
                <p class="bh-entry-kicker">Cómo se conectan</p>
                <h2 id="entry-goal-title">El dashboard da la referencia. Proyecciones hace las preguntas.</h2>
                <p>BeneHom usa el ahorro mensual del mes seleccionado como punto de partida para proyectar. También puedes editar esa cantidad dentro de Proyecciones para probar una hipótesis concreta sin cambiar ingresos ni gastos.</p>
            </div>
            <div class="bh-goal-card bh-landing-proof-card" aria-label="Ejemplo de conexión entre dashboard y proyecciones">
                <div class="bh-landing-proof-row">
                    <span>1</span>
                    <div>
                        <strong>Registras el mes</strong>
                        <p>Ingresos, esenciales, flexibles y balance quedan en el dashboard.</p>
                    </div>
                </div>
                <div class="bh-landing-proof-row">
                    <span>2</span>
                    <div>
                        <strong>Entras a Proyecciones</strong>
                        <p>La capacidad mensual disponible aparece como base editable.</p>
                    </div>
                </div>
                <div class="bh-landing-proof-row">
                    <span>3</span>
                    <div>
                        <strong>Guardas escenarios</strong>
                        <p>Las estimaciones quedan separadas de los movimientos reales.</p>
                    </div>
                </div>
                <div class="bh-goal-projection">
                    <span aria-hidden="true">Proy</span>
                    <p>Ideal para comparar antes de comprometer dinero, cambiar hábitos o asumir una cuota.</p>
                </div>
            </div>
        </section>

        <section class="bh-entry-band" aria-labelledby="entry-final-title">
            <div>
                <h2 id="entry-final-title">Empieza con tu próximo mes y proyecta cuando tengas una pregunta.</h2>
                <p>Registra lo básico, revisa el ahorro real y usa las proyecciones para explorar decisiones sin alterar tus datos.</p>
            </div>
            <div class="bh-entry-band-actions">
                <a class="bh-btn bh-btn-primary" href="<?= $primaryUrl ?>"><?= $primaryLabel ?></a>
                <?php if (!$usuarioLogueado): ?>
                    <a class="bh-btn bh-btn-secondary" href="<?= $loginUrl ?>">Iniciar sesión</a>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="bh-entry-footer">
        <span>BeneHom</span>
        <a href="<?= BASE_URL ?>index.php?r=legal/privacidad" target="_blank">Privacidad</a>
        <a href="<?= BASE_URL ?>index.php?r=legal/terminos" target="_blank">Términos</a>
    </footer>
</body>

</html>
