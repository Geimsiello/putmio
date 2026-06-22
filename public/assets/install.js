(function () {
  'use strict';

  document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-toggle-password');
      var input = document.getElementById(id);
      if (!input) return;
      var icon = btn.querySelector('.material-symbols-outlined');
      var isPass = input.type === 'password';
      input.type = isPass ? 'text' : 'password';
      if (icon) icon.textContent = isPass ? 'visibility_off' : 'visibility';
    });
  });

  var pass = document.getElementById('password');
  var confirmPass = document.getElementById('password_confirm');
  if (pass && confirmPass) {
    var validatePass = function () {
      if (!pass.value || !confirmPass.value) {
        confirmPass.classList.remove('install-input-match', 'install-input-mismatch');
        return;
      }
      confirmPass.classList.remove('install-input-match', 'install-input-mismatch');
      confirmPass.classList.add(pass.value === confirmPass.value ? 'install-input-match' : 'install-input-mismatch');
    };
    pass.addEventListener('input', validatePass);
    confirmPass.addEventListener('input', validatePass);
  }

  var runForm = document.getElementById('install-run-form');
  if (runForm) {
    runForm.addEventListener('submit', function () {
      var idle = document.getElementById('install-run-idle');
      var busy = document.getElementById('install-run-busy');
      var btn = runForm.querySelector('button[type="submit"]');
      if (idle) idle.classList.add('hidden');
      if (busy) busy.classList.remove('hidden');
      if (btn) btn.disabled = true;
    });
  }

  document.querySelectorAll('.install-requirement-row').forEach(function (row, index) {
    row.style.opacity = '0';
    row.style.transform = 'translateY(10px)';
    setTimeout(function () {
      row.style.transition = 'all 0.4s ease-out';
      row.style.opacity = '1';
      row.style.transform = 'translateY(0)';
    }, 100 + index * 60);
  });
})();
