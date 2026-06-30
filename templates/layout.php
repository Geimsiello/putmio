<?php
use PutMio\Auth\Csrf;
use PutMio\Auth\Session;
use PutMio\Config;

$appUrl = rtrim(Config::get('app.url', putmio_detect_base_url()), '/');
$appLocale = putmio_locale();
$htmlLang = putmio_available_locales()[$appLocale]['html'] ?? 'it';
$pageTitle = putmio_e($title ?? 'PutMio');
$tvUa = putmio_is_tv_user_agent();
$showFab = ($showSearchFab ?? false) && Session::userId() && !$tvUa;
$htmlClasses = 'dark' . ($tvUa ? ' tv-ua tv-mode' : '');
$viewportContent = $tvUa
    ? 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no'
    : 'width=device-width, initial-scale=1';
?>
<!DOCTYPE html>
<html lang="<?= putmio_e($htmlLang) ?>" class="<?= putmio_e($htmlClasses) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="<?= putmio_e($viewportContent) ?>">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#0b1326">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="PutMio">
  <title><?= $pageTitle ?> — PutMio</title>
  <link rel="manifest" href="<?= putmio_e($appUrl) ?>/manifest.webmanifest">
  <link rel="icon" href="<?= putmio_e($appUrl) ?>/public/assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="<?= putmio_e($appUrl) ?>/public/assets/icons/icon-192.png">
  <script>
    (function(){document.documentElement.classList.add('dark');try{localStorage.setItem('putmio_theme','dark');}catch(e){}document.cookie='putmio_theme=dark;path=/;max-age=31536000;SameSite=Strict';})();
  </script>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            primary: '#c0c1ff',
            'on-primary': '#1000a9',
            'primary-container': '#8083ff',
            'on-primary-container': '#0d0096',
            'primary-fixed-dim': '#c0c1ff',
            secondary: '#ffe083',
            tertiary: '#ffb783',
            'tertiary-container': '#d97721',
            'on-tertiary-container': '#452000',
            surface: '#0b1326',
            'surface-dim': '#0b1326',
            'surface-container': '#171f33',
            'surface-container-low': '#131b2e',
            'surface-container-high': '#222a3d',
            'surface-container-highest': '#2d3449',
            'surface-container-lowest': '#060e20',
            'surface-variant': '#2d3449',
            'on-surface': '#dae2fd',
            'on-surface-variant': '#c7c4d7',
            outline: '#908fa0',
            'outline-variant': '#464554',
            background: '#0b1326',
            warning: '#f59e0b',
            success: '#10b981',
            error: '#ef4444',
            'surface-bright': '#31394d',
            'on-tertiary': '#4f2500',
          },
          fontFamily: {
            'display-lg': ['Hanken Grotesk', 'sans-serif'],
            'display-lg-mobile': ['Hanken Grotesk', 'sans-serif'],
            'headline-lg': ['Hanken Grotesk', 'sans-serif'],
            'headline-md': ['Hanken Grotesk', 'sans-serif'],
            'body-md': ['Hanken Grotesk', 'sans-serif'],
            'body-lg': ['Hanken Grotesk', 'sans-serif'],
            'label-md': ['JetBrains Mono', 'monospace'],
            'label-sm': ['JetBrains Mono', 'monospace'],
          },
          fontSize: {
            'display-lg': ['48px', { lineHeight: '56px', letterSpacing: '-0.02em', fontWeight: '800' }],
            'display-lg-mobile': ['32px', { lineHeight: '40px', fontWeight: '800' }],
            'headline-lg': ['32px', { lineHeight: '40px', fontWeight: '700' }],
            'headline-md': ['24px', { lineHeight: '32px', fontWeight: '700' }],
            'body-md': ['16px', { lineHeight: '24px', fontWeight: '400' }],
            'body-lg': ['18px', { lineHeight: '28px', fontWeight: '400' }],
            'label-md': ['14px', { lineHeight: '20px', letterSpacing: '0.05em', fontWeight: '500' }],
            'label-sm': ['12px', { lineHeight: '16px', fontWeight: '500' }],
          },
          spacing: {
            'margin-desktop': '2.5rem',
            'margin-mobile': '1rem',
          },
          maxWidth: {
            'container-max': '1440px',
          },
        },
      },
    };
  </script>
  <link rel="stylesheet" href="<?= putmio_e(putmio_asset('public/assets/app.css')) ?>">
  <?= $extraHead ?? '' ?>
