<?php
/** @var list<array<string, mixed>> $previewItems */
/** @var \PutMio\CatalogService $catalog */
?>
<div class="tv-home">
  <?php if (!empty($previewItems)): ?>
  <section class="tv-row" data-tv-zone="row">
    <h2 class="tv-row__title"><?= putmio_e(putmio_lang('continue_watching')) ?></h2>
    <div class="tv-row__viewport">
      <div class="tv-row__track" data-tv-row-track>
        <?php foreach ($previewItems as $item):
          $poster = $catalog->posterWebPath($item['poster_local_path'] ?? null, $item['poster_url'] ?? null);
          $pct = ($item['duration_sec'] ?? 0) > 0 ? round(100 * $item['position_sec'] / $item['duration_sec']) : 0;
          $displayTitle = !empty($item['series_title']) ? (string) $item['series_title'] : (string) $item['title'];
          $episodeLabel = !empty($item['series_title']) ? (string) $item['title'] : null;
          $meta = $episodeLabel
            ? $episodeLabel . ' · ' . putmio_lang('resume') . ' · ' . $pct . '%'
            : putmio_lang('resume') . ' · ' . $pct . '%';
        ?>
        <a
          href="<?= putmio_e(putmio_tv_url('/play?id=' . (int) $item['id'])) ?>"
          class="tv-poster"
          data-tv-focus
          tabindex="0"
          data-tv-title="<?= putmio_e($displayTitle) ?>"
          data-tv-meta="<?= putmio_e($meta) ?>"
          data-tv-synopsis=""
        >
          <div class="tv-poster__image-wrap">
            <img src="<?= putmio_e($poster) ?>" alt="" class="tv-poster__image" loading="lazy" draggable="false">
            <?php if ($pct > 0): ?>
            <div class="tv-poster__progress" aria-hidden="true">
              <div class="tv-poster__progress-bar" style="width:<?= (int) $pct ?>%"></div>
            </div>
            <?php endif; ?>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php else: ?>
  <section class="tv-home__welcome">
    <h2 class="tv-home__welcome-title"><?= putmio_e(putmio_lang('home')) ?></h2>
    <p class="tv-home__welcome-text"><?= putmio_e(putmio_lang('no_media')) ?></p>
  </section>
  <?php endif; ?>
</div>
