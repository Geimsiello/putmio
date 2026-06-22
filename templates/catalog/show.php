<?php
/** @var array $media */
/** @var bool $isLinked */
if ($isLinked) {
    require putmio_base_path() . '/templates/catalog/show-linked.php';
} else {
    require putmio_base_path() . '/templates/catalog/show-unlinked.php';
}
