(function () {
  if (!window.PUTMIO) {
    return;
  }

  const list = document.getElementById('account-content-sources');
  const labels = (window.PUTMIO.accountContent || {});

  let saveTimer = null;
  let saveSeq = 0;

  function updateRowStyles() {
    if (!list) return;
    list.querySelectorAll('[data-pm-content-row]').forEach(function (row) {
      const cb = row.querySelector('[data-pm-content-source]');
      if (!cb) return;
      row.classList.toggle('pm-friend-row--selected', cb.checked);
    });
  }

  async function saveSelection() {
    if (!list) return;
    const seq = ++saveSeq;
    const keys = Array.from(list.querySelectorAll('[data-pm-content-source]:checked')).map(function (cb) {
      return cb.value;
    });

    if (window.pmToast) {
      window.pmToast(labels.toastSaving || '', 'info', 1800);
    }

    try {
      const body = new URLSearchParams();
      body.set('_csrf', window.PUTMIO.csrf);
      keys.forEach(function (key) {
        body.append('sources[]', key);
      });

      const response = await fetch(window.PUTMIO.baseUrl + '/api/account/catalog-sources', {
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
        window.pmToast(data.message || labels.toastSaved || '', 'success');
      }
    } catch (e) {
      if (seq !== saveSeq) return;
      if (window.pmToast) {
        window.pmToast(labels.toastSaveError || '', 'error');
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
      if (evt.target && evt.target.matches('[data-pm-content-source]')) {
        scheduleSave();
      }
    });
    updateRowStyles();
  }
})();
