<h1 class="text-2xl font-bold mb-6">Streaming</h1>
<p class="text-slate-500 mb-4">Banda oggi: <strong class="text-white"><?= putmio_format_bytes((int)$todayBytes) ?></strong></p>
<h2 class="font-semibold mb-3">Sessioni attive (<?= count($active) ?>)</h2>
<?php if (empty($active)): ?>
<p class="text-slate-500 text-sm">Nessuno stream in corso.</p>
<?php else: ?>
<ul class="space-y-2 text-sm">
<?php foreach ($active as $s): ?>
<li class="border border-slate-800 rounded-lg p-3 flex justify-between gap-4">
  <span><?= putmio_e($s['display_name'] ?? 'Utente') ?> — <?= putmio_e($s['title'] ?? 'Titolo') ?></span>
  <span class="text-slate-500"><?= putmio_format_bytes((int)$s['bytes_sent']) ?> · dal <?= putmio_e($s['started_at']) ?></span>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<p class="mt-6 text-xs text-slate-500">Aggiorna la pagina per vedere dati aggiornati.</p>
