<?php use PutMio\Auth\Csrf; use PutMio\Config; $appUrl = rtrim(Config::get('app.url'), '/');
$adminCrumbLabel = 'Utenti';
$adminPageTitle = 'Utenti';
$adminPageDescription = 'Gestisci account famiglia e inviti di registrazione.';
require putmio_base_path() . '/templates/partials/admin-header.php';
?>
<?php if (!empty($success)): ?>
<div class="mb-6 p-4 rounded-xl bg-success/10 border border-success/30 text-body-md text-on-surface">
  <?= putmio_e($success) ?>
</div>
<?php endif; ?>
<?php if (!empty($error)): ?>
<div class="mb-6 p-4 rounded-xl bg-error/10 border border-error/30 text-body-md text-on-surface">
  <?= putmio_e($error) ?>
</div>
<?php endif; ?>
<?php if (!empty($inviteLink)): ?>
<div class="mb-6 p-4 rounded-xl bg-warning/10 border border-warning/30 text-body-md break-all">
  <?= putmio_e(putmio_lang('invite_link_fallback')) ?>: <strong class="text-on-surface"><?= putmio_e($inviteLink) ?></strong>
</div>
<?php endif; ?>
<form method="post" action="<?= putmio_e($appUrl) ?>/admin/inviti" class="flex flex-col sm:flex-row gap-2 mb-8 max-w-lg"><?= Csrf::field() ?>
  <input type="email" name="email" placeholder="email@famiglia.it" required class="pm-input flex-1">
  <button class="pm-btn-primary shrink-0">Crea invito</button>
</form>
<div class="glass-panel rounded-2xl overflow-hidden">
<table class="w-full text-sm">
  <thead>
    <tr class="text-left text-on-surface-variant/70 font-label-md text-label-md border-b border-surface-variant/20">
      <th class="px-4 md:px-6 py-3">Nome</th>
      <th class="px-4 md:px-6 py-3">Email</th>
      <th class="px-4 md:px-6 py-3">Ruolo</th>
      <th class="px-4 md:px-6 py-3">Ultimo accesso</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($users as $u): ?>
  <tr class="border-b border-surface-variant/10 hover:bg-surface-variant/10 transition-colors">
    <td class="px-4 md:px-6 py-3 text-on-surface"><?= putmio_e($u['display_name']) ?></td>
    <td class="px-4 md:px-6 py-3 text-on-surface-variant"><?= putmio_e($u['email']) ?></td>
    <td class="px-4 md:px-6 py-3 text-on-surface-variant"><?= putmio_e($u['role']) ?></td>
    <td class="px-4 md:px-6 py-3 text-on-surface-variant"><?= putmio_e($u['last_login_at'] ?? '—') ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
