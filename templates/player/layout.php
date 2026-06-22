<?php
use PutMio\Config;
$appUrl = rtrim(Config::get('app.url'), '/');
$startAt = 0;
if (!empty($progress) && empty($progress['completed']) && ($progress['position_sec'] ?? 0) > 0) {
    $startAt = (int) $progress['position_sec'];
}
$streamUrl = $appUrl . '/stream?id=' . (int) $media['putio_id'];
?>
<!DOCTYPE html>
<html lang="it" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= putmio_e($title ?? 'Player') ?> — PutMio</title>
  <link href="https://vjs.zencdn.net/8.16.1/video-js.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-black text-white min-h-screen">
  <div class="max-w-6xl mx-auto p-4">
    <div class="flex items-center justify-between mb-4">
      <a href="<?= putmio_e($appUrl) ?>/media?id=<?= (int)$media['id'] ?>" class="text-sm text-slate-400 hover:text-white">← <?= putmio_e($media['title']) ?></a>
    </div>
    <?= $content ?>
  </div>
  <script>window.PUTMIO={baseUrl:<?= json_encode($appUrl) ?>,csrf:<?= json_encode(\PutMio\Auth\Csrf::token()) ?>,mediaId:<?= (int)$media['id'] ?>,startAt:<?= $startAt ?>,streamUrl:<?= json_encode($streamUrl) ?>};</script>
  <script src="https://vjs.zencdn.net/8.16.1/video.min.js"></script>
  <script src="<?= putmio_e($appUrl) ?>/public/assets/player.js" defer></script>
</body>
</html>
