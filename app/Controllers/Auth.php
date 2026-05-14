<?php
/**
 * Auth Controller - DST Recruitment System
 */

class Auth extends Controller {
    
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
        
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            setFlash('error', 'Email dan password wajib diisi');
            redirect('auth/login');
        }
        
        $user = db()->row(
            "SELECT * FROM users WHERE email = ?",
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
        
        $email = sanitize($_POST['email'] ?? '');
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
            "SELECT id FROM users WHERE email = ?",
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
            $user_id = db()->lastInsertId();
            
            $_SESSION['user_id'] = $user_id;
            $_SESSION['email'] = $email;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['role'] = 'user';
            session_regenerate_id(true);
            
            setFlash('success', 'Pendaftaran berhasil! Silakan lengkapi profil Anda.');
            redirect('profile/edit');
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
     * Forgot Password (placeholder)
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
}
