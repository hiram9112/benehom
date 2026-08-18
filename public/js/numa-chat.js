(function () {
    'use strict';

    const INITIAL_TOOLTIP_TEXT = 'Hola, soy Numa. ¿En qué puedo ayudarte?';
    const DEFAULT_TOOLTIP_TEXT = '¿En qué puedo ayudarte?';
    const INITIAL_TOOLTIP_TIMEOUT_MS = 5200;
    const OPEN_LABEL = 'Abrir Numa';
    const CLOSE_LABEL = 'Cerrar Numa';
    const MAX_INPUT_HEIGHT = 120;
    const PANEL_TRANSITION_DURATION_MS = 220;

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

    const configuredTextList = (widget, attribute, fallback) => {
        const value = widget.getAttribute(attribute);

        if (!value) {
            return fallback;
        }

        try {
            const parsed = JSON.parse(value);

            return Array.isArray(parsed) && parsed.every((item) => typeof item === 'string' && item.trim() !== '')
                ? parsed
                : fallback;
        } catch {
            return fallback;
        }
    };

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

    const formatSpanishDate = (value) => {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || ''));

        if (!match) {
            return '';
        }

        const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
        if (
            date.getFullYear() !== Number(match[1])
            || date.getMonth() !== Number(match[2]) - 1
            || date.getDate() !== Number(match[3])
        ) {
            return '';
        }

        return new Intl.DateTimeFormat('es-ES', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }).format(date);
    };

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
        const newConversationButton = panel ? panel.querySelector('[data-numa-new-conversation]') : null;
        const form = panel ? panel.querySelector('[data-numa-form]') : null;
        const input = panel ? panel.querySelector('[data-numa-input]') : null;
        const submitButton = panel ? panel.querySelector('[data-numa-submit]') : null;
        const submitIcon = panel ? panel.querySelector('[data-numa-submit-icon]') : null;
        const statusRetryButton = panel ? panel.querySelector('[data-numa-status-retry]') : null;
        const counter = panel ? panel.querySelector('[data-numa-counter]') : null;
        const initialState = panel ? panel.querySelector('[data-numa-initial]') : null;
        const emptyMessage = panel ? panel.querySelector('[data-numa-empty-message]') : null;
        const suggestions = panel ? panel.querySelector('[data-numa-suggestions]') : null;
        const messages = panel ? panel.querySelector('[data-numa-messages]') : null;
        const status = panel ? panel.querySelector('[data-numa-status]') : null;
        const shouldShowInitialTooltip = widget.getAttribute('data-numa-show-initial-tooltip') === 'true';
        const statusUrl = widget.getAttribute('data-numa-status-url') || '';
        const chatUrl = widget.getAttribute('data-numa-chat-url') || '';
        const newConversationUrl = widget.getAttribute('data-numa-new-conversation-url') || '';
        const csrfToken = widget.getAttribute('data-numa-csrf') || '';
        const isPublicMode = widget.getAttribute('data-numa-mode') === 'public';
        const emptyMessages = configuredTextList(widget, 'data-numa-empty-messages', EMPTY_MESSAGES);
        const configuredSuggestions = configuredTextList(widget, 'data-numa-suggestions', SUGGESTIONS);
        const configuredMaxMessageLength = Number(widget.getAttribute('data-numa-max-message-length'));
        const maxMessageLength = Number.isInteger(configuredMaxMessageLength) && configuredMaxMessageLength > 0
            ? configuredMaxMessageLength
            : input ? input.maxLength : 0;
        const configuredRequestTimeoutMs = Number(widget.getAttribute('data-numa-request-timeout-ms'));
        const requestTimeoutMs = Number.isInteger(configuredRequestTimeoutMs) && configuredRequestTimeoutMs > 0
            ? configuredRequestTimeoutMs
            : 26000;

        if (!launcher || !tooltip || !panel || !closeButton || !newConversationButton || !form || !input || !submitButton || !statusRetryButton || !initialState || !emptyMessage || !suggestions || !messages || !status) {
            return;
        }

        let panelOpen = false;
        let hovering = false;
        let focusing = false;
        let initialTooltipDismissed = !shouldShowInitialTooltip;
        let defaultTooltipSuppressed = false;
        let tooltipTimer = 0;
        let statusRequestId = 0;
        let statusLoading = false;
        let activeRequest = false;
        let hasConversation = false;
        let hasCanonicalConversation = false;
        let availability = 'unavailable';
        let panelTransitionFrame = 0;
        let panelCloseTimer = 0;
        let chatRequestId = 0;
        let activeAbortController = null;
        let activeRequestTimeout = 0;
        let thinkingMessage = null;
        let progressiveResponse = null;
        let responseRevealTimer = 0;
        let canonicalConversation = [];

        const prefersReducedMotion = () => window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const clearPanelTransitions = () => {
            window.cancelAnimationFrame(panelTransitionFrame);
            window.clearTimeout(panelCloseTimer);
            panelTransitionFrame = 0;
            panelCloseTimer = 0;
        };

        const finishPanelClose = () => {
            if (panelOpen) {
                return;
            }

            clearPanelTransitions();
            panel.classList.remove('is-numa-entering', 'is-numa-leaving');
            panel.hidden = true;
        };

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

        const canSend = () => (availability === 'available' || availability === 'near_limit') && !activeRequest;

        const updateCounter = () => {
            if (counter) {
                counter.textContent = `${input.value.length}/${maxMessageLength}`;
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
            newConversationButton.disabled = activeRequest || !hasCanonicalConversation;
        };

        const setProcessing = (processing) => {
            activeRequest = processing;
            submitButton.classList.toggle('is-processing', processing);
            submitButton.setAttribute('aria-label', processing ? 'Procesando consulta' : 'Enviar mensaje');
            form.setAttribute('aria-busy', processing ? 'true' : 'false');

            if (submitIcon) {
                submitIcon.hidden = processing;
            }

            setInteractiveState();
        };

        const setStatusRetryVisible = (visible) => {
            statusRetryButton.hidden = !visible;
            statusRetryButton.disabled = statusLoading;
        };

        const renderInitialState = () => {
            initialState.hidden = hasConversation;

            if (hasConversation) {
                suggestions.textContent = '';
                return;
            }

            emptyMessage.textContent = randomItem(emptyMessages);
            suggestions.textContent = '';
            randomSubset(configuredSuggestions, 3).forEach((suggestion) => {
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

        const normaliseConversation = (conversation) => {
            if (!Array.isArray(conversation)) {
                return [];
            }

            return conversation.reduce((entries, entry) => {
                if (!entry || typeof entry !== 'object') {
                    return entries;
                }

                const role = entry.role === 'user' ? 'user' : entry.role === 'assistant' ? 'assistant' : '';
                const message = normaliseText(entry.message);
                if (role !== '' && message !== '') {
                    entries.push({ role, message, period: entry.period });
                }

                return entries;
            }, []);
        };

        const conversationsMatch = (first, second) => first.length === second.length && first.every((entry, index) => (
            entry.role === second[index].role && entry.message === second[index].message
        ));

        const renderedConversationMatches = (conversation) => {
            const renderedMessages = Array.from(messages.querySelectorAll('[data-numa-canonical-message="true"]'));

            return renderedMessages.length === conversation.length && renderedMessages.every((item, index) => (
                item.dataset.numaRole === conversation[index].role
                && item.dataset.numaMessage === conversation[index].message
            ));
        };

        const appendPeriod = (bubble, period) => {
            if (!period || typeof period !== 'object' || !period.start || !period.end) {
                return;
            }

            const start = formatSpanishDate(period.start);
            const end = formatSpanishDate(period.end);
            if (start === '' || end === '') {
                return;
            }

            const label = start === end ? `Periodo: ${start}` : `Periodo: ${start} a ${end}`;
            bubble.appendChild(createTextNode('p', 'bh-numa-message-meta', label));
        };

        const addMessage = (role, text, metadata) => {
            markConversationStarted();

            const canonicalRole = role === 'user' ? 'user' : 'assistant';
            const item = document.createElement('article');
            item.className = `bh-numa-message is-${canonicalRole}`;
            item.dataset.numaRole = canonicalRole;

            if (!metadata || !metadata.state) {
                item.dataset.numaCanonicalMessage = 'true';
                item.dataset.numaMessage = text;
            }

            if (metadata && metadata.tone) {
                item.classList.add(`is-${metadata.tone}`);
            }

            if (metadata && metadata.state) {
                item.classList.add('is-state');
                item.dataset.numaStateMessage = text;
            }

            const content = document.createElement('div');
            content.className = 'bh-numa-message-content';
            content.appendChild(createTextNode('p', '', text));

            if (canonicalRole === 'assistant' && metadata) {
                appendPeriod(content, metadata.period);
            }

            item.appendChild(content);
            messages.appendChild(item);
            scrollMessagesToEnd();

            return item;
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

        const removeThinkingMessage = () => {
            if (thinkingMessage && thinkingMessage.isConnected) {
                thinkingMessage.remove();
            }

            thinkingMessage = null;
        };

        const showThinkingMessage = () => {
            removeThinkingMessage();
            thinkingMessage = addMessage('assistant', 'Pensando…', {
                state: true,
                tone: 'thinking',
            });
            thinkingMessage.classList.add('is-thinking');
        };

        const cancelProgressiveResponse = (complete) => {
            window.clearTimeout(responseRevealTimer);
            responseRevealTimer = 0;

            if (!progressiveResponse) {
                return;
            }

            const { item, text, resolve } = progressiveResponse;
            if (item.isConnected) {
                if (complete) {
                    const paragraph = item.querySelector('.bh-numa-message-content > p');
                    if (paragraph) {
                        paragraph.textContent = text;
                    }
                } else {
                    item.remove();
                }
            }

            progressiveResponse = null;
            resolve();
        };

        const revealAssistantResponse = (text, metadata, requestId) => {
            cancelProgressiveResponse(false);
            const item = addMessage('assistant', '', metadata);
            const paragraph = item.querySelector('.bh-numa-message-content > p');
            const words = text.match(/\S+\s*/g) || [text];
            const delays = words.map((word) => {
                if (/[.!?…]$/.test(word.trim())) {
                    return 100;
                }

                return /[,;:]$/.test(word.trim()) ? 55 : 35;
            });
            const totalDelay = delays.reduce((total, delay) => total + delay, 0);
            const acceleration = totalDelay > 2750 ? 2750 / totalDelay : 1;
            let wordIndex = 0;

            return new Promise((resolve) => {
                progressiveResponse = { item, text, requestId, resolve };

                const revealNextWord = () => {
                    if (!progressiveResponse || progressiveResponse.requestId !== requestId || !paragraph) {
                        resolve();
                        return;
                    }

                    paragraph.textContent += words[wordIndex];
                    wordIndex += 1;

                    if (wordIndex >= words.length) {
                        responseRevealTimer = 0;
                        progressiveResponse = null;
                        resolve();
                        return;
                    }

                    responseRevealTimer = window.setTimeout(
                        revealNextWord,
                        Math.max(8, Math.round(delays[wordIndex - 1] * acceleration))
                    );
                };

                if (prefersReducedMotion()) {
                    paragraph.textContent = text;
                    progressiveResponse = null;
                    resolve();
                    return;
                }

                revealNextWord();
            });
        };

        const renderConversation = (conversation) => {
            removeThinkingMessage();
            cancelProgressiveResponse(false);
            messages.textContent = '';
            hasConversation = false;
            hasCanonicalConversation = false;
            canonicalConversation = normaliseConversation(conversation);

            canonicalConversation.forEach((entry) => {
                addMessage(entry.role, entry.message, { period: entry.period });
                hasCanonicalConversation = true;
            });

            if (!hasCanonicalConversation) {
                hasConversation = false;
                renderInitialState();
            }

            setInteractiveState();
        };

        const setAvailability = (value) => {
            availability = [
                'available',
                'near_limit',
                'limit_reached',
                'unavailable',
                'configuration_required',
            ].includes(value) ? value : 'unavailable';
        };

        const statusMessageForAvailability = (value) => ({
            near_limit: 'Te estás acercando al límite de uso.',
            limit_reached: 'Has alcanzado el límite de uso. Podrás volver a utilizarlo cuando se renueve.',
            configuration_required: 'Ahora no puedo atender consultas mientras termino de configurarme.',
            unavailable: 'Ahora no puedo atender consultas. Inténtalo de nuevo más tarde.',
        }[value] || '');

        const applyServiceStatus = (payload) => {
            const data = payload && typeof payload === 'object' ? payload.data : null;
            const nextAvailability = data && typeof data.availability === 'string' ? data.availability : 'unavailable';
            const conversation = normaliseConversation(data && Array.isArray(data.conversation) ? data.conversation : []);

            if (!conversationsMatch(conversation, canonicalConversation) || !renderedConversationMatches(conversation)) {
                renderConversation(conversation);
            }
            setAvailability(nextAvailability);
            const statusMessage = statusMessageForAvailability(availability);

            if (statusMessage !== '') {
                addStateMessage(
                    statusMessage,
                    availability === 'near_limit' || availability === 'limit_reached' ? 'warning' : 'error'
                );
            } else {
                announceStatus('Ya puedes hacer una consulta.');
            }

            setInteractiveState();

            if (panelOpen && canSend() && (document.activeElement === closeButton || document.activeElement === launcher)) {
                input.focus();
            }
        };

        const loadStatus = () => {
            if (statusLoading) {
                return;
            }

            if (!statusUrl) {
                setAvailability('unavailable');
                addStateMessage('No se ha podido comprobar el estado del servicio.', 'error');
                setStatusRetryVisible(true);
                setInteractiveState();
                return;
            }

            const requestId = statusRequestId + 1;
            statusRequestId = requestId;
            statusLoading = true;
            setAvailability('unavailable');
            announceStatus('Comprobando disponibilidad…');
            setStatusRetryVisible(true);
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
                        setAvailability('unavailable');
                        addStateMessage(
                            response.status === 401
                                ? isPublicMode
                                    ? 'No se ha podido validar tu identidad temporal. Recarga la página e inténtalo de nuevo.'
                                    : 'La sesión ha caducado. Vuelve a iniciar sesión.'
                                : 'No se ha podido comprobar el estado del servicio.',
                            'error'
                        );
                        setInteractiveState();
                        return;
                    }

                    applyServiceStatus(payload);
                    setStatusRetryVisible(false);
                })
                .catch(() => {
                    if (requestId !== statusRequestId) {
                        return;
                    }

                    setAvailability('unavailable');
                    addStateMessage('No se ha podido comprobar el estado del servicio.', 'error');
                    setInteractiveState();
                    setStatusRetryVisible(true);
                })
                .finally(() => {
                    if (requestId !== statusRequestId) {
                        return;
                    }

                    statusLoading = false;
                    statusRetryButton.disabled = false;
                });
        };

        const safeErrorMessage = (payload, statusCode) => {
            const code = payload && payload.error && typeof payload.error.code === 'string'
                ? payload.error.code
                : '';
            const message = payload && payload.error && typeof payload.error.message === 'string'
                ? payload.error.message
                : '';

            if (code === 'NUMA_INVALID_CSRF' || statusCode === 403) {
                return 'Solicitud no válida. Recarga la página e inténtalo de nuevo.';
            }

            if (statusCode === 401) {
                return isPublicMode
                    ? 'No se ha podido validar tu identidad temporal. Recarga la página e inténtalo de nuevo.'
                    : 'La sesión ha caducado. Vuelve a iniciar sesión.';
            }

            if (statusCode === 429 || code === 'NUMA_LIMIT_REACHED' || code === 'NUMA_RATE_LIMITED') {
                return code === 'NUMA_RATE_LIMITED'
                    ? 'Has enviado demasiadas consultas seguidas. Espera un momento antes de volver a intentarlo.'
                    : 'Has alcanzado el límite de uso. Podrás volver a utilizarlo cuando se renueve.';
            }

            if (code === 'NUMA_PROVIDER_TIMEOUT') {
                return 'He tardado demasiado en responder. La consulta podría haberse enviado y haber consumido cuota. Comprueba el estado antes de volver a intentarlo.';
            }

            if (code === 'NUMA_NOT_AVAILABLE' || statusCode === 503) {
                return 'Ahora no puedo atender consultas. Inténtalo de nuevo más tarde.';
            }

            if (message !== '') {
                return message;
            }

            return 'No he podido responder ahora. Inténtalo de nuevo en unos minutos.';
        };

        const resetComposer = () => {
            input.value = '';
            updateCounter();
            resizeInput();
        };

        const invalidateChatRequest = (completeProgressiveResponse = false) => {
            chatRequestId += 1;
            window.clearTimeout(activeRequestTimeout);
            activeRequestTimeout = 0;

            if (activeAbortController) {
                activeAbortController.abort();
            }

            activeAbortController = null;
            removeThinkingMessage();
            cancelProgressiveResponse(completeProgressiveResponse);
            setProcessing(false);
        };

        const presentChatResponse = (data, requestId) => {
            const message = normaliseText(data.message);
            const conversation = normaliseConversation(data.conversation);
            const lastEntry = conversation[conversation.length - 1];

            removeThinkingMessage();

            if (lastEntry && lastEntry.role === 'assistant' && lastEntry.message === message) {
                const previousConversation = conversation.slice(0, -1);
                if (!renderedConversationMatches(previousConversation)) {
                    renderConversation(previousConversation);
                }

                canonicalConversation = conversation;
                hasCanonicalConversation = true;
                return revealAssistantResponse(message, { period: lastEntry.period || data.period }, requestId);
            }

            if (conversation.length > 0 && !renderedConversationMatches(conversation)) {
                renderConversation(conversation);
            }

            if (lastEntry && lastEntry.role === 'assistant' && lastEntry.message === message) {
                return Promise.resolve();
            }

            canonicalConversation = conversation.concat({ role: 'assistant', message, period: data.period });
            hasCanonicalConversation = true;
            return revealAssistantResponse(message, { period: data.period }, requestId);
        };

        const sendMessage = (rawMessage) => {
            const message = normaliseText(rawMessage);

            if (activeRequest) {
                return;
            }

            if (!canSend()) {
                addStateMessage(
                    availability === 'limit_reached'
                        ? statusMessageForAvailability(availability)
                        : 'Ahora no puedo recibir otra consulta.',
                    'warning'
                );
                return;
            }

            if (message === '') {
                announceStatus('Escribe una consulta válida.');
                return;
            }

            if (message.length > maxMessageLength) {
                announceStatus(`La consulta no puede superar ${maxMessageLength} caracteres.`);
                return;
            }

            if (!chatUrl) {
                addStateMessage('No se ha podido iniciar la consulta. Conservamos tu borrador para que puedas volver a intentarlo.', 'error');
                return;
            }

            setProcessing(true);
            const abortController = typeof AbortController === 'function' ? new AbortController() : null;
            const requestId = chatRequestId + 1;
            chatRequestId = requestId;
            activeAbortController = abortController;
            activeRequestTimeout = abortController
                ? window.setTimeout(() => abortController.abort(), requestTimeoutMs)
                : 0;
            let chatRequest;

            try {
                chatRequest = fetch(chatUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                    },
                    signal: abortController ? abortController.signal : undefined,
                    body: JSON.stringify({ message }),
                });
            } catch {
                window.clearTimeout(activeRequestTimeout);
                activeRequestTimeout = 0;
                activeAbortController = null;
                setProcessing(false);
                addStateMessage('No se ha podido iniciar la consulta. Conservamos tu borrador para que puedas volver a intentarlo.', 'error');
                return;
            }

            addMessage('user', message);
            resetComposer();
            announceStatus('Estoy revisando tu consulta.');
            showThinkingMessage();

            chatRequest
                .then((response) => response.json().catch(() => null).then((payload) => ({ response, payload })))
                .then(({ response, payload }) => {
                    if (requestId !== chatRequestId) {
                        return null;
                    }

                    if (
                        !response.ok
                        || !payload
                        || payload.ok !== true
                        || !payload.data
                        || typeof payload.data.message !== 'string'
                        || normaliseText(payload.data.message) === ''
                    ) {
                        const errorMessage = safeErrorMessage(payload, response.status);
                        removeThinkingMessage();
                        addMessage('assistant', errorMessage, { tone: 'error', state: true });

                        if (response.status === 401 || response.status === 429) {
                            setAvailability('unavailable');
                        }
                        return null;
                    }

                    if (typeof payload.data.availability === 'string') {
                        setAvailability(payload.data.availability);
                    }

                    return presentChatResponse(payload.data, requestId);
                })
                .catch((error) => {
                    if (requestId !== chatRequestId) {
                        return;
                    }

                    removeThinkingMessage();
                    const errorMessage = error && error.name === 'AbortError'
                        ? 'He tardado demasiado en responder. La consulta podría haberse enviado y haber consumido cuota. Comprueba el estado antes de volver a intentarlo.'
                        : 'No he podido conectar en este momento. La consulta podría haberse enviado y haber consumido cuota. Comprueba el estado antes de volver a intentarlo.';
                    addMessage(
                        'assistant',
                        errorMessage,
                        { tone: 'error', state: true }
                    );
                })
                .finally(() => {
                    if (requestId !== chatRequestId) {
                        return;
                    }

                    window.clearTimeout(activeRequestTimeout);
                    activeRequestTimeout = 0;
                    activeAbortController = null;
                    removeThinkingMessage();
                    setProcessing(false);
                    loadStatus();
                });
        };

        const startNewConversation = () => {
            if (activeRequest || !hasCanonicalConversation || !newConversationUrl) {
                return;
            }

            removeThinkingMessage();
            cancelProgressiveResponse(false);
            activeRequest = true;
            newConversationButton.setAttribute('aria-busy', 'true');
            announceStatus('Empezamos una nueva conversación.');
            setInteractiveState();

            fetch(newConversationUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
            })
                .then((response) => response.json().catch(() => null).then((payload) => ({ response, payload })))
                .then(({ response, payload }) => {
                    if (!response.ok || !payload || payload.ok !== true || !payload.data) {
                        addStateMessage(safeErrorMessage(payload, response.status), 'error');
                        return;
                    }

                    applyServiceStatus(payload);
                    announceStatus('Nueva conversación iniciada.');
                })
                .catch(() => {
                    addStateMessage('No he podido iniciar una nueva conversación.', 'error');
                })
                .finally(() => {
                    activeRequest = false;
                    newConversationButton.removeAttribute('aria-busy');
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
            clearPanelTransitions();
            hideTooltip(true);
            panel.hidden = false;
            panel.classList.remove('is-numa-leaving');
            launcher.setAttribute('aria-expanded', 'true');
            launcher.setAttribute('aria-label', CLOSE_LABEL);
            widget.classList.add('is-numa-open');

            if (!prefersReducedMotion()) {
                panel.classList.add('is-numa-entering');
                panel.getBoundingClientRect();
                panelTransitionFrame = window.requestAnimationFrame(() => {
                    panelTransitionFrame = 0;
                    panel.classList.remove('is-numa-entering');
                });
            }

            renderInitialState();
            loadStatus();
            focusFirstPanelTarget(panel, closeButton);
        };

        const closePanel = (returnFocus) => {
            if (!panelOpen) {
                return;
            }

            if (activeRequest || thinkingMessage || progressiveResponse) {
                invalidateChatRequest(true);
            }

            panelOpen = false;
            defaultTooltipSuppressed = true;
            clearPanelTransitions();
            panel.classList.remove('is-numa-entering');
            launcher.setAttribute('aria-expanded', 'false');
            launcher.setAttribute('aria-label', OPEN_LABEL);
            widget.classList.remove('is-numa-open');

            if (prefersReducedMotion()) {
                finishPanelClose();
            } else {
                panel.classList.add('is-numa-leaving');
                panelCloseTimer = window.setTimeout(finishPanelClose, PANEL_TRANSITION_DURATION_MS + 40);
            }

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
        newConversationButton.addEventListener('click', startNewConversation);
        statusRetryButton.addEventListener('click', loadStatus);

        panel.addEventListener('transitionend', (event) => {
            if (event.target === panel && event.propertyName === 'opacity' && !panelOpen) {
                finishPanelClose();
            }
        });

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
