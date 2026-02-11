<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Blog</title>
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
    <!-- 🔹 Botón menú móvil -->
    <button class="btn btn-dark d-md-none m-2"
        type="button"
        data-bs-toggle="offcanvas"
        data-bs-target="#mobileMenu"
        aria-controls="mobileMenu">
        <i class="bi bi-list"></i>
    </button>
    <!--Contenedor Principal-->
    <div class="container-fluid ">
        <div class="row">


            <!-- Panel Lateral izquierdo-->
            <aside class="d-none d-md-block col-md-3 col-lg-1  bg-side-menu text-white full-height py-4">



                <!-- Logo Benehom-->
                <div class="logo-container text-center mb-4">
                    <a href="index.php?r=dashboard/index">
                        <img src="<?= BASE_URL ?>img/logo-benehom.png" alt="Logo Benehom" class="logo-benehom">
                    </a>
                </div>

                <hr class="sidebar-separator">

                <!-- Panel Lateral izquierdo-->
                <nav>
                    <ul class="nav flex-column">
                        <li class="nav-item mb-2"><a class="nav-link p-0" href="index.php?r=dashboard/index">Inicio</a></li>
                        <li class="nav-item mb-2"><a class="nav-link p-0" href="index.php?r=metas/index">Metas</a></li>
                        <li class="nav-item mb-2"><a class="nav-link p-0" href="index.php?r=blog/index">Blog</a></li>
                        <li class="nav-item mb-2"><a class="nav-link p-0" href="index.php?r=cuenta/index">Cuenta</a></li>
                    </ul>
                </nav>

                <!--Enlace para cerrar cesión-->
                <div>
                    <a class="nav-link p-0" href="?r=auth/logout">Cerrar sesión</a>
                </div>
            </aside>

            <!-- 🔹 Sidebar MÓVIL (Offcanvas) -->
            <div class="offcanvas offcanvas-start bg-side-menu text-white d-md-none"
                tabindex="-1"
                id="mobileMenu">

                <div class="offcanvas-header">
                    <h5 class="offcanvas-title">Menú</h5>
                    <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="offcanvas">
                    </button>
                </div>

                <div class="offcanvas-body">

                    <div class="logo-container text-center mb-4">
                        <a href="index.php?r=dashboard/index">
                            <img src="<?= BASE_URL ?>img/logo-benehom.png"
                                alt="Logo Benehom"
                                class="logo-benehom">
                        </a>
                    </div>

                    <ul class="nav flex-column">
                        <li class="nav-item mb-2">
                            <a class="nav-link text-white" href="index.php?r=dashboard/index">Inicio</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link text-white" href="index.php?r=metas/index">Metas</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link text-white" href="index.php?r=blog/index">Blog</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link text-white" href="index.php?r=cuenta/index">Cuenta</a>
                        </li>
                        <li class="nav-item mt-3">
                            <a class="nav-link text-white" href="?r=auth/logout">Cerrar sesión</a>
                        </li>
                    </ul>

                </div>
            </div>

            <!--Panel Central-->
            <main class="col-12 col-md-9 col-lg-11 main-proximamente p-4 ">
                <div class="contenedor-proximamente">

                    <h4 id="proximamente-titulo">Próximamente</h4>
                    <p id="proximamente-texto"> Sección en desarrollo</p>

                </div>



            </main>
        </div>
    </div>
</body>