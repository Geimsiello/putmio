<?php use PutMio\Auth\Csrf; use PutMio\Config; $appUrl = rtrim(Config::get('app.url'), '/');
$adminCrumbLabel = putmio_lang('classify');
$adminPageTitle = putmio_lang('classify');
$adminPageDescription = putmio_lang('classify_page_desc');
$tmdbConfigured = $tmdbConfigured ?? false;
require putmio_base_path() . '/templates/partials/admin-header.php';
?>
<?php if (!empty($items)): ?>
<section class="mb-8 rounded-xl border border-outline-variant/30 bg-surface-container p-5 md:p-6">
  <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-4">
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
  <p class="text-sm text-warning/90 mb-4"><?= putmio_e(putmio_lang('classify_tmdb_not_configured')) ?> <a href="<?= putmio_e($appUrl) ?>/admin/impostazioni" class="text-primary hover:underline"><?= putmio_e(putmio_lang('settings')) ?></a>.</p>
  <?php endif; ?>
  <div id="classify-tmdb-panel" class="hidden border-t border-outline-variant/20 pt-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
      <div>
        <p id="classify-tmdb-progress" class="text-sm text-on-surface-variant"></p>
        <p id="classify-tmdb-summary" class="text-xs text-outline mt-1"></p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button type="button" id="classify-tmdb-select-all" class="px-4 py-2 rounded-xl border border-outline-variant/40 text-on-surface-variant font-label-md text-sm hover:bg-surface-variant/30 hover:text-on-surface transition-all active:scale-95"><?= putmio_e(putmio_lang('classify_tmdb_select_all')) ?></button>
        <button type="button" id="classify-tmdb-clear" class="px-4 py-2 rounded-xl border border-outline-variant/40 text-on-surface-variant font-label-md text-sm hover:bg-surface-variant/30 hover:text-on-surface transition-all active:scale-95"><?= putmio_e(putmio_lang('classify_tmdb_clear')) ?></button>
        <button type="button" id="classify-tmdb-save" class="pm-btn-primary text-sm py-2" disabled><?= putmio_e(putmio_lang('classify_tmdb_save')) ?></button>
      </div>
    </div>
    <div id="classify-tmdb-list" class="space-y-3 max-h-[min(60vh,640px)] overflow-y-auto custom-scrollbar pr-1"></div>
  </div>
</section>
<?php endif; ?>
<?php if (empty($items)): ?><p class="text-on-surface-variant"><?= putmio_e(putmio_lang('classify_empty')) ?></p><?php else: ?>
<div class="space-y-4">
<?php foreach ($items as $item): ?>
<?php
  $isSeriesGroup = empty($item['putio_file_id']) && (int) ($item['episode_count'] ?? 0) > 0;
  $fileLabel = $item['file_name'] ?? null;
  if ($fileLabel === null && $isSeriesGroup) {
      $fileLabel = putmio_lang('classify_series_episodes', ['count' => (string) (int) $item['episode_count']]);
  }
  $suggestedTitle = $item['title'];
  if ($fileLabel && $suggestedTitle === $fileLabel) {
      $guessed = putmio_guess_title_from_filename((string) $fileLabel);
      if ($guessed) {
          $suggestedTitle = $guessed;
      }
  } elseif ($isSeriesGroup && $suggestedTitle === $item['title']) {
      $guessed = putmio_guess_title_from_filename((string) $item['title']);
      if ($guessed) {
          $suggestedTitle = $guessed;
      }
  }
?>
<form method="post" action="<?= putmio_e($appUrl) ?>/admin/classificazione" class="rounded-xl border border-outline-variant/30 bg-surface-container-high p-4 grid md:grid-cols-4 gap-3 items-end" data-classify-media-id="<?= (int) $item['id'] ?>"><?= Csrf::field() ?>
  <input type="hidden" name="media_id" value="<?= (int)$item['id'] ?>">
  <div class="md:col-span-2">
    <?php if (!empty($item['shared_by_username'])): ?>
    <span class="inline-flex items-center gap-1 rounded-full bg-primary/15 border border-primary/30 px-2 py-0.5 text-[10px] font-label-md text-primary mb-1.5">
      <?= putmio_e(putmio_lang('classify_shared_from', ['user' => (string) $item['shared_by_username']])) ?>
    </span>
    <?php endif; ?>
    <?php if ($fileLabel): ?>
    <p class="text-xs text-on-surface-variant mb-1 font-mono truncate"><?= putmio_e((string) $fileLabel) ?></p>
    <?php endif; ?>
    <input name="title" value="<?= putmio_e($suggestedTitle) ?>" class="pm-input text-sm">
  </div>
  <select name="media_type" class="pm-input text-sm">
    <?php foreach (['film','serie','animazione','altro'] as $t): ?>
    <option value="<?= $t ?>" <?= $item['media_type'] === $t ? 'selected' : '' ?>><?= putmio_lang($t) ?></option>
    <?php endforeach; ?>
  </select>
  <div class="flex gap-2">
    <input type="hidden" name="classification_status" value="classified">
    <button type="submit" class="pm-btn-primary text-sm flex-1 justify-center py-2.5"><?= putmio_e(putmio_lang('save')) ?></button>
  </div>
</form>
<?php endforeach; ?>
</div>
<?php endif; ?>
