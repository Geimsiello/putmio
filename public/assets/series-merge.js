(function () {
  if (!window.PUTMIO) {
    return;
  }

  const btn = document.getElementById('series-merge-btn');
  const icon = document.getElementById('series-merge-icon');
  const statusEl = document.getElementById('series-merge-status');
  if (!btn || !icon || !statusEl) {
    return;
  }

  const labels = window.PUTMIO.seriesMergeLabels || {};
  let running = false;

  function setStatus(message, isError) {
    statusEl.textContent = message;
    statusEl.classList.remove('hidden', 'text-success', 'text-error');
    statusEl.classList.add(isError ? 'text-error' : 'text-success');
  }

  function setRunning(active) {
    running = active;
    btn.disabled = active;
    icon.textContent = active ? 'progress_activity' : 'merge';
    if (active) {
      icon.classList.add('animate-spin');
    } else {
      icon.classList.remove('animate-spin');
    }
  }

  btn.addEventListener('click', async function () {
    if (running) {
      return;
    }

    setRunning(true);
    setStatus(labels.running || '…', false);
    statusEl.classList.remove('text-success', 'text-error');
    statusEl.classList.add('text-on-surface-variant');

    try {
      const body = new URLSearchParams({
        _csrf: window.PUTMIO.csrf,
      });
      const response = await fetch(window.PUTMIO.baseUrl + '/api/series/merge-duplicates', {
        method: 'POST',
        body,
        headers: {
          Accept: 'application/json',
        },
      });
      const data = await response.json().catch(function () {
        return {};
      });

      if (!response.ok || !data.ok) {
        throw new Error(data.error || labels.error || 'Errore');
      }

      setStatus(data.message || '', false);
    } catch (error) {
      setStatus(error.message || labels.error || 'Errore', true);
    } finally {
      setRunning(false);
    }
  });
})();
