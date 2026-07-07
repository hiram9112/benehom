(() => {
  function initEducationPopovers(scope = document) {
    if (!window.bootstrap || !window.bootstrap.Popover) return;

    scope.querySelectorAll('[data-bh-popover]').forEach((trigger) => {
      const content = trigger.getAttribute('data-bh-popover');
      if (!content) return;

      const instance = window.bootstrap.Popover.getOrCreateInstance(trigger, {
        container: 'body',
        content,
        customClass: 'bh-education-popover',
        html: trigger.getAttribute('data-bh-popover-html') === 'true',
        placement: trigger.getAttribute('data-bh-popover-placement') || 'auto',
        title: trigger.getAttribute('data-bh-popover-title') || '',
        trigger: 'click',
      });

      trigger.setAttribute('aria-expanded', 'false');

      trigger.addEventListener('shown.bs.popover', () => {
        trigger.setAttribute('aria-expanded', 'true');
      });

      trigger.addEventListener('hidden.bs.popover', () => {
        trigger.setAttribute('aria-expanded', 'false');
      });

      trigger.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        instance.hide();
        trigger.focus({ preventScroll: true });
      });
    });

    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;

      document.querySelectorAll('[data-bh-popover][aria-expanded="true"]').forEach((trigger) => {
        const instance = window.bootstrap.Popover.getInstance(trigger);
        if (instance) instance.hide();
      });
    });
  }

  function initJumpNav(scope = document) {
    scope.querySelectorAll('[data-bh-jump-nav]').forEach((nav) => {
      const links = Array.from(nav.querySelectorAll('a[href^="#"]'));
      const sections = links
        .map((link) => document.getElementById(link.getAttribute('href').slice(1)))
        .filter(Boolean);

      if (!links.length || !sections.length) return;

      function setActive(id) {
        links.forEach((link) => {
          const active = link.getAttribute('href') === '#' + id;
          link.classList.toggle('is-active', active);
          if (active) {
            link.setAttribute('aria-current', 'true');
          } else {
            link.removeAttribute('aria-current');
          }
        });
      }

      links.forEach((link) => {
        link.addEventListener('click', (event) => {
          const target = document.getElementById(link.getAttribute('href').slice(1));
          if (!target) return;

          event.preventDefault();
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
          setActive(target.id);
        });
      });

      if (!('IntersectionObserver' in window)) {
        setActive(sections[0].id);
        return;
      }

      const observer = new IntersectionObserver((entries) => {
        const visible = entries
          .filter((entry) => entry.isIntersecting)
          .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

        if (visible && visible.target.id) setActive(visible.target.id);
      }, {
        rootMargin: '-20% 0px -65% 0px',
        threshold: [0.1, 0.35, 0.6],
      });

      sections.forEach((section) => observer.observe(section));
      setActive(sections[0].id);
    });
  }

  function init(scope = document) {
    initEducationPopovers(scope);
    initJumpNav(scope);
  }

  window.BHComponents = {
    init,
    initEducationPopovers,
    initJumpNav,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => init());
  } else {
    init();
  }
})();
