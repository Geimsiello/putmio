<?php

declare(strict_types=1);

namespace PutMio;

final class Config
{
    private static ?array $data = null;

    public static function load(?string $path = null): void
    {
        $path = $path ?? putmio_config_path();
        if (!is_file($path)) {
            self::$data = [];
            return;
        }
        $data = require $path;
        if (!is_array($data)) {
            throw new \RuntimeException('config.php non valido');
        }
        self::$data = $data;
    }

    public static function all(): array
    {
        if (self::$data === null) {
            self::load();
        }
        return self::$data ?? [];
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $parts = explode('.', $key);
        $value = self::all();
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function dbPrefix(): string
    {
        return (string) self::get('db.prefix', 'pm_');
    }

    public static function table(string $name): string
    {
        return self::dbPrefix() . $name;
    }
}
