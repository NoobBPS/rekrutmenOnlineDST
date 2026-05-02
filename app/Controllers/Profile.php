<?php
/**
 * Profile Controller - DST Recruitment System
 */

class Profile extends Controller {
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Lihat Profil
     */
    public function index() {
        if (!isLoggedIn()) {
            redirect('auth/login');
        }
        
        $user = db()->row(
            "SELECT * FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );
        
        $data = [
            'title' => 'Profil Saya - DST Recruitment',
            'page' => 'profile',
            'user' => $user
        ];
        
        $this->view('layouts/header', $data);
        $this->view('profile/index', $data);
        $this->view('layouts/footer');
    }
    
    /**
     * Edit Profil
     */
    public function edit() {
        if (!isLoggedIn()) {
            redirect('auth/login');
        }
        
        $user = db()->row(
            "SELECT * FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );
        
        $data = [
            'title' => 'Edit Profil - DST Recruitment',
            'page' => 'profile-edit',
            'user' => $user
        ];
        
        $this->view('layouts/header', $data);
        $this->view('profile/edit', $data);
        $this->view('layouts/footer');
    }
    
    /**
     * Simpan Profil
     */
    public function update() {
        if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('auth/login');
        }

        requireValidCsrf();
        
        $full_name = sanitize($_POST['full_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $education = sanitize($_POST['education'] ?? '');
        $skills = sanitize($_POST['skills'] ?? '');
        $bio = sanitize($_POST['bio'] ?? '');
        $experience_years = intval($_POST['experience_years'] ?? 0);
        
        if (empty($full_name)) {
            setFlash('error', 'Nama wajib diisi');
            redirect('profile/edit');
        }
        
        // Upload avatar jika ada
        $avatarFile = null;
        if (!empty($_FILES['avatar']['name'])) {
            $upload = uploadFile($_FILES['avatar'], 'avatars');
            if ($upload['success']) {
                $avatarFile = $upload['filename'];
            } else {
                setFlash('warning', 'Avatar gagal diupload: ' . $upload['message']);
            }
        }
        
        $sql = "UPDATE users SET full_name = ?, phone = ?, education = ?, skills = ?, bio = ?, experience_years = ?";
        $params = [$full_name, $phone, $education, $skills, $bio, $experience_years];

        if ($avatarFile !== null) {
            $sql .= ", avatar = ?";
            $params[] = $avatarFile;
        }

        $sql .= " WHERE id = ?";
        $params[] = $_SESSION['user_id'];
        
        db()->execute($sql, $params);
        
        $_SESSION['full_name'] = $full_name;
        
        setFlash('success', 'Profil berhasil diperbarui');
        redirect('profile');
    }
    
    /**
     * Ganti Password
     */
    public function password() {
        if (!isLoggedIn()) {
            redirect('auth/login');
        }
        
        $data = [
            'title' => 'Ganti Password - DST Recruitment',
            'page' => 'change-password'
        ];
        
        $this->view('layouts/header', $data);
        $this->view('profile/password', $data);
        $this->view('layouts/footer');
    }
    
    /**
     * Proses Ganti Password
     */
    public function updatePassword() {
        if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('auth/login');
        }

        requireValidCsrf();
        
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validasi
        $user = db()->row("SELECT password FROM users WHERE id = ?", [$_SESSION['user_id']]);
        
        if (!verifyPassword($current_password, $user['password'])) {
            setFlash('error', 'Password saat ini salah');
            redirect('profile/password');
        }
        
        if (strlen($new_password) < 6) {
            setFlash('error', 'Password baru minimal 6 karakter');
            redirect('profile/password');
        }
        
        if ($new_password !== $confirm_password) {
            setFlash('error', 'Password baru tidak cocok');
            redirect('profile/password');
        }
        
        db()->execute(
            "UPDATE users SET password = ? WHERE id = ?",
            [hashPassword($new_password), $_SESSION['user_id']]
        );
        
        setFlash('success', 'Password berhasil diperbarui');
        redirect('profile');
    }
}
