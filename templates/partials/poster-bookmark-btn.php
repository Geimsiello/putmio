<?php
/** @var int $bookmarkMediaId */
/** @var list<int>|null $watchlistIds */
$watchlistIds = $watchlistIds ?? [];
$bookmarkActive = in_array($bookmarkMediaId, $watchlistIds, true);
$bookmarkVariant = $bookmarkVariant ?? 'poster';
$btnClass = $bookmarkVariant === 'detail'
    ? 'pm-watchlist-btn pm-watchlist-btn--detail'
    : 'pm-poster-bookmark pm-watchlist-btn';
?>
<button
  type="button"
  class="<?= putmio_e($btnClass) ?><?= $bookmarkActive ? ' pm-watchlist-btn--active' : '' ?>"
  data-pm-watchlist-toggle="<?= (int) $bookmarkMediaId ?>"
  data-pm-watchlist-active="<?= $bookmarkActive ? '1' : '0' ?>"
  aria-pressed="<?= $bookmarkActive ? 'true' : 'false' ?>"
  aria-label="<?= putmio_e($bookmarkActive ? putmio_lang('watchlist_remove') : putmio_lang('watchlist_add')) ?>"
>
  <span class="material-symbols-outlined pm-watchlist-btn__icon"<?= $bookmarkActive ? ' style="font-variation-settings: \'FILL\' 1;"' : '' ?>>bookmark</span>
  <?php if ($bookmarkVariant === 'detail'): ?>
  <span class="pm-watchlist-btn__label"><?= putmio_e($bookmarkActive ? putmio_lang('watchlist_remove') : putmio_lang('watchlist_add')) ?></span>
  <?php endif; ?>
</button>
