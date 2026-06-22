<?php
/** @var int $mediaId */
/** @var string $tmdbSuggestedQuery */
/** @var string $fileName */
/** @var bool $tmdbAutoOpen */
$tmdbShowTrigger = $tmdbShowTrigger ?? true;
$tmdbCatalogMode = $tmdbCatalogMode ?? false;
$tmdbTriggerLabel = $tmdbTriggerLabel ?? 'Collega metadati TMDB';
?>
<div
  x-data="tmdbLinkModal(<?= (int) $mediaId ?>, <?= htmlspecialchars(json_encode($tmdbSuggestedQuery, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($fileName, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>, <?= $tmdbAutoOpen ? 'true' : 'false' ?>, <?= $tmdbCatalogMode ? 'true' : 'false' ?>)"
  @keydown.escape.window="closeModal()"
>
  <?php if ($tmdbShowTrigger): ?>
  <button
    type="button"
    @click="openModal()"
    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-outline-variant/40 text-on-surface-variant font-label-md text-label-md hover:bg-surface-variant/30 hover:text-on-surface transition-all active:scale-95"
  >
    <span class="material-symbols-outlined text-[18px]">movie_filter</span>
    <?= putmio_e($tmdbTriggerLabel) ?>
  </button>
  <?php endif; ?>

  <template x-teleport="body">
    <div
      x-show="isOpen"
      x-cloak
      class="fixed inset-0 z-[100] bg-[#161616]/80 backdrop-blur-md flex items-center justify-center p-4"
      @click.self="closeModal()"
      x-transition:enter="transition ease-out duration-200"
      x-transition:enter-start="opacity-0"
      x-transition:enter-end="opacity-100"
      x-transition:leave="transition ease-in duration-150"
      x-transition:leave-start="opacity-100"
      x-transition:leave-end="opacity-0"
    >
      <section
        class="bg-surface-container-low w-full max-w-[800px] max-h-[min(92vh,921px)] rounded-xl shadow-xl flex flex-col overflow-hidden border border-outline-variant/30"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.stop
        role="dialog"
        aria-modal="true"
        aria-labelledby="tmdb-modal-title"
      >
        <header class="flex items-start justify-between gap-4 px-6 py-4 border-b border-outline-variant/30 bg-surface-container">
          <div class="min-w-0 flex-1">
            <h1 id="tmdb-modal-title" class="font-headline-md text-headline-md text-on-surface">Collega metadati TMDB</h1>
            <p class="mt-1 font-label-sm text-label-sm text-on-surface-variant truncate" :title="fileName" x-text="fileName"></p>
          </div>
          <button type="button" @click="closeModal()" class="p-2 hover:bg-surface-variant rounded-full text-on-surface-variant transition-colors active:scale-95 shrink-0" aria-label="Chiudi">
            <span class="material-symbols-outlined">close</span>
          </button>
        </header>

        <div class="flex-1 flex flex-col md:flex-row overflow-hidden min-h-0">
          <aside class="w-full md:w-[320px] border-b md:border-b-0 md:border-r border-outline-variant/20 flex flex-col bg-surface-container-lowest/50 min-h-0 md:max-h-none max-h-[40vh]">
            <div class="p-4 shrink-0">
              <div class="relative group">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline group-focus-within:text-primary transition-colors">search</span>
                <input
                  x-model="query"
                  @keydown.enter.prevent="search()"
                  type="text"
                  placeholder="Cerca film o serie TV..."
                  class="w-full pl-10 pr-4 py-2 bg-surface-variant/40 border border-outline-variant rounded-lg font-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all placeholder:text-on-surface-variant/50"
                >
              </div>
              <button type="button" @click="search()" class="mt-2 w-full pm-btn-primary text-sm py-2" :disabled="loading">
                <span x-show="!loading">Cerca</span>
                <span x-show="loading">Ricerca...</span>
              </button>
            </div>

            <div class="flex-1 overflow-y-auto custom-scrollbar px-2 pb-4 min-h-0">
              <p x-show="!loading && searched && results.length === 0" class="px-2 py-4 text-sm text-on-surface-variant text-center">Nessun risultato.</p>
              <div class="space-y-1">
                <template x-for="r in results" :key="r.id + '-' + resultType(r)">
                  <button
                    type="button"
                    @click="select(r)"
                    class="w-full flex gap-3 p-2 rounded-lg text-left transition-all border"
                    :class="isSelected(r) ? 'bg-primary/10 border-primary/20' : 'border-transparent hover:bg-surface-variant/40'"
                  >
                    <div class="w-[46px] h-[69px] rounded shadow-sm shrink-0 overflow-hidden bg-surface-variant flex items-center justify-center">
                      <img
                        x-show="r.poster_path"
                        :src="posterUrl(r.poster_path, 'w92')"
                        alt=""
                        class="w-full h-full object-cover"
                        :class="isSelected(r) ? '' : 'opacity-80 group-hover:opacity-100'"
                      >
                      <span x-show="!r.poster_path" class="material-symbols-outlined text-outline text-xl">image</span>
                    </div>
                    <div class="flex flex-col justify-center min-w-0 flex-1">
                      <span class="font-label-md text-label-md truncate" :class="isSelected(r) ? 'text-on-surface' : 'text-on-surface-variant'" x-text="resultTitle(r)"></span>
                      <span
                        x-show="resultOriginal(r) && resultOriginal(r) !== resultTitle(r)"
                        class="font-label-sm text-label-sm text-on-surface-variant/70 truncate"
                        x-text="resultOriginal(r)"
                      ></span>
                      <div class="flex flex-wrap items-center gap-2 mt-1">
                        <span class="font-label-sm text-label-sm text-on-surface-variant" x-text="resultYear(r) || '—'"></span>
                        <span class="px-1.5 py-0.5 rounded text-[10px] font-label-sm uppercase tracking-wider" :class="typeBadgeClass(r)" x-text="typeLabel(r)"></span>
                        <span x-show="r.vote_average" class="font-label-sm text-label-sm text-on-surface-variant/70" x-text="'★ ' + Number(r.vote_average).toFixed(1)"></span>
                      </div>
                      <p x-show="r.overview" class="mt-1 text-[11px] leading-snug text-on-surface-variant/60 line-clamp-2" x-text="r.overview"></p>
                    </div>
                  </button>
                </template>
              </div>
            </div>
          </aside>

          <article class="flex-1 flex flex-col bg-surface-container-low min-h-0 overflow-hidden">
            <div x-show="!selected && !previewLoading" class="flex-1 flex flex-col items-center justify-center text-center text-on-surface-variant px-6 py-6">
              <span class="material-symbols-outlined text-5xl mb-3 opacity-40">movie</span>
              <p class="font-body-md">Seleziona un risultato per vedere l'anteprima dei metadati.</p>
            </div>

            <div x-show="previewLoading" class="flex-1 flex items-center justify-center text-on-surface-variant px-6 py-6">
              <span class="font-label-md">Caricamento anteprima...</span>
            </div>

            <div x-show="preview && !previewLoading" class="flex-1 overflow-y-auto custom-scrollbar p-6 min-h-0">
              <div class="flex flex-col sm:flex-row gap-6">
                <div class="w-full sm:w-[200px] shrink-0">
                  <div class="aspect-[2/3] w-full rounded-xl overflow-hidden shadow-2xl border border-outline-variant/30 bg-surface-variant">
                    <img x-show="preview?.poster_path" :src="posterUrl(preview?.poster_path, 'w500')" alt="" class="w-full h-full object-cover">
                    <div x-show="!preview?.poster_path" class="w-full h-full flex items-center justify-center text-outline">
                      <span class="material-symbols-outlined text-5xl">image</span>
                    </div>
                  </div>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span x-show="isTopResult" class="bg-success/10 text-success px-2 py-0.5 rounded-full font-label-sm text-[11px] uppercase border border-success/20">Consigliato</span>
                    <span class="font-label-sm text-outline" x-text="'ID TMDB: ' + (preview?.id || '')"></span>
                  </div>
                  <h2 class="font-headline-md text-headline-md text-on-surface mb-1" x-text="preview?.title || ''"></h2>
                  <p
                    x-show="preview?.original_title && preview.original_title !== preview.title"
                    class="font-label-sm text-label-sm text-on-surface-variant mb-2"
                    x-text="preview.original_title"
                  ></p>
                  <div class="flex flex-wrap gap-2 mb-4">
                    <template x-for="genre in (preview?.genres || [])" :key="genre">
                      <span class="text-on-surface-variant font-label-md text-label-md bg-surface-variant/40 px-3 py-1 rounded-full" x-text="genre"></span>
                    </template>
                  </div>
                  <div class="mb-4">
                    <p
                      class="text-on-surface-variant font-body-md leading-relaxed"
                      :class="!previewOverviewExpanded && isOverviewLong(preview?.overview) ? 'line-clamp-3' : ''"
                      x-text="preview?.overview || 'Nessuna descrizione disponibile.'"
                    ></p>
                    <button
                      type="button"
                      x-show="isOverviewLong(preview?.overview)"
                      @click="previewOverviewExpanded = !previewOverviewExpanded"
                      class="mt-1 text-primary font-label-sm text-label-sm hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/50 rounded"
                      x-text="previewOverviewExpanded ? 'mostra meno' : 'carica altro'"
                    ></button>
                  </div>
                  <div class="grid grid-cols-2 gap-4 border-t border-outline-variant/20 pt-4">
                    <div x-show="preview?.director">
                      <span class="block font-label-sm text-outline uppercase tracking-widest text-[10px] mb-1" x-text="preview?.media_type === 'tv' ? 'Creatore' : 'Regista'"></span>
                      <span class="font-body-md text-on-surface" x-text="preview?.director"></span>
                    </div>
                    <div x-show="preview?.runtime">
                      <span class="block font-label-sm text-outline uppercase tracking-widest text-[10px] mb-1">Durata</span>
                      <span class="font-body-md text-on-surface" x-text="preview.runtime + ' min'"></span>
                    </div>
                    <div x-show="preview?.number_of_seasons">
                      <span class="block font-label-sm text-outline uppercase tracking-widest text-[10px] mb-1">Stagioni</span>
                      <span class="font-body-md text-on-surface" x-text="preview.number_of_seasons"></span>
                    </div>
                    <div x-show="preview?.release_date">
                      <span class="block font-label-sm text-outline uppercase tracking-widest text-[10px] mb-1">Data uscita</span>
                      <span class="font-body-md text-on-surface" x-text="formatDate(preview.release_date)"></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <footer
              x-show="preview && !previewLoading"
              class="shrink-0 px-6 py-4 border-t border-outline-variant/20 flex items-center justify-end gap-3 bg-surface-container-low"
            >
              <button type="button" @click="closeModal()" class="px-6 py-2.5 rounded-full border border-outline text-on-surface-variant font-label-md hover:bg-surface-variant/30 hover:text-on-surface transition-all active:scale-95">
                Annulla
              </button>
              <button
                type="button"
                @click="apply()"
                :disabled="!selected || applying"
                class="px-8 py-2.5 rounded-full bg-primary-container text-on-primary-container font-label-md font-bold shadow-lg shadow-primary/20 hover:scale-[1.02] hover:brightness-110 transition-all active:scale-95 flex items-center gap-2 disabled:opacity-50 disabled:pointer-events-none"
              >
                <span class="material-symbols-outlined text-[20px]">check</span>
                <span x-text="applying ? 'Applicazione...' : 'Applica'"></span>
              </button>
            </footer>
          </article>
        </div>
      </section>
    </div>
  </template>
