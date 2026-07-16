(function () {
    'use strict';

    if (!window.gsap) return;

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (reduceMotion.matches) return;

    var card = document.querySelector('.bh-auth-card');
    if (!card) return;

    gsap.set(card, { opacity: 0, y: 20 });

    gsap.to(card, {
        opacity: 1,
        y: 0,
        duration: 0.8,
        ease: 'power3.out'
    });
})();
