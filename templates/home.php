<?php
use PutMio\Config;
$appUrl = rtrim(Config::get('app.url'), '/');
?>
<?php if (!$putioConnected): ?>
<div class="mb-6 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-amber-200 text-sm">
  <?= putmio_lang('connect_putio') ?> — <a class="underline" href="<?= putmio_e($appUrl) ?>/admin/impostazioni">Impostazioni</a>
</div>
<?php endif; ?>

<?php if (!empty($inProgress)): ?>
<section class="mb-10">
  <h2 class="text-xl font-semibold mb-4"><?= putmio_lang('continue_watching') ?></h2>
  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
    <?php foreach ($inProgress as $item): ?>
      <?php $poster = $catalog->posterWebPath($item['poster_local_path'] ?? null, $item['poster_url'] ?? null);
        $pct = ($item['duration_sec'] ?? 0) > 0 ? round(100 * $item['position_sec'] / $item['duration_sec']) : 0; ?>
      <a href="<?= putmio_e($appUrl) ?>/play?id=<?= (int)$item['id'] ?>" class="group block">
        <div class="relative aspect-[2/3] rounded-xl overflow-hidden bg-slate-800">
          <img src="<?= putmio_e($poster) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition" loading="lazy">
          <div class="absolute bottom-0 left-0 right-0 h-1 bg-slate-700"><div class="h-full bg-indigo-500" style="width:<?= $pct ?>%"></div></div>
        </div>
        <p class="mt-2 text-sm font-medium truncate"><?= putmio_e($item['title']) ?></p>
        <p class="text-xs text-slate-500"><?= putmio_lang('resume') ?> · <?= $pct ?>%</p>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section>
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-xl font-semibold"><?= putmio_lang('recently_added') ?></h2>
    <a href="<?= putmio_e($appUrl) ?>/catalogo" class="text-sm text-indigo-500">Vedi tutto</a>
  </div>
  <?php if (empty($recent)): ?>
    <p class="text-slate-500"><?= putmio_lang('no_media') ?></p>
  <?php else: ?>
  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
    <?php foreach ($recent as $item): ?>
      <?php $poster = $catalog->posterWebPath($item['poster_local_path'] ?? null, $item['poster_url'] ?? null); ?>
      <a href="<?= putmio_e($appUrl) ?>/media?id=<?= (int)$item['id'] ?>" class="group block">
        <div class="aspect-[2/3] rounded-xl overflow-hidden bg-slate-800">
          <img src="<?= putmio_e($poster) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition" loading="lazy">
        </div>
        <p class="mt-2 text-sm font-medium truncate"><?= putmio_e($item['title']) ?></p>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
