<!DOCTYPE HTML>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Restablecer contraseña</title>

     <!--Bootstrap CSS-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!--Conectamos con archivo CSS propio-->
    <link rel="stylesheet" href="<?=BASE_URL?>css/custom.css">
</head>
<body class="login-body">
    <div class="container vh-100 d-flex justify-content-center align-items-center">
        <div class="login-card card shadow p-4">

            <h2 class="text-center mb-3">Restablecer Contraseña</h2>
     
            <!--Mensajes para cuando somos redirigidos desde el formulario de registro -->
            <?php 
                //Registro exitoso
                if(isset($_SESSION['mensaje_exitoso'])){
                    echo'<div class="alert alert-success">';
                    echo $_SESSION['mensaje_exitoso'];
                    echo "</div>";

                    //eliminamos mensaje para no mostrarlo otra vez
                    unset($_SESSION['mensaje_exitoso']);
                }  
                //Error registrando ya existe el usuario inicie sesión o el usaurio o la contrasñea son incorrectos, damos dos usos a esta varible.
                if(isset($_SESSION['mensaje_error'])){
                    echo'<div class="alert alert-danger">';
                    echo $_SESSION['mensaje_error'];
                    echo"</div>";

                    //eliminamos mensaje para no mostrarlo ota vez;
                    unset($_SESSION['mensaje_error']);
                }
            ?>
            
            <form method="POST" action="?r=password/procesarReset">
                <?= csrf_field() ?>

                <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token']) ?>">

                <div class="mb-3">
                    <label class="form-label">Nueva contraseña</label>
                    <div class="input-group">
                        <input type="password" id="password" name="password" class="form-control" required>
                        <button class="btn btn-outline-secondary" type="button"
                            onclick="togglePassword('password', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Confirmar contraseña</label>
                    <div class="input-group">
                        <input type="password" id="password_confirm"name="password_confirm" class="form-control" required>
                        <button class="btn btn-outline-secondary" type="button"
                            onclick="togglePassword('password_confirm', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn w-100" id="btn-forgot">Cambiar contraseña</button>
            </form>

           
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



