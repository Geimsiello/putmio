<?php /** @var array $db */ /** @var string $csrf */ ?>
<div class="mb-8">
  <h2 class="font-headline-md text-headline-md text-on-surface mb-2"><?= putmio_lang('install_database') ?></h2>
  <p class="font-body-md text-body-md text-on-surface-variant"><?= putmio_lang('install_database_help') ?></p>
</div>

<form method="post" class="space-y-6"><?= $csrf ?>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="space-y-1.5">
      <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="db_host"><?= putmio_lang('db_host') ?></label>
      <input class="install-input" type="text" id="db_host" name="db_host" value="<?= putmio_e($db['host']) ?>" placeholder="localhost" required autocomplete="off">
    </div>
    <div class="space-y-1.5">
      <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="db_prefix"><?= putmio_lang('db_prefix') ?></label>
      <input class="install-input" type="text" id="db_prefix" name="db_prefix" value="<?= putmio_e($db['prefix']) ?>" placeholder="pm_" autocomplete="off">
    </div>
  </div>

  <div class="space-y-1.5">
    <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="db_name"><?= putmio_lang('db_name') ?></label>
    <input class="install-input" type="text" id="db_name" name="db_name" value="<?= putmio_e($db['name']) ?>" placeholder="es. putmio_media" required autocomplete="off">
  </div>

  <div class="space-y-1.5">
    <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="db_user"><?= putmio_lang('db_user') ?></label>
    <input class="install-input" type="text" id="db_user" name="db_user" value="<?= putmio_e($db['user']) ?>" placeholder="<?= putmio_e(putmio_lang('db_user')) ?>" required autocomplete="off">
  </div>

  <div class="space-y-1.5">
    <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="db_pass"><?= putmio_lang('db_pass') ?></label>
    <div class="relative">
      <input class="install-input pr-12" type="password" id="db_pass" name="db_pass" value="<?= putmio_e($db['pass']) ?>" placeholder="••••••••" autocomplete="new-password">
      <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface transition-colors" data-toggle-password="db_pass" aria-label="Mostra password">
        <span class="material-symbols-outlined text-[20px]">visibility</span>
      </button>
    </div>
  </div>

  <div class="pt-4 flex flex-col sm:flex-row gap-4">
    <button type="submit" name="action" value="test" class="flex-1 px-6 py-3.5 install-btn-secondary font-headline-md text-[16px] flex items-center justify-center gap-2">
      <span class="material-symbols-outlined text-[18px]">network_check</span>
      <?= putmio_lang('test_connection') ?>
    </button>
    <button type="submit" class="flex-1 px-6 py-3.5 install-btn-primary font-headline-md text-[16px] flex items-center justify-center gap-2 shadow-lg">
      <?= putmio_lang('next') ?>
      <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
    </button>
  </div>
</form>

<p class="mt-8 text-center font-label-sm text-label-sm text-outline">
  <?= putmio_lang('install_database_hint') ?>
</p>

<div class="mt-8 flex justify-start">
  <a href="?step=2" class="flex items-center gap-2 font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors">
    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
    <?= putmio_lang('back') ?>
  </a>
</div>
