<?php
use PutMio\Auth\Csrf;
use PutMio\Auth\DeviceLoginService;
use PutMio\Config;

$appUrl = rtrim(Config::get('app.url', putmio_detect_base_url()), '/');
$hasRequest = is_array($request) && !empty($request);
$codeInvalid = $code !== '' && !$hasRequest && empty($success);
$deviceLabel = $hasRequest ? DeviceLoginService::deviceLabel($request['user_agent'] ?? null) : '';
$clientIp = $hasRequest ? (string) ($request['client_ip'] ?? '') : '';
$brandTagline = putmio_lang('tagline');
$brandAppUrl = $appUrl;
$brandUseIcon = false;
?>
<div class="w-full max-w-[400px]">
  <?php require putmio_base_path() . '/templates/partials/brand-header.php'; ?>

  <div class="auth-glass rounded-xl shadow-2xl p-6 md:p-8">
    <div class="mb-6">
      <h1 class="font-headline-md text-headline-md text-on-surface mb-2"><?= putmio_lang('device_authorize_title') ?></h1>
      <div class="h-1 w-12 bg-primary rounded-full" aria-hidden="true"></div>
    </div>

    <?php if (!empty($success)): ?>
    <div class="mb-6 flex items-center gap-3 p-3 bg-success/10 border border-success/20 rounded-lg" role="status">
      <span class="material-symbols-outlined text-success text-[20px] shrink-0">check_circle</span>
      <span class="font-body-md text-body-md text-success"><?= putmio_e($success) ?></span>
    </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <div class="mb-6 flex items-center gap-3 p-3 bg-error/10 border border-error/20 rounded-lg" role="alert">
      <span class="material-symbols-outlined text-error text-[20px] shrink-0">error</span>
      <span class="font-body-md text-body-md text-error"><?= putmio_e($error) ?></span>
    </div>
    <?php elseif ($codeInvalid): ?>
    <div class="mb-6 flex items-center gap-3 p-3 bg-error/10 border border-error/20 rounded-lg" role="alert">
      <span class="material-symbols-outlined text-error text-[20px] shrink-0">error</span>
      <span class="font-body-md text-body-md text-error"><?= putmio_e(putmio_lang('device_authorize_invalid')) ?></span>
    </div>
    <?php endif; ?>

    <div id="device-pwa-browser-hint" class="hidden mb-6 p-4 rounded-lg bg-primary/10 border border-primary/20" role="note">
      <div class="flex items-start gap-3">
        <span class="material-symbols-outlined text-primary text-[22px] shrink-0">install_mobile</span>
        <div class="space-y-2">
          <p class="font-body-md text-body-md text-on-surface"><?= putmio_lang('device_pwa_in_browser_hint') ?></p>
          <?php if (!empty($authorizeUrl)): ?>
          <a href="<?= putmio_e($authorizeUrl) ?>" class="inline-flex items-center gap-1.5 font-label-md text-label-md text-primary hover:text-primary-fixed-dim transition-colors">
            <span><?= putmio_lang('device_pwa_launch_open_app') ?></span>
            <span class="material-symbols-outlined text-[16px]">open_in_new</span>
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($hasRequest): ?>
    <div class="space-y-5">
      <div class="p-4 rounded-lg bg-surface-container-low border border-outline-variant/20 space-y-3">
        <div class="flex items-start gap-3">
          <span class="material-symbols-outlined text-primary text-[24px] shrink-0">devices</span>
          <div>
            <p class="font-label-md text-label-md text-on-surface-variant"><?= putmio_lang('device_authorize_request') ?></p>
            <p class="font-body-lg text-body-lg text-on-surface font-medium"><?= putmio_e($deviceLabel) ?></p>
            <?php if ($clientIp !== ''): ?>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">IP: <?= putmio_e($clientIp) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <p class="font-mono text-xl tracking-[0.15em] text-primary text-center py-2"><?= putmio_e($code) ?></p>
      </div>

      <div class="flex flex-col gap-3">
        <button type="button" id="device-approve-btn" data-code="<?= putmio_e($code) ?>" class="auth-btn-primary w-full py-3.5 px-6 flex items-center justify-center gap-2">
          <span class="material-symbols-outlined text-[18px]">verified_user</span>
          <span><?= putmio_lang('device_authorize_confirm') ?></span>
        </button>
        <button type="button" id="device-deny-btn" data-code="<?= putmio_e($code) ?>" class="auth-btn-secondary w-full py-3 px-6 flex items-center justify-center gap-2">
          <span><?= putmio_lang('cancel') ?></span>
        </button>
      </div>
    </div>
    <?php else: ?>
    <div class="space-y-5">
      <p class="font-body-md text-body-md text-on-surface-variant"><?= putmio_lang('device_authorize_desc') ?></p>

      <form id="device-authorize-form" class="space-y-4">
        <div class="space-y-1.5">
          <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="device-authorize-code"><?= putmio_lang('device_login_code_label') ?></label>
          <input
            type="text"
            id="device-authorize-code"
            name="code"
            value="<?= putmio_e($code) ?>"
            required
            autocomplete="one-time-code"
            autocapitalize="characters"
            spellcheck="false"
            maxlength="9"
            inputmode="text"
            placeholder="ABCD-EFGH"
            class="auth-input text-center font-mono text-xl tracking-[0.15em] uppercase"
            autofocus
          >
        </div>
        <button type="submit" class="auth-btn-primary w-full py-3.5 px-6 flex items-center justify-center gap-2">
          <span><?= putmio_lang('next') ?></span>
          <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
        </button>
      </form>
    </div>
    <?php endif; ?>

    <div class="mt-6 pt-6 border-t border-outline-variant/20 text-center">
      <a href="<?= putmio_e($appUrl) ?>/" class="font-label-md text-label-md text-primary hover:text-primary-fixed-dim transition-colors">
        <?= putmio_lang('back') ?>
      </a>
    </div>
  </div>
</div>
<?php
$putmioExtra = array_merge($putmioExtra ?? [], [
    'deviceAuthorize' => [
        'invalidCode' => putmio_lang('device_authorize_invalid'),
        'approved' => putmio_lang('device_authorize_success'),
        'denied' => putmio_lang('device_authorize_denied'),
        'error' => putmio_lang('device_authorize_error'),
        'authorizeUrl' => $authorizeUrl ?? '',
    ],
]);
$extraScripts = '<script src="' . putmio_e($appUrl) . '/public/assets/device-authorize.js" defer></script>';
?>
