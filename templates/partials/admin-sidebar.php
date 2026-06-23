<?php
use PutMio\Config;

$appUrl = rtrim(Config::get('app.url'), '/');
$navStats = putmio_admin_nav_stats();
$unclassified = (int) ($navStats['unclassified'] ?? 0);
?>
<aside class="admin-sidebar hidden md:flex fixed left-0 top-16 bottom-0 z-40 flex-col py-6 w-64 bg-surface-container-low border-r border-surface-variant/30 shadow-xl">
  <nav class="flex-1 space-y-1 px-3">
    <a href="<?= putmio_e($appUrl) ?>/admin" class="<?= putmio_admin_nav_link_class('dashboard') ?>">
      <span class="material-symbols-outlined">dashboard</span>
      <div class="flex flex-col min-w-0">
        <span class="font-label-md text-label-md"><?= putmio_e(putmio_lang('admin_dashboard')) ?></span>
        <span class="text-[10px] text-on-surface-variant/60 font-normal leading-none mt-0.5"><?= putmio_e(putmio_lang('admin_nav_overview')) ?></span>
      </div>
    </a>
    <a href="<?= putmio_e($appUrl) ?>/admin/impostazioni" class="<?= putmio_admin_nav_link_class('settings') ?>">
      <span class="material-symbols-outlined">settings</span>
      <div class="flex flex-col min-w-0">
        <span class="font-label-md text-label-md"><?= putmio_lang('settings') ?></span>
        <span class="text-[10px] text-on-surface-variant/60 font-normal leading-none mt-0.5"><?= putmio_e(putmio_lang('admin_nav_settings_hint')) ?></span>
      </div>
    </a>
    <a href="<?= putmio_e($appUrl) ?>/admin/classificazione" class="<?= putmio_admin_nav_link_class('classify') ?>">
      <span class="material-symbols-outlined">inventory_2</span>
      <div class="flex flex-col min-w-0">
        <span class="font-label-md text-label-md"><?= putmio_lang('classify') ?></span>
        <span class="text-[10px] text-on-surface-variant/60 font-normal leading-none mt-0.5"><?= putmio_e(putmio_lang('admin_nav_unclassified', ['count' => (string) $unclassified])) ?></span>
      </div>
    </a>
    <a href="<?= putmio_e($appUrl) ?>/admin/streaming" class="<?= putmio_admin_nav_link_class('streaming') ?>">
      <span class="material-symbols-outlined">lan</span>
      <div class="flex flex-col min-w-0">
        <span class="font-label-md text-label-md"><?= putmio_e(putmio_lang('admin_streaming')) ?></span>
        <span class="text-[10px] text-on-surface-variant/60 font-normal leading-none mt-0.5"><?= putmio_e(putmio_lang('admin_nav_streaming_hint')) ?></span>
      </div>
    </a>
    <a href="<?= putmio_e($appUrl) ?>/admin/utenti" class="<?= putmio_admin_nav_link_class('users') ?>">
      <span class="material-symbols-outlined">group</span>
      <div class="flex flex-col min-w-0">
        <span class="font-label-md text-label-md"><?= putmio_e(putmio_lang('admin_users')) ?></span>
        <span class="text-[10px] text-on-surface-variant/60 font-normal leading-none mt-0.5"><?= putmio_e(putmio_lang('admin_nav_users_hint')) ?></span>
      </div>
    </a>
  </nav>
</aside>

<nav class="md:hidden flex gap-2 overflow-x-auto pb-2 mb-6 custom-scrollbar -mx-1 px-1" aria-label="<?= putmio_e(putmio_lang('admin_nav_aria')) ?>">
  <?php
  $mobileLinks = [
    ['dashboard', '/admin', putmio_lang('admin_dashboard')],
    ['settings', '/admin/impostazioni', putmio_lang('settings')],
    ['classify', '/admin/classificazione', putmio_lang('classify')],
    ['streaming', '/admin/streaming', putmio_lang('admin_streaming')],
    ['users', '/admin/utenti', putmio_lang('admin_users')],
  ];
  foreach ($mobileLinks as [$section, $href, $label]):
    $active = putmio_admin_section() === $section;
    $cls = $active
      ? 'shrink-0 px-3 py-1.5 rounded-full bg-primary/15 text-primary border border-primary/30 font-label-md text-label-sm'
      : 'shrink-0 px-3 py-1.5 rounded-full bg-surface-container text-on-surface-variant border border-outline-variant/30 font-label-md text-label-sm';
  ?>
  <a href="<?= putmio_e($appUrl . $href) ?>" class="<?= $cls ?>"><?= putmio_e($label) ?></a>
  <?php endforeach; ?>
</nav>
