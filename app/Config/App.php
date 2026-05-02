<?php
/**
 * Legacy App Config
 *
 * Catatan: aplikasi menggunakan helper di app/helpers.php sebagai sumber utama utility.
 * File ini dipertahankan agar tidak menyebabkan warning di editor saat project dibuka.
 */

if (!defined('APP_NAME')) {
    define('APP_NAME', 'DST Recruitment');
}

if (!defined('APP_ENV')) {
    define('APP_ENV', 'development');
}

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', true);
}

if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Jakarta');
}

date_default_timezone_set(APP_TIMEZONE);