<?php
/** @var array<int, list<array<string, mixed>>> $episodesBySeason */
/** @var array<int, array<string, mixed>> $episodeProgress */
use PutMio\Config;
$appUrl = rtrim(Config::get('app.url'), '/');
?>
<div class="space-y-8 border-t border-outline-variant/20 pt-8">
  <h2 class="text-headline-md font-headline-md text-on-surface"><?= putmio_lang('episodes') ?></h2>
  <?php foreach ($episodesBySeason as $season => $episodes): ?>
  <section>
    <h3 class="font-label-md text-label-md text-on-surface-variant uppercase tracking-widest mb-4">
      <?= putmio_lang('season') ?> <?= (int) $season ?>
    </h3>
    <div class="grid gap-2">
      <?php foreach ($episodes as $episode): ?>
      <?php
        $episodeId = (int) $episode['id'];
        $epProgress = $episodeProgress[$episodeId] ?? null;
        $epHasProgress = $epProgress && empty($epProgress['completed']) && ($epProgress['position_sec'] ?? 0) > 0;
        $epWatched = $epProgress && !empty($epProgress['completed']);
      ?>
      <a
        href="<?= putmio_e($appUrl) ?>/play?id=<?= $episodeId ?>"
        class="group flex items-center gap-4 rounded-xl border border-outline-variant/20 bg-surface-container/40 px-4 py-3 hover:border-primary/30 hover:bg-surface-container-high/60 transition-all"
      >
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary font-label-md text-label-md">
          <?= (int) ($episode['episode_number'] ?? 0) ?>
        </span>
        <div class="min-w-0 flex-1">
          <p class="font-body-md text-on-surface truncate group-hover:text-primary transition-colors"><?= putmio_e($episode['title']) ?></p>
          <?php if ($epHasProgress): ?>
          <p class="font-label-sm text-label-sm text-primary mt-0.5"><?= putmio_lang('resume') ?></p>
          <?php elseif ($epWatched): ?>
          <p class="font-label-sm text-label-sm text-success mt-0.5"><?= putmio_lang('mark_watched') ?></p>
          <?php endif; ?>
        </div>
        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary transition-colors" style="font-variation-settings: 'FILL' 1;">play_circle</span>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>
</div>
