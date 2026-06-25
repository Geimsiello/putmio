<?php
use PutMio\Config;

$appUrl = rtrim(Config::get('app.url'), '/');
$mobileSectionNav = $mobileSectionNav ?? [];
$navTitle = (string) ($mobileSectionNav['title'] ?? '');
$navAriaLabel = (string) ($mobileSectionNav['aria'] ?? $navTitle);
$currentLabel = (string) ($mobileSectionNav['current_label'] ?? $navTitle);
$currentHint = (string) ($mobileSectionNav['current_hint'] ?? '');
$links = $mobileSectionNav['links'] ?? [];
?>
<details class="pm-section-mobile-nav md:hidden mb-6">
  <summary class="pm-section-mobile-nav__summary">
    <span class="pm-section-mobile-nav__summary-text">
      <span class="pm-section-mobile-nav__eyebrow"><?= putmio_e($navTitle) ?></span>
      <span class="pm-section-mobile-nav__current"><?= putmio_e($currentLabel) ?></span>
      <?php if ($currentHint !== ''): ?>
      <span class="pm-section-mobile-nav__hint"><?= putmio_e($currentHint) ?></span>
      <?php endif; ?>
    </span>
    <span class="material-symbols-outlined pm-section-mobile-nav__chevron" aria-hidden="true">expand_more</span>
  </summary>

  <nav class="pm-section-mobile-nav__panel" aria-label="<?= putmio_e($navAriaLabel) ?>">
    <?php foreach ($links as $link):
      $active = !empty($link['active']);
      $itemClass = $active
        ? 'pm-section-mobile-nav__link pm-section-mobile-nav__link--active'
        : 'pm-section-mobile-nav__link';
    ?>
    <a
      href="<?= putmio_e($appUrl . (string) $link['href']) ?>"
      class="<?= $itemClass ?>"
      <?= $active ? 'aria-current="page"' : '' ?>
    >
      <span class="material-symbols-outlined pm-section-mobile-nav__icon" aria-hidden="true"><?= putmio_e((string) $link['icon']) ?></span>
      <span class="pm-section-mobile-nav__link-text">
        <span class="pm-section-mobile-nav__link-label"><?= putmio_e((string) $link['label']) ?></span>
        <?php if (!empty($link['hint'])): ?>
        <span class="pm-section-mobile-nav__link-hint"><?= putmio_e((string) $link['hint']) ?></span>
        <?php endif; ?>
      </span>
    </a>
    <?php endforeach; ?>
  </nav>
</details>
