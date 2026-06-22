<?php use PutMio\Config; $login = rtrim(Config::get('app.url', putmio_detect_base_url()), '/') . '/login'; ?>
<div class="text-center flex flex-col items-center py-4">
  <div class="install-success-pop mb-8 relative">
    <div class="absolute inset-0 bg-success/20 blur-2xl rounded-full"></div>
    <div class="w-24 h-24 bg-success/10 border-2 border-success/30 rounded-full flex items-center justify-center relative">
      <span class="material-symbols-outlined filled text-success text-[56px]">check_circle</span>
    </div>
  </div>

  <h2 class="font-headline-lg text-headline-lg text-on-surface mb-4 leading-tight"><?= putmio_lang('install_complete') ?></h2>
  <p class="font-body-lg text-body-lg text-on-surface-variant mb-10 max-w-sm mx-auto">
    PutMio è pronto. Accedi e collega <span class="text-primary font-bold">put.io</span> dalle impostazioni admin per iniziare lo streaming.
  </p>

  <a href="<?= putmio_e($login) ?>" class="group relative w-full inline-flex items-center justify-center gap-3 bg-primary py-5 px-8 rounded-2xl font-body-md text-on-primary font-bold transition-all duration-200 hover:scale-[1.02] hover:shadow-[0_0_30px_rgba(192,193,255,0.4)] active:scale-95">
    <span><?= putmio_lang('login') ?></span>
    <span class="material-symbols-outlined transition-transform group-hover:translate-x-1">arrow_forward</span>
  </a>
</div>
