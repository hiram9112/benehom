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

            <h2 class="mt-4 bh-legal-section-title">1. Responsable del tratamiento</h2>
            <p>
                El responsable del tratamiento de los datos es <strong>Hiram González</strong>,
                titular del proyecto BeneHom, herramienta web de gestión de economía
                doméstica. Para cualquier cuestión relativa a tus datos puedes escribir a
                <a href="mailto:benehom_web@gmail.com">benehom_web@gmail.com</a>.
            </p>

            <h2 class="mt-4 bh-legal-section-title">2. Datos que recogemos</h2>
            <ul>
                <li>Nombre de usuario</li>
                <li>Dirección de correo electrónico</li>
                <li>Contraseña (almacenada cifrada mediante hash, nunca en texto plano)</li>
                <li>
                    Información financiera introducida por decisión del usuario
                    (cantidades de ingresos, gastos, metas y simulaciones)
                </li>
            </ul>
            <p>
                No se solicitan ni almacenan DNI, documentos identificativos, números de
                tarjeta ni datos de cuentas bancarias.
            </p>

            <h2 class="mt-4 bh-legal-section-title">3. Finalidad del tratamiento</h2>
            <p>
                Los datos se utilizan exclusivamente para el registro, la autenticación y el
                funcionamiento de la aplicación. En ningún caso se ceden a terceros ni se
                emplean con fines comerciales o publicitarios.
            </p>
            <p>
                Si Numa está activada, el tratamiento se inicia únicamente cuando decides usar
                esta funcionalidad y formulas una consulta. BeneHom puede tratar el mensaje
                validado, el contexto conversacional de la sesión y los datos mínimos necesarios
                de ingresos, gastos y movimientos para responder a esa solicitud concreta dentro
                de la aplicación. Las respuestas financieras se ofrecen como texto informativo y
                no como recomendaciones personalizadas.
            </p>
            <p>
                En las páginas públicas donde esté disponible, Numa usa una cookie técnica
                anónima para aplicar una cuota de uso y conservar temporalmente el contexto de
                la conversación en la sesión PHP. Esta identidad no autentica a la persona,
                no da acceso a datos privados y no se utiliza para elaborar perfiles.
            </p>

            <h2 class="mt-4 bh-legal-section-title">4. Base legal</h2>
            <p>
                El tratamiento se basa en el consentimiento otorgado por el usuario al
                registrarse (art. 6.1.a RGPD) y en la ejecución del servicio solicitado
                (art. 6.1.b RGPD).
            </p>
            <p>
                El tratamiento asociado a Numa se basa específicamente en el artículo 6.1.b del
                RGPD, como prestación de la funcionalidad solicitada al formular una consulta.
                No se basa en un consentimiento específico para Gemini: la aceptación de esta
                política informa sobre el tratamiento, pero no legitima por sí misma el uso de
                Gemini. Por ello, no se requiere una casilla, modal ni aceptación adicional para
                utilizar Numa.
            </p>

            <h2 class="mt-4 bh-legal-section-title">5. Conservación de los datos</h2>
            <p>
                Los datos se conservan mientras la cuenta permanezca activa. El usuario puede
                eliminar su cuenta en cualquier momento desde su perfil, lo que implica la
                supresión de los datos asociados.
            </p>
            <p>
                El transcript de Numa se conserva únicamente en la sesión PHP para mantener la
                conversación visible mientras dure la sesión actual. No se guarda
                en la base de datos, no se persiste en el navegador y no existe memoria de Numa
                entre sesiones.
            </p>
            <p>
                La cuota pública de Numa se conserva durante el periodo necesario para aplicar
                sus límites. Solo contiene una identidad seudonimizada derivada de la cookie,
                la fecha y el número de llamadas confirmadas; no guarda consultas, respuestas,
                prompts ni el valor original de la cookie.
            </p>

            <h2 class="mt-4 bh-legal-section-title">6. Destinatarios y prestadores de servicios</h2>
            <p>
                Los datos se alojan en los servidores de nuestro proveedor de hosting
                (Hostinger), que actúa como encargado del tratamiento. Para el envío de
                correos (por ejemplo, la recuperación de contraseña) se utiliza un proveedor
                de correo electrónico. No se realizan otras cesiones de datos.
            </p>
            <p>
                Cuando se utilice Numa, las consultas pueden ser procesadas mediante Gemini API
                de Google como proveedor técnico de inteligencia artificial, con la finalidad
                exclusiva de generar la respuesta solicitada. BeneHom limita los datos enviados
                al mensaje validado, el contexto conversacional elegible y los resultados
                mínimos necesarios de las herramientas internas para responder a esa consulta.
                No se envían identificadores internos de usuario, correo de cuenta, SQL, tablas
                ni columnas.
            </p>
            <p>
                Gemini se utiliza desde un proyecto con facturación activa sujeto a las
                condiciones de servicios de pago de Google. La contribución voluntaria de datos
                para mejorar o entrenar modelos y el registro opcional de prompts y respuestas
                del proyecto permanecen desactivados. También están desactivados el
                almacenamiento de GenerateContent API y el almacenamiento de Interactions API,
                y no existen datasets compartidos voluntariamente con Google. Estos registros y
                ajustes son distintos del tratamiento que Google pueda realizar para la
                supervisión de abuso.
            </p>
            <p>
                Según la política de uso de Gemini API, Google conserva prompts, información de
                contexto y resultados de la API durante 55 días para detectar y prevenir usos
                prohibidos, proteger la seguridad del servicio y cumplir obligaciones legales o
                regulatorias. Este tratamiento de supervisión de abuso es independiente de los
                registros opcionales del proyecto. Para consultas sobre este tratamiento o para
                ejercer tus derechos, puedes contactar con BeneHom en los datos indicados en esta
                política; también se aplican las condiciones y la política de privacidad de
                Google correspondientes al servicio.
            </p>
            <p>
                Para prevenir abusos del acceso público se aplica un límite de ráfaga usando una
                versión seudonimizada y limitada de la dirección IP. BeneHom no almacena la IP
                en claro. No se utiliza fingerprinting del dispositivo, analítica ni publicidad.
            </p>

            <h2 class="mt-4 bh-legal-section-title">7. Tus derechos</h2>
            <p>
                Puedes ejercer tus derechos de acceso, rectificación, supresión, oposición,
                limitación del tratamiento y portabilidad escribiendo a
                <a href="mailto:benehom_web@gmail.com">benehom_web@gmail.com</a>. Si consideras
                que tus datos no se tratan correctamente, tienes derecho a reclamar ante la
                Agencia Española de Protección de Datos
                (<a href="https://www.aepd.es" target="_blank" rel="noopener">www.aepd.es</a>).
            </p>

            <h2 class="mt-4 bh-legal-section-title">8. Cookies</h2>
            <p>
                La aplicación utiliza una cookie técnica de sesión, estrictamente
                necesaria para mantener tu sesión iniciada y proteger las solicitudes frente a
                falsificaciones (CSRF). No se utilizan cookies de análisis, seguimiento
                ni publicidad, por lo que no se requiere consentimiento adicional.
            </p>

            <table class="mt-3">
                <thead>
                    <tr>
                        <th>Cookie</th>
                        <th>Finalidad</th>
                        <th>Tipo</th>
                        <th>Duración</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>PHPSESSID</td>
                        <td>Mantener la sesión y autenticar al usuario</td>
                        <td>Técnica y propia</td>
                        <td>Hasta cerrar el navegador o finalizar la sesión</td>
                    </tr>
                    <tr>
                        <td>bh_numa_anon</td>
                        <td>Aplicar la cuota pública de Numa y asociar temporalmente su conversación</td>
                        <td>Técnica, propia, HttpOnly y SameSite=Lax</td>
                        <td>30 días</td>
                    </tr>
                </tbody>
            </table>

            <h2 class="mt-4 bh-legal-section-title">9. Seguridad</h2>
            <p>
                BeneHom aplica medidas técnicas razonables para proteger la información,
                incluyendo el cifrado de contraseñas, la conexión segura (HTTPS), el control
                de acceso por sesión y la protección frente a CSRF.
            </p>
            <p>
                Una única clave de Gemini se emplea para generar respuestas y crear embeddings
                de Numa. Se mantiene únicamente en la configuración del servidor y se restringe
                a las APIs necesarias. BeneHom documenta su rotación periódica y la revocación inmediata
                ante una sospecha de exposición.
            </p>

            <h2 class="mt-4 bh-legal-section-title">10. Cambios en esta política</h2>
            <p>
                Esta política puede actualizarse para adaptarla a cambios legales o
                funcionales. La fecha de última actualización figura al inicio.
            </p>

        </div>
    </main>

<?php bh_document_end(); ?>
