<?php

declare(strict_types=1);

function putmio_base_path(): string
{
    return dirname(__DIR__);
}

/** Versione semantica della piattaforma (file VERSION in root). */
function putmio_version(): string
{
    static $version = null;
    if ($version !== null) {
        return $version;
    }
    $file = putmio_base_path() . '/VERSION';
    if (is_readable($file)) {
        $raw = trim((string) file_get_contents($file));
        if ($raw !== '') {
            return $version = $raw;
        }
    }
    return $version = 'dev';
}

function putmio_config_path(): string
{
    return putmio_base_path() . '/config.php';
}

function putmio_installed_lock(): string
{
    return putmio_base_path() . '/storage/.installed';
}

function putmio_is_installed(): bool
{
    return is_file(putmio_config_path()) && is_file(putmio_installed_lock());
}

function putmio_detect_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/putmio/index.php';
    $dir = str_replace('\\', '/', dirname($script));
    $dir = rtrim($dir, '/');
    if ($dir === '' || $dir === '.') {
        $dir = '';
    }
    return $scheme . '://' . $host . $dir;
}

function putmio_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * URL di un asset locale con versione derivata dal mtime del file.
 *
 * Per CSS/JS la versione finisce nel NOME del file (es. app.v1718900000.css):
 * una regola rewrite in .htaccess la rimappa al file reale. Così, quando il
 * file cambia, cambia anche l'URL e i client (anche le Smart TV, dove svuotare
 * la cache è scomodo) scaricano sempre l'ultima versione. Per altre estensioni
 * si usa una query string. Se il file non esiste, l'URL non viene versionato.
 */
function putmio_asset(string $relativePath): string
{
    $relativePath = ltrim($relativePath, '/');
    $base = rtrim(\PutMio\Config::get('app.url', putmio_detect_base_url()), '/');
    $mtime = @filemtime(putmio_base_path() . '/' . $relativePath);
    if ($mtime === false) {
        return $base . '/' . $relativePath;
    }
    $dotPos = strrpos($relativePath, '.');
    if ($dotPos === false) {
        return $base . '/' . $relativePath . '?v=' . $mtime;
    }
    $name = substr($relativePath, 0, $dotPos);
    $ext = substr($relativePath, $dotPos + 1);
    if ($ext === 'css' || $ext === 'js') {
        return $base . '/' . $name . '.v' . $mtime . '.' . $ext;
    }
    return $base . '/' . $relativePath . '?v=' . $mtime;
}

function putmio_redirect(string $path): void
{
    $base = putmio_is_installed()
        ? rtrim(\PutMio\Config::get('app.url', putmio_detect_base_url()), '/')
        : rtrim(putmio_detect_base_url(), '/');
    if (str_starts_with($path, '?')) {
        $target = $base . '/' . $path;
    } elseif (str_starts_with($path, 'http')) {
        $target = $path;
    } else {
        $target = $base . '/' . ltrim($path, '/');
    }
    header('Location: ' . $target, true, 302);
    exit;
}

function putmio_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function putmio_random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function putmio_video_extensions(): array
{
    return ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'm4v', 'webm', 'ts', 'mpeg', 'mpg'];
}

/** Titolo suggerito da un nome file release (null se non riconosciuto). */
function putmio_guess_title_from_filename(string $filename, ?string $folderName = null): ?string
{
    return \PutMio\Media\ReleaseNameParser::guessTitle($filename, $folderName);
}

function putmio_is_video_file(string $name, ?string $mime = null): bool
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (in_array($ext, putmio_video_extensions(), true)) {
        return true;
    }
    if ($mime && str_starts_with($mime, 'video/')) {
        return true;
    }
    return false;
}

function putmio_stream_mime_type(?string $fileName, ?string $mime = null): string
{
    if ($mime !== null && $mime !== '' && str_starts_with($mime, 'video/')) {
        return $mime;
    }

    $ext = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
    $map = [
        'mp4' => 'video/mp4',
        'm4v' => 'video/mp4',
        'mov' => 'video/quicktime',
        'webm' => 'video/webm',
        'mkv' => 'video/x-matroska',
        'avi' => 'video/x-msvideo',
        'wmv' => 'video/x-ms-wmv',
        'mpeg' => 'video/mpeg',
        'mpg' => 'video/mpeg',
        'ts' => 'video/mp2t',
    ];

    return $map[$ext] ?? 'video/mp4';
}

