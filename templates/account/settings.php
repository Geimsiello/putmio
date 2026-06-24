<?php
$accountCrumbLabel = putmio_lang('account_settings');
$accountPageTitle = putmio_lang('account_settings');
$accountPageDescription = putmio_lang('account_settings_desc');
$accountTitleAccent = true;
require putmio_base_path() . '/templates/partials/account-header.php';

$currentLocale = putmio_locale();
$locales = putmio_available_locales();
?>

<section class="rounded-xl border border-outline-variant/30 bg-surface-container p-5 md:p-6 max-w-xl">
  <div class="flex items-start gap-4 mb-6">
    <div class="w-11 h-11 shrink-0 rounded-xl bg-surface-container-high border border-outline-variant/20 flex items-center justify-center">
      <span class="material-symbols-outlined text-primary text-[22px]" aria-hidden="true">language</span>
    </div>
    <div>
      <h2 class="text-headline-md font-headline-md text-on-surface mb-1"><?= putmio_e(putmio_lang('account_default_language')) ?></h2>
      <p class="text-body-md text-on-surface-variant"><?= putmio_e(putmio_lang('account_default_language_desc')) ?></p>
    </div>
  </div>

  <div class="space-y-2" role="listbox" aria-label="<?= putmio_e(putmio_lang('language')) ?>">
    <?php foreach ($locales as $code => $meta):
      $active = $code === $currentLocale;
    ?>
    <button
      type="button"
      role="option"
      class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-lg border transition-colors text-left <?= $active
        ? 'border-primary/40 bg-primary/10 text-primary'
        : 'border-outline-variant/30 bg-surface-container-high text-on-surface hover:border-primary/30 hover:bg-surface-variant/20' ?>"
      data-locale="<?= putmio_e($code) ?>"
      data-pm-account-locale
      aria-selected="<?= $active ? 'true' : 'false' ?>"
      <?= $active ? 'disabled' : '' ?>
    >
      <span class="text-body-md font-medium"><?= putmio_e($meta['native']) ?></span>
      <?php if ($active): ?>
      <span class="material-symbols-outlined text-primary text-[20px]" aria-hidden="true">check_circle</span>
      <?php endif; ?>
    </button>
    <?php endforeach; ?>
  </div>
</section>

<script>
(function () {
  document.querySelectorAll('[data-pm-account-locale]').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      var locale = btn.getAttribute('data-locale') || '';
      if (!locale || btn.getAttribute('aria-selected') === 'true' || !window.PUTMIO) return;
      btn.disabled = true;
      try {
        var body = new URLSearchParams({ _csrf: window.PUTMIO.csrf, locale: locale });
        var res = await fetch(window.PUTMIO.baseUrl + '/api/preferences/locale', { method: 'POST', body: body });
        if (!res.ok) throw new Error('locale failed');
        localStorage.setItem('putmio_locale', locale);
        document.cookie = 'putmio_locale=' + locale + ';path=/;max-age=31536000;SameSite=Strict';
        window.location.reload();
      } catch (e) {
        btn.disabled = false;
        if (window.pmToast) {
          window.pmToast(window.PUTMIO.localeChangeError || 'Language change failed', 'error');
        }
      }
    });
  });
})();
</script>
