<?php
/** @var array $media */
/** @var array|null $progress */
/** @var array|null $series */
/** @var string $displayTitle */
/** @var string $subtitle */
/** @var string $synopsis */
/** @var int|null $year */
/** @var string|null $runtimeLabel */
/** @var int $detailMediaId */
/** @var array{prev: ?array, next: ?array} $adjacent */
/** @var array{ext: ?string, codec: ?string, resolution: ?string} $techLabels */
/** @var bool $audioWarning */
/** @var bool $mp4Available */
/** @var bool $isOriginalNonMp4 */
/** @var bool $putioConnected */
/** @var bool $showSourcePicker */
/** @var string $playbackFormat */
/** @var string $streamMime */
/** @var string $posterUrl */
/** @var string $playerPreload */
use PutMio\Config;

$appUrl = rtrim(Config::get('app.url'), '/');
$mediaId = (int) $media['id'];
$hasProgress = $progress && empty($progress['completed']) && ($progress['position_sec'] ?? 0) > 0;
$isWatched = $progress && !empty($progress['completed']);
$canRestart = $progress && (($progress['position_sec'] ?? 0) > 0 || !empty($progress['completed']));
$prevEpisode = $adjacent['prev'] ?? null;
$nextEpisode = $adjacent['next'] ?? null;
$hasTechMeta = !empty($techLabels['ext']) || !empty($techLabels['codec']) || !empty($techLabels['resolution']);
$playerPreload = putmio_player_preload($playerPreload ?? null);
?>
<a href="<?= putmio_e($appUrl) ?>/media?id=<?= $detailMediaId ?>" id="player-back-link" class="inline-flex items-center gap-2 text-on-surface-variant hover:text-primary transition-colors font-label-md text-label-md mb-6 group">
  <span class="material-symbols-outlined text-lg group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
  <?= putmio_lang('back_to_detail') ?>
</a>

