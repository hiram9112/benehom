(function () {
    'use strict';

    const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';
    const BLINK_MIN_DELAY = 2600;
    const BLINK_MAX_DELAY = 4700;
    const AUTO_WAVE_MIN_DELAY = 10000;
    const AUTO_WAVE_MAX_DELAY = 15000;
    const WAVE_FRAME_DURATION = 0.022;

    const parseFrames = (value) => {
        if (!value) {
            return [];
        }

        try {
            const frames = JSON.parse(value);

            return Array.isArray(frames) ? frames.filter((frame) => typeof frame === 'string' && frame !== '') : [];
        } catch (error) {
            return [];
        }
    };

    const preloadImage = (src) => new Promise((resolve, reject) => {
        const image = new Image();

        image.decoding = 'async';
        image.onload = () => {
            if (image.naturalWidth > 0 && image.naturalHeight > 0) {
                resolve(image);
                return;
            }

            reject(new Error('Numa image has invalid dimensions.'));
        };
        image.onerror = () => reject(new Error('Numa image failed to load.'));
        image.src = src;
    });

    const setImage = (image, src) => {
        if (image && src && image.getAttribute('src') !== src) {
            image.src = src;
        }
    };

    const randomBlinkDelay = () => BLINK_MIN_DELAY + Math.round(Math.random() * (BLINK_MAX_DELAY - BLINK_MIN_DELAY));
    const randomAutoWaveDelay = () => AUTO_WAVE_MIN_DELAY + Math.round(Math.random() * (AUTO_WAVE_MAX_DELAY - AUTO_WAVE_MIN_DELAY));

    const bindMotionChange = (mediaQuery, callback) => {
        if (!mediaQuery) {
            return;
        }

        if (typeof mediaQuery.addEventListener === 'function') {
            mediaQuery.addEventListener('change', callback);
            return;
        }

        if (typeof mediaQuery.addListener === 'function') {
            mediaQuery.addListener(callback);
        }
    };

    const initialiseCharacter = (launcher) => {
        const staticCharacter = launcher.querySelector('[data-numa-static]');
        const animatedCharacter = launcher.querySelector('[data-numa-animated]');
        const bodyImage = launcher.querySelector('[data-numa-body]');
        const faceImage = launcher.querySelector('[data-numa-face]');
        const armImage = launcher.querySelector('[data-numa-arm]');
        const mediaQuery = typeof window.matchMedia === 'function' ? window.matchMedia(REDUCED_MOTION_QUERY) : null;
        const gsap = window.gsap;

        if (staticCharacter) {
            staticCharacter.hidden = false;
        }

        if (!staticCharacter || !animatedCharacter || !bodyImage || !faceImage || !armImage || !gsap) {
            return;
        }

        const bodySrc = animatedCharacter.getAttribute('data-numa-body-src') || '';
        const faceFrames = parseFrames(animatedCharacter.getAttribute('data-numa-face-frames'));
        const armFrames = parseFrames(animatedCharacter.getAttribute('data-numa-arm-frames'));
        const requiredAssets = [bodySrc].concat(faceFrames, armFrames);

        let active = false;
        let failed = false;
        let loaded = false;
        let loading = false;
        let hovering = false;
        let focusing = false;
        let interactionEngaged = false;
        let armFrameIndex = 0;
        let blinkTimer = 0;
        let autoWaveTimer = 0;
        let blinkTimeline = null;
        let waveTimeline = null;

        const hasReducedMotion = () => Boolean(mediaQuery && mediaQuery.matches);

        const showStaticCharacter = () => {
            active = false;
            animatedCharacter.hidden = true;
            staticCharacter.hidden = false;
        };

        const restoreNeutralFace = () => {
            setImage(faceImage, faceFrames[0]);
        };

        const setArmFrame = (frameIndex) => {
            if (!armFrames[frameIndex]) {
                return;
            }

            armFrameIndex = frameIndex;
            setImage(armImage, armFrames[frameIndex]);
        };

        const restoreNeutralArm = () => {
            setArmFrame(0);
        };

        const restoreNeutralPose = () => {
            restoreNeutralFace();
            restoreNeutralArm();
        };

        const killTimeline = (timeline) => {
            if (timeline) {
                timeline.kill();
            }

            return null;
        };

        const stopAnimations = () => {
            window.clearTimeout(blinkTimer);
            window.clearTimeout(autoWaveTimer);
            blinkTimer = 0;
            autoWaveTimer = 0;
            blinkTimeline = killTimeline(blinkTimeline);
            waveTimeline = killTimeline(waveTimeline);
            interactionEngaged = false;

            if (loaded) {
                restoreNeutralPose();
            }
        };

        const scheduleBlink = () => {
            window.clearTimeout(blinkTimer);
            blinkTimer = 0;

            if (!active || hasReducedMotion() || failed) {
                return;
            }

            blinkTimer = window.setTimeout(() => {
                blinkTimer = 0;
                playBlink();
            }, randomBlinkDelay());
        };

        const completeBlink = () => {
            restoreNeutralFace();
            blinkTimeline = null;
            scheduleBlink();
        };

        const playBlink = () => {
            if (!active || hasReducedMotion() || failed || blinkTimeline || faceFrames.length < 3) {
                scheduleBlink();
                return;
            }

            blinkTimeline = gsap.timeline({ onComplete: completeBlink });
            blinkTimeline
                .call(() => setImage(faceImage, faceFrames[1]))
                .to({}, { duration: 0.055 })
                .call(() => setImage(faceImage, faceFrames[2]))
                .to({}, { duration: 0.075 })
                .call(() => setImage(faceImage, faceFrames[1]))
                .to({}, { duration: 0.055 });
        };

        const appendArmFrames = (timeline, frameIndexes) => {
            frameIndexes.forEach((frameIndex) => {
                timeline
                    .call(() => setArmFrame(frameIndex))
                    .to({}, { duration: WAVE_FRAME_DURATION });
            });
        };

        const interactionActive = () => hovering || focusing;

        const hasKeyboardFocus = () => {
            try {
                return launcher.matches(':focus-visible');
            } catch (error) {
                return document.activeElement === launcher;
            }
        };

        const armPathTo = (targetFrameIndex) => {
            const path = [];
            const direction = targetFrameIndex > armFrameIndex ? 1 : -1;

            for (
                let frameIndex = armFrameIndex + direction;
                direction > 0 ? frameIndex <= targetFrameIndex : frameIndex >= targetFrameIndex;
                frameIndex += direction
            ) {
                path.push(frameIndex);
            }

            return path;
        };

        const playArmTo = (targetFrameIndex, onComplete) => {
            waveTimeline = killTimeline(waveTimeline);

            const frameIndexes = armPathTo(targetFrameIndex);
            if (frameIndexes.length === 0) {
                onComplete();
                return;
            }

            waveTimeline = gsap.timeline({ onComplete });
            appendArmFrames(waveTimeline, frameIndexes);
        };

        const scheduleAutoWave = () => {
            window.clearTimeout(autoWaveTimer);
            autoWaveTimer = 0;

            if (!active || hasReducedMotion() || failed) {
                return;
            }

            autoWaveTimer = window.setTimeout(() => {
                autoWaveTimer = 0;

                if (interactionActive() || !playAutomaticWave(3)) {
                    scheduleAutoWave();
                }
            }, randomAutoWaveDelay());
        };

        const appendFullWaveCycle = (timeline) => {
            const upFrames = armFrames.slice(1).map((frame, index) => index + 1);
            const downFrames = armFrames.slice(0, -1).map((frame, index) => index).reverse();

            appendArmFrames(timeline, upFrames);
            appendArmFrames(timeline, downFrames);
        };

        const completeAutomaticWave = () => {
            restoreNeutralArm();
            waveTimeline = null;
            scheduleAutoWave();
        };

        const playAutomaticWave = (cycles) => {
            if (!active || hasReducedMotion() || failed || interactionActive() || waveTimeline || armFrames.length < 4) {
                return false;
            }

            restoreNeutralArm();
            waveTimeline = gsap.timeline({ onComplete: completeAutomaticWave });
            for (let index = 0; index < cycles; index += 1) {
                appendFullWaveCycle(waveTimeline);
            }

            return true;
        };

        const completeInteractionRaise = () => {
            waveTimeline = null;

            if (!interactionActive()) {
                lowerArmAfterInteraction();
            }
        };

        const completeInteractionLower = () => {
            waveTimeline = null;

            if (interactionActive()) {
                raiseArmForInteraction();
                return;
            }

            restoreNeutralArm();
            scheduleAutoWave();
        };

        const raiseArmForInteraction = () => {
            if (!active || hasReducedMotion() || failed) {
                return;
            }

            window.clearTimeout(autoWaveTimer);
            autoWaveTimer = 0;
            playArmTo(armFrames.length - 1, completeInteractionRaise);
        };

        const lowerArmAfterInteraction = () => {
            if (!active || hasReducedMotion() || failed) {
                return;
            }

            playArmTo(0, completeInteractionLower);
        };

        const syncInteraction = (force) => {
            const engaged = interactionActive();

            if (!force && engaged === interactionEngaged) {
                return;
            }

            interactionEngaged = engaged;
            if (engaged) {
                raiseArmForInteraction();
                return;
            }

            lowerArmAfterInteraction();
        };

        launcher.addEventListener('pointerenter', () => {
            hovering = true;
            syncInteraction(false);
        });
        launcher.addEventListener('pointerleave', () => {
            hovering = false;
            syncInteraction(false);
        });
        launcher.addEventListener('focus', () => {
            focusing = hasKeyboardFocus();
            syncInteraction(false);
        });
        launcher.addEventListener('blur', () => {
            focusing = false;
            syncInteraction(false);
        });

        const showAnimatedCharacter = () => {
            setImage(bodyImage, bodySrc);
            restoreNeutralPose();
            animatedCharacter.hidden = false;
            staticCharacter.hidden = true;
            active = true;
            scheduleBlink();
            syncInteraction(true);
        };

        const activate = () => {
            if (failed || hasReducedMotion()) {
                stopAnimations();
                showStaticCharacter();
                return;
            }

            if (loaded) {
                showAnimatedCharacter();
                return;
            }

            if (loading || requiredAssets.some((asset) => asset === '')) {
                showStaticCharacter();
                return;
            }

            loading = true;
            Promise.all(requiredAssets.map(preloadImage))
                .then(() => {
                    loaded = true;
                    loading = false;
                    activate();
                })
                .catch(() => {
                    failed = true;
                    loading = false;
                    stopAnimations();
                    showStaticCharacter();
                });
        };

        bindMotionChange(mediaQuery, () => {
            if (hasReducedMotion()) {
                stopAnimations();
                showStaticCharacter();
                return;
            }

            activate();
        });

        activate();
    };

    const initialise = () => {
        document.querySelectorAll('[data-numa-launcher]').forEach(initialiseCharacter);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise, { once: true });
        return;
    }

    initialise();
}());
