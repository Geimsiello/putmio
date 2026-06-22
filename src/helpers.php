<?php

declare(strict_types=1);

function putmio_base_path(): string
{
    return dirname(__DIR__);
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

function putmio_lang(string $key, array $replace = []): string
{
    static $strings = null;
    if ($strings === null) {
        $strings = require putmio_base_path() . '/lang/it.php';
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
