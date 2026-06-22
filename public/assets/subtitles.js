(function () {
  if (!window.PUTMIO) return;

  const labels = (window.PUTMIO.subtitleLabels || {});
  const mediaId = window.PUTMIO.mediaId || null;
  const isAdmin = !!window.PUTMIO.isAdmin;
  const configured = window.PUTMIO.subtitlesConfigured !== false;

  let availableSubtitles = (window.PUTMIO.availableSubtitles || []).slice();
  let activeSubtitleId = window.PUTMIO.activeSubtitleId || null;
  let offsetMs = window.PUTMIO.offsetMs || 0;

  const modalRoot = document.getElementById('subtitle-modal-root');
  const manageBtn = document.getElementById('player-subtitle-manage');
  const subtitleSelect = document.getElementById('player-subtitle-select');
  const offsetPanel = document.getElementById('player-subtitle-offset');
  const offsetInput = document.getElementById('player-subtitle-offset-input');
  const offsetResetBtn = document.getElementById('player-subtitle-offset-reset');
  const cachedList = document.getElementById('subtitle-cached-list');
  const cachedEmpty = document.getElementById('subtitle-cached-empty');
  const searchBtn = document.getElementById('subtitle-search-btn');
  const searchList = document.getElementById('subtitle-search-list');
  const searchStatus = document.getElementById('subtitle-search-status');
  const modalNotice = document.getElementById('subtitle-modal-notice');
  const catalogManageBtn = document.getElementById('catalog-subtitle-manage');

  let prefTimer = null;
  let currentModalMediaId = mediaId;

  function esc(text) {
    const el = document.createElement('span');
    el.textContent = text == null ? '' : String(text);
    return el.innerHTML;
  }

  function showNotice(message) {
    if (!modalNotice) return;
    modalNotice.textContent = message;
    modalNotice.classList.remove('hidden');
  }

  function hideNotice() {
    if (!modalNotice) return;
    modalNotice.classList.add('hidden');
    modalNotice.textContent = '';
  }

  function openModal(forMediaId) {
    if (!modalRoot) return;
    currentModalMediaId = forMediaId || mediaId;
    modalRoot.classList.remove('hidden');
    modalRoot.classList.add('flex');
    document.body.classList.add('overflow-hidden');
    hideNotice();
    if (!configured) {
      showNotice(labels.notConfigured || 'OpenSubtitles non configurato');
      if (searchBtn) searchBtn.disabled = true;
    } else if (searchBtn) {
      searchBtn.disabled = false;
    }
    refreshCachedList();
    if (searchList) searchList.innerHTML = '';
    if (searchStatus) {
      searchStatus.classList.add('hidden');
      searchStatus.textContent = '';
    }
  }

  function closeModal() {
    if (!modalRoot) return;
    modalRoot.classList.add('hidden');
    modalRoot.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
  }

  function scheduleSavePreference() {
    if (!currentModalMediaId && !mediaId) return;
    const targetMediaId = currentModalMediaId || mediaId;
    if (prefTimer) clearTimeout(prefTimer);
    prefTimer = setTimeout(function () {
      const body = new URLSearchParams({
        _csrf: window.PUTMIO.csrf,
        media_id: String(targetMediaId),
        subtitle_id: activeSubtitleId == null ? '' : String(activeSubtitleId),
        offset_ms: String(offsetMs),
      });
      fetch(window.PUTMIO.baseUrl + '/api/subtitles/preference', {
        method: 'POST',
        body: body,
      }).catch(function () {});
    }, 400);
  }

  function renderSelectOptions() {
    if (!subtitleSelect) return;
    const current = activeSubtitleId == null ? '' : String(activeSubtitleId);
    subtitleSelect.innerHTML = '<option value="">' + esc(labels.off || 'Off') + '</option>';
    availableSubtitles.forEach(function (sub) {
      const opt = document.createElement('option');
      opt.value = String(sub.id);
      opt.textContent = sub.label + (sub.language ? ' (' + sub.language + ')' : '');
      opt.selected = current === String(sub.id);
      subtitleSelect.appendChild(opt);
    });
    subtitleSelect.value = current;

    if (offsetPanel) {
      offsetPanel.classList.toggle('hidden', !activeSubtitleId);
    }
    if (offsetInput) {
      offsetInput.value = String((offsetMs / 1000).toFixed(1));
    }
  }

  function refreshCachedList() {
    if (!cachedList) return;
    cachedList.innerHTML = '';
    const listMediaId = currentModalMediaId || mediaId;
    if (!listMediaId) return;

    availableSubtitles.forEach(function (sub) {
      const li = document.createElement('li');
      li.className = 'flex flex-wrap items-center justify-between gap-3 rounded-xl border border-outline-variant/30 bg-surface-container-high px-4 py-3';
      li.innerHTML =
        '<div class="min-w-0">' +
          '<p class="font-label-md text-label-md text-on-surface">' + esc(sub.label) + '</p>' +
          '<p class="font-label-sm text-label-sm text-on-surface-variant">' + esc(sub.language) + '</p>' +
        '</div>' +
        '<div class="flex items-center gap-2 shrink-0">' +
          (isAdmin ? '<button type="button" class="px-3 py-1.5 rounded-lg border border-outline-variant/40 text-label-sm text-warning hover:bg-warning/10" data-pm-sub-delete="' + sub.id + '">' + esc(labels.delete || 'Elimina') + '</button>' : '') +
          '<button type="button" class="pm-btn-primary px-3 py-1.5 text-label-sm" data-pm-sub-use="' + sub.id + '">' + esc(labels.use || 'Usa') + '</button>' +
        '</div>';
      cachedList.appendChild(li);
    });

    if (cachedEmpty) {
      cachedEmpty.classList.toggle('hidden', availableSubtitles.length > 0);
    }
  }

  async function reloadSubtitles(forMediaId) {
    const id = forMediaId || mediaId;
    if (!id) return;
    const response = await fetch(window.PUTMIO.baseUrl + '/api/subtitles?media_id=' + encodeURIComponent(String(id)));
    const data = await response.json().catch(function () { return {}; });
    if (!response.ok || !data.ok) return;
    if (id === mediaId) {
      availableSubtitles = data.subtitles || [];
      if (data.activeSubtitleId !== undefined) activeSubtitleId = data.activeSubtitleId;
      if (data.offsetMs !== undefined) offsetMs = data.offsetMs;
      renderSelectOptions();
      if (window.PutMioPlayerSubtitles) {
        window.PutMioPlayerSubtitles.apply(activeSubtitleId, offsetMs, availableSubtitles);
      }
    }
    if ((currentModalMediaId || mediaId) === id) {
      availableSubtitles = data.subtitles || availableSubtitles;
      refreshCachedList();
    }
  }

  async function searchRemote() {
    const id = currentModalMediaId || mediaId;
    if (!id || !searchBtn) return;
    searchBtn.disabled = true;
    if (searchStatus) {
      searchStatus.classList.remove('hidden');
      searchStatus.textContent = labels.searching || 'Ricerca…';
    }
    if (searchList) searchList.innerHTML = '';

    try {
      const response = await fetch(window.PUTMIO.baseUrl + '/api/subtitles/search?media_id=' + encodeURIComponent(String(id)));
      const data = await response.json().catch(function () { return {}; });
      if (!response.ok) {
        throw new Error(data.error || 'Errore ricerca');
      }
      const results = data.results || [];
      if (searchStatus) {
        searchStatus.textContent = results.length ? '' : (labels.searchEmpty || 'Nessun risultato');
        searchStatus.classList.toggle('hidden', results.length > 0);
      }
      results.forEach(function (item) {
        if (!searchList) return;
        const li = document.createElement('li');
        li.className = 'rounded-xl border border-outline-variant/30 bg-surface-container-high px-4 py-3 space-y-2';
        const meta = [
          item.language || '',
          item.release || '',
          item.download_count ? item.download_count + ' dl' : '',
          item.uploader || '',
        ].filter(Boolean).join(' · ');
        li.innerHTML =
          '<p class="font-label-md text-label-md text-on-surface">' + esc(item.label || item.language) + '</p>' +
          '<p class="font-label-sm text-label-sm text-on-surface-variant line-clamp-2">' + esc(meta) + '</p>' +
          '<div class="flex justify-end">' +
            (item.cached
              ? '<span class="text-label-sm font-label-sm text-success">' + esc(labels.downloaded || 'Già scaricato') + '</span>'
              : '<button type="button" class="pm-btn-primary px-3 py-1.5 text-label-sm" data-pm-sub-dl="' + esc(item.file_id) + '" data-lang="' + esc(item.language) + '" data-label="' + esc(item.label) + '">' + esc(labels.download || 'Scarica') + '</button>') +
          '</div>';
        searchList.appendChild(li);
      });
    } catch (e) {
      if (searchStatus) {
        searchStatus.classList.remove('hidden');
        searchStatus.textContent = e.message;
      }
    } finally {
      searchBtn.disabled = !configured;
    }
  }

  async function downloadSubtitle(fileId, language, label) {
    const id = currentModalMediaId || mediaId;
    if (!id) return;
    const body = new URLSearchParams({
      _csrf: window.PUTMIO.csrf,
      media_id: String(id),
      file_id: fileId,
      language: language || '',
      label: label || '',
    });
    const response = await fetch(window.PUTMIO.baseUrl + '/api/subtitles/download', {
      method: 'POST',
      body: body,
    });
    const data = await response.json().catch(function () { return {}; });
    if (!response.ok || !data.ok) {
      throw new Error(data.error || labels.downloadError || 'Errore download');
    }
    await reloadSubtitles(id);
    if (id === mediaId && data.subtitle) {
      activeSubtitleId = data.subtitle.id;
      scheduleSavePreference();
      renderSelectOptions();
      if (window.PutMioPlayerSubtitles) {
        window.PutMioPlayerSubtitles.apply(activeSubtitleId, offsetMs, availableSubtitles);
      }
    }
    refreshCachedList();
    if (!document.getElementById('putmio-player')) {
      window.location.reload();
    }
  }
    const body = new URLSearchParams({
      _csrf: window.PUTMIO.csrf,
      id: String(subId),
    });
    const response = await fetch(window.PUTMIO.baseUrl + '/api/subtitles/delete', {
      method: 'POST',
      body: body,
    });
    const data = await response.json().catch(function () { return {}; });
    if (!response.ok || !data.ok) {
      throw new Error(data.error || 'Errore eliminazione');
    }
    if (activeSubtitleId === subId) {
      activeSubtitleId = null;
      scheduleSavePreference();
      if (window.PutMioPlayerSubtitles) {
        window.PutMioPlayerSubtitles.apply(null, offsetMs, availableSubtitles);
      }
    }
    await reloadSubtitles(currentModalMediaId || mediaId);
  }

  function setActiveSubtitle(subId) {
    activeSubtitleId = subId ? parseInt(subId, 10) : null;
    renderSelectOptions();
    scheduleSavePreference();
    if (window.PutMioPlayerSubtitles) {
      window.PutMioPlayerSubtitles.apply(activeSubtitleId, offsetMs, availableSubtitles);
    }
    closeModal();
  }

  function adjustOffset(deltaMs) {
    offsetMs += deltaMs;
    if (offsetInput) offsetInput.value = String((offsetMs / 1000).toFixed(1));
    scheduleSavePreference();
    if (window.PutMioPlayerSubtitles) {
      window.PutMioPlayerSubtitles.apply(activeSubtitleId, offsetMs, availableSubtitles);
    }
  }

  if (modalRoot) {
    modalRoot.querySelectorAll('[data-pm-subtitle-close]').forEach(function (el) {
      el.addEventListener('click', closeModal);
    });
  }

  if (manageBtn) {
    manageBtn.addEventListener('click', function () { openModal(mediaId); });
  }

  if (catalogManageBtn) {
    catalogManageBtn.addEventListener('click', function () {
      const id = parseInt(catalogManageBtn.getAttribute('data-media-id') || '0', 10);
      openModal(id || mediaId);
    });
  }

  if (searchBtn) {
    searchBtn.addEventListener('click', searchRemote);
    searchBtn.disabled = !configured;
  }

  if (cachedList) {
    cachedList.addEventListener('click', function (evt) {
      const useBtn = evt.target.closest('[data-pm-sub-use]');
      if (useBtn) {
        setActiveSubtitle(useBtn.getAttribute('data-pm-sub-use'));
        return;
      }
      const delBtn = evt.target.closest('[data-pm-sub-delete]');
      if (delBtn) {
        deleteSubtitle(parseInt(delBtn.getAttribute('data-pm-sub-delete'), 10)).catch(function (e) {
          if (window.pmToast) window.pmToast(e.message, 'error');
        });
      }
    });
  }

  if (searchList) {
    searchList.addEventListener('click', function (evt) {
      const btn = evt.target.closest('[data-pm-sub-dl]');
      if (!btn) return;
      btn.disabled = true;
      downloadSubtitle(
        btn.getAttribute('data-pm-sub-dl'),
        btn.getAttribute('data-lang'),
        btn.getAttribute('data-label')
      ).catch(function (e) {
        if (window.pmToast) window.pmToast(e.message, 'error');
        btn.disabled = false;
      });
    });
  }

  if (subtitleSelect) {
    subtitleSelect.addEventListener('change', function () {
      const val = subtitleSelect.value;
      setActiveSubtitle(val || null);
    });
    renderSelectOptions();
  }

  if (offsetPanel) {
    offsetPanel.addEventListener('click', function (evt) {
      const btn = evt.target.closest('[data-pm-offset]');
      if (!btn) return;
      adjustOffset(parseInt(btn.getAttribute('data-pm-offset'), 10));
    });
  }

  if (offsetInput) {
    offsetInput.addEventListener('change', function () {
      const sec = parseFloat(offsetInput.value);
      offsetMs = isFinite(sec) ? Math.round(sec * 1000) : 0;
      scheduleSavePreference();
      if (window.PutMioPlayerSubtitles) {
        window.PutMioPlayerSubtitles.apply(activeSubtitleId, offsetMs, availableSubtitles);
      }
    });
  }

  if (offsetResetBtn) {
    offsetResetBtn.addEventListener('click', function () {
      offsetMs = 0;
      if (offsetInput) offsetInput.value = '0';
      scheduleSavePreference();
      if (window.PutMioPlayerSubtitles) {
        window.PutMioPlayerSubtitles.apply(activeSubtitleId, offsetMs, availableSubtitles);
      }
    });
  }

  document.addEventListener('keydown', function (evt) {
    if (evt.key === 'Escape' && modalRoot && !modalRoot.classList.contains('hidden')) {
      closeModal();
    }
  });

  window.PutMioSubtitles = {
    open: openModal,
    reload: reloadSubtitles,
    getState: function () {
      return { activeSubtitleId: activeSubtitleId, offsetMs: offsetMs, availableSubtitles: availableSubtitles };
    },
  };
})();
