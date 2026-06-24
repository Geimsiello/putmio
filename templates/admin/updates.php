<?php
use PutMio\Auth\Csrf;
use PutMio\Config;

/** @var array<string, mixed> $status */
/** @var list<string> $protectedPaths */
/** @var list<string> $updatablePaths */

$appUrl = rtrim(Config::get('app.url'), '/');
$adminCrumbLabel = putmio_lang('admin_updates');
$adminPageTitle = putmio_lang('admin_updates_title');
$adminPageDescription = putmio_lang('admin_updates_desc');
require putmio_base_path() . '/templates/partials/admin-header.php';

$installed = (string) ($status['installed_version'] ?? putmio_version());
$latest = is_array($status['latest'] ?? null) ? $status['latest'] : null;
$updateAvailable = !empty($status['update_available']);
$canApply = !empty($status['can_apply']);
$blockers = is_array($status['apply_blockers'] ?? null) ? $status['apply_blockers'] : [];
$checkError = $status['check_error'] ?? null;
$configured = !empty($status['configured']);
$repository = (string) ($status['repository'] ?? '');
?>
<?php if (!empty($success)): ?>
<div class="mb-6 p-4 rounded-xl bg-primary/10 border border-primary/30 text-primary text-body-md" role="status"><?= putmio_e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
<div class="mb-6 p-4 rounded-xl bg-error/10 border border-error/30 text-error text-body-md" role="alert"><?= putmio_e($error) ?></div>
<?php endif; ?>

<section class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
  <div class="glass-panel rounded-2xl p-6">
    <div class="flex items-start gap-4 mb-4">
      <div class="p-3 bg-primary/10 text-primary rounded-xl">
        <span class="material-symbols-outlined">deployed_code</span>
      </div>
      <div class="min-w-0">
        <h2 class="text-headline-md font-headline-md text-on-surface"><?= putmio_e(putmio_lang('admin_updates_installed')) ?></h2>
        <p class="text-display-sm font-display-sm text-primary mt-2"><?= putmio_e($installed) ?></p>
      </div>
    </div>
    <p class="text-on-surface-variant text-body-md"><?= putmio_e(putmio_lang('admin_updates_installed_hint')) ?></p>
  </div>

  <div class="glass-panel rounded-2xl p-6">
    <div class="flex items-start gap-4 mb-4">
      <div class="p-3 rounded-xl <?= $updateAvailable ? 'bg-tertiary/10 text-tertiary' : 'bg-surface-container-high text-on-surface-variant' ?>">
        <span class="material-symbols-outlined"><?= $updateAvailable ? 'system_update_alt' : 'cloud_done' ?></span>
      </div>
      <div class="min-w-0">
        <h2 class="text-headline-md font-headline-md text-on-surface"><?= putmio_e(putmio_lang('admin_updates_remote')) ?></h2>
        <?php if ($latest): ?>
        <p class="text-display-sm font-display-sm <?= $updateAvailable ? 'text-tertiary' : 'text-on-surface' ?> mt-2"><?= putmio_e((string) $latest['version']) ?></p>
        <?php elseif ($checkError === 'repository_not_configured'): ?>
        <p class="text-body-md text-on-surface-variant mt-2"><?= putmio_e(putmio_lang('admin_updates_not_configured')) ?></p>
        <?php else: ?>
        <p class="text-body-md text-on-surface-variant mt-2"><?= putmio_e(putmio_lang('admin_updates_fetch_failed')) ?></p>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($configured): ?>
    <p class="text-on-surface-variant text-body-sm font-mono truncate"><?= putmio_e($repository) ?></p>
    <?php endif; ?>
    <?php if ($latest && !empty($latest['html_url'])): ?>
    <a href="<?= putmio_e((string) $latest['html_url']) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 mt-3 text-primary text-body-sm hover:underline">
      <?= putmio_e(putmio_lang('admin_updates_view_release')) ?>
      <span class="material-symbols-outlined text-[16px]">open_in_new</span>
    </a>
    <?php endif; ?>
  </div>
</section>

<?php if ($latest && trim((string) ($latest['body'] ?? '')) !== ''): ?>
<section class="glass-panel rounded-2xl p-6 mb-8">
  <h2 class="text-headline-md font-headline-md text-on-surface mb-3"><?= putmio_e(putmio_lang('admin_updates_changelog')) ?></h2>
  <div class="text-on-surface-variant text-body-md whitespace-pre-wrap leading-relaxed max-h-64 overflow-y-auto custom-scrollbar"><?= putmio_e((string) $latest['body']) ?></div>
</section>
<?php endif; ?>

<section class="glass-panel rounded-2xl p-6 mb-8">
  <h2 class="text-headline-md font-headline-md text-on-surface mb-2"><?= putmio_e(putmio_lang('admin_updates_apply_title')) ?></h2>
  <p class="text-on-surface-variant text-body-md mb-6"><?= putmio_e(putmio_lang('admin_updates_apply_desc')) ?></p>

  <?php if ($blockers !== []): ?>
  <ul class="mb-6 space-y-2 text-body-sm text-on-surface-variant">
    <?php foreach ($blockers as $blocker):
      $blockerKey = 'admin_update_blocker_' . $blocker;
      $blockerMsg = putmio_lang($blockerKey);
    ?>
    <li class="flex items-start gap-2">
      <span class="material-symbols-outlined text-error text-[18px] shrink-0">warning</span>
      <span><?= putmio_e($blockerMsg !== $blockerKey ? $blockerMsg : $blocker) ?></span>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <?php if ($updateAvailable): ?>
  <form method="post" action="<?= putmio_e($appUrl) ?>/admin/aggiornamenti/applica" onsubmit="return confirm(<?= json_encode(putmio_lang('admin_updates_confirm'), JSON_UNESCAPED_UNICODE) ?>);"><?= Csrf::field() ?>
    <button type="submit" class="pm-btn-primary" <?= $canApply ? '' : 'disabled' ?>>
      <span class="material-symbols-outlined text-[18px]">download</span>
      <?= putmio_e(putmio_lang('admin_updates_apply_btn', ['version' => (string) ($latest['version'] ?? '')])) ?>
    </button>
  </form>
  <?php elseif ($latest && !$updateAvailable): ?>
  <p class="text-primary text-body-md flex items-center gap-2">
    <span class="material-symbols-outlined">check_circle</span>
    <?= putmio_e(putmio_lang('admin_updates_up_to_date')) ?>
  </p>
  <?php endif; ?>
</section>

<details class="glass-panel rounded-2xl p-6">
  <summary class="cursor-pointer text-headline-md font-headline-md text-on-surface list-none flex items-center justify-between gap-4">
    <?= putmio_e(putmio_lang('admin_updates_scope_title')) ?>
    <span class="material-symbols-outlined text-on-surface-variant">expand_more</span>
  </summary>
  <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
      <h3 class="font-label-md text-label-md text-error mb-3"><?= putmio_e(putmio_lang('admin_updates_protected')) ?></h3>
      <ul class="space-y-1 text-body-sm font-mono text-on-surface-variant">
        <?php foreach ($protectedPaths as $path): ?>
        <li><?= putmio_e($path) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h3 class="font-label-md text-label-md text-primary mb-3"><?= putmio_e(putmio_lang('admin_updates_included')) ?></h3>
      <ul class="space-y-1 text-body-sm font-mono text-on-surface-variant">
        <?php foreach ($updatablePaths as $path): ?>
        <li><?= putmio_e($path) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</details>
