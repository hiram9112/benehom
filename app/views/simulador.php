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

    $formatearEuros = static function ($cantidad): string {
        return formatearCantidadPHP($cantidad) . ' €';
    };
    ?>

    <?php if (isset($_SESSION['mensaje_error'])): ?>
        <p class="alert alert-danger text-center">
            <?= htmlspecialchars($_SESSION['mensaje_error'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <?php unset($_SESSION['mensaje_error']); ?>
    <?php endif; ?>

    <!--Contenedor Principal-->
    <div class="bh-app-shell">
        <?php bh_sidebar(); ?>

        <!--Panel Central-->
        <main class="bh-main">
            <section class="bh-simulator-hero mb-4" aria-labelledby="simulador-titulo">
                <article class="bh-card bh-card-finance bh-simulator-intro-card">
                    <div class="bh-card-body">
                        <p class="bh-simulator-kicker">Herramienta educativa</p>
                        <h1 id="simulador-titulo">Simulador</h1>
                        <p>
                            Visualiza cómo decisiones de ahorro, inversión e inflación pueden influir en objetivos
                            financieros futuros sin convertir BeneHom en una contabilidad paralela.
                        </p>
                        <p>
                            Tomamos como referencia el ahorro mensual del mes seleccionado en Dashboard. Puedes editar
                            esa cantidad manualmente para probar escenarios sin modificar tus ingresos, gastos ni el Dashboard.
                        </p>
                        <p class="mb-0">
                            Los resultados son estimaciones orientativas, no garantías ni recomendaciones financieras.
                        </p>
                    </div>
                </article>

                <article class="bh-card bh-simulator-savings-card" aria-labelledby="ahorro-mensual-disponible-label">
                    <div class="bh-card-body">
                        <p id="ahorro-mensual-disponible-label" class="bh-simulator-savings-label">Ahorro mensual disponible</p>
                        <div class="bh-simulator-savings-value">
                            <span
                                id="ahorro_mensual_disponible"
                                class="bh-simulator-savings-amount"
                                role="button"
                                tabindex="0"
                                aria-label="Editar ahorro mensual disponible"
                                data-value="<?= htmlspecialchars((string) $ahorroMensualDisponible, ENT_QUOTES, 'UTF-8') ?>">
                                <?= formatearCantidadPHP($ahorroMensualDisponible) ?>
                            </span>
                            <span class="bh-simulator-currency">€</span>
                        </div>
                        <p class="bh-simulator-edit-hint">Haz clic en el importe para editarlo.</p>

                        <div class="bh-simulator-savings-breakdown">
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

            <?php if ($ahorroAsignadoSuperaDisponible): ?>
                <div class="bh-alert bh-alert-warning mb-4">
                    El ahorro asignado a metas supera el ahorro mensual disponible. Puedes ajustar el importe para simular
                    sin cambiar tus datos reales.
                </div>
            <?php endif; ?>

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
                                según el ahorro mensual disponible para simular.
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
                                rentabilidad hipotética y plazo, sin recomendar productos financieros.
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
    <script>
        window.CSRF_TOKEN = "<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>";
    </script>
    <script src="<?= BASE_URL ?>js/simulador.js?v=<?= time() ?>"></script>
</body>

</html>
