<?php
/** @var array $media */
/** @var array|null $progress */
/** @var array $genres */
/** @var bool $isSeries */
/** @var array<int, list<array<string, mixed>>> $episodesBySeason */
/** @var array<int, array<string, mixed>> $episodeProgress */
/** @var \PutMio\CatalogService $catalog */
/** @var string $catalogReturnUrl */
use PutMio\Config;
$appUrl = rtrim(Config::get('app.url'), '/');
$poster = $catalog->posterWebPath($media['poster_local_path'] ?? null, $media['poster_url'] ?? null);
$hasProgress = $progress && empty($progress['completed']) && ($progress['position_sec'] ?? 0) > 0;
$isWatched = $progress && !empty($progress['completed']);
$canRestart = $progress && (($progress['position_sec'] ?? 0) > 0 || !empty($progress['completed']));
$typeLabel = putmio_lang((string) ($media['media_type'] ?? 'altro'));
$runtimeSec = (int) ($media['duration_sec'] ?? 0);
if ($runtimeSec <= 0 && !empty($progress['duration_sec'])) {
    $runtimeSec = (int) $progress['duration_sec'];
}
$runtimeLabel = putmio_format_runtime_label($runtimeSec > 0 ? $runtimeSec : null);
$mediaId = (int) $media['id'];
$episodeCount = $isSeries ? $catalog->countSeriesEpisodes($mediaId) : 0;
?>
<a href="<?= putmio_e($catalogReturnUrl) ?>" class="inline-flex items-center gap-2 text-on-surface-variant hover:text-primary transition-colors font-label-md text-label-md mb-8 group">
  <span class="material-symbols-outlined text-lg group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
  <?= putmio_lang('back_to_catalog') ?>
</a>

<div class="grid lg:grid-cols-[280px_1fr] gap-8 lg:gap-12">
  <div class="mx-auto w-full max-w-[280px] lg:max-w-none">
    <div class="aspect-[2/3] rounded-xl overflow-hidden shadow-2xl border border-outline-variant/30 bg-surface-container">
      <img src="<?= putmio_e($poster) ?>" alt="" class="w-full h-full object-cover">
    </div>
  </div>

  <div class="flex flex-col min-w-0">
    <h1 class="text-headline-lg font-headline-lg text-on-surface mb-2"><?= putmio_e($media['title']) ?></h1>
    <p class="text-on-surface-variant font-label-md text-label-md mb-5">
      <?= putmio_e($typeLabel) ?><?= !empty($media['year']) ? ' · ' . (int) $media['year'] : '' ?><?= $episodeCount > 0 ? ' · ' . $episodeCount . ' ' . putmio_lang('episodes') : '' ?>
    </p>

    <?php if (!empty($genres)): ?>
    <div class="flex flex-wrap gap-2 mb-6">
      <?php foreach ($genres as $genre): ?>
      <span class="text-on-surface-variant font-label-md text-label-md bg-surface-variant/40 px-3 py-1 rounded-full"><?= putmio_e($genre) ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($media['synopsis'])): ?>
    <p class="text-on-surface-variant font-body-md leading-relaxed mb-8"><?= nl2br(putmio_e($media['synopsis'])) ?></p>
    <?php endif; ?>

    <div class="grid grid-cols-3 gap-4 sm:gap-8 border-t border-outline-variant/20 pt-6 mb-10">
      <div>
        <span class="block font-label-sm text-outline uppercase tracking-widest text-[10px] mb-1"><?= putmio_lang('duration') ?></span>
        <span class="font-body-md text-on-surface"><?= putmio_e($runtimeLabel ?? '—') ?></span>
      </div>
      <div>
        <span class="block font-label-sm text-outline uppercase tracking-widest text-[10px] mb-1"><?= putmio_lang('year') ?></span>
        <span class="font-body-md text-on-surface"><?= !empty($media['year']) ? (int) $media['year'] : '—' ?></span>
      </div>
      <div>
        <span class="block font-label-sm text-outline uppercase tracking-widest text-[10px] mb-1"><?= putmio_lang('type') ?></span>
        <span class="font-body-md text-on-surface"><?= putmio_e($typeLabel) ?></span>
      </div>
    </div>

    <?php if (\PutMio\Auth\Session::isAdmin()): ?>
    <div class="flex flex-wrap gap-3 items-center mb-8">
      <?php
      $fileName = (string) ($media['file_name'] ?? $media['title'] ?? '');
      $tmdbSuggestedQuery = (string) ($media['title'] ?? putmio_guess_title_from_filename($fileName) ?? '');
      $tmdbAutoOpen = false;
      $tmdbShowTrigger = true;
      $tmdbCatalogMode = false;
      $tmdbTriggerLabel = 'Riassegna metadati TMDB';
      require putmio_base_path() . '/templates/partials/tmdb-link-modal.php';
      ?>
    </div>
    <?php endif; ?>

    <?php if ($isSeries && $episodesBySeason !== []): ?>
      <?php require putmio_base_path() . '/templates/partials/series-episodes.php'; ?>
    <?php else: ?>
    <div class="mt-auto space-y-3 max-w-xl" data-media-actions="<?= $mediaId ?>">
      <div class="flex flex-col sm:flex-row gap-3">
        <a href="<?= putmio_e($appUrl) ?>/play?id=<?= $mediaId ?>" class="pm-btn-primary flex-1 justify-center px-6 py-3 text-base shadow-lg shadow-primary/20">
          <span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">play_circle</span>
          <?= $hasProgress ? putmio_lang('resume') : putmio_lang('play') ?>
        </a>
        <?php if (!$isWatched): ?>
        <button
          type="button"
          data-pm-watch-action="complete"
          class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl border border-outline-variant/50 text-on-surface font-label-md text-label-md hover:bg-surface-variant/30 transition-all active:scale-95"
        >
          <span class="material-symbols-outlined text-[20px]">check_circle</span>
          <?= putmio_lang('mark_watched') ?>
        </button>
        <?php endif; ?>
      </div>
      <?php if ($canRestart): ?>
      <button
        type="button"
        data-pm-watch-action="reset"
        class="w-full inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl border border-outline-variant/40 text-on-surface-variant font-label-md text-label-md hover:bg-surface-variant/20 hover:text-on-surface transition-all active:scale-95"
      >
        <span class="material-symbols-outlined text-[20px]">restart_alt</span>
        <?= putmio_lang('restart_from_beginning') ?>
      </button>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!$isSeries || $episodesBySeason === []): ?>
<script>
(function () {
  const root = document.querySelector('[data-media-actions="<?= $mediaId ?>"]');
  if (!root || !window.PUTMIO) return;

  root.addEventListener('click', async function (evt) {
    const btn = evt.target.closest('[data-pm-watch-action]');
    if (!btn) return;
    const action = btn.getAttribute('data-pm-watch-action');
    const body = new URLSearchParams({
      _csrf: window.PUTMIO.csrf,
      media_id: '<?= $mediaId ?>',
      action: action
    });
    btn.disabled = true;
    try {
      await fetch(window.PUTMIO.baseUrl + '/api/watch-progress', { method: 'POST', body });
      if (action === 'reset') {
        window.location.href = window.PUTMIO.baseUrl + '/play?id=<?= $mediaId ?>';
      } else {
        location.reload();
      }
    } catch (e) {
      btn.disabled = false;
    }
  });
})();
</script>
<?php endif; ?>

<?php if (\PutMio\Auth\Session::isAdmin()): ?>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>[x-cloak]{display:none!important}</style>
<?php endif; ?>
