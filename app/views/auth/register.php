<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de usuario</title>

    <!--Bootstrap CSS-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">


    <!--Conectamos con archivo CSS propio-->
    <link rel="stylesheet" href="<?=BASE_URL?>css/custom.css">
</head>
<body class="register-body">
     <div class="container vh-100 d-flex justify-content-center align-items-center">
        <div class="login-card card shadow p-4">
            <h2 class="text-center mb-3">Registrese aquí</h2>
            
            <!--Mostramos mensaje de error si existe-->
            <?php
            if (isset($_SESSION['mensaje_error'])) {
                echo '<div class="alert alert-danger">';
                echo $_SESSION['mensaje_error'];
                echo '</div>';
                unset($_SESSION['mensaje_error']);
            }
            ?>

            <!--Formulario para recoger datos de registro de nuevo usuario-->
            <form method="post" action="?r=registro/registrarUsuario">

                <div class="mb-3">
                    <label for="usuario" class="form-label">Usuario:</label>
                    <input type="text" class="form-control" name="usuario" id="usuario" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Correo electrónico:</label>
                    <input type="email" class="form-control" name="email" id="email" required>
                </div>
                <div class="mb-3"> 
                    <label for="password" class="form-label">Contraseña:</label>
                    <div class="input-group">
                        <input type="password" class="form-control" name="password" id="password" required>
                        <button class="btn btn-outline-secondary" type="button"
                            onclick="togglePassword('password', this)"><i class="bi bi-eye"></i></button>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password_confirm" class="form-label">Confirmar contraseña:</label>
                    <div class="input-group">
                        <input type="password" class="form-control" name="password_confirm" id="password_confirm" required>
                        <button class="btn btn-outline-secondary" type="button"
                            onclick="togglePassword('password_confirm', this)"><i class="bi bi-eye"></i></button>
                    </div>
                </div>



                <button type="submit" id="btn-register" class="btn w-100">Registrarse</button>
            </form>
            <!--Damos opción de iniciar sesión en caso de que ya tenga cuenta-->
            <p class="text-center mt-3 mb-0">¿Ya tienes cuenta?<a href="?r=auth/login">Inicia sesión aquí</a></p>
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


</body>
</html>