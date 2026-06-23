<?php
/**
 * Jobs Controller - DST Recruitment System (CI4)
 */

namespace App\Controllers;

class Jobs extends BaseController
{
    /**
     * Daftar Lowongan - Untuk Kandidat
     */
    public function index()
    {
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

        return view('layouts/header', $data) . view('jobs/index', $data) . view('layouts/footer');
    }

    /**
     * Detail Lowongan
     */
    public function detail($id = null)
    {
        if (!$id || !is_numeric($id)) {
            setFlash('error', 'Lowongan tidak ditemukan');
            return redirect()->to(base_url('jobs'));
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
            return redirect()->to(base_url('jobs'));
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

        return view('layouts/header', $data) . view('jobs/detail', $data) . view('layouts/footer');
    }

    /**
     * Lamar Lowongan
     */
    public function apply($job_id = null)
    {
        if ($redirect = $this->requireLogin()) return $redirect;

        if (hasRole('hrd') || hasRole('admin')) {
            setFlash('error', 'HRD/Admin tidak bisa melamar pekerjaan');
            return redirect()->to(base_url('jobs'));
        }

        if (!$job_id || !is_numeric($job_id)) {
            setFlash('error', 'Lowongan tidak ditemukan');
            return redirect()->to(base_url('jobs'));
        }

        // Ambil detail job
        $job = db()->row("SELECT job_id as id, jobs.* FROM jobs WHERE job_id = ? AND status = 'open'", [$job_id]);

        if (!$job) {
            setFlash('error', 'Lowongan tidak ditemukan atau sudah ditutup');
            return redirect()->to(base_url('jobs'));
        }

        // Cek apakah sudah pernah melamar
        $existing = db()->row(
            "SELECT * FROM applications WHERE user_id = ? AND job_id = ?",
            [$_SESSION['user_id'], $job_id]
        );

        if ($existing) {
            setFlash('error', 'Anda sudah melamar posisi ini');
            return redirect()->to(base_url('jobs/detail/' . $job_id));
        }

        // Ambil data profil user
        $user = db()->row("SELECT * FROM users WHERE user_id = ?", [$_SESSION['user_id']]);

        $data = [
            'title' => 'Lamar ' . $job['title'] . ' - DST Recruitment',
            'page' => 'apply',
            'job' => $job,
            'user' => $user
        ];

        return view('layouts/header', $data) . view('jobs/apply', $data) . view('layouts/footer');
    }

    /**
     * Proses Lamar Lowongan
     */
    public function doApply()
    {
        if ($redirect = $this->requireLogin()) return $redirect;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return redirect()->to(base_url('jobs'));
        }

        if (hasRole('hrd') || hasRole('admin')) {
            setFlash('error', 'HRD/Admin tidak bisa melamar pekerjaan');
            return redirect()->to(base_url('jobs'));
        }

        $job_id = intval($_POST['job_id'] ?? 0);

        // Verifikasi job exists
        $job = db()->row("SELECT job_id as id, jobs.* FROM jobs WHERE job_id = ? AND status = 'open'", [$job_id]);

        if (!$job) {
            setFlash('error', 'Lowongan tidak ditemukan');
            return redirect()->to(base_url('jobs'));
        }

        // Cek apakah sudah melamar
        $existing = db()->row(
            "SELECT * FROM applications WHERE user_id = ? AND job_id = ?",
            [$_SESSION['user_id'], $job_id]
        );

        if ($existing) {
            setFlash('error', 'Anda sudah melamar posisi ini');
            return redirect()->to(base_url('jobs/detail/' . $job_id));
        }

        if (empty($_FILES['cv_file']['name'])) {
            setFlash('error', 'CV wajib diupload');
            return redirect()->to(base_url('jobs/apply/' . $job_id));
        }

        // Proses upload CV (wajib)
        $upload_result = uploadFile($_FILES['cv_file'], 'cv');
        if (!$upload_result['success']) {
            setFlash('error', 'CV gagal diupload: ' . $upload_result['message']);
            return redirect()->to(base_url('jobs/apply/' . $job_id));
        }
        $cv_file = $upload_result['filename'];
        $cvPath = uploadPath('cv', $cv_file);
        $cvValidation = $this->validateUploadedCv($cvPath);
        if (empty($cvValidation['valid'])) {
            if (is_file($cvPath)) {
                @unlink($cvPath);
            }
            setFlash('error', 'CV ditolak: ' . ($cvValidation['message'] ?? 'Dokumen tidak valid'));
            return redirect()->to(base_url('jobs/apply/' . $job_id));
        }

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
            return redirect()->to(base_url('applications'));
        } else {
            setFlash('error', 'Gagal mengirim lamaran. Silakan coba lagi.');
            return redirect()->to(base_url('jobs/apply/' . $job_id));
        }
    }

    private function validateUploadedCv($path)
    {
        $path = (string) $path;
        if ($path === '' || !is_file($path)) {
            return ['valid' => false, 'message' => 'File CV tidak ditemukan setelah upload'];
        }

        $size = (int) @filesize($path);
        if ($size > 0 && $size < 1024) {
            return ['valid' => false, 'message' => 'Ukuran CV terlalu kecil dan terindikasi kosong'];
        }

        $text = $this->extractUploadedCvText($path);
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return ['valid' => false, 'message' => 'Isi CV kosong atau tidak terbaca'];
        }

        $tokens = preg_split('/[^a-z0-9\+#]+/i', strtolower($text));
        $tokens = array_values(array_filter(array_unique(array_map('trim', (array) $tokens))));
        if (count($tokens) < 15) {
            return ['valid' => false, 'message' => 'Isi CV terlalu minim untuk dievaluasi'];
        }

        return ['valid' => true, 'message' => 'ok'];
    }

    private function extractUploadedCvText($path)
    {
        $extension = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));

        if ($extension === 'docx') {
            if (!class_exists('ZipArchive')) {
                return '';
            }

            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) {
                return '';
            }

            $xml = (string) $zip->getFromName('word/document.xml');
            $zip->close();
            if ($xml === '') {
                return '';
            }

            return strip_tags(str_replace('</w:p>', "\n", $xml));
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return '';
        }

        if ($extension === 'pdf') {
            return $this->extractTextFromPdfBinary($raw);
        }

        $text = preg_replace('/[^[:print:]\r\n\t]/', ' ', $raw);
        return (string) $text;
    }

    private function extractTextFromPdfBinary($binary)
    {
        $binary = (string) $binary;
        if ($binary === '') {
            return '';
        }

        $text = '';
        if (preg_match_all('/\(([^()]*)\)\s*Tj/s', $binary, $matches)) {
            foreach ($matches[1] as $segment) {
                $text .= ' ' . $this->decodePdfMessageChunk((string) $segment);
            }
        }

        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $binary, $arrayMatches)) {
            foreach ($arrayMatches[1] as $chunk) {
                if (preg_match_all('/\(([^()]*)\)/s', (string) $chunk, $parts)) {
                    foreach ($parts[1] as $segment) {
                        $text .= ' ' . $this->decodePdfMessageChunk((string) $segment);
                    }
                }
            }
        }

        if (trim($text) === '') {
            $text = preg_replace('/[^[:print:]\r\n\t]/', ' ', $binary);
        }

        return (string) $text;
    }

    private function decodePdfMessageChunk($text)
    {
        $text = str_replace(
            ['\\\\', '\(', '\)', '\n', '\r', '\t', '\b', '\f'],
            ['\\', '(', ')', "\n", "\r", "\t", ' ', ' '],
            (string) $text
        );
        $text = preg_replace('/\\\\[0-7]{1,3}/', ' ', (string) $text);
        return (string) preg_replace('/[^[:print:]\r\n\t]/', ' ', (string) $text);
    }

    // ========================
    // Admin Methods
    // ========================

    /**
     * Kelola Lowongan - Admin
     */
    public function manage()
    {
        if ($redirect = $this->requireAdmin()) return $redirect;

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

        return view('layouts/header', $data) . view('jobs/manage', $data) . view('layouts/footer');
    }

    /**
     * Tambah/Edit Lowongan - Admin
     */
    public function form($id = null)
    {
        if ($redirect = $this->requireAdmin()) return $redirect;

        $job = null;
        if ($id) {
            $job = db()->row("SELECT job_id as id, jobs.* FROM jobs WHERE job_id = ?", [$id]);
            if (!$job) {
                setFlash('error', 'Lowongan tidak ditemukan');
                return redirect()->to(base_url('jobs/manage'));
            }
        }

        $data = [
            'title' => ($id ? 'Edit' : 'Tambah') . ' Lowongan - DST Recruitment',
            'page' => 'job-form',
            'job' => $job
        ];

        return view('layouts/header', $data) . view('jobs/form', $data) . view('layouts/footer');
    }

    /**
     * Simpan Lowongan - Admin
     */
    public function save()
    {
        if ($redirect = $this->requireAdmin()) return $redirect;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return redirect()->to(base_url('jobs/manage'));
        }

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
            return redirect()->to(base_url('jobs/form' . ($id ? '/' . $id : '')));
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

        return redirect()->to(base_url('jobs/manage'));
    }

    /**
     * Hapus Lowongan - Admin
     */
    public function delete($id = null)
    {
        if ($redirect = $this->requireAdmin()) return $redirect;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return redirect()->to(base_url('jobs/manage'));
        }

        if (!$id) {
            return redirect()->to(base_url('jobs/manage'));
        }

        // Hapus lowongan (dan aplikasinya akan terhapus karena ON DELETE CASCADE)
        db()->execute("DELETE FROM jobs WHERE job_id = ?", [$id]);

        setFlash('success', 'Lowongan berhasil dihapus');
        return redirect()->to(base_url('jobs/manage'));
    }

    /**
     * Toggle Status Lowongan
     */
    public function toggleStatus($id = null)
    {
        if ($redirect = $this->requireAdmin()) return $redirect;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return redirect()->to(base_url('jobs/manage'));
        }

        $job = db()->row("SELECT status FROM jobs WHERE job_id = ?", [$id]);

        if ($job) {
            $new_status = $job['status'] === 'open' ? 'closed' : 'open';
            db()->execute("UPDATE jobs SET status = ?, updated_at = NOW() WHERE job_id = ?", [$new_status, $id]);
            setFlash('success', 'Status lowongan diubah');
        }

        return redirect()->to(base_url('jobs/manage'));
    }
}
