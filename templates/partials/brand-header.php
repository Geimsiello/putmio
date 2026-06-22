<?php
/**
 * Logo PutMio + titolo. Variabili opzionali:
 * @var string|null $brandSubtitle  Sottotitolo monospaced (es. step wizard)
 * @var string|null $brandTagline   Descrizione sotto il logo (es. tagline login)
 * @var string      $brandAppUrl    Base URL app (senza slash finale)
 * @var bool        $brandLinkHome  Se true, logo+nome linkano alla home
 * @var bool        $brandUseIcon   Se false, usa favicon.svg al posto dell'icona Material
 */
$brandSubtitle = $brandSubtitle ?? null;
$brandTagline = $brandTagline ?? null;
$brandAppUrl = $brandAppUrl ?? rtrim(putmio_detect_base_url(), '/');
$brandLinkHome = $brandLinkHome ?? false;
$brandUseIcon = $brandUseIcon ?? true;
$logoUrl = $brandAppUrl . '/public/assets/favicon.svg';
$tag = $brandLinkHome ? 'a' : 'div';
$tagAttrs = $brandLinkHome
    ? ' href="' . putmio_e($brandAppUrl) . '/" class="inline-flex justify-center items-center gap-2.5 group transition-opacity hover:opacity-90"'
    : ' class="flex justify-center items-center gap-2.5 group cursor-default"';
?>
<header class="text-center <?= !empty($brandTagline) ? 'mb-8' : 'mb-6' ?> space-y-2">
  <<?= $tag ?><?= $tagAttrs ?>>
    <?php if ($brandUseIcon): ?>
    <div class="w-10 h-10 bg-primary rounded-xl flex items-center justify-center shadow-lg shadow-primary/20 group-hover:scale-110 transition-transform duration-300">
      <span class="material-symbols-outlined filled text-on-primary-container text-[22px]" style="font-variation-settings: 'FILL' 1;">play_circle</span>
    </div>
    <?php else: ?>
    <img
      src="<?= putmio_e($logoUrl) ?>"
      alt=""
      width="40"
      height="40"
      class="w-10 h-10 rounded-xl shadow-lg shadow-primary/20 transition-transform duration-300 group-hover:scale-110"
    >
    <?php endif; ?>
    <span class="font-headline-md text-headline-md font-extrabold text-primary tracking-tight">PutMio</span>
  </<?= $tag ?>>
  <?php if (!empty($brandTagline)): ?>
    <p class="font-body-md text-body-md text-on-surface-variant/80 max-w-xs mx-auto leading-relaxed"><?= putmio_e($brandTagline) ?></p>
  <?php endif; ?>
  <?php if (!empty($brandSubtitle)): ?>
    <p class="font-label-md text-label-md text-on-surface-variant/70 uppercase tracking-widest"><?= putmio_e($brandSubtitle) ?></p>
  <?php endif; ?>
</header>
