<?php
use PutMio\Auth\Csrf;
/** @var string $csrf */
?>
<div class="mb-8">
  <h2 class="font-headline-md text-headline-md text-on-surface mb-2"><?= putmio_lang('install_admin') ?></h2>
  <p class="font-body-md text-body-md text-on-surface-variant"><?= putmio_lang('install_admin_desc') ?></p>
</div>

<form method="post" class="space-y-6"><?= $csrf ?>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="space-y-1.5">
      <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="display_name"><?= putmio_lang('display_name') ?></label>
      <input class="install-input" id="display_name" name="display_name" placeholder="es. Renato" required>
    </div>
    <div class="space-y-1.5">
      <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="email"><?= putmio_lang('email') ?></label>
      <input class="install-input" type="email" id="email" name="email" placeholder="admin@esempio.it" required>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="space-y-1.5">
      <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="password"><?= putmio_lang('password') ?></label>
      <input class="install-input border-outline-variant/30" type="password" id="password" name="password" minlength="10" placeholder="••••••••" required>
    </div>
    <div class="space-y-1.5">
      <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="password_confirm"><?= putmio_lang('password_confirm') ?></label>
      <input class="install-input border-outline-variant/30" type="password" id="password_confirm" name="password_confirm" minlength="10" placeholder="••••••••" required>
    </div>
  </div>

  <details class="install-details group bg-surface-container-low rounded-2xl border border-outline-variant/20 overflow-hidden">
    <summary class="flex items-center justify-between p-4 cursor-pointer hover:bg-surface-variant/30 transition-colors">
      <div class="flex items-center gap-3">
        <span class="material-symbols-outlined text-primary">mail</span>
        <span class="font-label-md text-label-md text-on-surface"><?= putmio_lang('install_smtp_optional') ?></span>
      </div>
      <span class="material-symbols-outlined text-on-surface-variant group-open:rotate-180 transition-transform">expand_more</span>
    </summary>
    <div class="p-5 space-y-4 border-t border-outline-variant/10">
      <label class="flex items-center gap-2 font-body-md text-on-surface-variant cursor-pointer">
        <input type="checkbox" name="smtp_enable" value="1" class="rounded border-outline-variant text-primary-container focus:ring-primary">
        <?= putmio_lang('install_smtp_enable') ?>
      </label>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2 space-y-1.5">
          <label class="font-label-sm text-label-sm text-on-surface-variant">Host</label>
          <input class="install-input py-2 text-sm" name="smtp_host" placeholder="smtp.gmail.com">
        </div>
        <div class="space-y-1.5">
          <label class="font-label-sm text-label-sm text-on-surface-variant">Porta</label>
          <input class="install-input py-2 text-sm" name="smtp_port" placeholder="587">
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-1.5">
          <label class="font-label-sm text-label-sm text-on-surface-variant">Utente</label>
          <input class="install-input py-2 text-sm" name="smtp_user">
        </div>
        <div class="space-y-1.5">
          <label class="font-label-sm text-label-sm text-on-surface-variant">Password</label>
          <input class="install-input py-2 text-sm" type="password" name="smtp_pass">
        </div>
      </div>
      <div class="space-y-1.5">
        <label class="font-label-sm text-label-sm text-on-surface-variant"><?= putmio_lang('install_smtp_from') ?></label>
        <input class="install-input py-2 text-sm" name="smtp_from" placeholder="noreply@tuodominio.it" type="email">
      </div>
    </div>
  </details>

  <div class="pt-2">
    <button type="submit" class="w-full install-btn-primary py-4 rounded-2xl shadow-xl shadow-primary/10 flex items-center justify-center gap-3 group">
      <span class="font-headline-md text-[18px]"><?= putmio_lang('install_finish') ?></span>
      <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">rocket_launch</span>
    </button>
    <p class="text-center mt-4 font-label-sm text-label-sm text-outline">
      <?= putmio_lang('install_terms_hint') ?>
    </p>
  </div>
</form>

<div class="mt-8 flex justify-start">
  <a href="?step=4" class="flex items-center gap-2 font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors">
    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
    <?= putmio_lang('back') ?>
  </a>
</div>
