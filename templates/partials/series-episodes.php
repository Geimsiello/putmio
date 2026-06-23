<?php
/** @var array<int, list<array<string, mixed>>> $episodesBySeason */
/** @var array<int, array<string, mixed>> $episodeProgress */
/** @var array<string, mixed> $media */
/** @var \PutMio\CatalogService $catalog */
use PutMio\Config;
$appUrl = rtrim(Config::get('app.url'), '/');
$seasons = array_keys($episodesBySeason);
$firstSeason = $seasons[0] ?? 1;
?>
<section class="pm-series-episodes mt-10 lg:mt-12" data-pm-series-episodes>
  <div class="pm-series-episodes__header">
    <div class="pm-series-episodes__tabs custom-scrollbar" role="tablist" aria-label="<?= putmio_e(putmio_lang('episodes')) ?>">
      <?php foreach ($episodesBySeason as $season => $episodes): ?>
      <button
        type="button"
        role="tab"
        class="pm-series-episodes__tab<?= (int) $season === (int) $firstSeason ? ' is-active' : '' ?>"
        data-pm-season-tab="<?= (int) $season ?>"
        aria-selected="<?= (int) $season === (int) $firstSeason ? 'true' : 'false' ?>"
        aria-controls="pm-season-panel-<?= (int) $season ?>"
        id="pm-season-tab-<?= (int) $season ?>"
      >
        <?= putmio_e(putmio_lang('season')) ?> <?= (int) $season ?>
      </button>
      <?php endforeach; ?>
    </div>
    <?php foreach ($episodesBySeason as $season => $episodes): ?>
    <?php
      $watchedInSeason = 0;
      foreach ($episodes as $episode) {
          $epProgress = $episodeProgress[(int) $episode['id']] ?? null;
          if ($epProgress && !empty($epProgress['completed'])) {
              $watchedInSeason++;
          }
      }
      $seasonTotal = count($episodes);
    ?>
    <span
      class="pm-series-episodes__badge<?= (int) $season === (int) $firstSeason ? '' : ' hidden' ?>"
      data-pm-season-badge="<?= (int) $season ?>"
    >
      <?= putmio_e(putmio_lang('episodes_watched', [
          'watched' => (string) $watchedInSeason,
          'total' => (string) $seasonTotal,
      ])) ?>
    </span>
    <?php endforeach; ?>
  </div>

  <div class="pm-series-episodes__panels">
    <?php foreach ($episodesBySeason as $season => $episodes): ?>
    <div
      role="tabpanel"
      id="pm-season-panel-<?= (int) $season ?>"
      class="pm-series-episodes__panel<?= (int) $season === (int) $firstSeason ? '' : ' hidden' ?>"
      data-pm-season-panel="<?= (int) $season ?>"
      aria-labelledby="pm-season-tab-<?= (int) $season ?>"
    >
      <div class="pm-episode-list">
        <?php foreach ($episodes as $episode): ?>
        <?php
          $episodeId = (int) $episode['id'];
          $episodeNum = (int) ($episode['episode_number'] ?? 0);
          $epProgress = $episodeProgress[$episodeId] ?? null;
          $epWatched = $epProgress && !empty($epProgress['completed']);
          $epHasProgress = $epProgress && empty($epProgress['completed']) && ($epProgress['position_sec'] ?? 0) > 0;
          $durationSec = (int) ($episode['duration_sec'] ?? 0);
          if ($durationSec <= 0 && !empty($epProgress['duration_sec'])) {
              $durationSec = (int) $epProgress['duration_sec'];
          }
          $runtimeShort = putmio_format_runtime_short($durationSec > 0 ? $durationSec : null);
          $progressPct = 0;
          if ($epHasProgress && $durationSec > 0) {
              $progressPct = min(99, max(1, (int) round(((int) $epProgress['position_sec'] / $durationSec) * 100)));
          }
          $thumb = $catalog->playerArtworkWebPath($episode, $media);
          $cardClass = 'pm-episode-card';
          if ($epWatched) {
              $cardClass .= ' pm-episode-card--watched';
          } elseif ($epHasProgress) {
              $cardClass .= ' pm-episode-card--active';
          }
          $synopsis = trim((string) ($episode['synopsis'] ?? ''));
        ?>
        <a href="<?= putmio_e($appUrl) ?>/play?id=<?= $episodeId ?>" class="<?= $cardClass ?>">
          <span class="pm-episode-card__status" aria-hidden="true">
            <?php if ($epWatched): ?>
            <span class="pm-episode-card__check">
              <span class="material-symbols-outlined">check</span>
            </span>
            <?php else: ?>
            <span class="pm-episode-card__code"><?= sprintf('E%02d', $episodeNum) ?></span>
            <?php endif; ?>
          </span>

          <span class="pm-episode-card__thumb">
            <img src="<?= putmio_e($thumb) ?>" alt="" loading="lazy" class="pm-episode-card__thumb-img">
            <?php if ($epHasProgress): ?>
            <span class="pm-episode-card__thumb-progress" style="width: <?= $progressPct ?>%"></span>
            <?php endif; ?>
          </span>

          <span class="pm-episode-card__body">
            <span class="pm-episode-card__meta">
              <span class="pm-episode-card__number"><?= sprintf('E%02d', $episodeNum) ?></span>
              <?php if ($epHasProgress): ?>
              <span class="pm-episode-card__badge"><?= putmio_e(putmio_lang('episode_in_progress')) ?></span>
              <?php endif; ?>
            </span>
            <span class="pm-episode-card__title"><?= putmio_e(putmio_episode_card_title($episode)) ?></span>
            <?php if ($synopsis !== ''): ?>
            <span class="pm-episode-card__synopsis"><?= putmio_e($synopsis) ?></span>
            <?php endif; ?>
          </span>

          <span class="pm-episode-card__aside">
            <?php if ($epHasProgress): ?>
            <span class="pm-episode-card__percent"><?= $progressPct ?>%</span>
            <?php endif; ?>
            <?php if ($runtimeShort !== null): ?>
            <span class="pm-episode-card__duration"><?= putmio_e($runtimeShort) ?></span>
            <?php endif; ?>
          </span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<script>
(function () {
  const root = document.querySelector('[data-pm-series-episodes]');
  if (!root) return;

  const tabs = root.querySelectorAll('[data-pm-season-tab]');
  const panels = root.querySelectorAll('[data-pm-season-panel]');
  const badges = root.querySelectorAll('[data-pm-season-badge]');

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      const season = tab.getAttribute('data-pm-season-tab');
      if (!season) return;

      tabs.forEach(function (item) {
        const active = item.getAttribute('data-pm-season-tab') === season;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-selected', active ? 'true' : 'false');
      });

      panels.forEach(function (panel) {
        panel.classList.toggle('hidden', panel.getAttribute('data-pm-season-panel') !== season);
      });

      badges.forEach(function (badge) {
        badge.classList.toggle('hidden', badge.getAttribute('data-pm-season-badge') !== season);
      });
    });
  });
})();
</script>
