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
    <a href="<?= putmio_e($appUrl) ?>/admin/sincronizzazioni" class="<?= putmio_admin_nav_link_class('sync-log') ?>">
      <span class="material-symbols-outlined">sync_saved_locally</span>
      <div class="flex flex-col min-w-0">
        <span class="font-label-md text-label-md"><?= putmio_e(putmio_lang('admin_sync_log')) ?></span>
        <span class="text-[10px] text-on-surface-variant/60 font-normal leading-none mt-0.5"><?= putmio_e(putmio_lang('admin_nav_sync_log_hint')) ?></span>
      </div>
    </a>
    <a href="<?= putmio_e($appUrl) ?>/admin/utenti" class="<?= putmio_admin_nav_link_class('users') ?>">
      <span class="material-symbols-outlined">group</span>
      <div class="flex flex-col min-w-0">
        <span class="font-label-md text-label-md"><?= putmio_e(putmio_lang('admin_users')) ?></span>
        <span class="text-[10px] text-on-surface-variant/60 font-normal leading-none mt-0.5"><?= putmio_e(putmio_lang('admin_nav_users_hint')) ?></span>
      </div>
    </a>
    <a href="<?= putmio_e($appUrl) ?>/admin/dispositivi" class="<?= putmio_admin_nav_link_class('devices') ?>">
      <span class="material-symbols-outlined">devices</span>
      <div class="flex flex-col min-w-0">
        <span class="font-label-md text-label-md"><?= putmio_e(putmio_lang('account_devices')) ?></span>
        <span class="text-[10px] text-on-surface-variant/60 font-normal leading-none mt-0.5"><?= putmio_e(putmio_lang('admin_nav_devices_hint')) ?></span>
      </div>
    </a>
    <a href="<?= putmio_e($appUrl) ?>/admin/aggiornamenti" class="<?= putmio_admin_nav_link_class('updates') ?>">
      <span class="material-symbols-outlined">system_update</span>
      <div class="flex flex-col min-w-0">
        <span class="font-label-md text-label-md"><?= putmio_e(putmio_lang('admin_updates')) ?></span>
        <span class="text-[10px] text-on-surface-variant/60 font-normal leading-none mt-0.5"><?= putmio_e(putmio_lang('admin_nav_updates_hint')) ?></span>
      </div>
    </a>
  </nav>
  <div class="px-4 py-4 border-t border-outline-variant/20 mt-auto shrink-0" aria-label="<?= putmio_e(putmio_lang('admin_version_aria')) ?>">
    <div class="flex items-center gap-2.5 text-on-surface-variant/70">
      <span class="material-symbols-outlined text-lg opacity-50" aria-hidden="true">info</span>
      <div class="flex flex-col min-w-0">
        <span class="text-[10px] uppercase tracking-wider font-label-md text-on-surface-variant/50 leading-none">PutMio</span>
        <span class="font-label-sm text-label-sm text-on-surface-variant mt-1"><?= putmio_e(putmio_lang('admin_platform_version', ['version' => putmio_version()])) ?></span>
      </div>
    </div>
  </div>
</aside>

<?php
$adminMobileLinks = [
  [
    'section' => 'dashboard',
    'href' => '/admin',
    'label' => putmio_lang('admin_dashboard'),
    'hint' => putmio_lang('admin_nav_overview'),
    'icon' => 'dashboard',
  ],
  [
    'section' => 'settings',
    'href' => '/admin/impostazioni',
    'label' => putmio_lang('settings'),
    'hint' => putmio_lang('admin_nav_settings_hint'),
    'icon' => 'settings',
  ],
  [
    'section' => 'classify',
    'href' => '/admin/classificazione',
    'label' => putmio_lang('classify'),
    'hint' => putmio_lang('admin_nav_unclassified', ['count' => (string) $unclassified]),
    'icon' => 'inventory_2',
  ],
  [
    'section' => 'streaming',
    'href' => '/admin/streaming',
    'label' => putmio_lang('admin_streaming'),
    'hint' => putmio_lang('admin_nav_streaming_hint'),
    'icon' => 'lan',
  ],
  [
    'section' => 'sync-log',
    'href' => '/admin/sincronizzazioni',
    'label' => putmio_lang('admin_sync_log'),
    'hint' => putmio_lang('admin_nav_sync_log_hint'),
    'icon' => 'sync_saved_locally',
  ],
  [
    'section' => 'users',
    'href' => '/admin/utenti',
    'label' => putmio_lang('admin_users'),
    'hint' => putmio_lang('admin_nav_users_hint'),
    'icon' => 'group',
  ],
  [
    'section' => 'devices',
    'href' => '/admin/dispositivi',
    'label' => putmio_lang('account_devices'),
    'hint' => putmio_lang('admin_nav_devices_hint'),
    'icon' => 'devices',
  ],
  [
    'section' => 'updates',
    'href' => '/admin/aggiornamenti',
    'label' => putmio_lang('admin_updates'),
    'hint' => putmio_lang('admin_nav_updates_hint'),
    'icon' => 'system_update',
  ],
];
$adminCurrentSection = putmio_admin_section();
$adminCurrentLink = $adminMobileLinks[0];
foreach ($adminMobileLinks as &$link) {
  $link['active'] = $adminCurrentSection === $link['section'];
  if ($link['active']) {
    $adminCurrentLink = $link;
  }
}
unset($link);
$mobileSectionNav = [
  'title' => putmio_lang('admin'),
  'aria' => putmio_lang('admin_nav_aria'),
  'current_label' => $adminCurrentLink['label'],
  'current_hint' => $adminCurrentLink['hint'],
  'links' => $adminMobileLinks,
];
require putmio_base_path() . '/templates/partials/section-mobile-nav.php';
unset($mobileSectionNav, $adminMobileLinks, $adminCurrentSection, $adminCurrentLink);
?>
