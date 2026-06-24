<?php
use PutMio\Auth\Csrf;

/** @var list<array<string, mixed>> $devices */
/** @var int|null $currentDeviceId */
/** @var string $revokeAction */
/** @var string|null $success */
/** @var string|null $error */
$success = $success ?? null;
$error = $error ?? null;
?>
<?php if ($success): ?>
<div class="mb-6 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-body-md text-success" role="status">
  <?= putmio_e($success) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="mb-6 rounded-lg border border-error/30 bg-error/10 px-4 py-3 text-body-md text-error" role="alert">
  <?= putmio_e($error) ?>
</div>
<?php endif; ?>

<?php if (empty($devices)): ?>
<div class="rounded-xl border border-outline-variant/30 bg-surface-container p-6 md:p-8 text-center">
  <span class="material-symbols-outlined text-4xl text-on-surface-variant/40 mb-3" aria-hidden="true">devices_off</span>
  <p class="text-body-md text-on-surface-variant"><?= putmio_e(putmio_lang('account_devices_empty')) ?></p>
</div>
<?php else: ?>
<div class="space-y-3">
  <?php foreach ($devices as $device):
    $deviceId = (int) $device['id'];
    $label = (string) ($device['label'] ?? putmio_lang('device_unknown'));
    $isCurrent = $currentDeviceId !== null && $deviceId === $currentDeviceId;
    $icon = putmio_device_icon_for_label($label);
  ?>
  <article class="rounded-xl border border-outline-variant/30 bg-surface-container p-4 md:p-5 flex flex-col sm:flex-row sm:items-center gap-4 <?= $isCurrent ? 'border-primary/40 ring-1 ring-primary/20' : '' ?>">
    <div class="flex items-start gap-4 min-w-0 flex-1">
      <div class="w-11 h-11 shrink-0 rounded-xl bg-surface-container-high border border-outline-variant/20 flex items-center justify-center">
        <span class="material-symbols-outlined text-primary text-[22px]" aria-hidden="true"><?= putmio_e($icon) ?></span>
      </div>
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2 mb-1">
          <h2 class="text-body-md font-bold text-on-surface"><?= putmio_e($label) ?></h2>
          <?php if ($isCurrent): ?>
          <span class="inline-flex items-center rounded-full bg-primary/15 border border-primary/30 px-2.5 py-0.5 text-label-sm font-label-sm text-primary">
            <?= putmio_e(putmio_lang('account_device_this')) ?>
          </span>
          <?php endif; ?>
        </div>
        <dl class="text-label-sm font-label-sm text-on-surface-variant space-y-0.5">
          <?php if (!empty($device['client_ip'])): ?>
          <div><dt class="inline"><?= putmio_e(putmio_lang('account_device_ip')) ?>:</dt> <dd class="inline"><?= putmio_e((string) $device['client_ip']) ?></dd></div>
          <?php endif; ?>
          <div><dt class="inline"><?= putmio_e(putmio_lang('account_device_last_used')) ?>:</dt> <dd class="inline"><?= putmio_e(putmio_format_admin_datetime($device['last_used_at'] ?? $device['created_at'] ?? null)) ?></dd></div>
          <div><dt class="inline"><?= putmio_e(putmio_lang('account_device_expires')) ?>:</dt> <dd class="inline"><?= putmio_e(putmio_format_admin_datetime($device['expires_at'] ?? null)) ?></dd></div>
        </dl>
      </div>
    </div>
    <form
      method="post"
      action="<?= putmio_e($revokeAction) ?>"
      class="shrink-0"
      onsubmit="return confirm(<?= json_encode(putmio_lang('account_device_revoke_confirm'), JSON_UNESCAPED_UNICODE) ?>);"
    >
      <?= Csrf::field() ?>
      <input type="hidden" name="device_id" value="<?= (int) $deviceId ?>">
      <button
        type="submit"
        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-outline-variant/40 text-on-surface-variant hover:text-error hover:border-error/40 hover:bg-error/10 transition-colors text-label-sm font-label-sm"
        title="<?= putmio_e(putmio_lang('account_device_revoke')) ?>"
      >
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
        <span><?= putmio_e(putmio_lang('account_device_revoke')) ?></span>
      </button>
    </form>
  </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>