/** MIME usato dal player HTML5 (hint per contenitori non nativi del browser). */
function putmio_browser_playback_mime(?string $fileName, ?string $mime = null): string
{
    $ext = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
    $native = ['mp4', 'm4v', 'webm', 'mov', 'mpeg', 'mpg'];

    if (in_array($ext, $native, true)) {
        return putmio_stream_mime_type($fileName, $mime);
    }

    return 'video/mp4';
}

/** Codec audio non riproducibili nel browser senza transcoding. */
function putmio_has_unsupported_browser_audio(?string $fileName): bool
{
    if ($fileName === null || $fileName === '') {
        return false;
    }

    $upper = strtoupper($fileName);
    return (bool) preg_match('/\b(AC3|DD5\.?1|EAC3|DDP|DTS|DTS-HD|TRUEHD|ATMOS)\b/', $upper);
}

/** @return array<string, array{native: string, html: string}> */
function putmio_available_locales(): array
{
    return [
        'it' => ['native' => 'Italiano', 'html' => 'it'],
        'en' => ['native' => 'English', 'html' => 'en'],
    ];
}

function putmio_locale(): string
{
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved;
    }

    $available = putmio_available_locales();
    $candidate = $_SESSION['user_locale'] ?? $_COOKIE['putmio_locale'] ?? 'it';
    $resolved = isset($available[$candidate]) ? $candidate : 'it';

    return $resolved;
}

function putmio_set_locale(string $locale): void
{
    if (!isset(putmio_available_locales()[$locale])) {
        return;
    }

    $_SESSION['user_locale'] = $locale;

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    setcookie('putmio_locale', $locale, [
        'expires' => time() + 86400 * 365,
        'path' => '/',
        'secure' => $secure,
        'httponly' => false,
        'samesite' => 'Strict',
    ]);
}

function putmio_is_tv_user_agent(?string $ua = null): bool
{
    $ua = $ua ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '') {
        return false;
    }

    $patterns = [
        'Tizen',
        'SmartTV',
        'Smart-TV',
        'Android TV',
        'GoogleTV',
        'Apple TV',
        'tvOS',
        'Web0S',
        'WebOS',
        'HbbTV',
        'AFT',
    ];

    foreach ($patterns as $needle) {
        if (stripos($ua, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function putmio_tv_mode(): bool
{
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved;
    }

    if (putmio_is_tv_user_agent()) {
        return $resolved = true;
    }

    $sessionMode = $_SESSION['user_ui_mode'] ?? null;
    if ($sessionMode === 'tv' || $sessionMode === 'standard') {
        return $resolved = $sessionMode === 'tv';
    }

    $cookieMode = $_COOKIE['putmio_ui_mode'] ?? null;
    if ($cookieMode === 'tv' || $cookieMode === 'standard') {
        return $resolved = $cookieMode === 'tv';
    }

    return $resolved = false;
}

function putmio_admin_ui_enabled(): bool
{
    return \PutMio\Auth\Session::isAdmin() && !putmio_tv_mode();
}

function putmio_player_preload(?string $value = null): string
{
    $allowed = ['none', 'metadata', 'auto'];

    if ($value === null) {
        $value = (string) \PutMio\Config::get('app.player_preload', 'none');
    }

    $value = strtolower(trim($value));

    return in_array($value, $allowed, true) ? $value : 'none';
}

function putmio_lang(string $key, array $replace = []): string
{
    static $strings = [];
    static $loadedLocale = null;

    $locale = putmio_locale();
    if ($loadedLocale !== $locale) {
        $basePath = putmio_base_path() . '/lang';
        $fallback = require $basePath . '/it.php';
        $path = $basePath . '/' . $locale . '.php';
        $strings = is_file($path) ? require $path : [];
        if ($locale !== 'it') {
            $strings = array_merge($fallback, $strings);
        } else {
            $strings = $fallback;
        }
        $loadedLocale = $locale;
    }

    $text = $strings[$key] ?? $key;
    foreach ($replace as $k => $v) {
        $text = str_replace(':' . $k, (string) $v, $text);
    }
    return $text;
}

function putmio_log(string $message): void
{
    $dir = putmio_base_path() . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);
}

function putmio_encrypt(string $plain, string $key): string
{
    $keyBin = hash('sha256', $key, true);
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $keyBin, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        throw new RuntimeException('Crittografia fallita');
    }
    return base64_encode($iv . $cipher);
}

