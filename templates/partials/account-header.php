<?php
/** @var string $accountCrumbLabel */
/** @var string $accountPageTitle */
/** @var string|null $accountPageDescription */
/** @var bool $accountTitleAccent */
$accountPageDescription = $accountPageDescription ?? null;
$accountTitleAccent = $accountTitleAccent ?? false;
?>
<header class="mb-10">
  <nav class="flex items-center gap-2 mb-4" aria-label="Breadcrumb">
    <span class="text-on-surface-variant/60 font-label-md text-label-sm"><?= putmio_e(putmio_lang('account')) ?></span>
    <span class="material-symbols-outlined text-label-sm text-on-surface-variant/40">chevron_right</span>
    <span class="text-primary font-label-md text-label-sm"><?= putmio_e($accountCrumbLabel) ?></span>
  </nav>
  <div>
    <h1 class="text-headline-lg font-headline-lg text-on-surface tracking-tight">
      <?php if ($accountTitleAccent): ?>
      <span class="relative inline-block">
        <?= putmio_e($accountPageTitle) ?>
        <span class="absolute -bottom-1 left-0 w-14 h-0.5 bg-primary rounded-full" aria-hidden="true"></span>
      </span>
      <?php else: ?>
      <?= putmio_e($accountPageTitle) ?>
      <?php endif; ?>
    </h1>
    <?php if ($accountPageDescription): ?>
    <p class="text-on-surface-variant mt-1 text-body-md"><?= putmio_e($accountPageDescription) ?></p>
    <?php endif; ?>
  </div>
</header>
