<?php
use PutMio\Auth\Csrf;
use PutMio\Config;

$appUrl = rtrim(Config::get('app.url'), '/');
$activeCount = count($active);
$adminCrumbLabel = putmio_lang('admin_streaming');
$adminPageTitle = putmio_lang('admin_streaming');
$adminPageDescription = putmio_lang('admin_streaming_desc');
require putmio_base_path() . '/templates/partials/admin-header.php';
?>
<section class="glass-panel rounded-2xl p-6 mb-8">
  <p class="text-on-surface-variant text-body-md"><?= putmio_e(putmio_lang('admin_bandwidth_today')) ?></p>
  <p class="text-headline-lg font-headline-lg text-primary mt-1"><?= putmio_format_bytes((int) $todayBytes) ?></p>
</section>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
  <h2 class="text-headline-md font-headline-md text-on-surface"><?= putmio_e(putmio_lang('admin_active_sessions', ['count' => (string) $activeCount])) ?></h2>
  <?php if ($activeCount > 0): ?>
  <form method="post" action="<?= putmio_e($appUrl) ?>/admin/streaming/stop-all" onsubmit="return confirm(<?= json_encode(putmio_lang('admin_stop_all_confirm'), JSON_UNESCAPED_UNICODE) ?>);"><?= Csrf::field() ?>
    <button type="submit" class="pm-btn-outline-danger w-full sm:w-auto">
      <span class="material-symbols-outlined text-[18px]">stop_circle</span>
      <?= putmio_e(putmio_lang('admin_stop_all')) ?>
    </button>
  </form>
  <?php endif; ?>
</div>
<?php if (empty($active)): ?>
<p class="text-on-surface-variant text-body-md"><?= putmio_e(putmio_lang('admin_no_streams')) ?></p>
<?php else: ?>
<ul class="space-y-3">
<?php foreach ($active as $s): ?>
<li class="glass-panel rounded-xl p-4 flex flex-col sm:flex-row sm:justify-between gap-2">
  <span class="text-on-surface font-medium"><?= putmio_e($s['display_name'] ?? putmio_lang('admin_unknown_user')) ?> — <?= putmio_e($s['title'] ?? putmio_lang('admin_col_title')) ?></span>
  <span class="text-label-md font-label-md text-on-surface-variant"><?= putmio_format_bytes((int) $s['bytes_sent']) ?> · <?= putmio_e(putmio_session_duration_label($s['started_at'] ?? null)) ?></span>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<p class="mt-6 text-label-sm font-label-sm text-on-surface-variant/70"><?= putmio_e(putmio_lang('admin_refresh_hint')) ?></p>
