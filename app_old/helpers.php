<?php
/**
 * Helper Functions
 */

function dstIsLocalHost($host) {
    $host = strtolower(trim((string) $host));
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function dstRequestScheme() {
    $valProto = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : '';
    $forwardedProto = strtolower(trim(explode(',', (string) $valProto)[0]));
    if (in_array($forwardedProto, ['http', 'https'], true)) {
        return $forwardedProto;
    }

    $cfVisitor = (string) ($_SERVER['HTTP_CF_VISITOR'] ?? '');
    if (stripos($cfVisitor, '"scheme":"https"') !== false) {
        return 'https';
    }

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return 'https';
    }

    if ((string) (isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : '') === '443') {
        return 'https';
    }

    return 'http';
}

function dstDetectedBaseUrl() {
    $scheme = dstRequestScheme();
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $scriptDir = str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/'));
    $basePath = rtrim($scriptDir, '/');

    return $scheme . '://' . $host . ($basePath === '' ? '/' : $basePath . '/');
}

if (!defined('BASE_URL')) {
    $detectedBaseUrl = dstDetectedBaseUrl();
    $baseUrlEnv = trim((string) getenv('BASE_URL'));

    if ($baseUrlEnv !== '') {
        if (!preg_match('#^https?://#i', $baseUrlEnv)) {
            $baseUrlEnv = dstRequestScheme() . '://' . ltrim($baseUrlEnv, '/');
        }

        $configuredHost = parse_url($baseUrlEnv, PHP_URL_HOST) ?: '';
        $requestHost = explode(':', (string) (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost'))[0];

        // If a local .env accidentally reaches hosting, do not generate reset
        // links to localhost. Use the actual request host instead.
        // Also, if the request host doesn't match the configured host (e.g. www vs non-www),
        // use the detected host to prevent CORS and session loss issues.
        if ((dstIsLocalHost($configuredHost) && !dstIsLocalHost($requestHost)) || 
            (!empty($requestHost) && strcasecmp($configuredHost, $requestHost) !== 0)) {
            define('BASE_URL', $detectedBaseUrl);
        } else {
            define('BASE_URL', rtrim($baseUrlEnv, '/') . '/');
        }
    } else {
        define('BASE_URL', $detectedBaseUrl);
    }
}

if (!defined('UPLOAD_ROOT')) {
    define('UPLOAD_ROOT', ROOTPATH . 'uploads/');
}

if (!defined('CSRF_SESSION_KEY')) {
    define('CSRF_SESSION_KEY', 'csrf_token');
}

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }

    return htmlspecialchars(strip_tags(trim((string) $data)), ENT_QUOTES, 'UTF-8');
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . BASE_URL . ltrim($url, '/'));
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function getUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

function setFlash($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function getFlash() {
    $flash = isset($_SESSION['flash']) ? $_SESSION['flash'] : array();
    unset($_SESSION['flash']);
    return $flash;
}

function csrfToken() {
    if (empty($_SESSION[CSRF_SESSION_KEY])) {
        if (function_exists('random_bytes')) {
            $_SESSION[CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
        } else {
            $_SESSION[CSRF_SESSION_KEY] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }
    return $_SESSION[CSRF_SESSION_KEY];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
}

function verifyCsrfToken($token) {
    if (empty($_SESSION[CSRF_SESSION_KEY]) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_SESSION_KEY], $token);
}

function requireValidCsrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlash('error', 'Token keamanan tidak valid. Silakan coba lagi.');
        redirect('dashboard');
    }
}

function uploadFile($file, $folder = 'cv') {
    $result = ['success' => false, 'message' => '', 'filename' => null];

    if (empty($file) || !isset($file['error'])) {
        $result['message'] = 'File tidak ditemukan';
        return $result;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['message'] = 'Upload gagal';
        return $result;
    }

    $folder = trim($folder, '/');
    $targetFolder = UPLOAD_ROOT . $folder . DIRECTORY_SEPARATOR;

    if (!is_dir($targetFolder) && !mkdir($targetFolder, 0755, true) && !is_dir($targetFolder)) {
        $result['message'] = 'Folder upload tidak bisa dibuat';
        return $result;
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $isAvatar = $folder === 'avatars';

    $allowedExtensions = $isAvatar ? ['jpg', 'jpeg', 'png', 'webp'] : ['pdf', 'doc', 'docx'];
    $allowedMime = $isAvatar
        ? ['image/jpeg', 'image/png', 'image/webp']
        : ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    if (!in_array($extension, $allowedExtensions, true)) {
        $result['message'] = 'Ekstensi file tidak diizinkan';
        return $result;
    }

    $maxCvMb = (int) (getenv('MAX_CV_SIZE_MB') ?: 5);
    $maxAvatarMb = (int) (getenv('MAX_AVATAR_SIZE_MB') ?: 2);
    $maxSize = $isAvatar ? $maxAvatarMb * 1024 * 1024 : $maxCvMb * 1024 * 1024;
    if ((int) $file['size'] > $maxSize) {
        $result['message'] = 'Ukuran file melebihi batas';
        return $result;
    }

    $mimeType = $file['type'] ?? '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($file['tmp_name']);
        if (!empty($detected)) {
            $mimeType = $detected;
        }
    }
    if (!in_array($mimeType, $allowedMime, true)) {
        $result['message'] = 'Tipe file tidak valid';
        return $result;
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $target = $targetFolder . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        $result['message'] = 'Gagal menyimpan file';
        return $result;
    }

    $result['success'] = true;
    $result['filename'] = $filename;
    return $result;
}

function uploadPath($folder, $filename) {
    return UPLOAD_ROOT . trim($folder, '/') . DIRECTORY_SEPARATOR . basename($filename);
}

function avatarUrl($avatar = null) {
    $filename = basename((string) $avatar);

    if ($filename === '') {
        return null;
    }

    $path = uploadPath('avatars', $filename);
    if (!is_file($path)) {
        return null;
    }

    return BASE_URL . 'uploads/avatars/' . rawurlencode($filename);
}

function avatarInitial($fullName) {
    $name = trim((string) $fullName);
    if ($name === '') {
        return '?';
    }

    $initial = function_exists('mb_substr') ? mb_substr($name, 0, 1, 'UTF-8') : substr($name, 0, 1);
    return function_exists('mb_strtoupper') ? mb_strtoupper($initial, 'UTF-8') : strtoupper($initial);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

function timeAgo($datetime) {
    if (empty($datetime)) {
        return '-';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '-';
    }

    $diff = time() - $timestamp;

    if ($diff < 60) return 'Baru saja';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    if ($diff < 604800) return floor($diff / 86400) . ' hari lalu';

    return date('d M Y', $timestamp);
}

function formatDate($date) {
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '-';
    }

    return date('d F Y', $timestamp);
}

function statusLabel($status) {
    $labels = [
        'pending' => '<span class="badge badge-warning">Lamaran Baru</span>',
        'screening' => '<span class="badge badge-info">Screening</span>',
        'interview' => '<span class="badge badge-primary">Interview</span>',
        'accepted' => '<span class="badge badge-success">Diterima</span>',
        'rejected' => '<span class="badge badge-danger">Ditolak</span>',
    ];

    return isset($labels[$status]) ? $labels[$status] : h($status);
}
