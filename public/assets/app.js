(function () {
  const btn = document.getElementById('theme-toggle');
  if (!btn || !window.PUTMIO) return;

  btn.addEventListener('click', async function () {
    const isDark = document.documentElement.classList.toggle('dark');
    const theme = isDark ? 'dark' : 'light';
    localStorage.setItem('putmio_theme', theme);
    document.cookie = 'putmio_theme=' + theme + ';path=/;max-age=31536000;SameSite=Strict';

    try {
      const body = new URLSearchParams({ _csrf: window.PUTMIO.csrf, theme: theme });
      await fetch(window.PUTMIO.baseUrl + '/api/preferences/theme', { method: 'POST', body: body });
    } catch (e) { /* ignore */ }
  });
})();