function putmio_decrypt(string $encoded, string $key): string
{
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 17) {
        throw new RuntimeException('Token non valido');
    }
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $keyBin = hash('sha256', $key, true);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $keyBin, OPENSSL_RAW_DATA, $iv);
    if ($plain === false) {
        throw new RuntimeException('Decrittografia fallita');
    }
    return $plain;
}

function putmio_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }
    if ($bytes < 1073741824) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    return round($bytes / 1073741824, 2) . ' GB';
}

function putmio_normalize_table_prefix(string $prefix): string
{
    $prefix = preg_replace('/[^a-z0-9_]/i', '', $prefix) ?: 'pm';
    if (!str_ends_with($prefix, '_')) {
        $prefix .= '_';
    }
    return strtolower($prefix);
}

function putmio_sanitize_db_name(string $name): string
{
    return preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?: '';
}

function putmio_format_duration(int $seconds): string
{
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    if ($h > 0) {
        return sprintf('%d:%02d:%02d', $h, $m, $s);
    }
    return sprintf('%d:%02d', $m, $s);
}

function putmio_format_runtime_label(?int $seconds): ?string
{
    if ($seconds === null || $seconds <= 0) {
        return null;
    }
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    if ($h > 0) {
        return $h . 'h ' . $m . 'min';
    }
    return $m . ' min';
}

function putmio_format_runtime_short(?int $seconds): ?string
{
    if ($seconds === null || $seconds <= 0) {
        return null;
    }
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    if ($h > 0) {
        return $m > 0 ? $h . 'h ' . $m . 'm' : $h . 'h';
    }

    return $m . 'm';
}

/** @param array<string, mixed> $episode */
function putmio_episode_card_title(array $episode): string
{
    $raw = trim((string) ($episode['title'] ?? ''));
    if (preg_match('/^S\d{2}E\d{2}\s*·\s*(.+)$/u', $raw, $matches)) {
        return trim($matches[1]);
    }
    if (preg_match('/^S\d{2}E\d{2}$/u', $raw)) {
        $number = (int) ($episode['episode_number'] ?? 0);

        return $number > 0 ? putmio_lang('episode') . ' ' . $number : $raw;
    }

    return $raw !== '' ? $raw : putmio_lang('episode');
}

/** @param array<string, mixed> $episode */
function putmio_episode_code(array $episode): string
{
    return sprintf(
        'S%02dE%02d',
        (int) ($episode['season_number'] ?? 0),
        (int) ($episode['episode_number'] ?? 0)
    );
}

/** @return array{ext: ?string, codec: ?string, resolution: ?string} */
function putmio_file_technical_labels(?string $fileName): array
{
    $ext = $fileName ? strtoupper(pathinfo($fileName, PATHINFO_EXTENSION)) : '';
    $codec = null;
    $resolution = null;

    if ($fileName !== null && $fileName !== '') {
        $upper = strtoupper($fileName);
        if (preg_match('/\b(X264|H\.?264|AVC)\b/', $upper)) {
            $codec = 'H.264';
        } elseif (preg_match('/\b(X265|H\.?265|HEVC)\b/', $upper)) {
            $codec = 'H.265';
        } elseif (preg_match('/\bXVID\b/', $upper)) {
            $codec = 'XviD';
        }

        if (preg_match('/\b2160P?\b/', $upper)) {
            $resolution = '4K UHD';
        } elseif (preg_match('/\b1080P?\b/', $upper)) {
            $resolution = '1080p Full HD';
        } elseif (preg_match('/\b720P?\b/', $upper)) {
            $resolution = '720p HD';
        }
    }

    return [
        'ext' => $ext !== '' ? $ext : null,
        'codec' => $codec,
        'resolution' => $resolution,
    ];
}

