(function () {
  'use strict';

  if (!document.documentElement.classList.contains('tv-mode')) {
    return;
  }

  var FOCUS_SEL = '[data-pm-tv-focus]';
  var STORAGE_KEY = 'putmio_tv_focus_id';
  var rail = document.getElementById('pm-tv-info-rail');
  var railTitle = document.getElementById('pm-tv-info-title');
  var railMeta = document.getElementById('pm-tv-info-meta');
  var railSynopsis = document.getElementById('pm-tv-info-synopsis');
  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var lastFocused = null;
  var lastKeyStamp = { code: 0, at: 0 };

  function normalizeKey(evt) {
    if (typeof window.putmioNormalizeTvKey === 'function') {
      return window.putmioNormalizeTvKey(evt);
    }
    var code = evt.keyCode || evt.which;
    var key = evt.key || '';
    if (key === 'ArrowLeft' || code === 37 || code === 21) return 'left';
    if (key === 'ArrowRight' || code === 39 || code === 22) return 'right';
    if (key === 'ArrowUp' || code === 38 || code === 19) return 'up';
    if (key === 'ArrowDown' || code === 40 || code === 20) return 'down';
    if (key === 'Enter' || code === 13 || code === 23) return 'enter';
    if (code === 4 || key === 'Back' || key === 'GoBack' || key === 'Escape' || code === 27 || code === 8) return 'back';
    return null;
  }

  function isDirection(dir) {
    return dir === 'left' || dir === 'right' || dir === 'up' || dir === 'down';
  }

  function shouldHandleEvent(evt) {
    if (evt.type === 'keydown') return true;
    if (evt.type !== 'keyup') return false;
    if (!window.PUTMIO || !window.PUTMIO.tvKeyUpFallback) return false;
    return typeof window.putmioIsTvRemoteKey === 'function' && window.putmioIsTvRemoteKey(evt);
  }

  function isDuplicateKey(evt) {
    var code = evt.keyCode || evt.which || 0;
    var now = Date.now();
    if (code === lastKeyStamp.code && now - lastKeyStamp.at < 100) {
      return true;
    }
    lastKeyStamp = { code: code, at: now };
    return false;
  }

  function isVisible(el) {
    if (!el || el.disabled) return false;
    if (el.closest('[hidden]')) return false;
    var st = window.getComputedStyle(el);
    if (st.display === 'none' || st.visibility === 'hidden') return false;
    var r = el.getBoundingClientRect();
    return r.width > 2 && r.height > 2;
  }

  function isEditableTarget(el) {
    if (!el) return false;
    var tag = el.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
  }

  function isPlayerPage() {
    return !!document.querySelector('.putmio-player-tv');
  }

  function isPlayerContext() {
    if (isPlayerPage()) return true;
    if (document.fullscreenElement) {
      var fs = document.fullscreenElement;
      if (fs.id === 'putmio-player' || (fs.classList && fs.classList.contains('video-js'))) return true;
      if (fs.closest && (fs.closest('.video-js') || fs.closest('.putmio-player-wrap'))) return true;
    }
    var active = document.activeElement;
    if (active && active.closest && active.closest('.putmio-player-wrap')) return true;
    if (active && active.closest && active.closest('.video-js')) return true;
    if (active && active.id === 'putmio-player') return true;
    return false;
  }

  function allFocusables() {
    return Array.prototype.slice.call(document.querySelectorAll(FOCUS_SEL)).filter(isVisible);
  }

  function rect(el) {
    return el.getBoundingClientRect();
  }

  function center(r) {
    return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
  }

  function findNearest(current, direction) {
    var cur = rect(current);
    var curC = center(cur);
    var candidates = allFocusables().filter(function (el) { return el !== current; });
    var best = null;
    var bestScore = Infinity;

    candidates.forEach(function (el) {
      var r = rect(el);
      var c = center(r);
      var dx = c.x - curC.x;
      var dy = c.y - curC.y;

      if (direction === 'left' && dx >= -4) return;
      if (direction === 'right' && dx <= 4) return;
      if (direction === 'up' && dy >= -4) return;
      if (direction === 'down' && dy <= 4) return;

      var primary = direction === 'left' || direction === 'right' ? Math.abs(dx) : Math.abs(dy);
      var secondary = direction === 'left' || direction === 'right' ? Math.abs(dy) : Math.abs(dx);
      var score = primary + secondary * 1.5;
      if (score < bestScore) {
        bestScore = score;
        best = el;
      }
    });

    return best;
  }

  function scrollFocusIntoView(el) {
    var sliderTrack = el.closest('[data-pm-slider-track]');
    if (sliderTrack) {
      var er = rect(el);
      var tr = rect(sliderTrack);
      if (er.left < tr.left) {
        sliderTrack.scrollBy({ left: er.left - tr.left - 16, behavior: reducedMotion ? 'auto' : 'smooth' });
      } else if (er.right > tr.right) {
        sliderTrack.scrollBy({ left: er.right - tr.right + 16, behavior: reducedMotion ? 'auto' : 'smooth' });
      }
    } else {
      el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: reducedMotion ? 'auto' : 'smooth' });
    }
  }

  function updateInfoRail(el) {
    if (!rail || !el) return;
    var title = el.getAttribute('data-pm-tv-title') || '';
    var subtitle = el.getAttribute('data-pm-tv-subtitle') || '';
    var synopsis = el.getAttribute('data-pm-tv-synopsis') || '';

    if (!title && el.textContent) {
      title = el.textContent.trim();
    }

    if (!title) {
      rail.hidden = true;
      return;
    }

    rail.hidden = false;
    if (railTitle) railTitle.textContent = title;
    if (railMeta) railMeta.textContent = subtitle;
    if (railSynopsis) {
      railSynopsis.textContent = synopsis;
      railSynopsis.hidden = !synopsis;
    }

    var id = el.getAttribute('data-pm-tv-id');
    if (id) {
      try { sessionStorage.setItem(STORAGE_KEY, id); } catch (e) { /* ignore */ }
    }
  }

  function setFocus(el) {
    if (!el || !isVisible(el)) return;
    lastFocused = el;
    try {
      el.focus({ preventScroll: true });
    } catch (e) {
      try { el.focus(); } catch (e2) { /* ignore */ }
    }
    scrollFocusIntoView(el);
    updateInfoRail(el);
  }

  function getCurrentFocusable() {
    var active = document.activeElement;
    if (active && active.matches && active.matches(FOCUS_SEL) && isVisible(active)) {
      return active;
    }
    if (lastFocused && isVisible(lastFocused)) {
      return lastFocused;
    }
    return allFocusables()[0] || null;
  }

  function restoreFocus() {
    if (isPlayerPage()) {
      return;
    }
    var id = null;
    try { id = sessionStorage.getItem(STORAGE_KEY); } catch (e) { /* ignore */ }
    if (id) {
      var el = document.querySelector('[data-pm-tv-id="' + CSS.escape(id) + '"]');
      if (el && isVisible(el)) {
        setFocus(el);
        return;
      }
    }
    var first = allFocusables()[0];
    if (first) setFocus(first);
  }

  function activateFocusedItem() {
    var target = getCurrentFocusable();
    if (!target) return;
    if (target.tagName === 'A' || target.tagName === 'BUTTON') {
      setFocus(target);
      target.click();
      return;
    }
    if (target.click) {
      setFocus(target);
      target.click();
    }
  }

  function handleNavKey(evt) {
    if (!shouldHandleEvent(evt)) return;
    if (isEditableTarget(evt.target)) return;
    if (isPlayerContext()) return;

    var dir = normalizeKey(evt);
    if (!dir) return;
    if (dir === 'ff') dir = 'right';
    if (dir === 'rw') dir = 'left';
    if (dir === 'playpause') dir = 'enter';
    if (isDuplicateKey(evt)) return;

    if (dir === 'back') {
      if (document.fullscreenElement) return;
      evt.preventDefault();
      evt.stopPropagation();
      if (window.history.length > 1) {
        window.history.back();
      }
      return;
    }

    if (dir === 'enter') {
      evt.preventDefault();
      evt.stopPropagation();
      activateFocusedItem();
      return;
    }

    if (!isDirection(dir)) return;

    var current = getCurrentFocusable();
    if (!current) {
      restoreFocus();
      current = getCurrentFocusable();
    }
    if (!current) return;

    evt.preventDefault();
    evt.stopPropagation();

    var next = findNearest(current, dir);
    if (next) {
      setFocus(next);
    }
  }

  document.addEventListener('focusin', function (evt) {
    var el = evt.target.closest ? evt.target.closest(FOCUS_SEL) : null;
    if (el) {
      lastFocused = el;
      updateInfoRail(el);
    }
  });

  window.addEventListener('keydown', handleNavKey, true);
  window.addEventListener('keyup', handleNavKey, true);

  function bindUiModeToggle() {
    var btn = document.getElementById('pm-ui-mode-toggle');
    if (!btn || !window.PUTMIO) return;

    var labels = window.PUTMIO.uiModeLabels || {};
    var isTv = !!window.PUTMIO.tvMode;

    if (isTv) {
      btn.title = labels.standard || 'Desktop';
      btn.setAttribute('aria-label', labels.standard || 'Desktop');
      var labelEl = btn.querySelector('.pm-tv-header__action-label');
      if (labelEl) labelEl.textContent = labels.standard || 'Desktop';
    } else {
      btn.title = labels.tv || 'TV';
      btn.setAttribute('aria-label', labels.tv || 'TV');
    }

    btn.addEventListener('click', async function () {
      var next = isTv ? 'standard' : 'tv';
      if (!window.PUTMIO.csrf) {
        document.cookie = 'putmio_ui_mode=' + next + ';path=/;max-age=31536000;SameSite=Strict';
        window.location.reload();
        return;
      }
      btn.disabled = true;
      try {
        var body = new URLSearchParams({ _csrf: window.PUTMIO.csrf, ui_mode: next });
        var res = await fetch(window.PUTMIO.baseUrl + '/api/preferences/ui-mode', { method: 'POST', body: body });
        if (!res.ok) throw new Error('ui mode failed');
        document.cookie = 'putmio_ui_mode=' + next + ';path=/;max-age=31536000;SameSite=Strict';
        window.location.reload();
      } catch (e) {
        btn.disabled = false;
        if (window.pmToast) window.pmToast(labels.tvHint || 'Cambio interfaccia non riuscito', 'error');
      }
    });
  }

  function enforceDarkTheme() {
    document.documentElement.classList.add('dark');
    try {
      localStorage.setItem('putmio_theme', 'dark');
    } catch (e) { /* ignore */ }
    document.cookie = 'putmio_theme=dark;path=/;max-age=31536000;SameSite=Strict';
  }

  function bootstrapTvFocus() {
    var main = document.getElementById('pm-tv-main');
    if (main && !getCurrentFocusable()) {
      try { main.focus({ preventScroll: true }); } catch (e) { /* ignore */ }
    }
    restoreFocus();
  }

  window.addEventListener('DOMContentLoaded', function () {
    enforceDarkTheme();
    bindUiModeToggle();
    window.setTimeout(bootstrapTvFocus, 80);
    window.setTimeout(bootstrapTvFocus, 400);
  });
})();
