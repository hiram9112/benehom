<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Metas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

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
            <main class="bh-main bh-main-contained bh-main-centered">
                <div class="contenedor-proximamente">

                    <h4 id="proximamente-titulo">Próximamente</h4>
                    <p id="proximamente-texto"> Sección en desarrollo</p>

                </div>



            </main>
    </div>

    <?php bh_mobile_menu(); ?>
</body>
