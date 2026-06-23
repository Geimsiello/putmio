<!DOCTYPE html>
<html lang="it" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= putmio_e(putmio_lang('install_title')) ?></title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <?php $appUrl = rtrim(putmio_detect_base_url(), '/'); ?>
  <link rel="icon" href="<?= putmio_e($appUrl) ?>/public/assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="<?= putmio_e($appUrl) ?>/public/assets/favicon.svg">
  <link rel="stylesheet" href="<?= putmio_e(putmio_asset('public/assets/install.css')) ?>">
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            primary: '#c0c1ff',
            'on-primary': '#1000a9',
            'primary-container': '#8083ff',
            'on-primary-container': '#0d0096',
            'primary-fixed-dim': '#c0c1ff',
            secondary: '#ffe083',
            tertiary: '#ffb783',
            surface: '#0b1326',
            'surface-dim': '#0b1326',
            'surface-container': '#171f33',
            'surface-container-low': '#131b2e',
            'surface-container-high': '#222a3d',
            'surface-container-highest': '#2d3449',
            'surface-container-lowest': '#060e20',
            'surface-variant': '#2d3449',
            'on-surface': '#dae2fd',
            'on-surface-variant': '#c7c4d7',
            outline: '#908fa0',
            'outline-variant': '#464554',
            background: '#0b1326',
            error: '#ef4444',
            success: '#10b981',
            warning: '#f59e0b',
          },
          fontFamily: {
            'headline-lg': ['Hanken Grotesk', 'sans-serif'],
            'headline-md': ['Hanken Grotesk', 'sans-serif'],
            'body-lg': ['Hanken Grotesk', 'sans-serif'],
            'body-md': ['Hanken Grotesk', 'sans-serif'],
            'label-md': ['JetBrains Mono', 'monospace'],
            'label-sm': ['JetBrains Mono', 'monospace'],
          },
          fontSize: {
            'headline-lg': ['32px', { lineHeight: '40px', fontWeight: '700' }],
            'headline-md': ['24px', { lineHeight: '32px', fontWeight: '700' }],
            'body-lg': ['18px', { lineHeight: '28px', fontWeight: '400' }],
            'body-md': ['16px', { lineHeight: '24px', fontWeight: '400' }],
            'label-md': ['14px', { lineHeight: '20px', letterSpacing: '0.05em', fontWeight: '500' }],
            'label-sm': ['12px', { lineHeight: '16px', fontWeight: '500' }],
          },
        },
      },
    };
  </script>
</head>
<?php
$currentStep = (int) ($step ?? 1);
$isComplete = $currentStep === 6;
?>
<body class="install-wizard min-h-screen flex items-center justify-center p-4 md:p-6">
  <div class="fixed inset-0 overflow-hidden pointer-events-none -z-10">
    <div class="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] bg-primary/10 rounded-full blur-[120px]"></div>
    <div class="absolute -bottom-[10%] -right-[10%] w-[40%] h-[40%] bg-tertiary/5 rounded-full blur-[120px]"></div>
  </div>

  <main class="relative w-full max-w-[560px] install-glass rounded-xl shadow-2xl overflow-hidden">
    <div class="px-6 md:px-8 pt-6 md:pt-8">
      <?php
      $brandSubtitle = putmio_lang('install_title') . ' — Step ' . $currentStep . '/6';
      $brandAppUrl = $appUrl;
      require putmio_base_path() . '/templates/partials/brand-header.php';
      ?>

      <div class="flex gap-2 h-1.5 w-full mb-6" aria-label="Progresso installazione">
        <?php for ($i = 1; $i <= 6; $i++): ?>
          <?php if ($isComplete): ?>
            <div class="flex-1 rounded-full bg-success shadow-[0_0_8px_rgba(16,185,129,0.4)]"></div>
          <?php elseif ($i < $currentStep): ?>
            <div class="flex-1 rounded-full bg-primary shadow-[0_0_8px_rgba(192,193,255,0.4)]"></div>
          <?php elseif ($i === $currentStep): ?>
            <div class="flex-1 rounded-full bg-primary shadow-[0_0_8px_rgba(192,193,255,0.4)] relative overflow-hidden">
              <?php if ($currentStep === 4): ?>
                <div class="absolute inset-0 install-progress-shimmer"></div>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="flex-1 rounded-full bg-surface-variant/40"></div>
          <?php endif; ?>
        <?php endfor; ?>
      </div>

      <?php if (!empty($error)): ?>
        <div class="mb-6 rounded-xl bg-error/10 border border-error/30 text-error px-4 py-3 font-body-md flex items-start gap-2">
          <span class="material-symbols-outlined text-[18px] shrink-0">error</span>
          <span><?= putmio_e($error) ?></span>
        </div>
      <?php endif; ?>
      <?php if (!empty($success)): ?>
        <div class="mb-6 rounded-xl bg-success/10 border border-success/30 text-success px-4 py-3 font-body-md flex items-start gap-2">
          <span class="material-symbols-outlined filled text-[18px] shrink-0">check_circle</span>
          <span><?= putmio_e($success) ?></span>
        </div>
      <?php endif; ?>
    </div>

    <div class="px-6 md:px-8 pb-6 md:pb-8">
      <?= $content ?>
    </div>
  </main>

  <script src="<?= putmio_e(putmio_asset('public/assets/install.js')) ?>" defer></script>
</body>
</html>
