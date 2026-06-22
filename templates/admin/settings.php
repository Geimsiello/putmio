<?php
use PutMio\Auth\Csrf;
use PutMio\Config;
use PutMio\PutIO\Client;

$appUrl = rtrim(Config::get('app.url'), '/');
$authUrl = (new Client())->authorizeUrl();
$cronUrl = $appUrl . '/cron/sync?token=' . ($cronToken ?? '');
$putioCallbackUrl = $appUrl . '/admin/oauth/putio/callback';

$lastSyncLabel = '—';
if (!empty($lastSync)) {
    $ts = strtotime((string) $lastSync);
    if ($ts) {
        $itMonths = [1 => 'gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];
        $lastSyncLabel = (int) date('j', $ts) . ' ' . ($itMonths[(int) date('n', $ts)] ?? '') . ' ' . date('Y, H:i', $ts);
    }
}

$hasTmdbKey = !empty($tmdbKey);
$hasPutioSecret = !empty(Config::get('putio.client_secret'));
$adminCrumbLabel = putmio_lang('settings');
$adminPageTitle = putmio_lang('settings');
$adminTitleAccent = true;
require putmio_base_path() . '/templates/partials/admin-header.php';
?>

<section class="mb-10 md:mb-12 rounded-xl border border-outline-variant/30 bg-surface-container p-5 md:p-6">
  <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
    <div class="flex items-start gap-4 min-w-0">
      <div class="w-11 h-11 shrink-0 rounded-xl bg-surface-container-high border border-outline-variant/20 flex items-center justify-center">
        <span class="material-symbols-outlined text-primary text-[22px]">cloud</span>
      </div>
      <div class="min-w-0">
        <h2 class="text-headline-md font-headline-md text-on-surface mb-2">Integrazione put.io</h2>
        <?php if ($putioConnected): ?>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-success/15 border border-success/30 px-3 py-1 text-label-sm font-label-sm text-success mb-2">
          <span class="w-1.5 h-1.5 rounded-full bg-success" aria-hidden="true"></span>
          Connesso come <?= putmio_e($putioUser) ?>
        </span>
        <p class="text-label-sm font-label-sm text-on-surface-variant">
          Ultima sync: <?= putmio_e($lastSyncLabel) ?> | <?= (int) $lastSyncCount ?> elementi indicizzati
        </p>
        <?php else: ?>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-warning/15 border border-warning/30 px-3 py-1 text-label-sm font-label-sm text-warning mb-2">
          Non connesso
        </span>
        <p class="text-label-sm font-label-sm text-on-surface-variant">
          Configura client_id e secret, salva, poi collega l'account put.io.
        </p>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($putioConnected): ?>
    <div class="flex flex-wrap items-center gap-3 shrink-0">
      <button type="button" id="putio-sync-btn" class="pm-btn-primary" data-pm-putio-sync>
        <span class="material-symbols-outlined text-[18px]">sync</span>
        <?= putmio_lang('sync_now') ?>
      </button>
      <form method="post" action="<?= putmio_e($appUrl) ?>/admin/disconnect-putio"><?= Csrf::field() ?>
        <button type="submit" class="pm-btn-outline-danger">
          <span class="material-symbols-outlined text-[18px]">link_off</span>
          Disconnetti
        </button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</section>

<form method="post" action="<?= putmio_e($appUrl) ?>/admin/impostazioni" class="space-y-10 md:space-y-12"><?= Csrf::field() ?>

  <fieldset class="space-y-4">
    <legend class="flex items-center gap-2 text-headline-md font-headline-md text-on-surface mb-2">
      <span class="material-symbols-outlined text-primary text-[22px]">key</span>
      put.io OAuth
    </legend>

    <div class="rounded-xl border border-outline-variant/30 bg-surface-container-high p-4 md:p-5 space-y-4">
      <div class="flex items-start gap-3">
        <span class="material-symbols-outlined text-primary text-[22px] shrink-0 mt-0.5">help</span>
        <div class="min-w-0 space-y-3">
          <p class="text-body-md text-on-surface">Come collegare put.io</p>
          <ol class="list-decimal list-inside space-y-2 text-label-sm font-label-sm text-on-surface-variant">
            <li>
              Crea una nuova app OAuth su put.io
              (<a href="https://app.put.io/oauth/new" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">app.put.io/oauth/new</a>).
            </li>
            <li>Nel campo <span class="text-on-surface">Callback URL</span> di put.io incolla l'URL qui sotto (deve coincidere esattamente).</li>
            <li>Copia qui <span class="text-on-surface">Client ID</span> e <span class="text-on-surface">Client Secret</span>, poi salva le impostazioni.</li>
            <li>Clicca <span class="text-on-surface">Collega account put.io</span> per autorizzare l'accesso.</li>
          </ol>
        </div>
      </div>
      <div class="space-y-1.5">
        <span class="font-label-md text-label-md text-on-surface-variant ml-1">Callback URL da registrare su put.io</span>
        <div class="flex items-center gap-3 rounded-xl border border-outline-variant/30 bg-surface px-4 py-3">
          <code class="flex-1 min-w-0 text-label-sm font-label-sm text-on-surface-variant break-all" id="putio-callback-display"><?= putmio_e($putioCallbackUrl) ?></code>
          <button type="button" data-pm-copy="<?= putmio_e($putioCallbackUrl) ?>" class="shrink-0 p-2 rounded-lg text-outline hover:text-primary hover:bg-surface-variant/50 transition-colors" title="Copia Callback URL" aria-label="Copia Callback URL">
            <span class="material-symbols-outlined text-[20px]">content_copy</span>
          </button>
        </div>
      </div>
    </div>

    <div class="space-y-1.5">
      <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="putio_client_id">Client ID</label>
      <input class="pm-input" type="text" id="putio_client_id" name="putio_client_id" value="<?= putmio_e($putioClientId) ?>" placeholder="client_id" autocomplete="off">
    </div>
    <div class="space-y-1.5">
      <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="putio_client_secret">Client Secret</label>
      <div class="relative">
        <input class="pm-input pr-12" type="password" id="putio_client_secret" name="putio_client_secret" placeholder="<?= $hasPutioSecret ? '••••••••••••' : 'client_secret' ?>" autocomplete="new-password">
        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-primary transition-colors p-1" data-pm-toggle-password="putio_client_secret" aria-label="Mostra o nascondi secret">
          <span class="material-symbols-outlined text-[20px]">visibility</span>
        </button>
      </div>
    </div>
    <?php if (!$putioConnected && $putioClientId): ?>
    <a href="<?= putmio_e($authUrl) ?>" class="inline-flex items-center gap-2 text-primary font-label-md text-label-md hover:underline mt-1">
      Collega account put.io
      <span class="material-symbols-outlined text-[18px]">open_in_new</span>
    </a>
    <?php endif; ?>
  </fieldset>

  <?php if ($putioConnected): ?>
  <fieldset class="space-y-4">
    <legend class="flex items-center gap-2 text-headline-md font-headline-md text-on-surface mb-2">
      <span class="material-symbols-outlined text-primary text-[22px]">group</span>
      <?= putmio_e(putmio_lang('putio_shared_friends')) ?>
    </legend>
    <p class="text-body-md text-on-surface-variant max-w-2xl">
      <?= putmio_e(putmio_lang('putio_shared_friends_desc')) ?>
    </p>
    <?php if (!empty($friendsError)): ?>
    <div class="flex items-center gap-3 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-body-md text-warning">
      <span class="material-symbols-outlined text-[20px]">warning</span>
      <?= putmio_e($friendsError) ?>
    </div>
    <?php endif; ?>
    <?php if (empty($putioFriends)): ?>
    <p class="text-label-sm font-label-sm text-on-surface-variant ml-1">
      <?= putmio_e(putmio_lang('putio_no_friends')) ?>
    </p>
    <?php else: ?>
    <div id="putio-friends-list" class="rounded-xl border border-outline-variant/30 bg-surface-container-high divide-y divide-outline-variant/20 overflow-hidden">
      <?php foreach ($putioFriends as $friend): ?>
      <?php
        $friendId = (int) ($friend['id'] ?? 0);
        $hasFolder = !empty($friend['folder_putio_id']);
        $checked = !empty($friend['sync_enabled']);
        $rowClass = 'pm-friend-row flex items-center gap-4 px-4 py-3 cursor-pointer transition-colors border-l-4 border-transparent';
        if ($checked) {
            $rowClass .= ' pm-friend-row--selected';
        }
      ?>
      <label class="<?= putmio_e($rowClass) ?>" data-pm-friend-row>
        <input
          type="checkbox"
          value="<?= $friendId ?>"
          class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary/30"
          data-pm-friend-sync
          <?= $checked ? 'checked' : '' ?>
        >
        <span class="w-9 h-9 shrink-0 rounded-full bg-surface-container overflow-hidden flex items-center justify-center border border-outline-variant/20">
          <?php if (!empty($friend['avatar_url'])): ?>
          <img src="<?= putmio_e((string) $friend['avatar_url']) ?>" alt="" class="w-full h-full object-cover">
          <?php else: ?>
          <span class="material-symbols-outlined text-on-surface-variant text-[20px]">person</span>
          <?php endif; ?>
        </span>
        <span class="flex-1 min-w-0">
          <span class="block text-body-md text-on-surface font-medium truncate"><?= putmio_e((string) $friend['username']) ?></span>
          <?php if ($hasFolder): ?>
          <span class="block text-label-sm font-label-sm text-on-surface-variant"><?= putmio_e(putmio_lang('putio_friend_folder_ok')) ?></span>
          <?php else: ?>
          <span class="block text-label-sm font-label-sm text-warning"><?= putmio_e(putmio_lang('putio_friend_folder_missing')) ?></span>
          <?php endif; ?>
        </span>
      </label>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="flex flex-wrap items-center gap-3 pt-1">
      <button type="submit" form="refresh-putio-friends-form" class="inline-flex items-center gap-2 rounded-xl border border-outline-variant/40 bg-surface-container px-4 py-2.5 text-label-md font-label-md text-on-surface hover:bg-surface-variant/30 transition-colors">
        <span class="material-symbols-outlined text-[18px]">refresh</span>
        <?= putmio_e(putmio_lang('putio_refresh_friends')) ?>
      </button>
      <p class="text-label-sm font-label-sm text-on-surface-variant">
        <?= putmio_e(putmio_lang('putio_shared_sync_hint_ajax')) ?>
      </p>
    </div>
  </fieldset>
  <?php endif; ?>

  <fieldset class="space-y-4">
    <legend class="flex items-center gap-2 text-headline-md font-headline-md text-on-surface mb-2">
      <span class="material-symbols-outlined text-primary text-[22px]">movie</span>
      TMDB
    </legend>
    <div class="space-y-1.5">
      <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="tmdb_api_key">API Key TMDB</label>
      <input class="pm-input" type="password" id="tmdb_api_key" name="tmdb_api_key" placeholder="<?= $hasTmdbKey ? '••••••••••••' : 'API key TMDB' ?>" autocomplete="off">
    </div>
    <p class="text-label-sm font-label-sm text-on-surface-variant ml-1">
      Necessaria per recuperare poster e metadati cinematografici.
    </p>
  </fieldset>

  <fieldset class="space-y-4">
    <legend class="flex items-center gap-2 text-headline-md font-headline-md text-on-surface mb-2">
      <span class="material-symbols-outlined text-primary text-[22px]">mail</span>
      <?= putmio_e(putmio_lang('install_smtp_optional')) ?>
    </legend>
    <p class="text-body-md text-on-surface-variant max-w-2xl">
      <?= putmio_e(putmio_lang('settings_smtp_desc')) ?>
    </p>
  <?php if (!empty($smtpEnabled) && !empty($smtpHost) && !empty($smtpFromEmail)): ?>
    <span class="inline-flex items-center gap-1.5 rounded-full bg-success/15 border border-success/30 px-3 py-1 text-label-sm font-label-sm text-success">
      <span class="w-1.5 h-1.5 rounded-full bg-success" aria-hidden="true"></span>
      <?= putmio_e(putmio_lang('settings_smtp_configured')) ?>
    </span>
  <?php elseif (!empty($smtpEnabled)): ?>
    <span class="inline-flex items-center gap-1.5 rounded-full bg-warning/15 border border-warning/30 px-3 py-1 text-label-sm font-label-sm text-warning">
      <?= putmio_e(putmio_lang('settings_smtp_incomplete')) ?>
    </span>
  <?php endif; ?>
    <div class="rounded-xl border border-outline-variant/30 bg-surface-container-high p-4 md:p-5 space-y-4">
      <label class="flex items-center gap-3 font-body-md text-on-surface cursor-pointer">
        <input
          type="checkbox"
          name="smtp_enable"
          value="1"
          class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary/30"
          <?= !empty($smtpEnabled) ? 'checked' : '' ?>
        >
        <?= putmio_e(putmio_lang('install_smtp_enable')) ?>
      </label>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2 space-y-1.5">
          <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="smtp_host">Host</label>
          <input class="pm-input" type="text" id="smtp_host" name="smtp_host" value="<?= putmio_e($smtpHost ?? '') ?>" placeholder="ssl0.ovh.net" autocomplete="off">
        </div>
        <div class="space-y-1.5">
          <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="smtp_port">Porta</label>
          <input class="pm-input" type="text" id="smtp_port" name="smtp_port" value="<?= putmio_e((string) ($smtpPort ?? 587)) ?>" placeholder="587" inputmode="numeric" autocomplete="off">
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-1.5">
          <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="smtp_user">Utente</label>
          <input class="pm-input" type="text" id="smtp_user" name="smtp_user" value="<?= putmio_e($smtpUser ?? '') ?>" autocomplete="off">
        </div>
        <div class="space-y-1.5">
          <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="smtp_pass">Password</label>
          <input class="pm-input" type="password" id="smtp_pass" name="smtp_pass" placeholder="<?= !empty($hasSmtpPass) ? '••••••••••••' : '' ?>" autocomplete="new-password">
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-1.5">
          <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="smtp_from"><?= putmio_e(putmio_lang('install_smtp_from')) ?></label>
          <input class="pm-input" type="email" id="smtp_from" name="smtp_from" value="<?= putmio_e($smtpFromEmail ?? '') ?>" placeholder="noreply@tuodominio.it" autocomplete="email">
        </div>
        <div class="space-y-1.5">
          <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="smtp_from_name"><?= putmio_e(putmio_lang('settings_smtp_from_name')) ?></label>
          <input class="pm-input" type="text" id="smtp_from_name" name="smtp_from_name" value="<?= putmio_e($smtpFromName ?? 'PutMio') ?>" placeholder="PutMio" autocomplete="off">
        </div>
      </div>
    </div>
  </fieldset>

  <fieldset class="space-y-4">
    <legend class="flex items-center gap-2 text-headline-md font-headline-md text-on-surface mb-2">
      <span class="material-symbols-outlined text-primary text-[22px]">schedule</span>
      Cron sync
    </legend>
    <p class="text-body-md text-on-surface-variant max-w-2xl">
      Pannello di configurazione per i processi pianificati. Usa questo endpoint nel crontab del tuo hosting (es. OVH).
    </p>
    <div class="flex items-center gap-3 rounded-xl border border-outline-variant/30 bg-surface-container-high px-4 py-3">
      <code class="flex-1 min-w-0 text-label-sm font-label-sm text-on-surface-variant truncate" id="cron-endpoint-display"><?= putmio_e($appUrl) ?>/cron/sync?token=*****</code>
      <button type="button" data-pm-copy="<?= putmio_e($cronUrl) ?>" class="shrink-0 p-2 rounded-lg text-outline hover:text-primary hover:bg-surface-variant/50 transition-colors" title="Copia URL" aria-label="Copia URL cron">
        <span class="material-symbols-outlined text-[20px]">content_copy</span>
      </button>
    </div>
  </fieldset>

  <div class="pt-2">
    <button type="submit" class="pm-btn-primary px-6 py-3 text-body-md">
      Salva modifiche
    </button>
  </div>
</form>
<?php if ($putioConnected): ?>
<form id="refresh-putio-friends-form" method="post" action="<?= putmio_e($appUrl) ?>/admin/refresh-putio-friends" class="hidden"><?= Csrf::field() ?></form>
<?php endif; ?>
