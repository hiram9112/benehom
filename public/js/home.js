/* BeneHom - Home publica: GSAP, revelado al scroll, menu movil y boton "volver arriba". */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    /* ---------------------------------------------------------------------
       GSAP + ScrollTrigger + SplitText: registro y conexion con Lenis.
       Solo se activa si GSAP esta disponible (cargado exclusivamente en home).
    --------------------------------------------------------------------- */
    var hasGsap = Boolean(window.gsap && window.ScrollTrigger);
    var hasSplitText = Boolean(window.SplitText);
    var scrollTriggerUpdateQueued = false;

    function requestScrollTriggerUpdate() {
        if (!hasGsap || scrollTriggerUpdateQueued) return;

        scrollTriggerUpdateQueued = true;
        window.requestAnimationFrame(function () {
            scrollTriggerUpdateQueued = false;
            ScrollTrigger.update();
        });
    }

    function refreshScrollTriggersAfterLayout() {
        if (!hasGsap || reduceMotion.matches) return;

        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                if (window.BHLenis && typeof window.BHLenis.resize === 'function') {
                    window.BHLenis.resize();
                }

                ScrollTrigger.refresh(true);
                requestScrollTriggerUpdate();
            });
        });
    }

    function whenWindowLoaded() {
        if (document.readyState === 'complete') {
            return Promise.resolve();
        }

        return new Promise(function (resolve) {
            window.addEventListener('load', resolve, { once: true });
        });
    }

    function whenFontsReady() {
        if (!document.fonts || !document.fonts.ready) {
            return Promise.resolve();
        }

        return document.fonts.ready.catch(function () {});
    }

    if (hasGsap) {
        gsap.registerPlugin(ScrollTrigger);
        if (hasSplitText) {
            gsap.registerPlugin(SplitText);
        }

        if (!reduceMotion.matches && window.BHLenis) {
            window.BHLenis.on('scroll', requestScrollTriggerUpdate);
        }
    }

    /* ---------------------------------------------------------------------
       Seccion de proposito: "Entender tu dinero no es un lujo..."
       Titulo por palabras con SplitText + ScrollTrigger.
    --------------------------------------------------------------------- */
    if (hasGsap && hasSplitText && !reduceMotion.matches) {
        var beliefSection = document.querySelector('.bh-home-belief');

        if (beliefSection) {
            var beliefTitle = beliefSection.querySelector('h2');
            var beliefInner = beliefSection.querySelector('.bh-home-belief-inner');
            var beliefParagraphs = beliefInner ? beliefInner.querySelectorAll(':scope > p') : [];

            var wordSplit = new SplitText(beliefTitle, { type: 'words' });
            gsap.set(wordSplit.words, { opacity: 0, y: 20, filter: 'blur(5px)' });

            var beliefTl = gsap.timeline({
                scrollTrigger: {
                    trigger: beliefSection,
                    start: 'top 80%',
                    toggleActions: 'play none none none'
                }
            });

            beliefTl.to(wordSplit.words, {
                opacity: 1,
                y: 0,
                filter: 'blur(0px)',
                duration: 0.8,
                stagger: 0.065,
                ease: 'power3.out',
                onComplete: function () {
                    wordSplit.revert();
                }
            });

            if (beliefParagraphs.length) {
                gsap.set(beliefParagraphs, { opacity: 0, y: 12 });
                beliefTl.to(beliefParagraphs, {
                    opacity: 1,
                    y: 0,
                    duration: 0.5,
                    stagger: 0.06,
                    ease: 'power3.out'
                }, '-=0.25');
            }
        }
    }

    /* ---------------------------------------------------------------------
       Seccion de seguridad: "Tus finanzas, bajo tu control".
       Bloque izquierdo + tarjetas con stagger.
    --------------------------------------------------------------------- */
    if (hasGsap && !reduceMotion.matches) {
        var securitySection = document.querySelector('.bh-home-security');

        if (securitySection) {
            var securityIntro = securitySection.querySelector('.bh-home-security-intro');
            var securityCards = securitySection.querySelectorAll('.bh-home-security-list > li');

            if (securityIntro) {
                gsap.set(securityIntro, { opacity: 0, y: 18, filter: 'blur(3px)' });

                gsap.to(securityIntro, {
                    opacity: 1,
                    y: 0,
                    filter: 'blur(0px)',
                    duration: 0.55,
                    ease: 'power3.out',
                    scrollTrigger: {
                        trigger: securitySection,
                        start: 'top 75%',
                        toggleActions: 'play none none none'
                    }
                });
            }

            if (securityCards.length) {
                gsap.set(securityCards, { opacity: 0, y: 20, filter: 'blur(3px)' });

                gsap.to(securityCards, {
                    opacity: 1,
                    y: 0,
                    filter: 'blur(0px)',
                    duration: 0.5,
                    stagger: 0.08,
                    ease: 'power3.out',
                    scrollTrigger: {
                        trigger: securitySection,
                        start: 'top 70%',
                        toggleActions: 'play none none none'
                    }
                });
            }
        }
    }

    /* ---------------------------------------------------------------------
       Mockups de funciones: entrada con ScrollTrigger.
       Solo el area visual; el texto permanece estatico.
    --------------------------------------------------------------------- */
    if (hasGsap && !reduceMotion.matches) {
        var features = document.querySelectorAll('.bh-home-feature');

        features.forEach(function (feature) {
            var art = feature.querySelector('.bh-home-feature-art');
            if (!art) return;

            var bars = art.querySelectorAll('.bh-home-mock-bar span');
            var svgBars = art.querySelectorAll('.bh-home-chart-svg rect');
            var svgLine = art.querySelector('.bh-home-chart-svg path[stroke]');
            var svgArea = art.querySelector('.bh-home-chart-svg path[fill^="url"]');

            gsap.set(art, { opacity: 0, y: 16 });

            var featureTl = gsap.timeline({
                scrollTrigger: {
                    trigger: feature,
                    start: 'top 75%',
                    toggleActions: 'play none none none'
                }
            });

            featureTl.to(art, {
                opacity: 1,
                y: 0,
                duration: 0.55,
                ease: 'power3.out'
            });

            if (bars.length) {
                gsap.set(bars, { scaleX: 0 });
                featureTl.to(bars, {
                    scaleX: 1,
                    duration: 0.6,
                    stagger: 0.04,
                    ease: 'power2.out'
                }, '-=0.2');
            }

            if (svgBars.length) {
                gsap.set(svgBars, { scaleY: 0, transformOrigin: 'bottom center' });
                featureTl.to(svgBars, {
                    scaleY: 1,
                    duration: 0.5,
                    stagger: 0.04,
                    ease: 'power2.out'
                }, '-=0.2');
            }

            if (svgLine) {
                var length = svgLine.getTotalLength();
                gsap.set(svgLine, {
                    strokeDasharray: length,
                    strokeDashoffset: length
                });
                featureTl.to(svgLine, {
                    strokeDashoffset: 0,
                    duration: 0.8,
                    ease: 'power2.out'
                }, '-=0.3');
            }

            if (svgArea) {
                gsap.set(svgArea, { opacity: 0 });
                featureTl.to(svgArea, {
                    opacity: 1,
                    duration: 0.5,
                    ease: 'power2.out'
                }, '-=0.4');
            }
        });
    }

    /* ---------------------------------------------------------------------
       Como funciona: tres pasos como grupo con stagger corto.
    --------------------------------------------------------------------- */
    if (hasGsap && !reduceMotion.matches) {
        var steps = document.querySelectorAll('.bh-home-steps > li');

        if (steps.length) {
            gsap.set(steps, { opacity: 0, y: 12 });

            gsap.to(steps, {
                opacity: 1,
                y: 0,
                duration: 0.45,
                stagger: 0.08,
                ease: 'power2.out',
                scrollTrigger: {
                    trigger: '.bh-home-steps',
                    start: 'top 75%',
                    toggleActions: 'play none none none'
                }
            });
        }
    }

    if (hasGsap && !reduceMotion.matches) {
        Promise.all([whenWindowLoaded(), whenFontsReady()]).then(refreshScrollTriggersAfterLayout);
        window.addEventListener('pageshow', refreshScrollTriggersAfterLayout);
    }

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
            if (window.BHLenis && typeof window.BHLenis.scrollTo === 'function') {
                window.BHLenis.scrollTo(0);
            } else {
                window.scrollTo({ top: 0, behavior: reduceMotion.matches ? 'auto' : 'smooth' });
            }

            var brand = document.querySelector('.bh-home-brand');
            if (brand) { brand.focus({ preventScroll: true }); }
        });
    }

    /* ---------------------------------------------------------------------
       Cierra el offcanvas de la home sin interceptar el salto a la seccion.
    --------------------------------------------------------------------- */
    var homeMobileMenu = document.getElementById('bh-home-mobile-menu');
    if (homeMobileMenu && window.bootstrap && window.bootstrap.Offcanvas) {
        Array.prototype.forEach.call(homeMobileMenu.querySelectorAll('a[href^="#"]'), function (link) {
            link.addEventListener('click', function () {
                var menu = window.bootstrap.Offcanvas.getInstance(homeMobileMenu);
                if (menu) { menu.hide(); }
            });
        });
    }

    /* ---------------------------------------------------------------------
       Preguntas frecuentes: deja abierta solo la ultima desplegada.
    --------------------------------------------------------------------- */
    var faqList = document.querySelector('.bh-home-faq-list');
    if (faqList) {
        var faqItems = Array.prototype.slice.call(faqList.querySelectorAll('details'));
        var faqAnimationDuration = 340;
        var faqAnimationEasing = 'cubic-bezier(0.16, 1, 0.3, 1)';

        function resetFaqItem(item) {
            if (item._bhFaqAnimation) {
                item._bhFaqAnimation.cancel();
                item._bhFaqAnimation = null;
            }

            item.style.height = '';
            item.style.overflow = '';
            item.classList.remove('is-closing');
        }

        function openFaqItem(item) {
            if (item.open && !item._bhFaqAnimation) { return; }
            resetFaqItem(item);

            if (reduceMotion.matches || !item.animate) {
                item.open = true;
                return;
            }

            var startHeight = item.getBoundingClientRect().height;
            item.open = true;
            var endHeight = item.getBoundingClientRect().height;
            item.style.height = startHeight + 'px';
            item.style.overflow = 'hidden';

            var animation = item.animate([
                { height: startHeight + 'px' },
                { height: endHeight + 'px' }
            ], {
                duration: faqAnimationDuration,
                easing: faqAnimationEasing,
                fill: 'forwards'
            });

            item._bhFaqAnimation = animation;
            animation.onfinish = function () {
                if (item._bhFaqAnimation !== animation) { return; }
                item.style.height = endHeight + 'px';
                resetFaqItem(item);
            };
        }

        function closeFaqItem(item) {
            if (!item.open) { return; }

            if (reduceMotion.matches || !item.animate) {
                resetFaqItem(item);
                item.open = false;
                return;
            }

            var startHeight = item.getBoundingClientRect().height;
            resetFaqItem(item);
            item.classList.add('is-closing');

            item.open = false;
            var endHeight = item.getBoundingClientRect().height;
            item.open = true;
            item.style.height = startHeight + 'px';
            item.style.overflow = 'hidden';

            var animation = item.animate([
                { height: startHeight + 'px' },
                { height: endHeight + 'px' }
            ], {
                duration: faqAnimationDuration,
                easing: faqAnimationEasing,
                fill: 'forwards'
            });

            item._bhFaqAnimation = animation;
            animation.onfinish = function () {
                if (item._bhFaqAnimation !== animation) { return; }
                item.style.height = endHeight + 'px';
                item.open = false;
                resetFaqItem(item);
            };
        }

        faqItems.forEach(function (item) {
            var summary = item.querySelector('summary');
            if (!summary) { return; }

            summary.addEventListener('click', function (e) {
                e.preventDefault();

                if (item.open) {
                    closeFaqItem(item);
                    return;
                }

                faqItems.forEach(function (other) {
                    if (other !== item && other.open) {
                        closeFaqItem(other);
                    }
                });

                openFaqItem(item);
            });
        });
    }
})();
