(function () {
  'use strict';

  function labels() {
    return (window.PUTMIO && window.PUTMIO.deviceAuthorize) || {};
  }

  function showMessage(type, text) {
    var existing = document.getElementById('device-authorize-flash');
    if (existing) existing.remove();

    var div = document.createElement('div');
    div.id = 'device-authorize-flash';
    div.className = 'mb-6 flex items-center gap-3 p-3 rounded-lg ' +
      (type === 'success'
        ? 'bg-success/10 border border-success/20'
        : 'bg-error/10 border border-error/20');
    div.setAttribute('role', type === 'success' ? 'status' : 'alert');
    div.innerHTML =
      '<span class="material-symbols-outlined text-[20px] shrink-0 ' +
      (type === 'success' ? 'text-success">check_circle' : 'text-error">error') +
      '</span><span class="font-body-md text-body-md ' +
      (type === 'success' ? 'text-success' : 'text-error') + '">' + text + '</span>';

    var glass = document.querySelector('.auth-glass');
    if (glass) {
      var heading = glass.querySelector('.mb-6');
      if (heading && heading.nextElementSibling) {
        glass.insertBefore(div, heading.nextElementSibling);
      } else {
        glass.prepend(div);
      }
    }
  }

  async function postAction(endpoint, code) {
    var body = new FormData();
    body.append('_csrf', window.PUTMIO.csrf);
    body.append('code', code);
    var res = await fetch(window.PUTMIO.baseUrl + endpoint, { method: 'POST', body: body });
    return res.json();
  }

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('device-authorize-form');
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var input = document.getElementById('device-authorize-code');
        var code = (input && input.value) ? input.value.trim().toUpperCase() : '';
        if (!code) return;
        var base = window.PUTMIO.baseUrl.replace(/\/$/, '');
        window.location.href = base + '/authorize-device?code=' + encodeURIComponent(code);
      });

      var codeInput = document.getElementById('device-authorize-code');
      if (codeInput) {
        codeInput.addEventListener('input', function () {
          var raw = codeInput.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().slice(0, 8);
          if (raw.length > 4) {
            codeInput.value = raw.slice(0, 4) + '-' + raw.slice(4);
          } else {
            codeInput.value = raw;
          }
        });
      }
    }

    var approveBtn = document.getElementById('device-approve-btn');
    if (approveBtn) {
      approveBtn.addEventListener('click', async function () {
        var code = approveBtn.getAttribute('data-code') || '';
        approveBtn.disabled = true;
        try {
          var data = await postAction('/api/auth/device/approve', code);
          if (data.ok) {
            showMessage('success', labels().approved || 'Dispositivo autorizzato.');
            approveBtn.closest('.space-y-5')?.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
          } else {
            showMessage('error', labels().invalidCode || 'Codice non valido o scaduto.');
            approveBtn.disabled = false;
          }
        } catch (e) {
          showMessage('error', labels().error || 'Errore di connessione.');
          approveBtn.disabled = false;
        }
      });
    }

    var denyBtn = document.getElementById('device-deny-btn');
    if (denyBtn) {
      denyBtn.addEventListener('click', async function () {
        var code = denyBtn.getAttribute('data-code') || '';
        denyBtn.disabled = true;
        try {
          await postAction('/api/auth/device/deny', code);
          showMessage('success', labels().denied || 'Richiesta rifiutata.');
          denyBtn.closest('.space-y-5')?.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
        } catch (e) {
          showMessage('error', labels().error || 'Errore di connessione.');
          denyBtn.disabled = false;
        }
      });
    }
  });
})();
