(() => {
  function decoratePopoverTip(tip, trigger) {
    if (!tip) return;
    const baseId = tip.id;

    tip.setAttribute('role', 'dialog');
    tip.setAttribute('aria-modal', 'false');
    tip.tabIndex = -1;

    const headerEl = tip.querySelector('.popover-header');
    if (headerEl) {
      headerEl.id = `${baseId}-title`;
      tip.setAttribute('aria-labelledby', headerEl.id);
    }

    const bodyEl = tip.querySelector('.popover-body');
    if (bodyEl) {
      bodyEl.id = `${baseId}-body`;
      if (!tip.getAttribute('aria-labelledby')) {
        tip.setAttribute('aria-label', trigger.getAttribute('aria-label') || 'Información');
      } else {
        tip.setAttribute('aria-describedby', bodyEl.id);
      }
    }
  }

  function initEducationPopovers(scope = document) {
    if (!window.bootstrap || !window.bootstrap.Popover) return;

    scope.querySelectorAll('[data-bh-popover]').forEach((trigger) => {
      const content = trigger.getAttribute('data-bh-popover');
      if (!content) return;
      if (trigger.dataset.bhPopoverReady === 'true') return;
      trigger.dataset.bhPopoverReady = 'true';

      const titleAttr = trigger.getAttribute('data-bh-popover-title') || '';
      trigger.setAttribute('aria-haspopup', 'dialog');
      trigger.setAttribute('aria-expanded', 'false');

      const instance = window.bootstrap.Popover.getOrCreateInstance(trigger, {
        container: 'body',
        content,
        customClass: 'bh-education-popover',
        html: trigger.getAttribute('data-bh-popover-html') === 'true',
        placement: trigger.getAttribute('data-bh-popover-placement') || 'auto',
        title: titleAttr,
        trigger: 'click',
      });

      const syncAriaControls = () => {
        const tip = instance.tip;
        if (tip && tip.id) {
          trigger.setAttribute('aria-controls', tip.id);
        }
      };

      trigger.addEventListener('inserted.bs.popover', () => {
        const tip = instance.tip;
        if (tip) {
          syncAriaControls();
          decoratePopoverTip(tip, trigger);
        }
      });

      trigger.addEventListener('shown.bs.popover', () => {
        trigger.setAttribute('aria-expanded', 'true');
        const tip = instance.tip;
        if (tip) {
          syncAriaControls();
          decoratePopoverTip(tip, trigger);
        }
      });

      trigger.addEventListener('hidden.bs.popover', () => {
        trigger.setAttribute('aria-expanded', 'false');
        trigger.focus({ preventScroll: true });
      });

      trigger.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          event.preventDefault();
          instance.hide();
        }
      });
    });

    document.addEventListener('mousedown', (event) => {
      const openTriggers = Array.from(
        document.querySelectorAll('[data-bh-popover][aria-expanded="true"]')
      );
      if (!openTriggers.length) return;

      const target = event.target;
      for (let i = openTriggers.length - 1; i >= 0; i -= 1) {
        const trigger = openTriggers[i];
        if (trigger.contains(target)) continue;
        const tip = window.bootstrap.Popover.getInstance(trigger)?.tip;
        if (tip && tip.contains(target)) continue;
        window.bootstrap.Popover.getInstance(trigger)?.hide();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;

      const openTriggers = Array.from(
        document.querySelectorAll('[data-bh-popover][aria-expanded="true"]')
      );
      if (!openTriggers.length) return;

      const lastTrigger = openTriggers[openTriggers.length - 1];
      const instance = window.bootstrap.Popover.getInstance(lastTrigger);
      if (instance) instance.hide();
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
