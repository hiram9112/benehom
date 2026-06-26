<?php

/**
 * Sistema unificado de modales BeneHom.
 *
 * Una única plantilla para todos los modales centrados del proyecto, de modo
 * que la información se muestre siempre igual: mismo tamaño, misma tipografía
 * (legible) y misma estructura (cabecera + cuerpo + pie). El estilo vive en
 * las clases bh-modal* de public/css/src/components.css.
 *
 * Uso típico (modal informativo):
 *   bh_info_modal('infoIngresos', '¿Qué son los ingresos?', '<p>...</p>');
 *
 * Uso avanzado (cuerpo dinámico, pie personalizado, variante de marca):
 *   bh_modal([
 *       'id'      => 'modalConfirmacion',
 *       'title'   => 'Confirmar acción',
 *       'titleId' => 'modalConfirmacionTitulo',
 *       'bodyId'  => 'modalConfirmacionTexto',
 *       'body'    => '¿Estás seguro?',
 *       'footer'  => '<button ...>Cancelar</button><button ...>Aceptar</button>',
 *   ]);
 */

if (!function_exists('bh_modal')) {

    /**
     * Renderiza un modal centrado estándar (Bootstrap + clases bh-modal*).
     *
     * Opciones admitidas en $o:
     *   - id         (string, obligatorio) id del modal.
     *   - title      (string)              título visible en la cabecera.
     *   - titleId    (string)              id del <h2> del título; útil cuando el JS
     *                                      actualiza el texto (p. ej. modalConfirmacionTitulo).
     *   - eyebrow    (string)              kicker corto sobre el título (solo variante branded).
     *   - subtitle   (string)              subtítulo bajo el título.
     *   - subtitleId (string)              id del subtítulo (p. ej. para rellenar por JS).
     *   - size       (string)              'default' | 'lg' | 'sm'.
     *   - variant    (string)              '' | 'branded' (instantánea de inversión, azul de marca).
     *   - body       (string)              HTML interior del cuerpo.
     *   - bodyId     (string)              id del .bh-modal-body (contenido dinámico).
     *   - footer     (string|null)         HTML de los botones del pie.
     *                                        - omitido  => un único botón "Cerrar".
     *                                        - null     => sin pie.
     *   - closeWhite (bool)                fuerza el botón de cierre claro (por defecto en branded).
     */
    function bh_modal(array $o): void
    {
        $id         = $o['id'];
        $title      = $o['title'] ?? '';
        $titleId    = $o['titleId'] ?? ($id . 'Label');
        $eyebrow    = $o['eyebrow'] ?? '';
        $subtitle   = $o['subtitle'] ?? '';
        $subtitleId = $o['subtitleId'] ?? '';
        $size       = $o['size'] ?? 'default';
        $variant    = $o['variant'] ?? '';
        $body       = $o['body'] ?? '';
        $bodyId     = $o['bodyId'] ?? ($id . 'Body');
        $closeWhite = $o['closeWhite'] ?? ($variant === 'branded');

        // Pie: si no se pasa la clave, mostramos un "Cerrar" por defecto.
        // Si se pasa null explícitamente, no se renderiza pie.
        $footer = array_key_exists('footer', $o)
            ? $o['footer']
            : '<button type="button" class="bh-btn bh-btn-secondary" data-bs-dismiss="modal">Cerrar</button>';

        $dialogClass = 'modal-dialog modal-dialog-centered modal-dialog-scrollable bh-modal-dialog';
        if ($size === 'lg') {
            $dialogClass .= ' bh-modal-dialog--lg';
        } elseif ($size === 'sm') {
            $dialogClass .= ' bh-modal-dialog--sm';
        }

        $contentClass = 'modal-content bh-modal';
        if ($variant === 'branded') {
            $contentClass .= ' bh-investment-snapshot-modal';
        }

        $closeClass = 'btn-close' . ($closeWhite ? ' btn-close-white' : '');
        $bodyIdAttr = $bodyId !== '' ? ' id="' . htmlspecialchars($bodyId, ENT_QUOTES, 'UTF-8') . '"' : '';
        $subtitleIdAttr = $subtitleId !== '' ? ' id="' . htmlspecialchars($subtitleId, ENT_QUOTES, 'UTF-8') . '"' : '';
        $describedByAttr = $bodyId !== '' ? ' aria-describedby="' . htmlspecialchars($bodyId, ENT_QUOTES, 'UTF-8') . '"' : '';
?>
        <div class="modal fade" id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="<?= htmlspecialchars($titleId, ENT_QUOTES, 'UTF-8') ?>"<?= $describedByAttr ?> aria-hidden="true">
            <div class="<?= $dialogClass ?>">
                <div class="<?= $contentClass ?>">
                    <div class="modal-header bh-modal-header">
                        <div class="bh-modal-heading">
                            <?php if ($eyebrow !== ''): ?>
                                <p class="bh-modal-eyebrow"><?= $eyebrow ?></p>
                            <?php endif; ?>
                            <h2 class="modal-title bh-modal-title" id="<?= htmlspecialchars($titleId) ?>"><?= $title ?></h2>
                            <?php if ($subtitle !== '' || $subtitleId !== ''): ?>
                                <p class="bh-modal-subtitle"<?= $subtitleIdAttr ?>><?= $subtitle ?></p>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="<?= $closeClass ?>" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body bh-modal-body"<?= $bodyIdAttr ?>><?= $body ?></div>

                    <?php if ($footer !== null && $footer !== ''): ?>
                        <div class="modal-footer bh-modal-footer"><?= $footer ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
<?php
    }

    /**
     * Atajo para los modales informativos (cabecera + texto + único botón "Cerrar").
     *
     * @param string $id    id del modal.
     * @param string $title título visible.
     * @param string $body  HTML interior del cuerpo.
     * @param array  $o     opciones extra de bh_modal() (size, subtitle, etc.).
     */
    function bh_info_modal(string $id, string $title, string $body, array $o = []): void
    {
        bh_modal(array_merge($o, [
            'id'    => $id,
            'title' => $title,
            'body'  => $body,
        ]));
    }
}
