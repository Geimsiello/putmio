(function () {
  if (!window.PUTMIO || !window.videojs) return;

  const playerEl = document.getElementById('putmio-player');
  if (!playerEl) return;

  const tvMode = !!(window.PUTMIO && window.PUTMIO.tvMode);
  const tvDevice = !!(window.PUTMIO && window.PUTMIO.isTvDevice);
  const tvFullscreenEnabled = tvMode && tvDevice;
  const playerOptions = {
    responsive: true,
    fluid: true,
    aspectRatio: '16:9',
    controlBar: {
      skipButtons: {
        forward: 10,
        backward: 10
      },
      volumePanel: {
        inline: false
      }
    }
  };
  if (tvMode) {
    playerOptions.userActions = { hotkeys: false };
    playerOptions.enableDocumentPictureInPicture = false;
  }

  let player = videojs.getPlayer('putmio-player');
  if (!player) {
    player = videojs('putmio-player', playerOptions);
  }

  function syncPlayerAspectRatio() {
    const vw = player.videoWidth();
    const vh = player.videoHeight();
    if (vw > 0 && vh > 0) {
      player.aspectRatio(vw + ':' + vh);
    }
  }

  const startAt = window.PUTMIO.startAt || 0;
  const knownDuration = window.PUTMIO.durationSec || 0;
  const playerWrap = document.querySelector('.putmio-player-wrap');
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
  let playbackFormat = window.PUTMIO.playbackFormat || 'hls';
  const playerPreload = window.PUTMIO.playerPreload || 'none';
  let errorRetries = 0;
  const MAX_ERROR_RETRIES = 2;
  const playerLabels = window.PUTMIO.playerLabels || {};
  let tvFullscreenDone = false;
  let tvFullscreenAttempts = 0;
  let tvImmersiveActive = false;
  let tvLastKeyStamp = { code: 0, at: 0 };

  function setPlayerLoading(isLoading) {
    if (playerWrap) {
      playerWrap.classList.toggle('putmio-player-wrap--loading', isLoading);
    }
    const shell = document.querySelector('.putmio-player-tv');
    if (shell) {
      shell.classList.toggle('putmio-player-tv--loading', isLoading);
    }
  }

  function enterTvImmersive() {
    document.documentElement.classList.add('putmio-tv-player-immersive');
    const shell = document.querySelector('.putmio-player-tv');
    if (shell) {
      shell.classList.remove('putmio-player-tv--idle');
    }
  }

  function exitTvImmersive() {
    document.documentElement.classList.remove('putmio-tv-player-immersive');
    tvImmersiveActive = false;
    const shell = document.querySelector('.putmio-player-tv');
    if (shell) {
      shell.classList.add('putmio-player-tv--idle');
    }
    setPlayerLoading(false);
  }

  /** Fullscreen solo su dispositivi TV reali e quando la riproduzione è effettivamente partita. */
  function activateTvFullscreenWhenReady() {
    if (!tvFullscreenEnabled || tvImmersiveActive) return;
    try {
      if (player.paused()) return;
    } catch (e) {
      return;
    }
    tvImmersiveActive = true;
    setPlayerLoading(false);
    enterTvImmersive();
    scheduleTvFullscreenRetry();
  }

  function requestFullscreenOn(el) {
    if (!el) return null;
    const fn = el.requestFullscreen
      || el.webkitRequestFullscreen
      || el.webkitEnterFullscreen
      || el.mozRequestFullScreen
      || el.msRequestFullscreen;
    if (!fn) return null;
    try {
      const result = fn.call(el);
      return result && typeof result.then === 'function' ? result : Promise.resolve();
    } catch (e) {
      return Promise.reject(e);
    }
  }

  function enterTvFullscreen() {
    if (tvFullscreenDone || document.fullscreenElement) {
      tvFullscreenDone = true;
      return;
    }
    if (tvFullscreenAttempts > 16) return;
    tvFullscreenAttempts++;

    function markDone() {
      tvFullscreenDone = true;
      try {
        const root = player.el();
        if (root) {
          root.setAttribute('tabindex', '0');
          root.focus();
        }
      } catch (e) { /* ignore */ }
    }

    const root = player.el();
    const video = root ? root.querySelector('video') : null;
    const wrap = document.querySelector('.putmio-player-wrap');
    const chain = [
      function () { return player.requestFullscreen ? player.requestFullscreen() : null; },
      function () { return requestFullscreenOn(video); },
      function () { return requestFullscreenOn(root); },
      function () { return requestFullscreenOn(wrap); },
      function () { return requestFullscreenOn(document.documentElement); }
    ];

    function tryNext(index) {
      if (index >= chain.length) return;
      let req;
      try {
        req = chain[index]();
      } catch (e) {
        tryNext(index + 1);
        return;
      }
      if (req && typeof req.then === 'function') {
        req.then(markDone).catch(function () {
          tryNext(index + 1);
        });
        return;
      }
      if (document.fullscreenElement) {
        markDone();
        return;
      }
      tryNext(index + 1);
    }

    tryNext(0);
  }

  function scheduleTvFullscreenRetry() {
    if (!tvImmersiveActive) return;
    if (tvFullscreenDone || document.fullscreenElement) {
      tvFullscreenDone = true;
      return;
    }
    if (tvFullscreenAttempts > 16) return;
    enterTvFullscreen();
    window.setTimeout(function () {
      if (!tvFullscreenDone && !document.fullscreenElement && tvFullscreenAttempts <= 16) {
        scheduleTvFullscreenRetry();
      }
    }, 400);
  }

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

  function streamMimeFor(format) {
    if (format === 'hls') {
      return 'application/x-mpegURL';
    }
    if (format === 'original') {
      return window.PUTMIO.streamMime || 'video/mp4';
    }
    return 'video/mp4';
  }

  function setSource() {
    const url = new URL(buildStreamUrl(playbackFormat), window.location.origin);
    url.searchParams.set('_', String(Date.now()));
    player.src({
      src: url.toString(),
      type: streamMimeFor(playbackFormat)
    });
  }

  function shouldPrefetchSource() {
    return playerPreload === 'metadata' || playerPreload === 'auto';
  }

  function prefetchSource() {
    if (!shouldPrefetchSource() || started || loading || player.currentSrc()) {
      return;
    }
    setSource();
  }

  function begin(at, forceNewSource) {
    if (loading) {
      return;
    }

    const hasSource = !!player.currentSrc();

    if (started && hasSource && !forceNewSource) {
      startPlayback(at);
      return;
    }

    started = true;
    loading = true;
    setPlayerLoading(true);
    if (!hasSource || forceNewSource) {
      setSource();
    }
    waitForReady(at);
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
    setPlayerPlayingState(false);
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

  function scheduleAudioTrackRefresh() {
    refreshAudioTracks();
    if (playbackFormat !== 'hls') {
      return;
    }
    window.setTimeout(refreshAudioTracks, 400);
    window.setTimeout(refreshAudioTracks, 1500);
  }

  function startPlayback(at) {
    scheduleAudioTrackRefresh();
    if (at > 0) {
      player.currentTime(at);
    }
    player.play().then(function () {
      setPlayerLoading(false);
    }).catch(function () {
      setPlayerLoading(false);
    });
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
      errorRetries = 0;
      hideStreamError();
      player.off('loadedmetadata', onReady);
      player.off('canplay', onReady);
      startPlayback(at);
    }

    player.on('loadedmetadata', onReady);
    player.on('canplay', onReady);
  }

  function restartWithFormat(format) {
    const at = Math.floor(player.currentTime() || 0) || defaultStartAt();
    playbackFormat = format;
    started = false;
    loading = false;
    player.pause();
    begin(at, true);
  }

  function hookBigPlayButton() {
    const bigPlay = player.getChild('BigPlayButton');
    if (!bigPlay || !bigPlay.el()) {
      return;
    }

    bigPlay.el().addEventListener('click', function (event) {
      if (loading) {
        return;
      }
      event.preventDefault();
      event.stopImmediatePropagation();
      begin(defaultStartAt());
    }, true);
  }

  player.ready(function () {
    hookBigPlayButton();
    prefetchSource();
  });

  player.on('error', function () {
    loading = false;
    const err = player.error();
    if (err) {
      console.error('[PutMio] Errore player:', err.code, err.message);
    }

    if (leaving || errorRetries >= MAX_ERROR_RETRIES) {
      setPlayerLoading(false);
      showStreamError();
      return;
    }

    errorRetries++;
    const pos = Math.floor(player.currentTime() || 0);
    const wasPlaying = !player.paused();
    started = false;
    loading = true;
    showStreamRetrying();

    window.setTimeout(function () {
      begin(pos, true);
      if (wasPlaying) {
        player.one('playing', function onRecovered() {
          player.off('playing', onRecovered);
          errorRetries = 0;
          hideStreamError();
        });
      }
    }, 800);
  });

  function ensureStreamErrorEl() {
    if (!playerWrap) return null;
    let el = playerWrap.querySelector('.pm-stream-error');
    if (el) return el;

    el = document.createElement('div');
    el.className = 'pm-stream-error';
    el.hidden = true;
    el.setAttribute('role', 'alert');
    el.innerHTML =
      '<p class="pm-stream-error__title"></p>' +
      '<p class="pm-stream-error__message"></p>' +
      '<button type="button" class="pm-stream-error__retry">' +
        '<span class="material-symbols-outlined" aria-hidden="true">refresh</span>' +
        '<span class="pm-stream-error__retry-label"></span>' +
      '</button>';
    el.querySelector('.pm-stream-error__title').textContent =
      playerLabels.errorTitle || 'Riproduzione interrotta';
    el.querySelector('.pm-stream-error__message').textContent =
      playerLabels.errorMessage || 'Lo stream si è interrotto.';
    el.querySelector('.pm-stream-error__retry-label').textContent =
      playerLabels.errorRetry || 'Riprova';

    el.querySelector('.pm-stream-error__retry').addEventListener('click', function () {
      errorRetries = 0;
      hideStreamError();
      const pos = Math.floor(player.currentTime() || 0);
      started = false;
      loading = false;
      begin(pos, true);
    });

    playerWrap.appendChild(el);
    return el;
  }

  function showStreamRetrying() {
    const el = ensureStreamErrorEl();
    if (!el) return;
    el.hidden = false;
    el.classList.add('pm-stream-error--retrying');
    el.querySelector('.pm-stream-error__message').textContent =
      playerLabels.errorRetrying || 'Riconnessione in corso…';
    el.querySelector('.pm-stream-error__retry').hidden = true;
  }

  function showStreamError() {
    const el = ensureStreamErrorEl();
    if (!el) return;
    el.hidden = false;
    el.classList.remove('pm-stream-error--retrying');
    el.querySelector('.pm-stream-error__message').textContent =
      playerLabels.errorMessage || 'Lo stream si è interrotto.';
    el.querySelector('.pm-stream-error__retry').hidden = false;
  }

  function hideStreamError() {
    const el = playerWrap && playerWrap.querySelector('.pm-stream-error');
    if (!el) return;
    el.hidden = true;
    el.classList.remove('pm-stream-error--retrying');
    el.querySelector('.pm-stream-error__retry').hidden = false;
  }

  function setPlayerPlayingState(isPlaying) {
    if (!playerWrap) return;
    playerWrap.classList.toggle('putmio-player-wrap--playing', isPlaying);
  }

  function isRemotePlaybackActive(el) {
    const remote = el.remote;
    if (!remote || !remote.state) return false;
    return remote.state === 'connecting' || remote.state === 'connected';
  }

  function syncNativeCastOverlay() {
    const tech = player.tech(true);
    const el = tech && tech.el ? tech.el() : null;
    if (!el || typeof el.disableRemotePlayback === 'undefined') return;

    if (isRemotePlaybackActive(el)) {
      el.disableRemotePlayback = false;
      return;
    }

    el.disableRemotePlayback = !player.userActive();
  }

  player.on('useractive', syncNativeCastOverlay);
  player.on('userinactive', syncNativeCastOverlay);
  player.ready(function () {
    const tech = player.tech(true);
    const el = tech && tech.el ? tech.el() : null;
    if (el && el.remote) {
      el.remote.addEventListener('connecting', syncNativeCastOverlay);
      el.remote.addEventListener('connected', syncNativeCastOverlay);
      el.remote.addEventListener('disconnect', syncNativeCastOverlay);
    }
    syncNativeCastOverlay();
  });

  player.on('play', function () {
    if (!started && !loading) {
      player.pause();
      begin(defaultStartAt());
      return;
    }
    setPlayerPlayingState(true);
    setPlayerLoading(false);
  });

  player.on('pause', function () {
    if (!player.currentTime() || player.currentTime() <= 0) {
      setPlayerPlayingState(false);
    }
  });

  player.on('ended', function () {
    setPlayerPlayingState(false);
    if (tvMode) {
      exitTvImmersive();
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
      const value = sourceSelect.value;
      if (value === 'original' || value === 'mp4' || value === 'hls') {
        restartWithFormat(value);
      }
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

  function tvNormalizeKey(evt) {
    if (typeof window.putmioNormalizeTvKey === 'function') {
      return window.putmioNormalizeTvKey(evt);
    }
    const code = evt.keyCode || evt.which;
    const key = evt.key || '';
    if (key === 'ArrowLeft' || code === 37 || code === 21) return 'left';
    if (key === 'ArrowRight' || code === 39 || code === 22) return 'right';
    if (key === 'ArrowUp' || code === 38 || code === 19) return 'up';
    if (key === 'ArrowDown' || code === 40 || code === 20) return 'down';
    if (key === 'Enter' || code === 13 || code === 23) return 'enter';
    if (code === 4 || key === 'Back' || key === 'GoBack' || key === 'Escape' || code === 27 || code === 8) return 'back';
    if (key === 'MediaPlayPause' || code === 179 || code === 85) return 'playpause';
    if (key === 'MediaFastForward' || code === 228 || code === 417) return 'ff';
    if (key === 'MediaRewind' || code === 227 || code === 412) return 'rw';
    return null;
  }

  function shouldHandleTvPlayerKey(evt) {
    if (evt.type === 'keydown') return true;
    if (evt.type !== 'keyup') return false;
    if (!window.PUTMIO || !window.PUTMIO.tvKeyUpFallback) return false;
    return typeof window.putmioIsTvRemoteKey === 'function' && window.putmioIsTvRemoteKey(evt);
  }

  function isDuplicateTvKey(evt) {
    const code = evt.keyCode || evt.which || 0;
    const now = Date.now();
    if (code === tvLastKeyStamp.code && now - tvLastKeyStamp.at < 100) {
      return true;
    }
    tvLastKeyStamp = { code: code, at: now };
    return false;
  }

  function seekTvPlayer(stepSec) {
    if (!started) {
      begin(defaultStartAt());
      return;
    }
    const next = Math.max(0, (player.currentTime() || 0) + stepSec);
    player.currentTime(next);
    if (!tvFullscreenDone) {
      scheduleTvFullscreenRetry();
    }
  }

  function handleTvPlayerKey(evt) {
    if (!tvMode || !document.querySelector('.putmio-player-tv')) return;
    if (!shouldHandleTvPlayerKey(evt)) return;
    if (isDuplicateTvKey(evt)) return;

    activateTvFullscreenWhenReady();

    if (tvImmersiveActive && !tvFullscreenDone && !document.fullscreenElement) {
      scheduleTvFullscreenRetry();
    }

    const dir = tvNormalizeKey(evt);
    if (!dir) return;

    if (dir === 'back') {
      evt.preventDefault();
      evt.stopPropagation();
      if (document.fullscreenElement || (player.isFullscreen && player.isFullscreen())) {
        if (player.exitFullscreen) {
          player.exitFullscreen();
        } else if (document.exitFullscreen) {
          document.exitFullscreen();
        }
      }
      exitTvImmersive();
      return;
    }

    if (dir === 'playpause') {
      evt.preventDefault();
      evt.stopPropagation();
      if (!started) {
        begin(defaultStartAt());
        return;
      }
      if (player.paused()) {
        player.play().catch(function () {});
      } else {
        player.pause();
      }
      return;
    }

    if (dir === 'ff' || dir === 'right') {
      evt.preventDefault();
      evt.stopPropagation();
      seekTvPlayer(10);
      return;
    }

    if (dir === 'rw' || dir === 'left') {
      evt.preventDefault();
      evt.stopPropagation();
      seekTvPlayer(-10);
      return;
    }

    if (dir === 'enter' && !started) {
      evt.preventDefault();
      evt.stopPropagation();
      begin(defaultStartAt());
    }
  }

  if (!tvMode) {
    if (!resumeBtn && !playBtn) {
      begin(startAt > 30 ? 0 : startAt);
    } else if (!resumeBtn && playBtn && startAt < 30) {
      begin(0);
    }
  }

  if (tvFullscreenEnabled) {
    player.on('playing', activateTvFullscreenWhenReady);
    player.on('canplay', activateTvFullscreenWhenReady);

    player.ready(function () {
      const root = player.el();
      if (root) {
        root.setAttribute('tabindex', '0');
      }
      begin(defaultStartAt());
    });

    window.addEventListener('keydown', handleTvPlayerKey, true);
    window.addEventListener('keyup', handleTvPlayerKey, true);

    document.addEventListener('fullscreenchange', function () {
      const shell = document.querySelector('.putmio-player-tv');
      if (document.fullscreenElement) {
        tvFullscreenDone = true;
        if (shell) {
          shell.classList.remove('putmio-player-tv--idle');
        }
        try {
          player.el().focus();
        } catch (e) { /* ignore */ }
        return;
      }
      if (shell && !document.documentElement.classList.contains('putmio-tv-player-immersive')) {
        shell.classList.add('putmio-player-tv--idle');
        const back = document.getElementById('player-back-link');
        if (back) {
          back.focus();
        }
      }
    });
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
    if (tvFullscreenEnabled && !tvImmersiveActive) {
      activateTvFullscreenWhenReady();
    }
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

  let subtitleSyncing = false;
  let subtitleState = {
    list: [],
    activeId: null,
    offsetMs: 0,
  };

  function subtitleTrackId(subtitleId) {
    return 'pm-sub-' + subtitleId;
  }

  function parseSubtitleIdFromTrack(track) {
    if (!track || !track.id || track.id.indexOf('pm-sub-') !== 0) return null;
    const id = parseInt(track.id.slice(7), 10);
    return isFinite(id) ? id : null;
  }

  function emitSubtitleChange(subtitleId) {
    window.dispatchEvent(new CustomEvent('putmio:subtitlechange', {
      detail: { subtitleId: subtitleId },
    }));
  }

  function subtitleServeUrl(meta) {
    const url = new URL(meta.serveUrl, window.location.origin);
    const offset = subtitleState.activeId === meta.id ? (subtitleState.offsetMs || 0) : 0;
    url.searchParams.set('offset_ms', String(offset));
    url.searchParams.set('_', String(Date.now()));
    return url.toString();
  }

  function clearSubtitleTracks() {
    const remote = player.remoteTextTracks ? player.remoteTextTracks() : null;
    if (remote) {
      for (let i = remote.length - 1; i >= 0; i--) {
        player.removeRemoteTextTrack(remote[i]);
      }
    }
  }

  function findTextTrackBySubtitleId(subtitleId) {
    const want = subtitleTrackId(subtitleId);
    const tracks = player.textTracks();
    for (let i = 0; i < tracks.length; i++) {
      if (tracks[i].id === want) return tracks[i];
    }
    return null;
  }

  function setShowingTrack(textTrack) {
    const tracks = player.textTracks();
    subtitleSyncing = true;
    for (let i = 0; i < tracks.length; i++) {
      const track = tracks[i];
      if (track.kind === 'subtitles' || track.kind === 'captions') {
        track.mode = track === textTrack ? 'showing' : 'disabled';
      }
    }
    subtitleSyncing = false;
  }

  function disableAllSubtitleTracks() {
    subtitleSyncing = true;
    const tracks = player.textTracks();
    for (let i = 0; i < tracks.length; i++) {
      const track = tracks[i];
      if (track.kind === 'subtitles' || track.kind === 'captions') {
        track.mode = 'disabled';
      }
    }
    subtitleSyncing = false;
  }

  function activateSubtitleTrack(subtitleId) {
    subtitleState.activeId = subtitleId || null;
    if (!subtitleId) {
      disableAllSubtitleTracks();
      emitSubtitleChange(null);
      return;
    }

    const track = findTextTrackBySubtitleId(subtitleId);
    if (!track) return;
    setShowingTrack(track);
    emitSubtitleChange(subtitleId);
  }

  function attachTrackLoadHandler(textTrack, subtitleId) {
    textTrack.addEventListener('load', function onLoad() {
      textTrack.removeEventListener('load', onLoad);
      if (subtitleState.activeId === subtitleId) {
        setShowingTrack(textTrack);
      }
    });
  }

  function subtitleListSignature(list) {
    return (list || []).map(function (item) { return String(item.id); }).join(',');
  }

  function ensureSubtitleTracks(list, activeId, offsetMs) {
    const nextList = (list || []).slice();
    subtitleState.list = nextList;
    subtitleState.activeId = activeId || null;
    subtitleState.offsetMs = offsetMs || 0;

    clearSubtitleTracks();

    nextList.forEach(function (meta) {
      if (!meta.serveUrl || !meta.id) return;
      const remote = player.addRemoteTextTrack({
        kind: 'subtitles',
        src: subtitleServeUrl(meta),
        srclang: meta.language || 'und',
        label: meta.label || meta.language || 'Subtitles',
        id: subtitleTrackId(meta.id),
      }, false);
      if (remote && remote.track) {
        attachTrackLoadHandler(remote.track, meta.id);
      }
    });

    if (subtitleState.activeId) {
      activateSubtitleTrack(subtitleState.activeId);
    } else {
      disableAllSubtitleTracks();
    }
  }

  function setSubtitleOffset(offsetMs) {
    subtitleState.offsetMs = offsetMs || 0;
    ensureSubtitleTracks(subtitleState.list, subtitleState.activeId, subtitleState.offsetMs);
  }

  function tracksNeedReload() {
    if (subtitleState.list.length === 0) return false;
    return !findTextTrackBySubtitleId(subtitleState.list[0].id);
  }

  function initSubtitleAppearanceSettings() {
    const tts = player.textTrackSettings;
    if (!tts || typeof tts.setValues !== 'function') return;

    const defaults = {
      backgroundColor: '#060e20',
      backgroundOpacity: '1',
      color: '#FFF',
      edgeStyle: 'uniform',
      fontFamily: 'proportionalSansSerif',
      textOpacity: '1',
      windowColor: '#000',
      windowOpacity: '0',
    };

    if (window.PUTMIO && window.PUTMIO.tvMode) {
      defaults.fontPercent = '1.35';
    }

    let hasSaved = false;
    try {
      hasSaved = !!localStorage.getItem('vjs-text-track-settings');
    } catch (e) {}

    if (hasSaved && typeof tts.restoreSettings === 'function') {
      tts.restoreSettings();
    } else {
      if (typeof tts.setDefaults === 'function') {
        tts.setDefaults();
      }
      tts.setValues(defaults);
      if (typeof tts.saveSettings === 'function') {
        tts.saveSettings();
      }
    }

    if (typeof tts.updateDisplay === 'function') {
      tts.updateDisplay();
    }
  }

  function hookTextTrackChanges() {
    const tracks = player.textTracks();
    if (!tracks || !tracks.addEventListener) return;
    tracks.addEventListener('change', function () {
      if (subtitleSyncing) return;

      let showing = null;
      for (let i = 0; i < tracks.length; i++) {
        if (tracks[i].mode === 'showing') {
          showing = tracks[i];
          break;
        }
      }

      const subtitleId = showing ? parseSubtitleIdFromTrack(showing) : null;
      subtitleState.activeId = subtitleId;
      emitSubtitleChange(subtitleId);
    });
  }

  window.PutMioPlayerSubtitles = {
    loadTracks: ensureSubtitleTracks,
    activate: activateSubtitleTrack,
    setOffset: setSubtitleOffset,
    apply: function (activeId, offsetMs, list) {
      const nextList = list || subtitleState.list;
      const listChanged = subtitleListSignature(nextList) !== subtitleListSignature(subtitleState.list);
      const offsetChanged = (offsetMs || 0) !== subtitleState.offsetMs;
      if (list && (listChanged || offsetChanged)) {
        ensureSubtitleTracks(nextList, activeId, offsetMs);
        return;
      }
      if (activeId !== subtitleState.activeId) {
        subtitleState.offsetMs = offsetMs || 0;
        activateSubtitleTrack(activeId);
      }
    },
  };

  player.ready(function () {
    initSubtitleAppearanceSettings();
    hookTextTrackChanges();
    const initialList = window.PUTMIO.availableSubtitles || [];
    if (initialList.length > 0) {
      ensureSubtitleTracks(
        initialList,
        window.PUTMIO.activeSubtitleId || null,
        window.PUTMIO.offsetMs || 0
      );
    }
  });

  player.on('loadedmetadata', function () {
    syncPlayerAspectRatio();
    if (tracksNeedReload()) {
      ensureSubtitleTracks(subtitleState.list, subtitleState.activeId, subtitleState.offsetMs);
    }
  });
})();
