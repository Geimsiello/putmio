<?php
use PutMio\Auth\Session;

$mobileNavPart = $mobileNavPart ?? 'toggle';

if ($mobileNavPart === 'toggle'):
?>
<button
  type="button"
  id="pm-mobile-nav-toggle"
  class="pm-mobile-nav__toggle md:hidden p-2 rounded-full hover:bg-surface-variant transition-colors"
  aria-expanded="false"
  aria-controls="pm-mobile-nav"
  aria-label="<?= putmio_e(putmio_lang('nav_menu_open')) ?>"
>
  <span class="material-symbols-outlined text-on-surface" aria-hidden="true">menu</span>
</button>
<?php
  return;
endif;

$navItems = [
    ['/', putmio_lang('home'), 'home'],
    ['/catalogo', putmio_lang('catalog'), 'video_library'],
    ['/in-corso', putmio_lang('in_progress'), 'play_circle'],
    ['/authorize-device', putmio_lang('device_authorize_nav'), 'devices'],
];
if (Session::isAdmin()) {
    $navItems[] = ['/admin', putmio_lang('admin'), 'admin_panel_settings'];
}
$currentLocale = putmio_locale();
$locales = putmio_available_locales();
$currentLocaleMeta = $locales[$currentLocale] ?? ['native' => strtoupper($currentLocale)];
$userName = trim((string) ($_SESSION['user_name'] ?? ''));
?>
<div
  id="pm-mobile-nav"
  class="pm-mobile-nav hidden md:hidden bg-slate-50 dark:bg-background text-slate-900 dark:text-on-surface"
  role="dialog"
  aria-modal="true"
  aria-hidden="true"
  aria-label="<?= putmio_e(putmio_lang('nav_menu_aria')) ?>"
>
  <div class="pm-mobile-nav__topbar">
    <button
      type="button"
      id="pm-mobile-nav-close"
      class="pm-mobile-nav__close"
      aria-label="<?= putmio_e(putmio_lang('nav_menu_close')) ?>"
    >
      <span class="material-symbols-outlined text-[28px]" aria-hidden="true">close</span>
    </button>
  </div>

  <div class="pm-mobile-nav__inner">
    <nav class="pm-mobile-nav__main" aria-label="<?= putmio_e(putmio_lang('nav_menu_aria')) ?>">
      <?php foreach ($navItems as [$href, $label, $icon]):
        $active = putmio_nav_is_active($href);
        $itemClass = $active ? 'pm-mobile-nav__link pm-mobile-nav__link--active' : 'pm-mobile-nav__link';
      ?>
      <a href="<?= putmio_e($appUrl . ($href === '/' ? '/' : $href)) ?>" class="<?= $itemClass ?>">
        <span class="pm-mobile-nav__link-leading">
          <span class="material-symbols-outlined pm-mobile-nav__icon" aria-hidden="true"><?= putmio_e($icon) ?></span>
          <span><?= putmio_e($label) ?></span>
        </span>
      </a>
      <?php endforeach; ?>
    </nav>

    <div class="pm-mobile-nav__footer">
      <div class="pm-mobile-nav__locale" data-pm-locale-menu>
        <button
          type="button"
          class="pm-mobile-nav__locale-trigger pm-locale-menu__trigger"
          aria-haspopup="listbox"
          aria-expanded="false"
          aria-label="<?= putmio_e(putmio_lang('language')) ?>"
        >
          <span class="pm-mobile-nav__link-leading">
            <span class="material-symbols-outlined pm-mobile-nav__icon text-primary" aria-hidden="true">language</span>
            <span><?= putmio_e(putmio_lang('language')) ?></span>
          </span>
          <span class="pm-mobile-nav__locale-meta">
            <span class="pm-mobile-nav__locale-current"><?= putmio_e($currentLocaleMeta['native']) ?></span>
            <span class="material-symbols-outlined pm-mobile-nav__chevron" aria-hidden="true">expand_more</span>
          </span>
        </button>
        <div class="pm-mobile-nav__locale-panel pm-locale-menu__panel hidden" role="listbox" aria-label="<?= putmio_e(putmio_lang('language')) ?>">
          <?php foreach ($locales as $code => $meta): ?>
          <button
            type="button"
            role="option"
            class="pm-locale-menu__option pm-mobile-nav__locale-option"
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

      <a href="<?= putmio_e($appUrl) ?>/logout" class="pm-mobile-nav__link pm-mobile-nav__link--logout">
        <span class="pm-mobile-nav__link-leading">
          <span class="material-symbols-outlined pm-mobile-nav__icon" aria-hidden="true">logout</span>
          <?php if ($userName !== ''): ?>
          <span class="pm-mobile-nav__logout-name"><?= putmio_e($userName) ?></span>
          <?php endif; ?>
          <span><?= putmio_e(putmio_lang('logout')) ?></span>
        </span>
      </a>
    </div>
  </div>
</div>
