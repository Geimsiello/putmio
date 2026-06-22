<?php
use PutMio\Auth\Csrf;
use PutMio\Config;
use PutMio\PutIO\Client;
$appUrl = rtrim(Config::get('app.url'), '/');
$authUrl = (new Client())->authorizeUrl();
?>
<h1 class="text-2xl font-bold mb-6"><?= putmio_lang('settings') ?></h1>
<?php if (!empty($success)): ?><div class="mb-4 text-emerald-500 text-sm"><?= putmio_e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="mb-4 text-red-500 text-sm"><?= putmio_e($error) ?></div><?php endif; ?>

<section class="mb-8 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
  <h2 class="font-semibold mb-3">Integrazione put.io</h2>
  <?php if ($putioConnected): ?>
    <p class="text-sm text-emerald-500 mb-2">Connesso come <?= putmio_e($putioUser) ?></p>
    <p class="text-sm text-slate-500 mb-4">Ultima sync: <?= putmio_e($lastSync ?? '—') ?> (<?= (int)$lastSyncCount ?> elementi)</p>
    <form method="post" action="<?= putmio_e($appUrl) ?>/admin/sync" class="inline"><?= Csrf::field() ?>
      <button class="bg-indigo-600 text-white rounded-lg px-4 py-2 text-sm mr-2"><?= putmio_lang('sync_now') ?></button>
    </form>
    <form method="post" action="<?= putmio_e($appUrl) ?>/admin/disconnect-putio" class="inline"><?= Csrf::field() ?>
      <button class="border border-red-500 text-red-400 rounded-lg px-4 py-2 text-sm">Disconnetti</button>
    </form>
  <?php else: ?>
    <p class="text-sm text-slate-500 mb-4">Configura client_id e secret, salva, poi collega l'account.</p>
  <?php endif; ?>
</section>

<form method="post" action="<?= putmio_e($appUrl) ?>/admin/impostazioni" class="space-y-6 max-w-xl"><?= Csrf::field() ?>
  <fieldset class="space-y-3">
    <legend class="font-semibold">put.io OAuth</legend>
    <input name="putio_client_id" value="<?= putmio_e($putioClientId) ?>" placeholder="client_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
    <input name="putio_client_secret" type="password" placeholder="client_secret" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
    <?php if (!$putioConnected && $putioClientId): ?>
    <a href="<?= putmio_e($authUrl) ?>" class="inline-block bg-indigo-600 text-white rounded-lg px-4 py-2 text-sm">Collega account put.io</a>
    <?php endif; ?>
  </fieldset>
  <fieldset class="space-y-3">
    <legend class="font-semibold">TMDB</legend>
    <input name="tmdb_api_key" value="<?= putmio_e($tmdbKey) ?>" placeholder="API key TMDB" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
  </fieldset>
  <fieldset class="space-y-3">
    <legend class="font-semibold">Cron sync (pannello OVH)</legend>
    <code class="block text-xs bg-slate-900 p-3 rounded-lg break-all"><?= putmio_e($appUrl) ?>/cron/sync?token=<?= putmio_e($cronToken) ?></code>
  </fieldset>
  <button type="submit" class="bg-indigo-600 text-white rounded-lg px-5 py-2"><?= putmio_lang('save') ?></button>
</form>
