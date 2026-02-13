<!DOCTYPE HTML>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Iniciar sesión</title>

    <!--Bootstrap CSS-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!--Bootstrap JS(componentes interactivos)-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!--Conectamos con archivo CSS propio-->
    <link rel="stylesheet" href="<?= BASE_URL ?>css/custom.css">
</head>

<body class="login-body">
    <div class="container vh-100 d-flex justify-content-center align-items-center">
        <div class="login-card card shadow p-4">

            
            <h2 class="text-center">Inicie sesión</h2>


            <!--Mensajes para cuando somos redirigidos desde el formulario de registro -->
            <?php
            //Registro exitoso
            if (isset($_SESSION['mensaje_exitoso'])) {
                echo '<div class="alert alert-success">';
                echo $_SESSION['mensaje_exitoso'];
                echo "</div>";

                //eliminamos mensaje para no mostrarlo otra vez
                unset($_SESSION['mensaje_exitoso']);
            }
            //Error registrando ya existe el usuario inicie sesión o el usaurio o la contrasñea son incorrectos, damos dos usos a esta varible.
            if (isset($_SESSION['mensaje_error'])) {
                echo '<div class="alert alert-danger">';
                echo $_SESSION['mensaje_error'];
                echo "</div>";

                //eliminamos mensaje para no mostrarlo ota vez;
                unset($_SESSION['mensaje_error']);
            }
            ?>

            <!--Enviamos el formulario a la misma ruta donde nos encontramos , será una petición de tipo POST lo procesará diferente-->
            <form method="post" action="">
                <?= csrf_field() ?>

                <div class="mb-3 ">
                    <label for="email" class="form-label">Email:</label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña:</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control" required>
                        <button class="btn btn-outline-secondary" type="button"
                            onclick="togglePassword('password', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>


                <button type="submit" id="btn-login" class="btn w-100">Iniciar sesión</button>
            </form>

            <p class="text-center mt-3 mb-0">¿No tienes cuenta? <a href="?r=registro/registrarUsuario"> Registrate aquí</a></p><br>
            <p class="text-center mt-3 mb-0">
                <a href="?r=password/mostrarFormularioOlvido">¿Olvidaste la contraseña?</a>
            </p>

            <p class="text-center mt-3 mb-0">
                <a href="#"
                    data-bs-toggle="modal"
                    data-bs-target="#infoApp">
                    ¿Qué es BeneHom?
                </a>
            </p>



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

    <!-- Modal informativo sobre BeneHom -->
    <div class="modal fade" id="infoApp" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">¿Qué es BeneHom?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <p><strong>BeneHom</strong> es una herramienta de gestión de la economía familiar
                        diseñada para ayudarte a comprender con claridad cómo se mueve el dinero en tu hogar.</p>

                    <h6 class="mt-3">¿Qué permite?</h6>
                    <ul>
                        <li>Registrar ingresos y clasificarlos correctamente</li>
                        <li>Diferenciar obligaciones y decisiones de consumo</li>
                        <li>Visualizar tu capacidad real de ahorro vs lo que ahorras realmente</li>
                        <li>Detectar patrones de comportamiento que pueden generar problemas económico para el hogar.</li>
                    </ul>

                    <h6 class="mt-3">¿Cuál es su objetivo?</h6>
                    <p>
                        Fomentar una gestión consciente y sostenible del dinero,
                        demostrando que pequeños ajustes en los hábitos de consumo
                        pueden generar un impacto significativo en la estabilidad
                        económica familiar a largo plazo.
                    </p>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>

            </div>
        </div>
    </div>

</body>

</html>