<?php
$currentLocale = putmio_locale();
$locales = putmio_available_locales();
$menuVariant = $localeMenuVariant ?? 'header';
$triggerClass = $menuVariant === 'auth'
    ? 'pm-locale-menu__trigger pm-locale-menu__trigger--auth'
    : 'pm-locale-menu__trigger';
?>
<div class="pm-locale-menu relative" data-pm-locale-menu>
  <button
    type="button"
    class="<?= $triggerClass ?>"
    aria-haspopup="listbox"
    aria-expanded="false"
    aria-label="<?= putmio_e(putmio_lang('language')) ?>"
    title="<?= putmio_e(putmio_lang('language')) ?>"
  >
    <span class="material-symbols-outlined text-primary text-[20px]" aria-hidden="true">language</span>
    <span class="pm-locale-menu__code text-label-sm font-label-sm uppercase text-on-surface hidden sm:inline"><?= putmio_e($currentLocale) ?></span>
    <span class="material-symbols-outlined pm-locale-menu__chevron text-on-surface-variant text-[18px]" aria-hidden="true">expand_more</span>
  </button>
  <div class="pm-locale-menu__panel hidden" role="listbox" aria-label="<?= putmio_e(putmio_lang('language')) ?>">
    <?php foreach ($locales as $code => $meta): ?>
    <button
      type="button"
      role="option"
      class="pm-locale-menu__option"
      data-locale="<?= putmio_e($code) ?>"
      aria-selected="<?= $code === $currentLocale ? 'true' : 'false' ?>"
      <?= $code === $currentLocale ? 'disabled' : '' ?>
    >
      <span><?= putmio_e($meta['native']) ?></span>
      <?php if ($code === $currentLocale): ?>
      <span class="material-symbols-outlined pm-locale-menu__check text-primary text-[18px]" aria-hidden="true">check</span>
      <?php endif; ?>
    </button>
    <?php endforeach; ?>
  </div>
</div>
