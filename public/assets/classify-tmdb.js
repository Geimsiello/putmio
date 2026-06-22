(function () {
  if (!window.PUTMIO) {
    return;
  }

  const panel = document.getElementById('classify-tmdb-panel');
  const scanBtn = document.getElementById('classify-tmdb-scan');
  const saveBtn = document.getElementById('classify-tmdb-save');
  const selectAllBtn = document.getElementById('classify-tmdb-select-all');
  const clearBtn = document.getElementById('classify-tmdb-clear');
  const listEl = document.getElementById('classify-tmdb-list');
  const progressEl = document.getElementById('classify-tmdb-progress');
  const summaryEl = document.getElementById('classify-tmdb-summary');

  if (!panel || !scanBtn || !saveBtn || !listEl) {
    return;
  }

  const labels = window.PUTMIO.classifyTmdbLabels || {};
  const mediaIds = (window.PUTMIO.classifyTmdb && window.PUTMIO.classifyTmdb.mediaIds) || [];
  const suggestions = new Map();
  const checkedItems = new Set();
  const selectedCandidateIndex = new Map();
  let scanning = false;
  let saving = false;

  function label(key, fallback) {
    return labels[key] || fallback;
  }

  function mediaTypeLabel(type) {
    const map = {
      film: label('film', 'Film'),
      serie: label('serie', 'Serie TV'),
      animazione: label('animazione', 'Animazione'),
      altro: label('altro', 'Altro'),
    };
    return map[type] || type;
  }

  function tmdbTypeLabel(type) {
    return type === 'tv' ? label('serie', 'Serie TV') : label('film', 'Film');
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function setProgress(text) {
    if (progressEl) {
      progressEl.textContent = text || '';
    }
  }

  function candidatesForRow(row) {
    if (Array.isArray(row.candidates) && row.candidates.length > 0) {
      return row.candidates;
    }
    return row.match ? [row.match] : [];
  }

  function selectedMatchForRow(row) {
    const candidates = candidatesForRow(row);
    if (candidates.length === 0) {
      return null;
    }
    const mediaId = row.media_id;
    const index = selectedCandidateIndex.get(mediaId) ?? 0;
    return candidates[index] || candidates[0] || null;
  }

  function updateSummary() {
    if (!summaryEl) {
      return;
    }
    const withMatch = Array.from(suggestions.values()).filter((row) => candidatesForRow(row).length > 0);
    const checked = listEl.querySelectorAll('input[type="checkbox"][data-classify-tmdb-item]:checked').length;
    summaryEl.textContent = label(
      'summary',
      ':matched con corrispondenza, :checked selezionati'
    )
      .replace(':matched', String(withMatch.length))
      .replace(':checked', String(checked));
    saveBtn.disabled = saving || checked === 0;
  }

  function formatYear(year) {
    if (year) {
      return String(year);
    }
    return label('no_year', 'n.d.');
  }

  function renderCandidateOption(mediaId, candidate, index, selectedIndex, disabled) {
    const option = document.createElement('label');
    option.className = 'flex gap-3 items-start rounded-lg border border-outline-variant/25 bg-surface/40 p-2.5 cursor-pointer hover:border-primary/30 hover:bg-primary/5 transition-colors';

    const radio = document.createElement('input');
    radio.type = 'radio';
    radio.name = 'classify-tmdb-candidate-' + String(mediaId);
    radio.className = 'mt-1 h-4 w-4 border-outline-variant text-primary focus:ring-primary/50';
    radio.value = String(index);
    radio.checked = index === selectedIndex;
    radio.disabled = disabled;
    radio.addEventListener('change', function () {
      if (!radio.checked) {
        return;
      }
      selectedCandidateIndex.set(mediaId, index);
      const row = suggestions.get(mediaId);
      if (row) {
        row.match = candidate;
      }
    });

    const content = document.createElement('div');
    content.className = 'min-w-0 flex-1';

    const titleLine = document.createElement('p');
    titleLine.className = 'text-sm text-on-surface font-label-md leading-snug';
    titleLine.innerHTML = escapeHtml(candidate.title)
      + ' <span class="text-on-surface-variant font-body-md">('
      + escapeHtml(formatYear(candidate.year))
      + ')</span>';
    content.appendChild(titleLine);

    const meta = document.createElement('p');
    meta.className = 'text-xs text-on-surface-variant mt-1';
    const metaParts = [
      tmdbTypeLabel(candidate.tmdb_type),
      mediaTypeLabel(candidate.media_type),
      label('confidence', 'Affidabilità :value%').replace(':value', String(candidate.confidence ?? '')),
    ];
    if (candidate.vote_average) {
      metaParts.push(
        label('rating', 'Voto :value').replace(':value', Number(candidate.vote_average).toFixed(1))
      );
    }
    meta.textContent = metaParts.filter(Boolean).join(' · ');
    content.appendChild(meta);

    if (candidate.original_title && candidate.original_title !== candidate.title) {
      const original = document.createElement('p');
      original.className = 'text-xs text-on-surface-variant/80 mt-1 truncate';
      original.textContent = candidate.original_title;
      content.appendChild(original);
    }

    if (candidate.overview) {
      const overview = document.createElement('p');
      overview.className = 'text-[11px] text-outline mt-1 line-clamp-2';
      overview.textContent = candidate.overview;
      content.appendChild(overview);
    }

    option.appendChild(radio);
    option.appendChild(content);

    if (candidate.poster_url) {
      const poster = document.createElement('img');
      poster.src = candidate.poster_url;
      poster.alt = '';
      poster.className = 'w-10 h-[60px] rounded-md object-cover border border-outline-variant/30 shrink-0 bg-surface-variant/30';
      poster.loading = 'lazy';
      option.appendChild(poster);
    }

    return option;
  }

  function renderList() {
    listEl.innerHTML = '';
    const rows = Array.from(suggestions.values());
    if (rows.length === 0) {
      listEl.innerHTML = '<p class="text-sm text-on-surface-variant px-2 py-4 text-center">' + escapeHtml(label('empty', 'Nessun suggerimento.')) + '</p>';
      updateSummary();
      return;
    }

    rows.forEach((row) => {
      const mediaId = row.media_id;
      const candidates = candidatesForRow(row);
      const hasMatch = candidates.length > 0;
      const selectedIndex = selectedCandidateIndex.get(mediaId) ?? 0;
      const disabled = !hasMatch || saving;

      const card = document.createElement('article');
      card.className = 'rounded-xl border border-outline-variant/30 bg-surface-container-high p-4';
      card.dataset.mediaId = String(mediaId);

      const header = document.createElement('label');
      header.className = 'flex gap-3 items-start mb-3 cursor-pointer';

      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.className = 'mt-1 h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary/50 shrink-0 cursor-pointer';
      checkbox.dataset.classifyTmdbItem = '1';
      checkbox.disabled = disabled;
      checkbox.checked = checkedItems.has(mediaId);
      checkbox.addEventListener('click', function (event) {
        event.stopPropagation();
      });
      checkbox.addEventListener('change', function () {
        if (checkbox.checked) {
          checkedItems.add(mediaId);
        } else {
          checkedItems.delete(mediaId);
        }
        updateSummary();
      });

      const headerBody = document.createElement('div');
      headerBody.className = 'min-w-0 flex-1';

      const fileLine = document.createElement('p');
      fileLine.className = 'text-xs text-on-surface-variant font-mono truncate';
      fileLine.textContent = row.file_label || '';
      headerBody.appendChild(fileLine);

      if (row.shared_by_username) {
        const shared = document.createElement('span');
        shared.className = 'inline-flex items-center gap-1 rounded-full bg-primary/15 border border-primary/30 px-2 py-0.5 text-[10px] font-label-md text-primary mt-2';
        shared.textContent = label('shared_from', 'Condiviso da :user').replace(':user', row.shared_by_username);
        headerBody.appendChild(shared);
      }

      if (row.query) {
        const query = document.createElement('p');
        query.className = 'text-[11px] text-outline mt-2';
        query.textContent = label('searched_as', 'Ricerca: :query').replace(':query', row.query);
        headerBody.appendChild(query);
      }

      if (row.year_hint) {
        const yearHint = document.createElement('p');
        yearHint.className = 'text-[11px] text-on-surface-variant mt-1';
        yearHint.textContent = label('year_hint', 'Anno nel file: :year').replace(':year', String(row.year_hint));
        headerBody.appendChild(yearHint);
      }

      header.appendChild(checkbox);
      header.appendChild(headerBody);
      card.appendChild(header);

      if (hasMatch) {
        const pickLabel = document.createElement('p');
        pickLabel.className = 'text-xs font-label-md text-on-surface-variant mb-2 ml-7';
        pickLabel.textContent = label('pick_match', 'Scegli la corrispondenza TMDB');
        card.appendChild(pickLabel);

        const options = document.createElement('div');
        options.className = 'space-y-2 ml-7';
        candidates.forEach((candidate, index) => {
          options.appendChild(renderCandidateOption(mediaId, candidate, index, selectedIndex, disabled));
        });
        card.appendChild(options);
      } else {
        const none = document.createElement('p');
        none.className = 'text-sm text-warning/90 ml-7';
        none.textContent = row.error || label('no_match', 'Nessuna corrispondenza affidabile su TMDB.');
        card.appendChild(none);
      }

      listEl.appendChild(card);
    });

    updateSummary();
  }

  async function fetchSuggestion(mediaId) {
    const response = await fetch(
      window.PUTMIO.baseUrl + '/api/tmdb/classify-suggest?media_id=' + encodeURIComponent(String(mediaId))
    );
    const data = await response.json();
    if (!response.ok) {
      throw new Error(data.error || label('scan_error', 'Errore durante la scansione TMDB.'));
    }
    return data.suggestion;
  }

  function storeSuggestion(mediaId, suggestion) {
    if (!suggestion) {
      return;
    }
    suggestions.set(mediaId, suggestion);
    selectedCandidateIndex.set(mediaId, 0);
    if (suggestion.auto_select) {
      checkedItems.add(mediaId);
    }
    const match = selectedMatchForRow(suggestion);
    if (match) {
      suggestion.match = match;
    }
  }

  async function runScan() {
    if (scanning || mediaIds.length === 0) {
      return;
    }

    scanning = true;
    scanBtn.disabled = true;
    saveBtn.disabled = true;
    if (selectAllBtn) {
      selectAllBtn.disabled = true;
    }
    if (clearBtn) {
      clearBtn.disabled = true;
    }
    panel.classList.remove('hidden');
    suggestions.clear();
    checkedItems.clear();
    selectedCandidateIndex.clear();
    renderList();

    let index = 0;
    for (const mediaId of mediaIds) {
      index += 1;
      setProgress(
        label('scanning', 'Scansione TMDB… :current/:total')
          .replace(':current', String(index))
          .replace(':total', String(mediaIds.length))
      );
      try {
        const suggestion = await fetchSuggestion(mediaId);
        storeSuggestion(mediaId, suggestion);
        renderList();
      } catch (error) {
        suggestions.set(mediaId, {
          media_id: mediaId,
          file_label: '',
          query: '',
          candidates: [],
          match: null,
          confidence: 0,
          auto_select: false,
          error: error.message || label('scan_error', 'Errore durante la scansione TMDB.'),
        });
        renderList();
      }
    }

    scanning = false;
    scanBtn.disabled = false;
    if (selectAllBtn) {
      selectAllBtn.disabled = false;
    }
    if (clearBtn) {
      clearBtn.disabled = false;
    }
    renderList();
    setProgress(label('scan_done', 'Scansione completata.'));
    updateSummary();
  }

  async function saveSelected() {
    if (saving) {
      return;
    }

    const selected = [];
    listEl.querySelectorAll('input[type="checkbox"][data-classify-tmdb-item]:checked').forEach((input) => {
      const card = input.closest('[data-media-id]');
      if (!card) {
        return;
      }
      const mediaId = parseInt(card.dataset.mediaId || '0', 10);
      const row = suggestions.get(mediaId);
      const match = row ? selectedMatchForRow(row) : null;
      if (!row || !match) {
        return;
      }
      selected.push({
        media_id: mediaId,
        tmdb_id: match.tmdb_id,
        tmdb_type: match.tmdb_type,
      });
    });

    if (selected.length === 0) {
      if (window.pmToast) {
        window.pmToast(label('nothing_selected', 'Seleziona almeno un suggerimento.'), 'warning');
      }
      return;
    }

    saving = true;
    saveBtn.disabled = true;
    scanBtn.disabled = true;
    setProgress(label('saving', 'Salvataggio in corso…'));

    try {
      const body = new URLSearchParams({
        _csrf: window.PUTMIO.csrf,
        items: JSON.stringify(selected),
      });
      const response = await fetch(window.PUTMIO.baseUrl + '/api/tmdb/classify-apply', {
        method: 'POST',
        body,
      });
      const data = await response.json();
      if (!response.ok || !data.ok) {
        throw new Error(data.error || label('save_error', 'Errore nel salvataggio.'));
      }
      if (window.pmToast) {
        window.pmToast(data.message || label('saved', 'Associazioni salvate.'), 'success', 5000);
      }
      window.setTimeout(function () {
        window.location.reload();
      }, 700);
    } catch (error) {
      if (window.pmToast) {
        window.pmToast(error.message || label('save_error', 'Errore nel salvataggio.'), 'error');
      }
      saving = false;
      scanBtn.disabled = false;
      renderList();
      updateSummary();
      setProgress('');
    }
  }

  scanBtn.addEventListener('click', runScan);
  saveBtn.addEventListener('click', saveSelected);

  if (selectAllBtn) {
    selectAllBtn.addEventListener('click', function () {
      listEl.querySelectorAll('input[type="checkbox"][data-classify-tmdb-item]:not(:disabled)').forEach((input) => {
        const card = input.closest('[data-media-id]');
        if (!card) {
          return;
        }
        const mediaId = parseInt(card.dataset.mediaId || '0', 10);
        checkedItems.add(mediaId);
        input.checked = true;
      });
      updateSummary();
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      checkedItems.clear();
      listEl.querySelectorAll('input[type="checkbox"][data-classify-tmdb-item]').forEach((input) => {
        input.checked = false;
      });
      updateSummary();
    });
  }
})();
