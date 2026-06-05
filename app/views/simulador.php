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
    ?>

    <!--Contenedor Principal-->
    <div class="bh-app-shell">
        <?php bh_sidebar(); ?>

        <!--Panel Central-->
        <main class="bh-main bh-main-contained">
            <header class="bh-page-header">
                <div>
                    <h1>Simulador</h1>
                    <p>
                        Herramienta educativa para visualizar cómo decisiones de ahorro, inversión e inflación
                        pueden influir en objetivos financieros futuros.
                    </p>
                </div>
            </header>

            <section class="bh-card bh-card-finance mb-4" aria-labelledby="simulador-proposito">
                <div class="bh-card-header">
                    <h2 id="simulador-proposito" class="titulo m-0">Explora escenarios antes de decidir</h2>
                </div>
                <div class="bh-card-body">
                    <p>
                        El Simulador está pensado para ayudarte a entender posibilidades, comparar hipótesis y
                        aprender el impacto aproximado de distintos hábitos financieros.
                    </p>
                    <p class="mb-0">
                        Los resultados que se muestren en esta sección serán estimaciones orientativas, no garantías.
                        BeneHom no ofrece recomendaciones financieras, productos, entidades, activos ni estrategias
                        concretas.
                    </p>
                </div>
            </section>

            <section aria-labelledby="simulador-modulos">
                <div class="bh-page-header">
                    <div>
                        <h2 id="simulador-modulos">Módulos educativos</h2>
                        <p>Estas áreas forman parte del alcance funcional del Simulador.</p>
                    </div>
                </div>

                <div class="bh-content-grid">
                    <article class="bh-card h-100">
                        <div class="bh-card-header">
                            <h3 class="titulo m-0">
                                <i class="bi bi-piggy-bank me-2" aria-hidden="true"></i>
                                Metas de ahorro
                            </h3>
                        </div>
                        <div class="bh-card-body">
                            <p class="mb-0">
                                Permitirá crear escenarios de ahorro y estimar plazos o aportaciones necesarias
                                según una capacidad mensual configurada para simular.
                            </p>
                        </div>
                    </article>

                    <article class="bh-card h-100">
                        <div class="bh-card-header">
                            <h3 class="titulo m-0">
                                <i class="bi bi-graph-up-arrow me-2" aria-hidden="true"></i>
                                Interés compuesto
                            </h3>
                        </div>
                        <div class="bh-card-body">
                            <p class="mb-0">
                                Ayudará a comparar hipótesis de capital inicial, aportaciones periódicas,
                                rentabilidad estimada y plazo, sin recomendar productos financieros.
                            </p>
                        </div>
                    </article>

                    <article class="bh-card h-100">
                        <div class="bh-card-header">
                            <h3 class="titulo m-0">
                                <i class="bi bi-cash-coin me-2" aria-hidden="true"></i>
                                Inflación
                            </h3>
                        </div>
                        <div class="bh-card-body">
                            <p class="mb-0">
                                Servirá para entender cómo la inflación puede afectar al poder adquisitivo de una
                                cantidad a lo largo del tiempo, sin guardar simulaciones.
                            </p>
                        </div>
                    </article>
                </div>
            </section>
        </main>
    </div>

    <?php bh_mobile_menu(); ?>
</body>

</html>
