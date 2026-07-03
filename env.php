<?php
declare(strict_types=1);

/**
 * Minimal .env loader. Reads KEY=VALUE pairs from .env (gitignored) into
 * the process environment, then exposes them via env().
 */
function env(string $key, ?string $default = null): ?string
{
    static $loaded = false;

    if (!$loaded) {
        $path = __DIR__ . '/.env';
        if (is_file($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);
                if (strlen($v) >= 2 && (
                    ($v[0] === '"' && $v[-1] === '"') ||
                    ($v[0] === "'" && $v[-1] === "'")
                )) {
                    $v = substr($v, 1, -1);
                }
                if (getenv($k) === false) {
                    putenv("$k=$v");
                }
            }
        }
        $loaded = true;
    }

    $value = getenv($key);
    return $value !== false ? $value : $default;
}
