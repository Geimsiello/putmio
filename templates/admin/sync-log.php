<?php
use PutMio\Config;

/** @var list<array<string, mixed>> $runs */
/** @var array<int, array<string, list<array<string, mixed>>>> $itemsByRun */

$appUrl = rtrim(Config::get('app.url'), '/');
$adminCrumbLabel = putmio_lang('admin_sync_log');
$adminPageTitle = putmio_lang('admin_sync_log_title');
$adminPageDescription = putmio_lang('admin_sync_log_desc');
require putmio_base_path() . '/templates/partials/admin-header.php';

$triggerLabel = static function (string $trigger): string {
    $key = 'admin_sync_trigger_' . $trigger;
    $label = putmio_lang($key);
    return $label !== $key ? $label : $trigger;
};

$statusLabel = static function (string $status): string {
    $key = 'admin_sync_status_' . $status;
    $label = putmio_lang($key);
    return $label !== $key ? $label : $status;
};

$statusClass = static function (string $status): string {
    if ($status === 'success') {
        return 'bg-success/15 border-success/30 text-success';
    }
    if ($status === 'error') {
        return 'bg-error/15 border-error/30 text-error';
    }
    return 'bg-warning/15 border-warning/30 text-warning';
};

$renderItems = static function (string $action, array $items): void {
    $titleKey = 'admin_sync_items_' . $action;
    $title = putmio_lang($titleKey, ['count' => (string) count($items)]);
    if ($title === $titleKey) {
        $title = $action;
    }
    $tone = $action === 'removed' ? 'text-error' : 'text-primary';
    ?>
    <section class="rounded-xl border border-outline-variant/20 bg-surface-container-high/50 overflow-hidden">
      <div class="px-4 py-3 border-b border-outline-variant/20 flex items-center justify-between gap-3">
        <h3 class="text-label-lg font-label-lg <?= $tone ?>"><?= putmio_e($title) ?></h3>
        <span class="text-label-sm font-label-sm text-on-surface-variant"><?= count($items) ?></span>
      </div>
      <?php if ($items === []): ?>
      <p class="px-4 py-4 text-body-sm text-on-surface-variant"><?= putmio_e(putmio_lang('admin_sync_no_items_for_action')) ?></p>
      <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[420px] text-left">
          <thead>
            <tr class="text-label-sm font-label-sm text-on-surface-variant/70 border-b border-outline-variant/10">
              <th class="px-4 py-3 font-medium"><?= putmio_e(putmio_lang('admin_sync_col_content')) ?></th>
              <th class="px-4 py-3 font-medium"><?= putmio_e(putmio_lang('admin_sync_col_owner')) ?></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-outline-variant/10">
            <?php foreach ($items as $item): ?>
            <?php
              $isShared = !empty($item['is_shared']);
              $owner = (string) ($item['owner_username'] ?? '');
              $ownerLabel = $isShared && $owner !== ''
                  ? putmio_lang('classify_shared_from', ['user' => $owner])
                  : putmio_lang('admin_sync_owner_own');
            ?>
            <tr class="hover:bg-surface-variant/10">
              <td class="px-4 py-3 text-body-sm text-on-surface"><?= putmio_e((string) ($item['name'] ?? '')) ?></td>
              <td class="px-4 py-3 text-body-sm text-on-surface-variant"><?= putmio_e($ownerLabel) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>
    <?php
};
?>

<?php if (empty($runs)): ?>
<section class="glass-panel rounded-2xl p-8 text-center">
  <div class="mx-auto mb-4 w-14 h-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center">
    <span class="material-symbols-outlined text-[28px]">sync_saved_locally</span>
  </div>
  <h2 class="text-headline-md font-headline-md text-on-surface mb-2"><?= putmio_e(putmio_lang('admin_sync_log_empty_title')) ?></h2>
  <p class="text-body-md text-on-surface-variant max-w-xl mx-auto"><?= putmio_e(putmio_lang('admin_sync_log_empty_desc')) ?></p>
  <a href="<?= putmio_e($appUrl) ?>/admin/impostazioni" class="pm-btn-primary inline-flex mt-6">
    <span class="material-symbols-outlined text-[18px]">settings</span>
    <?= putmio_e(putmio_lang('settings')) ?>
  </a>