/** Contenuto con metadati TMDB applicati. */
function putmio_media_is_linked(array $media): bool
{
    return !empty($media['tmdb_id']);
}

function putmio_request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    if (putmio_is_installed()) {
        $base = rtrim(parse_url(\PutMio\Config::get('app.url', putmio_detect_base_url()), PHP_URL_PATH) ?? '', '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base)) ?: '/';
        }
    }
    $uri = '/' . trim($uri, '/');
    return $uri === '//' ? '/' : ($uri === '' ? '/' : $uri);
}

function putmio_nav_is_active(string $prefix): bool
{
    $path = putmio_request_path();
    if ($prefix === '/') {
        return $path === '/';
    }
    return str_starts_with($path, $prefix);
}

/** Percorso catalogo con filtri (es. `/catalogo?q=batman&type=film`). */
function putmio_catalog_path(array $filters, int $page = 1): string
{
    $query = array_filter([
        'q' => trim((string) ($filters['q'] ?? '')) ?: null,
        'type' => trim((string) ($filters['type'] ?? '')) ?: null,
        'genre' => trim((string) ($filters['genre'] ?? '')) ?: null,
        'shared_by' => trim((string) ($filters['shared_by'] ?? '')) ?: null,
        'page' => $page > 1 ? (string) $page : null,
    ], static fn ($v) => $v !== null && $v !== '');

    $path = '/catalogo';
    if ($query !== []) {
        $path .= '?' . http_build_query($query);
    }

    return $path;
}

/** Valida un percorso di ritorno al catalogo (anti open-redirect). */
function putmio_sanitize_catalog_return(?string $from): ?string
{
    if ($from === null || trim($from) === '') {
        return null;
    }
    $from = rawurldecode(trim($from));
    if (str_contains($from, '://') || str_starts_with($from, '//')) {
        return null;
    }
    if ($from !== '/catalogo' && !str_starts_with($from, '/catalogo?')) {
        return null;
    }
    $parts = parse_url($from);
    if ($parts === false || ($parts['path'] ?? '') !== '/catalogo') {
        return null;
    }

    $allowed = ['q', 'type', 'genre', 'shared_by', 'page'];
    parse_str($parts['query'] ?? '', $query);
    $clean = [];
    foreach ($allowed as $key) {
        if (!isset($query[$key]) || $query[$key] === '') {
            continue;
        }
        $clean[$key] = (string) $query[$key];
    }

    return putmio_catalog_path([
        'q' => $clean['q'] ?? null,
        'type' => $clean['type'] ?? null,
        'genre' => $clean['genre'] ?? null,
        'shared_by' => $clean['shared_by'] ?? null,
    ], max(1, (int) ($clean['page'] ?? 1)));
}

function putmio_catalog_return_url(?string $from = null): string
{
    $base = rtrim(\PutMio\Config::get('app.url', putmio_detect_base_url()), '/');
    $path = putmio_sanitize_catalog_return($from) ?? '/catalogo';

    return $base . $path;
}

/** Tipologia effettiva per la UI (media_type DB o inferenza da TMDB). */
function putmio_resolve_media_type(array $item): ?string
{
    $type = (string) ($item['media_type'] ?? 'altro');
    if ($type !== 'altro') {
        return $type;
    }
    return putmio_media_type_from_tmdb(
        (string) ($item['tmdb_type'] ?? ''),
        is_array($item['tmdb_genres'] ?? null) ? $item['tmdb_genres'] : []
    );
}

/** @param list<array{id?: int, name?: string}> $genres */
function putmio_media_type_from_tmdb(string $tmdbType, array $genres = []): ?string
{
    foreach ($genres as $genre) {
        if ((int) ($genre['id'] ?? 0) === 16) {
            return 'animazione';
        }
    }
    if ($tmdbType === 'tv') {
        return 'serie';
    }
    if ($tmdbType === 'movie') {
        return 'film';
    }
    return null;
}

