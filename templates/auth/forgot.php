<?php use PutMio\Auth\Csrf; ?>
<div class="max-w-md mx-auto">
  <h1 class="text-2xl font-bold mb-6"><?= putmio_lang('forgot_password') ?></h1>
  <?php if (!empty($success)): ?><div class="mb-4 text-emerald-500 text-sm"><?= putmio_e($success) ?></div><?php endif; ?>
  <form method="post" class="space-y-4"><?= Csrf::field() ?>
    <label class="block text-sm"><span class="text-on-surface-variant"><?= putmio_lang('email') ?></span>
      <input type="email" name="email" required class="mt-1 w-full rounded-lg border border-outline-variant/40 bg-surface-container-lowest text-on-surface px-3 py-2">
    </label>
    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg py-2">Invia link</button>
  </form>
</div>
