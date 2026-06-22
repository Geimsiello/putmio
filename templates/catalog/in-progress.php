<?php use PutMio\Config; $appUrl = rtrim(Config::get('app.url'), '/'); ?>
<h1 class="text-2xl font-bold mb-6"><?= putmio_lang('in_progress') ?></h1>
<?php if (empty($items)): ?>
<p class="text-slate-500">Nessun titolo in corso.</p>
<?php else: ?>
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
<?php foreach ($items as $item): ?>
  <?php
    $poster = $catalog->posterWebPath($item['poster_local_path'] ?? null, $item['poster_url'] ?? null);
    $pct = ($item['duration_sec'] ?? 0) > 0 ? round(100 * $item['position_sec'] / $item['duration_sec']) : 0;
    $remaining = max(0, ($item['duration_sec'] ?? 0) - ($item['position_sec'] ?? 0));
  ?>
  <div class="group">
    <a href="<?= putmio_e($appUrl) ?>/play?id=<?= (int)$item['id'] ?>" class="block">
      <div class="relative aspect-[2/3] rounded-xl overflow-hidden bg-slate-800">
        <img src="<?= putmio_e($poster) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition" loading="lazy">
        <div class="absolute bottom-0 left-0 right-0 h-1.5 bg-slate-700"><div class="h-full bg-indigo-500" style="width:<?= $pct ?>%"></div></div>
      </div>
      <p class="mt-2 text-sm font-medium truncate"><?= putmio_e($item['title']) ?></p>
      <p class="text-xs text-slate-500">Ancora <?= putmio_format_duration($remaining) ?></p>
    </a>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
