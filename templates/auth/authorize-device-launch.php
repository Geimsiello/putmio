<?php
use PutMio\Config;

$appUrl = rtrim(Config::get('app.url', putmio_detect_base_url()), '/');
$authorizeUrl = $appUrl . '/authorize-device?code=' . rawurlencode($code);
$browserUrl = $authorizeUrl . '&browser=1';
$brandTagline = putmio_lang('tagline');
$brandAppUrl = $appUrl;
$brandUseIcon = false;
?>
<div class="w-full max-w-[400px]">
  <?php require putmio_base_path() . '/templates/partials/brand-header.php'; ?>

  <div class="auth-glass rounded-xl shadow-2xl p-6 md:p-8">
    <div class="mb-6">
      <h1 class="font-headline-md text-headline-md text-on-surface mb-2"><?= putmio_lang('device_pwa_launch_title') ?></h1>
      <div class="h-1 w-12 bg-primary rounded-full" aria-hidden="true"></div>
    </div>

    <div id="pwa-launch-redirecting" class="hidden flex flex-col items-center gap-3 py-6 text-center">
      <span class="material-symbols-outlined text-primary text-[32px] animate-spin">progress_activity</span>
      <p class="font-body-md text-body-md text-on-surface-variant"><?= putmio_lang('device_pwa_launch_redirecting') ?></p>
    </div>

    <div id="pwa-launch-browser" class="space-y-5">
      <p class="font-body-md text-body-md text-on-surface-variant"><?= putmio_lang('device_pwa_launch_desc') ?></p>

      <div class="p-4 rounded-lg bg-surface-container-low border border-outline-variant/20 text-center">
        <p class="font-label-md text-label-md text-on-surface-variant mb-2"><?= putmio_lang('device_login_code_label') ?></p>
        <p id="pwa-launch-code" class="font-mono text-3xl font-bold tracking-[0.2em] text-primary select-all"><?= putmio_e($code) ?></p>
      </div>

      <div id="pwa-launch-copied" class="hidden flex items-center gap-2 p-3 rounded-lg bg-success/10 border border-success/20" role="status">
        <span class="material-symbols-outlined text-success text-[20px]">content_paste</span>
        <span class="font-body-md text-body-md text-success"><?= putmio_lang('device_pwa_launch_copied') ?></span>
      </div>

      <a
        id="pwa-launch-open-app"
        href="<?= putmio_e($authorizeUrl) ?>"
        class="auth-btn-primary w-full py-3.5 px-6 flex items-center justify-center gap-2"
      >
        <span class="material-symbols-outlined text-[18px]">install_mobile</span>
        <span><?= putmio_lang('device_pwa_launch_open_app') ?></span>
      </a>

      <p class="font-body-sm text-body-sm text-on-surface-variant/80 text-center"><?= putmio_lang('device_pwa_launch_ios_hint') ?></p>

      <a href="<?= putmio_e($browserUrl) ?>" class="block text-center font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors">
        <?= putmio_lang('device_pwa_launch_browser') ?>
      </a>
    </div>
  </div>
</div>
<?php
$putmioExtra = array_merge($putmioExtra ?? [], [
    'devicePwaLaunch' => [
        'code' => $code,
        'authorizeUrl' => $authorizeUrl,
        'loginNext' => 'authorize-device?code=' . rawurlencode($code),
    ],
]);
$extraScripts = '<script src="' . putmio_e(putmio_asset('public/assets/device-pwa-launch.js')) . '" defer></script>';
?>
