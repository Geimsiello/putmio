<?php
use PutMio\Config;

/** @var list<array<string, mixed>> $items */
/** @var PutMio\CatalogService $catalog */
/** @var list<int> $watchlistIds */

$appUrl = rtrim(Config::get('app.url'), '/');
$complete = (float) Config::get('app.stream_complete_ratio', 0.90);
$min = (float) Config::get('app.stream_min_progress_ratio', 0.05);
?>
<h1 class="text-2xl font-bold mb-6"><?= putmio_lang('watchlist') ?></h1>
<?php if (empty($items)): ?>
<p class="text-on-surface-variant"><?= putmio_lang('watchlist_empty') ?></p>
<?php else: ?>
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
<?php foreach ($items as $item): ?>
  <?php
    $poster = $catalog->posterWebPath($item['poster_local_path'] ?? null, $item['poster_url'] ?? null);
    $bookmarkMediaId = (int) $item['id'];
    $positionSec = (int) ($item['position_sec'] ?? 0);
    $durationSec = (int) ($item['duration_sec'] ?? 0);
    $hasProgress = $positionSec > 0
        && $durationSec > 0
        && ($positionSec / $durationSec) >= $min
        && ($positionSec / $durationSec) < $complete;
    $pct = $hasProgress && $durationSec > 0 ? round(100 * $positionSec / $durationSec) : 0;
    $mediaUrl = putmio_e($appUrl) . '/media?id=' . $bookmarkMediaId;
  ?>
  <div class="group">
    <div class="relative">
      <a href="<?= $mediaUrl ?>" class="block">
        <div class="relative aspect-[2/3] rounded-xl overflow-hidden bg-surface-container shadow-md poster-card<?= $hasProgress ? ' poster-card--with-progress' : '' ?> group-hover:scale-105 transition-transform">
          <img src="<?= putmio_e($poster) ?>" alt="" class="w-full h-full object-cover" loading="lazy">
          <?php require putmio_base_path() . '/templates/partials/poster-owner-badge.php'; ?>
          <?php if ($hasProgress): ?>
          <div class="absolute bottom-0 left-0 right-0 h-1.5 bg-surface-container-highest">
            <div class="h-full bg-primary" style="width:<?= $pct ?>%"></div>
          </div>
          <?php endif; ?>
        </div>
        <p class="mt-2 text-sm font-medium truncate"><?= putmio_e($item['title']) ?></p>
        <p class="text-xs text-on-surface-variant truncate"><?= putmio_e(putmio_catalog_subtitle($item)) ?></p>
      </a>
      <?php require putmio_base_path() . '/templates/partials/poster-bookmark-btn.php'; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
