<?php
/** @var array<string, mixed> $item */
/** @var PutMio\CatalogService $catalog */
$poster = $catalog->posterWebPath($item['poster_local_path'] ?? null, $item['poster_url'] ?? null);
$type = putmio_resolve_media_type($item) ?? 'altro';
$typeLabel = putmio_lang($type);
[$badgeBg, $badgeText] = putmio_media_badge_classes($type);
$mediaId = (int) $item['id'];
$cardTitle = (string) $item['title'];
$cardSubtitle = putmio_catalog_subtitle($item);
$tvAttrs = putmio_tv_card_attrs([
    'id' => $mediaId,
    'title' => $cardTitle,
    'subtitle' => $cardSubtitle,
    'synopsis' => (string) ($item['synopsis'] ?? ''),
]);
$href = putmio_tv_mode()
    ? putmio_media_url($mediaId)
    : $appUrl . '/media?id=' . $mediaId;
$posterWidthClass = putmio_tv_mode() ? 'w-44 sm:w-48' : 'w-36 sm:w-44';
?>
<a href="<?= putmio_e($href) ?>" class="flex-shrink-0 <?= $posterWidthClass ?> snap-start group block" <?= $tvAttrs ?>>
  <div class="relative aspect-[2/3] rounded-xl overflow-hidden bg-surface-container shadow-md group-hover:scale-105 group-hover:shadow-xl transition-all duration-300 poster-card">
    <img src="<?= putmio_e($poster) ?>" alt="" class="w-full h-full object-cover" loading="lazy" draggable="false">
    <?php require putmio_base_path() . '/templates/partials/poster-owner-badge.php'; ?>
    <?php if ($type !== 'altro'): ?>
    <div class="absolute top-2 right-2 pm-tv-poster-badge">
      <span class="<?= putmio_e($badgeBg) ?> px-2 py-1 rounded text-[10px] font-bold <?= putmio_e($badgeText) ?> uppercase tracking-wider"><?= putmio_e($typeLabel) ?></span>
    </div>
    <?php endif; ?>
    <div class="pm-tv-play-overlay absolute inset-0 flex items-center justify-center bg-background/40 transition-opacity">
      <span class="material-symbols-outlined text-white text-5xl" style="font-variation-settings: 'FILL' 1;">play_circle</span>
    </div>
  </div>
  <div class="mt-3 pm-tv-card-caption">
    <h3 class="text-body-md font-bold text-on-surface truncate"><?= putmio_e($cardTitle) ?></h3>
    <p class="text-label-sm font-label-sm text-on-surface-variant truncate"><?= putmio_e($cardSubtitle) ?></p>
  </div>
</a>
