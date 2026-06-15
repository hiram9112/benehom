<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="BeneHom te ayuda a mirar tu economía con perspectiva: entender tus gastos, descubrir tu margen real y comprobar con números cómo cada decisión te acerca o te aleja de tus objetivos.">
    <meta property="og:title" content="BeneHom | Tu dinero cuenta una historia">
    <meta property="og:description" content="BeneHom te ayuda a mirar tu economía con perspectiva: entender tus gastos, descubrir tu margen real y comprobar cómo cada decisión puede abrir o cerrar camino hacia tus objetivos.">
    <meta property="og:url" content="https://benehom.es">
    <meta property="og:type" content="website">
    <meta property="og:image" content="https://benehom.es/img/og-image.png">
    <meta property="og:locale" content="es_ES">

    <title>BeneHom | Tu dinero cuenta una historia</title>

    <link rel="icon" type="image/png" href="<?= BASE_URL ?>img/og-image.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/custom.css">
    <script>
        (function() {
            var d = document.documentElement;
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            d.classList.add('bh-js');
            // Red de seguridad: si home.js falla, revela todo el contenido igualmente.
            window.setTimeout(function() {
                var els = document.querySelectorAll('.bh-reveal');
                for (var i = 0; i < els.length; i++) {
                    els[i].classList.add('is-in');
                }
            }, 4000);
        })();
    </script>
</head>

