<?php
/** @var string $title */
/** @var bool|null $guest */
/** @var string $content */
/** @var array<string, mixed>|null $putmioExtra */
/** @var string|null $extraHead */
/** @var string|null $extraScripts */

use PutMio\Auth\Csrf;
use PutMio\Auth\Session;
use PutMio\Config;

$appUrl = rtrim(Config::get('app.url', putmio_detect_base_url()), '/');
$appLocale = putmio_locale();
$htmlLang = putmio_available_locales()[$appLocale]['html'] ?? 'it';
$pageTitle = putmio_e($title ?? 'PutMio');
$isGuest = !empty($guest) || !Session::userId();
putmio_tv_security_headers(!$isGuest);
$bodyClass = $isGuest ? 'tv-site tv-site--guest' : 'tv-site tv-site--app';
$extraHead = $extraHead ?? '';
$extraScripts = $extraScripts ?? '';
?>
<!DOCTYPE html>
<html lang="<?= putmio_e($htmlLang) ?>" class="tv-site dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#0b1326">
  <title><?= $pageTitle ?> — PutMio TV</title>
  <link rel="icon" href="<?= putmio_e($appUrl) ?>/public/assets/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="<?= putmio_e(putmio_tv_asset('tv.css')) ?>">
  <?= $extraHead ?>
</head>
<body class="<?= putmio_e($bodyClass) ?>">
<?php if (!$isGuest): ?>
<?php require putmio_base_path() . '/templates/tv/partials/header.php'; ?>
<?php endif; ?>

<main class="tv-main" id="tv-main">
  <?= $content ?>
</main>

<?php if (!$isGuest): ?>
<aside class="tv-info-rail" id="tv-info-rail" aria-live="polite" hidden>
  <div class="tv-info-rail__inner">
    <h2 class="tv-info-rail__title" id="tv-info-rail-title"></h2>
    <p class="tv-info-rail__meta" id="tv-info-rail-meta"></p>
    <p class="tv-info-rail__synopsis" id="tv-info-rail-synopsis"></p>
  </div>
</aside>
<?php endif; ?>

<script>
  window.PUTMIO = <?= json_encode(array_merge([
    'baseUrl' => $appUrl,
    'tvSite' => true,
    'csrf' => Csrf::token(),
  ], $putmioExtra ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= putmio_e(putmio_tv_asset('spatial-nav.js')) ?>" defer></script>
<?= $extraScripts ?>
</body>
</html>
