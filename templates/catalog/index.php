<?php
use PutMio\Config;

$appUrl = rtrim(Config::get('app.url'), '/');
$catalogReturnPath = putmio_catalog_path($filters, $page);
$queryBase = array_filter([
    'q' => $filters['q'] ?? null,
    'type' => $filters['type'] ?? null,
    'genre' => $filters['genre'] ?? null,
    'shared_by' => $filters['shared_by'] ?? null,
    'page' => $page > 1 ? $page : null,
], static fn ($v) => $v !== null && $v !== '');
$loadMoreQuery = http_build_query($queryBase);
?>
<div class="mb-10">
  <h1 class="text-display-lg-mobile md:text-display-lg font-display-lg text-on-surface mb-6 md:mb-8"><?= putmio_lang('catalog') ?></h1>

  <form method="get" class="flex flex-col md:flex-row md:flex-wrap items-stretch md:items-center gap-4 bg-surface-container p-4 rounded-xl border border-outline-variant/20 shadow-lg">
    <div class="relative flex-grow w-full min-w-0">
      <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">search</span>
      <input
        name="q"
        value="<?= putmio_e($filters['q'] ?? '') ?>"
        placeholder="<?= putmio_lang('search') ?>..."
        class="w-full bg-surface-variant border-none rounded-lg pl-10 pr-4 py-2.5 text-on-surface placeholder:text-on-surface-variant focus:ring-2 focus:ring-primary/50 transition-all"
        type="search"
        autocomplete="off"
      >
    </div>
    <div class="relative w-full md:w-44 shrink-0">
      <label class="sr-only" for="catalog-type"><?= putmio_lang('typology') ?></label>
      <select
        id="catalog-type"
        name="type"
        class="pm-select w-full bg-surface-variant border-none rounded-lg px-4 py-2.5 text-on-surface focus:ring-2 focus:ring-primary/50 transition-all cursor-pointer pr-10"
      >
        <option value=""><?= putmio_lang('all') ?></option>
        <?php foreach (['film', 'serie', 'animazione', 'altro'] as $t): ?>
        <option value="<?= $t ?>" <?= ($filters['type'] ?? '') === $t ? 'selected' : '' ?>><?= putmio_lang($t) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant">expand_more</span>
    </div>
    <?php if (!empty($genres)): ?>
    <div class="relative w-full md:w-44 shrink-0">
      <label class="sr-only" for="catalog-genre"><?= putmio_lang('genre') ?></label>
      <select
        id="catalog-genre"
        name="genre"
        class="pm-select w-full bg-surface-variant border-none rounded-lg px-4 py-2.5 text-on-surface focus:ring-2 focus:ring-primary/50 transition-all cursor-pointer pr-10"
      >
        <option value=""><?= putmio_lang('all_genres') ?></option>
        <?php foreach ($genres as $genre): ?>
        <option value="<?= (int) $genre['id'] ?>" <?= (string) ($filters['genre'] ?? '') === (string) $genre['id'] ? 'selected' : '' ?>><?= putmio_e($genre['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant">expand_more</span>
    </div>
    <?php endif; ?>
    <?php if (!empty($sharers)): ?>
    <div class="relative w-full md:w-44 shrink-0">
      <label class="sr-only" for="catalog-shared-by"><?= putmio_lang('shared_by') ?></label>
      <select
        id="catalog-shared-by"
        name="shared_by"
        class="pm-select w-full bg-surface-variant border-none rounded-lg px-4 py-2.5 text-on-surface focus:ring-2 focus:ring-primary/50 transition-all cursor-pointer pr-10"
      >
        <option value=""><?= putmio_lang('all_sharers') ?></option>
        <?php foreach ($sharers as $username): ?>
        <option value="<?= putmio_e($username) ?>" <?= ($filters['shared_by'] ?? '') === $username ? 'selected' : '' ?>><?= putmio_e($username) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant">expand_more</span>
    </div>
    <?php endif; ?>
    <button type="submit" class="w-full md:w-auto px-8 py-2.5 bg-primary text-on-primary font-bold rounded-lg hover:bg-primary-container transition-all active:scale-95 flex items-center justify-center gap-2 shrink-0">
      <span class="material-symbols-outlined text-[20px]">filter_list</span>
      <?= putmio_lang('search') ?>
    </button>
  </form>
</div>

<?php if (empty($items)): ?>
<p class="text-on-surface-variant"><?= putmio_lang('no_media') ?></p>
<?php else: ?>
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4 md:gap-6" data-catalog-grid>
  <?php require putmio_base_path() . '/templates/catalog/_grid-items.php'; ?>
</div>

<?php if (!empty($hasMore)): ?>
<div class="mt-12 md:mt-16 flex justify-center" data-catalog-load-more-wrap>
  <button
    type="button"
    data-catalog-load-more
    data-offset="<?= count($items) ?>"
    data-query="<?= putmio_e($loadMoreQuery) ?>"
    class="px-10 py-3 bg-surface-variant text-on-surface font-label-md rounded-full border border-outline-variant/30 hover:bg-surface-bright transition-all active:scale-95 shadow-lg disabled:opacity-60 disabled:cursor-wait"
  >
    <?= putmio_lang('load_more') ?>
  </button>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if (putmio_admin_ui_enabled()): ?>
<?php
$mediaId = 0;
$tmdbSuggestedQuery = '';
$fileName = '';
$tmdbAutoOpen = false;
$tmdbShowTrigger = false;
$tmdbCatalogMode = true;
require putmio_base_path() . '/templates/partials/tmdb-link-modal.php';
?>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>[x-cloak]{display:none!important}</style>
<?php endif; ?>
