<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Política de Privacidad - BeneHom</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <link rel="stylesheet" href="<?= BASE_URL ?>css/custom.css">
</head>

<body>

    <main class="py-5">
        <div class="bh-card bh-card-legal p-4 p-lg-5">

            <h1 class="mb-4">Política de Privacidad</h1>

            <p class="text-muted">
                Última actualización: <?= date('d/m/Y') ?>
            </p>

            <hr>

            <h5 class="mt-4">1. Responsable del tratamiento</h5>
            <p>
                El responsable del tratamiento de los datos es Hiram González,
                desarrollador del proyecto personal BeneHom – Gestor de Economía Familiar.
            </p>

            <h5 class="mt-4">2. Datos que se recogen</h5>
            <ul>
                <li>Nombre de usuario</li>
                <li>Dirección de correo electrónico</li>
                <li>Contraseña (almacenada de forma cifrada)</li>
                <li>Información financiera introducida voluntariamente por el usuario</li>
            </ul>

            <h5 class="mt-4">3. Finalidad del tratamiento</h5>
            <p>
                Los datos se utilizan exclusivamente para permitir el registro,
                autenticación y uso funcional de la aplicación.
                En ningún caso se ceden a terceros ni se emplean con fines comerciales.
            </p>

            <h5 class="mt-4">4. Base legal</h5>
            <p>
                El tratamiento de los datos se fundamenta en el consentimiento
                otorgado por el usuario en el momento del registro.
            </p>

            <h5 class="mt-4">5. Conservación de los datos</h5>
            <p>
                Los datos se conservarán mientras la cuenta permanezca activa.
                El usuario puede solicitar la eliminación de su cuenta en cualquier momento,
                lo que implicará la supresión de los datos asociados.
            </p>

            <h5 class="mt-4">6. Seguridad</h5>
            <p>
                BeneHom aplica medidas técnicas razonables para proteger la información,
                incluyendo cifrado de contraseñas y mecanismos de control de acceso.
            </p>

            <hr class="mt-5">

            <p class="mb-0">
                <a href="<?= BASE_URL ?>index.php?r=cuenta/index">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    Volver a la cuenta
                </a>
            </p>

        </div>
    </main>

</body>

</html>
