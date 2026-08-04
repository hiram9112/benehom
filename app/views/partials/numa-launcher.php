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
        <span class="bh-numa-launcher-icon" aria-hidden="true">
            <i class="ti ti-message-circle"></i>
        </span>
        <span class="bh-numa-launcher-dot" aria-hidden="true"></span>
    </button>
    <?php
}
