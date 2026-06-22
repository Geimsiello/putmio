<?php
/** @var array $media */
/** @var array|null $progress */
/** @var string $tmdbSuggestedQuery */
/** @var bool $isSeries */
/** @var array<int, list<array<string, mixed>>> $episodesBySeason */
/** @var array<int, array<string, mixed>> $episodeProgress */
/** @var \PutMio\CatalogService $catalog */
/** @var string $catalogReturnUrl */
use PutMio\Config;
$appUrl = rtrim(Config::get('app.url'), '/');
$poster = $catalog->posterWebPath($media['poster_local_path'] ?? null, $media['poster_url'] ?? null);
$hasProgress = $progress && empty($progress['completed']) && ($progress['position_sec'] ?? 0) > 0;
$fileName = (string) ($media['file_name'] ?? $media['title'] ?? '');
$tmdbAutoOpen = false;
$mediaId = (int) $media['id'];
$typeLabel = putmio_lang((string) ($media['media_type'] ?? 'altro'));
$episodeCount = $isSeries ? $catalog->countSeriesEpisodes($mediaId) : 0;
?>
<a href="<?= putmio_e($catalogReturnUrl) ?>" class="inline-flex items-center gap-2 text-on-surface-variant hover:text-primary transition-colors font-label-md text-label-md mb-8 group">
  <span class="material-symbols-outlined text-lg group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
  <?= putmio_lang('back_to_catalog') ?>
</a>

<div class="grid md:grid-cols-[220px_1fr] gap-8">
  <img src="<?= putmio_e($poster) ?>" alt="" class="w-full rounded-xl aspect-[2/3] object-cover bg-surface-container mx-auto max-w-[220px] md:max-w-none">
  <div>
    <h1 class="text-headline-lg font-headline-lg text-on-surface mb-2"><?= putmio_e($media['title']) ?></h1>
    <?php if ($fileName !== '' && !$isSeries): ?>
    <p class="font-label-sm text-label-sm text-on-surface-variant/80 mb-1 truncate" title="<?= putmio_e($fileName) ?>"><?= putmio_e($fileName) ?></p>
    <?php endif; ?>
    <p class="text-on-surface-variant font-label-md text-label-md mb-6">
      <?= putmio_e($typeLabel) ?><?= $media['year'] ? ' · ' . (int)$media['year'] : '' ?><?= $episodeCount > 0 ? ' · ' . $episodeCount . ' ' . putmio_lang('episodes') : '' ?>
    </p>

    <?php if (!$isSeries): ?>
    <div class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 mb-6 flex items-start gap-3">
      <span class="material-symbols-outlined text-warning shrink-0" style="font-variation-settings: 'FILL' 1;">info</span>
      <p class="text-body-md text-warning/90">Collega questo file ai metadati TMDB per completare titolo, poster e descrizione.</p>
    </div>

    <div class="flex flex-wrap gap-3 items-center mb-8">
      <a href="<?= putmio_e($appUrl) ?>/play?id=<?= $mediaId ?>" class="pm-btn-primary px-5 py-2.5">
        <?= $hasProgress ? putmio_lang('resume') : putmio_lang('play') ?>
      </a>
      <?php if (\PutMio\Auth\Session::isAdmin()): ?>
        <?php
        $tmdbShowTrigger = true;
        $tmdbCatalogMode = false;
        require putmio_base_path() . '/templates/partials/tmdb-link-modal.php';
        ?>
      <?php endif; ?>
    </div>
    <?php elseif (\PutMio\Auth\Session::isAdmin()): ?>
    <div class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 mb-6 flex items-start gap-3">
      <span class="material-symbols-outlined text-warning shrink-0" style="font-variation-settings: 'FILL' 1;">info</span>
      <p class="text-body-md text-warning/90">Collega la serie ai metadati TMDB per completare titolo, poster e descrizione.</p>
    </div>
    <div class="flex flex-wrap gap-3 items-center mb-8">
      <?php
      $tmdbShowTrigger = true;
      $tmdbCatalogMode = false;
      require putmio_base_path() . '/templates/partials/tmdb-link-modal.php';
      ?>
    </div>
    <?php endif; ?>

    <?php if ($isSeries && $episodesBySeason !== []): ?>
      <?php require putmio_base_path() . '/templates/partials/series-episodes.php'; ?>
    <?php endif; ?>
  </div>
</div>

<?php if (\PutMio\Auth\Session::isAdmin()): ?>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>[x-cloak]{display:none!important}</style>
<?php endif; ?>
