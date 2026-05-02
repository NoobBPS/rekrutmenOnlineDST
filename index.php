<?php
/**
 * DST Recruitment - Entry Point
 */

define('APPPATH', __DIR__ . '/app/');
define('BASEPATH', __DIR__ . '/system/');
define('ROOTPATH', __DIR__ . '/');

/**
 * Simple .env loader
 */
$envPath = ROOTPATH . '.env';
if (is_file($envPath) && is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

date_default_timezone_set('Asia/Jakarta');

$isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('dst_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-XSS-Protection: 1; mode=block');

require_once APPPATH . 'core/Controller.php';
require_once APPPATH . 'core/Model.php';
require_once APPPATH . 'helpers.php';

$route = $_GET['route'] ?? '';
$route = rtrim($route, '/');
$route = $route ?: 'dashboard';

$segments = explode('/', $route);
$controller_name = ucfirst($segments[0] ?? 'Dashboard');
$method = $segments[1] ?? 'index';
$params = array_slice($segments, 2);

$controller_file = APPPATH . 'Controllers/' . $controller_name . '.php';

if (!file_exists($controller_file)) {
    http_response_code(404);
    echo "404 - Controller not found";
    exit;
}

require_once $controller_file;

$controller_class = $controller_name;
$controller = new $controller_class();

if (!method_exists($controller, $method)) {
    http_response_code(404);
    echo "404 - Method not found";
    exit;
}

call_user_func_array([$controller, $method], $params);
