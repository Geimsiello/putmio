<?php use PutMio\Auth\Csrf; use PutMio\Config; $appUrl = rtrim(Config::get('app.url'), '/'); ?>
<h1 class="text-2xl font-bold mb-6"><?= putmio_lang('classify') ?></h1>
<?php if (empty($items)): ?><p class="text-slate-500">Nessun file da classificare.</p><?php else: ?>
<div class="space-y-4">
<?php foreach ($items as $item): ?>
<form method="post" action="<?= putmio_e($appUrl) ?>/admin/classificazione" class="rounded-xl border border-slate-200 dark:border-slate-800 p-4 grid md:grid-cols-4 gap-3 items-end"><?= Csrf::field() ?>
  <input type="hidden" name="media_id" value="<?= (int)$item['id'] ?>">
  <div class="md:col-span-2">
    <p class="text-xs text-slate-500 mb-1"><?= putmio_e($item['file_name']) ?></p>
    <input name="title" value="<?= putmio_e($item['title']) ?>" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm">
  </div>
  <select name="media_type" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm">
    <?php foreach (['film','serie','animazione','altro'] as $t): ?>
    <option value="<?= $t ?>" <?= $item['media_type'] === $t ? 'selected' : '' ?>><?= putmio_lang($t) ?></option>
    <?php endforeach; ?>
  </select>
  <div class="flex gap-2">
    <input type="hidden" name="classification_status" value="classified">
    <button class="bg-indigo-600 text-white rounded-lg px-4 py-2 text-sm flex-1">Salva</button>
  </div>
</form>
<?php endforeach; ?>
</div>
<?php endif; ?>
