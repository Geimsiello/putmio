<?php
use PutMio\Auth\Csrf;
use PutMio\Config;
$appUrl = rtrim(Config::get('app.url'), '/');
$poster = $catalog->posterWebPath($media['poster_local_path'] ?? null, $media['poster_url'] ?? null);
$hasProgress = $progress && empty($progress['completed']) && ($progress['position_sec'] ?? 0) > 0;
?>
<div class="grid md:grid-cols-[220px_1fr] gap-8">
  <img src="<?= putmio_e($poster) ?>" alt="" class="w-full rounded-xl aspect-[2/3] object-cover bg-slate-800">
  <div>
    <h1 class="text-3xl font-bold mb-2"><?= putmio_e($media['title']) ?></h1>
    <p class="text-slate-500 mb-4"><?= putmio_e($media['media_type']) ?><?= $media['year'] ? ' · ' . (int)$media['year'] : '' ?></p>
    <?php if (!empty($media['synopsis'])): ?><p class="text-slate-300 mb-6 leading-relaxed"><?= nl2br(putmio_e($media['synopsis'])) ?></p><?php endif; ?>
    <div class="flex flex-wrap gap-3">
      <a href="<?= putmio_e($appUrl) ?>/play?id=<?= (int)$media['id'] ?>" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-5 py-2 font-medium">
        <?= $hasProgress ? putmio_lang('resume') : putmio_lang('play') ?>
      </a>
    </div>
    <?php if (\PutMio\Auth\Session::isAdmin()): ?>
    <div class="mt-8 border-t border-slate-800 pt-6" x-data="tmdbSearch(<?= (int)$media['id'] ?>)">
      <h3 class="font-semibold mb-2">TMDB (admin)</h3>
      <div class="flex gap-2 mb-3">
        <input x-model="query" type="text" placeholder="Cerca su TMDB..." class="flex-1 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm">
        <button type="button" @click="search()" class="bg-slate-700 rounded-lg px-4 text-sm">Cerca</button>
      </div>
      <template x-for="r in results" :key="r.id">
        <button type="button" @click="apply(r)" class="block w-full text-left border border-slate-800 rounded-lg p-2 mb-2 text-sm hover:bg-slate-800" x-text="(r.title || r.name) + ' (' + (r.release_date || r.first_air_date || '?') + ')'"></button>
      </template>
    </div>
    <?php endif; ?>
  </div>
</div>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function tmdbSearch(mediaId) {
  return {
    query: '', results: [],
    async search() {
      const r = await fetch(window.PUTMIO.baseUrl + '/api/tmdb/search?q=' + encodeURIComponent(this.query));
      const d = await r.json();
      this.results = d.results || [];
    },
    async apply(item) {
      const body = new URLSearchParams({
        _csrf: window.PUTMIO.csrf,
        media_id: mediaId,
        tmdb_id: item.id,
        tmdb_type: item.media_type === 'tv' ? 'tv' : 'movie'
      });
      await fetch(window.PUTMIO.baseUrl + '/api/tmdb/apply', { method: 'POST', body });
      location.reload();
    }
  };
}
</script>
