(function () {
  document.querySelectorAll('[data-pm-locale-menu]').forEach(function (menu) {
    var trigger = menu.querySelector('.pm-locale-menu__trigger');
    var panel = menu.querySelector('.pm-locale-menu__panel');
    if (!trigger || !panel) return;

    function closePanel() {
      panel.classList.add('hidden');
      trigger.setAttribute('aria-expanded', 'false');
    }

    function openPanel() {
      panel.classList.remove('hidden');
      trigger.setAttribute('aria-expanded', 'true');
    }

    trigger.addEventListener('click', function (evt) {
      evt.stopPropagation();
      if (panel.classList.contains('hidden')) {
        openPanel();
      } else {
        closePanel();
      }
    });

    document.addEventListener('click', function (evt) {
      if (!menu.contains(evt.target)) {
        closePanel();
      }
    });

    document.addEventListener('keydown', function (evt) {
      if (evt.key === 'Escape') {
        closePanel();
      }
    });

    panel.querySelectorAll('[data-locale]').forEach(function (option) {
      option.addEventListener('click', async function () {
        var locale = option.getAttribute('data-locale') || '';
        if (!locale || option.getAttribute('aria-selected') === 'true') {
          closePanel();
          return;
        }
        if (!window.PUTMIO || !window.PUTMIO.csrf) {
          document.cookie = 'putmio_locale=' + locale + ';path=/;max-age=31536000;SameSite=Strict';
          window.location.reload();
          return;
        }

        option.disabled = true;
        try {
          var body = new URLSearchParams({ _csrf: window.PUTMIO.csrf, locale: locale });
          var res = await fetch(window.PUTMIO.baseUrl + '/api/preferences/locale', { method: 'POST', body: body });
          if (!res.ok) {
            throw new Error('locale failed');
          }
          localStorage.setItem('putmio_locale', locale);
          document.cookie = 'putmio_locale=' + locale + ';path=/;max-age=31536000;SameSite=Strict';
          window.location.reload();
        } catch (e) {
          option.disabled = false;
          if (window.pmToast) {
            window.pmToast(window.PUTMIO.localeChangeError || 'Language change failed', 'error');
          }
        }
      });
    });
  });

  const btn = document.getElementById('theme-toggle');
  if (btn && window.PUTMIO) {
    btn.addEventListener('click', async function () {
      const isDark = document.documentElement.classList.toggle('dark');
      const theme = isDark ? 'dark' : 'light';
      localStorage.setItem('putmio_theme', theme);
      document.cookie = 'putmio_theme=' + theme + ';path=/;max-age=31536000;SameSite=Strict';

      try {
        const body = new URLSearchParams({ _csrf: window.PUTMIO.csrf, theme: theme });
        await fetch(window.PUTMIO.baseUrl + '/api/preferences/theme', { method: 'POST', body: body });
      } catch (e) { /* ignore */ }
    });
  }

  document.querySelectorAll('.custom-scrollbar').forEach(function (container) {
    container.addEventListener('wheel', function (evt) {
      if (Math.abs(evt.deltaY) <= Math.abs(evt.deltaX)) return;
      evt.preventDefault();
      container.scrollLeft += evt.deltaY;
    }, { passive: false });
  });

  document.querySelectorAll('[data-pm-slider]').forEach(function (slider) {
    var track = slider.querySelector('[data-pm-slider-track]');
    var prev = slider.querySelector('[data-pm-slider-prev]');
    var next = slider.querySelector('[data-pm-slider-next]');
    if (!track || !prev || !next) return;

    track.querySelectorAll('img').forEach(function (img) {
      img.setAttribute('draggable', 'false');
    });

    track.addEventListener('dragstart', function (evt) {
      evt.preventDefault();
    }, true);

    var dragPointerId = null;
    var dragStartX = 0;
    var dragLastX = 0;
    var didDrag = false;

    function handlePointerMove(evt) {
      if (dragPointerId !== evt.pointerId) return;
      var deltaFromStart = evt.clientX - dragStartX;
      if (!didDrag && Math.abs(deltaFromStart) > 4) {
        didDrag = true;
        track.classList.add('pm-slider__track--dragging');
        track.classList.add('pm-slider__track--panning');
        try {
          track.setPointerCapture(dragPointerId);
        } catch (e) { /* ignore */ }
      }
      if (!didDrag) return;
      evt.preventDefault();
      var step = evt.clientX - dragLastX;
      dragLastX = evt.clientX;
      track.scrollLeft -= step;
    }

    function endDrag(evt) {
      if (dragPointerId === null || (evt && evt.pointerId !== dragPointerId)) return;
      window.removeEventListener('pointermove', handlePointerMove);
      window.removeEventListener('pointerup', endDrag);
      window.removeEventListener('pointercancel', endDrag);
      if (didDrag) {
        try {
          track.releasePointerCapture(dragPointerId);
        } catch (e) { /* ignore */ }
      }
      dragPointerId = null;
      track.classList.remove('pm-slider__track--dragging');
      track.classList.remove('pm-slider__track--panning');
    }

    track.addEventListener('pointerdown', function (evt) {
      if (evt.pointerType !== 'mouse' || evt.button !== 0) return;
      dragPointerId = evt.pointerId;
      dragStartX = evt.clientX;
      dragLastX = evt.clientX;
      didDrag = false;
      window.addEventListener('pointermove', handlePointerMove);
      window.addEventListener('pointerup', endDrag);
      window.addEventListener('pointercancel', endDrag);
    }, true);

    track.addEventListener('click', function (evt) {
      if (!didDrag) return;
      var link = evt.target.closest('a');
      if (!link || !track.contains(link)) return;
      evt.preventDefault();
      evt.stopImmediatePropagation();
      didDrag = false;
    }, true);

    function updateNav() {
      var maxScroll = track.scrollWidth - track.clientWidth;
      var canScroll = maxScroll > 8;
      prev.hidden = !canScroll;
      next.hidden = !canScroll;
      if (!canScroll) return;
      prev.disabled = track.scrollLeft <= 4;
      next.disabled = track.scrollLeft >= maxScroll - 4;
    }

    function scrollByPage(direction) {
      track.scrollBy({ left: direction * track.clientWidth * 0.85, behavior: 'smooth' });
    }

    prev.addEventListener('click', function () { scrollByPage(-1); });
    next.addEventListener('click', function () { scrollByPage(1); });
    track.addEventListener('scroll', updateNav, { passive: true });
    window.addEventListener('resize', updateNav);
    if (typeof ResizeObserver !== 'undefined') {
      new ResizeObserver(updateNav).observe(track);
    }
    updateNav();
  });

  const banner = document.getElementById('putio-banner');
  const dismiss = document.getElementById('putio-banner-dismiss');
  if (banner && dismiss) {
    if (localStorage.getItem('putmio_dismiss_putio_banner') === '1') {
      banner.hidden = true;
    }
    dismiss.addEventListener('click', function () {
      banner.hidden = true;
      localStorage.setItem('putmio_dismiss_putio_banner', '1');
    });
  }

  document.querySelectorAll('[data-pm-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const input = document.getElementById(btn.getAttribute('data-pm-toggle-password'));
      const icon = btn.querySelector('.material-symbols-outlined');
      if (!input || !icon) return;
      const hidden = input.type === 'password';
      input.type = hidden ? 'text' : 'password';
      icon.textContent = hidden ? 'visibility_off' : 'visibility';
    });
  });

  document.querySelectorAll('[data-pm-copy]').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      const url = btn.getAttribute('data-pm-copy') || '';
      if (!url) return;
      try {
        await navigator.clipboard.writeText(url);
        const icon = btn.querySelector('.material-symbols-outlined');
        if (icon) {
          const prev = icon.textContent;
          icon.textContent = 'check';
          setTimeout(function () { icon.textContent = prev; }, 2000);
        }
      } catch (e) { /* ignore */ }
    });
  });

  window.pmToast = function (message, type, duration) {
    type = type || 'success';
    duration = typeof duration === 'number' ? duration : 4200;
    if (!message) return;

    var icons = { success: 'check_circle', error: 'error', info: 'info' };
    var stack = document.getElementById('pm-toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.id = 'pm-toast-stack';
      stack.className = 'pm-toast-stack';
      stack.setAttribute('aria-live', 'polite');
      stack.setAttribute('aria-atomic', 'true');
      document.body.appendChild(stack);
    }

    var toast = document.createElement('div');
    toast.className = 'pm-toast pm-toast--' + type;
    toast.innerHTML =
      '<span class="material-symbols-outlined pm-toast__icon" aria-hidden="true">' + (icons[type] || icons.info) + '</span>' +
      '<span class="pm-toast__text"></span>';
    toast.querySelector('.pm-toast__text').textContent = message;
    stack.appendChild(toast);

    window.setTimeout(function () {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(0.5rem)';
      toast.style.transition = 'opacity 0.2s, transform 0.2s';
      window.setTimeout(function () { toast.remove(); }, 220);
    }, duration);
  };
})();
