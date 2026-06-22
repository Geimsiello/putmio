<?php /** @var string $csrf */ ?>
<div class="relative w-full aspect-video mb-8 rounded-xl overflow-hidden shadow-inner">
  <div class="absolute inset-0 bg-gradient-to-br from-primary/25 via-surface-container to-tertiary/15"></div>
  <div class="absolute inset-0 flex items-center justify-center">
    <span class="material-symbols-outlined filled text-primary/40 text-7xl">theater_comedy</span>
  </div>
  <div class="absolute inset-0 bg-gradient-to-t from-surface-container/90 to-transparent"></div>
</div>

<div class="space-y-4 mb-10">
  <h2 class="font-headline-lg text-headline-lg text-on-surface tracking-tight"><?= putmio_lang('welcome') ?></h2>
  <p class="font-body-lg text-body-lg text-on-surface-variant leading-relaxed">
    <?= putmio_lang('install_welcome_desc') ?>
  </p>
</div>

<form method="post" class="space-y-4"><?= $csrf ?>
  <button type="submit" class="group relative w-full h-14 install-btn-primary font-headline-md text-[16px] overflow-hidden shadow-[0_4px_20px_rgba(192,193,255,0.25)]">
    <span class="relative z-10"><?= putmio_lang('install_start') ?></span>
    <div class="absolute inset-0 bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
  </button>
  <div class="flex justify-center items-center gap-2 py-2">
    <span class="material-symbols-outlined filled text-success text-[18px]">verified_user</span>
    <span class="font-label-sm text-label-sm text-on-surface-variant/50"><?= putmio_lang('install_secure') ?></span>
  </div>
</form>
