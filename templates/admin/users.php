<?php use PutMio\Auth\Csrf; use PutMio\Config; $appUrl = rtrim(Config::get('app.url'), '/'); ?>
<h1 class="text-2xl font-bold mb-6">Utenti</h1>
<?php if (!empty($inviteLink)): ?>
<div class="mb-4 p-4 rounded-xl bg-emerald-900/30 border border-emerald-700 text-sm break-all">Link invito: <strong><?= putmio_e($inviteLink) ?></strong></div>
<?php endif; ?>
<form method="post" action="<?= putmio_e($appUrl) ?>/admin/inviti" class="flex gap-2 mb-8 max-w-lg"><?= Csrf::field() ?>
  <input type="email" name="email" placeholder="email@famiglia.it" required class="flex-1 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm">
  <button class="bg-indigo-600 text-white rounded-lg px-4 py-2 text-sm">Crea invito</button>
</form>
<table class="w-full text-sm">
  <thead><tr class="text-left text-slate-500 border-b border-slate-800"><th class="py-2">Nome</th><th>Email</th><th>Ruolo</th><th>Ultimo accesso</th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
  <tr class="border-b border-slate-800/50"><td class="py-2"><?= putmio_e($u['display_name']) ?></td><td><?= putmio_e($u['email']) ?></td><td><?= putmio_e($u['role']) ?></td><td><?= putmio_e($u['last_login_at'] ?? '—') ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
