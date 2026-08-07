(function () {
    'use strict';

    const INITIAL_TOOLTIP_TEXT = 'Hola, soy Numa. ¿En qué puedo ayudarte?';
    const DEFAULT_TOOLTIP_TEXT = '¿En qué puedo ayudarte?';
    const INITIAL_TOOLTIP_TIMEOUT_MS = 5200;
    const OPEN_LABEL = 'Abrir Numa';
    const CLOSE_LABEL = 'Cerrar Numa';
    const MAX_MESSAGE_LENGTH = 300;
    const MAX_INPUT_HEIGHT = 120;

    const EMPTY_MESSAGES = [
        '¿Qué quieres revisar hoy?',
        '¿En qué puedo ayudarte?',
        '¿Qué quieres consultar?',
        '¿Hay algo que quieras revisar?',
        '¿Qué te gustaría saber?',
        '¿Por dónde empezamos?',
    ];

    const SUGGESTIONS = [
        '¿Cuánto he ahorrado este mes?',
        '¿En qué gasto más?',
        '¿Qué son gastos esenciales y flexibles?',
        '¿Cómo funcionan mis metas?',
        '¿Qué es el ahorro disponible?',
        'Compara este mes con el anterior.',
        '¿Cómo añado un movimiento?',
        '¿Qué es el ahorro posible?',
    ];

    const randomItem = (items) => items[Math.floor(Math.random() * items.length)];

    const randomSubset = (items, count) => items
        .map((item) => ({ item, sort: Math.random() }))
        .sort((a, b) => a.sort - b.sort)
        .slice(0, count)
        .map(({ item }) => item);

    const focusFirstPanelTarget = (panel, closeButton) => {
        const input = panel.querySelector('[data-numa-input]:not(:disabled)');

        if (input) {
            input.focus();
            return;
        }

        if (closeButton) {
            closeButton.focus();
        }
    };

    const normaliseText = (value) => String(value || '').trim();

    const createTextNode = (tagName, className, text) => {
        const element = document.createElement(tagName);

        if (className) {
            element.className = className;
        }

        element.textContent = text;

        return element;
    };

    const initialiseWidget = (widget) => {
        const launcher = widget.querySelector('[data-numa-launcher]');
        const tooltip = widget.querySelector('[data-numa-tooltip]');
        const panelId = launcher ? launcher.getAttribute('aria-controls') : '';
        const panel = panelId ? document.getElementById(panelId) : widget.querySelector('[data-numa-panel]');
        const closeButton = panel ? panel.querySelector('[data-numa-close]') : null;
        const form = panel ? panel.querySelector('[data-numa-form]') : null;
        const input = panel ? panel.querySelector('[data-numa-input]') : null;
        const submitButton = panel ? panel.querySelector('[data-numa-submit]') : null;
        const submitIcon = panel ? panel.querySelector('[data-numa-submit-icon]') : null;
        const counter = panel ? panel.querySelector('[data-numa-counter]') : null;
        const initialState = panel ? panel.querySelector('[data-numa-initial]') : null;
        const emptyMessage = panel ? panel.querySelector('[data-numa-empty-message]') : null;
        const suggestions = panel ? panel.querySelector('[data-numa-suggestions]') : null;
        const messages = panel ? panel.querySelector('[data-numa-messages]') : null;
        const status = panel ? panel.querySelector('[data-numa-status]') : null;
        const shouldShowInitialTooltip = widget.getAttribute('data-numa-show-initial-tooltip') === 'true';
        const statusUrl = widget.getAttribute('data-numa-status-url') || '';
        const chatUrl = widget.getAttribute('data-numa-chat-url') || '';
        const csrfToken = widget.getAttribute('data-numa-csrf') || '';

        if (!launcher || !tooltip || !panel || !closeButton || !form || !input || !submitButton || !initialState || !emptyMessage || !suggestions || !messages || !status) {
            return;
        }

        let panelOpen = false;
        let hovering = false;
        let focusing = false;
        let initialTooltipDismissed = !shouldShowInitialTooltip;
        let defaultTooltipSuppressed = false;
        let tooltipTimer = 0;
        let statusRequestId = 0;
        let activeRequest = false;
        let hasConversation = false;
        let serviceReady = false;
        let currentUsage = null;

        const clearTooltipTimer = () => {
            window.clearTimeout(tooltipTimer);
            tooltipTimer = 0;
        };

        const interactionActive = () => hovering || focusing;

        const showTooltip = (text, type) => {
            if (panelOpen) {
                return;
            }

            clearTooltipTimer();
            tooltip.textContent = text;
            tooltip.dataset.numaTooltipType = type;
            tooltip.hidden = false;
            launcher.setAttribute('aria-describedby', tooltip.id);

            if (type === 'initial') {
                tooltipTimer = window.setTimeout(() => {
                    initialTooltipDismissed = true;
                    hideTooltip(false);
                }, INITIAL_TOOLTIP_TIMEOUT_MS);
            }
        };

        const hideTooltip = (dismissInitial) => {
            clearTooltipTimer();

            if (dismissInitial && tooltip.dataset.numaTooltipType === 'initial') {
                initialTooltipDismissed = true;
            }

            tooltip.hidden = true;
            tooltip.textContent = '';
            delete tooltip.dataset.numaTooltipType;
            launcher.removeAttribute('aria-describedby');
        };

        const syncDefaultTooltip = () => {
            if (panelOpen || !initialTooltipDismissed || defaultTooltipSuppressed) {
                return;
            }

            if (interactionActive()) {
                showTooltip(DEFAULT_TOOLTIP_TEXT, 'default');
                return;
            }

            if (tooltip.dataset.numaTooltipType === 'default') {
                hideTooltip(false);
            }
        };

        const announceStatus = (text) => {
            status.textContent = text;
        };

        const setUsage = (usageData) => {
            currentUsage = usageData;
        };

        const hasRemainingUsage = () => {
            if (!currentUsage) {
                return true;
            }

            const dailyRemaining = Number(currentUsage.daily_remaining);
            const monthlyRemaining = Number(currentUsage.monthly_remaining);

            return (!Number.isFinite(dailyRemaining) || dailyRemaining > 0)
                && (!Number.isFinite(monthlyRemaining) || monthlyRemaining > 0);
        };

        const canSend = () => serviceReady && hasRemainingUsage() && !activeRequest;

        const updateCounter = () => {
            if (counter) {
                counter.textContent = `${input.value.length}/${MAX_MESSAGE_LENGTH}`;
            }
        };

        const resizeInput = () => {
            input.style.height = 'auto';
            const nextHeight = Math.min(input.scrollHeight, MAX_INPUT_HEIGHT);
            input.style.height = `${nextHeight}px`;
            input.style.overflowY = input.scrollHeight > MAX_INPUT_HEIGHT ? 'auto' : 'hidden';
        };

        const setInteractiveState = () => {
            const enabled = canSend();
            input.disabled = !enabled;
            submitButton.disabled = !enabled || normaliseText(input.value) === '';
            suggestions.querySelectorAll('button').forEach((button) => {
                button.disabled = !enabled;
            });
        };

        const setProcessing = (processing) => {
            activeRequest = processing;
            submitButton.classList.toggle('is-processing', processing);
            submitButton.setAttribute('aria-label', processing ? 'Numa está procesando' : 'Enviar mensaje');
            form.setAttribute('aria-busy', processing ? 'true' : 'false');

            if (submitIcon) {
                submitIcon.hidden = processing;
            }

            setInteractiveState();
        };

        const renderInitialState = () => {
            initialState.hidden = hasConversation;

            if (hasConversation) {
                suggestions.textContent = '';
                return;
            }

            emptyMessage.textContent = randomItem(EMPTY_MESSAGES);
            suggestions.textContent = '';
            randomSubset(SUGGESTIONS, 3).forEach((suggestion) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'bh-numa-suggestion';
                button.textContent = suggestion;
                button.addEventListener('click', () => sendMessage(suggestion));
                suggestions.appendChild(button);
            });
            setInteractiveState();
        };

        const markConversationStarted = () => {
            if (hasConversation) {
                return;
            }

            hasConversation = true;
            initialState.hidden = true;
            suggestions.textContent = '';
        };

        const scrollMessagesToEnd = () => {
            messages.scrollTop = messages.scrollHeight;
        };

        const appendSources = (bubble, sources) => {
            if (!Array.isArray(sources) || sources.length === 0) {
                return;
            }

            const list = document.createElement('ul');
            list.className = 'bh-numa-message-sources';

            sources.slice(0, 3).forEach((source) => {
                if (!source || typeof source !== 'object') {
                    return;
                }

                const title = normaliseText(source.title);
                const section = normaliseText(source.section);

                if (title === '' && section === '') {
                    return;
                }

                const item = document.createElement('li');
                item.textContent = section !== '' ? `${title} · ${section}` : title;
                list.appendChild(item);
            });

            if (list.childNodes.length > 0) {
                bubble.appendChild(list);
            }
        };

        const appendPeriod = (bubble, period) => {
            if (!period || typeof period !== 'object' || !period.start || !period.end) {
                return;
            }

            bubble.appendChild(createTextNode('p', 'bh-numa-message-meta', `Periodo: ${period.start} a ${period.end}`));
        };

        const addMessage = (role, text, metadata) => {
            markConversationStarted();

            const item = document.createElement('article');
            item.className = `bh-numa-message is-${role}`;

            if (metadata && metadata.tone) {
                item.classList.add(`is-${metadata.tone}`);
            }

            if (metadata && metadata.state) {
                item.dataset.numaStateMessage = text;
            }

            const bubble = document.createElement('div');
            bubble.className = 'bh-numa-message-bubble';
            bubble.appendChild(createTextNode('p', '', text));

            if (role === 'assistant' && metadata) {
                appendSources(bubble, metadata.sources);
                appendPeriod(bubble, metadata.period);
            }

            item.appendChild(bubble);
            messages.appendChild(item);
            scrollMessagesToEnd();
        };

        const addStateMessage = (text, tone) => {
            const message = normaliseText(text);
            const lastMessage = messages.lastElementChild;

            if (message === '') {
                return;
            }

            announceStatus(message);

            if (lastMessage && lastMessage.dataset.numaStateMessage === message) {
                scrollMessagesToEnd();
                return;
            }

            addMessage('assistant', message, {
                state: true,
                tone: tone || 'warning',
            });
        };

        const statusMessageForUsage = (usageData) => {
            if (!usageData || typeof usageData !== 'object') {
                return '';
            }

            const dailyRemaining = Number(usageData.daily_remaining);
            const monthlyRemaining = Number(usageData.monthly_remaining);
            const messages = [];

            if (Number.isFinite(dailyRemaining) && dailyRemaining <= 0) {
                messages.push('Has alcanzado el límite diario de consultas.');
            }

            if (Number.isFinite(monthlyRemaining) && monthlyRemaining <= 0) {
                messages.push('Has alcanzado el límite mensual de consultas.');
            }

            return messages.join(' ');
        };

        const applyServiceStatus = (payload) => {
            const data = payload && typeof payload === 'object' ? payload.data : null;
            const available = Boolean(data && data.available === true);
            const usageData = data && typeof data.usage === 'object' ? data.usage : null;

            serviceReady = available;
            setUsage(usageData);

            if (!available) {
                addStateMessage('Numa no está disponible en este momento.', 'error');
            } else {
                const usageMessage = statusMessageForUsage(usageData);

                if (usageMessage !== '') {
                    addStateMessage(usageMessage, 'warning');
                } else {
                    announceStatus('Numa está disponible.');
                }
            }

            setInteractiveState();

            if (panelOpen && canSend() && (document.activeElement === closeButton || document.activeElement === launcher)) {
                input.focus();
            }
        };

        const loadStatus = () => {
            if (!statusUrl) {
                serviceReady = false;
                addStateMessage('No se ha podido comprobar el estado de Numa.', 'error');
                setInteractiveState();
                return;
            }

            const requestId = statusRequestId + 1;
            statusRequestId = requestId;
            serviceReady = false;
            announceStatus('Comprobando Numa…');
            setInteractiveState();

            fetch(statusUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            })
                .then((response) => response.json().catch(() => null).then((payload) => ({ response, payload })))
                .then(({ response, payload }) => {
                    if (requestId !== statusRequestId) {
                        return;
                    }

                    if (!response.ok || !payload || payload.ok !== true) {
                        serviceReady = false;
                        addStateMessage(
                            response.status === 401
                                ? 'La sesión ha caducado. Vuelve a iniciar sesión.'
                                : 'No se ha podido comprobar el estado de Numa.',
                            'error'
                        );
                        setInteractiveState();
                        return;
                    }

                    applyServiceStatus(payload);
                })
                .catch(() => {
                    if (requestId !== statusRequestId) {
                        return;
                    }

                    serviceReady = false;
                    addStateMessage('No se ha podido comprobar el estado de Numa.', 'error');
                    setInteractiveState();
                });
        };

        const safeErrorMessage = (payload, statusCode) => {
            const message = payload && payload.error && typeof payload.error.message === 'string'
                ? payload.error.message
                : '';

            if (message !== '') {
                return message;
            }

            if (statusCode === 401) {
                return 'La sesión ha caducado. Vuelve a iniciar sesión.';
            }

            if (statusCode === 429) {
                return 'Has alcanzado el límite de consultas de Numa.';
            }

            return 'No he podido responder ahora. Inténtalo de nuevo en unos minutos.';
        };

        const resetComposer = () => {
            input.value = '';
            updateCounter();
            resizeInput();
        };

        const sendMessage = (rawMessage) => {
            const message = normaliseText(rawMessage);

            if (activeRequest) {
                return;
            }

            if (!canSend()) {
                addStateMessage('Numa no está lista para recibir otra consulta.', 'warning');
                return;
            }

            if (message === '') {
                announceStatus('Escribe una consulta válida.');
                return;
            }

            if (message.length > MAX_MESSAGE_LENGTH) {
                announceStatus(`La consulta no puede superar ${MAX_MESSAGE_LENGTH} caracteres.`);
                return;
            }

            addMessage('user', message);
            resetComposer();
            announceStatus('Numa está procesando la consulta.');
            setProcessing(true);

            fetch(chatUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({ message }),
            })
                .then((response) => response.json().catch(() => null).then((payload) => ({ response, payload })))
                .then(({ response, payload }) => {
                    if (!response.ok || !payload || payload.ok !== true || !payload.data || typeof payload.data.message !== 'string') {
                        const errorMessage = safeErrorMessage(payload, response.status);
                        addMessage('assistant', errorMessage, { tone: 'error', state: true });

                        if (response.status === 401 || response.status === 429) {
                            serviceReady = false;
                        }
                        return;
                    }

                    addMessage('assistant', payload.data.message, {
                        sources: payload.data.sources,
                        period: payload.data.period,
                    });

                    if (payload.data.usage && typeof payload.data.usage === 'object') {
                        setUsage(payload.data.usage);
                    }
                })
                .catch(() => {
                    addMessage(
                        'assistant',
                        'No he podido conectar con Numa ahora. Inténtalo de nuevo en unos minutos.',
                        { tone: 'error', state: true }
                    );
                })
                .finally(() => {
                    setProcessing(false);
                    const usageMessage = statusMessageForUsage(currentUsage);

                    if (usageMessage !== '') {
                        addStateMessage(usageMessage, 'warning');
                    }

                    setInteractiveState();

                    if (canSend()) {
                        input.focus();
                    }
                });
        };

        const openPanel = () => {
            if (panelOpen) {
                return;
            }

            panelOpen = true;
            hideTooltip(true);
            panel.hidden = false;
            launcher.setAttribute('aria-expanded', 'true');
            launcher.setAttribute('aria-label', CLOSE_LABEL);
            widget.classList.add('is-numa-open');
            renderInitialState();
            loadStatus();
            focusFirstPanelTarget(panel, closeButton);
        };

        const closePanel = (returnFocus) => {
            if (!panelOpen) {
                return;
            }

            panelOpen = false;
            defaultTooltipSuppressed = true;
            panel.hidden = true;
            launcher.setAttribute('aria-expanded', 'false');
            launcher.setAttribute('aria-label', OPEN_LABEL);
            widget.classList.remove('is-numa-open');

            if (returnFocus) {
                launcher.focus();
            }

            syncDefaultTooltip();
        };

        launcher.addEventListener('click', () => {
            if (panelOpen) {
                closePanel(false);
                return;
            }

            openPanel();
        });

        closeButton.addEventListener('click', () => closePanel(true));

        launcher.addEventListener('pointerenter', () => {
            hovering = true;
            syncDefaultTooltip();
        });

        launcher.addEventListener('pointerleave', () => {
            hovering = false;

            if (!interactionActive()) {
                defaultTooltipSuppressed = false;
            }

            syncDefaultTooltip();
        });

        launcher.addEventListener('focus', () => {
            focusing = true;
            syncDefaultTooltip();
        });

        launcher.addEventListener('blur', () => {
            focusing = false;

            if (!interactionActive()) {
                defaultTooltipSuppressed = false;
            }

            syncDefaultTooltip();
        });

        input.addEventListener('input', () => {
            updateCounter();
            resizeInput();
            setInteractiveState();
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            sendMessage(input.value);
        });

        panel.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closePanel(true);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            if (panelOpen) {
                event.preventDefault();
                closePanel(true);
                return;
            }

            if (!tooltip.hidden) {
                event.preventDefault();
                hideTooltip(true);
            }
        });

        resetComposer();
        renderInitialState();
        setInteractiveState();

        if (shouldShowInitialTooltip) {
            showTooltip(INITIAL_TOOLTIP_TEXT, 'initial');
        }
    };

    const initialise = () => {
        document.querySelectorAll('[data-numa-widget]').forEach(initialiseWidget);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise, { once: true });
        return;
    }

    initialise();
}());
