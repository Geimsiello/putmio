<?php
/** @var int $subtitleModalMediaId */
/** @var bool $subtitleModalAutoOpen */
use PutMio\Config;

$appUrl = rtrim(Config::get('app.url'), '/');
$subtitleModalAutoOpen = $subtitleModalAutoOpen ?? false;
?>
<div
  id="subtitle-modal-root"
  class="fixed inset-0 z-[120] hidden items-center justify-center p-4"
  role="dialog"
  aria-modal="true"
  aria-labelledby="subtitle-modal-title"
>
  <div class="absolute inset-0 bg-[#161616]/80 backdrop-blur-md" data-pm-subtitle-close></div>
  <div class="relative w-full max-w-[800px] max-h-[85vh] flex flex-col rounded-xl border border-outline-variant/30 bg-surface-container shadow-2xl overflow-hidden">
    <header class="flex items-start justify-between gap-4 p-5 border-b border-outline-variant/20 shrink-0">
      <div>
        <h2 id="subtitle-modal-title" class="font-headline-md text-headline-md text-on-surface"><?= putmio_e(putmio_lang('subtitles_title')) ?></h2>
        <p class="font-label-sm text-label-sm text-on-surface-variant mt-1" id="subtitle-modal-subtitle"></p>
      </div>
      <button type="button" class="p-2 rounded-lg text-on-surface-variant hover:text-on-surface hover:bg-surface-variant/30 transition-colors" data-pm-subtitle-close aria-label="<?= putmio_e(putmio_lang('cancel')) ?>">
        <span class="material-symbols-outlined">close</span>
      </button>
    </header>

    <div id="subtitle-modal-notice" class="hidden mx-5 mt-4 rounded-lg border border-warning/40 bg-warning/10 px-4 py-3 text-label-sm font-label-sm text-on-surface-variant"></div>

    <div class="flex-1 overflow-y-auto p-5 space-y-6">
      <section>
        <h3 class="font-label-md text-label-md text-on-surface mb-3"><?= putmio_e(putmio_lang('subtitles_available')) ?></h3>
        <ul id="subtitle-cached-list" class="space-y-2"></ul>
        <p id="subtitle-cached-empty" class="hidden text-label-sm font-label-sm text-on-surface-variant"><?= putmio_e(putmio_lang('subtitles_count_none')) ?></p>
      </section>

      <section>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
          <h3 class="font-label-md text-label-md text-on-surface"><?= putmio_e(putmio_lang('subtitles_search')) ?></h3>
          <button type="button" id="subtitle-search-btn" class="pm-btn-primary px-4 py-2 text-label-sm">
            <span class="material-symbols-outlined text-[18px]">search</span>
            <?= putmio_e(putmio_lang('subtitles_search')) ?>
          </button>
        </div>
        <p id="subtitle-search-status" class="hidden text-label-sm font-label-sm text-on-surface-variant mb-3"></p>
        <ul id="subtitle-search-list" class="space-y-2"></ul>
      </section>
    </div>

    <footer class="shrink-0 p-4 border-t border-outline-variant/20 text-center">
      <p class="text-[11px] text-outline"><?= putmio_e(putmio_lang('subtitles_attribution')) ?></p>
    </footer>
  </div>
</div>
