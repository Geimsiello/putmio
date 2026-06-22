<?php use PutMio\Auth\Csrf; use PutMio\Config; $appUrl = rtrim(Config::get('app.url'), '/'); ?>
<h1 class="text-2xl font-bold mb-6"><?= putmio_lang('admin') ?></h1>
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
  <a href="<?= putmio_e($appUrl) ?>/admin/impostazioni" class="rounded-xl border border-slate-200 dark:border-slate-800 p-5 hover:border-indigo-500 transition">
    <h2 class="font-semibold"><?= putmio_lang('settings') ?></h2>
    <p class="text-sm text-slate-500 mt-1">put.io, TMDB, SMTP</p>
  </a>
  <a href="<?= putmio_e($appUrl) ?>/admin/classificazione" class="rounded-xl border border-slate-200 dark:border-slate-800 p-5 hover:border-indigo-500 transition">
    <h2 class="font-semibold"><?= putmio_lang('classify') ?></h2>
    <p class="text-sm text-slate-500 mt-1"><?= (int)$unclassified ?> da classificare</p>
  </a>
  <a href="<?= putmio_e($appUrl) ?>/admin/streaming" class="rounded-xl border border-slate-200 dark:border-slate-800 p-5 hover:border-indigo-500 transition">
    <h2 class="font-semibold">Streaming</h2>
    <p class="text-sm text-slate-500 mt-1">Banda e sessioni attive</p>
  </a>
  <a href="<?= putmio_e($appUrl) ?>/admin/utenti" class="rounded-xl border border-slate-200 dark:border-slate-800 p-5 hover:border-indigo-500 transition">
    <h2 class="font-semibold">Utenti</h2>
    <p class="text-sm text-slate-500 mt-1">Inviti famiglia</p>
  </a>
</div>
