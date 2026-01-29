<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de usuario</title>

    <!--Bootstrap CSS-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!--Conectamos con archivo CSS propio-->
    <link rel="stylesheet" href="<?=BASE_URL?>css/custom.css">
</head>
<body class="register-body">
     <div class="container vh-100 d-flex justify-content-center align-items-center">
        <div class="login-card card shadow p-4">
            <h2 class="text-center mb-3">Registrese aquí</h2>
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
                <div class="mb3"> 
                    <label for="password" class="form-label">Contraseña:</label>
                    <input type="password" class="form-control" name="password" id="password" required>
                </div>

                <button type="submit" id="btn-register" class="btn w-100">Registrarse</button>
            </form>
            <!--Damos opción de iniciar sesión en caso de que ya tenga cuenta-->
            <p class="text-center mt-3 mb-0">¿Ya tienes cuenta?<a href="?r=auth/login">Inicia sesión aquí</a></p>
        </div>
    </div>       

</body>
</html>