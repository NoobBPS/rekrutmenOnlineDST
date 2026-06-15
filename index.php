<?php
/**
 * DST Recruitment - Entry Point
 */

define('APPPATH', __DIR__ . '/app/');
define('BASEPATH', __DIR__ . '/system/');
define('ROOTPATH', __DIR__ . '/');

/**
 * Simple .env loader.
 *
 * Hosting deployments sometimes upload .env.production but forget to rename it
 * to .env. Keep .env as the primary file, then use .env.production as a safe
 * fallback when .env is not present.
 */
$envPath = ROOTPATH . '.env';
if (!is_file($envPath)) {
    $envPath = ROOTPATH . '.env.production';
}

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
        if ($key !== '') {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

date_default_timezone_set('Asia/Jakarta');

$isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('dst_session');

// Use a long cookie lifetime (30 days) so the session cookie survives browser
// restarts and tab switches.  The value 0 makes the cookie expire when the
// browser closes which causes the "auto-logout" behaviour reported by users.
$cookieLifetime = 30 * 24 * 60 * 60; // 30 days
session_set_cookie_params([
    'lifetime' => $cookieLifetime,
    'path' => '/',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Increase the server-side session garbage collection max-lifetime so that
// sessions are not prematurely cleaned up while the user is still active.
ini_set('session.gc_maxlifetime', (string) $cookieLifetime);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Keep the session alive on each page load / AJAX request so that long-lived
// tabs do not expire the session unexpectedly.
$_SESSION['last_activity'] = time();

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