/** Sottotitolo card catalogo: «Tipologia • Genere/Anno» come da design Stitch */
function putmio_catalog_subtitle(array $item): string
{
    $type = putmio_resolve_media_type($item);
    $typeLabel = $type !== null ? putmio_lang($type) : null;

    $secondary = null;
    $genreNames = trim((string) ($item['genre_names'] ?? ''));
    if ($genreNames !== '') {
        $secondary = explode(', ', $genreNames)[0];
    } elseif (!empty($item['year'])) {
        $secondary = (string) (int) $item['year'];
    }

    if ($typeLabel !== null && $secondary !== null) {
        return $typeLabel . ' • ' . $secondary;
    }
    if ($secondary !== null) {
        return $secondary;
    }
    if ($typeLabel !== null) {
        return $typeLabel;
    }
    return putmio_lang('unclassified');
}

/** Nick put.io del proprietario per contenuti condivisi (null se tuo). */
function putmio_catalog_owner_nick(array $item): ?string
{
    $owner = trim((string) ($item['shared_by_username'] ?? ''));
    return $owner !== '' ? $owner : null;
}

/** @return array{0: string, 1: string} classi badge poster (sfondo, testo) */
function putmio_media_badge_classes(string $type): array
{
    switch ($type) {
        case 'serie':
            return ['bg-primary/80 backdrop-blur-md', 'text-on-primary'];
        case 'animazione':
            return ['bg-tertiary-container/80 backdrop-blur-md', 'text-on-tertiary-container'];
        default:
            return ['bg-background/60 backdrop-blur-md', 'text-white'];
    }
}

/** Sezione admin attiva (null se fuori dall'area admin). */
function putmio_admin_section(): ?string
{
    if (!\PutMio\Auth\Session::isAdmin()) {
        return null;
    }
    $path = putmio_request_path();
    if ($path === '/admin') {
        return 'dashboard';
    }
    if (str_starts_with($path, '/admin/impostazioni')) {
        return 'settings';
    }
    if (str_starts_with($path, '/admin/classificazione')) {
        return 'classify';
    }
    if (str_starts_with($path, '/admin/streaming')) {
        return 'streaming';
    }
    if (str_starts_with($path, '/admin/sincronizzazioni')) {
        return 'sync-log';
    }
    if (str_starts_with($path, '/admin/utenti')) {
        return 'users';
    }
    if (str_starts_with($path, '/admin/aggiornamenti')) {
        return 'updates';
    }
    if (str_starts_with($path, '/admin/dispositivi')) {
        return 'devices';
    }
    return null;
}

function putmio_account_section(): ?string
{
    if (!\PutMio\Auth\Session::userId() || \PutMio\Auth\Session::isAdmin()) {
        return null;
    }
    $path = putmio_request_path();
    if ($path === '/account') {
        return 'general';
    }
    if (str_starts_with($path, '/account/dispositivi')) {
        return 'devices';
    }
    if (str_starts_with($path, '/account/contenuti')) {
        return 'content';
    }
    return null;
}

function putmio_account_nav_link_class(string $section): string
{
    $active = putmio_account_section() === $section;
    if ($active) {
        return 'flex items-center gap-4 px-4 py-3 rounded-lg text-primary font-bold border-r-4 border-primary bg-primary/5 transition-transform hover:translate-x-1';
    }
    return 'flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:bg-surface-variant/20 transition-transform hover:translate-x-1';
}

/** @return array{devices: list<array<string, mixed>>, currentDeviceId: ?int} */
function putmio_user_devices_context(int $userId): array
{
    $devices = \PutMio\Auth\TrustedDevice::listForUser($userId);
    $currentDeviceId = null;

    $raw = (string) ($_COOKIE['putmio_device'] ?? '');
    if (preg_match('/^([a-f0-9]{32}):/', $raw, $matches)) {
        $selector = $matches[1];
        $pdo = \PutMio\Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id FROM `' . \PutMio\Config::table('user_devices') . '`
             WHERE user_id = ? AND selector = ? AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$userId, $selector]);
        $row = $stmt->fetch();
        if ($row) {
            $currentDeviceId = (int) $row['id'];
        }
    }

    return [
        'devices' => $devices,
        'currentDeviceId' => $currentDeviceId,
    ];
}

