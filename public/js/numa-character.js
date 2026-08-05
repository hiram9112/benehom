(function () {
    'use strict';

    const states = Object.freeze([
        'idle',
        'hover',
        'focus',
        'open',
        'thinking',
        'answer',
        'unavailable',
        'limit-reached',
    ]);
    const stateSet = new Set(states);
    const stableStates = new Set(['idle', 'open', 'unavailable', 'limit-reached']);
    const transientStateTimeouts = Object.freeze({
        thinking: 25000,
        answer: 1200,
    });
    const animationChannels = Object.freeze(['ambient', 'transition']);
    const controllers = new WeakMap();
    const requiredFaceLayers = [
        '.numa-face',
        '.numa-brow-left',
        '.numa-brow-right',
        '.numa-eye-left',
        '.numa-eye-right',
        '.numa-pupil-left',
        '.numa-pupil-right',
        '.numa-highlight-left',
        '.numa-highlight-right',
        '.numa-eyelid-left',
        '.numa-eyelid-right',
        '.numa-mouth',
    ];

    const createStateController = (launcher, animationTargets) => {
        const animations = {
            ambient: null,
            transition: null,
        };
        const timers = {
            ambient: null,
            transition: null,
        };
        const initialState = stateSet.has(launcher.dataset.numaState)
            ? launcher.dataset.numaState
            : (launcher.dataset.available === 'false' ? 'unavailable' : 'idle');
        let currentState = initialState;
        let stableState = stableStates.has(initialState) ? initialState : 'idle';

        const gsapInstance = () => (
            typeof window.gsap !== 'undefined'
            && typeof window.gsap.killTweensOf === 'function'
                ? window.gsap
                : null
        );

        const clearTimer = (channel) => {
            if (timers[channel] === null) {
                return;
            }

            window.clearTimeout(timers[channel]);
            timers[channel] = null;
        };

        const stopAnimation = (channel) => {
            const animation = animations[channel];
            if (animation && typeof animation.kill === 'function') {
                animation.kill();
            }

            animations[channel] = null;
        };

        const resetAnimatedProperties = () => {
            const gsap = gsapInstance();
            if (!gsap || animationTargets.length === 0) {
                return;
            }

            gsap.killTweensOf(animationTargets);
            if (typeof gsap.set === 'function') {
                gsap.set(animationTargets, { clearProps: 'all' });
            }
        };

        const clearActivity = () => {
            animationChannels.forEach((channel) => {
                clearTimer(channel);
                stopAnimation(channel);
            });
            resetAnimatedProperties();
        };

        const schedule = (channel, callback, delay) => {
            if (!animationChannels.includes(channel) || typeof callback !== 'function') {
                return null;
            }

            clearTimer(channel);
            timers[channel] = window.setTimeout(() => {
                timers[channel] = null;
                callback();
            }, delay);

            return timers[channel];
        };

        const registerAnimation = (channel, animation) => {
            if (!animationChannels.includes(channel)) {
                return false;
            }

            stopAnimation(channel);
            animations[channel] = animation || null;

            return true;
        };

        const setState = (nextState) => {
            if (!stateSet.has(nextState)) {
                return false;
            }

            clearActivity();
            currentState = nextState;
            launcher.dataset.numaState = nextState;

            if (stableStates.has(nextState)) {
                stableState = nextState;
            }

            if (Object.prototype.hasOwnProperty.call(transientStateTimeouts, nextState)) {
                schedule('transition', () => {
                    if (currentState === nextState) {
                        setState(stableState);
                    }
                }, transientStateTimeouts[nextState]);
            }

            return true;
        };

        launcher.dataset.numaState = currentState;

        return Object.freeze({
            getState: () => currentState,
            registerAnimation,
            schedule,
            setState,
        });
    };

    document.querySelectorAll('[data-numa-launcher]').forEach((launcher) => {
        const staticCharacter = launcher.querySelector('[data-numa-static]');
        const hybridCharacter = launcher.querySelector('[data-numa-hybrid]');
        const baseImage = launcher.querySelector('.bh-numa-launcher-base');
        const face = launcher.querySelector('.bh-numa-face');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const animationTargets = [
            hybridCharacter,
            ...requiredFaceLayers.map((selector) => face && face.querySelector(selector)),
        ].filter(Boolean);
        const stateController = createStateController(launcher, animationTargets);

        controllers.set(launcher, stateController);

        const showStaticFallback = () => {
            if (staticCharacter) {
                staticCharacter.hidden = false;
            }

            if (hybridCharacter) {
                hybridCharacter.hidden = true;
            }

            launcher.classList.remove('is-character-ready');
        };

        const hasAnimationSupport = () => (
            !reducedMotion.matches
            && typeof window.gsap !== 'undefined'
            && typeof window.gsap.to === 'function'
            && face !== null
            && requiredFaceLayers.every((selector) => face.querySelector(selector) !== null)
        );

        const showHybridCharacter = () => {
            if (!staticCharacter || !hybridCharacter || !baseImage || !hasAnimationSupport()) {
                showStaticFallback();
                return;
            }

            if (!baseImage.complete) {
                return;
            }

            if (baseImage.naturalWidth === 0) {
                showStaticFallback();
                return;
            }

            hybridCharacter.hidden = false;
            staticCharacter.hidden = true;
            launcher.classList.add('is-character-ready');
        };

        showStaticFallback();

        if (baseImage) {
            baseImage.addEventListener('load', showHybridCharacter, { once: true });
            baseImage.addEventListener('error', showStaticFallback, { once: true });
        }

        showHybridCharacter();
        reducedMotion.addEventListener('change', () => {
            if (reducedMotion.matches) {
                showStaticFallback();
                return;
            }

            showHybridCharacter();
        });
    });

    const resolveLauncher = (launcher) => (
        launcher instanceof Element
            ? launcher
            : document.querySelector('[data-numa-launcher]')
    );

    window.BHNumaCharacter = Object.freeze({
        states,
        getState(launcher) {
            const controller = controllers.get(resolveLauncher(launcher));

            return controller ? controller.getState() : null;
        },
        registerAnimation(channel, animation, launcher) {
            const controller = controllers.get(resolveLauncher(launcher));

            return controller ? controller.registerAnimation(channel, animation) : false;
        },
        schedule(channel, callback, delay, launcher) {
            const controller = controllers.get(resolveLauncher(launcher));

            return controller ? controller.schedule(channel, callback, delay) : null;
        },
        setState(state, launcher) {
            const controller = controllers.get(resolveLauncher(launcher));

            return controller ? controller.setState(state) : false;
        },
    });
}());
