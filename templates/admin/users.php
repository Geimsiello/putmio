<?php use PutMio\Auth\Csrf; use PutMio\Config; $appUrl = rtrim(Config::get('app.url'), '/');
$adminCrumbLabel = putmio_lang('admin_users');
$adminPageTitle = putmio_lang('admin_users');
$adminPageDescription = putmio_lang('admin_users_page_desc');
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
  <input type="email" name="email" placeholder="<?= putmio_e(putmio_lang('admin_invite_email_placeholder')) ?>" required class="pm-input flex-1">
  <button class="pm-btn-primary shrink-0"><?= putmio_e(putmio_lang('admin_create_invite')) ?></button>
</form>
<div class="glass-panel rounded-2xl overflow-hidden">
<table class="w-full text-sm">
  <thead>
    <tr class="text-left text-on-surface-variant/70 font-label-md text-label-md border-b border-surface-variant/20">
      <th class="px-4 md:px-6 py-3"><?= putmio_e(putmio_lang('admin_col_name')) ?></th>
      <th class="px-4 md:px-6 py-3"><?= putmio_e(putmio_lang('email')) ?></th>
      <th class="px-4 md:px-6 py-3"><?= putmio_e(putmio_lang('admin_col_role')) ?></th>
      <th class="px-4 md:px-6 py-3"><?= putmio_e(putmio_lang('admin_col_last_login')) ?></th>
      <th class="px-4 md:px-6 py-3 text-right"><?= putmio_e(putmio_lang('admin_col_actions')) ?></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($users as $u):
    $isSelf = (int) $u['id'] === (int) ($currentUserId ?? 0);
    $deleteConfirm = putmio_lang('admin_delete_user_confirm', ['name' => (string) $u['display_name']]);
  ?>
  <tr class="border-b border-surface-variant/10 hover:bg-surface-variant/10 transition-colors">
    <td class="px-4 md:px-6 py-3 text-on-surface"><?= putmio_e($u['display_name']) ?></td>
    <td class="px-4 md:px-6 py-3 text-on-surface-variant"><?= putmio_e($u['email']) ?></td>
    <td class="px-4 md:px-6 py-3 text-on-surface-variant"><?= putmio_e(putmio_user_role_label((string) $u['role'])) ?></td>
    <td class="px-4 md:px-6 py-3 text-on-surface-variant"><?= putmio_e($u['last_login_at'] ?? '—') ?></td>
    <td class="px-4 md:px-6 py-3 text-right">
      <?php if (!$isSelf): ?>
      <form
        method="post"
        action="<?= putmio_e($appUrl) ?>/admin/utenti/elimina"
        class="inline"
        onsubmit="return confirm(<?= json_encode($deleteConfirm, JSON_UNESCAPED_UNICODE) ?>);"
      >
        <?= Csrf::field() ?>
        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
        <button
          type="submit"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-outline-variant/40 text-on-surface-variant hover:text-error hover:border-error/40 hover:bg-error/10 transition-colors text-label-sm font-label-sm"
          title="<?= putmio_e(putmio_lang('admin_delete_user')) ?>"
        >
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
          <span class="hidden sm:inline"><?= putmio_e(putmio_lang('admin_delete_user')) ?></span>
        </button>
      </form>
      <?php else: ?>
      <span class="text-on-surface-variant/40 text-label-sm">—</span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
