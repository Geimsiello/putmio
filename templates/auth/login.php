<?php
use PutMio\Auth\Csrf;
use PutMio\Config;

$appUrl = rtrim(Config::get('app.url', putmio_detect_base_url()), '/');
$hasError = !empty($error);
$brandTagline = putmio_lang('tagline');
$brandAppUrl = $appUrl;
$brandUseIcon = false;
?>
<div class="w-full max-w-[400px]">
  <?php require putmio_base_path() . '/templates/partials/brand-header.php'; ?>

  <div class="auth-glass rounded-xl shadow-2xl p-6 md:p-8">
    <div class="mb-6">
      <h1 class="font-headline-md text-headline-md text-on-surface mb-2"><?= putmio_lang('login') ?></h1>
      <div class="h-1 w-12 bg-primary rounded-full" aria-hidden="true"></div>
    </div>

    <?php if ($hasError): ?>
    <div id="login-error" class="mb-6 flex items-center gap-3 p-3 bg-error/10 border border-error/20 rounded-lg" role="alert">
      <span class="material-symbols-outlined text-error text-[20px] shrink-0">error</span>
      <span class="font-body-md text-body-md text-error"><?= putmio_e($error) ?></span>
    </div>
    <?php endif; ?>

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
            <?= $hasError ? 'aria-invalid="true" aria-describedby="login-error"' : 'autofocus' ?>
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

  <div class="mt-10 text-center" aria-hidden="true">
    <div class="flex justify-center gap-6 text-on-surface-variant/40">
      <span class="material-symbols-outlined">movie</span>
      <span class="material-symbols-outlined">tv</span>
      <span class="material-symbols-outlined">library_music</span>
    </div>
  </div>
</div>
