<?php
use PutMio\Auth\Session;
use PutMio\Config;

$appUrl = rtrim(Config::get('app.url', putmio_detect_base_url()), '/');
$navItems = [
    ['/', putmio_lang('home')],
    ['/catalogo', putmio_lang('catalog')],
    ['/in-corso', putmio_lang('in_progress')],
];
?>
<header class="pm-tv-header fixed top-0 w-full z-50 flex justify-between items-center px-[5%] h-20 border-b border-outline-variant/30 bg-surface-container/95 shadow-sm">
  <div class="flex items-center gap-8 min-w-0">
    <a href="<?= putmio_e($appUrl) ?>/" class="text-headline-md font-headline-md font-extrabold text-primary shrink-0" data-pm-tv-focus tabindex="0">PutMio</a>
    <nav class="flex gap-2">
      <?php foreach ($navItems as [$href, $label]):
        $active = putmio_nav_is_active($href);
        $linkClass = $active
          ? 'pm-tv-header__link pm-tv-header__link--active'
          : 'pm-tv-header__link';
      ?>
      <a href="<?= putmio_e($appUrl . ($href === '/' ? '/' : $href)) ?>" class="<?= $linkClass ?>" data-pm-tv-focus tabindex="0"><?= putmio_e($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
  <div class="flex items-center gap-3 shrink-0">
    <?php /* Toggle desktop temporaneamente nascosto: funzionalità attiva, UI da riabilitare quando la TV mode è stabile */ ?>
    <button
      type="button"
      id="pm-ui-mode-toggle"
      class="pm-tv-header__action hidden"
      data-pm-tv-focus
      tabindex="0"
      title="<?= putmio_e(putmio_lang('ui_mode_standard')) ?>"
      aria-label="<?= putmio_e(putmio_lang('ui_mode_standard')) ?>"
    >
      <span class="material-symbols-outlined">computer</span>
      <span class="pm-tv-header__action-label"><?= putmio_e(putmio_lang('ui_mode_standard')) ?></span>
    </button>
    <a
      href="<?= putmio_e($appUrl) ?>/logout"
      class="pm-tv-header__action"
      data-pm-tv-focus
      tabindex="0"
      title="<?= putmio_e(putmio_lang('logout')) ?>"
    >
      <span class="material-symbols-outlined">logout</span>
      <span class="pm-tv-header__action-label"><?= putmio_e(putmio_lang('logout')) ?></span>
    </a>
  </div>
</header>
