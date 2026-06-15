/* BeneHom - Home publica: revelado al scroll, menu movil y boton "volver arriba". */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    /* ---------------------------------------------------------------------
       Revelado de secciones al entrar en pantalla.
       El contenido es visible por defecto; solo se oculta si se aplico la
       clase .bh-js (script en <head>), que solo ocurre con animacion activa.
    --------------------------------------------------------------------- */
    var reveals = Array.prototype.slice.call(document.querySelectorAll('.bh-reveal'));

    function revealAll() {
        reveals.forEach(function (el) { el.classList.add('is-in'); });
    }

    if (!('IntersectionObserver' in window) || reduceMotion.matches) {
        revealAll();
    } else {
        var io = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-in');
                    obs.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -10% 0px', threshold: 0.12 });

        reveals.forEach(function (el) { io.observe(el); });

        // Si la pestana se abre en segundo plano, revela lo que ya este en pantalla al volver.
        window.addEventListener('load', function () {
            reveals.forEach(function (el) {
                var r = el.getBoundingClientRect();
                if (r.top < window.innerHeight && r.bottom > 0) {
                    el.classList.add('is-in');
                }
            });
        });
    }

    /* ---------------------------------------------------------------------
       Boton "volver arriba".
    --------------------------------------------------------------------- */
    var topBtn = document.querySelector('.bh-home-top');
    if (topBtn) {
        topBtn.hidden = false;
        var ticking = false;

        function updateTopBtn() {
            ticking = false;
            topBtn.classList.toggle('is-shown', window.scrollY > 500);
        }

        window.addEventListener('scroll', function () {
            if (!ticking) {
                window.requestAnimationFrame(updateTopBtn);
                ticking = true;
            }
        }, { passive: true });
        updateTopBtn();

        topBtn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: reduceMotion.matches ? 'auto' : 'smooth' });
            var brand = document.querySelector('.bh-home-brand');
            if (brand) { brand.focus({ preventScroll: true }); }
        });
    }

    /* ---------------------------------------------------------------------
       Menu de navegacion en movil.
    --------------------------------------------------------------------- */
    var burger = document.querySelector('.bh-home-burger');
    var panel = document.getElementById('bh-home-mobile');
    var nav = document.querySelector('.bh-home-nav');

    if (burger && panel && nav) {
        function setMenu(open) {
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
            burger.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
            panel.hidden = !open;
            nav.classList.toggle('is-open', open);
        }

        burger.addEventListener('click', function () {
            setMenu(burger.getAttribute('aria-expanded') !== 'true');
        });

        Array.prototype.forEach.call(panel.querySelectorAll('a'), function (a) {
            a.addEventListener('click', function () { setMenu(false); });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && burger.getAttribute('aria-expanded') === 'true') {
                setMenu(false);
                burger.focus();
            }
        });

        document.addEventListener('click', function (e) {
            if (burger.getAttribute('aria-expanded') === 'true' && !nav.contains(e.target)) {
                setMenu(false);
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 991 && burger.getAttribute('aria-expanded') === 'true') {
                setMenu(false);
            }
        });
    }
})();
