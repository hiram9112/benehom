<?php

function bh_numa_launcher(): void
{
    $available = bh_env_bool('NUMA_ENABLED', false);
    $stateClass = $available ? ' is-available' : ' is-unavailable';
    $bodySrc = bh_asset('img/numa/runtime/numa-body.webp');
    $faceFrames = [
        bh_asset('img/numa/runtime/blink/numa-face-00.webp'),
        bh_asset('img/numa/runtime/blink/numa-face-01.webp'),
        bh_asset('img/numa/runtime/blink/numa-face-02.webp'),
    ];
    $armFrames = array_map(
        static fn (int $frame): string => bh_asset(sprintf('img/numa/runtime/wave/numa-arm-%02d.webp', $frame)),
        range(0, 20)
    );
    $faceFramesJson = htmlspecialchars((string) json_encode($faceFrames, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
    $armFramesJson = htmlspecialchars((string) json_encode($armFrames, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
    ?>
    <button
        type="button"
        class="bh-numa-launcher<?= $stateClass ?>"
        aria-label="Abrir Numa"
        aria-expanded="false"
        data-numa-launcher
        data-tooltip="Abrir Numa"
        data-available="<?= $available ? 'true' : 'false' ?>">
        <span class="bh-numa-launcher-character" aria-hidden="true">
            <picture class="bh-numa-launcher-picture bh-numa-launcher-static" data-numa-static>
                <source
                    srcset="<?= htmlspecialchars(bh_asset('img/numa/numa-static.webp'), ENT_QUOTES, 'UTF-8') ?>"
                    type="image/webp">
                <img
                    src="<?= htmlspecialchars(bh_asset('img/numa/numa-static-master.png'), ENT_QUOTES, 'UTF-8') ?>"
                    alt=""
                    width="1024"
                    height="1024">
            </picture>
            <span
                class="bh-numa-launcher-animated"
                data-numa-animated
                data-numa-body-src="<?= htmlspecialchars($bodySrc, ENT_QUOTES, 'UTF-8') ?>"
                data-numa-face-frames="<?= $faceFramesJson ?>"
                data-numa-arm-frames="<?= $armFramesJson ?>"
                hidden>
                <img class="bh-numa-launcher-layer bh-numa-launcher-body" data-numa-body alt="" width="384" height="384" draggable="false">
                <img class="bh-numa-launcher-layer bh-numa-launcher-face" data-numa-face alt="" width="384" height="384" draggable="false">
                <img class="bh-numa-launcher-layer bh-numa-launcher-arm" data-numa-arm alt="" width="384" height="384" draggable="false">
            </span>
        </span>
    </button>
    <script src="<?= htmlspecialchars(bh_asset('js/vendor/gsap/gsap.min.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(bh_asset('js/numa-character.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php
}
