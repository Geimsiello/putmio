<?php use PutMio\Config; $appUrl = rtrim(Config::get('app.url'), '/'); ?>
<h1 class="text-2xl font-bold mb-6">Catalogo</h1>
<form method="get" class="flex flex-wrap gap-2 mb-6">
  <input name="q" value="<?= putmio_e($filters['q'] ?? '') ?>" placeholder="<?= putmio_lang('search') ?>..." class="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
  <select name="type" class="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
    <option value=""><?= putmio_lang('all') ?></option>
    <?php foreach (['film','serie','animazione','altro'] as $t): ?>
    <option value="<?= $t ?>" <?= ($filters['type'] ?? '') === $t ? 'selected' : '' ?>><?= putmio_lang($t) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="bg-indigo-600 text-white rounded-lg px-4 py-2 text-sm"><?= putmio_lang('search') ?></button>
</form>
<?php if (empty($items)): ?>
<p class="text-slate-500"><?= putmio_lang('no_media') ?></p>
<?php else: ?>
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
<?php foreach ($items as $item): ?>
  <?php $poster = $catalog->posterWebPath($item['poster_local_path'] ?? null, $item['poster_url'] ?? null); ?>
  <a href="<?= putmio_e($appUrl) ?>/media?id=<?= (int)$item['id'] ?>" class="group block">
    <div class="aspect-[2/3] rounded-xl overflow-hidden bg-slate-800">
      <img src="<?= putmio_e($poster) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition" loading="lazy">
    </div>
    <p class="mt-2 text-sm font-medium truncate"><?= putmio_e($item['title']) ?></p>
    <p class="text-xs text-slate-500"><?= putmio_e($item['media_type']) ?></p>
  </a>
<?php endforeach; ?>
</div>
<?php endif; ?>
