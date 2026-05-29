<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Cuenta</title>
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

    <!--Mensajes para notificar al usuario si la contraseña se ha modificado o no -->
    <?php
    //Registro exitoso
    if (isset($_SESSION['mensaje_exitoso'])) {
        echo "<p class='alert alert-success text-center'>";
        echo $_SESSION['mensaje_exitoso'];
        echo "</p>";

        //eliminamos mensaje para no mostrarlo otra vez
        unset($_SESSION['mensaje_exitoso']);
    }
    //Error registrando ya existe el usuario inicie sesión o el usaurio o la contrasñea son incorrectos, damos dos usos a esta varible.
    if (isset($_SESSION['mensaje_error'])) {
        echo "<p class='alert alert-danger text-center'>";
        echo $_SESSION['mensaje_error'];
        echo "</p>";

        //eliminamos mensaje para no mostrarlo ota vez;
        unset($_SESSION['mensaje_error']);
    }
    ?>

    <?php
    require_once APP_PATH . '/views/partials/app-navigation.php';
    bh_mobile_nav();
    ?>


    <!--Contenedor Principal-->
    <div class="bh-app-shell">
            <?php bh_sidebar(); ?>
            <!--Panel Central-->
            <main class="bh-main bh-main-contained">

                <!--tarjeta cambiar contraseña-->
                <div class="bh-card mb-3 card-cuenta">
                    <div class="bh-card-header">
                        <h4 class=" titulo m-0">Cambiar contraseña</h4>
                    </div>

                    <div class="bh-card-body">

                        <form method="POST" action="index.php?r=cuenta/cambiarPassword" class="formulario-bh">
                            <?= csrf_field() ?>


                            <label for="password_actual">Contraseña actual: </label>
                            <input type="password" id="password_actual" name="password_actual" required>
                            <button class="btn btn-outline-secondary" type="button"
                                onclick="togglePassword('password_actual', this)">
                                <i class="bi bi-eye"></i>
                            </button>


                            <label for="password_nueva">Contraseña nueva: </label>
                            <input type="password" id="password_nueva" name="password_nueva" required>
                            <button class="btn btn-outline-secondary" type="button"
                                onclick="togglePassword('password_nueva', this)">
                                <i class="bi bi-eye"></i>
                            </button>

                            <button type="submit" class="mt-2">Cambiar contraseña</button>
                        </form>
                    </div>
                </div>

                <!--tarjeta eliminar cuenta-->
                <div class="bh-card mb-3 card-cuenta">
                    <div class="bh-card-header">
                        <h4 class=" titulo m-0 text-danger">Eliminar cuenta</h4>
                    </div>

                    <div class="bh-card-body">

                        <!--Alertamos al usuario -->
                        <p class="text-danger fw-bold"> Esta acción es irreversible, se eliminarán todos los datos de la cuenta</p>

                        <form id="formEliminarCuenta" method="POST" action="index.php?r=cuenta/eliminarCuenta" class="formulario-bh">
                            <?= csrf_field() ?>


                            <label for="password_confirmacion">Introduce tu contraseña para confirmar: </label>
                            <input type="password" id="password_confirmacion" name="password_confirmacion" required>
                            <button class="btn btn-outline-secondary" type="button"
                                onclick="togglePassword('password_confirmacion', this)">
                                <i class="bi bi-eye"></i>
                            </button>



                            <button type="submit" class="mt-2 btn btn-danger">
                                Eliminar cuenta
                            </button>
                        </form>
                    </div>
                </div>

                <!-- tarjeta documentación legal -->
                <div class="bh-card bh-card-legal card-cuenta">
                    <div class="bh-card-header">
                        <h4 class="titulo m-0">Documentación legal</h4>
                    </div>

                    <div class="bh-card-body">
                        <p class="mb-3">
                            Puedes consultar en cualquier momento la información relativa
                            a la protección de datos y condiciones de uso de la aplicación.
                        </p>

                        <ul class="list-unstyled mb-0">
                            <li>
                                <a href="index.php?r=legal/privacidad" target="_blank">
                                    Política de Privacidad
                                </a>
                            </li>
                            <li>
                                <a href="index.php?r=legal/terminos" target="_blank">
                                    Términos y Condiciones de Uso
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

            </main>
    </div>

    <?php bh_mobile_menu(); ?>

    <!-- Modal de confirmación genérico -->
    <div class="modal fade" id="modalConfirmacion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalConfirmacionTitulo">Confirmar acción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body" id="modalConfirmacionTexto">
                    ¿Estás seguro?
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-danger" id="modalConfirmacionAceptar">
                        Aceptar
                    </button>
                </div>

            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    </script>

    <script src="<?= BASE_URL ?>js/cuenta.js?v=<?= time() ?>"></script>
</body>

</html>
