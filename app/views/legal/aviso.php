<?php
require_once APP_PATH . '/views/partials/head.php';

bh_document_begin([
    'title' => 'Aviso legal',
    'description' => 'Información legal sobre el titular, objeto y condiciones generales del sitio web BeneHom.',
    'canonical' => bh_public_page_url('aviso'),
    'robots' => 'index',
]);
?>

    <main id="contenido" class="py-5">
        <div class="bh-card bh-card-legal p-4 p-lg-5">

            <h1 class="mb-4">Aviso Legal</h1>

            <p class="text-muted">
                Última actualización: <?= date('d/m/Y') ?>
            </p>

            <hr>

            <h5 class="mt-4">1. Titular del sitio</h5>
            <p>
                En cumplimiento de la Ley 34/2002, de Servicios de la Sociedad de la
                Información y de Comercio Electrónico (LSSI-CE), se informa de que este sitio
                web es titularidad de <strong>Hiram González</strong>, titular del proyecto
                BeneHom, herramienta web de gestión de economía doméstica. Contacto:
                <a href="mailto:benehom_web@gmail.com">benehom_web@gmail.com</a>.
            </p>

            <h5 class="mt-4">2. Objeto</h5>
            <p>
                BeneHom ofrece una herramienta gratuita de control y educación sobre la
                economía doméstica.
            </p>

            <h5 class="mt-4">3. Condiciones de uso</h5>
            <p>
                El uso del sitio se rige por los
                <a href="<?= htmlspecialchars(bh_public_page_url('terminos'), ENT_QUOTES, 'UTF-8') ?>">Términos y Condiciones</a>
                y la
                <a href="<?= htmlspecialchars(bh_public_page_url('privacidad'), ENT_QUOTES, 'UTF-8') ?>">Política de Privacidad</a>.
            </p>

            <h5 class="mt-4">4. Propiedad intelectual</h5>
            <p>
                Los contenidos, la marca y el diseño del sitio están protegidos y no pueden
                utilizarse sin autorización.
            </p>

            <h5 class="mt-4">5. Responsabilidad</h5>
            <p>
                La información tiene carácter educativo y orientativo y no constituye
                asesoramiento financiero. El titular no se responsabiliza de las decisiones
                tomadas a partir de su uso.
            </p>

            <h5 class="mt-4">6. Legislación aplicable</h5>
            <p>
                Este aviso legal se rige por la legislación española.
            </p>

        </div>
    </main>

<?php bh_document_end(); ?>
