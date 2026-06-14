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
    <meta name="description" content="BeneHom es un cuaderno de cuentas para el hogar: registra ingresos y gastos, ve cuánto ahorras de verdad y prueba con números tus planes de ahorro, inversión o hipoteca antes de decidir.">
    <meta property="og:title" content="BeneHom | Las cuentas de casa, claras mes a mes">
    <meta property="og:description" content="Apunta lo que entra y lo que sale, compara tu ahorro posible con el real y guarda proyecciones de metas, inflación, inversión o hipoteca sin tocar tus datos del mes.">
    <meta property="og:url" content="https://benehom.es">
    <meta property="og:type" content="website">
    <meta property="og:image" content="https://benehom.es/img/og-image.png">
    <meta property="og:locale" content="es_ES">

    <title>BeneHom | Las cuentas de casa, claras mes a mes</title>

    <link rel="icon" type="image/png" href="<?= BASE_URL ?>img/og-image.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/custom.css">
</head>

<body class="bh-home-body">
    <a class="bh-skip-link" href="#contenido">Saltar al contenido</a>

    <div class="bh-home-top">
        <header class="bh-home-nav" aria-label="Navegación principal">
            <div class="bh-home-wrap">
                <a class="bh-home-brand" href="<?= BASE_URL ?>index.php" aria-label="BeneHom, ir al inicio">
                    <img src="<?= BASE_URL ?>img/logo-benehom.png" alt="BeneHom" width="120" height="80">
                </a>
                <nav aria-label="Secciones de la página">
                    <ul class="bh-home-nav-links">
                        <li><a href="#como-funciona">Cómo funciona</a></li>
                        <li><a href="#simuladores">Simuladores</a></li>
                        <li><a href="#blog">Blog</a></li>
                    </ul>
                </nav>
                <nav class="bh-home-nav-actions" aria-label="Acceso a la cuenta">
                    <?php if (!$usuarioLogueado): ?>
                        <a class="bh-btn bh-btn-ghost" href="<?= $loginUrl ?>">Iniciar sesión</a>
                        <a class="bh-btn bh-btn-primary" href="<?= $registerUrl ?>">Crear cuenta</a>
                    <?php else: ?>
                        <a class="bh-btn bh-btn-primary" href="<?= $panelUrl ?>">Ir al panel</a>
                    <?php endif; ?>
                </nav>
            </div>
        </header>

        <section class="bh-home-hero" id="contenido" aria-labelledby="hero-title">
            <div class="bh-home-wrap">
                <div class="bh-home-hero-copy">
                    <h1 id="hero-title">Las cuentas de casa, claras mes a mes.</h1>
                    <p class="bh-home-lead">BeneHom es un cuaderno de cuentas para el hogar: apuntas lo que entra y lo que sale, ves cuánto ahorras de verdad y, antes de tomar una decisión con tu dinero, la pruebas con números en una proyección.</p>
                    <div class="bh-home-cta">
                        <a class="bh-btn bh-btn-primary" href="<?= $primaryUrl ?>"><?= $primaryLabel ?></a>
                        <?php if (!$usuarioLogueado): ?>
                            <a class="bh-btn bh-btn-ghost" href="<?= $loginUrl ?>">Ya tengo cuenta</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bh-home-ledger" aria-label="Ejemplo del resumen mensual de BeneHom">
                    <article class="bh-home-ledger-card">
                        <header class="bh-home-ledger-head">
                            <h2>Resumen del mes</h2>
                            <span>Junio</span>
                        </header>
                        <dl class="bh-home-ledger-rows">
                            <div>
                                <dt class="is-income">Ingresos</dt>
                                <dd>2.450&nbsp;€</dd>
                            </div>
                            <div>
                                <dt class="is-expense">Gastos esenciales</dt>
                                <dd>1.420&nbsp;€</dd>
                            </div>
                            <div>
                                <dt class="is-expense">Gastos flexibles</dt>
                                <dd>445&nbsp;€</dd>
                            </div>
                        </dl>
                        <div class="bh-home-ledger-total">
                            <span>Ahorro real del mes</span>
                            <strong>585&nbsp;€</strong>
                            <small>Un 24&nbsp;% de lo que ha entrado.</small>
                        </div>
                    </article>
                    <aside class="bh-home-slip" aria-label="Ejemplo de proyección guardada">
                        <span class="bh-home-slip-tag">Proyección guardada</span>
                        <p class="bh-home-slip-question">¿Y si aparto 150&nbsp;€ al mes?</p>
                        <p class="bh-home-slip-result">La meta de 3.600&nbsp;€ se cumpliría en 24 meses.</p>
                    </aside>
                </div>

                <ul class="bh-home-claims" aria-label="Qué hace BeneHom">
                    <li>Gastos esenciales y flexibles, separados desde el primer apunte.</li>
                    <li>Ahorro posible frente a ahorro real, comparados cada mes.</li>
                    <li>Proyecciones de metas, inversión, hipoteca e inflación, guardadas aparte.</li>
                </ul>
            </div>
        </section>
    </div>

    <main>
        <section class="bh-home-method" id="como-funciona" aria-labelledby="method-title">
            <div class="bh-home-wrap">
                <div class="bh-home-section-head">
                    <h2 id="method-title">Cómo funciona</h2>
                    <p>La idea es sencilla: registrar cada mes tal y como ha sido, sin maquillarlo, y dejar que los números cuenten el resto. Lo que ya ha pasado y lo que te gustaría probar viven en espacios distintos.</p>
                </div>
                <ol class="bh-home-steps">
                    <li>
                        <span aria-hidden="true">1</span>
                        <h3>Apunta el mes</h3>
                        <p>Registra tus ingresos y tus gastos, separando lo esencial (vivienda, comida, suministros) de lo flexible (ocio, compras, extras).</p>
                    </li>
                    <li>
                        <span aria-hidden="true">2</span>
                        <h3>Lee tus números</h3>
                        <p>El panel compara cuánto podrías ahorrar con cuánto ahorras en realidad, y los gráficos muestran cómo evoluciona esa diferencia.</p>
                    </li>
                    <li>
                        <span aria-hidden="true">3</span>
                        <h3>Proyecta aparte</h3>
                        <p>Si una decisión te ronda la cabeza, ponle números en una proyección. Se guarda en su propio espacio y no toca nada de lo que has registrado.</p>
                    </li>
                </ol>
            </div>
        </section>

        <section class="bh-home-simulators" id="simuladores" aria-labelledby="simulators-title">
            <div class="bh-home-wrap">
                <div class="bh-home-simulators-copy">
                    <h2 id="simulators-title">Un simulador para cada pregunta</h2>
                    <p>Cada proyección parte de tu capacidad de ahorro mensual y devuelve una estimación orientativa. No es asesoramiento financiero: es una forma de ver los números con calma antes de mover dinero de verdad.</p>
                </div>
                <div class="bh-home-sim-list">
                    <article>
                        <span>Meta de ahorro</span>
                        <h3>¿Cuánto tendría que apartar cada mes para reunir 6.000&nbsp;€?</h3>
                        <p>Define la meta por aportación mensual o por fecha objetivo y comprueba si encaja en tu margen real.</p>
                    </article>
                    <article>
                        <span>Gastos flexibles</span>
                        <h3>¿Qué pasaría si recortara una parte de lo que gasto en ocio?</h3>
                        <p>Elige una categoría de tu propio mes y proyecta el recorte sobre datos reales, no sobre suposiciones.</p>
                    </article>
                    <article>
                        <span>Inflación</span>
                        <h3>¿Qué valor tendrán mis ahorros dentro de cinco años?</h3>
                        <p>Estima la pérdida de poder adquisitivo de una cantidad con la inflación anual que tú decidas.</p>
                    </article>
                    <article>
                        <span>Inversión</span>
                        <h3>¿Hasta dónde llegaría una aportación mensual con interés compuesto?</h3>
                        <p>Combina capital inicial, aportación, plazo y rentabilidad estimada para ver cómo crece el dinero con el tiempo.</p>
                    </article>
                    <article>
                        <span>Hipoteca</span>
                        <h3>¿Qué cuota pagaría y cuánto costaría el préstamo en total?</h3>
                        <p>Introduce importe, plazo e interés y compara la cuota resultante con el ahorro real de tu mes.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="bh-home-blog" id="blog" aria-labelledby="blog-title">
            <div class="bh-home-wrap">
                <div class="bh-home-section-head">
                    <h2 id="blog-title">Para entender lo que estás mirando</h2>
                    <p>El blog explica los conceptos que aparecen al revisar tus cuentas o al preparar una proyección. Artículos breves, sin tecnicismos y con ejemplos de andar por casa.</p>
                </div>
                <div class="bh-home-blog-grid">
                    <article class="bh-home-blog-card">
                        <span>Inflación</span>
                        <h3>Por qué sube todo y cómo afecta a tu presupuesto</h3>
                        <p>Una explicación con ejemplos domésticos, lejos de la jerga económica.</p>
                    </article>
                    <article class="bh-home-blog-card">
                        <span>Hipotecas</span>
                        <h3>Lo que conviene mirar antes de firmar</h3>
                        <p>La cuota es solo una parte: el plazo y el interés deciden el coste final.</p>
                    </article>
                    <article class="bh-home-blog-card">
                        <span>Activos financieros</span>
                        <h3>Renta fija, variable y fondos, en lenguaje llano</h3>
                        <p>Qué es cada cosa y qué papel juegan el riesgo y el plazo.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="bh-home-band" aria-labelledby="band-title">
            <div class="bh-home-wrap">
                <div class="bh-home-band-inner">
                    <div>
                        <h2 id="band-title">Empieza por este mes</h2>
                        <p>Crear una cuenta lleva un minuto. Apunta los primeros movimientos y, cuando el mes termine, sabrás exactamente dónde ha ido tu dinero.</p>
                    </div>
                    <div class="bh-home-band-actions">
                        <a class="bh-btn bh-btn-primary" href="<?= $primaryUrl ?>"><?= $primaryLabel ?></a>
                        <?php if (!$usuarioLogueado): ?>
                            <a class="bh-btn bh-btn-secondary" href="<?= $loginUrl ?>">Iniciar sesión</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="bh-home-footer">
        <div class="bh-home-wrap">
            <span>BeneHom</span>
            <a href="<?= BASE_URL ?>index.php?r=legal/privacidad" target="_blank" rel="noopener">Privacidad</a>
            <a href="<?= BASE_URL ?>index.php?r=legal/terminos" target="_blank" rel="noopener">Términos</a>
        </div>
    </footer>
</body>

</html>
