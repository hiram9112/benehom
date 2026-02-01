<!DOCTYPE HTML>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperación de Contraseña</title>

     <!--Bootstrap CSS-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!--Conectamos con archivo CSS propio-->
    <link rel="stylesheet" href="<?=BASE_URL?>css/custom.css">
</head>
<body class="login-body">
    <div class="container vh-100 d-flex justify-content-center align-items-center">
        <div class="login-card card shadow p-4">

        <h2 class="text-center mb-3">Recuperar contraseña</h2>

        <?php
            // Mensaje de éxito (si el email existe o no, siempre el mismo)
            if (isset($_SESSION['mensaje_exitoso'])) {
                echo '<div class="alert alert-success">';
                echo $_SESSION['mensaje_exitoso'];
                echo '</div>';
                unset($_SESSION['mensaje_exitoso']);
            }

            // Mensaje de error genérico
            if (isset($_SESSION['mensaje_error'])) {
                echo '<div class="alert alert-danger">';
                echo $_SESSION['mensaje_error'];
                echo '</div>';
                unset($_SESSION['mensaje_error']);
            }
        ?>

        <p class="text-center mb-4">
            Introduce tu correo electrónico
        </p>

        <form method="POST" action="?r=password/procesarFormularioOlvido">
            <?= csrf_field(); ?>

            <div class="mb-3">
                <label for="email" class="form-label"></label>
                <input type="email" name="email" id="email" class="form-control" placeholder="Correo electrónico"required>
            </div>

            <button id ="btn-forgot" type="submit" class="btn w-100">
                Enviar enlace de recuperación
            </button>
        </form>

        <p class="text-center mt-3 mb-0">
            <a href="?r=auth/login">Volver a iniciar sesión</a>
        </p>

    </div>
</div>

</body>
</html>



