<?php
/** @var array<string, mixed> $item */
$owner = putmio_catalog_owner_nick($item ?? []);
if ($owner === null) {
    return;
}
?>
<span class="poster-owner-badge" title="<?= putmio_e(putmio_lang('catalog_owner_badge', ['user' => $owner])) ?>">
  <?= putmio_e($owner) ?>
</span>
