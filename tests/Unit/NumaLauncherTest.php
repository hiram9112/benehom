<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/views/partials/numa-launcher.php';

final class NumaLauncherTest extends TestCase
{
    private bool $hadNumaEnabled;
    private ?string $numaEnabled;
    private bool $hadMaxMessageLength;
    private ?string $maxMessageLength;
    private array $sessionBackup;
    private array $queryBackup;

    protected function setUp(): void
    {
        $this->hadNumaEnabled = array_key_exists('NUMA_ENABLED', $_ENV);
        $this->numaEnabled = $this->hadNumaEnabled ? (string) $_ENV['NUMA_ENABLED'] : null;
        $this->hadMaxMessageLength = array_key_exists('NUMA_MAX_MESSAGE_LENGTH', $_ENV);
        $this->maxMessageLength = $this->hadMaxMessageLength ? (string) $_ENV['NUMA_MAX_MESSAGE_LENGTH'] : null;
        $_ENV['NUMA_MAX_MESSAGE_LENGTH'] = '300';
        $this->sessionBackup = $_SESSION ?? [];
        $this->queryBackup = $_GET;
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if ($this->hadNumaEnabled) {
            $_ENV['NUMA_ENABLED'] = $this->numaEnabled;
        } else {
            unset($_ENV['NUMA_ENABLED']);
        }

        if ($this->hadMaxMessageLength) {
            $_ENV['NUMA_MAX_MESSAGE_LENGTH'] = $this->maxMessageLength;
        } else {
            unset($_ENV['NUMA_MAX_MESSAGE_LENGTH']);
        }

        $_SESSION = $this->sessionBackup;
        $_GET = $this->queryBackup;

        parent::tearDown();
    }

    public function testRenderizaBotonAccesibleDisponible(): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';

        $html = $this->renderLauncher();

