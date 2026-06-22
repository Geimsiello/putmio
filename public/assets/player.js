(function () {
  if (!window.PUTMIO || !window.videojs) return;

  const playerEl = document.getElementById('putmio-player');
  if (!playerEl) return;

  let player = videojs.getPlayer('putmio-player');
  if (!player) {
    player = videojs('putmio-player', { responsive: true, fluid: true });
  }

  const startAt = window.PUTMIO.startAt || 0;
  const knownDuration = window.PUTMIO.durationSec || 0;
  const actionsRoot = document.querySelector('[data-player-actions]');
  const sourceSelect = document.getElementById('player-source-select');
  const audioRoot = document.getElementById('player-audio-tracks');
  const audioSelect = document.getElementById('player-audio-select');
  const resumeBtn = document.getElementById('player-resume');
  const playBtn = document.getElementById('player-play');
  const restartBtn = document.getElementById('player-restart');
  let started = false;
  let loading = false;
  let leaving = false;
  let playbackFormat = window.PUTMIO.playbackFormat || 'mp4';

  function defaultStartAt() {
    if (resumeBtn && startAt > 0) {
      return startAt;
    }
    return 0;
  }

  function buildStreamUrl(format) {
    const url = new URL(window.PUTMIO.streamUrl, window.location.origin);
    url.searchParams.set('format', format);
    return url.toString();
  }

  function setSource() {
    const url = buildStreamUrl(playbackFormat);
    player.src({
      src: url,
      type: 'video/mp4'
    });
  }

  function mediaDuration() {
    const d = player.duration();
    if (d && isFinite(d) && d > 0) {
      return Math.floor(d);
    }
    return knownDuration > 0 ? knownDuration : 0;
  }

  function saveProgress() {
    if (leaving) return;
    const pos = Math.floor(player.currentTime() || 0);
    const dur = mediaDuration();
    if (!pos || !dur) return;
    const body = new URLSearchParams({
      _csrf: window.PUTMIO.csrf,
      media_id: String(window.PUTMIO.mediaId),
      position_sec: String(pos),
      duration_sec: String(dur)
    });
    fetch(window.PUTMIO.baseUrl + '/api/watch-progress', {
      method: 'POST',
      body: body,
      keepalive: true,
      credentials: 'same-origin'
    }).catch(function () {});
  }

  function saveProgressBeacon() {
    const pos = Math.floor(player.currentTime() || 0);
    const dur = mediaDuration();
    if (!pos || !dur || !navigator.sendBeacon) return;
    const body = new URLSearchParams({
      _csrf: window.PUTMIO.csrf,
      media_id: String(window.PUTMIO.mediaId),
      position_sec: String(pos),
      duration_sec: String(dur)
    });
    navigator.sendBeacon(
      window.PUTMIO.baseUrl + '/api/watch-progress',
      new Blob([body.toString()], { type: 'application/x-www-form-urlencoded' })
    );
  }

  function teardownPlayer() {
    if (leaving) return;
    leaving = true;
    saveProgressBeacon();
    try {
      player.pause();
      player.reset();
    } catch (e) {}
  }

  function refreshAudioTracks() {
    if (!audioSelect || !audioRoot) return;

    const tracks = player.audioTracks && player.audioTracks();
    if (!tracks || tracks.length <= 1) {
      audioRoot.classList.add('hidden');
      audioSelect.innerHTML = '';
      return;
    }

    audioRoot.classList.remove('hidden');
    audioSelect.innerHTML = '';

    for (let i = 0; i < tracks.length; i++) {
      const track = tracks[i];
      const option = document.createElement('option');
      option.value = String(i);
      const label = track.label || track.language || ('Traccia ' + (i + 1));
      option.textContent = label;
      option.selected = !!track.enabled;
      audioSelect.appendChild(option);
    }
  }

  function startPlayback(at) {
    refreshAudioTracks();
    if (at > 0) {
      player.currentTime(at);
    }
    player.play().catch(function () {});
  }

  function waitForReady(at) {
    if (player.readyState() >= 1) {
      loading = false;
      startPlayback(at);
      return;
    }

    let done = false;
    function onReady() {
      if (done) return;
      done = true;
      loading = false;
      player.off('loadedmetadata', onReady);
      player.off('canplay', onReady);
      startPlayback(at);
    }

    player.on('loadedmetadata', onReady);
    player.on('canplay', onReady);
  }

  function begin(at) {
    if (loading) {
      return;
    }

    if (started && player.currentSrc()) {
      startPlayback(at);
      return;
    }

    started = true;
    loading = true;
    setSource();
    waitForReady(at);
  }

  function restartWithFormat(format) {
    const at = Math.floor(player.currentTime() || 0) || defaultStartAt();
    playbackFormat = format;
    started = false;
    loading = false;
    player.pause();
    begin(at);
  }

  function hookBigPlayButton() {
    const bigPlay = player.getChild('BigPlayButton');
    if (!bigPlay || !bigPlay.el()) {
      return;
    }

    bigPlay.el().addEventListener('click', function (event) {
      if (started || loading) {
        return;
      }
      event.preventDefault();
      event.stopImmediatePropagation();
      begin(defaultStartAt());
    }, true);
  }

  player.ready(function () {
    hookBigPlayButton();
  });

  player.on('error', function () {
    loading = false;
    const err = player.error();
    if (err) {
      console.error('[PutMio] Errore player:', err.code, err.message);
    }
  });

  player.on('play', function () {
    if (!started && !loading) {
      player.pause();
      begin(defaultStartAt());
    }
  });

  if (player.audioTracks) {
    const tracks = player.audioTracks();
    tracks.addEventListener('change', refreshAudioTracks);
    tracks.addEventListener('addtrack', refreshAudioTracks);
  }

  if (audioSelect) {
    audioSelect.addEventListener('change', function () {
      const tracks = player.audioTracks && player.audioTracks();
      if (!tracks) return;
      const index = parseInt(audioSelect.value, 10);
      for (let i = 0; i < tracks.length; i++) {
        tracks[i].enabled = i === index;
      }
    });
  }

  if (sourceSelect) {
    sourceSelect.addEventListener('change', function () {
      restartWithFormat(sourceSelect.value === 'original' ? 'original' : 'mp4');
    });
  }

  if (resumeBtn) {
    resumeBtn.addEventListener('click', function () {
      begin(startAt);
    });
  }

  if (playBtn) {
    playBtn.addEventListener('click', function () {
      begin(0);
    });
  }

  if (restartBtn) {
    restartBtn.addEventListener('click', async function () {
      const body = new URLSearchParams({
        _csrf: window.PUTMIO.csrf,
        media_id: String(window.PUTMIO.mediaId),
        action: 'reset'
      });
      restartBtn.disabled = true;
      try {
        await fetch(window.PUTMIO.baseUrl + '/api/watch-progress', { method: 'POST', body });
        started = false;
        loading = false;
        begin(0);
      } catch (e) {
        restartBtn.disabled = false;
      }
    });
  }

  if (!resumeBtn && !playBtn) {
    begin(startAt > 30 ? 0 : startAt);
  } else if (!resumeBtn && playBtn && startAt < 30) {
    begin(0);
  }

  if (actionsRoot) {
    actionsRoot.addEventListener('click', async function (evt) {
      const btn = evt.target.closest('[data-pm-watch-action]');
      if (!btn || btn.id === 'player-restart') return;
      const action = btn.getAttribute('data-pm-watch-action');
      const body = new URLSearchParams({
        _csrf: window.PUTMIO.csrf,
        media_id: String(window.PUTMIO.mediaId),
        action: action
      });
      btn.disabled = true;
      try {
        await fetch(window.PUTMIO.baseUrl + '/api/watch-progress', { method: 'POST', body });
        location.reload();
      } catch (e) {
        btn.disabled = false;
      }
    });
  }

  let tick = 0;
  player.on('timeupdate', function () {
    if (leaving) return;
    tick++;
    if (tick % 6 === 0) saveProgress();
  });
  player.on('pause', function () {
    if (!leaving) saveProgress();
  });

  const backLink = document.getElementById('player-back-link');
  if (backLink) {
    backLink.addEventListener('click', function () {
      teardownPlayer();
    });
  }

  window.addEventListener('pagehide', teardownPlayer);

  const nextEpisode = window.PUTMIO.nextEpisode;
  const playerLabels = window.PUTMIO.playerLabels || {};
  const NEXT_SHOW_SEC = 30;
  let nextToastEl = null;
  let nextToastDismissed = false;
  let navigatedNext = false;

  function formatAutoPlayLabel(seconds) {
    const template = playerLabels.autoPlayIn || 'Tra :seconds s';
    return template.replace(':seconds', String(seconds));
  }

  function goToNextEpisode() {
    if (navigatedNext || !nextEpisode || !nextEpisode.playUrl) {
      return;
    }
    navigatedNext = true;
    teardownPlayer();
    window.location.href = nextEpisode.playUrl;
  }

  function ensureNextToast() {
    if (nextToastEl || !nextEpisode) {
      return nextToastEl;
    }

    const wrap = document.querySelector('.putmio-player-wrap');
    if (!wrap) {
      return null;
    }

    const el = document.createElement('div');
    el.className = 'pm-next-episode';
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    el.hidden = true;

    const codeHtml = nextEpisode.code
      ? '<p class="pm-next-episode__code"></p>'
      : '';
    el.innerHTML =
      '<button type="button" class="pm-next-episode__dismiss" aria-label="">' +
        '<span class="material-symbols-outlined" aria-hidden="true">close</span>' +
      '</button>' +
      '<p class="pm-next-episode__kicker"></p>' +
      codeHtml +
      '<p class="pm-next-episode__title"></p>' +
      '<div class="pm-next-episode__actions">' +
        '<button type="button" class="pm-next-episode__play">' +
          '<span class="material-symbols-outlined" aria-hidden="true">play_arrow</span>' +
          '<span class="pm-next-episode__play-label"></span>' +
        '</button>' +
        '<span class="pm-next-episode__countdown"></span>' +
      '</div>';

    el.querySelector('.pm-next-episode__kicker').textContent = playerLabels.nextUp || 'Prossimo episodio';
    if (nextEpisode.code) {
      el.querySelector('.pm-next-episode__code').textContent = nextEpisode.code;
    }
    el.querySelector('.pm-next-episode__title').textContent = nextEpisode.title || '';
    el.querySelector('.pm-next-episode__play-label').textContent = playerLabels.playNow || 'Riproduci ora';
    el.querySelector('.pm-next-episode__dismiss').setAttribute(
      'aria-label',
      playerLabels.dismiss || 'Chiudi'
    );

    el.querySelector('.pm-next-episode__dismiss').addEventListener('click', function () {
      nextToastDismissed = true;
      el.hidden = true;
    });
    el.querySelector('.pm-next-episode__play').addEventListener('click', goToNextEpisode);

    wrap.appendChild(el);
    nextToastEl = el;
    return el;
  }

  function updateNextToast(remainingSec) {
    const el = ensureNextToast();
    if (!el || nextToastDismissed) {
      return;
    }

    const seconds = Math.max(1, Math.ceil(remainingSec));
    el.hidden = false;
    el.querySelector('.pm-next-episode__countdown').textContent = formatAutoPlayLabel(seconds);
  }

  function hideNextToast() {
    if (nextToastEl && !nextToastDismissed) {
      nextToastEl.hidden = true;
    }
  }

  if (nextEpisode) {
    player.on('timeupdate', function () {
      if (nextToastDismissed || navigatedNext || leaving) {
        return;
      }

      const dur = mediaDuration();
      if (!dur) {
        return;
      }

      const remaining = dur - (player.currentTime() || 0);
      if (remaining <= NEXT_SHOW_SEC && remaining > 0.5) {
        updateNextToast(remaining);
      } else if (remaining > NEXT_SHOW_SEC) {
        hideNextToast();
      }
    });

    player.on('ended', function () {
      if (!nextToastDismissed) {
        goToNextEpisode();
      }
    });
  }

  let subtitleTrackEl = null;
  let subtitleOffsetMs = window.PUTMIO.offsetMs || 0;
  let subtitleBaseCues = [];

  function findSubtitleMeta(id, list) {
    if (!id || !list) return null;
    for (let i = 0; i < list.length; i++) {
      if (list[i].id === id) return list[i];
    }
    return null;
  }

  function removeSubtitleTrack() {
    if (subtitleTrackEl && subtitleTrackEl.parentNode) {
      subtitleTrackEl.parentNode.removeChild(subtitleTrackEl);
    }
    subtitleTrackEl = null;
    subtitleBaseCues = [];
    const tracks = player.remoteTextTracks ? player.remoteTextTracks() : null;
    if (tracks) {
      for (let i = tracks.length - 1; i >= 0; i--) {
        player.removeRemoteTextTrack(tracks[i]);
      }
    }
  }

  function applyCueOffset(track, offsetSec) {
    if (!track || !track.cues) return;
    for (let i = 0; i < track.cues.length; i++) {
      const base = subtitleBaseCues[i];
      if (!base) continue;
      track.cues[i].startTime = Math.max(0, base.start + offsetSec);
      track.cues[i].endTime = Math.max(track.cues[i].startTime, base.end + offsetSec);
    }
  }

  function captureBaseCues(track) {
    subtitleBaseCues = [];
    if (!track || !track.cues) return;
    for (let i = 0; i < track.cues.length; i++) {
      subtitleBaseCues.push({
        start: track.cues[i].startTime,
        end: track.cues[i].endTime,
      });
    }
  }

  function activateSubtitleTrack(track) {
    const tracks = player.textTracks();
    for (let i = 0; i < tracks.length; i++) {
      tracks[i].mode = tracks[i] === track ? 'showing' : 'disabled';
    }
  }

  function loadSubtitleTrack(subtitleId, offsetMs, list) {
    removeSubtitleTrack();
    if (!subtitleId) return;

    const meta = findSubtitleMeta(subtitleId, list || window.PUTMIO.availableSubtitles || []);
    if (!meta || !meta.serveUrl) return;

    subtitleOffsetMs = offsetMs || 0;
    const offsetSec = subtitleOffsetMs / 1000;

    subtitleTrackEl = document.createElement('track');
    subtitleTrackEl.kind = 'subtitles';
    subtitleTrackEl.src = meta.serveUrl;
    subtitleTrackEl.srclang = meta.language || 'und';
    subtitleTrackEl.label = meta.label || 'Subtitles';
    subtitleTrackEl.default = true;

    subtitleTrackEl.addEventListener('load', function () {
      const track = subtitleTrackEl.track;
      if (!track) return;
      captureBaseCues(track);
      applyCueOffset(track, offsetSec);
      activateSubtitleTrack(track);
    });

    player.el().appendChild(subtitleTrackEl);
  }

  window.PutMioPlayerSubtitles = {
    apply: function (subtitleId, offsetMs, list) {
      loadSubtitleTrack(subtitleId, offsetMs, list);
    },
  };

  if (window.PUTMIO.activeSubtitleId) {
    player.ready(function () {
      loadSubtitleTrack(
        window.PUTMIO.activeSubtitleId,
        window.PUTMIO.offsetMs || 0,
        window.PUTMIO.availableSubtitles || []
      );
    });
  }
})();
