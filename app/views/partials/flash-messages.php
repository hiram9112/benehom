<?php

function bh_flash_messages(): void
{
    $tieneExito = isset($_SESSION['mensaje_exitoso']);
    $tieneError = isset($_SESSION['mensaje_error']);

    if (!$tieneExito && !$tieneError) {
        return;
    }

    $sanitizar = static function (string $raw): string {
        $raw = preg_replace('/<br\s*\/?>/i', "\n", $raw);
        return nl2br(htmlspecialchars(strip_tags($raw), ENT_QUOTES, 'UTF-8'), false);
    };

    ?>
    <div class="bh-flash-stack" aria-live="polite" aria-atomic="true">
        <?php if ($tieneExito): ?>
            <div class="bh-flash bh-flash-success" role="status" data-flash-message data-flash-autodismiss="5000">
                <i class="ti ti-circle-check" aria-hidden="true"></i>
                <p><?= $sanitizar($_SESSION['mensaje_exitoso']) ?></p>
                <button type="button" class="bh-flash-close" data-flash-dismiss aria-label="Cerrar mensaje">
                    <i class="ti ti-x" aria-hidden="true"></i>
                </button>
            </div>
            <?php unset($_SESSION['mensaje_exitoso']); ?>
        <?php endif; ?>

        <?php if ($tieneError): ?>
            <div class="bh-flash bh-flash-error" role="alert" data-flash-message data-flash-autodismiss="5000">
                <i class="ti ti-alert-circle" aria-hidden="true"></i>
                <p><?= $sanitizar($_SESSION['mensaje_error']) ?></p>
                <button type="button" class="bh-flash-close" data-flash-dismiss aria-label="Cerrar mensaje">
                    <i class="ti ti-x" aria-hidden="true"></i>
                </button>
            </div>
            <?php unset($_SESSION['mensaje_error']); ?>
        <?php endif; ?>
    </div>
    <?php
}
