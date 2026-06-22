<?php
use PutMio\Config;

$appUrl = rtrim(Config::get('app.url'), '/');
$adminCrumbLabel = 'Dashboard';
$adminPageTitle = 'Dashboard Amministrazione';
$adminPageDescription = 'Benvenuto nel cuore pulsante di PutMio. Monitora e gestisci la tua istanza privata.';
require putmio_base_path() . '/templates/partials/admin-header.php';

$bandwidthPct = $activeCount > 0 ? min(95, 35 + ($activeCount * 15)) : 0;
?>
<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
  <a href="<?= putmio_e($appUrl) ?>/admin/impostazioni" class="bento-card glass-panel rounded-2xl p-6 flex flex-col justify-between group">
    <div class="flex justify-between items-start mb-6">
      <div class="p-3 bg-primary/10 text-primary rounded-xl group-hover:bg-primary group-hover:text-on-primary transition-colors">
        <span class="material-symbols-outlined">settings</span>
      </div>
      <span class="material-symbols-outlined text-on-surface-variant/30">arrow_outward</span>
    </div>
    <div>
      <h2 class="text-headline-md font-headline-md mb-2"><?= putmio_lang('settings') ?></h2>
      <p class="text-on-surface-variant text-body-md opacity-80">Configura sistema, put.io e TMDB</p>
    </div>
  </a>

  <a href="<?= putmio_e($appUrl) ?>/admin/classificazione" class="bento-card glass-panel rounded-2xl p-6 flex flex-col justify-between group relative overflow-hidden">
    <div class="flex justify-between items-start mb-6">
      <div class="p-3 bg-tertiary/10 text-tertiary rounded-xl group-hover:bg-tertiary group-hover:text-on-tertiary transition-colors">
        <span class="material-symbols-outlined">folder</span>
      </div>
      <?php if ($unclassified > 0): ?>
      <div class="bg-tertiary/20 text-tertiary px-3 py-1 rounded-full font-label-md text-label-sm border border-tertiary/30">
        <?= (int) $unclassified ?>
      </div>
      <?php endif; ?>
    </div>
    <div>
      <h2 class="text-headline-md font-headline-md mb-2">Da classificare</h2>
      <p class="text-on-surface-variant text-body-md opacity-80">Titoli in attesa di metadati</p>
    </div>
  </a>

  <a href="<?= putmio_e($appUrl) ?>/admin/utenti" class="bento-card glass-panel rounded-2xl p-6 flex flex-col justify-between group">
    <div class="flex justify-between items-start mb-6">
      <div class="p-3 bg-success/10 text-success rounded-xl group-hover:bg-success group-hover:text-background transition-colors">
        <span class="material-symbols-outlined">group</span>
      </div>
      <span class="material-symbols-outlined text-on-surface-variant/30">arrow_outward</span>
    </div>
    <div>
      <h2 class="text-headline-md font-headline-md mb-2">Utenti e inviti</h2>
      <p class="text-on-surface-variant text-body-md opacity-80">Gestisci i membri della famiglia e i permessi</p>
    </div>
  </a>

  <a href="<?= putmio_e($appUrl) ?>/admin/streaming" class="bento-card bg-surface-container-high border border-primary/20 rounded-2xl p-6 flex flex-col group hover:border-primary/40 transition-colors">
    <div class="flex items-center gap-3 mb-6">
      <div class="p-2 bg-primary/20 text-primary rounded-lg">
        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">signal_cellular_alt</span>
      </div>
      <span class="font-label-md text-label-md text-primary uppercase tracking-widest">Streaming</span>
    </div>
    <div class="space-y-4">
      <div class="flex justify-between items-end border-b border-surface-variant/30 pb-3">
        <div>
          <p class="text-[40px] leading-none font-bold text-primary"><?= (int) $activeCount ?></p>
          <p class="text-on-surface-variant text-label-md font-label-md">Stream attivi</p>
        </div>
        <div class="text-right">
          <p class="text-headline-md font-headline-md text-on-surface leading-none font-bold">
            <?= putmio_format_bytes((int) $todayBytes) ?>
          </p>
          <p class="text-on-surface-variant text-label-md font-label-md">Banda oggi</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <div class="flex-1 h-1 bg-surface-variant rounded-full overflow-hidden">
          <div class="h-full bg-primary-container transition-all duration-500" style="width:<?= (int) $bandwidthPct ?>%"></div>
        </div>
        <?php if ($activeCount > 0): ?>
        <span class="text-[10px] text-primary font-label-sm font-label-md">LIVE</span>
        <?php endif; ?>
      </div>
    </div>
  </a>
