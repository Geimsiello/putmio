<?php
/** @var array<string, mixed> $item */
/** @var PutMio\CatalogService $catalog */
/** @var list<int>|null $watchlistIds */

$poster = $catalog->posterWebPath($item['poster_local_path'] ?? null, $item['poster_url'] ?? null);
$type = putmio_resolve_media_type($item) ?? 'altro';
$typeLabel = putmio_lang($type);
[$badgeBg, $badgeText] = putmio_media_badge_classes($type);
$bookmarkMediaId = (int) $item['id'];
$watchlistIds = $watchlistIds ?? [];
$mediaUrl = putmio_e($appUrl) . '/media?id=' . $bookmarkMediaId;
?>
<div class="flex-shrink-0 w-36 sm:w-44 snap-start">
  <div class="relative group">
    <a href="<?= $mediaUrl ?>" class="block">
      <div class="relative aspect-[2/3] rounded-xl overflow-hidden bg-surface-container shadow-md group-hover:scale-105 group-hover:shadow-xl transition-all duration-300 poster-card">
        <img src="<?= putmio_e($poster) ?>" alt="" class="w-full h-full object-cover" loading="lazy" draggable="false">
        <?php require putmio_base_path() . '/templates/partials/poster-owner-badge.php'; ?>
        <?php if ($type !== 'altro'): ?>
        <div class="absolute top-2 right-2 z-[1]">
          <span class="<?= putmio_e($badgeBg) ?> px-2 py-1 rounded text-[10px] font-bold <?= putmio_e($badgeText) ?> uppercase tracking-wider"><?= putmio_e($typeLabel) ?></span>
        </div>
        <?php endif; ?>
      </div>
      <div class="mt-3">
        <h3 class="text-body-md font-bold text-on-surface truncate"><?= putmio_e($item['title']) ?></h3>
        <p class="text-label-sm font-label-sm text-on-surface-variant truncate"><?= putmio_e(putmio_catalog_subtitle($item)) ?></p>
      </div>
    </a>
    <?php require putmio_base_path() . '/templates/partials/poster-bookmark-btn.php'; ?>
  </div>
</div>
