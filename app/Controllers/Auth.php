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

        // Always show success message to prevent email enumeration
        $user = db()->row("SELECT user_id FROM users WHERE LOWER(email) = ?", [$email]);

        if ($user) {
            // Delete old tokens for this email
            db()->execute("DELETE FROM password_resets WHERE email = ?", [$email]);

            // Generate token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            db()->execute(
                "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)",
                [$email, $token, $expires]
            );

            // Send reset email
            $resetUrl = BASE_URL . 'auth/resetPassword?token=' . $token;
            $this->sendResetEmail($email, $resetUrl);

            setFlash('success', 'Link reset password telah dikirim ke email Anda.');
            redirect('auth/resetPassword?token=' . $token);
        } else {
            setFlash('success', 'Jika email terdaftar, link reset password telah dikirim.');
        }

        redirect('auth/forgot');
    }

    /**
     * Reset Password - Show new password form
     */
    public function resetPassword() {
        $token = trim((string) ($_GET['token'] ?? ''));

        if (empty($token)) {
            setFlash('error', 'Token tidak valid');
            redirect('auth/login');
        }

        $reset = db()->row(
            "SELECT email, expires_at FROM password_resets WHERE token = ?",
            [$token]
        );

        if (!$reset) {
            setFlash('error', 'Token tidak ditemukan atau sudah digunakan');
            redirect('auth/login');
        }

        if (strtotime($reset['expires_at']) < time()) {
            db()->execute("DELETE FROM password_resets WHERE token = ?", [$token]);
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

        $token = trim((string) ($_POST['token'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($token)) {
            setFlash('error', 'Token tidak valid');
            redirect('auth/login');
        }

        $reset = db()->row(
            "SELECT email, expires_at FROM password_resets WHERE token = ?",
            [$token]
        );

        if (!$reset) {
            setFlash('error', 'Token tidak ditemukan atau sudah digunakan');
            redirect('auth/login');
        }

        if (strtotime($reset['expires_at']) < time()) {
            db()->execute("DELETE FROM password_resets WHERE token = ?", [$token]);
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

        // Update password
        $hashed = hashPassword($password);
        $email = $reset['email'];

        db()->execute(
            "UPDATE users SET password = ?, updated_at = NOW() WHERE LOWER(email) = ?",
            [$hashed, strtolower($email)]
        );

        // Delete the token
        db()->execute("DELETE FROM password_resets WHERE token = ?", [$token]);

        // Also delete any other tokens for this email
        db()->execute("DELETE FROM password_resets WHERE email = ?", [$email]);

        setFlash('success', 'Password berhasil diubah! Silakan login dengan password baru.');
        redirect('auth/login');
    }

    /**
     * Send password reset email
     */
    private function sendResetEmail(string $email, string $resetUrl): void {
        require_once APPPATH . 'Config/Mail.php';

        $subject = 'Reset Password - DST Recruitment';
        $boundary = md5(time());

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= "<!DOCTYPE html><html><body>";
        $body .= "<h2>Reset Password</h2>";
        $body .= "<p>Anda telah meminta reset password untuk akun DST Recruitment.</p>";
        $body .= "<p>Klik link di bawah untuk mengatur password baru:</p>";
        $body .= "<p><a href=\"{$resetUrl}\" style=\"display:inline-block;padding:12px 24px;background:#0f5e5e;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;\">Reset Password</a></p>";
        $body .= "<p>Atau salin link ini ke browser: {$resetUrl}</p>";
        $body .= "<p>Link ini berlaku selama 1 jam.</p>";
        $body .= "<p>Jika Anda tidak meminta reset password, abaikan email ini.</p>";
        $body .= "<hr><p style='color:#888;font-size:12px;'>DST Recruitment - PT Digdaya Solusi Teknologi</p>";
        $body .= "</body></html>";
        $body .= "\r\n--{$boundary}--";

        $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

        @mail($email, $subject, $body, $headers);
    }
}