</section>

<section class="glass-panel rounded-3xl overflow-hidden mb-12">
  <div class="px-6 md:px-8 py-5 md:py-6 border-b border-surface-variant/30 flex flex-wrap justify-between items-center gap-3 bg-surface-container-high/40">
    <div class="flex items-center gap-3">
      <span class="material-symbols-outlined text-primary">sensors</span>
      <h2 class="text-headline-md font-headline-md">Stream in tempo reale</h2>
    </div>
    <?php if ($activeCount > 0): ?>
    <div class="px-3 py-1.5 bg-surface-container-lowest rounded-lg flex items-center gap-2 border border-surface-variant/50">
      <div class="w-2 h-2 rounded-full bg-success animate-pulse"></div>
      <span class="text-label-md font-label-md text-on-surface-variant">Live Update</span>
    </div>
    <?php endif; ?>
  </div>

  <?php if (empty($activeStreams)): ?>
  <div class="px-6 md:px-8 py-12 text-center text-on-surface-variant text-body-md">
    Nessuno stream in corso al momento.
  </div>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table class="w-full text-left min-w-[640px]">
      <thead>
        <tr class="text-on-surface-variant/60 font-label-md text-label-md border-b border-surface-variant/10">
          <th class="px-6 md:px-8 py-4 font-medium">Utente</th>
          <th class="px-6 md:px-8 py-4 font-medium">Titolo</th>
          <th class="px-6 md:px-8 py-4 font-medium">Durata sessione</th>
          <th class="px-6 md:px-8 py-4 font-medium">Banda</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-surface-variant/10">
        <?php foreach ($activeStreams as $stream):
          $name = (string) ($stream['display_name'] ?? 'Utente');
          $initial = strtoupper(mb_substr($name, 0, 1));
          $poster = $catalog->posterWebPath($stream['poster_local_path'] ?? null, $stream['poster_url'] ?? null);
          $bitrate = putmio_stream_bitrate_label((int) ($stream['bytes_sent'] ?? 0), $stream['started_at'] ?? null);
        ?>
        <tr class="hover:bg-surface-variant/10 transition-colors">
          <td class="px-6 md:px-8 py-4">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-primary/20 text-primary flex items-center justify-center font-bold shrink-0"><?= putmio_e($initial) ?></div>
              <div class="min-w-0">
                <p class="text-body-md font-medium text-on-surface truncate"><?= putmio_e($name) ?></p>
                <p class="text-label-sm font-label-sm text-on-surface-variant/60"><?= putmio_e($stream['client_ip'] ?? '') ?></p>
              </div>
            </div>
          </td>
          <td class="px-6 md:px-8 py-4">
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-8 h-12 rounded bg-surface-container-highest overflow-hidden shrink-0">
                <img src="<?= putmio_e($poster) ?>" alt="" class="w-full h-full object-cover" loading="lazy">
              </div>
              <span class="text-body-md text-on-surface truncate"><?= putmio_e($stream['title'] ?? 'Titolo sconosciuto') ?></span>
            </div>
          </td>
          <td class="px-6 md:px-8 py-4 text-on-surface-variant font-label-md"><?= putmio_e(putmio_session_duration_label($stream['started_at'] ?? null)) ?></td>
          <td class="px-6 md:px-8 py-4">
            <span class="text-primary font-label-md"><?= putmio_e($bitrate) ?></span>
            <span class="text-[10px] bg-primary/10 text-primary px-1.5 py-0.5 rounded border border-primary/20 ml-2"><?= putmio_format_bytes((int) ($stream['bytes_sent'] ?? 0)) ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="px-6 md:px-8 py-4 bg-surface-container-lowest/50 text-center">
    <a href="<?= putmio_e($appUrl) ?>/admin/streaming" class="text-label-md font-label-md text-primary hover:underline">Vedi tutti gli stream attivi</a>
  </div>
  <?php endif; ?>
</section>
