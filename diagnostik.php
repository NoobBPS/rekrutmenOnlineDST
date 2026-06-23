<?php
/**
 * DST Recruitment - Hosting Diagnostics
 * Upload file ini ke hosting, akses via browser, lalu hapus setelah selesai.
 * PENTING: Hapus file ini setelah selesai diagnosa!
 */

// Lindungi dengan password sederhana
$password = 'dst_diag_2024';
if (($_GET['key'] ?? '') !== $password) {
    http_response_code(403);
    die('Akses ditolak. Tambahkan ?key=' . $password . ' ke URL');
}

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>DST Diagnostics</title>';
echo '<style>body{font-family:monospace;padding:20px;} .ok{color:green;} .err{color:red;} .warn{color:orange;} table{border-collapse:collapse;} td,th{border:1px solid #ccc;padding:6px 12px;}</style></head><body>';
echo '<h1>DST Recruitment - Diagnostik Hosting</h1>';

// ===========================
// 1. PHP VERSION
// ===========================
echo '<h2>1. Versi PHP</h2>';
$ver = PHP_VERSION;
$major = (int)PHP_MAJOR_VERSION;
$minor = (int)PHP_MINOR_VERSION;
if ($major >= 8 || ($major === 7 && $minor >= 4)) {
    echo '<p class="ok">✅ PHP ' . $ver . ' — Compatible</p>';
} else {
    echo '<p class="err">❌ PHP ' . $ver . ' — TERLALU LAMA! Butuh minimal PHP 7.4</p>';
}

// ===========================
// 2. REQUIRED EXTENSIONS
// ===========================
echo '<h2>2. Ekstensi PHP yang Dibutuhkan</h2><table><tr><th>Ekstensi</th><th>Status</th></tr>';
$required = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl', 'fileinfo', 'session'];
foreach ($required as $ext) {
    $ok = extension_loaded($ext);
    echo '<tr><td>' . $ext . '</td><td class="' . ($ok ? 'ok' : 'err') . '">' . ($ok ? '✅ Aktif' : '❌ TIDAK ADA') . '</td></tr>';
}
echo '</table>';

// ===========================
// 3. DATABASE CONNECTION
// ===========================
echo '<h2>3. Koneksi Database</h2>';
// Load .env
$envPath = __DIR__ . '/.env';
if (!is_file($envPath)) $envPath = __DIR__ . '/.env.production';
$env = [];
if (is_file($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $env[trim($parts[0])] = trim($parts[1], " \t\n\r\0\x0B\"'");
        }
    }
    echo '<p class="ok">✅ File .env ditemukan: ' . $envPath . '</p>';
    echo '<p>DB_HOST: <strong>' . ($env['DB_HOST'] ?? 'tidak diset') . '</strong></p>';
    echo '<p>DB_NAME: <strong>' . ($env['DB_NAME'] ?? 'tidak diset') . '</strong></p>';
    echo '<p>DB_USER: <strong>' . ($env['DB_USER'] ?? 'tidak diset') . '</strong></p>';
    echo '<p>DB_PASS: <strong>' . (empty($env['DB_PASS']) ? '(kosong)' : '***tersembunyi***') . '</strong></p>';
} else {
    echo '<p class="err">❌ File .env TIDAK DITEMUKAN di: ' . __DIR__ . '</p>';
}

try {
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $name = $env['DB_NAME'] ?? 'dst_recruitment';
    $user = $env['DB_USER'] ?? 'root';
    $pass = $env['DB_PASS'] ?? '';
    $port = $env['DB_PORT'] ?? '3306';
    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo '<p class="ok">✅ Koneksi database berhasil!</p>';
    $ver = $pdo->query('SELECT VERSION() as v')->fetch()['v'];
    echo '<p>MySQL Version: <strong>' . $ver . '</strong></p>';

    // Cek tabel
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo '<p>Tabel tersedia: <strong>' . implode(', ', $tables) . '</strong></p>';
} catch (Exception $e) {
    echo '<p class="err">❌ Gagal konek database: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

// ===========================
// 4. FILE & FOLDER PERMISSIONS
// ===========================
echo '<h2>4. Folder & Izin Tulis</h2><table><tr><th>Path</th><th>Exists</th><th>Writable</th></tr>';
$paths = [
    __DIR__ . '/uploads',
    __DIR__ . '/uploads/cv',
    __DIR__ . '/uploads/avatars',
    __DIR__ . '/storage',
    __DIR__ . '/vendor',
    __DIR__ . '/app/Controllers/Chat.php',
];
foreach ($paths as $path) {
    $exists = file_exists($path);
    $writable = $exists && is_writable($path);
    echo '<tr>';
    echo '<td>' . str_replace(__DIR__, '.', $path) . '</td>';
    echo '<td class="' . ($exists ? 'ok' : 'err') . '">' . ($exists ? '✅' : '❌ TIDAK ADA') . '</td>';
    echo '<td class="' . ($writable ? 'ok' : 'warn') . '">' . ($writable ? '✅ Bisa tulis' : '⚠️ Tidak writable') . '</td>';
    echo '</tr>';
}
echo '</table>';

// ===========================
// 5. SESSION & HEADERS
// ===========================
echo '<h2>5. Session</h2>';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo '<p class="ok">✅ Session berjalan (ID: ' . session_id() . ')</p>';

// ===========================
// 6. .HTACCESS / URL REWRITE
// ===========================
echo '<h2>6. URL Rewrite</h2>';
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    $hasRewrite = in_array('mod_rewrite', $modules);
    echo '<p class="' . ($hasRewrite ? 'ok' : 'err') . '">' . ($hasRewrite ? '✅ mod_rewrite aktif' : '❌ mod_rewrite TIDAK aktif — URL routing tidak akan bekerja!') . '</p>';
} else {
    echo '<p class="warn">⚠️ Tidak dapat mengecek mod_rewrite (bukan Apache atau tidak ada akses)</p>';
    echo '<p>Coba akses: <a href="' . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/chat">URL chat</a> untuk test rewrite.</p>';
}

// ===========================
// 7. PHP LIMITS
// ===========================
echo '<h2>7. Konfigurasi PHP (Penting untuk Upload)</h2><table><tr><th>Setting</th><th>Nilai</th></tr>';
$settings = ['upload_max_filesize', 'post_max_size', 'max_execution_time', 'memory_limit', 'display_errors', 'error_reporting', 'session.save_path'];
foreach ($settings as $s) {
    echo '<tr><td>' . $s . '</td><td>' . ini_get($s) . '</td></tr>';
}
echo '</table>';

echo '<hr><p style="color:red;font-weight:bold;">⚠️ HAPUS FILE INI SETELAH SELESAI DIAGNOSA!</p>';
echo '</body></html>';
