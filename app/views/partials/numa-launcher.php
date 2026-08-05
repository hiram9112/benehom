<?php

function bh_numa_launcher(): void
{
    $available = bh_env_bool('NUMA_ENABLED', false);
    $stateClass = $available ? ' is-available' : ' is-unavailable';
    ?>
    <button
        type="button"
        class="bh-numa-launcher<?= $stateClass ?>"
        aria-label="Abrir Numa"
        aria-expanded="false"
        data-numa-launcher
        data-numa-state="<?= $available ? 'idle' : 'unavailable' ?>"
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
            <span class="bh-numa-launcher-hybrid" data-numa-hybrid hidden>
                <picture class="bh-numa-launcher-picture">
                    <source
                        srcset="<?= htmlspecialchars(bh_asset('img/numa/numa-base.webp'), ENT_QUOTES, 'UTF-8') ?>"
                        type="image/webp">
                    <img
                        class="bh-numa-launcher-base"
                        src="<?= htmlspecialchars(bh_asset('img/numa/numa-base-master.png'), ENT_QUOTES, 'UTF-8') ?>"
                        alt=""
                        width="1024"
                        height="1024">
                </picture>
                <?php
                $facePath = BASE_PATH . '/public/img/numa/numa-face.svg';
                if (is_file($facePath)) {
                    require $facePath;
                }
                ?>
            </span>
        </span>
    </button>
    <script src="<?= htmlspecialchars(bh_asset('js/vendor/gsap/gsap.min.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(bh_asset('js/numa-character.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php
}
