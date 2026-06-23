<?php use PutMio\Auth\Csrf; ?>
<div class="max-w-md mx-auto">
  <h1 class="text-2xl font-bold mb-6">Reimposta password</h1>
  <?php if (!empty($error)): ?><div class="mb-4 text-red-500 text-sm"><?= putmio_e($error) ?></div><?php endif; ?>
  <form method="post" class="space-y-4"><?= Csrf::field() ?>
    <input type="hidden" name="token" value="<?= putmio_e($token ?? '') ?>">
    <label class="block text-sm"><span class="text-on-surface-variant">Nuova password</span>
      <input type="password" name="password" minlength="10" required class="mt-1 w-full rounded-lg border border-outline-variant/40 bg-surface-container-lowest text-on-surface px-3 py-2">
    </label>
    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg py-2">Salva</button>
  </form>
</div>
