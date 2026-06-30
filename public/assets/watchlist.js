(function () {
  if (!window.PUTMIO) return;

  function setBookmarkState(btn, active) {
    btn.setAttribute('data-pm-watchlist-active', active ? '1' : '0');
    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    btn.setAttribute(
      'aria-label',
      active ? (window.PUTMIO.watchlistRemoveLabel || 'Remove from watchlist') : (window.PUTMIO.watchlistAddLabel || 'Add to watchlist')
    );
    btn.classList.toggle('pm-watchlist-btn--active', active);

    const icon = btn.querySelector('.pm-watchlist-btn__icon');
    if (icon) {
      icon.style.fontVariationSettings = active ? "'FILL' 1" : '';
    }

    const label = btn.querySelector('.pm-watchlist-btn__label');
    if (label) {
      label.textContent = active
        ? (window.PUTMIO.watchlistRemoveLabel || 'Remove from watchlist')
        : (window.PUTMIO.watchlistAddLabel || 'Add to watchlist');
    }
  }

  document.addEventListener('click', async function (evt) {
    const btn = evt.target.closest('[data-pm-watchlist-toggle]');
    if (!btn || btn.disabled) return;

    evt.preventDefault();
    evt.stopPropagation();

    const mediaId = btn.getAttribute('data-pm-watchlist-toggle');
    if (!mediaId) return;

    const wasActive = btn.getAttribute('data-pm-watchlist-active') === '1';
    const action = wasActive ? 'remove' : 'add';

    btn.disabled = true;

    try {
      const body = new URLSearchParams({
        _csrf: window.PUTMIO.csrf,
        media_id: mediaId,
        action: action,
      });
      const res = await fetch(window.PUTMIO.baseUrl + '/api/watchlist', {
        method: 'POST',
        body: body,
      });
      const data = await res.json().catch(function () {
        return { ok: false };
      });

      if (!res.ok || !data.ok) {
        if (typeof window.pmToast === 'function') {
          window.pmToast(window.PUTMIO.watchlistErrorLabel || 'Unable to update watchlist', 'error');
        }
        return;
      }

      setBookmarkState(btn, !!data.in_watchlist);

      if (typeof window.pmToast === 'function' && data.message) {
        window.pmToast(data.message, 'success');
      }
    } catch (e) {
      if (typeof window.pmToast === 'function') {
        window.pmToast(window.PUTMIO.watchlistErrorLabel || 'Unable to update watchlist', 'error');
      }
    } finally {
      btn.disabled = false;
    }
  });
})();
