<?php use PutMio\Auth\Csrf; ?>
<div class="max-w-md mx-auto">
  <h1 class="text-2xl font-bold mb-6"><?= putmio_lang('login') ?></h1>
  <?php if (!empty($error)): ?><div class="mb-4 text-red-500 text-sm"><?= putmio_e($error) ?></div><?php endif; ?>
  <form method="post" class="space-y-4"><?= Csrf::field() ?>
    <label class="block text-sm"><span class="text-slate-500"><?= putmio_lang('email') ?></span>
      <input type="email" name="email" required class="mt-1 w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2">
    </label>
    <label class="block text-sm"><span class="text-slate-500"><?= putmio_lang('password') ?></span>
      <input type="password" name="password" required class="mt-1 w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2">
    </label>
    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg py-2"><?= putmio_lang('login') ?></button>
  </form>
  <p class="mt-4 text-sm"><a href="<?= putmio_e(rtrim(\PutMio\Config::get('app.url', putmio_detect_base_url()), '/')) ?>/forgot-password" class="text-indigo-500"><?= putmio_lang('forgot_password') ?></a></p>
</div>
