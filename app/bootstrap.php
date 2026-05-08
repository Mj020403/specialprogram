<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));
}
if (!defined('APP_BASE_URL')) {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $projectDir = basename(APP_ROOT);
    $pos = strpos($scriptName, '/' . $projectDir . '/');
    define('APP_BASE_URL', $pos !== false ? '/' . $projectDir : '/harvest');
}

function app_path(string $path = ''): string {
    return APP_ROOT . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function app_require(string $path): void {
    require_once app_path($path);
}

function db_conn(): mysqli {
    if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof mysqli)) {
        require_once app_path('app/config/database.php');
    }
    return $GLOBALS['conn'];
}

function app_url(string $path = ''): string {
    return rtrim(APP_BASE_URL, '/') . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function redirect_to(string $path = ''): void {
    header('Location: ' . app_url($path));
    exit;
}
