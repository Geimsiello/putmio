<?php /** @var string $csrf */ ?>
<form method="post" id="install-run-form"><?= $csrf ?>
  <div id="install-run-idle">
    <div class="space-y-2 mb-8 text-center">
      <h2 class="font-headline-lg text-headline-lg text-on-surface"><?= putmio_lang('install_run_title') ?></h2>
      <p class="font-body-md text-body-md text-on-surface-variant"><?= putmio_lang('install_run_desc') ?></p>
    </div>

    <div class="space-y-3 mb-8">
      <div class="flex justify-between items-end">
        <span class="font-label-md text-label-md text-primary"><?= putmio_lang('install_run_ready') ?></span>
        <span class="font-label-md text-label-md text-on-surface font-bold">0%</span>
      </div>
      <div class="h-4 w-full bg-surface-container-lowest rounded-full overflow-hidden border border-outline-variant/20">
        <div class="h-full w-0 bg-primary rounded-full"></div>
      </div>
    </div>

    <div class="flex items-start gap-3 p-4 bg-primary-container/10 rounded-lg border border-primary-container/20 mb-8">
      <span class="material-symbols-outlined text-primary shrink-0">info</span>
      <p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
        <?= putmio_lang('install_run_info') ?>
      </p>
    </div>

    <button type="submit" class="w-full install-btn-primary py-4 font-headline-md text-[16px] flex items-center justify-center gap-2 shadow-lg">
      <?= putmio_lang('install_run_action') ?>
      <span class="material-symbols-outlined">database</span>
    </button>
  </div>

  <div id="install-run-busy" class="hidden space-y-8">
    <div class="text-center space-y-2">
      <h2 class="font-headline-lg text-headline-lg text-on-surface"><?= putmio_lang('install_run_title') ?></h2>
      <p class="font-body-md text-body-md text-on-surface-variant flex items-center justify-center gap-2">
        <span class="flex h-2 w-2 relative">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
        </span>
        <?= putmio_lang('install_run_processing') ?>
      </p>
    </div>

    <div class="space-y-3">
      <div class="flex justify-between items-end">
        <span class="font-label-md text-label-md text-primary"><?= putmio_lang('install_run_progress') ?></span>
        <span class="font-label-md text-label-md text-on-surface font-bold animate-pulse">…</span>
      </div>
      <div class="h-4 w-full bg-surface-container-lowest rounded-full overflow-hidden border border-outline-variant/20">
        <div class="h-full w-2/3 bg-primary relative install-progress-shimmer">
          <div class="absolute inset-0 install-progress-shimmer opacity-50"></div>
        </div>
      </div>
    </div>

    <div class="space-y-2">
      <label class="font-label-sm text-label-sm text-on-surface-variant/60 uppercase tracking-widest"><?= putmio_lang('install_run_logs') ?></label>
      <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/20 p-5 font-label-md text-label-md h-48 overflow-y-auto">
        <div class="space-y-2">
          <div class="flex items-center gap-2 text-on-surface">
            <span class="text-primary-fixed-dim">➜</span>
            <span class="opacity-90"><?= putmio_lang('install_run_log_active') ?></span>
            <span class="install-terminal-cursor"></span>
          </div>
        </div>
      </div>
    </div>

    <div class="flex items-start gap-3 p-4 bg-primary-container/10 rounded-lg border border-primary-container/20">
      <span class="material-symbols-outlined text-primary shrink-0">info</span>
      <p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
        <?= putmio_lang('install_run_info') ?>
      </p>
    </div>
  </div>
</form>
