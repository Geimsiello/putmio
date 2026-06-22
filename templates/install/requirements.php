<?php /** @var array $requirements */ /** @var string $csrf */ ?>
<div class="space-y-1 mb-6">
  <h2 class="font-headline-md text-headline-md text-on-surface"><?= putmio_lang('install_requirements') ?></h2>
  <p class="font-body-md text-body-md text-on-surface-variant"><?= putmio_lang('install_requirements_desc') ?></p>
</div>

<div class="grid gap-2 mb-8">
  <?php foreach ($requirements['checks'] as $check): ?>
    <div class="install-requirement-row flex items-center justify-between p-3 rounded-lg transition-colors border border-transparent">
      <div class="flex items-center gap-3 min-w-0">
        <span class="font-body-md text-on-surface"><?= putmio_e($check['label']) ?></span>
        <?php if ($check['label'] === 'PHP 7.4+'): ?>
          <span class="font-label-sm text-label-sm text-on-surface-variant bg-surface-container px-2 py-0.5 rounded shrink-0"><?= putmio_e($check['message']) ?></span>
        <?php elseif ($check['message'] !== 'OK' && $check['message'] !== 'Mancante'): ?>
          <span class="font-label-sm text-label-sm text-on-surface-variant shrink-0"><?= putmio_e($check['message']) ?></span>
        <?php endif; ?>
      </div>
      <?php if ($check['ok']): ?>
        <span class="material-symbols-outlined filled text-success shrink-0">check_circle</span>
      <?php else: ?>
        <span class="material-symbols-outlined text-error shrink-0">cancel</span>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="flex justify-between items-center pt-4 border-t border-outline-variant/20 -mx-2 px-2">
  <a href="?step=1" class="text-on-surface-variant font-label-md text-label-md hover:text-on-surface transition-colors flex items-center gap-1">
    <span class="material-symbols-outlined text-[18px]">chevron_left</span>
    <?= putmio_lang('back') ?>
  </a>
  <form method="post"><?= $csrf ?>
    <button type="submit" class="install-btn-primary font-label-md text-label-md px-10 py-3 flex items-center gap-2 group shadow-lg shadow-primary/20 disabled:opacity-40 disabled:pointer-events-none" <?= $requirements['all_ok'] ? '' : 'disabled' ?>>
      <?= putmio_lang('next') ?>
      <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
    </button>
  </form>
</div>
