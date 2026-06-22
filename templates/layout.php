<?php
use PutMio\Auth\Csrf;
use PutMio\Auth\Session;
use PutMio\Config;

$appUrl = rtrim(Config::get('app.url', putmio_detect_base_url()), '/');
$userTheme = $_SESSION['user_theme'] ?? $_COOKIE['putmio_theme'] ?? 'dark';
$isDark = $userTheme === 'dark';
$pageTitle = putmio_e($title ?? 'PutMio');
?>
<!DOCTYPE html>
<html lang="it" class="<?= $isDark ? 'dark' : '' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= $pageTitle ?> — PutMio</title>
  <script>
    (function(){var t=localStorage.getItem('putmio_theme')||document.cookie.match(/putmio_theme=(dark|light)/)?.[1]||'<?= $isDark ? 'dark' : 'light' ?>';if(t==='dark')document.documentElement.classList.add('dark');else document.documentElement.classList.remove('dark');})();
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { darkMode: 'class', theme: { extend: { colors: { surface: { DEFAULT: '#0f172a', light: '#f8fafc' }, accent: '#6366f1' } } } };
  </script>
  <link rel="stylesheet" href="<?= putmio_e($appUrl) ?>/public/assets/app.css">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<?php if (Session::userId()): ?>
<header class="border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur sticky top-0 z-40">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
    <a href="<?= putmio_e($appUrl) ?>/" class="font-bold text-lg text-indigo-500">PutMio</a>
    <nav class="flex items-center gap-3 text-sm flex-wrap">
      <a href="<?= putmio_e($appUrl) ?>/" class="hover:text-indigo-400"><?= putmio_lang('home') ?></a>
      <a href="<?= putmio_e($appUrl) ?>/catalogo" class="hover:text-indigo-400">Catalogo</a>
      <a href="<?= putmio_e($appUrl) ?>/in-corso" class="hover:text-indigo-400"><?= putmio_lang('in_progress') ?></a>
      <?php if (Session::isAdmin()): ?>
      <a href="<?= putmio_e($appUrl) ?>/admin" class="hover:text-indigo-400"><?= putmio_lang('admin') ?></a>
      <?php endif; ?>
    </nav>
    <div class="ml-auto flex items-center gap-3">
      <button type="button" id="theme-toggle" class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1 text-sm" title="Tema">
        <span class="dark:hidden">🌙</span><span class="hidden dark:inline">☀️</span>
      </button>
      <span class="text-sm text-slate-500"><?= putmio_e($_SESSION['user_name'] ?? '') ?></span>
      <a href="<?= putmio_e($appUrl) ?>/logout" class="text-sm text-slate-500 hover:text-red-400"><?= putmio_lang('logout') ?></a>
    </div>
  </div>
</header>
<?php endif; ?>
<main class="max-w-7xl mx-auto px-4 py-6">
  <?= $content ?>
</main>
<script>
  window.PUTMIO = {
    baseUrl: <?= json_encode($appUrl) ?>,
    csrf: <?= json_encode(Csrf::token()) ?>
  };
</script>
<script src="<?= putmio_e($appUrl) ?>/public/assets/app.js" defer></script>
</body>
</html>
