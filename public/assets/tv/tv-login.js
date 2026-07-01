(function () {
  'use strict';

  if (!window.PUTMIO || !window.PUTMIO.tvSite) {
    return;
  }

  var POLL_MS = 2500;
  var pollTimer = null;
  var deviceToken = null;

  function $(id) {
    return document.getElementById(id);
  }

  function labels() {
    return (window.PUTMIO && window.PUTMIO.deviceLogin) || {};
  }

  function show(el) {
    if (el) el.hidden = false;
  }

  function hide(el) {
    if (el) el.hidden = true;
  }

  function showError(message) {
    var box = $('device-login-error');
    var text = $('device-login-error-text');
    if (!box || !text) return;
    text.textContent = message;
    show(box);
    hide($('device-login-loading'));
    hide($('device-login-ready'));
  }

  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  function renderQr(url) {
    var container = $('device-qr');
    if (!container || typeof QRCode === 'undefined') return;
    container.textContent = '';
    new QRCode(container, {
      text: url,
      width: 240,
      height: 240,
      colorDark: '#dae2fd',
      colorLight: '#171f33',
      correctLevel: QRCode.CorrectLevel.M,
    });
  }

  async function startDeviceLogin() {
    stopPolling();
    deviceToken = null;

    show($('device-login-loading'));
    hide($('device-login-ready'));
    hide($('device-login-error'));

    try {
      var res = await fetch(window.PUTMIO.baseUrl + '/api/auth/device/start', { method: 'POST' });
      var data = await res.json();

      if (!data.ok) {
        showError(res.status === 429 ? labels().rateLimited : labels().error);
        return;
      }

      deviceToken = data.device_token;
      var codeEl = $('device-login-code');
      if (codeEl) codeEl.textContent = data.code;
      renderQr(data.authorize_url);

      hide($('device-login-loading'));
      show($('device-login-ready'));

      pollTimer = setInterval(pollStatus, POLL_MS);
      pollStatus();
    } catch (e) {
      showError(labels().error || 'Errore di connessione');
    }
  }

  async function pollStatus() {
    if (!deviceToken) return;

    try {
      var res = await fetch(
        window.PUTMIO.baseUrl + '/api/auth/device/status?device_token=' + encodeURIComponent(deviceToken)
      );
      var data = await res.json();
      if (!data.ok) return;

      if (data.status === 'approved') {
        stopPolling();
        await completeLogin();
      } else if (data.status === 'expired' || data.status === 'not_found') {
        stopPolling();
        showError(labels().expired);
      } else if (data.status === 'denied') {
        stopPolling();
        showError(labels().denied);
      }
    } catch (e) {
      /* retry */
    }
  }

  async function completeLogin() {
    var waiting = $('device-login-waiting');
    if (waiting) {
      waiting.textContent = labels().completing || 'Accesso in corso…';
    }

    try {
      var body = new FormData();
      body.append('device_token', deviceToken);
      var res = await fetch(window.PUTMIO.baseUrl + '/api/auth/device/complete', { method: 'POST', body: body });
      var data = await res.json();
      if (data.ok && data.redirect) {
        window.location.href = data.redirect;
        return;
      }
      showError(labels().error);
    } catch (e) {
      showError(labels().error);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var refreshBtn = $('device-login-refresh');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', startDeviceLogin);
    }
    startDeviceLogin();
  });
})();
