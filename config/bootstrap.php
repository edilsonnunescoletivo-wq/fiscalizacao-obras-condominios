<?php

declare(strict_types=1);

$root = dirname(__DIR__);

if (file_exists($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
}

spl_autoload_register(function (string $class) use ($root): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $root . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

function env(string $key, mixed $default = null): mixed
{
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $path = dirname(__DIR__) . '/.env';
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$k, $v] = array_map('trim', explode('=', $line, 2));
                $vars[$k] = trim($v, "\"'");
            }
        }
    }
    return $vars[$key] ?? $_ENV[$key] ?? $default;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) env('SESSION_NAME', 'fiscalizacao_obras_session'));
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}
