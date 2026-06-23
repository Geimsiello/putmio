<?php use PutMio\Config; $appUrl = rtrim(Config::get('app.url'), '/'); $tvMode = putmio_tv_mode(); ?>
<h1 class="text-2xl font-bold mb-6"><?= putmio_lang('in_progress') ?></h1>
<?php if (empty($items)): ?>
<p class="text-slate-500">Nessun titolo in corso.</p>
<?php else: ?>
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4"<?= $tvMode ? ' data-catalog-grid' : '' ?>>
<?php foreach ($items as $item): ?>
  <?php
    $poster = $catalog->posterWebPath($item['poster_local_path'] ?? null, $item['poster_url'] ?? null);
    $pct = ($item['duration_sec'] ?? 0) > 0 ? round(100 * $item['position_sec'] / $item['duration_sec']) : 0;
    $remaining = max(0, ($item['duration_sec'] ?? 0) - ($item['position_sec'] ?? 0));
    $displayTitle = !empty($item['series_title']) ? (string) $item['series_title'] : (string) $item['title'];
    $episodeLabel = !empty($item['series_title']) ? (string) $item['title'] : null;
    $subtitle = $episodeLabel
        ? $episodeLabel . ' · ' . putmio_lang('resume') . ' · ' . putmio_format_duration($remaining)
        : putmio_lang('resume') . ' · ' . putmio_format_duration($remaining);
    $tvAttrs = putmio_tv_card_attrs([
        'id' => (int) $item['id'],
        'title' => $displayTitle,
        'subtitle' => $subtitle,
    ]);
  ?>
  <div class="group">
    <a href="<?= putmio_e(putmio_play_url((int) $item['id'])) ?>" class="block" <?= $tvAttrs ?>>
      <div class="relative aspect-[2/3] rounded-xl overflow-hidden bg-slate-800 poster-card--with-progress">
        <img src="<?= putmio_e($poster) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition" loading="lazy">
        <?php require putmio_base_path() . '/templates/partials/poster-owner-badge.php'; ?>
        <div class="absolute bottom-0 left-0 right-0 h-1.5 bg-slate-700"><div class="h-full bg-indigo-500" style="width:<?= $pct ?>%"></div></div>
        <div class="pm-tv-play-overlay absolute inset-0 flex items-center justify-center bg-background/40 opacity-0 group-hover:opacity-100 transition-opacity">
          <span class="material-symbols-outlined text-white text-5xl" style="font-variation-settings: 'FILL' 1;">play_circle</span>
        </div>
      </div>
      <p class="mt-2 text-sm font-medium truncate pm-tv-card-caption"><?= putmio_e($displayTitle) ?></p>
      <p class="text-xs text-slate-500 pm-tv-card-caption"><?= putmio_e($subtitle) ?></p>
    </a>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
