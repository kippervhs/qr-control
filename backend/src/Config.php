<?php

declare(strict_types=1);

namespace QrControl;

final class Config
{
    private static array $values = [];

    public static function load(string $file): void
    {
        if (is_file($file)) {
            $values = parse_ini_file($file, false, INI_SCANNER_RAW);
            if ($values === false) {
                throw new \RuntimeException('Não foi possível ler o arquivo de ambiente.');
            }
            self::$values = $values;
        }
    }

    public static function get(string $key, ?string $default = null): string
    {
        $value = getenv($key);
        $value = $value === false ? (self::$values[$key] ?? $default) : $value;
        if ($value === null || $value === '') {
            throw new \RuntimeException("Configuração obrigatória ausente: {$key}");
        }
        return (string) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = getenv($key);
        $value = $value === false ? (self::$values[$key] ?? null) : $value;
        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public static function appUrl(): string
    {
        $url = rtrim(self::get('APP_URL'), '/');
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new \RuntimeException('APP_URL deve ser uma URL HTTP ou HTTPS válida.');
        }
        return $url;
    }
}