function putmio_device_icon_for_label(string $label): string
{
    $lower = mb_strtolower($label);
    if (str_contains($lower, 'tv') || str_contains($lower, 'webos') || str_contains($lower, 'tizen')) {
        return 'tv';
    }
    if (str_contains($lower, 'android')) {
        return 'android';
    }
    if (str_contains($lower, 'iphone') || str_contains($lower, 'ipad') || str_contains($lower, 'apple')) {
        return 'phone_iphone';
    }
    if (str_contains($lower, 'mobile') || str_contains($lower, 'tablet') || str_contains($lower, 'telefono')) {
        return 'smartphone';
    }
    if (str_contains($lower, 'console') || str_contains($lower, 'playstation') || str_contains($lower, 'xbox')) {
        return 'sports_esports';
    }
    return 'devices';
}

function putmio_classify_pending_count(?\PDO $pdo = null): int
{
    $pdo = $pdo ?? \PutMio\Database::pdo();
    $mediaTable = \PutMio\Config::table('media_items');

    return (int) $pdo->query(
        "SELECT COUNT(*) FROM `{$mediaTable}` mi
         WHERE mi.classification_status = 'unclassified'
           AND mi.series_id IS NULL
           AND (
             mi.putio_file_id IS NOT NULL
             OR EXISTS (
               SELECT 1 FROM `{$mediaTable}` ep
               WHERE ep.series_id = mi.id AND ep.classification_status = 'unclassified'
             )
           )"
    )->fetchColumn();
}

/** @return array{unclassified: int} */
function putmio_admin_nav_stats(): array
{
    static $stats = null;
    if ($stats !== null) {
        return $stats;
    }
    if (!putmio_is_installed() || !\PutMio\Auth\Session::isAdmin()) {
        return $stats = ['unclassified' => 0];
    }
    try {
        $stats = [
            'unclassified' => putmio_classify_pending_count(),
        ];
    } catch (\Throwable $e) {
        $stats = ['unclassified' => 0];
    }
    return $stats;
}

function putmio_admin_nav_link_class(string $section): string
{
    $active = putmio_admin_section() === $section;
    if ($active) {
        return 'flex items-center gap-4 px-4 py-3 rounded-lg text-primary font-bold border-r-4 border-primary bg-primary/5 transition-transform hover:translate-x-1';
    }
    return 'flex items-center gap-4 px-4 py-3 rounded-lg text-on-surface-variant hover:bg-surface-variant/20 transition-transform hover:translate-x-1';
}

function putmio_session_duration_label(?string $startedAt): string
{
    if (!$startedAt) {
        return '—';
    }
    $ts = strtotime($startedAt);
    if (!$ts) {
        return '—';
    }
    return putmio_format_duration(max(0, time() - $ts));
}

function putmio_stream_bitrate_label(int $bytesSent, ?string $startedAt): string
{
    if (!$startedAt || $bytesSent <= 0) {
        return '—';
    }
    $ts = strtotime($startedAt);
    if (!$ts) {
        return '—';
    }
    $seconds = max(1, time() - $ts);
    $mbps = ($bytesSent * 8) / $seconds / 1_000_000;
    if ($mbps < 0.1) {
        return round($mbps * 1000, 1) . ' Kbps';
    }
    return round($mbps, 1) . ' Mbps';
}

function putmio_format_admin_datetime(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    $ts = strtotime($datetime);
    if (!$ts) {
        return '—';
    }
    if (putmio_locale() === 'en') {
        return date('M j, Y, H:i', $ts);
    }
    $months = [1 => 'gen', 2 => 'feb', 3 => 'mar', 4 => 'apr', 5 => 'mag', 6 => 'giu', 7 => 'lug', 8 => 'ago', 9 => 'set', 10 => 'ott', 11 => 'nov', 12 => 'dic'];
    return (int) date('j', $ts) . ' ' . ($months[(int) date('n', $ts)] ?? '') . ' ' . date('Y, H:i', $ts);
}

