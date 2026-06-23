(function () {
  if (!window.PUTMIO) {
    return;
  }

  const settings = window.PUTMIO.settings || {};
  const list = document.getElementById('putio-friends-list');
  const syncBtn = document.getElementById('putio-sync-btn');
  const syncIcon = document.getElementById('putio-sync-icon');

  function setSyncSpinning(active) {
    if (!syncIcon) return;
    syncIcon.classList.toggle('animate-spin', active);
  }

  if (window.PUTMIO.initialToast && window.pmToast) {
    const t = window.PUTMIO.initialToast;
    window.pmToast(t.message, t.type || 'success');
  }

  let saveTimer = null;
  let saveSeq = 0;

  function updateRowStyles() {
    if (!list) return;
    list.querySelectorAll('[data-pm-friend-row]').forEach(function (row) {
      const cb = row.querySelector('[data-pm-friend-sync]');
      if (!cb) return;
      row.classList.toggle('pm-friend-row--selected', cb.checked);
    });
  }

  async function saveSelection() {
    if (!list) return;
    const seq = ++saveSeq;
    const ids = Array.from(list.querySelectorAll('[data-pm-friend-sync]:checked')).map(function (cb) {
      return cb.value;
    });

    if (window.pmToast) {
      window.pmToast(settings.toastSaving || '', 'info', 1800);
    }

    try {
      const body = new URLSearchParams();
      body.set('_csrf', window.PUTMIO.csrf);
      ids.forEach(function (id) {
        body.append('sync_friends[]', id);
      });

      const response = await fetch(window.PUTMIO.baseUrl + '/api/putio/sync-friends', {
        method: 'POST',
        body: body,
      });
      const data = await response.json().catch(function () {
        return { ok: false };
      });

      if (seq !== saveSeq) return;
      if (!response.ok || !data.ok) {
        throw new Error(data.error || 'save failed');
      }

      if (window.pmToast) {
        window.pmToast(data.message || settings.toastSaved || '', 'success');
      }
    } catch (e) {
      if (seq !== saveSeq) return;
      if (window.pmToast) {
        window.pmToast(settings.toastSaveError || '', 'error');
      }
    }
  }

  function scheduleSave() {
    updateRowStyles();
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(saveSelection, 350);
  }

  if (list) {
    list.addEventListener('change', function (evt) {
      if (evt.target && evt.target.matches('[data-pm-friend-sync]')) {
        scheduleSave();
      }
    });
    updateRowStyles();
  }

  if (syncBtn) {
    syncBtn.addEventListener('click', async function () {
      if (syncBtn.disabled) return;
      syncBtn.disabled = true;
      syncBtn.setAttribute('aria-busy', 'true');
      syncBtn.classList.add('opacity-70', 'pointer-events-none');
      setSyncSpinning(true);

      if (window.pmToast) {
        window.pmToast(settings.toastSyncRunning || '', 'info', 2500);
      }

      try {
        const body = new URLSearchParams();
        body.set('_csrf', window.PUTMIO.csrf);
        const response = await fetch(window.PUTMIO.baseUrl + '/api/putio/sync', {
          method: 'POST',
          body: body,
        });
        const data = await response.json().catch(function () {
          return { ok: false };
        });

        if (!response.ok || !data.ok) {
          throw new Error(data.error || 'sync failed');
        }

        if (window.pmToast) {
          window.pmToast(data.message || 'Sync completata', 'success', 5500);
        }

        window.setTimeout(function () {
          window.location.reload();
        }, 1200);
      } catch (e) {
        if (window.pmToast) {
          window.pmToast(e.message || settings.toastSyncError || 'Sync error', 'error', 6000);
        }
        syncBtn.disabled = false;
        syncBtn.removeAttribute('aria-busy');
        syncBtn.classList.remove('opacity-70', 'pointer-events-none');
        setSyncSpinning(false);
      }
    });
  }

  const osTestBtn = document.getElementById('opensubtitles-test-btn');
  if (osTestBtn) {
    osTestBtn.addEventListener('click', async function () {
      if (osTestBtn.disabled) return;
      osTestBtn.disabled = true;

      if (window.pmToast) {
        window.pmToast(settings.toastSubtitlesTesting || '', 'info', 2000);
      }

      try {
        const body = new URLSearchParams();
        body.set('_csrf', window.PUTMIO.csrf);
        const response = await fetch(window.PUTMIO.baseUrl + '/api/opensubtitles/test', {
          method: 'POST',
          body: body,
        });
        const data = await response.json().catch(function () {
          return { ok: false };
        });

        if (!response.ok || !data.ok) {
          throw new Error(data.error || 'test failed');
        }

        if (window.pmToast) {
          window.pmToast(data.message || settings.toastSubtitlesTestOk || '', 'success');
        }
      } catch (e) {
        if (window.pmToast) {
          window.pmToast(e.message || settings.toastSubtitlesTestError || '', 'error', 6000);
        }
      } finally {
        osTestBtn.disabled = false;
      }
    });
  }
})();
