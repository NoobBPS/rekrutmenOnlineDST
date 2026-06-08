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
        
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            setFlash('error', 'Email dan password wajib diisi');
            redirect('auth/login');
        }
        
        $user = db()->row(
            "SELECT * FROM users WHERE LOWER(email) = ?",
            [$email]
        );
        
        if (!$user || !verifyPassword($password, $user['password'])) {
            setFlash('error', 'Email atau password salah');
            redirect('auth/login');
        }
        
        if (($user['status'] ?? 'active') === 'inactive') {
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

        if (($user['role'] ?? '') === 'admin') {
            redirect('jobs/manage');
        }

        if (($user['role'] ?? '') === 'hrd') {
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
        
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $full_name = sanitize($_POST['full_name'] ?? '');
        
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

    private function ensureResetPasswordSchema(): bool {
        try {
            $resetTokenColumn = db()->row(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'reset_token'"
            );
            if (!$resetTokenColumn) {
                db()->execute("ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL AFTER status");
            }

            $resetExpiresColumn = db()->row(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'reset_token_expires'"
            );
            if (!$resetExpiresColumn) {
                db()->execute("ALTER TABLE users ADD COLUMN reset_token_expires DATETIME NULL AFTER reset_token");
            }

            $resetTokenIndex = db()->row(
                "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_reset_token' LIMIT 1"
            );
            if (!$resetTokenIndex) {
                db()->execute("CREATE INDEX idx_users_reset_token ON users(reset_token)");
            }

            return true;
        } catch (\Throwable $e) {
            error_log('Reset password schema check failed: ' . $e->getMessage());
            return false;
        }
    }

    private function resetPasswordUrl(string $token): string {
        return BASE_URL . 'auth/resetPassword?token=' . rawurlencode($token);
    }

    private function canUseLocalResetFallback(): bool {
        $host = explode(':', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'))[0];
        $appEnv = strtolower((string) (getenv('APP_ENV') ?: 'development'));

        return $appEnv !== 'production' && function_exists('dstIsLocalHost') && dstIsLocalHost($host);
    }

    /**
     * Process Forgot Password - Generate reset token
     */
    public function doForgot() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('auth/forgot');
        }

        requireValidCsrf();

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));

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
        $user = db()->row("SELECT user_id FROM users WHERE LOWER(email) = ?", [$email]);

        if ($user) {
            // Clear old token for this user
            db()->execute(
                "UPDATE users SET reset_token = NULL, reset_token_expires = NULL WHERE LOWER(email) = ?",
                [$email]
            );

            // Generate token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            db()->execute(
                "UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE LOWER(email) = ?",
                [$token, $expires, $email]
            );

            // Send reset email
            $resetUrl = $this->resetPasswordUrl($token);
            $mailResult = $this->sendResetEmail($email, $resetUrl);

            if (empty($mailResult['success'])) {
                $mailMessage = $mailResult['message'] ?? 'Unknown mail error';
                error_log('Reset email failed for ' . $email . ': ' . $mailMessage);

                if ($this->canUseLocalResetFallback()) {
                    setFlash('success', 'Mode localhost: SMTP belum dikonfigurasi, jadi halaman reset password dibuka langsung untuk testing.');
                    redirect('auth/resetPassword?token=' . rawurlencode($token));
                }

                setFlash('error', 'Gagal mengirim link reset password. Pastikan konfigurasi SMTP hosting sudah benar.');
                redirect('auth/forgot');
            }

            setFlash('success', 'Link reset password telah dikirim ke <strong>' . h($email) . '</strong>. Silakan cek inbox atau folder spam Anda.');
        } else {
            setFlash('success', 'Jika email terdaftar, link reset password telah dikirim. Silakan cek inbox Anda.');
        }

        redirect('auth/forgot');
    }

    /**
     * Reset Password - Show new password form
     */
    public function resetPassword() {
        $token = trim((string) ($_GET['token'] ?? ''));

        if (!$this->ensureResetPasswordSchema()) {
            setFlash('error', 'Fitur reset password belum siap di database hosting. Jalankan migrasi forgot password atau hubungi admin.');
            redirect('auth/login');
        }

        if (empty($token)) {
            setFlash('error', 'Token tidak valid');
            redirect('auth/login');
        }

        $reset = db()->row(
            "SELECT email, reset_token_expires FROM users WHERE reset_token = ?",
            [$token]
        );

        if (!$reset) {
            setFlash('error', 'Token tidak ditemukan atau sudah digunakan');
            redirect('auth/login');
        }

        if (strtotime($reset['reset_token_expires']) < time()) {
            db()->execute(
                "UPDATE users SET reset_token = NULL, reset_token_expires = NULL WHERE reset_token = ?",
                [$token]
            );
            setFlash('error', 'Token sudah kedaluwarsa. Silakan minta link baru.');
            redirect('auth/forgot');
        }

        $data = [
            'title' => 'Reset Password - DST Recruitment',
            'page' => 'reset-password',
            'token' => $token,
            'email' => $reset['email']
        ];

        $this->view('layouts/header', $data);
        $this->view('auth/reset', $data);
        $this->view('layouts/footer');
    }

    /**
     * Process Reset Password
     */
    public function doResetPassword() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('auth/login');
        }

        requireValidCsrf();

        if (!$this->ensureResetPasswordSchema()) {
            setFlash('error', 'Fitur reset password belum siap di database hosting. Jalankan migrasi forgot password atau hubungi admin.');
            redirect('auth/login');
        }

        $token = trim((string) ($_POST['token'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($token)) {
            setFlash('error', 'Token tidak valid');
            redirect('auth/login');
        }

        $reset = db()->row(
            "SELECT email, reset_token_expires FROM users WHERE reset_token = ?",
            [$token]
        );

        if (!$reset) {
            setFlash('error', 'Token tidak ditemukan atau sudah digunakan');
            redirect('auth/login');
        }

        if (strtotime($reset['reset_token_expires']) < time()) {
            db()->execute(
                "UPDATE users SET reset_token = NULL, reset_token_expires = NULL WHERE reset_token = ?",
                [$token]
            );
            setFlash('error', 'Token sudah kedaluwarsa. Silakan minta link baru.');
            redirect('auth/forgot');
        }

        if (strlen($password) < 6) {
            setFlash('error', 'Password baru minimal 6 karakter');
            redirect('auth/resetPassword?token=' . $token);
        }

        if ($password !== $confirm_password) {
            setFlash('error', 'Password tidak cocok');
            redirect('auth/resetPassword?token=' . $token);
        }

        // Update password and clear token
        $hashed = hashPassword($password);
        $email = $reset['email'];

        db()->execute(
            "UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL, updated_at = NOW() WHERE LOWER(email) = ?",
            [$hashed, strtolower($email)]
        );

        setFlash('success', 'Password berhasil diubah! Silakan login dengan password baru.');
        redirect('auth/login');
    }

    /**
     * Send password reset email via Gmail SMTP
     */
    private function sendResetEmail(string $email, string $resetUrl): array {
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
