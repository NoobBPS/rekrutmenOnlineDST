<?php
/**
 * Dashboard Controller - DST Recruitment System (CI4)
 */

namespace App\Controllers;

class Dashboard extends BaseController
{
    /**
     * Dashboard Kandidat
     */
    public function index()
    {
        if (!isLoggedIn()) {
            return redirect()->to(base_url('auth/login'));
        }

        if (hasRole('admin')) {
            return redirect()->to(base_url('jobs/manage'));
        }

        if (hasRole('hrd')) {
            return $this->hrd();
        }

        $user_id = $_SESSION['user_id'];

        // Ambil data user lengkap
        $user = db()->row(
            "SELECT * FROM users WHERE user_id = ?",
            [$user_id]
        );

        // Ambil lamaran user
        $my_applications = db()->select(
            "SELECT a.*, j.title as job_title, j.location, j.type, j.department 
             FROM applications a 
             JOIN jobs j ON a.job_id = j.job_id 
             WHERE a.user_id = ? 
             ORDER BY a.applied_at DESC",
            [$user_id]
        );

        // Ambil lowongan terbaru
        $recent_jobs = db()->select(
            "SELECT job_id as id, jobs.* FROM jobs WHERE status = 'open' ORDER BY created_at DESC LIMIT 6"
        );

        // Hitung statistik
        $stats = [
            'total_jobs' => db()->count('jobs', "status = 'open'"),
            'my_applications' => count($my_applications),
            'interview' => 0,
            'accepted' => 0,
            'rejected' => 0
        ];

        foreach ($my_applications as $app) {
            if ($app['status'] === 'interview') $stats['interview']++;
            if ($app['status'] === 'accepted') $stats['accepted']++;
            if ($app['status'] === 'rejected') $stats['rejected']++;
        }

        $data = [
            'title' => 'Dashboard - DST Recruitment',
            'page' => 'dashboard',
            'user' => $user,
            'my_applications' => $my_applications,
            'recent_jobs' => $recent_jobs,
            'stats' => $stats
        ];

        return view('layouts/header', $data) . view('dashboard/index', $data) . view('layouts/footer');
    }

    /**
     * Dashboard HRD
     */
    public function hrd()
    {
        if ($redirect = $this->requireHRD()) return $redirect;

        // Statistik utama
        $stats = [
            'total_jobs' => db()->count('jobs', "status = 'open'"),
            'total_applications' => db()->count('applications', "1=1"),
            'pending' => db()->count('applications', "status = 'pending'"),
            'screening' => db()->count('applications', "status = 'screening'"),
            'interview' => db()->count('applications', "status = 'interview'"),
            'accepted' => db()->count('applications', "status = 'accepted'"),
            'rejected' => db()->count('applications', "status = 'rejected'")
        ];

        // Hitung conversion rate
        $total = $stats['total_applications'];
        $stats['conversion_rate'] = $total > 0 ? round(($stats['accepted'] / $total) * 100) : 0;

        // Lamaran terbaru
        $recent_applications = db()->select(
            "SELECT a.*, u.full_name as candidate_name, u.email as candidate_email, u.skills, u.education, u.avatar as candidate_avatar,
                    j.title as job_title, j.location 
             FROM applications a
             JOIN users u ON a.user_id = u.user_id
             JOIN jobs j ON a.job_id = j.job_id
             ORDER BY a.applied_at DESC
             LIMIT 10"
        );

        // Lowongan aktif
        $active_jobs = db()->select(
            "SELECT j.job_id as id, j.*, 
                    (SELECT COUNT(*) FROM applications WHERE job_id = j.job_id) as applicant_count
             FROM jobs j
             WHERE j.status = 'open'
             ORDER BY j.created_at DESC"
        );

        // Pipeline data
        $pipeline = [
            ['status' => 'pending', 'label' => 'Lamaran Baru', 'count' => $stats['pending']],
            ['status' => 'screening', 'label' => 'Screening', 'count' => $stats['screening']],
            ['status' => 'interview', 'label' => 'Interview', 'count' => $stats['interview']],
            ['status' => 'accepted', 'label' => 'Diterima', 'count' => $stats['accepted']],
            ['status' => 'rejected', 'label' => 'Ditolak', 'count' => $stats['rejected']]
        ];

        $data = [
            'title' => 'Dashboard HRD - DST Recruitment',
            'page' => 'dashboard-hrd',
            'stats' => $stats,
            'recent_applications' => $recent_applications,
            'active_jobs' => $active_jobs,
            'pipeline' => $pipeline
        ];

        return view('layouts/header', $data) . view('dashboard/hrd', $data) . view('layouts/footer');
    }

    /**
     * API: Get stats (for AJAX)
     */
    public function api_stats()
    {
        if (!hasRole('hrd')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Unauthorized']);
        }

        $stats = [
            'total_jobs' => db()->count('jobs', "status = 'open'"),
            'total_applications' => db()->count('applications', "1=1"),
            'pending' => db()->count('applications', "status = 'pending'"),
            'screening' => db()->count('applications', "status = 'screening'"),
            'interview' => db()->count('applications', "status = 'interview'"),
            'accepted' => db()->count('applications', "status = 'accepted'"),
            'rejected' => db()->count('applications', "status = 'rejected'")
        ];

        return $this->response->setJSON(['success' => true, 'data' => $stats]);
    }
}
