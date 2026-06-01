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
    <meta name="description" content="BeneHom combina control financiero, educación sencilla, ahorro real, simulación de decisiones y metas económicas para el hogar.">
    <meta property="og:title" content="BeneHom | Educación financiera para el hogar">
    <meta property="og:description" content="Entiende tu dinero, mejora tus hábitos y avanza hacia tus metas con decisiones más claras.">
    <meta property="og:url" content="https://benehom.es">
    <meta property="og:type" content="website">
    <meta property="og:image" content="https://benehom.es/img/og-image.png">
    <meta property="og:locale" content="es_ES">

    <title>BeneHom | Educación financiera para el hogar</title>

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
        <section class="bh-entry-hero" aria-labelledby="entry-title">
            <div class="bh-entry-copy">
                <p class="bh-entry-kicker">Educación financiera para el hogar</p>
                <h1 id="entry-title">Entiende tu dinero, mejora tus hábitos y alcanza tus metas.</h1>
                <p class="bh-entry-lead">BeneHom te ayuda a registrar ingresos, separar gastos esenciales y flexibles, conocer tu ahorro real y ver cómo tus decisiones impactan en la economía de tu hogar.</p>
                <ul class="bh-entry-points" aria-label="Beneficios principales">
                    <li>Identifica a dónde va realmente tu dinero.</li>
                    <li>Distingue entre gastos esenciales y gastos flexibles.</li>
                    <li>Mide cómo pequeños cambios pueden acercarte a tus metas.</li>
                </ul>
            </div>

            <div class="bh-dashboard-mockup" aria-label="Vista previa de dashboard BeneHom">
                <div class="bh-mockup-topbar">
                    <div>
                        <span>Resumen mensual</span>
                        <strong>Junio</strong>
                    </div>
                    <span class="bh-mockup-pill">Meta activa</span>
                </div>

                <div class="bh-mockup-grid">
                    <article class="bh-mockup-stat is-income">
                        <span>Ingresos del mes</span>
                        <strong>2.450 €</strong>
                    </article>
                    <article class="bh-mockup-stat is-expense">
                        <span>Gastos esenciales</span>
                        <strong>1.420 €</strong>
                    </article>
                    <article class="bh-mockup-stat is-flexible">
                        <span>Gastos flexibles</span>
                        <strong>445 €</strong>
                    </article>
                    <article class="bh-mockup-stat is-saving">
                        <span>Ahorro posible</span>
                        <strong>585 €</strong>
                    </article>
                    <article class="bh-mockup-stat is-real">
                        <span>Ahorro real</span>
                        <strong>320 €</strong>
                    </article>
                    <article class="bh-mockup-stat is-goal">
                        <span>Meta activa</span>
                        <strong>Fondo de emergencia</strong>
                    </article>
                </div>

                <div class="bh-mockup-insight">
                    <span class="bh-insight-dot" aria-hidden="true"></span>
                    <p>Reduciendo 80 € en gastos flexibles, podrías adelantar tu meta 2 meses.</p>
                </div>

                <div class="bh-mockup-progress" aria-label="Progreso de meta Fondo de emergencia">
                    <div>
                        <span>Progreso de meta</span>
                        <strong>850 € / 3.000 €</strong>
                    </div>
                    <div class="bh-progress-track"><span style="width: 28%;"></span></div>
                    <small>Tu ahorro real actual te permitiría alcanzar esta meta en 7 meses.</small>
                </div>
            </div>
        </section>

        <section class="bh-entry-section" aria-labelledby="entry-value-title">
            <div class="bh-entry-section-copy">
                <h2 id="entry-value-title">Una herramienta para entender, aprender y mejorar.</h2>
                <p>BeneHom combina seguimiento financiero, educación financiera y planificación de metas para ayudarte a tomar mejores decisiones en casa.</p>
            </div>
            <div class="bh-entry-feature-grid is-four">
                <article class="bh-entry-feature">
                    <span class="bh-entry-feature-icon" aria-hidden="true">€</span>
                    <h3>Control claro del mes</h3>
                    <p>Registra ingresos y gastos para saber cuánto entra, cuánto sale y cómo se reparte tu dinero cada mes.</p>
                </article>

                <article class="bh-entry-feature">
                    <span class="bh-entry-feature-icon is-learning" aria-hidden="true">i</span>
                    <h3>Educación financiera</h3>
                    <p>Entiende conceptos básicos sobre el dinero para tomar mejores decisiones y evitar errores que salen caros.</p>
                </article>

                <article class="bh-entry-feature">
                    <span class="bh-entry-feature-icon is-saving" aria-hidden="true">%</span>
                    <h3>Metas con datos reales</h3>
                    <p>Define una meta económica y calcula cuánto podrías tardar en alcanzarla según tu ahorro real.</p>
                </article>

                <article class="bh-entry-feature">
                    <span class="bh-entry-feature-icon is-flexible" aria-hidden="true">+</span>
                    <h3>Simula antes de decidir</h3>
                    <p>Visualiza cómo ajustar ciertos gastos puede mejorar tu ahorro mensual y acercarte antes a tus objetivos.</p>
                </article>
            </div>
        </section>

        <section class="bh-blog-section" aria-labelledby="entry-blog-title">
            <div class="bh-entry-section-copy">
                <p class="bh-entry-kicker">Blog educativo</p>
                <h2 id="entry-blog-title">Aprende finanzas sin complicarte.</h2>
                <p>Publicaciones breves y prácticas para entender conceptos económicos importantes y aplicarlos a la economía del hogar.</p>
            </div>
            <div class="bh-blog-grid">
                <article class="bh-blog-card is-featured">
                    <span>Inflación</span>
                    <h3>¿Qué es la inflación y cómo afecta a tu dinero?</h3>
                    <p>Aprende por qué suben los precios y cómo ajustar tu presupuesto familiar sin perder claridad.</p>
                </article>
                <article class="bh-blog-card">
                    <span>Inversión</span>
                    <h3>¿Por qué es importante invertir?</h3>
                    <p>Una introducción sencilla para entender cómo proteger y hacer crecer tu dinero a largo plazo.</p>
                </article>
                <article class="bh-blog-card">
                    <span>Conceptos básicos</span>
                    <h3>Renta fija y renta variable explicadas de forma sencilla.</h3>
                    <p>Diferencias clave, riesgos y usos cotidianos antes de tomar decisiones de inversión.</p>
                </article>

            </div>
        </section>

        <section class="bh-goal-section" aria-labelledby="entry-goal-title">
            <div class="bh-goal-copy">
                <p class="bh-entry-kicker">Metas</p>
                <h2 id="entry-goal-title">Convierte tus objetivos en un plan medible.</h2>
                <p>Establece una meta, indica tus ahorros actuales y BeneHom te ayuda a estimar cuánto tiempo podrías tardar en alcanzarla según tu ahorro real.</p>
            </div>
            <div class="bh-goal-card" aria-label="Ejemplo de meta económica">
                <div class="bh-goal-header">
                    <span>Meta activa</span>
                    <strong>Fondo de emergencia</strong>
                </div>
                <dl class="bh-goal-list">
                    <div>
                        <dt>Objetivo</dt>
                        <dd>3.000 €</dd>
                    </div>
                    <div>
                        <dt>Ahorro actual</dt>
                        <dd>850 €</dd>
                    </div>
                    <div>
                        <dt>Ahorro real mensual</dt>
                        <dd>320 €</dd>
                    </div>
                    <div>
                        <dt>Tiempo estimado</dt>
                        <dd>7 meses</dd>
                    </div>
                </dl>
                <div class="bh-goal-simulation">
                    <span aria-hidden="true">+50 €</span>
                    <p>Si aumentas tu ahorro en 50 €/mes, podrías alcanzarla antes.</p>
                </div>
            </div>
        </section>

        <section class="bh-entry-band" aria-labelledby="entry-final-title">
            <div>
                <h2 id="entry-final-title">Empieza a mejorar tu economía con decisiones más claras.</h2>
                <p>Registra tus movimientos, aprende conceptos financieros y descubre cómo pequeños cambios pueden ayudarte a alcanzar tus metas.</p>
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