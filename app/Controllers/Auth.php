<?php
/**
 * Auth Controller - DST Recruitment System
 */

class Auth extends \Controller {
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Login Page
     */
    public function login() {
        if (isLoggedIn()) {
            if (hasRole('admin')) {
                redirect('jobs/manage');
            }

            if (hasRole('hrd')) {
                redirect('dashboard/hrd');
            }

            redirect('dashboard');
        }
        
        $data = [
            'title' => 'Login - DST Recruitment',
            'page' => 'login'
        ];
        
        $this->view('layouts/header', $data);
        $this->view('auth/login', $data);
        $this->view('layouts/footer');
    }
    
    /**
     * Register Page
     */
    public function register() {
        if (isLoggedIn()) {
            redirect('dashboard');
        }
        
        $data = [
            'title' => 'Daftar - DST Recruitment',
            'page' => 'register'
        ];
        
        $this->view('layouts/header', $data);
        $this->view('auth/register', $data);
        $this->view('layouts/footer');
    }
    
    /**
     * Process Login
     */
    public function doLogin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('auth/login');
        }

        requireValidCsrf();
        
        $email = strtolower(trim((string) (isset($_POST['email']) ? $_POST['email'] : '')));
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($email) || empty($password)) {
            setFlash('error', 'Email dan password wajib diisi');
            redirect('auth/login');
        }
        
        $user = db()->row(
            "SELECT * FROM users WHERE LOWER(email) = ?",
            [$email]
        );
        
        $status = isset($user['status']) ? $user['status'] : 'active';
        if (!$user || !verifyPassword($password, $user['password'])) {
            setFlash('error', 'Email atau password salah');
            redirect('auth/login');
        }
        
        if ($status === 'inactive') {
            setFlash('error', 'Akun Anda dinonaktifkan');
            redirect('auth/login');
        }

        $user_id = (int) ($user['user_id'] ?? $user['id'] ?? 0);
        if ($user_id <= 0) {
            setFlash('error', 'Akun tidak valid');
            redirect('auth/login');
        }
        
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        session_regenerate_id(true);
        
        setFlash('success', 'Selamat datang, ' . $user['full_name'] . '!');
        $role = isset($user['role']) ? $user['role'] : '';

        if ($role === 'admin') {
            redirect('jobs/manage');
        }

        if ($role === 'hrd') {
            redirect('dashboard/hrd');
        }

        redirect('dashboard');
    }
    
    /**
     * Process Register
     */
    public function doRegister() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('auth/register');
        }

        requireValidCsrf();
        
        $email = strtolower(trim((string) (isset($_POST['email']) ? $_POST['email'] : '')));
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $full_name = sanitize(isset($_POST['full_name']) ? $_POST['full_name'] : '');
        
        // Validasi
        if (empty($email) || empty($password) || empty($full_name)) {
            setFlash('error', 'Semua field wajib diisi');
            redirect('auth/register');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Format email tidak valid');
            redirect('auth/register');
        }
        
        if (strlen($password) < 6) {
            setFlash('error', 'Password minimal 6 karakter');
            redirect('auth/register');
        }
        
        if ($password !== $confirm_password) {
            setFlash('error', 'Password tidak cocok');
            redirect('auth/register');
        }
        
        // Cek email sudah terdaftar
        $existing = db()->row(
            "SELECT 1 FROM users WHERE LOWER(email) = ? LIMIT 1",
            [$email]
        );
        
        if ($existing) {
            setFlash('error', 'Email sudah terdaftar');
            redirect('auth/register');
        }
        
        // Insert user baru
        $hashed_password = hashPassword($password);
        
        $success = db()->execute(
            "INSERT INTO users (email, password, full_name, role) VALUES (?, ?, ?, 'user')",
            [$email, $hashed_password, $full_name]
        );
        
        if ($success) {
            // Do not auto-login the user after registration. Redirect to the login page
            // so they can explicitly authenticate. This avoids creating sessions before
            // email verification or profile completion workflows.
            setFlash('success', 'Pendaftaran berhasil! Silakan login untuk melanjutkan.');
            redirect('auth/login');
        } else {
            setFlash('error', 'Pendaftaran gagal. Silakan coba lagi.');
            redirect('auth/register');
        }
    }
    
    /**
     * Logout
     */
    public function logout() {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        session_start();
        setFlash('success', 'Anda telah logout');
        redirect('auth/login');
    }
    
    /**
     * Forgot Password - Show email form
     */
    public function forgot() {
        $data = [
            'title' => 'Lupa Password - DST Recruitment',
            'page' => 'forgot'
        ];
        
        $this->view('layouts/header', $data);
        $this->view('auth/forgot', $data);
        $this->view('layouts/footer');
    }

    private function ensureResetPasswordSchema() {
        try {
            $tableExists = db()->row(
                "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_token'"
            );
            if (!$tableExists) {
                db()->execute("
                    CREATE TABLE user_token (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        email VARCHAR(255) NOT NULL,
                        token VARCHAR(255) NOT NULL,
                        date_created INT NOT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }
            return true;
        } catch (\Exception $e) {
            error_log('Reset password schema check failed: ' . $e->getMessage());
            return false;
        }
    }

    private function resetPasswordUrl($token) {
        return BASE_URL . 'auth/resetPassword?email=' . rawurlencode($_POST['email']) . '&token=' . rawurlencode($token);
    }

    private function canUseLocalResetFallback() {
        $hostValue = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $host = explode(':', (string) $hostValue)[0];
        $appEnv = strtolower((string) (getenv('APP_ENV') ?: 'development'));

        return $appEnv !== 'production' && function_exists('dstIsLocalHost') && dstIsLocalHost($host);
    }

    private function canShowMailDebug() {
        $debug = strtolower((string) (getenv('APP_DEBUG') ?: 'false'));

        return in_array($debug, ['1', 'true', 'yes', 'on'], true);
    }

    private function resetMailErrorMessage($mailResult) {
        $message = 'Gagal mengirim link reset password. Pastikan konfigurasi SMTP (Host, User, Password, Port) sudah benar di hosting.';

        if ($this->canShowMailDebug() && isset($mailResult['message']) && !empty($mailResult['message'])) {
            $message .= ' Detail: ' . (string) $mailResult['message'];
        }

        return $message;
    }

    /**
     * Process Forgot Password - Generate reset token
     */
    public function doForgot() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('auth/forgot');
        }

        requireValidCsrf();

        $email = strtolower(trim((string) (isset($_POST['email']) ? $_POST['email'] : '')));

        if (empty($email)) {
            setFlash('error', 'Email wajib diisi');
            redirect('auth/forgot');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Format email tidak valid');
            redirect('auth/forgot');
        }

        if (!$this->ensureResetPasswordSchema()) {
            setFlash('error', 'Fitur reset password belum siap di database hosting. Jalankan migrasi forgot password atau hubungi admin.');
            redirect('auth/forgot');
        }

        // Non-existing emails still receive a generic success message.
        $user = db()->row("SELECT user_id, status FROM users WHERE LOWER(email) = ?", [$email]);

        if ($user) {
            if (($user['status'] ?? 'active') === 'inactive') {
                setFlash('error', 'Email belum diaktivasi atau dinonaktifkan!');
                redirect('auth/forgot');
            }

            // Generate token (WPU style)
            if (function_exists('random_bytes')) {
                $token = base64_encode(random_bytes(32));
            } else {
                $token = base64_encode(openssl_random_pseudo_bytes(32));
            }

            // Insert into user_token
            db()->execute(
                "INSERT INTO user_token (email, token, date_created) VALUES (?, ?, ?)",
                [$email, $token, time()]
            );

            // Send reset email
            $resetUrl = $this->resetPasswordUrl($token);
            $mailResult = $this->sendResetEmail($email, $resetUrl);

            if (empty($mailResult['success'])) {
                $mailMessage = $mailResult['message'] ?? 'Unknown mail error';
                error_log('Reset email failed for ' . $email . ': ' . $mailMessage);
                setFlash('error', 'Gagal mengirim email. Detail: ' . $mailMessage);
                redirect('auth/forgot');
            }

            setFlash('success', 'Silakan cek email Anda untuk reset password!');
        } else {
            setFlash('error', 'Email tidak terdaftar!');
        }

        redirect('auth/forgot');
    }

    /**
     * Reset Password - Verify token and redirect to change password
     */
    public function resetPassword() {
        $email = trim((string) (isset($_GET['email']) ? $_GET['email'] : ''));
        $token = trim((string) (isset($_GET['token']) ? $_GET['token'] : ''));

        if (!$this->ensureResetPasswordSchema()) {
            setFlash('error', 'Fitur reset password belum siap di database.');
            redirect('auth/login');
        }

        $user = db()->row("SELECT email FROM users WHERE LOWER(email) = ?", [strtolower($email)]);

        if ($user) {
            $user_token = db()->row("SELECT * FROM user_token WHERE token = ?", [$token]);

            if ($user_token) {
                // Check if token is older than 24 hours (86400 seconds)
                if (time() - (int)$user_token['date_created'] < (60 * 60 * 24)) {
                    $_SESSION['reset_email'] = $email;
                    redirect('auth/changePassword');
                } else {
                    db()->execute("DELETE FROM user_token WHERE email = ?", [$email]);
                    setFlash('error', 'Reset password gagal! Token kedaluwarsa.');
                    redirect('auth/login');
                }
            } else {
                setFlash('error', 'Reset password gagal! Token salah.');
                redirect('auth/login');
            }
        } else {
            setFlash('error', 'Reset password gagal! Email salah.');
            redirect('auth/login');
        }
    }

    /**
     * Change Password Page
     */
    public function changePassword() {
        if (!isset($_SESSION['reset_email'])) {
            redirect('auth/login');
        }

        $data = [
            'title' => 'Ganti Password - DST Recruitment',
            'page' => 'reset-password',
            'email' => $_SESSION['reset_email']
        ];

        $this->view('layouts/header', $data);
        $this->view('auth/change-password', $data);
        $this->view('layouts/footer');
    }

    /**
     * Process Change Password
     */
    public function doChangePassword() {
        if (!isset($_SESSION['reset_email'])) {
            redirect('auth/login');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('auth/changePassword');
        }

        requireValidCsrf();

        $password = isset($_POST['password1']) ? $_POST['password1'] : (isset($_POST['password']) ? $_POST['password'] : '');
        $confirm_password = isset($_POST['password2']) ? $_POST['password2'] : (isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '');

        if (strlen($password) < 6) {
            setFlash('error', 'Password terlalu pendek!');
            redirect('auth/changePassword');
        }

        if ($password !== $confirm_password) {
            setFlash('error', 'Password tidak cocok!');
            redirect('auth/changePassword');
        }

        // Update password and clear token
        $hashed = hashPassword($password);
        $email = $_SESSION['reset_email'];

        db()->execute(
            "UPDATE users SET password = ?, updated_at = NOW() WHERE LOWER(email) = ?",
            [$hashed, strtolower($email)]
        );

        unset($_SESSION['reset_email']);
        db()->execute("DELETE FROM user_token WHERE LOWER(email) = ?", [strtolower($email)]);

        setFlash('success', 'Password berhasil diubah! Silakan login.');
        redirect('auth/login');
    }

    /**
     * Send password reset email via Gmail SMTP
     */
    private function sendResetEmail($email, $resetUrl) {
        require_once APPPATH . 'helpers/mail.php';

        $safeResetUrl = h($resetUrl);
        $subject = 'Reset Password - DST Recruitment';

        $body = "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;'>";
        $body .= "<div style='max-width:500px;margin:0 auto;padding:20px;'>";
        $body .= "<div style='text-align:center;margin-bottom:20px;'>";
        $body .= "<img src='" . BASE_URL . "assets/images/logoDST.png' alt='DST' style='width:60px;'>";
        $body .= "</div>";
        $body .= "<h2 style='color:#0f5e5e;text-align:center;'>Reset Password</h2>";
        $body .= "<p>Anda telah meminta reset password untuk akun DST Recruitment.</p>";
        $body .= "<p style='text-align:center;margin:30px 0;'>";
        $body .= "<a href=\"{$safeResetUrl}\" style='display:inline-block;padding:14px 32px;background:#0f5e5e;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;'>Reset Password</a>";
        $body .= "</p>";
        $body .= "<p style='color:#666;font-size:13px;'>Atau salin link ini ke browser:<br><a href=\"{$safeResetUrl}\" style='color:#0f5e5e;'>{$safeResetUrl}</a></p>";
        $body .= "<p style='color:#666;font-size:13px;'>Link ini berlaku selama <strong>1 jam</strong>.</p>";
        $body .= "<hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>";
        $body .= "<p style='color:#999;font-size:12px;text-align:center;'>DST Recruitment - PT Digdaya Solusi Teknologi</p>";
        $body .= "</div></body></html>";

        return sendMail($email, $subject, $body);
    }
}
