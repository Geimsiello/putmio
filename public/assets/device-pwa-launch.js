(function () {
  'use strict';

  function isStandalone() {
    if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
      return true;
    }
    if (window.navigator.standalone === true) {
      return true;
    }
    if (document.referrer && document.referrer.indexOf('android-app://') === 0) {
      return true;
    }
    return false;
  }

  function isIos() {
    return /iPad|iPhone|iPod/.test(navigator.userAgent)
      || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  }

  async function copyCode(code) {
    if (!code) return false;
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(code);
        return true;
      }
    } catch (e) {
      /* fallback below */
    }
    try {
      var ta = document.createElement('textarea');
      ta.value = code;
      ta.setAttribute('readonly', '');
      ta.style.position = 'absolute';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      var ok = document.execCommand('copy');
      document.body.removeChild(ta);
      return ok;
    } catch (e2) {
      return false;
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var cfg = (window.PUTMIO && window.PUTMIO.devicePwaLaunch) || {};
    var code = cfg.code || '';
    var baseUrl = (window.PUTMIO && window.PUTMIO.baseUrl || '').replace(/\/$/, '');
    var browserPanel = document.getElementById('pwa-launch-browser');
    var redirectPanel = document.getElementById('pwa-launch-redirecting');

    if (isStandalone()) {
      if (browserPanel) browserPanel.classList.add('hidden');
      if (redirectPanel) redirectPanel.classList.remove('hidden');

      var loginNext = cfg.loginNext || ('authorize-device?code=' + encodeURIComponent(code));
      window.location.replace(baseUrl + '/login?next=' + encodeURIComponent(loginNext));
      return;
    }

    if (isIos() && code) {
      copyCode(code).then(function (ok) {
        if (ok) {
          var copied = document.getElementById('pwa-launch-copied');
          if (copied) copied.classList.remove('hidden');
        }
      });
    }

    if ('getInstalledRelatedApps' in navigator) {
      navigator.getInstalledRelatedApps().then(function (apps) {
        if (apps && apps.length > 0) {
          var openBtn = document.getElementById('pwa-launch-open-app');
          if (openBtn) openBtn.classList.add('ring-2', 'ring-primary/40');
        }
      }).catch(function () { /* ignore */ });
    }
  });
})();
