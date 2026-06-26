<?php
use PutMio\Config;

$appUrl = rtrim(Config::get('app.url'), '/');
$sectionNavPart = $sectionNavPart ?? 'all';
?>
<?php if ($sectionNavPart !== 'mobile'): ?>
<aside class="account-sidebar hidden md:flex fixed left-0 top-16 bottom-0 z-40 flex-col py-6 w-64 bg-surface-container-low border-r border-surface-variant/30 shadow-xl">
  <nav class="flex-1 space-y-1 px-3" aria-label="<?= putmio_e(putmio_lang('account_nav_aria')) ?>">
    <a href="<?= putmio_e($appUrl) ?>/account" class="<?= putmio_account_nav_link_class('general') ?>">
      <span class="material-symbols-outlined">language</span>
      <div class="flex flex-col min-w-0">
        <span class="font-label-md text-label-md"><?= putmio_e(putmio_lang('account_settings')) ?></span>
        <span class="text-[10px] text-on-surface-variant/60 font-normal leading-none mt-0.5"><?= putmio_e(putmio_lang('account_nav_settings_hint')) ?></span>
      </div>
    </a>
    <a href="<?= putmio_e($appUrl) ?>/account/dispositivi" class="<?= putmio_account_nav_link_class('devices') ?>">
      <span class="material-symbols-outlined">devices</span>
      <div class="flex flex-col min-w-0">
        <span class="font-label-md text-label-md"><?= putmio_e(putmio_lang('account_devices')) ?></span>
        <span class="text-[10px] text-on-surface-variant/60 font-normal leading-none mt-0.5"><?= putmio_e(putmio_lang('account_nav_devices_hint')) ?></span>
      </div>
    </a>
    <a href="<?= putmio_e($appUrl) ?>/account/contenuti" class="<?= putmio_account_nav_link_class('content') ?>">
      <span class="material-symbols-outlined">video_library</span>
      <div class="flex flex-col min-w-0">
        <span class="font-label-md text-label-md"><?= putmio_e(putmio_lang('account_content')) ?></span>
        <span class="text-[10px] text-on-surface-variant/60 font-normal leading-none mt-0.5"><?= putmio_e(putmio_lang('account_nav_content_hint')) ?></span>
      </div>
    </a>
  </nav>
</aside>
<?php endif; ?>

<?php if ($sectionNavPart !== 'desktop'): ?>
<?php
$accountMobileLinks = [
  [
    'section' => 'general',
    'href' => '/account',
    'label' => putmio_lang('account_settings'),
    'hint' => putmio_lang('account_nav_settings_hint'),
    'icon' => 'language',
  ],
  [
    'section' => 'devices',
    'href' => '/account/dispositivi',
    'label' => putmio_lang('account_devices'),
    'hint' => putmio_lang('account_nav_devices_hint'),
    'icon' => 'devices',
  ],
  [
    'section' => 'content',
    'href' => '/account/contenuti',
    'label' => putmio_lang('account_content'),
    'hint' => putmio_lang('account_nav_content_hint'),
    'icon' => 'video_library',
  ],
];
$accountCurrentSection = putmio_account_section();
$accountCurrentLink = $accountMobileLinks[0];
foreach ($accountMobileLinks as &$link) {
  $link['active'] = $accountCurrentSection === $link['section'];
  if ($link['active']) {
    $accountCurrentLink = $link;
  }
}
unset($link);
$mobileSectionNav = [
  'title' => putmio_lang('account'),
  'aria' => putmio_lang('account_nav_aria'),
  'current_label' => $accountCurrentLink['label'],
  'current_hint' => $accountCurrentLink['hint'],
  'links' => $accountMobileLinks,
];
require putmio_base_path() . '/templates/partials/section-mobile-nav.php';
unset($mobileSectionNav, $accountMobileLinks, $accountCurrentSection, $accountCurrentLink);
?>
<?php endif; ?>
