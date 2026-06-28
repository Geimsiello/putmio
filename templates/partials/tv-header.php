<?php
/** @var string $appUrl */

$navItems = [
    ['/', putmio_lang('home')],
    ['/catalogo', putmio_lang('catalog')],
    ['/in-corso', putmio_lang('in_progress')],
];
?>
<header class="pm-tv-header glass-header">
  <a href="<?= putmio_e($appUrl) ?>/" class="pm-tv-header__brand" data-pm-tv-focus tabindex="0">PutMio</a>
  <nav class="pm-tv-header__nav" aria-label="<?= putmio_e(putmio_lang('catalog')) ?>">
    <?php foreach ($navItems as [$href, $label]):
      $active = putmio_nav_is_active($href);
      $linkClass = 'pm-tv-header__link' . ($active ? ' pm-tv-header__link--active' : '');
    ?>
    <a
      href="<?= putmio_e($appUrl . ($href === '/' ? '/' : $href)) ?>"
      class="<?= $linkClass ?>"
      data-pm-tv-focus
      tabindex="0"
    ><?= putmio_e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="pm-tv-header__actions">
    <a
      href="<?= putmio_e($appUrl) ?>/logout"
      class="pm-tv-header__action"
      data-pm-tv-focus
      tabindex="0"
      title="<?= putmio_e(putmio_lang('logout')) ?>"
    >
      <span class="material-symbols-outlined text-[20px]" aria-hidden="true">logout</span>
      <span><?= putmio_e(putmio_lang('logout')) ?></span>
    </a>
  </div>
</header>
