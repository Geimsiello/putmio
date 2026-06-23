<?php
use PutMio\Config;

$appUrl = rtrim(Config::get('app.url'), '/');
?>
<?php if (!$putioConnected): ?>
<section class="mt-2 md:mt-6" id="putio-banner">
  <div class="bg-warning/10 border border-warning/30 rounded-xl px-4 md:px-6 py-4 flex items-center justify-between group hover:bg-warning/15 transition-all">
    <div class="flex items-center gap-3 min-w-0">
      <span class="material-symbols-outlined text-warning shrink-0" style="font-variation-settings: 'FILL' 1;">warning</span>
      <p class="text-body-md font-body-md text-warning truncate">
        <?= putmio_lang('connect_putio') ?> —
        <a class="font-bold underline hover:opacity-90" href="<?= putmio_e($appUrl) ?>/admin/impostazioni"><?= putmio_lang('settings') ?></a>
      </p>
    </div>
    <button type="button" id="putio-banner-dismiss" class="text-warning opacity-50 group-hover:opacity-100 transition-opacity shrink-0 ml-2" aria-label="<?= putmio_lang('cancel') ?>">
      <span class="material-symbols-outlined">close</span>
    </button>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($inProgress)): ?>
<section class="mt-8 md:mt-10">
  <div class="flex justify-between items-end mb-6 gap-4">
    <h2 class="text-headline-lg font-headline-lg text-on-surface"><?= putmio_lang('continue_watching') ?></h2>
    <a class="text-primary font-label-md text-label-md hover:underline flex items-center gap-1 shrink-0" href="<?= putmio_e($appUrl) ?>/in-corso">
      <?= putmio_lang('view_all') ?> <span class="material-symbols-outlined text-sm">chevron_right</span>
    </a>
  </div>
  <?php ob_start(); ?>
    <?php foreach ($inProgress as $item): ?>
      <?php
        $poster = $catalog->posterWebPath($item['poster_local_path'] ?? null, $item['poster_url'] ?? null);
        $pct = ($item['duration_sec'] ?? 0) > 0 ? round(100 * $item['position_sec'] / $item['duration_sec']) : 0;
        $displayTitle = !empty($item['series_title']) ? (string) $item['series_title'] : (string) $item['title'];
        $episodeLabel = !empty($item['series_title']) ? (string) $item['title'] : null;
      ?>
      <a href="<?= putmio_e($appUrl) ?>/play?id=<?= (int)$item['id'] ?>" class="flex-shrink-0 w-36 sm:w-44 snap-start group block">
        <div class="relative aspect-[2/3] rounded-xl overflow-hidden bg-surface-container shadow-lg group-hover:scale-105 group-hover:shadow-primary/20 transition-all duration-300 poster-card poster-card--with-progress">
          <img src="<?= putmio_e($poster) ?>" alt="" class="w-full h-full object-cover" loading="lazy" draggable="false">
          <?php require putmio_base_path() . '/templates/partials/poster-owner-badge.php'; ?>
          <div class="absolute bottom-0 left-0 w-full p-3 bg-gradient-to-t from-background/90 to-transparent">
            <div class="w-full bg-surface-container-highest rounded-full h-1">
              <div class="bg-primary h-1 rounded-full" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
          <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-background/40">
            <span class="material-symbols-outlined text-white text-5xl" style="font-variation-settings: 'FILL' 1;">play_circle</span>
          </div>
        </div>
        <div class="mt-3">
          <h3 class="text-body-md font-bold text-on-surface truncate"><?= putmio_e($displayTitle) ?></h3>
          <p class="text-label-sm font-label-sm text-on-surface-variant">
            <?php if ($episodeLabel): ?>
              <?= putmio_e($episodeLabel) ?> · <?= putmio_lang('resume') ?> · <?= $pct ?>%
            <?php else: ?>
              <?= putmio_lang('resume') ?> · <?= $pct ?>%
            <?php endif; ?>
          </p>
        </div>
      </a>
    <?php endforeach; ?>
  <?php
    $sliderInner = ob_get_clean();
    $sliderId = 'home-continue-watching';
    require putmio_base_path() . '/templates/partials/pm-slider.php';
  ?>
</section>
<?php endif; ?>

<section class="<?= !empty($inProgress) ? 'mt-10 md:mt-12' : 'mt-8 md:mt-10' ?>">
  <div class="flex justify-between items-end mb-6 md:mb-8 gap-4">
    <h2 class="text-headline-lg font-headline-lg text-on-surface"><?= putmio_lang('recently_added') ?></h2>
    <a href="<?= putmio_e($appUrl) ?>/catalogo" class="text-primary font-label-md text-label-md hover:underline flex items-center gap-1 shrink-0">
      <?= putmio_lang('view_all') ?> <span class="material-symbols-outlined text-sm">chevron_right</span>
    </a>
  </div>
  <?php if (empty($recent)): ?>
    <p class="text-on-surface-variant"><?= putmio_lang('no_media') ?></p>
  <?php else: ?>
  <?php ob_start(); ?>
    <?php foreach ($recent as $item): ?>
      <?php require putmio_base_path() . '/templates/partials/home-catalog-poster.php'; ?>
    <?php endforeach; ?>
  <?php
    $sliderInner = ob_get_clean();
    $sliderId = 'home-recently-added';
    require putmio_base_path() . '/templates/partials/pm-slider.php';
  ?>
  <?php endif; ?>
</section>

<?php foreach ($genreRows as $genreRow): ?>
<section class="mt-10 md:mt-12">
  <div class="flex justify-between items-end mb-6 gap-4">
    <h2 class="text-headline-lg font-headline-lg text-on-surface"><?= putmio_e($genreRow['name']) ?></h2>
    <a href="<?= putmio_e($appUrl) ?>/catalogo?genre=<?= (int) $genreRow['id'] ?>" class="text-primary font-label-md text-label-md hover:underline flex items-center gap-1 shrink-0">
      <?= putmio_lang('view_all') ?> <span class="material-symbols-outlined text-sm">chevron_right</span>
    </a>
  </div>
  <?php ob_start(); ?>
    <?php foreach ($genreRow['items'] as $item): ?>
      <?php require putmio_base_path() . '/templates/partials/home-catalog-poster.php'; ?>
    <?php endforeach; ?>
  <?php
    $sliderInner = ob_get_clean();
    $sliderId = 'home-genre-' . (int) $genreRow['id'];
    require putmio_base_path() . '/templates/partials/pm-slider.php';
  ?>
</section>
<?php endforeach; ?>
