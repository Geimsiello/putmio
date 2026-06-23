(function () {
  'use strict';

  var POLL_MS = 2500;
  var pollTimer = null;
  var deviceToken = null;
  var qrInstance = null;

  function $(id) {
    return document.getElementById(id);
  }

  function labels() {
    return (window.PUTMIO && window.PUTMIO.deviceLogin) || {};
  }

  function showError(message) {
    var box = $('device-login-error');
    var text = $('device-login-error-text');
    if (!box || !text) return;
    text.textContent = message;
    box.classList.remove('hidden');
    var loading = $('device-login-loading');
    var ready = $('device-login-ready');
    if (loading) loading.classList.add('hidden');
    if (ready) ready.classList.add('hidden');
  }

  function setTab(mode) {
    var emailTab = $('login-tab-email');
    var deviceTab = $('login-tab-device');
    var emailPanel = $('login-panel-email');
    var devicePanel = $('login-panel-device');
    if (!emailTab || !deviceTab || !emailPanel || !devicePanel) return;

    var isDevice = mode === 'device';
    emailTab.setAttribute('aria-selected', isDevice ? 'false' : 'true');
    deviceTab.setAttribute('aria-selected', isDevice ? 'true' : 'false');
    emailTab.classList.toggle('bg-surface-container-high', !isDevice);
    emailTab.classList.toggle('text-on-surface', !isDevice);
    emailTab.classList.toggle('shadow-sm', !isDevice);
    emailTab.classList.toggle('text-on-surface-variant', isDevice);
    deviceTab.classList.toggle('bg-surface-container-high', isDevice);
    deviceTab.classList.toggle('text-on-surface', isDevice);
    deviceTab.classList.toggle('shadow-sm', isDevice);
    deviceTab.classList.toggle('text-on-surface-variant', !isDevice);
    emailPanel.classList.toggle('hidden', isDevice);
    devicePanel.classList.toggle('hidden', !isDevice);

    if (isDevice) {
      startDeviceLogin();
    } else {
      stopPolling();
    }
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
    container.innerHTML = '';
    qrInstance = new QRCode(container, {
      text: url,
      width: 200,
      height: 200,
      colorDark: '#dae2fd',
      colorLight: '#171f33',
      correctLevel: QRCode.CorrectLevel.M,
    });
  }

  async function startDeviceLogin() {
    stopPolling();
    deviceToken = null;

    var loading = $('device-login-loading');
    var ready = $('device-login-ready');
    var errorBox = $('device-login-error');
    if (loading) loading.classList.remove('hidden');
    if (ready) ready.classList.add('hidden');
    if (errorBox) errorBox.classList.add('hidden');

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

      if (loading) loading.classList.add('hidden');
      if (ready) ready.classList.remove('hidden');

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

      var status = data.status;
      if (status === 'approved') {
        stopPolling();
        await completeLogin();
      } else if (status === 'expired' || status === 'not_found') {
        stopPolling();
        showError(labels().expired);
      } else if (status === 'denied') {
        stopPolling();
        showError(labels().denied);
      }
    } catch (e) {
      /* retry on next tick */
    }
  }

  async function completeLogin() {
    var waiting = $('device-login-waiting');
    if (waiting) {
      waiting.innerHTML =
        '<span class="material-symbols-outlined text-success text-[20px]">check_circle</span>' +
        '<span class="font-body-md text-body-md text-success">' + (labels().completing || 'Accesso in corso…') + '</span>';
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
    var emailTab = $('login-tab-email');
    var deviceTab = $('login-tab-device');
    var refreshBtn = $('device-login-refresh');

    if (!emailTab || !deviceTab) return;

    emailTab.addEventListener('click', function () { setTab('email'); });
    deviceTab.addEventListener('click', function () { setTab('device'); });
    if (refreshBtn) refreshBtn.addEventListener('click', startDeviceLogin);

    if (deviceTab.getAttribute('aria-selected') === 'true') {
      startDeviceLogin();
    }
  });
})();
