<?php

function bh_numa_widget_mode(): ?string
{
    $route = isset($_GET['r']) ? trim((string) $_GET['r'], '/') : 'home/index';
    $privateRoutes = ['dashboard/index', 'proyecciones/index', 'cuenta/index'];
    $publicRoutes = ['home/index', 'blog/index', 'blog/detalle'];

    if (in_array($route, $privateRoutes, true)) {
        return 'private';
    }

    if (in_array($route, $publicRoutes, true)) {
        return !empty($_SESSION['usuario_id']) ? 'private' : 'public';
    }

    return null;
}

function bh_numa_launcher(string $mode = 'private'): void
{
    if (!in_array($mode, ['private', 'public'], true)) {
        throw new InvalidArgumentException('Modo de Numa no valido.');
    }

    $isPublic = $mode === 'public';
    $available = bh_env_bool('NUMA_ENABLED', false) && (!$isPublic || bh_env_bool('NUMA_PUBLIC_ENABLED', false));
    $maxMessageLength = bh_numa_max_message_length();
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
    $showInitialTooltip = !$isPublic && !empty($_SESSION['usuario_id']) && empty($_SESSION['numa_initial_tooltip_shown']);
    if ($showInitialTooltip) {
        $_SESSION['numa_initial_tooltip_shown'] = true;
    }
    $csrfToken = session_status() === PHP_SESSION_ACTIVE ? csrf_token() : (string) ($_SESSION['csrf_token'] ?? '');
    $endpoints = $isPublic
        ? [
            'status' => BASE_URL . 'index.php?r=numa/public/status',
            'chat' => BASE_URL . 'index.php?r=numa/public/chat',
            'conversation' => BASE_URL . 'index.php?r=numa/public/conversation/new',
        ]
        : [
            'status' => BASE_URL . 'index.php?r=numa/status',
            'chat' => BASE_URL . 'index.php?r=numa/chat',
            'conversation' => BASE_URL . 'index.php?r=numa/conversation/new',
        ];
    $emptyMessages = $isPublic
        ? ['¿Qué quieres saber sobre BeneHom?', '¿En qué concepto de economía familiar puedo ayudarte?', '¿Qué función de BeneHom quieres conocer?']
        : ['¿Qué quieres revisar hoy?', '¿En qué puedo ayudarte?', '¿Qué quieres consultar?', '¿Hay algo que quieras revisar?', '¿Qué te gustaría saber?', '¿Por dónde empezamos?'];
    $suggestions = $isPublic
        ? ['¿Cómo funciona BeneHom?', '¿Qué son los gastos esenciales y flexibles?', '¿Cómo añado un movimiento?', '¿Qué es el ahorro posible?']
        : ['¿Cuánto he ahorrado este mes?', '¿En qué gasto más?', '¿Qué son gastos esenciales y flexibles?', '¿Cómo funcionan mis metas?', '¿Qué es el ahorro disponible?', 'Compara este mes con el anterior.', '¿Cómo añado un movimiento?', '¿Qué es el ahorro posible?'];
    $faceFramesJson = htmlspecialchars((string) json_encode($faceFrames, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
    $armFramesJson = htmlspecialchars((string) json_encode($armFrames, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
    ?>
    <div
        class="bh-numa-widget"
        data-numa-widget
        data-numa-mode="<?= $mode ?>"
        data-numa-show-initial-tooltip="<?= $showInitialTooltip ? 'true' : 'false' ?>"
        data-numa-status-url="<?= htmlspecialchars($endpoints['status'], ENT_QUOTES, 'UTF-8') ?>"
        data-numa-chat-url="<?= htmlspecialchars($endpoints['chat'], ENT_QUOTES, 'UTF-8') ?>"
        data-numa-new-conversation-url="<?= htmlspecialchars($endpoints['conversation'], ENT_QUOTES, 'UTF-8') ?>"
        data-numa-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
        data-numa-max-message-length="<?= $maxMessageLength ?>"
        data-numa-empty-messages="<?= htmlspecialchars((string) json_encode($emptyMessages, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
        data-numa-suggestions="<?= htmlspecialchars((string) json_encode($suggestions, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
        <button
            type="button"
            class="bh-numa-launcher<?= $stateClass ?>"
            aria-label="Abrir Numa"
            aria-expanded="false"
            aria-controls="bh-numa-panel"
            data-numa-launcher
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

        <div
            id="bh-numa-tooltip"
            class="bh-numa-tooltip"
            role="tooltip"
            data-numa-tooltip
            hidden></div>

        <section
            id="bh-numa-panel"
            class="bh-numa-panel"
            aria-label="Chat con Numa"
            data-numa-panel
            hidden>
            <button
                type="button"
                class="bh-numa-new-conversation"
                data-numa-new-conversation
                disabled>Nueva conversación</button>
            <button type="button" class="bh-btn bh-btn-icon bh-btn-ghost bh-numa-panel-close" aria-label="Cerrar Numa" data-numa-close>
                <i class="ti ti-x" aria-hidden="true"></i>
            </button>

            <div class="bh-numa-panel-body" data-numa-panel-content>
                <?php if ($isPublic): ?>
                    <p class="bh-numa-public-note">Numa responde sobre BeneHom y contenido público. Inicia sesión para analizar tus datos personales.</p>
                <?php endif; ?>
                <div class="bh-numa-panel-initial" data-numa-initial>
                    <p data-numa-empty-message></p>
                    <div class="bh-numa-suggestions" aria-label="Preguntas sugeridas para Numa" data-numa-suggestions></div>
                </div>

                <div class="bh-numa-messages" role="log" aria-live="polite" aria-relevant="additions text" data-numa-messages></div>

                <p class="visually-hidden" role="status" aria-live="polite" data-numa-status></p>

                <form class="bh-numa-form" data-numa-form>
                    <label class="visually-hidden" for="bh-numa-message">Pregunta para Numa</label>
                    <div class="bh-numa-composer">
                        <textarea
                            id="bh-numa-message"
                            class="bh-numa-input"
                            name="message"
                            rows="1"
                            maxlength="<?= $maxMessageLength ?>"
                            placeholder="Pregunta a Numa…"
                            data-numa-input
                            disabled></textarea>
                        <span class="bh-numa-counter" data-numa-counter>0/<?= $maxMessageLength ?></span>
                        <button type="submit" class="bh-numa-submit" aria-label="Enviar mensaje" data-numa-submit disabled>
                            <i class="ti ti-arrow-up" aria-hidden="true" data-numa-submit-icon></i>
                            <span class="bh-numa-submit-processing" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>
    <?php
}

function bh_numa_assets(): void
{
    ?>
    <script src="<?= htmlspecialchars(bh_asset('js/vendor/gsap/gsap.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(bh_asset('js/numa-character.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(bh_asset('js/numa-chat.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php
}
