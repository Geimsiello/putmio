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
        <span class="font-label-md text-label-md">Dashboard</span>
        <span class="text-[10px] text-on-surface-variant/60 font-normal leading-none mt-0.5">Panoramica sistema</span>
      </div>
    </a>
    <a href="<?= putmio_e($appUrl) ?>/admin/impostazioni" class="<?= putmio_admin_nav_link_class('settings') ?>">
      <span class="material-symbols-outlined">settings</span>
      <div class="flex flex-col min-w-0">
        <span class="font-label-md text-label-md"><?= putmio_lang('settings') ?></span>
        <span class="text-[10px] text-on-surface-variant/60 font-normal leading-none mt-0.5">put.io, TMDB, SMTP</span>
      </div>
    </a>
    <a href="<?= putmio_e($appUrl) ?>/admin/classificazione" class="<?= putmio_admin_nav_link_class('classify') ?>">
      <span class="material-symbols-outlined">inventory_2</span>
      <div class="flex flex-col min-w-0">
        <span class="font-label-md text-label-md"><?= putmio_lang('classify') ?></span>
        <span class="text-[10px] text-on-surface-variant/60 font-normal leading-none mt-0.5"><?= $unclassified ?> da classificare</span>
      </div>
    </a>
    <a href="<?= putmio_e($appUrl) ?>/admin/streaming" class="<?= putmio_admin_nav_link_class('streaming') ?>">
      <span class="material-symbols-outlined">lan</span>
      <div class="flex flex-col min-w-0">
        <span class="font-label-md text-label-md">Streaming</span>
        <span class="text-[10px] text-on-surface-variant/60 font-normal leading-none mt-0.5">Banda e sessioni attive</span>
      </div>
    </a>
    <a href="<?= putmio_e($appUrl) ?>/admin/utenti" class="<?= putmio_admin_nav_link_class('users') ?>">
      <span class="material-symbols-outlined">group</span>
      <div class="flex flex-col min-w-0">
        <span class="font-label-md text-label-md">Utenti</span>
        <span class="text-[10px] text-on-surface-variant/60 font-normal leading-none mt-0.5">Inviti famiglia</span>
      </div>
    </a>
  </nav>
</aside>

<nav class="md:hidden flex gap-2 overflow-x-auto pb-2 mb-6 custom-scrollbar -mx-1 px-1" aria-label="Navigazione admin">
  <?php
  $mobileLinks = [
    ['dashboard', '/admin', 'Dashboard'],
    ['settings', '/admin/impostazioni', putmio_lang('settings')],
    ['classify', '/admin/classificazione', putmio_lang('classify')],
    ['streaming', '/admin/streaming', 'Streaming'],
    ['users', '/admin/utenti', 'Utenti'],
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
