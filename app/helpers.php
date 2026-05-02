<?php
/**
 * Helper Functions
 */

if (!defined('BASE_URL')) {
    $baseUrlEnv = getenv('BASE_URL');
    if (!empty($baseUrlEnv)) {
        define('BASE_URL', rtrim($baseUrlEnv, '/') . '/');
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $basePath = rtrim($scriptDir, '/');
        define('BASE_URL', $scheme . '://' . $host . ($basePath === '' ? '/' : $basePath . '/'));
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
    return $_SESSION['user_id'] ?? null;
}

function setFlash($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function getFlash() {
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}

function csrfToken() {
    if (empty($_SESSION[CSRF_SESSION_KEY])) {
        $_SESSION[CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
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
        'pending' => '<span class="badge badge-warning">Baru</span>',
        'screening' => '<span class="badge badge-info">Screening</span>',
        'interview' => '<span class="badge badge-primary">Interview</span>',
        'accepted' => '<span class="badge badge-success">Diterima</span>',
        'rejected' => '<span class="badge badge-danger">Ditolak</span>',
    ];

    return $labels[$status] ?? h($status);
}