</div>

<script>
function tmdbLinkModal(mediaId, initialQuery, fileName, autoOpen, catalogMode) {
  return {
    mediaId: mediaId || 0,
    isOpen: false,
    query: initialQuery || '',
    fileName: fileName || '',
    results: [],
    selected: null,
    preview: null,
    previewLoading: false,
    loading: false,
    applying: false,
    searched: false,
    previewOverviewExpanded: false,
    init() {
      if (catalogMode) {
        window.addEventListener('catalog-tmdb-link', (e) => {
          const d = e.detail || {};
          this.openFor(d.id, d.query, d.fileName);
        });
      }
      if (autoOpen && this.query.trim()) {
        this.isOpen = true;
        document.body.classList.add('overflow-hidden');
        this.search();
      }
    },
    openFor(id, query, fileName) {
      this.mediaId = id || 0;
      this.query = query || '';
      this.fileName = fileName || '';
      this.results = [];
      this.selected = null;
      this.preview = null;
      this.searched = false;
      this.previewOverviewExpanded = false;
      this.loading = false;
      this.applying = false;
      this.isOpen = true;
      document.body.classList.add('overflow-hidden');
      if (this.query.trim()) {
        this.search();
      }
    },
    openModal() {
      this.isOpen = true;
      document.body.classList.add('overflow-hidden');
      if (this.query.trim() && !this.searched) {
        this.search();
      }
    },
    closeModal() {
      this.isOpen = false;
      document.body.classList.remove('overflow-hidden');
    },
    posterUrl(path, size) {
      if (!path) return '';
      return 'https://image.tmdb.org/t/p/' + size + path;
    },
    resultTitle(r) {
      return r.title || r.name || '';
    },
    resultOriginal(r) {
      return r.original_title || r.original_name || '';
    },
    resultYear(r) {
      const d = r.release_date || r.first_air_date || '';
      return d ? d.slice(0, 4) : '';
    },
    resultType(r) {
      return r.media_type === 'tv' ? 'tv' : 'movie';
    },
    typeLabel(r) {
      return this.resultType(r) === 'tv' ? 'Serie TV' : 'Film';
    },
    typeBadgeClass(r) {
      if (this.resultType(r) === 'tv') {
        return 'bg-secondary-container/20 text-secondary';
      }
      return 'bg-tertiary/10 text-tertiary';
    },
    isSelected(r) {
      return this.selected && this.selected.id === r.id && this.resultType(this.selected) === this.resultType(r);
    },
    get isTopResult() {
      return this.results.length > 0 && this.selected && this.isSelected(this.results[0]);
    },
    formatDate(iso) {
      if (!iso) return '';
      const [y, m, d] = iso.split('-');
      if (!y) return iso;
      return [d, m, y].filter(Boolean).join('/');
    },
    isOverviewLong(text) {
      return (text || '').trim().length > 140;
    },
    async search() {
      const q = this.query.trim();
      if (!q) return;
      this.loading = true;
      this.searched = true;
      try {
        const r = await fetch(window.PUTMIO.baseUrl + '/api/tmdb/search?q=' + encodeURIComponent(q));
        const d = await r.json();
        this.results = d.results || [];
        if (this.results.length > 0) {
          await this.select(this.results[0]);
        } else {
          this.selected = null;
          this.preview = null;
        }
      } finally {
        this.loading = false;
      }
    },
    async select(item) {
      this.selected = item;
      this.previewOverviewExpanded = false;
      this.previewLoading = true;
      this.preview = null;
      try {
        const type = this.resultType(item);
        const r = await fetch(window.PUTMIO.baseUrl + '/api/tmdb/details?id=' + item.id + '&type=' + type);
        const d = await r.json();
        if (d.error) throw new Error(d.error);
        this.preview = d;
      } catch (e) {
        this.preview = {
          id: item.id,
          media_type: this.resultType(item),
          title: this.resultTitle(item),
          original_title: this.resultOriginal(item),
          overview: item.overview || '',
          poster_path: item.poster_path || null,
          release_date: item.release_date || item.first_air_date || null,
          genres: [],
        };
      } finally {
        this.previewLoading = false;
      }
    },
    async apply() {
      if (!this.selected || this.applying) return;
      this.applying = true;
      try {
        const body = new URLSearchParams({
          _csrf: window.PUTMIO.csrf,
          media_id: this.mediaId,
          tmdb_id: this.selected.id,
          tmdb_type: this.resultType(this.selected)
        });
        await fetch(window.PUTMIO.baseUrl + '/api/tmdb/apply', { method: 'POST', body });
        location.reload();
      } finally {
        this.applying = false;
      }
    }
  };
}
</script>
