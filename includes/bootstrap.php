<?php
// Shared configuration for Apache, PHP-FPM and the CLI.
$bcLocalConfig = [];
if (is_file(__DIR__ . '/config.local.php')) {
    $bcLocalConfig = require __DIR__ . '/config.local.php';
    if (!is_array($bcLocalConfig)) {
        throw new RuntimeException('config.local.php must return an array.');
    }
}

function bcConfig(string $name, string $default = ''): string
{
    global $bcLocalConfig;
    $value = getenv($name);
    return $value !== false ? (string)$value : (string)($bcLocalConfig[$name] ?? $default);
}

function bcBasePath(): string
{
    $configured = bcConfig('BC_BASE_PATH', '__auto__');
    if ($configured !== '__auto__') {
        $base = $configured;
    } else {
        $root = str_replace('\\', '/', dirname(__DIR__));
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $url = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $relative = str_starts_with($script, $root . '/') ? substr($script, strlen($root)) : '';
        $base = $relative !== '' && str_ends_with($url, $relative)
            ? substr($url, 0, -strlen($relative)) : '';
    }
    $base = rtrim($base, '/');
    if ($base !== '' && !preg_match('~^/(?:[A-Za-z0-9_\-]+/)*[A-Za-z0-9_\-]+$~D', $base)) {
        throw new RuntimeException('Invalid BC_BASE_PATH. Use a path such as /BC1, or an empty string.');
    }
    return $base;
}

define('BC_BASE_PATH', bcBasePath());
date_default_timezone_set('Asia/Bangkok');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE && !headers_sent()) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || bcConfig('BC_SECURE_COOKIES') === '1',
        'samesite' => 'Lax',
        'path' => BC_BASE_PATH . '/',
    ]);
}