</section>
<?php else: ?>
<div class="space-y-4">
  <?php foreach ($runs as $run): ?>
  <?php
    $runId = (int) ($run['id'] ?? 0);
    $items = $itemsByRun[$runId] ?? ['added' => [], 'removed' => [], 'updated' => []];
    $added = (int) ($run['count_added'] ?? count($items['added'] ?? []));
    $removed = (int) ($run['count_removed'] ?? count($items['removed'] ?? []));
    $updated = (int) ($run['count_updated'] ?? count($items['updated'] ?? []));
    $status = (string) ($run['status'] ?? 'running');
    $trigger = (string) ($run['trigger_source'] ?? 'unknown');
    $triggeredBy = trim((string) ($run['triggered_by_name'] ?? ''));
    if ($triggeredBy === '') {
        $triggeredBy = trim((string) ($run['triggered_by_email'] ?? ''));
    }
  ?>
  <details class="glass-panel rounded-2xl border border-outline-variant/20 overflow-hidden">
    <summary class="cursor-pointer list-none px-5 py-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2 mb-2">
          <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-label-sm font-label-sm <?= putmio_e($statusClass($status)) ?>">
            <span class="material-symbols-outlined text-[16px]"><?= $status === 'success' ? 'check_circle' : ($status === 'error' ? 'error' : 'pending') ?></span>
            <?= putmio_e($statusLabel($status)) ?>
          </span>
          <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-container-high border border-outline-variant/30 px-2.5 py-1 text-label-sm font-label-sm text-on-surface-variant">
            <span class="material-symbols-outlined text-[16px]">bolt</span>
            <?= putmio_e($triggerLabel($trigger)) ?>
          </span>
          <?php if (!empty($run['putio_username'])): ?>
          <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 border border-primary/20 px-2.5 py-1 text-label-sm font-label-sm text-primary">
            <span class="material-symbols-outlined text-[16px]">cloud</span>
            <?= putmio_e((string) $run['putio_username']) ?>
          </span>
          <?php endif; ?>
        </div>
        <h2 class="text-headline-sm font-headline-sm text-on-surface">
          <?= putmio_e(putmio_format_admin_datetime((string) ($run['started_at'] ?? ''))) ?>
        </h2>
        <p class="text-body-sm text-on-surface-variant mt-1">
          <?= putmio_e(putmio_lang('admin_sync_run_meta', [
              'finished' => putmio_format_admin_datetime($run['finished_at'] ?? null),
              'user' => $triggeredBy !== '' ? $triggeredBy : putmio_lang('admin_sync_trigger_system'),
          ])) ?>
        </p>
      </div>
      <div class="flex items-center gap-3 shrink-0">
        <div class="text-center rounded-xl bg-primary/10 border border-primary/20 px-4 py-2">
          <p class="text-headline-sm font-headline-sm text-primary"><?= $added ?></p>
          <p class="text-label-sm font-label-sm text-primary/80"><?= putmio_e(putmio_lang('admin_sync_added')) ?></p>
        </div>
        <div class="text-center rounded-xl bg-error/10 border border-error/20 px-4 py-2">
          <p class="text-headline-sm font-headline-sm text-error"><?= $removed ?></p>
          <p class="text-label-sm font-label-sm text-error/80"><?= putmio_e(putmio_lang('admin_sync_removed')) ?></p>
        </div>
        <span class="material-symbols-outlined text-on-surface-variant">expand_more</span>
      </div>
    </summary>
    <div class="px-5 pb-5 space-y-4">
      <?php if ($status === 'error' && !empty($run['error_message'])): ?>
      <div class="rounded-xl border border-error/30 bg-error/10 px-4 py-3 text-body-sm text-error">
        <?= putmio_e((string) $run['error_message']) ?>
      </div>
      <?php endif; ?>
      <?php $renderItems('added', $items['added'] ?? []); ?>
      <?php $renderItems('removed', $items['removed'] ?? []); ?>
      <?php if ($updated > 0): ?>
      <?php $renderItems('updated', $items['updated'] ?? []); ?>
      <?php endif; ?>
    </div>
  </details>
  <?php endforeach; ?>
</div>
<?php endif; ?>
