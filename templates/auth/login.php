<?php
use PutMio\Auth\Csrf;
use PutMio\Auth\DeviceLoginService;
use PutMio\Config;

$appUrl = rtrim(Config::get('app.url', putmio_detect_base_url()), '/');
$hasError = !empty($error);
$brandTagline = putmio_lang('tagline');
$brandAppUrl = $appUrl;
$brandUseIcon = false;
$loginMode = putmio_is_tv_user_agent()
    ? 'device'
    : (($_GET['mode'] ?? '') === 'device' ? 'device' : 'email');
?>
<div class="w-full max-w-[400px]">
  <?php require putmio_base_path() . '/templates/partials/brand-header.php'; ?>

  <div class="auth-glass rounded-xl shadow-2xl p-6 md:p-8">
    <div class="mb-6">
      <h1 class="font-headline-md text-headline-md text-on-surface mb-2"><?= putmio_lang('login') ?></h1>
      <div class="h-1 w-12 bg-primary rounded-full" aria-hidden="true"></div>
    </div>

    <div class="flex gap-1 p-1 mb-6 rounded-lg bg-surface-container-low border border-outline-variant/20" role="tablist" aria-label="<?= putmio_e(putmio_lang('login')) ?>">
      <button
        type="button"
        id="login-tab-email"
        role="tab"
        aria-selected="<?= $loginMode === 'email' ? 'true' : 'false' ?>"
        aria-controls="login-panel-email"
        class="login-tab flex-1 py-2.5 px-3 rounded-md font-label-md text-label-md transition-colors <?= $loginMode === 'email' ? 'bg-surface-container-high text-on-surface shadow-sm' : 'text-on-surface-variant hover:text-on-surface' ?>"
      >
        <?= putmio_lang('login_tab_email') ?>
      </button>
      <button
        type="button"
        id="login-tab-device"
        role="tab"
        aria-selected="<?= $loginMode === 'device' ? 'true' : 'false' ?>"
        aria-controls="login-panel-device"
        class="login-tab flex-1 py-2.5 px-3 rounded-md font-label-md text-label-md transition-colors <?= $loginMode === 'device' ? 'bg-surface-container-high text-on-surface shadow-sm' : 'text-on-surface-variant hover:text-on-surface' ?>"
      >
        <?= putmio_lang('login_tab_device') ?>
      </button>
    </div>

    <?php if ($hasError): ?>
    <div id="login-error" class="mb-6 flex items-center gap-3 p-3 bg-error/10 border border-error/20 rounded-lg" role="alert">
      <span class="material-symbols-outlined text-error text-[20px] shrink-0">error</span>
      <span class="font-body-md text-body-md text-error"><?= putmio_e($error) ?></span>
    </div>
    <?php endif; ?>

    <div id="login-panel-email" role="tabpanel" aria-labelledby="login-tab-email" class="<?= $loginMode === 'device' ? 'hidden' : '' ?>">
      <form
        id="login-form"
        method="post"
        action="<?= putmio_e($appUrl) ?>/login"
        class="space-y-5"
        <?= $hasError ? 'aria-describedby="login-error"' : '' ?>
      ><?= Csrf::field() ?>
        <div class="space-y-1.5">
          <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="login-email"><?= putmio_lang('email') ?></label>
          <div class="relative group">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-outline group-focus-within:text-primary transition-colors">
              <span class="material-symbols-outlined text-[20px]">mail</span>
            </div>
            <input
              type="email"
              id="login-email"
              name="email"
              required
              autocomplete="username"
              inputmode="email"
              autocapitalize="none"
              spellcheck="false"
              enterkeyhint="next"
              placeholder="nome@esempio.com"
              <?= $hasError ? 'aria-invalid="true" aria-describedby="login-error"' : ($loginMode === 'email' ? 'autofocus' : '') ?>
              class="auth-input auth-input-icon"
            >
          </div>
        </div>

        <div class="space-y-1.5">
          <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="login-password"><?= putmio_lang('password') ?></label>
          <div class="relative group">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-outline group-focus-within:text-primary transition-colors">
              <span class="material-symbols-outlined text-[20px]">lock</span>
            </div>
            <input
              type="password"
              id="login-password"
              name="password"
              required
              autocomplete="current-password"
              spellcheck="false"
              enterkeyhint="go"
              placeholder="••••••••"
              <?= $hasError ? 'aria-invalid="true" aria-describedby="login-error"' : '' ?>
              class="auth-input auth-input-icon"
            >
          </div>
        </div>

        <div class="flex items-center justify-between gap-3">
          <label class="inline-flex items-center gap-2.5 cursor-pointer select-none" for="login-remember">
            <input
              type="checkbox"
              id="login-remember"
              name="remember"
              value="1"
              class="auth-checkbox"
            >
            <span class="font-label-md text-label-md text-on-surface-variant"><?= putmio_lang('remember_me') ?></span>
          </label>
          <a href="<?= putmio_e($appUrl) ?>/forgot-password" class="font-label-md text-label-md text-primary hover:text-primary-fixed-dim transition-colors border-b border-transparent hover:border-primary-fixed-dim shrink-0">
            <?= putmio_lang('forgot_password') ?>
          </a>
        </div>

        <div class="pt-1">
          <button type="submit" class="auth-btn-primary w-full py-3.5 px-6 flex items-center justify-center gap-2">
            <span><?= putmio_lang('login') ?></span>
            <span class="material-symbols-outlined text-[18px]">login</span>
          </button>
        </div>
      </form>
    </div>

    <div id="login-panel-device" role="tabpanel" aria-labelledby="login-tab-device" class="<?= $loginMode === 'email' ? 'hidden' : '' ?>">
      <div class="text-center space-y-5">
        <p class="font-body-md text-body-md text-on-surface-variant"><?= putmio_lang('device_login_desc') ?></p>

        <div id="device-login-loading" class="flex flex-col items-center gap-3 py-6">
          <span class="material-symbols-outlined text-primary text-[32px] animate-spin">progress_activity</span>
          <span class="font-body-md text-body-md text-on-surface-variant"><?= putmio_lang('device_login_generating') ?></span>
        </div>

        <div id="device-login-error" class="hidden flex items-center gap-3 p-3 bg-error/10 border border-error/20 rounded-lg text-left" role="alert">
          <span class="material-symbols-outlined text-error text-[20px] shrink-0">error</span>
          <span id="device-login-error-text" class="font-body-md text-body-md text-error"></span>
        </div>

        <div id="device-login-ready" class="hidden space-y-5">
          <div class="flex justify-center">
            <div id="device-qr-wrap" class="p-3 rounded-xl bg-surface-container-low border border-outline-variant/30 inline-block">
              <div id="device-qr" class="w-[200px] h-[200px] flex items-center justify-center"></div>
            </div>
          </div>

          <div>
            <p class="font-label-md text-label-md text-on-surface-variant mb-2"><?= putmio_lang('device_login_code_label') ?></p>
            <p id="device-login-code" class="font-mono text-3xl md:text-4xl font-bold tracking-[0.2em] text-primary select-all" aria-live="polite"></p>
          </div>

          <div id="device-login-waiting" class="flex items-center justify-center gap-2 text-on-surface-variant">
            <span class="material-symbols-outlined text-primary text-[20px] animate-spin">progress_activity</span>
            <span class="font-body-md text-body-md"><?= putmio_lang('device_login_waiting') ?></span>
          </div>

          <p class="font-body-sm text-body-sm text-on-surface-variant/70"><?= putmio_lang('device_login_pwa_hint') ?></p>

          <button type="button" id="device-login-refresh" class="auth-btn-secondary w-full py-3 px-6 flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-[18px]">refresh</span>
            <span><?= putmio_lang('device_login_refresh') ?></span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-10 text-center" aria-hidden="true">
    <div class="flex justify-center gap-6 text-on-surface-variant/40">
      <span class="material-symbols-outlined">movie</span>
      <span class="material-symbols-outlined">tv</span>
      <span class="material-symbols-outlined">library_music</span>
    </div>
  </div>
</div>
<?php
$putmioExtra = array_merge($putmioExtra ?? [], [
    'deviceLogin' => [
        'rateLimited' => putmio_lang('device_login_rate_limited'),
        'expired' => putmio_lang('device_login_expired'),
        'denied' => putmio_lang('device_login_denied'),
        'error' => putmio_lang('device_login_error'),
        'completing' => putmio_lang('device_login_completing'),
    ],
]);
$extraScripts = '<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>'
    . '<script src="' . putmio_e($appUrl) . '/public/assets/device-login.js" defer></script>';
?>
