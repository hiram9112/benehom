<?php
require_once APP_PATH . '/views/partials/head.php';

bh_document_begin([
    'title' => 'Términos y condiciones',
    'description' => 'Condiciones de uso de BeneHom, herramienta web para gestión personal de la economía doméstica.',
    'canonical' => bh_url('index.php?r=legal/terminos'),
    'robots' => 'index',
]);
?>

    <main id="contenido" class="py-5">
        <div class="bh-card bh-card-legal p-4 p-lg-5">

            <h1 class="mb-4">Términos y Condiciones de Uso</h1>

            <p class="text-muted">
                Última actualización: <?= date('d/m/Y') ?>
            </p>

            <hr>

            <h5 class="mt-4">1. Objeto y titular</h5>
            <p>
                BeneHom es una aplicación web destinada a la gestión personal de la economía
                doméstica. El titular del servicio es <strong>Hiram González</strong>
                (proyecto BeneHom); contacto:
                <a href="mailto:benehom_web@gmail.com">benehom_web@gmail.com</a>. Su uso es
                personal y no comercial.
            </p>

            <h5 class="mt-4">2. Registro y cuenta de usuario</h5>
            <p>
                Para utilizar la aplicación es necesario crear una cuenta proporcionando
                información veraz. El usuario es responsable de mantener la confidencialidad
                de sus credenciales de acceso.
            </p>

            <h5 class="mt-4">3. Uso adecuado</h5>
            <p>
                El usuario se compromete a utilizar la aplicación conforme a la legislación
                vigente y a no emplearla para fines ilícitos o contrarios a la buena fe.
            </p>

            <h5 class="mt-4">4. Naturaleza de la herramienta y limitación de responsabilidad</h5>
            <p>
                BeneHom es una herramienta de apoyo y educación para la gestión doméstica.
                Las calculadoras y simuladores (ahorro, inflación, inversión, hipoteca, etc.)
                ofrecen estimaciones orientativas con fines educativos y
                <strong>no constituyen asesoramiento financiero, fiscal ni de inversión</strong>,
                ni garantizan resultado alguno. Las decisiones que el usuario tome a partir de
                la información de la aplicación son de su exclusiva responsabilidad. El servicio
                se ofrece "tal cual", sin garantía de disponibilidad ininterrumpida.
            </p>

            <h5 class="mt-4">5. Propiedad intelectual</h5>
            <p>
                La marca, el diseño y los contenidos de BeneHom están protegidos. No se permite
                su reproducción o uso sin autorización.
            </p>

            <h5 class="mt-4">6. Eliminación de cuenta</h5>
            <p>
                El usuario puede eliminar su cuenta en cualquier momento desde su perfil,
                lo que supondrá la supresión de los datos asociados.
            </p>

            <h5 class="mt-4">7. Modificaciones</h5>
            <p>
                El titular podrá actualizar estos términos cuando sea necesario para
                adaptarlos a cambios legales o funcionales de la aplicación.
            </p>

            <h5 class="mt-4">8. Protección de datos</h5>
            <p>
                El tratamiento de los datos personales se rige por la
                <a href="<?= BASE_URL ?>index.php?r=legal/privacidad">Política de Privacidad</a>.
            </p>

            <h5 class="mt-4">9. Legislación aplicable</h5>
            <p>
                Estos términos se rigen por la legislación española.
            </p>

        </div>
    </main>

<?php bh_document_end(); ?>