<div class="max-w-[1200px] mx-auto space-y-6 relative">
  <section class="relative group">
    <div class="putmio-player-wrap w-full relative bg-surface-container-lowest rounded-xl overflow-hidden border border-outline-variant/30 shadow-2xl">
      <?php if (!empty($posterUrl)): ?>
      <div class="putmio-player-poster-bg" style="background-image: url('<?= putmio_e($posterUrl) ?>')" aria-hidden="true"></div>
      <?php endif; ?>
      <video
        id="putmio-player"
        class="video-js vjs-big-play-centered"
        controls
        preload="<?= putmio_e($playerPreload) ?>"
        playsinline
        <?php if (!empty($posterUrl)): ?>poster="<?= putmio_e($posterUrl) ?>"<?php endif; ?>
      ></video>
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-3">
      <?php if (!empty($showSourcePicker)): ?>
      <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-sm text-on-surface-variant">movie</span>
        <label for="player-source-select" class="font-label-sm text-label-sm text-on-surface-variant"><?= putmio_lang('player_source_label') ?></label>
        <select
          id="player-source-select"
          class="bg-surface-container-high border border-outline-variant/40 rounded-lg px-3 py-1.5 font-label-sm text-label-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40"
        >
          <option value="hls"<?= ($playbackFormat ?? 'hls') === 'hls' ? ' selected' : '' ?>><?= putmio_lang('player_source_hls') ?></option>
          <?php if (!empty($mp4Available)): ?>
          <option value="mp4"<?= ($playbackFormat ?? '') === 'mp4' ? ' selected' : '' ?>><?= putmio_lang('player_source_mp4') ?></option>
          <?php endif; ?>
          <?php if (!empty($isOriginalNonMp4)): ?>
          <option value="original"<?= ($playbackFormat ?? '') === 'original' ? ' selected' : '' ?>><?= putmio_lang('player_source_original') ?></option>
          <?php endif; ?>
        </select>
      </div>
      <?php elseif (!empty($putioConnected)): ?>
      <div class="flex items-center gap-2 text-on-surface-variant">
        <span class="material-symbols-outlined text-sm text-primary">check_circle</span>
        <span class="font-label-sm text-label-sm"><?= putmio_lang('player_source_hls_active') ?></span>
      </div>
      <?php elseif (!empty($mp4Available)): ?>
      <div class="flex items-center gap-2 text-on-surface-variant">
        <span class="material-symbols-outlined text-sm text-primary">check_circle</span>
        <span class="font-label-sm text-label-sm"><?= putmio_lang('player_source_mp4_active') ?></span>
      </div>
      <?php endif; ?>
      <div id="player-audio-tracks" class="hidden flex items-center gap-2">
        <span class="material-symbols-outlined text-sm text-on-surface-variant">headphones</span>
        <label for="player-audio-select" class="font-label-sm text-label-sm text-on-surface-variant"><?= putmio_lang('player_audio_track_label') ?></label>
        <select
          id="player-audio-select"
          class="bg-surface-container-high border border-outline-variant/40 rounded-lg px-3 py-1.5 font-label-sm text-label-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40"
        ></select>
      </div>
      <div id="player-subtitle-controls" class="flex flex-wrap items-center gap-2">
        <button type="button" id="player-subtitle-manage" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-outline-variant/40 bg-surface-container-high font-label-sm text-label-sm text-on-surface hover:border-primary/40 hover:text-primary transition-colors">
          <span class="material-symbols-outlined text-sm">subtitles</span>
          <?= putmio_e(putmio_lang('subtitles_manage')) ?>
        </button>
        <span class="font-label-sm text-label-sm text-on-surface-variant"><?= putmio_e(putmio_lang('player_subtitle_native_hint')) ?></span>
      </div>
      <div id="player-subtitle-offset" class="hidden flex flex-wrap items-center gap-2 w-full">
        <span class="font-label-sm text-label-sm text-on-surface-variant"><?= putmio_e(putmio_lang('subtitles_offset_label')) ?></span>
        <button type="button" data-pm-offset="-500" class="px-2 py-1 rounded-lg border border-outline-variant/40 text-label-sm font-label-sm text-on-surface hover:bg-surface-variant/30">−0.5s</button>
        <button type="button" data-pm-offset="-100" class="px-2 py-1 rounded-lg border border-outline-variant/40 text-label-sm font-label-sm text-on-surface hover:bg-surface-variant/30">−0.1s</button>
        <input type="number" id="player-subtitle-offset-input" step="0.1" class="w-24 bg-surface-container-high border border-outline-variant/40 rounded-lg px-2 py-1 font-label-sm text-label-sm text-on-surface text-center" value="0">
        <span class="font-label-sm text-label-sm text-on-surface-variant">s</span>
        <button type="button" data-pm-offset="100" class="px-2 py-1 rounded-lg border border-outline-variant/40 text-label-sm font-label-sm text-on-surface hover:bg-surface-variant/30">+0.1s</button>
        <button type="button" data-pm-offset="500" class="px-2 py-1 rounded-lg border border-outline-variant/40 text-label-sm font-label-sm text-on-surface hover:bg-surface-variant/30">+0.5s</button>
        <button type="button" id="player-subtitle-offset-reset" class="px-2 py-1 rounded-lg border border-outline-variant/40 text-label-sm font-label-sm text-on-surface-variant hover:text-primary"><?= putmio_e(putmio_lang('subtitles_offset_reset')) ?></button>
      </div>
    </div>
    <?php if (!empty($audioWarning)): ?>
    <div class="mt-3 flex items-start gap-2 rounded-lg border border-secondary/40 bg-secondary-container/20 px-4 py-3">
      <span class="material-symbols-outlined text-secondary shrink-0 text-lg">info</span>
      <p class="font-label-sm text-label-sm text-on-surface-variant">
        <?= putmio_lang('player_audio_codec_warning') ?>
      </p>
    </div>
    <?php endif; ?>
    <div class="mt-3 flex items-center gap-2">
      <span class="material-symbols-outlined text-sm text-primary">cloud_download</span>
      <p class="font-label-sm text-label-sm text-on-surface-variant"><?= putmio_lang('streaming_from_putio') ?></p>
    </div>
  </section>

  <section class="bg-surface-container rounded-xl p-6 border border-outline-variant/20 shadow-sm relative overflow-hidden group">
    <div class="absolute inset-0 putmio-shimmer pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity" aria-hidden="true"></div>
    <div class="relative z-10">
      <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4 mb-4">
        <div class="space-y-1 min-w-0">
          <h1 class="font-headline-lg text-headline-lg text-on-surface"><?= putmio_e($displayTitle) ?></h1>
          <p class="font-label-md text-label-md text-primary tracking-wide"><?= putmio_e($subtitle) ?></p>
        </div>
        <div class="text-left sm:text-right shrink-0">
          <?php if ($runtimeLabel || $year): ?>
          <span class="font-label-sm text-label-sm text-on-surface-variant block">
            <?= putmio_e(trim(($runtimeLabel ?? '') . ($runtimeLabel && $year ? ' · ' : '') . ($year ? (string) (int) $year : ''))) ?>
          </span>
          <?php endif; ?>
          <?php if ($series): ?>
          <a href="<?= putmio_e($appUrl) ?>/media?id=<?= (int) $series['id'] ?>" class="font-label-sm text-label-sm text-primary hover:underline mt-2 inline-block">
            <?= putmio_lang('go_to_series_page') ?>
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($synopsis !== ''): ?>
      <p class="font-body-md text-body-md text-on-surface-variant max-w-4xl leading-relaxed"><?= nl2br(putmio_e($synopsis)) ?></p>
      <?php endif; ?>
    </div>
  </section>

  <section class="flex flex-wrap gap-4 pt-2" data-player-actions="<?= $mediaId ?>">
    <?php if ($hasProgress): ?>
    <button
      type="button"
      id="player-resume"
      class="pm-btn-primary px-8 py-3 text-base shadow-lg shadow-primary-container/20"
    >
      <span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">play_arrow</span>
      <?= putmio_lang('resume') ?>
    </button>
    <?php else: ?>
    <button
      type="button"
      id="player-play"
      class="pm-btn-primary px-8 py-3 text-base shadow-lg shadow-primary-container/20"
    >
      <span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">play_arrow</span>
      <?= putmio_lang('play') ?>
    </button>
    <?php endif; ?>

    <?php if (!$isWatched): ?>
    <button
      type="button"
      data-pm-watch-action="complete"
      class="inline-flex items-center gap-2 px-6 py-3 rounded-lg border border-outline text-on-surface font-headline-md hover:bg-surface-container-highest hover:border-primary transition-all active:scale-95"
    >
      <span class="material-symbols-outlined">check_circle</span>
      <?= putmio_lang('mark_watched') ?>
    </button>
    <?php endif; ?>

    <?php if ($canRestart): ?>
    <button
      type="button"
      id="player-restart"
      data-pm-watch-action="reset"
      class="inline-flex items-center gap-2 px-6 py-3 rounded-lg border border-outline text-on-surface font-headline-md hover:bg-surface-container-highest hover:border-primary transition-all active:scale-95"
    >
      <span class="material-symbols-outlined">replay</span>
      <?= putmio_lang('restart_from_beginning') ?>
    </button>
    <?php endif; ?>

    <?php if ($prevEpisode || $nextEpisode): ?>
    <div class="flex flex-wrap gap-2 w-full sm:w-auto sm:ml-auto">
      <?php if ($prevEpisode): ?>
      <a
        href="<?= putmio_e($appUrl) ?>/play?id=<?= (int) $prevEpisode['id'] ?>"
        class="inline-flex items-center gap-2 px-6 py-3 rounded-lg border border-outline text-on-surface font-headline-md hover:bg-surface-container-highest transition-all active:scale-95"
      >
        <span class="material-symbols-outlined">skip_previous</span>
        <?= putmio_lang('previous_episode') ?>
      </a>
      <?php endif; ?>
      <?php if ($nextEpisode): ?>
      <a
        href="<?= putmio_e($appUrl) ?>/play?id=<?= (int) $nextEpisode['id'] ?>"
        class="inline-flex items-center gap-2 px-6 py-3 rounded-lg border border-outline text-on-surface font-headline-md hover:bg-surface-container-highest transition-all active:scale-95"
      >
        <span class="material-symbols-outlined">skip_next</span>
        <?= putmio_lang('next_episode') ?>
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </section>

  <?php if ($hasTechMeta): ?>
  <footer class="mt-8 pt-6 border-t border-outline-variant/10">
    <div class="flex flex-col md:flex-row justify-between items-center gap-4">
      <div class="flex flex-wrap items-center gap-6">
        <?php if (!empty($techLabels['ext'])): ?>
        <div class="flex items-center gap-2 text-on-surface-variant">
          <span class="material-symbols-outlined text-sm">description</span>
          <span class="font-label-sm text-label-sm uppercase">File <?= putmio_e($techLabels['ext']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($techLabels['codec'])): ?>
        <div class="flex items-center gap-2 text-on-surface-variant">
          <span class="material-symbols-outlined text-sm">high_quality</span>
          <span class="font-label-sm text-label-sm uppercase">Codec <?= putmio_e($techLabels['codec']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($techLabels['resolution'])): ?>
        <div class="flex items-center gap-2 text-on-surface-variant">
          <span class="material-symbols-outlined text-sm">hd</span>
          <span class="font-label-sm text-label-sm uppercase"><?= putmio_e($techLabels['resolution']) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </footer>
  <?php endif; ?>
</div>

<?php
$subtitleModalMediaId = $mediaId;
$subtitleModalAutoOpen = false;
require putmio_base_path() . '/templates/partials/subtitle-modal.php';
?>

<div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none" aria-hidden="true">
  <div class="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] bg-primary-container/10 blur-[120px] rounded-full"></div>
  <div class="absolute -bottom-[10%] -right-[10%] w-[40%] h-[40%] bg-tertiary-container/5 blur-[120px] rounded-full"></div>
</div>