<body class="bh-home-body">
    <a class="bh-skip-link" href="#contenido">Saltar al contenido</a>

    <header class="bh-home-nav" aria-label="Navegación principal">
        <div class="bh-home-wrap">
            <a class="bh-home-brand" href="<?= BASE_URL ?>index.php" aria-label="BeneHom, ir al inicio">
                <img src="<?= BASE_URL ?>img/logo-benehom.png" alt="BeneHom" width="120" height="80">
            </a>

            <nav class="bh-home-nav-links" aria-label="Secciones de la página">
                <a href="#como-funciona">Cómo funciona</a>
                <a href="#funciones">Funciones</a>
                <a href="#blog">Blog</a>
                <a href="#faq">Preguntas frecuentes</a>
            </nav>

            <div class="bh-home-nav-actions">
                <a class="bh-btn bh-btn-ghost" href="<?= BASE_URL ?>index.php?r=auth/login">Iniciar sesión</a>
                <a class="bh-btn bh-btn-primary" href="<?= BASE_URL ?>index.php?r=registro/registrarUsuario">Crear cuenta</a>
            </div>

            <button class="bh-home-burger" type="button" aria-expanded="false" aria-controls="bh-home-mobile" aria-label="Abrir menú">
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
            </button>
        </div>

        <div class="bh-home-mobile" id="bh-home-mobile" hidden>
            <nav aria-label="Secciones de la página">
                <a href="#como-funciona">Cómo funciona</a>
                <a href="#funciones">Funciones</a>
                <a href="#blog">Blog</a>
                <a href="#faq">Preguntas frecuentes</a>
            </nav>
            <div class="bh-home-mobile-actions">
                <a class="bh-btn bh-btn-secondary" href="<?= BASE_URL ?>index.php?r=auth/login">Iniciar sesión</a>
                <a class="bh-btn bh-btn-primary" href="<?= BASE_URL ?>index.php?r=registro/registrarUsuario">Crear cuenta</a>
            </div>
        </div>
    </header>

    <main id="contenido">
        <!-- ============================ Hero ============================ -->
        <section class="bh-home-hero" aria-labelledby="hero-title">
            <div class="bh-home-wrap bh-home-hero-grid">
                <div class="bh-home-hero-copy">
                    <h1 id="hero-title">Tu dinero cuenta una historia. BeneHom te ayuda a leerla.</h1>
                    <p class="bh-home-lead">BeneHom te ayuda a mirar tu economía con perspectiva: entender tus gastos, descubrir tu margen real y comprobar cómo cada decisión puede abrir o cerrar camino hacia tus objetivos.</p>
                    <div class="bh-home-cta">
                        <a class="bh-btn bh-btn-primary" href="<?= BASE_URL ?>index.php?r=registro/registrarUsuario">Crear cuenta gratis</a>
                        <a class="bh-btn bh-btn-ghost" href="#como-funciona">Ver cómo funciona</a>
                    </div>
                    <p class="bh-home-cta-note">Gratis y sin tarjeta. Sin conectar tu banco ni instalar nada.</p>
                </div>

                <div class="bh-home-hero-art bh-reveal">
                    <div class="bh-home-mock bh-home-mock-ledger" role="img" aria-label="Ejemplo del resumen mensual de BeneHom: 2.450 euros de ingresos, 1.420 de gastos esenciales, 445 flexibles y un ahorro real de 585 euros, el 24 por ciento de lo que ha entrado">
                        <div class="bh-home-mock-head">
                            <h2>Resumen del mes</h2>
                            <span>Junio</span>
                        </div>
                        <ul class="bh-home-mock-rows">
                            <li>
                                <div class="bh-home-mock-row-head">
                                    <span>Ingresos</span>
                                    <strong>2.450&nbsp;€</strong>
                                </div>
                                <div class="bh-home-mock-bar"><span class="is-income" style="--w:100%"></span></div>
                            </li>
                            <li>
                                <div class="bh-home-mock-row-head">
                                    <span>Gastos esenciales</span>
                                    <strong>1.420&nbsp;€</strong>
                                </div>
                                <div class="bh-home-mock-bar"><span class="is-essential" style="--w:58%"></span></div>
                            </li>
                            <li>
                                <div class="bh-home-mock-row-head">
                                    <span>Gastos flexibles</span>
                                    <strong>445&nbsp;€</strong>
                                </div>
                                <div class="bh-home-mock-bar"><span class="is-flexible" style="--w:18%"></span></div>
                            </li>
                        </ul>
                        <div class="bh-home-mock-total">
                            <span>Ahorro real del mes</span>
                            <strong>585&nbsp;€</strong>
                            <small>Un 24&nbsp;% de lo que ha entrado.</small>
                        </div>
                    </div>

                    <div class="bh-home-mock-slip" aria-hidden="true">
                        <span class="bh-home-mock-slip-tag">Proyección guardada</span>
                        <p class="bh-home-mock-slip-q">¿Y si aparto 150&nbsp;€ al mes?</p>
                        <p class="bh-home-mock-slip-a">La meta de 3.600&nbsp;€ se cumpliría en 24 meses.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================== Señales de confianza ===================== -->
        <section class="bh-home-trust" aria-label="Por qué puedes confiar en BeneHom">
            <div class="bh-home-wrap">
                <ul class="bh-home-trust-list">
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="4" y="10" width="16" height="10" rx="2" />
                            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                        </svg>
                        <span>Contraseñas cifradas</span>
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 3l8 3v5c0 4.5-3 7.5-8 10-5-2.5-8-5.5-8-10V6z" />
                            <path d="M9 12l2 2 4-4" />
                        </svg>
                        <span>No vendemos tus datos</span>
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 7h14" />
                            <path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" />
                            <path d="M7 7l1 12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2l1-12" />
                        </svg>
                        <span>Borra tu cuenta cuando quieras</span>
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="4" width="8" height="16" rx="1.5" />
                            <rect x="13" y="4" width="8" height="16" rx="1.5" />
                        </svg>
                        <span>Apuntes y proyecciones, separados</span>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ========================= Beneficios ========================= -->
        <section class="bh-home-benefits" aria-labelledby="benefits-title">
            <div class="bh-home-wrap">
                <div class="bh-home-section-head">
                    <h2 id="benefits-title">Para qué te sirve, en claro</h2>
                    <p>No es una app de banca ni una hoja de cálculo. Es una forma sencilla y práctica de entender tu dinero y de comprender el impacto tus decisiones antes de tomarlas.</p>
                </div>
                <div class="bh-home-benefits-grid">
                    <article class="bh-home-benefit bh-reveal">
                        <span class="bh-home-benefit-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 14h4l2-7 3 14 2-9 2 4h3" />
                            </svg>
                        </span>
                        <h3>Sabes cuánto ahorras de verdad</h3>
                        <p>BeneHom compara lo que podrías ahorrar con lo que ahorras en realidad. La diferencia deja de ser una sensación y pasa a ser un número.</p>
                    </article>
                    <article class="bh-home-benefit bh-reveal">
                        <span class="bh-home-benefit-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 4v16" />
                                <path d="M6 7l-3 6h6z" />
                                <path d="M18 7l-3 6h6z" />
                                <path d="M7 20h10" />
                            </svg>
                        </span>
                        <h3>Controlas el gasto sin culpa</h3>
                        <p>Separas lo esencial de lo flexible desde el primer apunte. Ves dónde podrías ajustar sin renunciar a lo que de verdad te importa.</p>
                    </article>
                    <article class="bh-home-benefit bh-reveal">
                        <span class="bh-home-benefit-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="8" />
                                <path d="M12 8v4l2.5 2.5" />
                            </svg>
                        </span>
                        <h3>Pruebas decisiones con números</h3>
                        <p>Una meta de ahorro, una inversión, una hipoteca: ponle cifras en una proyección y mira el resultado antes de mover el dinero.</p>
                    </article>
                    <article class="bh-home-benefit bh-reveal">
                        <span class="bh-home-benefit-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 5a2 2 0 0 1 2-2h7v16H6a2 2 0 0 0-2 2z" />
                                <path d="M20 5a2 2 0 0 0-2-2h-5v16h5a2 2 0 0 1 2 2z" />
                            </svg>
                        </span>
                        <h3>Entiendes tus finanzas</h3>
                        <p>Aprende conceptos financieros indispensables explicados de forma sencilla, con ejemplos cercanos y sin jerga innecesaria.</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- ======================= Cómo funciona ======================= -->
        <section class="bh-home-method" id="como-funciona" aria-labelledby="method-title">
            <div class="bh-home-wrap">
                <div class="bh-home-section-head">
                    <h2 id="method-title">Cómo funciona</h2>
                    <p>Registra cada mes tal y como ha sido, sin maquillarlo, y deja que los números cuenten el resto. Lo que ya ha pasado y lo que te gustaría probar viven en espacios distintos.</p>
                </div>
                <ol class="bh-home-steps">
                    <li class="bh-reveal">
                        <span aria-hidden="true">1</span>
                        <h3>Apunta el mes</h3>
                        <p>Registra tus ingresos y tus gastos, separando lo esencial (vivienda, comida, suministros) de lo flexible (ocio, compras, extras).</p>
                    </li>
                    <li class="bh-reveal">
                        <span aria-hidden="true">2</span>
                        <h3>Lee tus números</h3>
                        <p>El panel compara cuánto podrías ahorrar con cuánto ahorras de verdad, y los gráficos muestran cómo evoluciona esa diferencia.</p>
                    </li>
                    <li class="bh-reveal">
                        <span aria-hidden="true">3</span>
                        <h3>Proyecta aparte</h3>
                        <p>Si una decisión te ronda la cabeza, ponle números en una proyección. Se guarda en su propio espacio y no toca nada de lo registrado.</p>
                    </li>
                </ol>
            </div>
        </section>

        <!-- ==================== Funcionalidades ===================== -->
        <section class="bh-home-features" id="funciones" aria-labelledby="features-title">
            <div class="bh-home-wrap">
                <div class="bh-home-section-head">
                    <h2 id="features-title">Todo lo que necesitas, sin ruido</h2>
                    <p>Herramientas que trabajan juntas: registrar, comparar, proyectar y ver la evolución. Cada una integrada en el mismo sitio.</p>
                </div>

                <!-- Feature 1: registro -->
                <article class="bh-home-feature bh-reveal">
                    <div class="bh-home-feature-text">
                        <span class="bh-home-feature-tag">Registro</span>
                        <h3>Apunta gastos esenciales y flexibles</h3>
                        <p>Cada movimiento queda clasificado desde el principio. Lo esencial sostiene tu mes; lo flexible es donde de verdad tienes margen para decidir.</p>
                        <ul class="bh-home-feature-points">
                            <li>Ingresos y gastos sin límite de apuntes.</li>
                            <li>Categorías propias para tu hogar.</li>
                            <li>El total del mes, siempre a la vista.</li>
                        </ul>
                    </div>
                    <div class="bh-home-feature-art">
                        <div class="bh-home-mock bh-home-mock-ledger">
                            <div class="bh-home-mock-head">
                                <h4>Movimientos de junio</h4>

                            </div>
                            <ul class="bh-home-mock-rows">
                                <li>
                                    <div class="bh-home-mock-row-head"><span>Nómina</span><strong class="is-pos">+2.450&nbsp;€</strong></div>
                                    <div class="bh-home-mock-bar"><span class="is-income" style="--w:100%"></span></div>
                                </li>
                                <li>
                                    <div class="bh-home-mock-row-head"><span>Alquiler · esencial</span><strong>−850&nbsp;€</strong></div>
                                    <div class="bh-home-mock-bar"><span class="is-essential" style="--w:35%"></span></div>
                                </li>
                                <li>
                                    <div class="bh-home-mock-row-head"><span>Compra · esencial</span><strong>−420&nbsp;€</strong></div>
                                    <div class="bh-home-mock-bar"><span class="is-essential" style="--w:17%"></span></div>
                                </li>
                                <li>
                                    <div class="bh-home-mock-row-head"><span>Cena fuera · flexible</span><strong>−54&nbsp;€</strong></div>
                                    <div class="bh-home-mock-bar"><span class="is-flexible" style="--w:9%"></span></div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </article>

                <!-- Feature 2: ahorro real vs posible -->
                <article class="bh-home-feature bh-reveal">
                    <div class="bh-home-feature-text">
                        <span class="bh-home-feature-tag">Ahorro</span>
                        <h3>Ahorro posible y ahorro real</h3>
                        <p>El ahorro posible es un dato teórico: lo que te quedaría si solo cubrieras lo imprescindible. Marca el punto a partir del cual cada euro que gastas es una decisión de consumo. El ahorro real es lo que de verdad queda al final del mes; la diferencia entre ambos no te culpa, te muestra tu margen de mejora.</p>
                        <ul class="bh-home-feature-points">
                            <li>El ahorro posible: lo que queda tras lo imprescindible.</li>
                            <li>La diferencia con el real: tus decisiones de consumo.</li>
                            <li>Sin culpa: tu margen de mejora, mes a mes.</li>
                        </ul>
                    </div>
                    <div class="bh-home-feature-art">
                        <div class="bh-home-mock">
                            <div class="bh-home-mock-compare-bars">
                                <div class="bh-home-mock-cmp">
                                    <div class="bh-home-mock-row-head"><span>Ahorro posible</span><strong>750&nbsp;€</strong></div>
                                    <div class="bh-home-mock-bar"><span class="is-possible" style="--w:100%"></span></div>
                                </div>
                                <div class="bh-home-mock-cmp">
                                    <div class="bh-home-mock-row-head"><span>Ahorro real</span><strong>585&nbsp;€</strong></div>
                                    <div class="bh-home-mock-bar"><span class="is-real" style="--w:78%"></span></div>
                                </div>
                            </div>
                            <div class="bh-home-mock-total">
                                <span>Decisiones de consumo</span>
                                <strong>165&nbsp;€</strong>
                                <small>Tu margen de mejora este mes.</small>
                            </div>
                        </div>
                    </div>
                </article>

                <!-- Feature 3: proyecciones -->
                <article class="bh-home-feature bh-reveal">
                    <div class="bh-home-feature-text">
                        <span class="bh-home-feature-tag">Proyecciones</span>
                        <h3>Un simulador para cada pregunta</h3>
                        <p>Cada proyección parte de tu capacidad de ahorro real y devuelve una estimación orientativa. No es asesoramiento financiero: es ver los números con calma antes de mover dinero de verdad.</p>
                        <ul class="bh-home-feature-points">
                            <li>Metas de ahorro por importe o por fecha.</li>
                            <li>Inflación, interés compuesto e hipoteca.</li>
                            <li>Guardadas aparte, sin tocar tus datos reales.</li>
                        </ul>
                    </div>
                    <div class="bh-home-feature-art">
                        <div class="bh-home-mock bh-home-mock-sim">
                            <span class="bh-home-mock-slip-tag">Meta de ahorro</span>
                            <p class="bh-home-mock-slip-q">Reunir 6.000&nbsp;€ apartando 200&nbsp;€ al mes.</p>
                            <div class="bh-home-mock-sim-result">
                                <div><span>Tiempo estimado</span><strong>30 meses</strong></div>
                                <div><span>Encaje en tu mes</span><strong class="is-pos">Sí, cabe</strong></div>
                            </div>
                            <div class="bh-home-mock-chips">
                                <span>Meta</span>
                                <span>Inflación</span>
                                <span>Inversión</span>
                                <span>Hipoteca</span>
                            </div>
                        </div>
                    </div>
                </article>

                <!-- Feature 4: gráficos -->
                <article class="bh-home-feature bh-reveal">
                    <div class="bh-home-feature-text">
                        <span class="bh-home-feature-tag">Gráficos</span>
                        <h3>Tu dinero, desde varios ángulos</h3>
                        <p>BeneHom convierte tus apuntes en varios gráficos, y cada uno te muestra algo distinto: cómo ha cerrado el mes, si tu ritmo de ahorro se sostiene, cómo evolucionan tus gastos y qué peso real tienen tus hábitos. Todos se leen rápido y comparten el mismo sistema visual.</p>
                        <ul class="bh-home-feature-points">
                            <li>Presupuesto del mes: ingresos, gastos y ahorro.</li>
                            <li>Ahorro posible frente al real, mes a mes.</li>
                            <li>Gastos esenciales y flexibles a lo largo del tiempo.</li>
                            <li>Tus mayores hábitos, en media mensual o a un año.</li>
                        </ul>
                    </div>
                    <div class="bh-home-feature-art">
                        <div class="bh-home-mock bh-home-mock-chart">
                            <div class="bh-home-mock-head">
                                <h4>Tus gráficos</h4>
                                <span>El panel</span>
                            </div>

                            <div class="bh-home-mock-chartlet">
                                <span class="bh-home-mock-chartlet-label">Ahorro, mes a mes</span>
                                <svg class="bh-home-chart-svg" viewBox="0 0 320 110" role="img" aria-label="Gráfico de barras con el ahorro de los últimos seis meses, en aumento">
                                    <line x1="10" y1="96" x2="310" y2="96" stroke="rgba(22,63,127,0.14)" stroke-width="1" />
                                    <rect x="10" y="66" width="30" height="30" rx="4" fill="#3EB225" fill-opacity="0.45" />
                                    <rect x="64" y="56" width="30" height="40" rx="4" fill="#3EB225" fill-opacity="0.45" />
                                    <rect x="118" y="60" width="30" height="36" rx="4" fill="#3EB225" fill-opacity="0.55" />
                                    <rect x="172" y="44" width="30" height="52" rx="4" fill="#3EB225" fill-opacity="0.7" />
                                    <rect x="226" y="34" width="30" height="62" rx="4" fill="#3EB225" fill-opacity="0.85" />
                                    <rect x="280" y="22" width="30" height="74" rx="4" fill="#3EB225" />
                                </svg>
                            </div>

                            <div class="bh-home-mock-chartlet">
                                <span class="bh-home-mock-chartlet-label">Gastos, mes a mes</span>
                                <svg class="bh-home-chart-svg" viewBox="0 0 320 110" role="img" aria-label="Gráfico de líneas con la evolución de los gastos flexibles mes a mes">
                                    <defs>
                                        <linearGradient id="bhAreaGastos" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#B83E3E" stop-opacity="0.22" />
                                            <stop offset="100%" stop-color="#B83E3E" stop-opacity="0" />
                                        </linearGradient>
                                    </defs>
                                    <line x1="10" y1="96" x2="310" y2="96" stroke="rgba(22,63,127,0.14)" stroke-width="1" />
                                    <path d="M10,60 L70,50 L130,64 L190,48 L250,56 L310,46 L310,96 L10,96 Z" fill="url(#bhAreaGastos)" />
                                    <path d="M10,60 L70,50 L130,64 L190,48 L250,56 L310,46" fill="none" stroke="#B83E3E" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                    <circle cx="310" cy="46" r="4" fill="#B83E3E" />
                                </svg>
                            </div>

                            <div class="bh-home-mock-chips">
                                <span>Presupuesto</span>
                                <span>Ahorro</span>
                                <span>Gastos</span>
                                <span>Hábitos</span>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <!-- ========================= Recursos / Blog ========================= -->
        <section class="bh-home-blog" id="blog" aria-labelledby="blog-title">
            <div class="bh-home-wrap">
                <div class="bh-home-section-head">
                    <h2 id="blog-title">Lo que no entiendes del dinero también decide por ti</h2>
                    <p>Inflación, deuda, hipotecas, ahorro o inversión pueden afectar tu calidad de vida aunque no les prestes atención. BeneHom te ayuda a entender esos conceptos con explicaciones claras y ejemplos sencillos.</p>
                </div>
                <div class="bh-home-blog-grid">
                    <a class="bh-home-blog-card bh-reveal" href="<?= BASE_URL ?>index.php?r=blog/index">
                        <span class="bh-home-feature-tag">Inflación</span>
                        <h3>Por qué sube todo y cómo afecta a tu presupuesto</h3>
                        <p>Una explicación con ejemplos domésticos, lejos de la jerga económica.</p>
                        <span class="bh-home-blog-more">Leer artículo</span>
                    </a>
                    <a class="bh-home-blog-card bh-reveal" href="<?= BASE_URL ?>index.php?r=blog/index">
                        <span class="bh-home-feature-tag">Hipotecas</span>
                        <h3>Lo que conviene mirar antes de firmar</h3>
                        <p>La cuota es solo una parte: el plazo y el interés deciden el coste final.</p>
                        <span class="bh-home-blog-more">Leer artículo</span>
                    </a>
                    <a class="bh-home-blog-card bh-reveal" href="<?= BASE_URL ?>index.php?r=blog/index">
                        <span class="bh-home-feature-tag">Activos</span>
                        <h3>Renta fija, variable y fondos, en lenguaje llano</h3>
                        <p>Qué es cada cosa y qué papel juegan el riesgo y el plazo.</p>
                        <span class="bh-home-blog-more">Leer artículo</span>
                    </a>
                </div>
                <div class="bh-home-blog-foot">
                    <a class="bh-btn bh-btn-secondary" href="<?= BASE_URL ?>index.php?r=blog/index">Ver todo el blog</a>
                </div>
            </div>
        </section>

        <!-- ===================== Por qué importa ===================== -->
        <section class="bh-home-belief" id="por-que" aria-labelledby="belief-title">
            <div class="bh-home-wrap">
                <div class="bh-home-belief-inner bh-reveal">
                    <span class="bh-home-belief-rule" aria-hidden="true"></span>
                    <h2 id="belief-title">Entender tu dinero no es un lujo. Es lo que te deja vivir tranquilo.</h2>
                    <p>Casi nunca descuadra el mes una gran decisión, sino muchas pequeñas que nunca paramos a mirar. Cuando ves tus cuentas con calma, el «a ver si este mes ahorro» se convierte en saber cuánto te queda y por qué.</p>
                    <p class="bh-home-belief-close">Para eso está BeneHom: para que mirar tus cuentas sea sencillo, y decidir, cosa tuya.</p>
                </div>
            </div>
        </section>

        <!-- ===================== Seguridad y privacidad ===================== -->
        <section class="bh-home-security" id="seguridad" aria-labelledby="security-title">
            <div class="bh-home-wrap bh-home-security-grid">
                <div class="bh-home-security-intro">
                    <h2 id="security-title">Tus finanzas, bajo tu control</h2>
                    <p>Son datos del hogar, así que los tratamos con cuidado y sin letra pequeña. Esto es lo que hacemos y lo que no.</p>
                    <p class="bh-home-security-note">¿Quieres una copia de tus datos? Escríbenos a <a href="mailto:benehom_web@gmail.com">benehom_web@gmail.com</a>.</p>
                </div>
                <ul class="bh-home-security-list">
                    <li class="bh-reveal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="4" y="10" width="16" height="10" rx="2" />
                            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                        </svg>
                        <div>
                            <h3>Contraseñas cifradas</h3>
                            <p>Guardamos tus contraseñas cifradas, nunca en texto plano.</p>
                        </div>
                    </li>
                    <li class="bh-reveal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M5 12h14" />
                            <path d="M12 5a14 14 0 0 1 0 14 14 14 0 0 1 0-14" />
                        </svg>
                        <div>
                            <h3>No vendemos tus datos</h3>
                            <p>No compartimos ni vendemos tu información a terceros con fines publicitarios.</p>
                        </div>
                    </li>
                    <li class="bh-reveal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 7h14" />
                            <path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" />
                            <path d="M7 7l1 12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2l1-12" />
                        </svg>
                        <div>
                            <h3>Tú decides cuándo irte</h3>
                            <p>Borra tu cuenta y todos tus datos cuando quieras, sin tener que pedir permiso.</p>
                        </div>
                    </li>
                    <li class="bh-reveal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="4" width="8" height="16" rx="1.5" />
                            <rect x="13" y="4" width="8" height="16" rx="1.5" />
                        </svg>
                        <div>
                            <h3>Apuntes y proyecciones, separados</h3>
                            <p>Las proyecciones nunca modifican tus datos reales del mes. Probar no cambia lo que ya registraste.</p>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ========================= Preguntas frecuentes ========================= -->
        <section class="bh-home-faq" id="faq" aria-labelledby="faq-title">
            <div class="bh-home-wrap">
                <div class="bh-home-section-head">
                    <h2 id="faq-title">Preguntas frecuentes</h2>
                    <p>Las dudas más comunes antes de empezar. Si te queda alguna, escríbenos a <a href="mailto:benehom_web@gmail.com">benehom_web@gmail.com</a>.</p>
                </div>
                <div class="bh-home-faq-list">
                    <details class="bh-home-faq-item">
                        <summary>¿BeneHom es gratis de verdad?</summary>
                        <p>Sí. No pedimos tarjeta ni hay planes de pago. Creas tu cuenta y empiezas a apuntar tus movimientos sin coste.</p>
                    </details>
                    <details class="bh-home-faq-item">
                        <summary>¿Tengo que conectar mi banco?</summary>
                        <p>No. Los movimientos se apuntan a mano. Nunca te pedimos las claves de acceso de tu banco ni nos conectamos a tus cuentas.</p>
                    </details>
                    <details class="bh-home-faq-item">
                        <summary>¿Las proyecciones son consejos de inversión?</summary>
                        <p>No. Son estimaciones educativas y orientativas para ver los números con calma. No son recomendaciones ni promesas de rentabilidad.</p>
                    </details>
                    <details class="bh-home-faq-item">
                        <summary>¿Se mezclan mis apuntes con las proyecciones?</summary>
                        <p>No. Lo que ya ha pasado y lo que pruebas viven en espacios separados. Una proyección nunca cambia tus datos reales del mes.</p>
                    </details>
                    <details class="bh-home-faq-item">
                        <summary>¿Puedo copiar los ingresos y gastos de un mes a otro?</summary>
                        <p>No, y es algo que hemos decidido a propósito. Apuntar el mes desde cero es justo lo que te mantiene en contacto con tu dinero: si lo copias todo en automático, el registro se vuelve un trámite y es la forma más fácil de perderle el rastro a los gastos. Preparar el presupuesto no debería llevarte más de una hora al mes, y ese pequeño rato es lo que de verdad ayuda a mejorar. No buscamos complicarte la vida, sino que cada mes vuelvas a mirar tus números con intención.</p>
                    </details>
                    <details class="bh-home-faq-item">
                        <summary>¿Puedo usarlo en el móvil?</summary>
                        <p>Sí. BeneHom funciona en escritorio, tablet y móvil con la misma cuenta, sin instalar nada.</p>
                    </details>
                    <details class="bh-home-faq-item">
                        <summary>¿Y si quiero dejar de usarlo?</summary>
                        <p>Borras tu cuenta y todos tus datos cuando quieras. Antes puedes pedir una copia escribiendo a <a href="mailto:benehom_web@gmail.com">benehom_web@gmail.com</a>.</p>
                    </details>
                </div>
            </div>
        </section>

        <!-- ========================= CTA final ========================= -->
        <section class="bh-home-band" aria-labelledby="band-title">
            <div class="bh-home-wrap">
                <div class="bh-home-band-inner">
                    <div>
                        <h2 id="band-title">Empieza por este mes</h2>
                        <p>Crear una cuenta lleva un minuto. Apunta los primeros movimientos y cuando el mes termine sabrás exactamente dónde ha ido tu dinero.</p>
                    </div>
                    <div class="bh-home-band-actions">
                        <a class="bh-btn bh-btn-primary" href="<?= BASE_URL ?>index.php?r=registro/registrarUsuario">Crear cuenta gratis</a>
                        <a class="bh-btn bh-btn-secondary" href="<?= BASE_URL ?>index.php?r=auth/login">Iniciar sesión</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- ========================= Pie ========================= -->
    <footer class="bh-home-footer">
        <div class="bh-home-wrap">
            <div class="bh-home-footer-top">
                <div class="bh-home-footer-brand">
                    <img src="<?= BASE_URL ?>img/logo-benehom.png" alt="BeneHom" width="120" height="80">
                    <p>Tu dinero cuenta una historia.</p>
                </div>
                <nav class="bh-home-footer-cols" aria-label="Enlaces del pie">
                    <div>
                        <h2>Producto</h2>
                        <a href="#como-funciona">Cómo funciona</a>
                        <a href="#funciones">Funciones</a>
                        <a href="#por-que">Por qué importa</a>
                    </div>
                    <div>
                        <h2>Recursos</h2>
                        <a href="<?= BASE_URL ?>index.php?r=blog/index">Blog</a>
                        <a href="#seguridad">Seguridad</a>
                        <a href="#faq">Preguntas frecuentes</a>
                    </div>
                    <div>
                        <h2>Legal</h2>
                        <a href="<?= BASE_URL ?>index.php?r=legal/privacidad" target="_blank" rel="noopener">Privacidad</a>
                        <a href="<?= BASE_URL ?>index.php?r=legal/terminos" target="_blank" rel="noopener">Términos</a>
                    </div>
                    <div>
                        <h2>Contacto</h2>
                        <a href="mailto:benehom_web@gmail.com">benehom_web@gmail.com</a>
                    </div>
                </nav>
            </div>
            <div class="bh-home-footer-bottom">
                <span>&copy; <?= date('Y') ?> BeneHom</span>
                <span>Las proyecciones son orientativas y no constituyen asesoramiento financiero.</span>
                <span>Desarrollado por <a href="https://www.linkedin.com/in/hiramgonzalez9112" target="_blank" rel="noopener">hiram9112</a></span>
            </div>
        </div>
    </footer>

    <button class="bh-home-top" type="button" aria-label="Volver arriba" hidden>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 19V6" />
            <path d="M6 12l6-6 6 6" />
        </svg>
    </button>

    <script src="<?= BASE_URL ?>js/home.js" defer></script>
</body>

</html>