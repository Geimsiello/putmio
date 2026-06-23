(function () {
  document.addEventListener('click', function (e) {
    const link = e.target.closest('[data-catalog-tmdb-link]');
    if (!link) {
      return;
    }
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) {
      return;
    }
    e.preventDefault();
    window.dispatchEvent(new CustomEvent('catalog-tmdb-link', {
      detail: {
        id: parseInt(link.getAttribute('data-catalog-tmdb-link') || '0', 10),
        query: link.getAttribute('data-catalog-tmdb-query') || '',
        fileName: link.getAttribute('data-catalog-tmdb-file') || '',
      },
    }));
  });

  const loadMoreBtn = document.querySelector('[data-catalog-load-more]');
  if (!loadMoreBtn || !window.PUTMIO) {
    return;
  }

  const grid = document.querySelector('[data-catalog-grid]');
  if (!grid) {
    return;
  }

  loadMoreBtn.addEventListener('click', async function () {
    if (loadMoreBtn.disabled) {
      return;
    }

    const offset = parseInt(loadMoreBtn.getAttribute('data-offset') || '0', 10);
    const params = new URLSearchParams(loadMoreBtn.getAttribute('data-query') || '');
    params.set('offset', String(offset));

    loadMoreBtn.disabled = true;
    const label = loadMoreBtn.textContent;
    loadMoreBtn.setAttribute('aria-busy', 'true');

    try {
      const response = await fetch(window.PUTMIO.baseUrl + '/api/catalog/items?' + params.toString());
      const data = await response.json();
      if (!response.ok || !data.html) {
        throw new Error('load failed');
      }

      grid.insertAdjacentHTML('beforeend', data.html);

      if (data.hasMore) {
        loadMoreBtn.setAttribute('data-offset', String(data.nextOffset));
        loadMoreBtn.disabled = false;
        loadMoreBtn.removeAttribute('aria-busy');
      } else {
        var loadMoreWrap = loadMoreBtn.closest('[data-catalog-load-more-wrap]');
        if (loadMoreWrap) {
          loadMoreWrap.remove();
        }
      }
    } catch (e) {
      loadMoreBtn.disabled = false;
      loadMoreBtn.removeAttribute('aria-busy');
      loadMoreBtn.textContent = label;
      if (window.pmToast) {
        window.pmToast('Impossibile caricare altri contenuti.', 'error');
      }
    }
  });
})();
