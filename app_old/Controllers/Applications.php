<?php
/**
 * Applications Controller - DST Recruitment System
 */

namespace App\Controllers;

class Applications extends BaseController {

    private $sawWeights = [
        'skill' => 0.40,
        'education' => 0.20,
        'experience' => 0.25,
        'activity' => 0.15
    ];
    private $cvAnalysisCache = [];
    
    /**
     * Daftar Lamaran - Untuk Kandidat
     */
    public function index() {
        if (hasRole('hrd')) {
            return $this->hrd();
        }

        if (hasRole('admin')) {
            return redirect()->to(base_url('jobs/manage'));
        }
        
        $user_id = $_SESSION['user_id'];
        
        $applications = db()->select(
            "SELECT a.*, j.title as job_title, j.location, j.type, j.department 
             FROM applications a 
             JOIN jobs j ON a.job_id = j.job_id 
             WHERE a.user_id = ? 
             ORDER BY a.applied_at DESC",
            [$user_id]
        );
        
        // Pisahkan berdasarkan status untuk ditampilkan
        $grouped = [
            'pending' => [],
            'screening' => [],
            'interview' => [],
            'accepted' => [],
            'rejected' => []
        ];
        
        foreach ($applications as $app) {
            if (isset($grouped[$app['status']])) {
                $grouped[$app['status']][] = $app;
            }
        }
        
        $data = [
            'title' => 'Lamaran Saya - DST Recruitment',
            'page' => 'my-applications',
            'applications' => $applications,
            'grouped' => $grouped
        ];
        
        return view('layouts/header', $data) . view('applications/index', $data) . view('layouts/footer');
    }
    
    /**
     * Detail Lamaran
     *
     * @param int|string $id Application id (int or numeric string)
     */
    public function detail($id) {
        $application = db()->row(
            "SELECT a.*, j.title as job_title, j.location, j.type, j.description, j.requirements, j.skills,
                    u.full_name as hrd_name
             FROM applications a
             JOIN jobs j ON a.job_id = j.job_id
             LEFT JOIN users u ON j.created_by = u.user_id
             WHERE a.id = ?",
            [$id]
        );
        
        if (!$application) {
            setFlash('error', 'Lamaran tidak ditemukan');
            return redirect()->to(base_url('applications'));
        }
        
        // Cek akses
        if (!hasRole('hrd') && $application['user_id'] != $_SESSION['user_id']) {
            setFlash('error', 'Anda tidak memiliki akses ke lamaran ini');
            return redirect()->to(base_url('applications'));
        }
        
        // Ambil data pelamar
        $candidate = null;
        $application_saw = null;
        $job_saw_recommendation = null;
        if (hasRole('hrd') || $application['user_id'] == $_SESSION['user_id']) {
            $candidate = db()->row("SELECT * FROM users WHERE user_id = ?", [$application['user_id']]);
        }

        if ($candidate) {
            $applicationsByJob = db()->select(
                "SELECT a.*, u.full_name as candidate_name, u.email as candidate_email,
                        u.education as candidate_education, u.skills as candidate_skills,
                        u.experience_years as candidate_experience_years, u.bio as candidate_bio,
                        j.title as job_title, j.department as job_department, j.skills as job_skills,
                        j.requirements as job_requirements, j.description as job_description
                 FROM applications a
                 JOIN users u ON a.user_id = u.user_id
                 JOIN jobs j ON a.job_id = j.job_id
                 WHERE a.job_id = ?",
                [$application['job_id']]
            );

            $sawRanking = $this->buildSawRankings($applicationsByJob);
            $application_saw = $sawRanking['by_application_id'][$application['id']] ?? null;
            $job_saw_recommendation = $sawRanking['recommendations'][$application['job_id']] ?? null;
        }

        $decision_reason_display = $this->buildDecisionReasonDisplay($application, $application_saw);
        $decision_saw_display = $this->buildDecisionSawDisplay($application, $application_saw);
        
        $data = [
            'title' => 'Detail Lamaran - DST Recruitment',
            'page' => 'application-detail',
            'application' => $application,
            'candidate' => $candidate,
            'application_saw' => $application_saw,
            'job_saw_recommendation' => $job_saw_recommendation,
            'decision_reason_display' => $decision_reason_display,
            'decision_saw_display' => $decision_saw_display
        ];
        
        return view('layouts/header', $data) . view('applications/detail', $data) . view('layouts/footer');
    }
    
    // ========================
    // HRD Methods
    // ========================
    
    /**
     * Daftar Pelamar - HRD
     */
    public function hrd() {
        $status = sanitize($_GET['status'] ?? '');
        $job_id = sanitize($_GET['job_id'] ?? '');
        $skill = sanitize($_GET['skill'] ?? '');
        $education = sanitize($_GET['education'] ?? '');
        
        $where = "1=1";
        $params = [];
        
        if (!empty($status)) {
            $where .= " AND a.status = ?";
            $params[] = $status;
        }
        
        if (!empty($job_id)) {
            $where .= " AND a.job_id = ?";
            $params[] = $job_id;
        }

        if (!empty($skill)) {
            $where .= " AND u.skills LIKE ?";
            $params[] = '%' . $skill . '%';
        }

        if (!empty($education)) {
            $where .= " AND u.education LIKE ?";
            $params[] = '%' . $education . '%';
        }
        
        $applications = db()->select(
            "SELECT a.*, u.full_name as candidate_name, u.email as candidate_email, 
                    u.phone, u.education as candidate_education, u.skills as candidate_skills,
                    u.experience_years as candidate_experience_years, u.bio as candidate_bio, u.avatar as candidate_avatar, u.cv_file as user_cv_file,
                    j.title as job_title, j.location, j.department as job_department, j.skills as job_skills,
                    j.requirements as job_requirements, j.description as job_description
             FROM applications a
             JOIN users u ON a.user_id = u.user_id
             JOIN jobs j ON a.job_id = j.job_id
             WHERE $where
             ORDER BY a.applied_at DESC",
            $params
        );

        $sawRanking = $this->buildSawRankings($applications);
        $applications = $sawRanking['applications'];

        if (!empty($job_id)) {
            usort($applications, function ($left, $right) {
                $leftRank = isset($left['saw_rank']) ? (int) $left['saw_rank'] : PHP_INT_MAX;
                $rightRank = isset($right['saw_rank']) ? (int) $right['saw_rank'] : PHP_INT_MAX;

                if ($leftRank === $rightRank) {
                    return strcmp((string) $right['applied_at'], (string) $left['applied_at']);
                }

                if ($leftRank < $rightRank) return -1;
                if ($leftRank > $rightRank) return 1;
                return 0;
            });
        }
        
        // Semua lowongan untuk filter
        $jobs = db()->select("SELECT job_id as id, title FROM jobs WHERE status = 'open' ORDER BY title");
        
        // Hitung statistik per status
        $stats = array(
            'all' => db()->count('applications', "1=1"),
            'pending' => db()->count('applications', "status = 'pending'"),
            'screening' => db()->count('applications', "status = 'screening'"),
            'interview' => db()->count('applications', "status = 'interview'"),
            'accepted' => db()->count('applications', "status = 'accepted'"),
            'rejected' => db()->count('applications', "status = 'rejected'")
        );
        
        $data = [
            'title' => 'Daftar Pelamar - DST Recruitment',
            'page' => 'hrd-applications',
            'applications' => $applications,
            'jobs' => $jobs,
            'stats' => $stats,
            'filter_status' => $status,
            'filter_job' => $job_id,
            'filter_skill' => $skill,
            'filter_education' => $education,
            'saw_weights' => $this->sawWeights,
            'saw_recommendations' => $sawRanking['recommendations']
        ];
        
        return view('layouts/header', $data) . view('applications/hrd', $data) . view('layouts/footer');
    }

