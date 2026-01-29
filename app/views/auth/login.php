<!DOCTYPE HTML>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar sesión</title>

     <!--Bootstrap CSS-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!--Conectamos con archivo CSS propio-->
    <link rel="stylesheet" href="<?=BASE_URL?>css/custom.css">
</head>
<body class="login-body">
    <div class="container vh-100 d-flex justify-content-center align-items-center">
        <div class="login-card card shadow p-4">

            <h2 class="text-center mb-3">Inicie sesión</h2>
     
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
    
            <!--Enviamos el formulario a la misma ruta donde nos encontramos , será una petición de tipo POST lo procesará diferente-->
            <form method="post" action="">
                <div class="mb-3">
                    <label for="email" class="form-label">Email:</label>
                    <input type="email" name="email" id="email" class="form-control"required>
                </div>

                <div class="mb-3"> 
                    <label for="password" class="form-label">Contraseña:</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>

                <button type="submit" id="btn-login" class="btn w-100">Iniciar sesión</button>
            </form>

            <p class="text-center mt-3 mb-0">¿No tienes cuenta? <a href="?r=registro/registrarUsuario"> Registrate aquí</a></p><br>
            <p class="text-center mt-3 mb-0"> <a href="#" 
                onclick="alert('Esta funcionalidad está en desarrollo, si necesita recuperar el acceso contacte con el administrador: hiram9112@gmail.com')">
                ¿Olvidaste la contraseña?</a></p>
        </div>
    </div>
</body>
</html>



