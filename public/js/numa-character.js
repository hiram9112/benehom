(function () {
    'use strict';

    const initialiseStaticCharacter = (launcher) => {
        const staticCharacter = launcher.querySelector('[data-numa-static]');

        if (staticCharacter) {
            staticCharacter.hidden = false;
        }
    };

    const initialise = () => {
        document.querySelectorAll('[data-numa-launcher]').forEach(initialiseStaticCharacter);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise, { once: true });
        return;
    }

    initialise();
}());
