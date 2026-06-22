(function () {
  if (!window.PUTMIO || !window.videojs) return;

  const player = videojs('putmio-player', { responsive: true, fluid: true });
  const dialog = document.getElementById('resume-dialog');
  const startAt = window.PUTMIO.startAt || 0;

  function setSource() {
    player.src({ src: window.PUTMIO.streamUrl, type: 'video/mp4' });
  }

  function saveProgress() {
    const pos = Math.floor(player.currentTime() || 0);
    const dur = Math.floor(player.duration() || 0);
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

  function begin(at) {
    setSource();
    player.one('loadedmetadata', function () {
      if (at > 0) player.currentTime(at);
      player.play();
    });
  }

  if (startAt > 30) {
    dialog.classList.remove('hidden');
    document.getElementById('resume-yes').onclick = function () {
      dialog.classList.add('hidden');
      begin(startAt);
    };
    document.getElementById('resume-no').onclick = function () {
      dialog.classList.add('hidden');
      begin(0);
    };
  } else {
    begin(0);
  }

  let tick = 0;
  player.on('timeupdate', function () {
    tick++;
    if (tick % 6 === 0) saveProgress();
  });
  player.on('pause', saveProgress);
  window.addEventListener('beforeunload', saveProgress);
})();
