<?php
/**
 * Jobs Controller - DST Recruitment System
 */

class Jobs extends Controller {
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Daftar Lowongan - Untuk Kandidat
     */
    public function index() {
        // SEO friendly: jobs, lowongan
        $search = sanitize($_GET['q'] ?? '');
        $location = sanitize($_GET['location'] ?? '');
        
        $where = "j.status = 'open'";
        $params = [];
        
        if (!empty($search)) {
            $where .= " AND (j.title LIKE ? OR j.description LIKE ? OR j.skills LIKE ?)";
            $search_param = "%$search%";
            $params = [$search_param, $search_param, $search_param];
        }
        
        if (!empty($location)) {
            $where .= " AND j.location = ?";
            $params[] = $location;
        }
        
        $jobs = db()->select(
            "SELECT j.job_id as id, j.*, 
                    (SELECT COUNT(*) FROM applications WHERE job_id = j.job_id) as applicant_count,
                    u.full_name as created_by_name
             FROM jobs j
             JOIN users u ON j.created_by = u.user_id
             WHERE $where
             ORDER BY j.created_at DESC",
            $params
        );
        
        // Ambil semua lokasi unik untuk filter
        $locations = db()->select("SELECT DISTINCT location FROM jobs WHERE status = 'open' ORDER BY location");
        
        $data = [
            'title' => 'Lowongan Pekerjaan - DST Recruitment',
            'page' => 'jobs',
            'jobs' => $jobs,
            'locations' => $locations,
            'search' => $search,
            'selected_location' => $location
        ];
        
        $this->view('layouts/header', $data);
        $this->view('jobs/index', $data);
        $this->view('layouts/footer');
    }
    
    /**
     * Detail Lowongan
     */
    public function detail($id = null) {
        if (!$id || !is_numeric($id)) {
            setFlash('error', 'Lowongan tidak ditemukan');
            redirect('jobs');
        }
        
        $job = db()->row(
            "SELECT j.job_id as id, j.*, u.full_name as created_by_name 
             FROM jobs j 
             JOIN users u ON j.created_by = u.user_id 
             WHERE j.job_id = ?",
            [$id]
        );
        
        if (!$job) {
            setFlash('error', 'Lowongan tidak ditemukan');
            redirect('jobs');
        }
        
        // Cek apakah user sudah melamar
        $applied = null;
        if (isLoggedIn() && !hasRole('hrd') && !hasRole('admin')) {
            $applied = db()->row(
                "SELECT * FROM applications WHERE user_id = ? AND job_id = ?",
                [$_SESSION['user_id'], $id]
            );
        }
        
        // Parse skills
        $job['skills_array'] = $job['skills'] ? explode(',', $job['skills']) : [];
        
        $data = [
            'title' => $job['title'] . ' - DST Recruitment',
            'page' => 'job-detail',
            'job' => $job,
            'applied' => $applied
        ];
        
        $this->view('layouts/header', $data);
        $this->view('jobs/detail', $data);
        $this->view('layouts/footer');
    }
    
