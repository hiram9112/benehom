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
        data-tooltip="Abrir Numa"
        data-available="<?= $available ? 'true' : 'false' ?>">
        <span class="bh-numa-launcher-character" aria-hidden="true">
            <img
                class="bh-numa-launcher-base"
                src="<?= htmlspecialchars(bh_asset('img/numa/numa-base-master.png'), ENT_QUOTES, 'UTF-8') ?>"
                alt=""
                width="1024"
                height="1024">
            <?php require BASE_PATH . '/public/img/numa/numa-face.svg'; ?>
        </span>
    </button>
    <?php
}