</head>
<body class="min-h-screen bg-background text-on-surface selection:bg-primary/30">
<?php if (Session::userId()): ?>
<?php if ($tvUa): ?>
<?php require putmio_base_path() . '/templates/partials/tv-header.php'; ?>
<?php else: ?>
<header class="fixed top-0 w-full z-50 flex justify-between items-center px-4 md:px-margin-desktop h-16 glass-header border-b border-outline-variant/30 shadow-sm">
  <div class="flex items-center gap-6 md:gap-8 min-w-0">
    <a href="<?= putmio_e($appUrl) ?>/" class="text-headline-md font-headline-md font-extrabold text-primary-fixed-dim shrink-0">PutMio</a>
    <nav class="hidden md:flex gap-6">
      <?php
      $navItems = [
        ['/', putmio_lang('home')],
        ['/catalogo', putmio_lang('catalog')],
        ['/in-corso', putmio_lang('in_progress')],
        ['/watchlist', putmio_lang('watchlist')],
      ];
      if (Session::isAdmin()) {
        $navItems[] = ['/admin', putmio_lang('admin')];
      }
      foreach ($navItems as [$href, $label]):
        $active = putmio_nav_is_active($href);
        $linkClass = $active
          ? 'text-primary-fixed-dim font-bold border-b-2 border-primary pb-1 text-label-md font-label-md'
          : 'text-on-surface-variant hover:text-primary transition-colors text-label-md font-label-md';
      ?>
      <a href="<?= putmio_e($appUrl . ($href === '/' ? '/' : $href)) ?>" class="<?= $linkClass ?>"><?= putmio_e($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
  <div class="pm-header-actions flex items-center gap-2 md:gap-3 shrink-0">
    <div class="hidden md:block">
      <?php require putmio_base_path() . '/templates/partials/locale-menu.php'; ?>
    </div>
    <?php if (!Session::isAdmin()): ?>
    <a
      href="<?= putmio_e($appUrl) ?>/account"
      class="pm-header-settings inline-flex items-center justify-center p-2 rounded-full text-on-surface-variant hover:text-primary hover:bg-surface-variant/30 transition-colors"
      title="<?= putmio_e(putmio_lang('account_settings')) ?>"
      aria-label="<?= putmio_e(putmio_lang('account_settings')) ?>"
    >
      <span class="material-symbols-outlined text-[22px]" aria-hidden="true">settings</span>
    </a>
    <?php endif; ?>
    <div class="hidden md:flex items-center gap-2 px-3 py-1 bg-surface-container rounded-full border border-outline-variant/20">
      <span class="text-label-md font-label-md text-on-surface"><?= putmio_e($_SESSION['user_name'] ?? '') ?></span>
      <a href="<?= putmio_e($appUrl) ?>/logout" class="text-on-surface-variant hover:text-primary transition-colors" title="<?= putmio_lang('logout') ?>">
        <span class="material-symbols-outlined text-base">logout</span>
      </a>
    </div>
    <?php $mobileNavPart = 'toggle'; require putmio_base_path() . '/templates/partials/mobile-nav.php'; unset($mobileNavPart); ?>
  </div>
</header>
<?php $mobileNavPart = 'drawer'; require putmio_base_path() . '/templates/partials/mobile-nav.php'; unset($mobileNavPart); ?>
<?php endif; ?>
<?php endif; ?>
<?php
$adminSection = putmio_admin_section();
$isAdminShell = $adminSection !== null;
$accountSection = putmio_account_section();
$isAccountShell = $accountSection !== null;
$isAuthShell = !empty($authShell) && !Session::userId();
?>
<?php if ($isAuthShell): ?>
<div class="fixed inset-0 overflow-hidden pointer-events-none -z-10">
  <div class="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] bg-primary/10 rounded-full blur-[120px]"></div>
  <div class="absolute -bottom-[10%] -right-[10%] w-[40%] h-[40%] bg-tertiary/5 rounded-full blur-[120px]"></div>
</div>
<div class="fixed top-6 right-6 md:top-8 md:right-8 z-50 flex items-center gap-2">
  <?php $localeMenuVariant = 'auth'; require putmio_base_path() . '/templates/partials/locale-menu.php'; unset($localeMenuVariant); ?>
</div>
<main class="auth-shell min-h-screen flex items-center justify-center p-4 md:p-6">
  <?= $content ?>
</main>
<?php elseif ($isAdminShell): ?>
<?php $sectionNavPart = 'desktop'; require putmio_base_path() . '/templates/partials/admin-sidebar.php'; unset($sectionNavPart); ?>
<main class="pt-24 min-h-screen md:ml-64 bg-background">
  <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop pb-6 md:pb-10">
    <?php $sectionNavPart = 'mobile'; require putmio_base_path() . '/templates/partials/admin-sidebar.php'; unset($sectionNavPart); ?>
    <?= $content ?>
  </div>
</main>
<?php elseif ($isAccountShell): ?>
<?php $sectionNavPart = 'desktop'; require putmio_base_path() . '/templates/partials/account-sidebar.php'; unset($sectionNavPart); ?>
<main class="pt-24 min-h-screen md:ml-64 bg-background">
  <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop pb-6 md:pb-10">
    <?php $sectionNavPart = 'mobile'; require putmio_base_path() . '/templates/partials/account-sidebar.php'; unset($sectionNavPart); ?>
    <?= $content ?>
  </div>
</main>
<?php else: ?>
<main class="<?= Session::userId() ? 'pt-24 pb-12' : 'py-6' ?> min-h-screen max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop">
  <?= $content ?>
</main>
<?php endif; ?>
<?php if ($showFab): ?>
<a href="<?= putmio_e($appUrl) ?>/catalogo" class="fixed bottom-8 right-8 z-40 w-14 h-14 bg-primary text-on-primary rounded-full shadow-2xl flex items-center justify-center hover:scale-110 active:scale-95 transition-all group" title="<?= putmio_lang('search') ?>">
  <span class="material-symbols-outlined text-3xl group-hover:rotate-12 transition-transform">search</span>
</a>
<?php endif; ?>
<script>
  window.PUTMIO = <?= json_encode(array_merge([
    'baseUrl' => $appUrl,
    'csrf' => Csrf::token(),
    'localeChangeError' => putmio_lang('locale_change_error'),
    'isTvDevice' => $tvUa,
    'tvMode' => $tvUa,
    'tvKeyUpFallback' => $tvUa,
    'watchlistAddLabel' => putmio_lang('watchlist_add'),
    'watchlistRemoveLabel' => putmio_lang('watchlist_remove'),
    'watchlistErrorLabel' => putmio_lang('watchlist_error'),
  ], $putmioExtra ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<?php if ($tvUa): ?>
<script src="<?= putmio_e(putmio_asset('public/assets/tv-keys.js')) ?>" defer></script>
<script src="<?= putmio_e(putmio_asset('public/assets/tv-nav.js')) ?>" defer></script>
<?php endif; ?>
<script src="<?= putmio_e(putmio_asset('public/assets/app.js')) ?>" defer></script>
<?php if (Session::userId() && !$tvUa): ?>
<script src="<?= putmio_e(putmio_asset('public/assets/watchlist.js')) ?>" defer></script>
<?php endif; ?>
<?= $extraScripts ?? '' ?>
</body>
</html>
