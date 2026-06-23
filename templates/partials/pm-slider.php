<?php
/** @var string $sliderInner */
/** @var string $sliderId */
/** @var string|null $trackClass */
$trackClass = trim((string) ($trackClass ?? ''));
?>
<div class="pm-slider relative" id="<?= putmio_e($sliderId) ?>" data-pm-slider>
  <button type="button" class="pm-slider__nav pm-slider__nav--prev" aria-label="<?= putmio_lang('slider_prev') ?>" data-pm-slider-prev hidden>
    <span class="material-symbols-outlined" aria-hidden="true">chevron_left</span>
  </button>
  <div class="pm-slider__track flex overflow-x-auto -mx-1 px-1 snap-x snap-proximity overscroll-x-contain <?= putmio_e($trackClass) ?>" data-pm-slider-track>
    <?= $sliderInner ?>
  </div>
  <button type="button" class="pm-slider__nav pm-slider__nav--next" aria-label="<?= putmio_lang('slider_next') ?>" data-pm-slider-next hidden>
    <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
  </button>
</div>
