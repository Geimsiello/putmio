<?php use PutMio\Config; $appUrl = rtrim(Config::get('app.url'), '/');
$adminCrumbLabel = putmio_lang('classify');
$adminPageTitle = putmio_lang('classify');
$adminPageDescription = putmio_lang('classify_page_desc');
$tmdbConfigured = $tmdbConfigured ?? false;
$itemCount = count($items ?? []);
require putmio_base_path() . '/templates/partials/admin-header.php';
?>
<section class="mb-6 rounded-xl border border-outline-variant/30 bg-surface-container-high p-4 md:p-5 flex items-center gap-4">
  <div class="p-2.5 bg-tertiary/10 text-tertiary rounded-lg shrink-0">
    <span class="material-symbols-outlined">folder_open</span>
  </div>
  <div>
    <p class="text-[32px] leading-none font-bold text-on-surface"><?= (int) $itemCount ?></p>
    <p class="text-on-surface-variant text-label-md font-label-md mt-1"><?= putmio_e(putmio_lang('classify_pending_label')) ?></p>
  </div>
</section>
<?php if ($itemCount > 0): ?>
<section class="mb-8 rounded-xl border border-outline-variant/30 bg-surface-container p-5 md:p-6">
  <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
    <div class="min-w-0">
      <h2 class="text-headline-md font-headline-md text-on-surface mb-1"><?= putmio_e(putmio_lang('classify_tmdb_title')) ?></h2>
      <p class="text-body-md text-on-surface-variant"><?= putmio_e(putmio_lang('classify_tmdb_desc')) ?></p>
    </div>
    <div class="flex flex-wrap gap-2 shrink-0">
      <button
        type="button"
        id="classify-tmdb-scan"
        class="pm-btn-primary text-sm inline-flex items-center gap-2"
        <?= $tmdbConfigured ? '' : 'disabled title="' . putmio_e(putmio_lang('classify_tmdb_not_configured')) . '"' ?>
      >
        <span class="material-symbols-outlined text-[18px]">travel_explore</span>
        <?= putmio_e(putmio_lang('classify_tmdb_scan')) ?>
      </button>
    </div>
  </div>
  <?php if (!$tmdbConfigured): ?>
  <p class="text-sm text-warning/90 mt-4"><?= putmio_e(putmio_lang('classify_tmdb_not_configured')) ?> <a href="<?= putmio_e($appUrl) ?>/admin/impostazioni" class="text-primary hover:underline"><?= putmio_e(putmio_lang('settings')) ?></a>.</p>
  <?php endif; ?>
</section>
<section id="classify-tmdb-panel" class="hidden mb-8 rounded-xl border border-outline-variant/30 bg-surface-container p-5 md:p-6">
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
    <div>
      <h2 class="text-headline-sm font-headline-md text-on-surface mb-1"><?= putmio_e(putmio_lang('classify_tmdb_results_title')) ?></h2>
      <p id="classify-tmdb-progress" class="text-sm text-on-surface-variant"></p>
      <p id="classify-tmdb-summary" class="text-xs text-outline mt-1"></p>
    </div>
    <div class="flex flex-wrap gap-2">
      <button type="button" id="classify-tmdb-select-all" class="px-4 py-2 rounded-xl border border-outline-variant/40 text-on-surface-variant font-label-md text-sm hover:bg-surface-variant/30 hover:text-on-surface transition-all active:scale-95"><?= putmio_e(putmio_lang('classify_tmdb_select_all')) ?></button>
      <button type="button" id="classify-tmdb-clear" class="px-4 py-2 rounded-xl border border-outline-variant/40 text-on-surface-variant font-label-md text-sm hover:bg-surface-variant/30 hover:text-on-surface transition-all active:scale-95"><?= putmio_e(putmio_lang('classify_tmdb_clear')) ?></button>
      <button type="button" id="classify-tmdb-save" class="pm-btn-primary text-sm py-2" disabled><?= putmio_e(putmio_lang('classify_tmdb_save')) ?></button>
    </div>
  </div>
  <div id="classify-tmdb-list" class="space-y-3"></div>
</section>
<?php else: ?>
<p class="text-on-surface-variant"><?= putmio_e(putmio_lang('classify_empty')) ?></p>
<?php endif; ?>
