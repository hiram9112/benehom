(function () {
    'use strict';

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

    document.querySelectorAll('[data-numa-launcher]').forEach((launcher) => {
        const staticCharacter = launcher.querySelector('[data-numa-static]');
        const hybridCharacter = launcher.querySelector('[data-numa-hybrid]');
        const baseImage = launcher.querySelector('.bh-numa-launcher-base');
        const face = launcher.querySelector('.bh-numa-face');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

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
}());
