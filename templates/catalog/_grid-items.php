<?php
/** @var list<array<string, mixed>> $items */
/** @var PutMio\CatalogService $catalog */
$mediaFromQuery = isset($catalogReturnPath) && $catalogReturnPath !== ''
    ? '&from=' . rawurlencode($catalogReturnPath)
    : '';

foreach ($items as $item):
    $poster = $catalog->posterWebPath($item['poster_local_path'] ?? null, $item['poster_url'] ?? null);
    $isUnlinked = !putmio_media_is_linked($item);
    $openTmdbFromCatalog = $isUnlinked && putmio_admin_ui_enabled();
    $fileName = (string) ($item['file_name'] ?? $item['title'] ?? '');
    $tmdbSuggestedQuery = putmio_guess_title_from_filename($fileName) ?? (string) ($item['title'] ?? '');
    $mediaId = (int) $item['id'];
    $cardTitle = (string) $item['title'];
    $cardSubtitle = putmio_catalog_subtitle($item);
    $tvAttrs = putmio_tv_card_attrs([
        'id' => $mediaId,
        'title' => $cardTitle,
        'subtitle' => $cardSubtitle,
        'synopsis' => (string) ($item['synopsis'] ?? ''),
    ]);
    $mediaHref = putmio_media_url($mediaId) . $mediaFromQuery;
?>
<a
  href="<?= putmio_e($mediaHref) ?>"
  class="group flex flex-col gap-3 poster-hover"
  <?= $tvAttrs ?>
  <?php if ($openTmdbFromCatalog): ?>
  data-catalog-tmdb-link="<?= $mediaId ?>"
  data-catalog-tmdb-query="<?= putmio_e($tmdbSuggestedQuery) ?>"
  data-catalog-tmdb-file="<?= putmio_e($fileName) ?>"
  <?php endif; ?>
>
  <div class="aspect-[2/3] relative overflow-hidden rounded-xl bg-surface-container-high shadow-lg transition-all duration-300 group-hover:scale-[1.03] group-hover:shadow-primary/10">
    <img src="<?= putmio_e($poster) ?>" alt="" class="w-full h-full object-cover" loading="lazy">
    <?php require putmio_base_path() . '/templates/partials/poster-owner-badge.php'; ?>
    <div class="pm-tv-play-overlay poster-overlay absolute inset-0 bg-gradient-to-t from-background via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end p-4">
      <span class="material-symbols-outlined text-primary text-4xl" style="font-variation-settings: 'FILL' 1;">play_circle</span>
    </div>
  </div>
  <div class="min-w-0 pm-tv-card-caption">
    <h3 class="text-body-lg font-bold text-on-surface truncate group-hover:text-primary transition-colors"><?= putmio_e($cardTitle) ?></h3>
    <p class="font-label-sm text-label-sm text-on-surface-variant truncate"><?= putmio_e($cardSubtitle) ?></p>
  </div>
</a>
<?php endforeach; ?>