    /**
     * Lamar Lowongan
     */
    public function apply($job_id = null) {
        $this->requireLogin();
        
        if (hasRole('hrd') || hasRole('admin')) {
            setFlash('error', 'HRD/Admin tidak bisa melamar pekerjaan');
            redirect('jobs');
        }
        
        if (!$job_id || !is_numeric($job_id)) {
            setFlash('error', 'Lowongan tidak ditemukan');
            redirect('jobs');
        }
        
        // Ambil detail job
        $job = db()->row("SELECT job_id as id, jobs.* FROM jobs WHERE job_id = ? AND status = 'open'", [$job_id]);
        
        if (!$job) {
            setFlash('error', 'Lowongan tidak ditemukan atau sudah ditutup');
            redirect('jobs');
        }
        
        // Cek apakah sudah pernah melamar
        $existing = db()->row(
            "SELECT * FROM applications WHERE user_id = ? AND job_id = ?",
            [$_SESSION['user_id'], $job_id]
        );
        
        if ($existing) {
            setFlash('error', 'Anda sudah melamar posisi ini');
            redirect('jobs/detail/' . $job_id);
        }
        
        // Ambil data profil user
        $user = db()->row("SELECT * FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
        
        $data = [
            'title' => 'Lamar ' . $job['title'] . ' - DST Recruitment',
            'page' => 'apply',
            'job' => $job,
            'user' => $user
        ];
        
        $this->view('layouts/header', $data);
        $this->view('jobs/apply', $data);
        $this->view('layouts/footer');
    }
    
    /**
     * Proses Lamar Lowongan
     */
    public function doApply() {
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('jobs');
        }

        requireValidCsrf();

        if (hasRole('hrd') || hasRole('admin')) {
            setFlash('error', 'HRD/Admin tidak bisa melamar pekerjaan');
            redirect('jobs');
        }
        
        $job_id = intval($_POST['job_id'] ?? 0);
        
        // Verifikasi job exists
        $job = db()->row("SELECT job_id as id, jobs.* FROM jobs WHERE job_id = ? AND status = 'open'", [$job_id]);
        
        if (!$job) {
            setFlash('error', 'Lowongan tidak ditemukan');
            redirect('jobs');
        }
        
        // Cek apakah sudah melamar
        $existing = db()->row(
            "SELECT * FROM applications WHERE user_id = ? AND job_id = ?",
            [$_SESSION['user_id'], $job_id]
        );
        
        if ($existing) {
            setFlash('error', 'Anda sudah melamar posisi ini');
            redirect('jobs/detail/' . $job_id);
        }
        
        if (empty($_FILES['cv_file']['name'])) {
            setFlash('error', 'CV wajib diupload');
            redirect('jobs/apply/' . $job_id);
        }

        // Proses upload CV (wajib)
        $upload_result = uploadFile($_FILES['cv_file'], 'cv');
        if (!$upload_result['success']) {
            setFlash('error', 'CV gagal diupload: ' . $upload_result['message']);
            redirect('jobs/apply/' . $job_id);
        }
        $cv_file = $upload_result['filename'];
        
        // Ambil skills dari profil user
        $user = db()->row("SELECT skills FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
        
        // Hitung skill match score
        $score = 0;
        if ($user['skills'] && $job['skills']) {
            $user_skills = array_map('trim', explode(',', strtolower($user['skills'])));
            $job_skills = array_map('trim', explode(',', strtolower($job['skills'])));
            
            $match = array_intersect($user_skills, $job_skills);
            $score = count($job_skills) > 0 ? round((count($match) / count($job_skills)) * 100) : 0;
        }
        
        // Cover letter (opsional)
        $cover_letter = sanitize($_POST['cover_letter'] ?? '');
        
        // Insert lamaran
        $success = db()->execute(
            "INSERT INTO applications (user_id, job_id, cv_file, cover_letter, status, score, applied_at) 
             VALUES (?, ?, ?, ?, 'pending', ?, NOW())",
            [$_SESSION['user_id'], $job_id, $cv_file, $cover_letter, $score]
        );
        
        if ($success) {
            setFlash('success', 'Lamaran berhasil dikirim! Tim HRD akan mereview aplikasi Anda.');
            redirect('applications');
        } else {
            setFlash('error', 'Gagal mengirim lamaran. Silakan coba lagi.');
            redirect('jobs/apply/' . $job_id);
        }
    }
    
    // ========================
    // Admin Methods
    // ========================
    
    /**
     * Kelola Lowongan - Admin
     */
    public function manage() {
        $this->requireAdmin();
        
        $jobs = db()->select(
            "SELECT j.job_id as id, j.*, 
                    (SELECT COUNT(*) FROM applications WHERE job_id = j.job_id) as applicant_count,
                    (SELECT COUNT(*) FROM applications WHERE job_id = j.job_id AND status = 'accepted') as accepted_count
             FROM jobs j
             ORDER BY j.created_at DESC"
        );
        
        $data = [
            'title' => 'Kelola Lowongan - DST Recruitment',
            'page' => 'manage-jobs',
            'jobs' => $jobs
        ];
        
        $this->view('layouts/header', $data);
        $this->view('jobs/manage', $data);
        $this->view('layouts/footer');
    }
    
    /**
     * Tambah/Edit Lowongan - Admin
     */
    public function form($id = null) {
        $this->requireAdmin();
        
        $job = null;
        if ($id) {
            $job = db()->row("SELECT job_id as id, jobs.* FROM jobs WHERE job_id = ?", [$id]);
            if (!$job) {
                setFlash('error', 'Lowongan tidak ditemukan');
                redirect('jobs/manage');
            }
        }
        
        $data = [
            'title' => ($id ? 'Edit' : 'Tambah') . ' Lowongan - DST Recruitment',
            'page' => 'job-form',
            'job' => $job
        ];
        
        $this->view('layouts/header', $data);
        $this->view('jobs/form', $data);
        $this->view('layouts/footer');
    }
    
    /**
     * Simpan Lowongan - Admin
     */
    public function save() {
        $this->requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('jobs/manage');
        }

        requireValidCsrf();
        
        $id = intval($_POST['id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $department = sanitize($_POST['department'] ?? '');
        $location = sanitize($_POST['location'] ?? '');
        $type = sanitize($_POST['type'] ?? 'Full-time');
        $salary_min = sanitize($_POST['salary_min'] ?? '');
        $salary_max = sanitize($_POST['salary_max'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $requirements = sanitize($_POST['requirements'] ?? '');
        $skills = sanitize($_POST['skills'] ?? '');
        
        // Validasi
        if (empty($title) || empty($location) || empty($description)) {
            setFlash('error', 'Field wajib diisi');
            redirect('jobs/form' . ($id ? '/' . $id : ''));
        }
        
        if ($id) {
            // Update
            $success = db()->execute(
                "UPDATE jobs SET title = ?, department = ?, location = ?, type = ?, 
                 salary_min = ?, salary_max = ?, description = ?, requirements = ?, skills = ?, updated_at = NOW() 
                 WHERE job_id = ?",
                [$title, $department, $location, $type, $salary_min, $salary_max, $description, $requirements, $skills, $id]
            );
            
            if ($success) {
                setFlash('success', 'Lowongan berhasil diperbarui');
            }
        } else {
            // Insert
            $success = db()->execute(
                "INSERT INTO jobs (title, department, location, type, salary_min, salary_max, description, requirements, skills, created_by, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$title, $department, $location, $type, $salary_min, $salary_max, $description, $requirements, $skills, $_SESSION['user_id']]
            );
            
            if ($success) {
                setFlash('success', 'Lowongan berhasil dibuat');
            }
        }
        
        redirect('jobs/manage');
    }
    
    /**
     * Hapus Lowongan - Admin
     */
    public function delete($id) {
        $this->requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('jobs/manage');
        }

        requireValidCsrf();

        if (!$id) {
            redirect('jobs/manage');
        }
        
        // Hapus lowongan (dan aplikasinya akan terhapus karena ON DELETE CASCADE)
        db()->execute("DELETE FROM jobs WHERE job_id = ?", [$id]);
        
        setFlash('success', 'Lowongan berhasil dihapus');
        redirect('jobs/manage');
    }
    
    /**
     * Toggle Status Lowongan
     */
    public function toggleStatus($id) {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('jobs/manage');
        }

        requireValidCsrf();
        
        $job = db()->row("SELECT status FROM jobs WHERE job_id = ?", [$id]);
        
        if ($job) {
            $new_status = $job['status'] === 'open' ? 'closed' : 'open';
            db()->execute("UPDATE jobs SET status = ?, updated_at = NOW() WHERE job_id = ?", [$new_status, $id]);
            setFlash('success', 'Status lowongan diubah');
        }
        
        redirect('jobs/manage');
    }
}
