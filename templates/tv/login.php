<div class="tv-login">
  <div class="tv-login__brand">
    <img src="<?= putmio_e(putmio_asset('public/assets/favicon.svg')) ?>" alt="" class="tv-login__logo" width="56" height="56">
    <div class="tv-login__brand-text">
      <h1 class="tv-login__title">PutMio</h1>
      <p class="tv-login__tagline"><?= putmio_e(putmio_lang('tagline')) ?></p>
    </div>
  </div>

  <div class="tv-login__card">
    <div class="tv-login__columns">
      <div class="tv-login__col tv-login__col--text">
        <h2 class="tv-login__heading"><?= putmio_e(putmio_lang('login')) ?></h2>
        <p class="tv-login__desc"><?= putmio_e(putmio_lang('device_login_desc')) ?></p>

        <div id="device-login-error" class="tv-login__error" role="alert" hidden>
          <span id="device-login-error-text"></span>
        </div>

        <div id="device-login-text-loading" class="tv-login__status">
          <?= putmio_e(putmio_lang('device_login_generating')) ?>
        </div>

        <div id="device-login-text-ready" class="tv-login__text-ready" hidden>
          <p class="tv-login__code-label"><?= putmio_e(putmio_lang('device_login_code_label')) ?></p>
          <p id="device-login-code" class="tv-login__code" aria-live="polite"></p>

          <div id="device-login-waiting" class="tv-login__waiting">
            <span class="tv-login__spinner tv-login__spinner--sm" aria-hidden="true"></span>
            <span id="device-login-waiting-text"><?= putmio_e(putmio_lang('device_login_waiting')) ?></span>
          </div>

          <p class="tv-login__hint"><?= putmio_e(putmio_lang('device_login_pwa_hint')) ?></p>

          <button type="button" id="device-login-refresh" class="tv-btn tv-btn--secondary" data-tv-focus tabindex="0">
            <?= putmio_e(putmio_lang('device_login_refresh')) ?>
          </button>
        </div>
      </div>

      <div class="tv-login__col tv-login__col--qr">
        <div class="tv-login__qr-panel" aria-hidden="false">
          <div id="device-login-loading" class="tv-login__qr-loading">
            <span class="tv-login__spinner" aria-hidden="true"></span>
          </div>
          <div id="device-login-ready" class="tv-login__qr-ready" hidden>
            <div class="tv-login__qr-wrap">
              <div id="device-qr" class="tv-login__qr"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