        self::assertStringContainsString('type="button"', $html);
        self::assertStringContainsString('aria-label="Abrir Numa"', $html);
        self::assertStringContainsString('aria-expanded="false"', $html);
        self::assertStringContainsString('aria-controls="bh-numa-panel"', $html);
        self::assertStringContainsString('class="bh-numa-launcher is-available"', $html);
        self::assertStringNotContainsString('data-numa-state=', $html);
        self::assertStringNotContainsString('data-tooltip=', $html);
        self::assertStringContainsString('data-available="true"', $html);
        self::assertStringContainsString('data-numa-widget', $html);
        self::assertStringContainsString('data-numa-show-initial-tooltip="false"', $html);
        self::assertStringContainsString('data-numa-status-url="/index.php?r=numa/status"', $html);
        self::assertStringContainsString('data-numa-chat-url="/index.php?r=numa/chat"', $html);
        self::assertStringContainsString('data-numa-new-conversation-url="/index.php?r=numa/conversation/new"', $html);
        self::assertStringContainsString('data-numa-login-url="/index.php?r=auth/login"', $html);
        self::assertStringContainsString('data-numa-csrf="', $html);
        self::assertStringContainsString('data-numa-max-message-length="300"', $html);
        self::assertStringContainsString('data-numa-request-timeout-ms="26000"', $html);
        self::assertStringContainsString('maxlength="300"', $html);
        self::assertStringContainsString('>0/300</span>', $html);
        self::assertStringContainsString('class="bh-numa-launcher-character"', $html);
        self::assertStringContainsString('/img/numa/numa-static-sm.webp?v=', $html);
        self::assertStringContainsString('/img/numa/numa-static-master.png?v=', $html);
        self::assertStringContainsString('/img/numa/runtime/numa-body.webp?v=', $html);
        self::assertStringContainsString('/img/numa/runtime/blink/numa-face-00.webp?v=', $html);
        self::assertStringContainsString('/img/numa/runtime/wave/numa-arm-00.webp?v=', $html);
        self::assertStringContainsString('data-numa-animated', $html);
        self::assertStringContainsString('data-numa-body-src=', $html);
        self::assertStringContainsString('data-numa-face-frames=', $html);
        self::assertStringContainsString('data-numa-arm-frames=', $html);
        self::assertStringNotContainsString('/img/numa/numa-base-master.png?v=', $html);
        self::assertStringNotContainsString('numa-face.svg', $html);
        self::assertStringNotContainsString('class="numa-face"', $html);
        self::assertStringNotContainsString('ti ti-message-circle', $html);
        self::assertStringNotContainsString('Disponible', $html);
        self::assertStringContainsString('aria-hidden="true"', $html);
    }

    public function testRenderizaEstadoNoDisponibleSinDesactivarElBoton(): void
    {
        $_ENV['NUMA_ENABLED'] = 'false';

        $html = $this->renderLauncher();

        self::assertStringContainsString('class="bh-numa-launcher is-unavailable"', $html);
        self::assertStringNotContainsString('data-numa-state=', $html);
        self::assertStringContainsString('data-available="false"', $html);
        self::assertStringNotContainsString('No disponible', $html);
        self::assertStringNotContainsString('aria-disabled="true"', $html);
        self::assertMatchesRegularExpression('/<button\s+type="button"\s+class="bh-numa-launcher is-unavailable"[^>]*data-available="false">/s', $html);
    }

    public function testPropagaElMaximoConfiguradoAlContratoDelCliente(): void
    {
        $_ENV['NUMA_MAX_MESSAGE_LENGTH'] = '280';

        $html = $this->renderLauncher();
        $script = file_get_contents(BASE_PATH . '/public/js/numa-chat.js');

        self::assertStringContainsString('data-numa-max-message-length="280"', $html);
        self::assertStringContainsString('maxlength="280"', $html);
        self::assertStringContainsString('>0/280</span>', $html);
        self::assertIsString($script);
        self::assertStringContainsString("widget.getAttribute('data-numa-max-message-length')", $script);
        self::assertStringContainsString('input.value.length', $script);
    }

    public function testMontaElWidgetDesdeUnUnicoPuntoYSeleccionaElModoEnServidor(): void
    {
        foreach (['dashboard.php', 'proyecciones.php', 'cuenta.php', 'home.php', 'blog.php', 'blog-detalle.php'] as $view) {
            $source = file_get_contents(APP_PATH . '/views/' . $view);

            self::assertIsString($source);
            self::assertStringNotContainsString('bh_numa_launcher();', $source, $view);
            self::assertStringNotContainsString("partials/numa-launcher.php", $source, $view);
        }

        $head = file_get_contents(APP_PATH . '/views/partials/head.php');

        self::assertIsString($head);
        self::assertStringContainsString("require_once APP_PATH . '/views/partials/numa-launcher.php';", $head);
        self::assertStringContainsString('bh_numa_widget_mode()', $head);

        $_GET['r'] = 'home/index';
        self::assertSame('public', bh_numa_widget_mode());

        $_SESSION['usuario_id'] = 123;
        self::assertSame('private', bh_numa_widget_mode());

        foreach (['dashboard/index', 'proyecciones/index', 'cuenta/index'] as $route) {
            $_GET['r'] = $route;
            self::assertSame('private', bh_numa_widget_mode());
        }

        unset($_SESSION['usuario_id']);
        $_GET['r'] = 'legal/privacidad';
        self::assertNull(bh_numa_widget_mode());
    }

    public function testConfiguraElWidgetPublicoSinPermitirQueElClienteElijaElModo(): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';
        $_ENV['NUMA_PUBLIC_ENABLED'] = 'true';

        $html = $this->renderLauncher('public');

        self::assertStringContainsString('data-numa-mode="public"', $html);
        self::assertStringContainsString('data-numa-status-url="/index.php?r=numa/public/status"', $html);
        self::assertStringContainsString('data-numa-chat-url="/index.php?r=numa/public/chat"', $html);
        self::assertStringContainsString('data-numa-new-conversation-url="/index.php?r=numa/public/conversation/new"', $html);
        self::assertStringContainsString('data-numa-login-url="/index.php?r=auth/login"', $html);
        self::assertStringContainsString('data-numa-empty-messages=', $html);
        self::assertStringContainsString('data-numa-suggestions=', $html);
        self::assertStringContainsString('¿Qué quieres revisar hoy?', $html);
        self::assertStringContainsString('¿Qué son gastos esenciales y flexibles?', $html);
        self::assertStringContainsString('¿Cómo añado un movimiento?', $html);
        self::assertStringContainsString('¿Qué es el ahorro posible?', $html);
        self::assertStringNotContainsString('¿Cuánto he ahorrado este mes?', $html);
        self::assertStringNotContainsString('¿En qué gasto más?', $html);
        self::assertStringNotContainsString('¿Cómo funcionan mis metas?', $html);
        self::assertStringNotContainsString('Compara este mes con el anterior.', $html);
        self::assertStringNotContainsString('bh-numa-public-note', $html);
        self::assertStringNotContainsString('data-numa-tools=', $html);
        self::assertStringNotContainsString('data-numa-usuario', $html);
    }

    public function testEstilosFijanElBotonYLoAdaptanEnResponsive(): void
    {
        $css = file_get_contents(BASE_PATH . '/public/css/src/numa.css');

        self::assertIsString($css);
        self::assertStringContainsString('position: fixed', $css);
        self::assertStringContainsString('--bh-numa-launcher-size: 88px', $css);
        self::assertStringContainsString('--bh-numa-launcher-size: 96px', $css);
        self::assertStringContainsString('--bh-numa-head-anchor-x: 44px', $css);
        self::assertStringContainsString('--bh-numa-head-anchor-x: 48px', $css);
        self::assertStringContainsString('--bh-numa-panel-offset-x: 0.75rem', $css);
        self::assertStringContainsString('--bh-numa-panel-offset-x: 0rem', $css);
        self::assertStringContainsString('--bh-numa-edge-x: max(16px, env(safe-area-inset-right))', $css);
        self::assertStringContainsString('--bh-numa-edge-y: max(16px, env(safe-area-inset-bottom))', $css);
        self::assertStringContainsString('right: var(--bh-numa-edge-x)', $css);
        self::assertStringContainsString('bottom: var(--bh-numa-edge-y)', $css);
        self::assertStringContainsString('border: 0', $css);
        self::assertStringContainsString('background: transparent', $css);
        self::assertStringContainsString('overflow: visible', $css);
        self::assertStringContainsString('.bh-numa-tooltip', $css);
        self::assertStringContainsString('background-color: var(--bh-brand)', $css);
        self::assertStringContainsString('color: var(--bh-text-inverse)', $css);
        self::assertStringContainsString('border: 1px solid var(--bh-brand-deep)', $css);
        self::assertStringContainsString('right: var(--bh-numa-head-anchor-x)', $css);
        self::assertStringContainsString('border: 0.5rem solid transparent', $css);
        self::assertStringContainsString('border-top-color: var(--bh-brand)', $css);
        self::assertStringContainsString('.bh-numa-panel::after', $css);
        self::assertStringContainsString('right: calc(var(--bh-numa-head-anchor-x) - var(--bh-numa-panel-offset-x))', $css);
        self::assertStringNotContainsString('background-color: var(--bh-neutral-ink)', $css);
        self::assertStringNotContainsString('content: attr(data-tooltip)', $css);
        self::assertStringContainsString(':focus-visible', $css);
        self::assertStringContainsString('outline: 3px solid var(--bh-focus-color)', $css);
        self::assertStringContainsString(':disabled', $css);
        self::assertStringNotContainsString('border-radius: 50%', $css);
        self::assertMatchesRegularExpression('/\.bh-numa-launcher\{[^}]*overflow: visible/s', $css);
        self::assertStringContainsString('env(safe-area-inset-bottom)', $css);
        self::assertStringContainsString('@media (min-width: 768px)', $css);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        self::assertStringContainsString('grid-template-columns: minmax(0, 1fr)', $css);
        self::assertStringContainsString('grid-template-rows: minmax(0, 1fr) auto', $css);
        self::assertStringContainsString('grid-area: 1 / 1', $css);
        self::assertStringNotContainsString('.bh-numa-public-note', $css);
        self::assertStringContainsString('grid-template-columns: minmax(0, 1fr) minmax(2.25rem, auto) 44px', $css);
        self::assertStringContainsString('transition: opacity 220ms ease-out, transform 220ms ease-out', $css);
        self::assertStringContainsString('.bh-numa-panel.is-numa-entering,', $css);
        self::assertStringContainsString('width: 100%', $css);
        self::assertStringContainsString('transform: translateY(-8%)', $css);
        self::assertStringContainsString('white-space: nowrap', $css);
        self::assertStringContainsString('background: var(--bh-brand)', $css);
        self::assertStringContainsString('border-left-color: var(--bh-negative-ink)', $css);
        self::assertStringContainsString('.bh-numa-panel-header{', $css);
        self::assertStringContainsString('border-bottom: 1px solid var(--bh-border-color)', $css);
        self::assertStringContainsString('background: var(--bh-surface-card)', $css);
        self::assertStringNotContainsString('.bh-numa-status{', $css);
        self::assertStringNotContainsString('.bh-numa-scope-note', $css);
    }

    public function testRenderizaPersonajeEstaticoDecorativo(): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';

        $html = $this->renderLauncher();

        foreach ([
            'numa-face.svg',
            'data-numa-hybrid',
            'bh-numa-launcher-hybrid',
            'bh-numa-launcher-base',
            'numa-brow-left',
            'numa-brow-right',
            'numa-eye-left',
            'numa-eye-right',
            'numa-pupil-left',
            'numa-pupil-right',
            'numa-highlight-left',
            'numa-highlight-right',
            'numa-eyelid-left',
            'numa-eyelid-right',
            'numa-mouth',
        ] as $faceLayer) {
            self::assertStringNotContainsString($faceLayer, $html, $faceLayer);
        }

        self::assertStringContainsString('alt=""', $html);
        self::assertStringContainsString('aria-hidden="true"', $html);

        $css = file_get_contents(BASE_PATH . '/public/css/src/numa.css');

        self::assertIsString($css);
        self::assertStringNotContainsString('.bh-numa-launcher-base', $css);
        self::assertStringNotContainsString('.bh-numa-face', $css);
        self::assertStringContainsString('inset: 0', $css);
        self::assertStringContainsString('width: 100%', $css);
        self::assertStringContainsString('height: 100%', $css);
        self::assertStringContainsString('pointer-events: none', $css);
        self::assertStringContainsString('.bh-numa-launcher-animated', $css);
        self::assertStringContainsString('.bh-numa-launcher-layer', $css);
    }

    public function testUsaAssetsOptimizadosConFallbackEstatico(): void
    {
        $html = $this->renderLauncher();

        self::assertStringContainsString('/img/numa/numa-static-sm.webp?v=', $html);
        self::assertStringContainsString('/img/numa/numa-static-master.png?v=', $html);
        self::assertStringContainsString('/img/numa/runtime/numa-body.webp?v=', $html);
        self::assertStringContainsString('/img/numa/runtime/blink/numa-face-01.webp?v=', $html);
        self::assertStringContainsString('/img/numa/runtime/blink/numa-face-02.webp?v=', $html);
        self::assertStringContainsString('/img/numa/runtime/wave/numa-arm-20.webp?v=', $html);
        self::assertStringNotContainsString('/img/numa/runtime/numa-face-', $html);
        self::assertStringNotContainsString('/img/numa/runtime/numa-arm-', $html);
        self::assertStringNotContainsString('/img/numa/numa-base.webp?v=', $html);
        self::assertStringNotContainsString('/img/numa/numa-base-master.png?v=', $html);
        self::assertStringContainsString('data-numa-static', $html);
        self::assertStringContainsString('data-numa-animated', $html);
        self::assertStringNotContainsString('data-numa-hybrid', $html);
        self::assertStringContainsString('<noscript>', $html);
        self::assertStringContainsString('Numa necesita JavaScript para funcionar', $html);
        $assets = $this->renderAssets();
        self::assertStringContainsString('/js/vendor/gsap/gsap.min.js?v=', $assets);
        self::assertStringContainsString('/js/numa-character.js?v=', $assets);
        self::assertStringContainsString('/js/numa-chat.js?v=', $assets);
    }

    public function testRenderizaTooltipYPanelAccesibleSinOffcanvas(): void
    {
        $html = $this->renderLauncher();

        self::assertStringContainsString('id="bh-numa-tooltip"', $html);
        self::assertStringContainsString('role="tooltip"', $html);
        self::assertStringContainsString('data-numa-tooltip', $html);
        self::assertStringContainsString('id="bh-numa-panel"', $html);
        self::assertStringContainsString('aria-label="Chat con Numa"', $html);
        self::assertStringNotContainsString('aria-labelledby="bh-numa-panel-title"', $html);
        self::assertStringNotContainsString('id="bh-numa-panel-title"', $html);
        self::assertStringNotContainsString('Guía de BeneHom', $html);
        self::assertStringNotContainsString('data-numa-panel-header', $html);
        self::assertStringContainsString('data-numa-panel', $html);
        self::assertStringContainsString('data-numa-close', $html);
        self::assertStringContainsString('data-numa-new-conversation', $html);
        self::assertStringContainsString('Nueva conversación', $html);
        self::assertStringContainsString('data-numa-confirmation', $html);
        self::assertStringContainsString('role="dialog"', $html);
        self::assertStringContainsString('aria-modal="true"', $html);
        self::assertStringContainsString('aria-label="Confirmar nueva conversación"', $html);
        self::assertStringContainsString('¿Empezar de nuevo?', $html);
        self::assertStringContainsString('Numa olvidará lo hablado hasta ahora. Tu límite de uso no cambia.', $html);
        self::assertStringContainsString('data-numa-confirmation-cancel', $html);
        self::assertStringContainsString('data-numa-confirmation-confirm', $html);
        self::assertStringContainsString('>Empezar de nuevo</button>', $html);
        self::assertStringContainsString('data-numa-initial', $html);
        self::assertStringContainsString('data-numa-suggestions', $html);
        self::assertStringContainsString('data-numa-messages', $html);
        self::assertStringContainsString('data-numa-messages data-lenis-prevent', $html);
        self::assertStringContainsString('role="log"', $html);
        self::assertStringContainsString('aria-relevant="additions"', $html);
        self::assertStringNotContainsString('aria-relevant="additions text"', $html);
        self::assertStringContainsString('aria-label="Conversación con Numa"', $html);
        self::assertStringContainsString('tabindex="0"', $html);
        self::assertStringContainsString('data-numa-status', $html);
        self::assertStringContainsString('data-numa-status-retry', $html);
        self::assertStringContainsString('>Reintentar estado</button>', $html);
        self::assertStringNotContainsString('data-numa-usage', $html);
        self::assertStringContainsString('data-numa-form', $html);
        self::assertStringNotContainsString('data-numa-scope', $html);
        self::assertStringContainsString('data-numa-empty-message', $html);
        self::assertStringContainsString('data-numa-counter', $html);
        self::assertStringContainsString('data-numa-counter-value', $html);
        self::assertStringContainsString('id="bh-numa-counter"', $html);
        self::assertStringContainsString('aria-describedby="bh-numa-counter bh-numa-help"', $html);
        self::assertStringContainsString('id="bh-numa-help"', $html);
        self::assertStringContainsString('pulsa Enter para enviar', $html);
        self::assertStringContainsString('Mayús+Enter para un salto de línea', $html);
        self::assertStringContainsString('Máximo 300 caracteres.', $html);
        self::assertStringContainsString('data-numa-submit-icon', $html);
        self::assertStringContainsString('aria-label="Enviar mensaje"', $html);
        self::assertStringContainsString('placeholder="Pregunta a Numa…"', $html);
        self::assertStringContainsString('rows="1"', $html);
        self::assertStringContainsString('role="status"', $html);
        self::assertStringContainsString('maxlength="300"', $html);
        self::assertStringNotContainsString('Numa responde únicamente sobre BeneHom.', $html);
        self::assertStringNotContainsString('El chat de Numa aparecerá aquí', $html);
        self::assertStringNotContainsString('offcanvas', $html);
    }

    public function testSaludoInicialSeMarcaUnaSolaVezPorSesionAutenticada(): void
    {
        $_SESSION['usuario_id'] = 123;

        $primerHtml = $this->renderLauncher();

        self::assertStringContainsString('data-numa-show-initial-tooltip="true"', $primerHtml);
        self::assertTrue($_SESSION['numa_initial_tooltip_shown'] ?? false);

        $segundoHtml = $this->renderLauncher();

        self::assertStringContainsString('data-numa-show-initial-tooltip="false"', $segundoHtml);
        self::assertTrue($_SESSION['numa_initial_tooltip_shown'] ?? false);
    }

    public function testSaludoInicialNoSeMarcaFueraDeSesionAutenticada(): void
    {
        $html = $this->renderLauncher();

        self::assertStringContainsString('data-numa-show-initial-tooltip="false"', $html);
        self::assertArrayNotHasKey('numa_initial_tooltip_shown', $_SESSION);
    }

    public function testControladorFuncionalGestionaTooltipsPanelYFoco(): void
    {
        $javascript = file_get_contents(BASE_PATH . '/public/js/numa-chat.js');

        self::assertIsString($javascript);
        self::assertStringContainsString("const INITIAL_TOOLTIP_TEXT = 'Hola, soy Numa. ¿En qué puedo ayudarte?'", $javascript);
        self::assertStringContainsString("const DEFAULT_TOOLTIP_TEXT = '¿En qué puedo ayudarte?'", $javascript);
        self::assertStringContainsString('const INITIAL_TOOLTIP_TIMEOUT_MS = 5200', $javascript);
        self::assertStringContainsString("widget.getAttribute('data-numa-max-message-length')", $javascript);
        self::assertStringContainsString("'¿Qué quieres consultar?'", $javascript);
        self::assertStringContainsString("'¿Cuánto he ahorrado este mes?'", $javascript);
        self::assertStringContainsString("'¿Qué son gastos esenciales y flexibles?'", $javascript);
        self::assertStringContainsString("widget.getAttribute('data-numa-show-initial-tooltip') === 'true'", $javascript);
        self::assertStringContainsString("widget.getAttribute('data-numa-status-url')", $javascript);
        self::assertStringContainsString("widget.getAttribute('data-numa-chat-url')", $javascript);
        self::assertStringContainsString("widget.getAttribute('data-numa-new-conversation-url')", $javascript);
        self::assertStringContainsString("widget.getAttribute('data-numa-login-url')", $javascript);
        self::assertStringContainsString("widget.getAttribute('data-numa-csrf')", $javascript);
        self::assertStringContainsString("widget.getAttribute('data-numa-request-timeout-ms')", $javascript);
        self::assertStringContainsString('let initialTooltipDismissed = !shouldShowInitialTooltip', $javascript);
        self::assertStringContainsString('let defaultTooltipSuppressed = false', $javascript);
        self::assertStringContainsString('if (shouldShowInitialTooltip) {', $javascript);
        self::assertStringContainsString("document.querySelectorAll('[data-numa-widget]')", $javascript);
        self::assertStringContainsString("launcher.setAttribute('aria-expanded', 'true')", $javascript);
        self::assertStringContainsString("launcher.setAttribute('aria-expanded', 'false')", $javascript);
        self::assertStringContainsString("launcher.setAttribute('aria-label', CLOSE_LABEL)", $javascript);
        self::assertStringContainsString("launcher.setAttribute('aria-label', OPEN_LABEL)", $javascript);
        self::assertStringContainsString('panel.inert = false', $javascript);
        self::assertStringContainsString('panel.inert = true', $javascript);
        self::assertStringContainsString('const PANEL_TRANSITION_DURATION_MS = 220', $javascript);
        self::assertStringContainsString("window.matchMedia('(prefers-reduced-motion: reduce)').matches", $javascript);
        self::assertStringContainsString("panel.classList.add('is-numa-entering')", $javascript);
        self::assertStringContainsString("panel.classList.add('is-numa-leaving')", $javascript);
        self::assertStringContainsString('panel.hidden = true', $javascript);
        self::assertStringContainsString("panel.addEventListener('transitionend'", $javascript);
        self::assertStringContainsString('defaultTooltipSuppressed = true', $javascript);
        self::assertStringContainsString('if (panelOpen || !initialTooltipDismissed || defaultTooltipSuppressed)', $javascript);
        self::assertStringContainsString("event.key === 'Escape'", $javascript);
        self::assertStringContainsString('focusFirstPanelTarget(panel, closeButton)', $javascript);
        self::assertStringContainsString('launcher.focus()', $javascript);
        self::assertStringContainsString("form.addEventListener('submit'", $javascript);
        self::assertStringContainsString("button.addEventListener('click', () => sendMessage(suggestion))", $javascript);
        self::assertStringContainsString("fetch(statusUrl", $javascript);
        self::assertStringContainsString("fetch(chatUrl", $javascript);
        self::assertStringContainsString("statusRetryButton.addEventListener('click', loadStatus)", $javascript);
        self::assertStringContainsString("'X-CSRF-Token': csrfToken", $javascript);
        self::assertStringContainsString('JSON.stringify({ message })', $javascript);
        self::assertStringContainsString('presentChatResponse(payload.data, requestId)', $javascript);
        self::assertStringContainsString("newConversationButton.addEventListener('click', () => {", $javascript);
        self::assertStringContainsString("submitButton.classList.toggle('is-processing', processing)", $javascript);
        self::assertStringContainsString("form.setAttribute('aria-busy', processing ? 'true' : 'false')", $javascript);
        self::assertStringContainsString("addMessage('user', message)", $javascript);
        self::assertStringContainsString("addMessage('assistant'", $javascript);
        self::assertStringContainsString("data && typeof data.availability === 'string'", $javascript);
        self::assertStringContainsString("configuredTextList(widget, 'data-numa-empty-messages', EMPTY_MESSAGES)", $javascript);
        self::assertStringContainsString("configuredTextList(widget, 'data-numa-suggestions', SUGGESTIONS)", $javascript);
        self::assertStringContainsString('const setAvailability', $javascript);
        self::assertStringContainsString('const statusMessageForAvailability', $javascript);
        self::assertStringContainsString('Te estás acercando al límite de uso.', $javascript);
        self::assertStringContainsString('Has alcanzado el límite de uso. Podrás volver a utilizarlo cuando se renueve.', $javascript);
        self::assertMatchesRegularExpression('/setProcessing\(false\);\s+if \(!sessionRedirecting\) \{\s+loadStatus\(requestFailed, true\);/', $javascript);
        self::assertStringContainsString('Conservamos tu borrador', $javascript);
        self::assertStringContainsString('AbortController', $javascript);
        self::assertStringContainsString('podría haberse enviado y haber consumido cuota', $javascript);
        self::assertStringContainsString("code === 'NUMA_INVALID_CSRF'", $javascript);
        self::assertStringContainsString('const redirectToLoginWhenSessionExpired', $javascript);
        self::assertStringContainsString("response.status !== 401 || errorCode !== 'UNAUTHENTICATED'", $javascript);
        self::assertStringContainsString("window.location.assign(loginUrl || 'index.php?r=auth/login')", $javascript);
        self::assertStringNotContainsString('daily_remaining', $javascript);
        self::assertStringNotContainsString('monthly_remaining', $javascript);
        self::assertStringNotContainsString('user_limit', $javascript);
        self::assertStringNotContainsString('visitor_limit', $javascript);
        self::assertStringNotContainsString('global_limit', $javascript);
        self::assertStringNotContainsString('public_global_limit', $javascript);
        self::assertStringNotContainsString('setStatus(', $javascript);
        self::assertStringNotContainsString('window.BHNumaCharacter', $javascript);
        self::assertStringNotContainsString('setNumaState', $javascript);
        self::assertStringNotContainsString('localStorage', $javascript);
        self::assertStringNotContainsString('sessionStorage', $javascript);
        self::assertStringNotContainsString('document.cookie', $javascript);
        self::assertStringNotContainsString('usuario_id', $javascript);
    }

    public function testClienteMuestraSoloTextoSeguroSinExponerElPeriodoEstructurado(): void
    {
        $javascript = file_get_contents(BASE_PATH . '/public/js/numa-chat.js');
        $css = file_get_contents(BASE_PATH . '/public/css/src/numa.css');

        self::assertIsString($javascript);
        self::assertIsString($css);
        self::assertStringContainsString('element.textContent = text', $javascript);
        self::assertStringContainsString('entries.push({ role, message, period: entry.period })', $javascript);
        self::assertStringNotContainsString('const appendPeriod', $javascript);
        self::assertStringNotContainsString('formatSpanishDate', $javascript);
        self::assertStringNotContainsString('bh-numa-message-meta', $javascript);
        self::assertStringNotContainsString('innerHTML', $javascript);
        self::assertStringNotContainsString('data-numa-sources', $javascript);
        self::assertStringNotContainsString('metadata.sources', $javascript);
        self::assertStringContainsString('white-space: pre-line', $css);
    }

    public function testClienteDistingueMensajesPorSuRolCanonico(): void
    {
        $javascript = file_get_contents(BASE_PATH . '/public/js/numa-chat.js');
        $css = file_get_contents(BASE_PATH . '/public/css/src/numa.css');

        self::assertIsString($javascript);
        self::assertIsString($css);
        self::assertStringContainsString("const canonicalRole = role === 'user' ? 'user' : 'assistant'", $javascript);
        self::assertStringContainsString('item.className = `bh-numa-message is-${canonicalRole}`', $javascript);
        self::assertStringContainsString("content.className = 'bh-numa-message-content'", $javascript);
        self::assertStringContainsString("addMessage('assistant', message", $javascript);
        self::assertStringContainsString("const role = entry.role === 'user' ? 'user' : entry.role === 'assistant' ? 'assistant' : ''", $javascript);
        self::assertStringContainsString('.bh-numa-message.is-user .bh-numa-message-content', $css);
        self::assertStringContainsString('justify-content: flex-end', $css);
        self::assertStringContainsString('.bh-numa-message.is-assistant .bh-numa-message-content', $css);
        self::assertStringContainsString('color: var(--bh-brand)', $css);
        self::assertStringContainsString('font-family: var(--bh-font-interface)', $css);
        self::assertStringContainsString('font-size: 1rem', $css);
        self::assertStringContainsString('.bh-numa-message.is-assistant.is-state .bh-numa-message-content', $css);
        self::assertStringNotContainsString('.bh-numa-message.is-assistant .bh-numa-message-bubble', $css);
    }

    public function testClienteMuestraEsperaYRevelaSoloLaRespuestaNueva(): void
    {
        $javascript = file_get_contents(BASE_PATH . '/public/js/numa-chat.js');
        $css = file_get_contents(BASE_PATH . '/public/css/src/numa.css');

        self::assertIsString($javascript);
        self::assertIsString($css);
        self::assertStringContainsString("showThinkingMessage();", $javascript);
        self::assertStringContainsString("'Pensando'", $javascript);
        self::assertStringContainsString('removeThinkingMessage();', $javascript);
        self::assertStringContainsString('const revealAssistantResponse', $javascript);
        self::assertStringContainsString('const words = text.match(/\\S+\\s*/g)', $javascript);
        self::assertStringContainsString('const RESPONSE_REVEAL = {', $javascript);
        self::assertStringContainsString('wordDelayMs: 80', $javascript);
        self::assertStringContainsString('punctuationDelayMs: 120', $javascript);
        self::assertStringContainsString('sentenceDelayMs: 180', $javascript);
        self::assertStringContainsString('maxTotalDelayMs: 6000', $javascript);
        self::assertStringContainsString('RESPONSE_REVEAL.maxTotalDelayMs / totalDelay', $javascript);
        self::assertStringContainsString('if (prefersReducedMotion()) {', $javascript);
        self::assertStringContainsString('cancelProgressiveResponse(false);', $javascript);
        self::assertStringContainsString('const invalidateChatRequest', $javascript);
        self::assertStringContainsString('invalidateChatRequest(true);', $javascript);
        self::assertStringContainsString('if (!preserveConversation && (!conversationsMatch(conversation, canonicalConversation) || !renderedConversationMatches(conversation)))', $javascript);
        self::assertStringContainsString('.bh-numa-message.is-assistant.is-thinking .bh-numa-message-content', $css);
        self::assertStringContainsString("paragraph.classList.add('bh-numa-thinking-word')", $javascript);
        self::assertStringContainsString('.bh-numa-thinking-word::after', $css);
        self::assertStringContainsString('background-size: 220% 100%', $css);
        self::assertStringContainsString('animation: bh-numa-thinking-wash 3200ms', $css);
        self::assertStringContainsString('@keyframes bh-numa-thinking-wash', $css);
        self::assertStringContainsString('.bh-numa-thinking-word::after{', $css);
    }

    public function testClienteSigueSiempreElFinalDelTranscript(): void
    {
        $javascript = file_get_contents(BASE_PATH . '/public/js/numa-chat.js');

        self::assertIsString($javascript);
        self::assertStringContainsString('const scheduleTranscriptScroll', $javascript);
        self::assertStringContainsString('window.requestAnimationFrame', $javascript);
        self::assertStringContainsString('messages.scrollTop = messages.scrollHeight;', $javascript);
        self::assertStringContainsString('scheduleTranscriptScroll();', $javascript);
        self::assertStringContainsString("input.addEventListener('input'", $javascript);
        self::assertStringContainsString('const cancelTranscriptScroll', $javascript);
    }

    public function testNuevaConversacionPideConfirmacionSoloConTranscriptVisibleYNoBorraElBorradorSinElla(): void
    {
        $javascript = file_get_contents(BASE_PATH . '/public/js/numa-chat.js');

        self::assertIsString($javascript);
        self::assertStringContainsString('const enabled = canSend() && !confirmationOpen', $javascript);
        self::assertStringContainsString('newConversationButton.disabled = confirmationOpen || activeRequest || !hasCanonicalConversation', $javascript);
        self::assertStringContainsString('const openNewConversationConfirmation', $javascript);
        self::assertStringContainsString('const closeNewConversationConfirmation', $javascript);
        self::assertStringContainsString("confirmationConfirmButton.addEventListener('click'", $javascript);
        self::assertStringContainsString("confirmationCancelButton.addEventListener('click'", $javascript);
        self::assertStringContainsString("if (event.key === 'Escape')", $javascript);
        self::assertStringContainsString("if (event.key === 'Tab')", $javascript);
        self::assertStringContainsString('panelHeader.inert = true;', $javascript);
        self::assertStringContainsString('panelContent.inert = true;', $javascript);
        self::assertStringContainsString('panelHeader.inert = false;', $javascript);
        self::assertStringContainsString('panelContent.inert = false;', $javascript);
        self::assertStringContainsString("newConversationButton.setAttribute('aria-expanded', 'true')", $javascript);
        self::assertStringNotContainsString('window.confirm', $javascript);
        self::assertStringContainsString('applyServiceStatus(payload);', $javascript);
        self::assertStringContainsString('resetComposer();', $javascript);
        self::assertMatchesRegularExpression('/applyServiceStatus\(payload\);\s+resetComposer\(\);/', $javascript);
    }

    public function testConfirmacionCubreYDesenfocaSoloElPanel(): void
    {
        $css = file_get_contents(BASE_PATH . '/public/css/src/numa.css');

        self::assertIsString($css);
        self::assertStringContainsString('.bh-numa-confirmation{', $css);
        self::assertStringContainsString('inset: 0', $css);
        self::assertStringContainsString('place-items: center', $css);
        self::assertStringContainsString('backdrop-filter: blur(6px)', $css);
        self::assertStringContainsString('width: min(calc(100% - var(--bh-space-5)), 18rem)', $css);
    }

    public function testClienteAnunciaSoloContenidoNuevoYSilenciaLaRestauracionDelHistorial(): void
    {
        $javascript = file_get_contents(BASE_PATH . '/public/js/numa-chat.js');

        self::assertIsString($javascript);
        self::assertStringContainsString("speakerPrefix.textContent = canonicalRole === 'user' ? 'Tú: ' : 'Numa: '", $javascript);
        self::assertStringContainsString("speakerPrefix.className = 'visually-hidden'", $javascript);
        self::assertStringContainsString('const liveState = messages.getAttribute(\'aria-live\')', $javascript);
        self::assertStringContainsString("messages.setAttribute('aria-live', 'off')", $javascript);
        self::assertStringContainsString('messages.textContent = \'\'', $javascript);
        self::assertStringContainsString('messages.setAttribute(\'aria-live\', liveState)', $javascript);
        self::assertStringContainsString('const settleProgressiveResponse', $javascript);
        self::assertStringContainsString("addMessage('assistant', text, metadata)", $javascript);
        self::assertStringContainsString('if (item.isConnected) {', $javascript);
        self::assertStringContainsString('item.remove();', $javascript);
        self::assertStringNotContainsString("announceStatus(message);\n\n            if (lastMessage && lastMessage.dataset.numaStateMessage === message)", $javascript);
    }

    public function testClienteGestionaEnterShiftEnterYCompositorSinRobarFoco(): void
    {
        $javascript = file_get_contents(BASE_PATH . '/public/js/numa-chat.js');

        self::assertIsString($javascript);
        self::assertStringContainsString("input.addEventListener('keydown', (event) => {", $javascript);
        self::assertStringContainsString("if (event.key !== 'Enter' || event.shiftKey || event.isComposing) {", $javascript);
        self::assertStringContainsString('event.preventDefault();', $javascript);
        self::assertStringContainsString('sendMessage(input.value);', $javascript);
        self::assertStringContainsString('let composerHadFocus = false', $javascript);
        self::assertStringContainsString('if (processing && (document.activeElement === input || document.activeElement === submitButton)) {', $javascript);
        self::assertStringContainsString('composerHadFocus = true;', $javascript);
        self::assertStringContainsString('document.activeElement === document.body || document.activeElement === input', $javascript);
        self::assertStringContainsString('input.focus();', $javascript);
        self::assertStringContainsString('input.focus({ preventScroll: true });', $javascript);
        self::assertStringContainsString('restoreComposerFocus && panelOpen && canSend()', $javascript);
        self::assertStringContainsString("form.setAttribute('aria-busy', processing ? 'true' : 'false')", $javascript);
    }

    public function testClienteEvitaQueEscapeCierreNumaConOtroOverlayActivo(): void
    {
        $javascript = file_get_contents(BASE_PATH . '/public/js/numa-chat.js');

        self::assertIsString($javascript);
        self::assertStringContainsString('const hasBlockingOverlay = () => Boolean(', $javascript);
        self::assertStringContainsString("document.querySelector('.modal.show, .offcanvas.show, [data-bh-popover][aria-expanded=\"true\"]')", $javascript);
        self::assertStringContainsString("if (event.key === 'Escape' && !confirmationOpen && !hasBlockingOverlay()) {", $javascript);
        self::assertStringContainsString("if (event.key !== 'Escape' || confirmationOpen || hasBlockingOverlay()) {", $javascript);
        self::assertStringContainsString('}, { capture: true });', $javascript);
    }

    public function testEstilosMantienenZonaTactilSuficiente(): void
    {
        $css = file_get_contents(BASE_PATH . '/public/css/src/numa.css');

        self::assertIsString($css);
        self::assertStringContainsString('.bh-numa-launcher{', $css);
        self::assertStringContainsString('touch-action: manipulation', $css);
        self::assertStringContainsString('.bh-numa-suggestion{', $css);
        self::assertStringContainsString('min-height: 44px', $css);
        self::assertStringContainsString('.bh-numa-new-conversation{', $css);
        self::assertStringContainsString('min-height: 44px', $css);
        self::assertStringContainsString('.bh-numa-submit{', $css);
        self::assertStringContainsString('width: 44px', $css);
        self::assertStringContainsString('height: 44px', $css);
    }

    public function testAssetsOptimizadosConservanDimensionesYTransparencia(): void
    {
        foreach (['numa-static'] as $asset) {
            $masterPath = BASE_PATH . '/public/img/numa/' . $asset . '-master.png';
            $optimizedPath = BASE_PATH . '/public/img/numa/' . $asset . '.webp';
            $masterSize = getimagesize($masterPath);
            $optimizedSize = getimagesize($optimizedPath);

            self::assertIsArray($masterSize, $masterPath);
            self::assertIsArray($optimizedSize, $optimizedPath);
            self::assertSame([1024, 1024], [$masterSize[0], $masterSize[1]], $masterPath);
            self::assertSame([$masterSize[0], $masterSize[1]], [$optimizedSize[0], $optimizedSize[1]], $optimizedPath);
            self::assertSame('image/webp', $optimizedSize['mime'], $optimizedPath);

            $optimizedImage = imagecreatefromwebp($optimizedPath);
            self::assertNotFalse($optimizedImage, $optimizedPath);
            self::assertGreaterThan(0, (imagecolorat($optimizedImage, 0, 0) >> 24) & 0x7F, $optimizedPath);
            imagedestroy($optimizedImage);
        }

        $runtimeAssets = array_merge(
            ['numa-body.webp', 'blink/numa-face-00.webp', 'blink/numa-face-01.webp', 'blink/numa-face-02.webp'],
            array_map(static fn (int $frame): string => sprintf('wave/numa-arm-%02d.webp', $frame), range(0, 20))
        );

        foreach ($runtimeAssets as $asset) {
            $runtimePath = BASE_PATH . '/public/img/numa/runtime/' . $asset;
            $runtimeSize = getimagesize($runtimePath);

            self::assertIsArray($runtimeSize, $runtimePath);
            self::assertSame([384, 384], [$runtimeSize[0], $runtimeSize[1]], $runtimePath);
            self::assertSame('image/webp', $runtimeSize['mime'], $runtimePath);

            $runtimeImage = imagecreatefromwebp($runtimePath);
            self::assertNotFalse($runtimeImage, $runtimePath);
            self::assertGreaterThan(0, (imagecolorat($runtimeImage, 0, 0) >> 24) & 0x7F, $runtimePath);
            self::assertLessThan(90000, filesize($runtimePath), $runtimePath);
            imagedestroy($runtimeImage);
        }
    }

    public function testMaestrosDefinitivosDelPersonajeExisten(): void
    {
        $masters = array_merge(
            [BASE_PATH . '/public/img/numa/masters/numa-body-master.png'],
            array_map(static fn (int $frame): string => sprintf(BASE_PATH . '/public/img/numa/masters/blink/numa-blink-%02d-master.png', $frame), [0, 1, 2]),
            array_map(static fn (int $frame): string => sprintf(BASE_PATH . '/public/img/numa/masters/wave/numa-wave-%02d-master.png', $frame), range(0, 20))
        );

        foreach ($masters as $masterPath) {
            $masterSize = getimagesize($masterPath);

            self::assertIsArray($masterSize, $masterPath);
            self::assertSame([1024, 1024], [$masterSize[0], $masterSize[1]], $masterPath);
            self::assertSame('image/png', $masterSize['mime'], $masterPath);

            $masterImage = imagecreatefrompng($masterPath);
            self::assertNotFalse($masterImage, $masterPath);
            self::assertGreaterThan(0, (imagecolorat($masterImage, 0, 0) >> 24) & 0x7F, $masterPath);
            imagedestroy($masterImage);
        }
    }

    public function testInicializadorPrecargaYActivaComposicionSinControlarElChat(): void
    {
        $javascript = file_get_contents(BASE_PATH . '/public/js/numa-character.js');

        self::assertIsString($javascript);
        self::assertStringContainsString("document.querySelectorAll('[data-numa-launcher]')", $javascript);
        self::assertStringContainsString('staticCharacter.hidden = false', $javascript);
        self::assertStringContainsString('Promise.all(requiredAssets.map(preloadImage))', $javascript);
        self::assertStringContainsString('animatedCharacter.hidden = false', $javascript);
        self::assertStringContainsString('staticCharacter.hidden = true', $javascript);
        self::assertStringContainsString("'(prefers-reduced-motion: reduce)'", $javascript);
        self::assertStringContainsString("launcher.addEventListener('pointerenter'", $javascript);
        self::assertStringContainsString("launcher.addEventListener('focus'", $javascript);
        self::assertStringContainsString('const BLINK_MIN_DELAY = 2600', $javascript);
        self::assertStringContainsString('const BLINK_MAX_DELAY = 4700', $javascript);
        self::assertStringContainsString('const AUTO_WAVE_MIN_DELAY = 10000', $javascript);
        self::assertStringContainsString('const AUTO_WAVE_MAX_DELAY = 15000', $javascript);
        self::assertStringContainsString('const WAVE_FRAME_DURATION = 0.022', $javascript);
        self::assertStringContainsString('playAutomaticWave(3)', $javascript);
        self::assertStringContainsString('appendFullWaveCycle(waveTimeline)', $javascript);
        self::assertStringContainsString('if (interactionActive() || !playAutomaticWave(3))', $javascript);
        self::assertStringContainsString('playArmTo(armFrames.length - 1, completeInteractionRaise)', $javascript);
        self::assertStringContainsString('playArmTo(0, completeInteractionLower)', $javascript);
        self::assertStringContainsString('if (!interactionActive()) {', $javascript);
        self::assertStringContainsString('lowerArmAfterInteraction();', $javascript);
        self::assertStringContainsString("launcher.matches(':focus-visible')", $javascript);
        self::assertStringContainsString('waveTimeline = killTimeline(waveTimeline)', $javascript);
        self::assertStringNotContainsString('addEventListener(\'click\'', $javascript);
        self::assertStringNotContainsString('playWave(1, true)', $javascript);
        self::assertStringNotContainsString('is-character-ready', $javascript);
        self::assertStringNotContainsString('[highFrames[0], highFrames[1], highFrames[0], highFrames[1]]', $javascript);
        self::assertStringNotContainsString('timeline.to({}, { duration: 0.1 })', $javascript);
        self::assertStringNotContainsString('appendArmFrames(timeline, upFrames, 0.032)', $javascript);
        self::assertStringNotContainsString('appendArmFrames(timeline, downFrames, 0.024)', $javascript);
    }

    public function testNoExponeLaApiVisualDescartadaNiEstadosDelChat(): void
    {
        $javascript = file_get_contents(BASE_PATH . '/public/js/numa-character.js');

        self::assertIsString($javascript);
        foreach ([
            'thinking',
            'answer',
            'limit-reached',
            'requiredFaceLayers',
            'data-numa-hybrid',
        ] as $state) {
            self::assertStringNotContainsString($state, $javascript, $state);
        }

        self::assertStringNotContainsString('window.BHNumaCharacter', $javascript);
        self::assertStringNotContainsString('setState', $javascript);
        self::assertStringNotContainsString('dataset.numaState', $javascript);
        self::assertStringNotContainsString('const controllers = new WeakMap()', $javascript);
    }

    public function testNoConservaCanalesNiTimeoutsDelControladorVisualAnterior(): void
    {
        $javascript = file_get_contents(BASE_PATH . '/public/js/numa-character.js');

        self::assertIsString($javascript);
        self::assertStringNotContainsString("const animationChannels = Object.freeze(['ambient', 'transition'])", $javascript);
        self::assertStringNotContainsString('window.clearTimeout(timers[channel])', $javascript);
        self::assertStringNotContainsString('animation.kill()', $javascript);
        self::assertStringNotContainsString('gsap.killTweensOf(animationTargets)', $javascript);
        self::assertStringNotContainsString("gsap.set(animationTargets, { clearProps: 'all' })", $javascript);
        self::assertStringNotContainsString('clearActivity();', $javascript);
        self::assertStringNotContainsString('thinking: 25000', $javascript);
        self::assertStringNotContainsString('answer: 1200', $javascript);
        self::assertStringNotContainsString('setState(stableState)', $javascript);
    }

    public function testCssIncluyeMejorasResponsiveYDegradacion(): void
    {
        $css = file_get_contents(BASE_PATH . '/public/css/src/numa.css');

        self::assertIsString($css);
        self::assertStringContainsString('.bh-numa-message-content{', $css);
        self::assertStringContainsString('overflow-wrap: anywhere', $css);
        self::assertStringContainsString('word-break: break-word', $css);
        self::assertStringContainsString('.bh-numa-input{', $css);
        self::assertStringContainsString('font-size: 1rem', $css);
        self::assertStringContainsString('@media (max-width: 389.98px)', $css);
        self::assertStringContainsString('@media (max-width: 359.98px)', $css);
        self::assertStringContainsString('@media (max-width: 319.98px)', $css);
        self::assertStringContainsString('@media (max-height: 500px) and (orientation: landscape)', $css);
        self::assertStringContainsString('.bh-numa-noscript{', $css);
        self::assertStringContainsString('Numa necesita JavaScript para funcionar', $this->renderLauncher());
    }

    public function testFallbackEstaticoOptimizadoTieneDimensionesYTransparencia(): void
    {
        $optimizedPath = BASE_PATH . '/public/img/numa/numa-static-sm.webp';
        $optimizedSize = getimagesize($optimizedPath);

        self::assertIsArray($optimizedSize, $optimizedPath);
        self::assertSame([384, 384], [$optimizedSize[0], $optimizedSize[1]], $optimizedPath);
        self::assertSame('image/webp', $optimizedSize['mime'], $optimizedPath);

        $optimizedImage = imagecreatefromwebp($optimizedPath);
        self::assertNotFalse($optimizedImage, $optimizedPath);
        self::assertGreaterThan(0, (imagecolorat($optimizedImage, 0, 0) >> 24) & 0x7F, $optimizedPath);
        self::assertLessThan(90000, filesize($optimizedPath), $optimizedPath);
        imagedestroy($optimizedImage);
    }

    private function renderLauncher(string $mode = 'private'): string
    {
        ob_start();
        \bh_numa_launcher($mode);

        return (string) ob_get_clean();
    }

    private function renderAssets(): string
    {
        ob_start();
        \bh_numa_assets();

        return (string) ob_get_clean();
    }
}
