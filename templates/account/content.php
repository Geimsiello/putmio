<?php
/** @var list<array{key: string, label: string, is_own: bool}> $options */
/** @var list<string> $hidden */
?>

<?php
$accountCrumbLabel = putmio_lang('account_content');
$accountPageTitle = putmio_lang('account_content');
$accountPageDescription = putmio_lang('account_content_desc');
$accountTitleAccent = true;
require putmio_base_path() . '/templates/partials/account-header.php';
?>

<section class="rounded-xl border border-outline-variant/30 bg-surface-container p-5 md:p-6 max-w-2xl">
  <div id="account-content-sources" class="rounded-xl border border-outline-variant/30 bg-surface-container-high divide-y divide-outline-variant/20 overflow-hidden">
    <?php foreach ($options as $option):
      $key = (string) $option['key'];
      $checked = !in_array($key, $hidden, true);
      $rowClass = 'pm-friend-row flex items-center gap-4 px-4 py-3 cursor-pointer transition-colors border-l-4 border-transparent';
      if ($checked) {
          $rowClass .= ' pm-friend-row--selected';
      }
    ?>
    <label class="<?= putmio_e($rowClass) ?>" data-pm-content-row>
      <input
        type="checkbox"
        value="<?= putmio_e($key) ?>"
        class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary/30"
        data-pm-content-source
        <?= $checked ? 'checked' : '' ?>
      >
      <span class="w-9 h-9 shrink-0 rounded-full bg-surface-container overflow-hidden flex items-center justify-center border border-outline-variant/20">
        <span class="material-symbols-outlined text-primary text-[20px]" aria-hidden="true"><?= !empty($option['is_own']) ? 'home_storage' : 'person' ?></span>
      </span>
      <span class="flex-1 min-w-0">
        <span class="block text-body-md text-on-surface font-medium truncate"><?= putmio_e((string) $option['label']) ?></span>
        <span class="block text-label-sm font-label-sm text-on-surface-variant">
          <?= putmio_e(!empty($option['is_own']) ? putmio_lang('account_content_own_hint') : putmio_lang('account_content_shared_hint')) ?>
        </span>
      </span>
    </label>
    <?php endforeach; ?>
  </div>

  <p class="text-label-sm font-label-sm text-on-surface-variant mt-4">
    <?= putmio_e(putmio_lang('account_content_save_hint')) ?>
  </p>
</section>
