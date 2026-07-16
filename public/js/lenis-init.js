/* BeneHom - inicializacion global de Lenis. */
(function bhLenisInitModule() {
  'use strict';

  var REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';
  var TOUCH_INPUT_QUERY = '(hover: none), (pointer: coarse)';
  var reducedMotion = window.matchMedia ? window.matchMedia(REDUCED_MOTION_QUERY) : null;
  var touchInput = window.matchMedia ? window.matchMedia(TOUCH_INPUT_QUERY) : null;

  function prefersReducedMotion() {
    return Boolean(reducedMotion && reducedMotion.matches);
  }

  function usesTouchInput() {
    return Boolean(
      (touchInput && touchInput.matches) ||
      (window.navigator && window.navigator.maxTouchPoints > 0)
    );
  }

  function easeOutCubic(t) {
    return 1 - Math.pow(1 - t, 3);
  }

  function destroyLenis() {
    if (!window.BHLenis || typeof window.BHLenis.destroy !== 'function') return;

    window.BHLenis.destroy();
    window.BHLenis = null;
  }

  function hasOpenBlockingOverlay() {
    return Boolean(document.querySelector('.modal.show, .offcanvas.show'));
  }

  function stopLenis() {
    if (!window.BHLenis || typeof window.BHLenis.stop !== 'function') return;

    window.BHLenis.stop();
  }

  function resumeLenis() {
    if (!window.BHLenis || typeof window.BHLenis.start !== 'function' || hasOpenBlockingOverlay()) return;

    window.BHLenis.start();
  }

  function startLenis() {
    if (!window.Lenis || window.BHLenis || prefersReducedMotion() || usesTouchInput()) return;

    window.BHLenis = new window.Lenis({
      autoRaf: true,
      smoothWheel: true,
      duration: 1,
      easing: easeOutCubic,
      anchors: true,
      syncTouch: false,
      stopInertiaOnNavigate: true
    });

    // Si se incorpora GSAP/ScrollTrigger en la home, sustituir autoRaf por el ticker compartido de GSAP; nunca ejecutar ambos a la vez.

    if (hasOpenBlockingOverlay()) stopLenis();
  }

  function handleReducedMotionChange() {
    if (prefersReducedMotion()) {
      destroyLenis();
      return;
    }

    startLenis();
  }

  function handlePageHide() {
    destroyLenis();
  }

  function handleOverlayShow() {
    stopLenis();
  }

  function handleOverlayHidden() {
    resumeLenis();
  }

  startLenis();

  if (reducedMotion) {
    if (typeof reducedMotion.addEventListener === 'function') {
      reducedMotion.addEventListener('change', handleReducedMotionChange);
    } else if (typeof reducedMotion.addListener === 'function') {
      reducedMotion.addListener(handleReducedMotionChange);
    }
  }

  window.addEventListener('pagehide', handlePageHide);
  document.addEventListener('show.bs.modal', handleOverlayShow);
  document.addEventListener('show.bs.offcanvas', handleOverlayShow);
  document.addEventListener('hidden.bs.modal', handleOverlayHidden);
  document.addEventListener('hidden.bs.offcanvas', handleOverlayHidden);
}());