function putmio_user_role_label(string $role): string
{
    if ($role === 'admin') {
        return putmio_lang('role_admin');
    }
    if ($role === 'user') {
        return putmio_lang('role_user');
    }
    return $role;
}

/** Normalizza la lingua restituita da put.io (nome o codice) in codice ISO 639-1. */
function putmio_putio_subtitle_language_code(string $language): string
{
    $language = trim($language);
    if ($language === '') {
        return 'und';
    }

    if (preg_match('/^[a-z]{2,3}(-[a-z]{2})?$/i', $language) === 1) {
        return strtolower($language);
    }

    $map = [
        'italian' => 'it',
        'english' => 'en',
        'spanish' => 'es',
        'french' => 'fr',
        'german' => 'de',
        'portuguese' => 'pt',
        'russian' => 'ru',
        'japanese' => 'ja',
        'korean' => 'ko',
        'chinese' => 'zh',
        'arabic' => 'ar',
        'dutch' => 'nl',
        'polish' => 'pl',
        'swedish' => 'sv',
        'norwegian' => 'no',
        'danish' => 'da',
        'finnish' => 'fi',
        'greek' => 'el',
        'turkish' => 'tr',
        'romanian' => 'ro',
        'hungarian' => 'hu',
        'czech' => 'cs',
        'catalan' => 'ca',
        'brazilian portuguese' => 'pt-br',
    ];

    $key = strtolower($language);

    return $map[$key] ?? 'und';
}

/**
 * @param array{language?: string, name?: string, source?: string} $subtitle
 */
function putmio_putio_subtitle_label(array $subtitle): string
{
    $lang = putmio_putio_subtitle_language_code((string) ($subtitle['language'] ?? ''));
    $label = putmio_subtitle_language_label($lang);
    $name = trim((string) ($subtitle['name'] ?? ''));
    if ($name !== '' && stripos($label, $name) === false) {
        $label .= ' · ' . $name;
    }

    $source = strtolower(trim((string) ($subtitle['source'] ?? '')));
    if ($source === 'mkv') {
        $label .= ' (MKV)';
    } elseif ($source === 'folder') {
        $label .= ' (file)';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($label, 0, 80);
    }

    return substr($label, 0, 80);
}

/** Etichetta lingua sottotitoli da codice ISO 639-1. */
function putmio_subtitle_language_label(string $code): string
{
    $code = strtolower(trim($code));
    $map = [
        'it' => 'Italiano',
        'en' => 'English',
        'es' => 'Español',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'pt' => 'Português',
        'pt-br' => 'Português (BR)',
        'ru' => 'Русский',
        'ja' => '日本語',
        'ko' => '한국어',
        'zh' => '中文',
        'zh-cn' => '中文 (简体)',
        'zh-tw' => '中文 (繁體)',
        'ar' => 'العربية',
        'nl' => 'Nederlands',
        'pl' => 'Polski',
        'sv' => 'Svenska',
        'no' => 'Norsk',
        'da' => 'Dansk',
        'fi' => 'Suomi',
        'el' => 'Ελληνικά',
        'tr' => 'Türkçe',
        'ro' => 'Română',
        'hu' => 'Magyar',
        'cs' => 'Čeština',
        'und' => 'Sconosciuta',
    ];

    return $map[$code] ?? strtoupper($code);
}

/** @return list<array<string, mixed>> */
function putmio_subtitle_payload_list(array $rows, string $appUrl): array
{
    $list = [];
    foreach ($rows as $row) {
        $list[] = [
            'id' => (int) $row['id'],
            'language' => (string) ($row['language'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'serveUrl' => $appUrl . '/subtitles/serve?id=' . (int) $row['id'],
            'downloadedBy' => (string) ($row['downloaded_by_name'] ?? ''),
            'createdAt' => (string) ($row['created_at'] ?? ''),
        ];
    }

    return $list;
}
