<?php
/** @var list<array<string, mixed>> $items */
/** @var PutMio\CatalogService $catalog */
/** @var list<int>|null $watchlistIds */

$watchlistIds = $watchlistIds ?? [];
$mediaFromQuery = isset($catalogReturnPath) && $catalogReturnPath !== ''
    ? '&from=' . rawurlencode($catalogReturnPath)
    : '';

foreach ($items as $item):
    $poster = $catalog->posterWebPath($item['poster_local_path'] ?? null, $item['poster_url'] ?? null);
    $isUnlinked = !putmio_media_is_linked($item);
    $openTmdbFromCatalog = $isUnlinked && \PutMio\Auth\Session::isAdmin();
    $fileName = (string) ($item['file_name'] ?? $item['title'] ?? '');
    $tmdbSuggestedQuery = putmio_guess_title_from_filename($fileName) ?? (string) ($item['title'] ?? '');
    $bookmarkMediaId = (int) $item['id'];
    $mediaUrl = putmio_e($appUrl) . '/media?id=' . $bookmarkMediaId . $mediaFromQuery;
?>
<div class="group flex flex-col gap-3 poster-hover">
  <div class="relative">
    <a
      href="<?= $mediaUrl ?>"
      class="block"
      <?php if ($openTmdbFromCatalog): ?>
      data-catalog-tmdb-link="<?= $bookmarkMediaId ?>"
      data-catalog-tmdb-query="<?= putmio_e($tmdbSuggestedQuery) ?>"
      data-catalog-tmdb-file="<?= putmio_e($fileName) ?>"
      <?php endif; ?>
    >
      <div class="aspect-[2/3] relative overflow-hidden rounded-xl bg-surface-container-high shadow-lg transition-all duration-300 group-hover:scale-[1.03] group-hover:shadow-primary/10">
        <img src="<?= putmio_e($poster) ?>" alt="" class="w-full h-full object-cover" loading="lazy">
        <?php require putmio_base_path() . '/templates/partials/poster-owner-badge.php'; ?>
        <div class="poster-overlay absolute inset-0 bg-gradient-to-t from-background via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end p-4">
          <span class="material-symbols-outlined text-primary text-4xl" style="font-variation-settings: 'FILL' 1;">play_circle</span>
        </div>
      </div>
    </a>
    <?php require putmio_base_path() . '/templates/partials/poster-bookmark-btn.php'; ?>
  </div>
  <a href="<?= $mediaUrl ?>" class="min-w-0 block">
    <h3 class="text-body-lg font-bold text-on-surface truncate group-hover:text-primary transition-colors"><?= putmio_e($item['title']) ?></h3>
    <p class="font-label-sm text-label-sm text-on-surface-variant truncate"><?= putmio_e(putmio_catalog_subtitle($item)) ?></p>
  </a>
</div>
<?php endforeach; ?>
