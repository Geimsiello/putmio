<?php
use PutMio\Config;

$appUrl = rtrim(Config::get('app.url'), '/');
?>
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

<nav class="md:hidden flex gap-2 overflow-x-auto pb-2 mb-6 custom-scrollbar -mx-1 px-1" aria-label="<?= putmio_e(putmio_lang('account_nav_aria')) ?>">
  <?php
  $mobileLinks = [
    ['general', '/account', putmio_lang('account_settings')],
    ['devices', '/account/dispositivi', putmio_lang('account_devices')],
    ['content', '/account/contenuti', putmio_lang('account_content')],
  ];
  foreach ($mobileLinks as [$section, $href, $label]):
    $active = putmio_account_section() === $section;
    $cls = $active
      ? 'shrink-0 px-3 py-1.5 rounded-full bg-primary/15 text-primary border border-primary/30 font-label-md text-label-sm'
      : 'shrink-0 px-3 py-1.5 rounded-full bg-surface-container text-on-surface-variant border border-outline-variant/30 font-label-md text-label-sm';
  ?>
  <a href="<?= putmio_e($appUrl . $href) ?>" class="<?= $cls ?>"><?= putmio_e($label) ?></a>
  <?php endforeach; ?>
</nav>
