<?php
/** @var string $appUrl */

$navItems = [
    ['/', putmio_lang('home')],
    ['/catalogo', putmio_lang('catalog')],
    ['/in-corso', putmio_lang('in_progress')],
];
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$basePath = rtrim(parse_url(\PutMio\Config::get('app.url', putmio_detect_base_url()), PHP_URL_PATH) ?? '', '/');
if ($basePath !== '' && str_starts_with($currentPath, $basePath)) {
    $currentPath = substr($currentPath, strlen($basePath)) ?: '/';
}
$currentPath = '/' . trim($currentPath, '/');
if ($currentPath === '/tv') {
    $currentPath = '/tv/';
}
?>
<header class="tv-header" data-tv-zone="header">
  <a href="<?= putmio_e(putmio_tv_url('/')) ?>" class="tv-header__brand" data-tv-focus tabindex="0">PutMio</a>
  <nav class="tv-header__nav" aria-label="<?= putmio_e(putmio_lang('catalog')) ?>">
    <?php foreach ($navItems as [$path, $label]):
      $tvPath = $path === '/' ? '/tv/' : '/tv' . $path;
      $active = $currentPath === $tvPath;
      $linkClass = 'tv-header__link' . ($active ? ' tv-header__link--active' : '');
    ?>
    <a
      href="<?= putmio_e(putmio_tv_url($path)) ?>"
      class="<?= $linkClass ?>"
      data-tv-focus
      tabindex="0"
    ><?= putmio_e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="tv-header__actions">
    <a
      href="<?= putmio_e(putmio_tv_url('/logout')) ?>"
      class="tv-header__action"
      data-tv-focus
      tabindex="0"
    ><?= putmio_e(putmio_lang('logout')) ?></a>
  </div>
</header>
