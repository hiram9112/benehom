<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Cuenta</title>
    <!--Bootstrap CSS-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!--Bootstrap Iconos-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!--Bootstrap JS(componentes interactivos)-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!--Conectamos con archivo CSS propio-->
    <link rel="stylesheet" href="<?=BASE_URL?>css/custom.css">
</head>

<body>

    <!--Mensajes para notificar al usuario si la contraseña se ha modificado o no -->
    <?php 
        //Registro exitoso
        if(isset($_SESSION['mensaje_exitoso'])){
            echo"<p class='alert alert-success text-center'>";
            echo $_SESSION['mensaje_exitoso'];
            echo "</p>";

            //eliminamos mensaje para no mostrarlo otra vez
            unset($_SESSION['mensaje_exitoso']);

        }  
        //Error registrando ya existe el usuario inicie sesión o el usaurio o la contrasñea son incorrectos, damos dos usos a esta varible.
        if(isset($_SESSION['mensaje_error'])){
            echo"<p class='alert alert-danger text-center'>";
            echo $_SESSION['mensaje_error'];
            echo"</p>";

            //eliminamos mensaje para no mostrarlo ota vez;
            unset($_SESSION['mensaje_error']);
        }
    ?>

    <!--Contenedor Principal-->
    <div class="container-fluid ">
        <div class="row">


            <!-- Panel Lateral izquierdo-->
            <aside class="col-12 col-md-3 col-lg-1  bg-side-menu text-white full-height py-4">

                

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

            <!--Panel Central-->
            <main class="col-12 col-md-9 col-lg-11 bg-main-content p-4 ">

                <!--tarjeta cambiar contraseña-->
                <div class="card mb-3 card-cuenta">
                    <div class="card-header">
                        <h4 class=" titulo m-0">Cambiar contraseña</h4>
                    </div>

                    <div class="card-body">

                        <form method="POST" action="index.php?r=cuenta/cambiarPassword" class="formulario-bh">

                            <label for="password_actual">Contraseña actual: </label>
                            <input type="password" id="password_actual" name="password_actual" required>

                            <label for="password_nueva">Contraseña nueva: </label>
                            <input type="password" id="password_nueva" name="password_nueva" required>

                            <button type="submit" class="mt-2">Cambiar contraseña</button>
                        </form>
                    </div>
                </div>    
                
                <!--tarjeta eliminar cuenta-->
                <div class="card mb-3 card-cuenta">
                    <div class="card-header">
                        <h4 class=" titulo m-0 text-danger">Eliminar cuenta</h4>
                    </div>

                    <div class="card-body">

                        <!--Alertamos al usuario -->
                        <p class="text-danger fw-bold"> Esta acción es irreversible, se eliminarán todos los datos de la cuenta</p>

                        <form method="POST" action="index.php?r=cuenta/eliminarCuenta" class="formulario-bh">

                            <label for="password_confirmacion">Introduce tu contraseña para confirmar: </label>
                            <input type="password" id="password_confirmacion" name="password_confirmacion" required>

                            

                            <button type="submit"  class="mt-2 btn btn-danger"
                                onclick="return confirm('¿Seguro que desea eliminar su cuenta? Esta acción no se puede deshacer.');">
                                Eliminar cuenta
                            </button>
                        </form>
                    </div>
                </div>    
            </main>                             
        </div>
    </div>
</body>
</html>
