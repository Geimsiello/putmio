(function (global) {
  'use strict';

  /**
   * Normalizza input telecomando (Fire TV / Android TV / browser TV).
   * Fire TV Silk: D-pad 37–40, Select 13/23, Back 4, Play 179/85, RW 227, FF 228.
   */
  function normalizeTvKey(evt) {
    if (!evt) return null;
    var code = evt.keyCode || evt.which || 0;
    var key = evt.key || '';

    if (key === 'ArrowLeft' || code === 37 || code === 21) return 'left';
    if (key === 'ArrowRight' || code === 39 || code === 22) return 'right';
    if (key === 'ArrowUp' || code === 38 || code === 19) return 'up';
    if (key === 'ArrowDown' || code === 40 || code === 20) return 'down';

    if (key === 'Enter' || code === 13 || code === 23) return 'enter';

    if (code === 4 || code === 461 || key === 'Back' || key === 'GoBack'
      || key === 'Escape' || code === 27 || code === 8) {
      return 'back';
    }

    if (key === 'MediaPlayPause' || code === 179 || code === 85 || code === 10252) {
      return 'playpause';
    }
    if (key === 'MediaFastForward' || code === 228 || code === 417 || code === 90) {
      return 'ff';
    }
    if (key === 'MediaRewind' || code === 227 || code === 412 || code === 89) {
      return 'rw';
    }
    if (key === 'MediaTrackNext' || code === 87) return 'ff';
    if (key === 'MediaTrackPrevious' || code === 88) return 'rw';

    return null;
  }

  function isTvRemoteKey(evt) {
    return normalizeTvKey(evt) !== null;
  }

  global.putmioNormalizeTvKey = normalizeTvKey;
  global.putmioIsTvRemoteKey = isTvRemoteKey;
})(typeof window !== 'undefined' ? window : this);