    /**
     * Terapkan rekomendasi SAW otomatis - HRD
     */
    public function applyRecommendation() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return redirect()->to(base_url('applications/hrd'));
        }

        requireValidCsrf();

        $job_id = intval(isset($_POST['job_id']) ? $_POST['job_id'] : 0);
        $target_status = sanitize(isset($_POST['target_status']) ? $_POST['target_status'] : 'accepted');
        $allowed_target = array('screening', 'interview', 'accepted');

        if ($job_id <= 0) {
            setFlash('error', 'Posisi tidak valid untuk rekomendasi SAW');
            return redirect()->to(base_url('applications/hrd'));
        }

        if (!in_array($target_status, $allowed_target)) {
            setFlash('error', 'Status rekomendasi tidak valid');
            return redirect()->to(base_url('applications/hrd?job_id=' . $job_id));
        }

        $applications = db()->select(
            "SELECT a.*, u.full_name as candidate_name, u.email as candidate_email,
                    u.education as candidate_education, u.skills as candidate_skills,
                    u.experience_years as candidate_experience_years, u.bio as candidate_bio,
                    j.title as job_title, j.department as job_department, j.skills as job_skills,
                    j.requirements as job_requirements, j.description as job_description
             FROM applications a
             JOIN users u ON a.user_id = u.user_id
             JOIN jobs j ON a.job_id = j.job_id
             WHERE a.job_id = ?",
            [$job_id]
        );

        if (empty($applications)) {
            setFlash('error', 'Belum ada pelamar pada posisi ini');
            return redirect()->to(base_url('applications/hrd?job_id=' . $job_id));
        }

        $sawRanking = $this->buildSawRankings($applications);
        $recommendation = $sawRanking['recommendations'][$job_id] ?? null;

        if (!$recommendation || empty($recommendation['application_id'])) {
            setFlash('warning', 'Belum ada kandidat yang lolos validasi CV untuk rekomendasi SAW.');
            return redirect()->to(base_url('applications/hrd?job_id=' . $job_id));
        }

        if (empty($recommendation['can_auto_apply'])) {
            $reason = trim((string) ($recommendation['reason'] ?? 'Kandidat terbaik sudah berada pada status final.'));
            setFlash('warning', $reason);
            return redirect()->to(base_url('applications/hrd?job_id=' . $job_id));
        }

        $application_id = (int) $recommendation['application_id'];
        $recommendedSaw = $sawRanking['by_application_id'][$application_id] ?? null;
        $application = db()->row("SELECT id, user_id, notes FROM applications WHERE id = ?", [$application_id]);

        if (!$application) {
            setFlash('error', 'Lamaran rekomendasi tidak ditemukan');
            return redirect()->to(base_url('applications/hrd?job_id=' . $job_id));
        }

        $newNote = "[" . date('d/m/Y H:i') . "] Rekomendasi SAW diterapkan otomatis. "
            . "Skor SAW: " . number_format((float) $recommendation['saw_score'], 2) . "%. "
            . "Status diubah ke " . ucfirst($target_status) . ".";
        $updatedNotes = !empty($application['notes']) ? $application['notes'] . "\n" . $newNote : $newNote;

        $success = false;
        if ($target_status === 'accepted') {
            $acceptedReason = "Diterima karena memenuhi penilaian SAW tertinggi untuk posisi ini.";
            $summaryPayload = $this->buildDecisionSawSummary($recommendedSaw);
            if ($summaryPayload === null) {
                $summaryPayload = json_encode([
                    'score' => (float) $recommendation['saw_score'],
                    'rank' => (int) ($recommendation['rank'] ?? 1),
                    'total_candidates' => (int) ($recommendation['total_candidates'] ?? 0),
                    'weights' => $this->sawWeights,
                    'components' => [
                        'skill' => 0,
                        'education' => 0,
                        'experience' => 0,
                        'activity' => 0
                    ]
                ]);
            }

            $success = db()->execute(
                "UPDATE applications
                 SET status = ?, notes = ?, decision_reason = ?, decision_saw_summary = ?, decision_at = NOW(), updated_at = NOW()
                 WHERE id = ?",
                [$target_status, $updatedNotes, $acceptedReason, $summaryPayload, $application_id]
            );

            // Jika diterima, tolak semua lamaran lain dari kandidat yang sama
            if ($success) {
                $rejectNote = "[" . date('d/m/Y H:i') . "] Status diubah ke Rejected secara otomatis karena kandidat telah diterima di posisi lain melalui rekomendasi SAW.";
                $rejectReason = "Kandidat telah diterima pada posisi lain.";
                
                db()->execute(
                    "UPDATE applications 
                     SET status = 'rejected', 
                         notes = CONCAT(COALESCE(notes, ''), '\\n', ?), 
                         decision_reason = ?, 
                         decision_at = NOW(), 
                         updated_at = NOW() 
                     WHERE user_id = ? AND id != ? AND status NOT IN ('accepted', 'rejected')",
                    [$rejectNote, $rejectReason, $application['user_id'], $application_id]
                );
            }
        } else {
            $success = db()->execute(
                "UPDATE applications 
                 SET status = ?, notes = ?, decision_reason = NULL, decision_saw_summary = NULL, decision_at = NULL, updated_at = NOW() 
                 WHERE id = ?",
                [$target_status, $updatedNotes, $application_id]
            );
        }

        if ($success) {
            setFlash(
                'success',
                'Rekomendasi SAW diterapkan: ' . $recommendation['candidate_name']
                . ' (Skor ' . number_format((float) $recommendation['saw_score'], 2) . '%)'
            );
        } else {
            setFlash('error', 'Gagal menerapkan rekomendasi SAW');
        }

        return redirect()->to(base_url('applications/hrd?job_id=' . $job_id));
    }
    
    /**
     * Update Status Lamaran - HRD
     */
    public function updateStatus() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return redirect()->to(base_url('applications/hrd'));
        }

        requireValidCsrf();
        
        $application_id = intval(isset($_POST['application_id']) ? $_POST['application_id'] : 0);
        $status = sanitize(isset($_POST['status']) ? $_POST['status'] : '');
        $notes = sanitize(isset($_POST['notes']) ? $_POST['notes'] : '');
        $decision_reason = sanitize(isset($_POST['decision_reason']) ? $_POST['decision_reason'] : '');
        
        // Validasi status
        $allowed_status = ['pending', 'screening', 'interview', 'accepted', 'rejected'];
        if (!in_array($status, $allowed_status)) {
            setFlash('error', 'Status tidak valid');
            return redirect()->to(base_url('applications/hrd'));
        }

        $isFinalDecision = in_array($status, ['accepted', 'rejected'], true);
        if ($isFinalDecision && $decision_reason === '') {
            setFlash('error', 'Alasan keputusan wajib diisi untuk status diterima/ditolak');
            return redirect()->to(base_url('applications/detail/' . $application_id));
        }
        
        // Ambil data lamaran sekarang
        $application = db()->row("SELECT * FROM applications WHERE id = ?", [$application_id]);
        
        if (!$application) {
            setFlash('error', 'Lamaran tidak ditemukan');
            return redirect()->to(base_url('applications/hrd'));
        }
        
        $application_saw = null;
        $decision_saw_summary = null;
        $decision_at = null;

        if ($isFinalDecision) {
            $applicationsByJob = db()->select(
                "SELECT a.*, u.full_name as candidate_name, u.email as candidate_email,
                        u.education as candidate_education, u.skills as candidate_skills,
                        u.experience_years as candidate_experience_years, u.bio as candidate_bio,
                        j.title as job_title, j.department as job_department, j.skills as job_skills,
                        j.requirements as job_requirements, j.description as job_description
                 FROM applications a
                 JOIN users u ON a.user_id = u.user_id
                 JOIN jobs j ON a.job_id = j.job_id
                 WHERE a.job_id = ?",
                [$application['job_id']]
            );

            $sawRanking = $this->buildSawRankings($applicationsByJob);
            $application_saw = $sawRanking['by_application_id'][$application_id] ?? null;
            $decision_saw_summary = $this->buildDecisionSawSummary($application_saw);
            $decision_at = date('Y-m-d H:i:s');
        }

        // Build notes dengan timestamp
        $new_note = "[" . date('d/m/Y H:i') . "] Status diubah ke " . ucfirst($status);
        if (!empty($notes)) {
            $new_note .= ". Catatan: " . $notes;
        }
        if ($isFinalDecision) {
            $new_note .= ". Alasan keputusan: " . $decision_reason;
            if (!empty($application_saw['saw_score'])) {
                $new_note .= ". Skor SAW: " . number_format((float) $application_saw['saw_score'], 2) . "%";
            }
        }
        
        $old_notes = $application['notes'] ?? '';
        $updated_notes = $old_notes ? $old_notes . "\n" . $new_note : $new_note;
        
        $success = false;
        if ($isFinalDecision) {
            $success = db()->execute(
                "UPDATE applications 
                 SET status = ?, notes = ?, decision_reason = ?, decision_saw_summary = ?, decision_at = ?, updated_at = NOW() 
                 WHERE id = ?",
                [$status, $updated_notes, $decision_reason, $decision_saw_summary, $decision_at, $application_id]
            );

            // Jika diterima, tolak semua lamaran lain dari kandidat yang sama
            if ($success && $status === 'accepted') {
                $rejectNote = "[" . date('d/m/Y H:i') . "] Status diubah ke Rejected secara otomatis karena kandidat telah diterima di posisi lain.";
                $rejectReason = "Kandidat telah diterima pada posisi lain.";
                
                db()->execute(
                    "UPDATE applications 
                     SET status = 'rejected', 
                         notes = CONCAT(COALESCE(notes, ''), '\\n', ?), 
                         decision_reason = ?, 
                         decision_at = NOW(), 
                         updated_at = NOW() 
                     WHERE user_id = ? AND id != ? AND status NOT IN ('accepted', 'rejected')",
                    [$rejectNote, $rejectReason, $application['user_id'], $application_id]
                );
            }
        } else {
            $success = db()->execute(
                "UPDATE applications 
                 SET status = ?, notes = ?, decision_reason = NULL, decision_saw_summary = NULL, decision_at = NULL, updated_at = NOW() 
                 WHERE id = ?",
                [$status, $updated_notes, $application_id]
            );
        }
        
        if ($success) {
            setFlash('success', 'Status lamaran berhasil diperbarui');
        } else {
            setFlash('error', 'Gagal memperbarui status');
        }
        
        return redirect()->to(base_url('applications/hrd'));
    }
    
    /**
     * Simpan Catatan - HRD
     */
    public function saveNotes() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return redirect()->to(base_url('applications/hrd'));
        }

        requireValidCsrf();
        
        $application_id = intval($_POST['application_id'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? '');
        
        db()->execute(
            "UPDATE applications SET notes = ?, updated_at = NOW() WHERE id = ?",
            [$notes, $application_id]
        );
        
        setFlash('success', 'Catatan disimpan');
        return redirect()->to(base_url('applications/detail/' . $application_id));
    }
    
    /**
     * Tambah Catatan Interaktif - HRD (AJAX)
     */
    public function addNote() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid request']);
        }

        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Token keamanan tidak valid']);
        }
        
        $application_id = intval($_POST['application_id'] ?? 0);
        $note = sanitize($_POST['note'] ?? '');
        
        if (empty($note)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Catatan tidak boleh kosong']);
        }
        
        $application = db()->row("SELECT notes FROM applications WHERE id = ?", [$application_id]);
        
        if (!$application) {
            return $this->response->setJSON(['success' => false, 'message' => 'Lamaran tidak ditemukan']);
        }
        
        $new_note = "[" . date('d/m/Y H:i') . " " . $_SESSION['full_name'] . "] " . $note;
        $updated_notes = $application['notes'] ? $application['notes'] . "\n" . $new_note : $new_note;
        
        db()->execute(
            "UPDATE applications SET notes = ?, updated_at = NOW() WHERE id = ?",
            [$updated_notes, $application_id]
        );
        
        return $this->response->setJSON(['success' => true, 'message' => 'Catatan ditambahkan']);
    }

    /**
     * Download CV (authorized)
     *
     * @param int|string $application_id Application id
     */
    public function downloadCv($application_id) {
        $application_id = intval($application_id);
        if ($application_id <= 0) {
            setFlash('error', 'Lamaran tidak ditemukan');
            return redirect()->to(base_url('applications'));
        }

        $application = db()->row(
            "SELECT id, user_id, cv_file FROM applications WHERE id = ?",
            [$application_id]
        );

        if (!$application || empty($application['cv_file'])) {
            setFlash('error', 'CV tidak tersedia');
            return redirect()->to(base_url('applications/detail/' . $application_id));
        }

        if (!hasRole('hrd') && (int) $application['user_id'] !== (int) $_SESSION['user_id']) {
            setFlash('error', 'Akses ditolak');
            return redirect()->to(base_url('applications'));
        }

        $filename = basename($application['cv_file']);
        $path = uploadPath('cv', $filename);

        // Backward compatibility if old files are still in /cv folder.
        if (!is_file($path)) {
            $legacyPath = ROOTPATH . 'cv/' . $filename;
            if (is_file($legacyPath)) {
                $path = $legacyPath;
            }
        }

        if (!is_file($path)) {
            setFlash('error', 'File CV tidak ditemukan di server');
            return redirect()->to(base_url('applications/detail/' . $application_id));
        }

        $mimeType = function_exists('mime_content_type') ? mime_content_type($path) : false;
        $mimeType = $mimeType ?: 'application/octet-stream';
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($path);
        exit;
    }

    /**
     * Build human-readable reason text for a final decision
     *
     * @param array<string,mixed> $application Application row array
     * @param array<string,mixed>|null $applicationSaw SAW analysis for this application (if available)
     * @return string
     */
    private function buildDecisionReasonDisplay($application, $applicationSaw = null) {
        $status = (string) ($application['status'] ?? '');
        if (!in_array($status, ['accepted', 'rejected'], true)) {
            return '';
        }

        $storedReason = trim((string) ($application['decision_reason'] ?? ''));
        if ($storedReason !== '') {
            return $storedReason;
        }

        if ($status === 'accepted') {
            return 'Anda memenuhi penilaian SAW dan kebutuhan posisi ini berdasarkan evaluasi HRD.';
        }

        return 'Saat ini kandidat lain memiliki nilai SAW yang lebih tinggi untuk posisi ini.';
    }

    /**
     * Build a JSON summary payload for SAW decision storage
     *
     * @param array<string,mixed>|null $applicationSaw
     * @return string|null JSON string or null when no SAW data
     */
    private function buildDecisionSawSummary($applicationSaw) {
        if (empty($applicationSaw)) {
            return null;
        }

        $summary = [
            'score' => (float) ($applicationSaw['saw_score'] ?? 0),
            'rank' => (int) ($applicationSaw['saw_rank'] ?? 0),
            'total_candidates' => (int) ($applicationSaw['saw_total_candidates'] ?? 0),
            'weights' => $this->sawWeights,
            'components' => [
                'skill' => (float) ($applicationSaw['saw_components']['skill'] ?? 0),
                'education' => (float) ($applicationSaw['saw_components']['education'] ?? 0),
                'experience' => (float) ($applicationSaw['saw_components']['experience'] ?? 0),
                'activity' => (float) ($applicationSaw['saw_components']['activity'] ?? 0)
            ]
        ];

        return json_encode($summary);
    }

    /**
     * Build displayable SAW summary for decision pages
     *
     * @param array<string,mixed> $application
     * @param array<string,mixed>|null $applicationSaw
     * @return array<string,mixed>|null
     */
    private function buildDecisionSawDisplay($application, $applicationSaw = null) {
        $status = (string) ($application['status'] ?? '');
        if (!in_array($status, ['accepted', 'rejected'], true)) {
            return null;
        }

        $stored = trim((string) ($application['decision_saw_summary'] ?? ''));
        if ($stored !== '') {
            $decoded = json_decode($stored, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (empty($applicationSaw)) {
            $fallbackScore = (float) ($application['score'] ?? 0);
            return [
                'score' => $fallbackScore,
                'rank' => 0,
                'total_candidates' => 0,
                'weights' => $this->sawWeights,
                'components' => [
                    'skill' => $fallbackScore,
                    'education' => 0,
                    'experience' => 0,
                    'activity' => 0
                ]
            ];
        }

        return json_decode((string) $this->buildDecisionSawSummary($applicationSaw), true);
    }

    /**
     * Build SAW ranking per posisi (job_id)
     *
     * @param array<int,array<string,mixed>> $applications Array of application rows
     * @return array{applications: array<int,array<string,mixed>>, recommendations: array<int,array<string,mixed>>, by_application_id: array<int,array<string,mixed>>}
     */
    private function buildSawRankings($applications) {
        if (empty($applications)) {
            return [
                'applications' => [],
                'recommendations' => [],
                'by_application_id' => []
            ];
        }

        $grouped = [];
        foreach ($applications as $application) {
            $jobId = (int) ($application['job_id'] ?? 0);
            if ($jobId <= 0) {
                continue;
            }
            if (!isset($grouped[$jobId])) {
                $grouped[$jobId] = [];
            }
            $grouped[$jobId][] = $application;
        }

        $enrichedById = [];
        $recommendations = [];

        foreach ($grouped as $jobId => $jobApplications) {
            $processed = [];
            $maxValues = [
                'skill' => 0.0,
                'education' => 0.0,
                'experience' => 0.0,
                'activity' => 0.0
            ];

            foreach ($jobApplications as $application) {
                $criteria = $this->calculateSawCriteria($application);
                $cvAnalysis = isset($criteria['cv_analysis']) && is_array($criteria['cv_analysis'])
                    ? $criteria['cv_analysis']
                    : [];

                foreach ($maxValues as $criterion => $value) {
                    $maxValues[$criterion] = max($maxValues[$criterion], (float) $criteria[$criterion]);
                }

                $application['saw_components'] = $criteria;
                $application['saw_normalized'] = [];
                $application['saw_score'] = 0;
                $application['saw_score_base'] = 0;
                $application['is_saw_recommended'] = false;
                $application['saw_rank'] = null;
                $application['saw_total_candidates'] = count($jobApplications);
                $application['saw_cv_quality_factor'] = max(0.15, min((float) ($cvAnalysis['quality_factor'] ?? 1.0), 1.0));
                $application['saw_cv_job_relevance'] = (float) ($cvAnalysis['job_relevance'] ?? 0);
                $application['saw_cv_profile_consistency'] = (float) ($cvAnalysis['profile_consistency'] ?? 0);
                $application['saw_cv_summary'] = (string) ($cvAnalysis['summary'] ?? '');
                $application['saw_cv_disqualified'] = !empty($cvAnalysis['is_disqualified']);
                $application['saw_cv_passed'] = empty($application['saw_cv_disqualified']);
                $application['saw_eligible'] = $this->candidateIsEligibleForRecommendation($application['status'] ?? 'pending')
                    && !empty($application['saw_cv_passed']);
                $application['saw_display_eligible'] = $this->candidateCanAppearInRecommendationDisplay($application['status'] ?? 'pending')
                    && !empty($application['saw_cv_passed']);

                $processed[] = $application;
            }

            foreach ($processed as &$application) {
                $normalized = [];
                $weighted = 0.0;

                foreach ($this->sawWeights as $criterion => $weight) {
                    $rawValue = (float) ($application['saw_components'][$criterion] ?? 0);
                    $maxValue = (float) ($maxValues[$criterion] ?? 0);
                    $normalizedValue = $maxValue > 0 ? ($rawValue / $maxValue) : 0.0;
                    $normalized[$criterion] = round($normalizedValue, 4);
                    $weighted += $normalizedValue * $weight;
                }

                $qualityFactor = max(0.15, min((float) ($application['saw_cv_quality_factor'] ?? 1.0), 1.0));
                if (!empty($application['saw_cv_disqualified'])) {
                    $qualityFactor = 0.0;
                }
                $application['saw_normalized'] = $normalized;
                $application['saw_score_base'] = round($weighted * 100, 2);
                $application['saw_score'] = round($weighted * $qualityFactor * 100, 2);
            }
            unset($application);

            usort($processed, function ($left, $right) {
                $leftScore = (float) ($left['saw_score'] ?? 0);
                $rightScore = (float) ($right['saw_score'] ?? 0);

                if (abs($rightScore - $leftScore) < 0.0001) {
                    return strcmp((string) ($left['applied_at'] ?? ''), (string) ($right['applied_at'] ?? ''));
                }

            if ($rightScore < $leftScore) return -1;
            if ($rightScore > $leftScore) return 1;
            return 0;
            });

            $rank = 1;
            foreach ($processed as &$application) {
                $application['saw_rank'] = $rank++;
            }
            unset($application);

            $recommended = null;
            $displayRecommendedIndex = null;
            foreach ($processed as &$application) {
                if ($displayRecommendedIndex === null && !empty($application['saw_display_eligible'])) {
                    $displayRecommendedIndex = (int) ($application['id'] ?? 0);
                }

                if ($recommended === null && !empty($application['saw_eligible'])) {
                    $application['is_saw_recommended'] = true;
                    $recommended = [
                        'job_id' => $jobId,
                        'job_title' => $application['job_title'] ?? '',
                        'application_id' => (int) $application['id'],
                        'candidate_name' => $application['candidate_name'] ?? '',
                        'candidate_email' => $application['candidate_email'] ?? '',
                        'saw_score' => (float) $application['saw_score'],
                        'rank' => (int) $application['saw_rank']
                    ];
                }
                $enrichedById[(int) $application['id']] = $application;
            }
            unset($application);

            if (!empty($processed)) {
                if ($displayRecommendedIndex === null) {
                    foreach ($processed as $candidateRow) {
                        if (!empty($candidateRow['saw_cv_passed'])) {
                            $displayRecommendedIndex = (int) ($candidateRow['id'] ?? 0);
                            break;
                        }
                    }
                }

                if ($displayRecommendedIndex !== null) {
                    $displayRecommended = $enrichedById[$displayRecommendedIndex] ?? null;
                    if ($displayRecommended !== null) {
                        $displayRecommended['is_saw_recommended'] = true;
                        $enrichedById[$displayRecommendedIndex] = $displayRecommended;

                        $canAutoApply = !empty($displayRecommended['saw_eligible']);
                        $recommendationReason = null;
                        if (!$canAutoApply) {
                            $recommendationReason = 'Kandidat terbaik sudah berada pada status final sehingga rekomendasi otomatis tidak dapat diterapkan.';
                        }

                        $recommended = [
                            'job_id' => $jobId,
                            'job_title' => $displayRecommended['job_title'] ?? '',
                            'application_id' => (int) ($displayRecommended['id'] ?? 0),
                            'candidate_name' => $displayRecommended['candidate_name'] ?? '',
                            'candidate_email' => $displayRecommended['candidate_email'] ?? '',
                            'saw_score' => (float) ($displayRecommended['saw_score'] ?? 0),
                            'rank' => (int) ($displayRecommended['saw_rank'] ?? 0),
                            'can_auto_apply' => $canAutoApply,
                            'reason' => $recommendationReason
                        ];
                    }
                } else {
                    $fallback = $processed[0];
                    $recommended = [
                        'job_id' => $jobId,
                        'job_title' => $fallback['job_title'] ?? '',
                        'application_id' => null,
                        'candidate_name' => null,
                        'candidate_email' => null,
                        'saw_score' => null,
                        'rank' => null,
                        'can_auto_apply' => false,
                        'reason' => 'Belum ada kandidat yang lolos validasi CV untuk rekomendasi otomatis.'
                    ];
                }
            }

            if ($recommended !== null) {
                $recommended['total_candidates'] = count($processed);
                $recommendations[$jobId] = $recommended;
            }
        }

        $enrichedApplications = [];
        foreach ($applications as $application) {
            $id = (int) ($application['id'] ?? 0);
            if ($id > 0 && isset($enrichedById[$id])) {
                $enrichedApplications[] = $enrichedById[$id];
            } else {
                $enrichedApplications[] = $application;
            }
        }

        return [
            'applications' => $enrichedApplications,
            'recommendations' => $recommendations,
            'by_application_id' => $enrichedById
        ];
    }

    /**
     * Hitung nilai kriteria SAW per pelamar
     *
     * @param array<string,mixed> $application Application row with candidate and job info
     * @return array<string,mixed>
     */
    private function calculateSawCriteria($application) {
        $candidateSkills = (string) ($application['candidate_skills'] ?? $application['skills'] ?? '');
        $candidateEducation = (string) ($application['candidate_education'] ?? $application['education'] ?? '');
        $candidateExperienceYears = (int) ($application['candidate_experience_years'] ?? $application['experience_years'] ?? 0);
        $candidateBio = (string) ($application['candidate_bio'] ?? $application['bio'] ?? '');
        $coverLetter = (string) ($application['cover_letter'] ?? '');

        $jobSkills = (string) ($application['job_skills'] ?? $application['skills'] ?? '');
        $jobContext = trim(
            ($application['job_title'] ?? '') . ' ' .
            ($application['job_department'] ?? '') . ' ' .
            ($application['job_requirements'] ?? '') . ' ' .
            ($application['job_description'] ?? '')
        );

        $profileEvidenceText = trim(
            $candidateSkills . ' ' .
            $candidateEducation . ' ' .
            $candidateExperienceYears . ' tahun ' .
            $candidateBio . ' ' .
            $coverLetter
        );
        $applicationCvFile = trim((string) ($application['cv_file'] ?? ''));
        if ($applicationCvFile === '') {
            $applicationCvFile = trim((string) ($application['user_cv_file'] ?? ''));
        }

        $cvAnalysis = $this->analyzeCvDocument(
            $applicationCvFile,
            $jobContext,
            $jobSkills,
            $profileEvidenceText
        );
        $cvText = (string) ($cvAnalysis['text'] ?? '');
        $evidenceText = trim($candidateBio . ' ' . $coverLetter . ' ' . $cvText);

        $profileSkillScore = $this->calculateSkillScore($candidateSkills, $jobSkills);
        $combinedSkillScore = round(
            ($profileSkillScore * 0.75) + (((float) ($cvAnalysis['job_relevance'] ?? 0.0)) * 0.25),
            2
        );

        $activityScore = $this->calculateActivityScore($evidenceText, $jobSkills);
        $activityScore = round(
            ($activityScore * 0.65) + (((float) ($cvAnalysis['profile_consistency'] ?? 0.0)) * 0.35),
            2
        );

        return [
            'skill' => $combinedSkillScore,
            'education' => $this->calculateEducationScore($candidateEducation, $jobContext),
            'experience' => $this->calculateExperienceScore($candidateExperienceYears, $jobContext, $evidenceText),
            'activity' => $activityScore,
            'cv_analysis' => $cvAnalysis
        ];
    }

    /**
     * Calculate skill match percentage between candidate skills and job skills
     *
     * @param string $candidateSkillsText Candidate skills as CSV/text
     * @param string $jobSkillsText Job skills as CSV/text
     * @return float Percentage 0-100
     */
    private function calculateSkillScore($candidateSkillsText, $jobSkillsText) {
        $candidateSkills = $this->splitCsvValues($candidateSkillsText);
        $jobSkills = $this->splitCsvValues($jobSkillsText);

        if (empty($jobSkills)) {
            return 50.0;
        }

        if (empty($candidateSkills)) {
            return 0.0;
        }

        $matchCount = $this->countSkillMatches($candidateSkills, $jobSkills);
        return round(($matchCount / max(count($jobSkills), 1)) * 100, 2);
    }

    /**
     * Calculate education match score
     *
     * @param string $candidateEducationText Candidate education text
     * @param string $jobContextText Job context text
     * @return float
     */
    private function calculateEducationScore($candidateEducationText, $jobContextText) {
        $candidateEducationText = $this->normalizeText($candidateEducationText);
        $jobContextText = $this->normalizeText($jobContextText);

        $candidateLevel = $this->detectCandidateEducationLevel($candidateEducationText);
        $requiredLevel = $this->detectRequiredEducationLevel($jobContextText);

        if ($requiredLevel > 0) {
            $levelScore = $candidateLevel > 0
                ? min(120.0, ($candidateLevel / $requiredLevel) * 100.0)
                : 0.0;
        } else {
            $levelScore = $candidateLevel > 0 ? min(100.0, $candidateLevel * 20.0) : 40.0;
        }

        $jobKeywords = $this->extractKeywords(
            preg_replace('/\b(sma|smk|d3|d4|s1|s2|s3|tahun|minimal|pengalaman|kerja)\b/i', ' ', $jobContextText),
            4
        );

        $matchedKeywords = 0;
        foreach ($jobKeywords as $keyword) {
            if (strpos($candidateEducationText, $keyword) !== false) {
                $matchedKeywords++;
            }
        }

        $keywordDivider = max(min(count($jobKeywords), 4), 1);
        $keywordScore = empty($jobKeywords) ? 50.0 : min(100.0, ($matchedKeywords / $keywordDivider) * 100.0);

        return round(($levelScore * 0.7) + ($keywordScore * 0.3), 2);
    }

    /**
     * Calculate experience score
     *
     * @param int|string $candidateExperienceYears Candidate years of experience (int or numeric string)
     * @param string $jobContextText Job context text
     * @param string $evidenceText Combined evidence text from profile/CV
     * @return float
     */
    private function calculateExperienceScore($candidateExperienceYears, $jobContextText, $evidenceText) {
        $candidateExperienceYears = max(0, (int) $candidateExperienceYears);
        $requiredYears = $this->extractRequiredYears($jobContextText);
        $evidenceText = $this->normalizeText($evidenceText);

        if ($requiredYears > 0) {
            $baseScore = min(130.0, ($candidateExperienceYears / $requiredYears) * 100.0);
        } else {
            $baseScore = min(100.0, $candidateExperienceYears * 20.0);
        }

        $experienceKeywords = [
            'pengalaman', 'kerja', 'work', 'magang', 'internship', 'intern',
            'proyek', 'project', 'freelance', 'part time', 'volunteer'
        ];

        $hitCount = 0;
        foreach ($experienceKeywords as $keyword) {
            if (strpos($evidenceText, $keyword) !== false) {
                $hitCount++;
            }
        }

        $evidenceBonus = min(20.0, $hitCount * 2.5);
        return round(min(130.0, $baseScore + $evidenceBonus), 2);
    }

    /**
     * Calculate activity score from profile/CV evidence
     *
     * @param string $evidenceText Evidence text
     * @param string $jobSkillsText Job skills text
     * @return float
     */
    private function calculateActivityScore($evidenceText, $jobSkillsText) {
        $evidenceText = $this->normalizeText($evidenceText);

        $activityGroups = [
            ['magang', 'intern', 'internship', 'pkl', 'praktik kerja'],
            ['organisasi', 'komunitas', 'himpunan', 'kepanitiaan', 'volunteer'],
            ['proyek', 'project', 'portfolio', 'portofolio', 'freelance', 'bootcamp'],
            ['sertifikat', 'certificate', 'certification', 'lomba', 'kompetisi', 'prestasi'],
            ['ketua', 'leader', 'lead', 'koordinator', 'coordinator', 'mentor']
        ];

        $groupHit = 0;
        foreach ($activityGroups as $group) {
            foreach ($group as $keyword) {
                if (strpos($evidenceText, $keyword) !== false) {
                    $groupHit++;
                    break;
                }
            }
        }

        $groupScore = ($groupHit / max(count($activityGroups), 1)) * 100.0;

        $candidateTokens = $this->extractKeywords($evidenceText, 3);
        $jobSkills = $this->splitCsvValues($jobSkillsText);
        $jobSkillTokens = [];
        foreach ($jobSkills as $skill) {
            $jobSkillTokens = array_merge($jobSkillTokens, $this->extractKeywords($skill, 2));
        }
        $jobSkillTokens = array_values(array_unique($jobSkillTokens));

        $matched = 0;
        $candidateTokenMap = array_fill_keys($candidateTokens, true);
        foreach ($jobSkillTokens as $token) {
            if (isset($candidateTokenMap[$token])) {
                $matched++;
            }
        }

        $relevanceDivider = max(min(count($jobSkillTokens), 4), 1);
        $relevanceScore = empty($jobSkillTokens) ? 50.0 : min(100.0, ($matched / $relevanceDivider) * 100.0);

        return round(($groupScore * 0.6) + ($relevanceScore * 0.4), 2);
    }

    /**
     * Analyze CV file for relevance/consistency and produce scores
     *
     * @param string $cvFile Filename or path segment of CV
     * @param string $jobContextText Concatenated job title/department/requirements/description
     * @param string $jobSkillsText CSV or text of job skills
     * @param string $profileEvidenceText Candidate profile text (skills, education, bio)
     * @return array<string,mixed>
     */
    private function analyzeCvDocument($cvFile, $jobContextText, $jobSkillsText, $profileEvidenceText) {
        $cacheKey = md5((string) $cvFile . '|' . $jobContextText . '|' . $jobSkillsText . '|' . $profileEvidenceText);
        if (isset($this->cvAnalysisCache[$cacheKey])) {
            return $this->cvAnalysisCache[$cacheKey];
        }

        $analysis = [
            'has_file' => false,
            'has_valid_text' => false,
            'is_disqualified' => true,
            'job_relevance' => 0.0,
            'profile_consistency' => 0.0,
            'quality_factor' => 0.2,
            'summary' => 'CV belum tersedia',
            'text' => ''
        ];

        $cvFile = basename((string) $cvFile);
        if ($cvFile === '') {
            $this->cvAnalysisCache[$cacheKey] = $analysis;
            return $analysis;
        }

        $analysis['has_file'] = true;
        $path = $this->resolveCvPath($cvFile);
        if ($path === null || !is_file($path)) {
            $analysis['summary'] = 'File CV tidak ditemukan di server';
            $this->cvAnalysisCache[$cacheKey] = $analysis;
            return $analysis;
        }

        $fileSize = (int) @filesize($path);
        if ($fileSize > 0 && $fileSize < 1024) {
            $analysis['summary'] = 'CV terdeteksi terlalu kecil dan berisiko kosong';
            $analysis['quality_factor'] = 0.15;
            $this->cvAnalysisCache[$cacheKey] = $analysis;
            return $analysis;
        }

        $extractedText = $this->extractTextFromCvFile($path);
        $normalizedCvText = $this->normalizeText($extractedText);
        $analysis['text'] = $normalizedCvText;

        if ($normalizedCvText === '') {
            $analysis['summary'] = 'Isi CV tidak dapat dibaca atau kosong';
            $analysis['quality_factor'] = 0.15;
            $this->cvAnalysisCache[$cacheKey] = $analysis;
            return $analysis;
        }

        $cvTokens = $this->extractKeywords($normalizedCvText, 3);
        // require a small minimum amount of tokens, but be permissive for short CVs
        if (count($cvTokens) < 8) {
            $analysis['summary'] = 'Isi CV terlalu minim untuk evaluasi akurat';
            $analysis['quality_factor'] = 0.25;
            $this->cvAnalysisCache[$cacheKey] = $analysis;
            return $analysis;
        }

        $analysis['has_valid_text'] = true;

        $jobTokens = $this->extractKeywords(trim($jobContextText . ' ' . $jobSkillsText), 3);
        $profileTokens = $this->extractKeywords((string) $profileEvidenceText, 3);

        $jobOverlap = $this->calculateTokenOverlapScore($cvTokens, $jobTokens, 8);
        $profileOverlap = $this->calculateTokenOverlapScore($cvTokens, $profileTokens, 10);

        $analysis['job_relevance'] = round($jobOverlap, 2);
        $analysis['profile_consistency'] = round($profileOverlap, 2);
        $analysis['quality_factor'] = round(
            max(
                0.15,
                min(
                    1.0,
                    ($jobOverlap * 0.6 + $profileOverlap * 0.4) / 100
                )
            ),
            4
        );

        // Adjust thresholds to be a bit more permissive to avoid false positives
        if ($jobOverlap < 10) {
            $analysis['summary'] = 'CV tidak relevan dengan posisi yang dilamar';
            $analysis['is_disqualified'] = true;
            $analysis['quality_factor'] = max(0.18, min($analysis['quality_factor'], 0.45));
        } elseif ($profileOverlap < 8) {
            $analysis['summary'] = 'Isi CV tidak konsisten dengan profil kandidat';
            $analysis['is_disqualified'] = true;
            $analysis['quality_factor'] = max(0.25, min($analysis['quality_factor'], 0.55));
        } else {
            $analysis['summary'] = 'CV valid dan relevan untuk posisi';
            $analysis['is_disqualified'] = false;
            $analysis['quality_factor'] = max(0.55, $analysis['quality_factor']);
        }

        $this->cvAnalysisCache[$cacheKey] = $analysis;
        return $analysis;
    }

    /**
     * Resolve CV filename to an absolute path on disk
     *
     * @param string $cvFile
     * @return string|null
     */
    private function resolveCvPath($cvFile) {
        $filename = basename((string) $cvFile);
        if ($filename === '') {
            return null;
        }

        $primary = uploadPath('cv', $filename);
        if (is_file($primary)) {
            return $primary;
        }

        $legacy = ROOTPATH . 'cv/' . $filename;
        if (is_file($legacy)) {
            return $legacy;
        }

        return null;
    }

    /**
     * Extract readable text from a CV file (pdf/docx/txt)
     *
     * @param string $path Absolute path
     * @return string
     */
    private function extractTextFromCvFile($path) {
        $path = (string) $path;
        if ($path === '' || !is_file($path)) {
            return '';
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

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

            $text = strip_tags(str_replace('</w:p>', "\n", $xml));
            return trim(preg_replace('/\s+/', ' ', html_entity_decode($text, ENT_QUOTES, 'UTF-8')));
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return '';
        }

        if ($extension === 'pdf') {
            return $this->extractTextFromPdf($raw);
        }

        $text = preg_replace('/[^[:print:]\r\n\t]/', ' ', $raw);
        return trim(preg_replace('/\s+/', ' ', (string) $text));
    }

    /**
     * Attempt to extract text from a raw PDF binary blob
     *
     * @param string $binary Raw file contents
     * @return string
     */
    private function extractTextFromPdf($binary) {
        $binary = (string) $binary;
        if ($binary === '') {
            return '';
        }

        $text = '';
        if (preg_match_all('/\(([^()]*)\)\s*Tj/s', $binary, $matches)) {
            foreach ($matches[1] as $segment) {
                $text .= ' ' . $this->decodePdfLiteralString((string) $segment);
            }
        }

        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $binary, $arrayMatches)) {
            foreach ($arrayMatches[1] as $chunk) {
                if (preg_match_all('/\(([^()]*)\)/s', (string) $chunk, $parts)) {
                    foreach ($parts[1] as $segment) {
                        $text .= ' ' . $this->decodePdfLiteralString((string) $segment);
                    }
                }
            }
        }

        if (trim($text) === '') {
            $text = preg_replace('/[^[:print:]\r\n\t]/', ' ', $binary);
        }

        return trim(preg_replace('/\s+/', ' ', (string) $text));
    }

    /**
     * Decode escaped PDF literal string contents into plain text
     *
     * @param string $text Raw literal string from PDF content stream
     * @return string Decoded plain text
     */
    private function decodePdfLiteralString($text) {
        $text = str_replace(
            ['\\\\', '\\(', '\\)', '\\n', '\\r', '\\t', '\\b', '\\f'],
            ['\\', '(', ')', "\n", "\r", "\t", ' ', ' '],
            (string) $text
        );

        // Remove octal escapes like \123
        $text = preg_replace('/\\\\[0-7]{1,3}/', ' ', (string) $text);

        return (string) preg_replace('/[^[:print:]\r\n\t]/', ' ', (string) $text);
    }

    /**
     * Calculate overlap percentage between candidate tokens and reference tokens
     *
     * @param array<string> $candidateTokens
     * @param array<string> $referenceTokens
     * @param int $capDivider Divider cap to normalize overlap
     * @return float
     */
    private function calculateTokenOverlapScore(array $candidateTokens, array $referenceTokens, int $capDivider = 8): float {
        $candidateTokens = array_values(array_unique(array_filter((array) $candidateTokens)));
        $referenceTokens = array_values(array_unique(array_filter((array) $referenceTokens)));

        if (empty($candidateTokens) || empty($referenceTokens)) {
            return 0.0;
        }

        $candidateMap = array_fill_keys($candidateTokens, true);
        $matched = 0;
        foreach ($referenceTokens as $token) {
            if (isset($candidateMap[$token])) {
                $matched++;
            }
        }

        $divider = max(min(count($referenceTokens), (int) $capDivider), 1);
        return min(100.0, ($matched / $divider) * 100.0);
    }

    private function extractRequiredYears(string $text): int {
        $text = $this->normalizeText((string) $text);
        preg_match_all('/(\d+)\s*(\+)?\s*(tahun|year|years|yr)/i', $text, $matches);

        if (empty($matches[1])) {
            return 0;
        }

        $years = array_map('intval', $matches[1]);
        return max($years);
    }

    private function detectCandidateEducationLevel(string $text): int {
        $text = $this->normalizeText((string) $text);

        $patterns = [
            6 => '/\b(s3|strata 3|doktor|doctor|phd)\b/i',
            5 => '/\b(s2|strata 2|magister|master|msc|m\.?si)\b/i',
            4 => '/\b(s1|strata 1|sarjana|bachelor|d4)\b/i',
            3 => '/\b(d3|diploma 3)\b/i',
            2 => '/\b(d1|d2|diploma)\b/i',
            1 => '/\b(sma|smk|man|slta)\b/i'
        ];

        foreach ($patterns as $level => $pattern) {
            if (preg_match($pattern, $text)) {
                return $level;
            }
        }

        return 0;
    }

    private function detectRequiredEducationLevel(string $text): int {
        $text = $this->normalizeText((string) $text);
        $levels = [];

        $patterns = [
            6 => '/\b(s3|strata 3|doktor|doctor|phd)\b/i',
            5 => '/\b(s2|strata 2|magister|master|msc|m\.?si)\b/i',
            4 => '/\b(s1|strata 1|sarjana|bachelor|d4)\b/i',
            3 => '/\b(d3|diploma 3)\b/i',
            2 => '/\b(d1|d2|diploma)\b/i',
            1 => '/\b(sma|smk|man|slta)\b/i'
        ];

        foreach ($patterns as $level => $pattern) {
            if (preg_match($pattern, $text)) {
                $levels[] = $level;
            }
        }

        if (empty($levels)) {
            return 0;
        }

        // Jika ada beberapa level ditulis (mis. D3/S1), anggap level minimum yang diterima.
        return min($levels);
    }

    private function splitCsvValues(string $text): array {
        $text = $this->normalizeText((string) $text);
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/[,;\n\r]+/', $text);
        $result = [];

        foreach ($parts as $part) {
            $value = trim($part);
            if ($value !== '') {
                $result[] = $value;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * Count matching skills between candidate and job
     *
     * @param array<string> $candidateSkills
     * @param array<string> $jobSkills
     * @return int
     */
    private function countSkillMatches(array $candidateSkills, array $jobSkills): int {
        $matches = 0;
        $usedCandidateIndex = [];

        foreach ($jobSkills as $jobSkill) {
            $jobSkill = trim($jobSkill);
            if ($jobSkill === '') {
                continue;
            }

            foreach ($candidateSkills as $index => $candidateSkill) {
                if (isset($usedCandidateIndex[$index])) {
                    continue;
                }

                $candidateSkill = trim($candidateSkill);
                if ($candidateSkill === '') {
                    continue;
                }

                if (
                    $candidateSkill === $jobSkill
                    || strpos($candidateSkill, $jobSkill) !== false
                    || strpos($jobSkill, $candidateSkill) !== false
                ) {
                    $matches++;
                    $usedCandidateIndex[$index] = true;
                    break;
                }
            }
        }

        return $matches;
    }

    private function extractKeywords(string $text, int $minLength = 3): array {
        $text = $this->normalizeText((string) $text);
        if ($text === '') {
            return [];
        }

        $tokens = preg_split('/[^a-z0-9\+#]+/i', $text);
        $stopWords = [
            'dan', 'atau', 'yang', 'untuk', 'dengan', 'dari', 'pada', 'the', 'and', 'for', 'to',
            'in', 'of', 'a', 'an', 'di', 'ke', 'ini', 'itu', 'minimal', 'tahun', 'pengalaman', 'kerja'
        ];

        $keywords = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '' || strlen($token) < $minLength) {
                continue;
            }
            if (in_array($token, $stopWords, true)) {
                continue;
            }
            $keywords[] = $token;
        }

        return array_values(array_unique($keywords));
    }

    private function normalizeText(string $text): string {
        $text = (string) $text;
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($text, 'UTF-8');
        }

        return strtolower($text);
    }

    private function candidateIsEligibleForRecommendation(string $status): bool {
        $status = (string) $status;
        return !in_array($status, ['accepted', 'rejected'], true);
    }

    private function candidateCanAppearInRecommendationDisplay(string $status): bool {
        $status = (string) $status;
        return $status !== 'rejected';
    }
}
