<?php
require_once APP_PATH . '/views/partials/head.php';

bh_document_begin([
    'title' => 'Política de privacidad',
    'description' => 'Consulta cómo BeneHom trata los datos personales y financieros introducidos por los usuarios en la aplicación.',
    'canonical' => bh_public_page_url('privacidad'),
    'robots' => 'index',
]);
?>

    <main id="contenido" class="py-5">
        <div class="bh-card bh-card-legal p-4 p-lg-5">

            <h1 class="mb-4">Política de Privacidad</h1>

            <p class="text-muted">
                Última actualización: <?= date('d/m/Y') ?>
            </p>

            <hr>

            <h5 class="mt-4">1. Responsable del tratamiento</h5>
            <p>
                El responsable del tratamiento de los datos es <strong>Hiram González</strong>,
                titular del proyecto BeneHom, herramienta web de gestión de economía
                doméstica. Para cualquier cuestión relativa a tus datos puedes escribir a
                <a href="mailto:benehom_web@gmail.com">benehom_web@gmail.com</a>.
            </p>

            <h5 class="mt-4">2. Datos que recogemos</h5>
            <ul>
                <li>Nombre de usuario</li>
                <li>Dirección de correo electrónico</li>
                <li>Contraseña (almacenada cifrada mediante hash, nunca en texto plano)</li>
                <li>
                    Información financiera introducida voluntariamente por el usuario
                    (cantidades de ingresos, gastos, metas y simulaciones)
                </li>
            </ul>
            <p>
                No se solicitan ni almacenan DNI, documentos identificativos, números de
                tarjeta ni datos de cuentas bancarias.
            </p>

            <h5 class="mt-4">3. Finalidad del tratamiento</h5>
            <p>
                Los datos se utilizan exclusivamente para el registro, la autenticación y el
                funcionamiento de la aplicación. En ningún caso se ceden a terceros ni se
                emplean con fines comerciales o publicitarios.
            </p>

            <h5 class="mt-4">4. Base legal</h5>
            <p>
                El tratamiento se basa en el consentimiento otorgado por el usuario al
                registrarse (art. 6.1.a RGPD) y en la ejecución del servicio solicitado
                (art. 6.1.b RGPD).
            </p>

            <h5 class="mt-4">5. Conservación de los datos</h5>
            <p>
                Los datos se conservan mientras la cuenta permanezca activa. El usuario puede
                eliminar su cuenta en cualquier momento desde su perfil, lo que implica la
                supresión de los datos asociados.
            </p>

            <h5 class="mt-4">6. Destinatarios y prestadores de servicios</h5>
            <p>
                Los datos se alojan en los servidores de nuestro proveedor de hosting
                (Hostinger), que actúa como encargado del tratamiento. Para el envío de
                correos (por ejemplo, la recuperación de contraseña) se utiliza un proveedor
                de correo electrónico. No se realizan otras cesiones de datos.
            </p>

            <h5 class="mt-4">7. Tus derechos</h5>
            <p>
                Puedes ejercer tus derechos de acceso, rectificación, supresión, oposición,
                limitación del tratamiento y portabilidad escribiendo a
                <a href="mailto:benehom_web@gmail.com">benehom_web@gmail.com</a>. Si consideras
                que tus datos no se tratan correctamente, tienes derecho a reclamar ante la
                Agencia Española de Protección de Datos
                (<a href="https://www.aepd.es" target="_blank" rel="noopener">www.aepd.es</a>).
            </p>

            <h5 class="mt-4">8. Cookies</h5>
            <p>
                La aplicación utiliza únicamente cookies técnicas de sesión, necesarias para
                mantener tu sesión iniciada. No se utilizan cookies de análisis, seguimiento
                ni publicidad, por lo que no se requiere consentimiento adicional.
            </p>

            <h5 class="mt-4">9. Seguridad</h5>
            <p>
                BeneHom aplica medidas técnicas razonables para proteger la información,
                incluyendo el cifrado de contraseñas, la conexión segura (HTTPS), el control
                de acceso por sesión y la protección frente a CSRF.
            </p>

            <h5 class="mt-4">10. Cambios en esta política</h5>
            <p>
                Esta política puede actualizarse para adaptarla a cambios legales o
                funcionales. La fecha de última actualización figura al inicio.
            </p>

        </div>
    </main>

<?php bh_document_end(); ?>
