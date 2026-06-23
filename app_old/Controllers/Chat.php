<?php
/**
 * Chat Controller - DST Recruitment System
 */

namespace App\Controllers;

class Chat extends BaseController {
    const DELETED_MARKER = '__MSG_DELETED__';
    const EDIT_WINDOW_SECONDS = 10;

    /**
     * Daftar Percakapan
     */
    public function index() {
        // Admin tidak bisa akses chat
        if (hasRole('admin')) {
            return redirect()->to(base_url('jobs/manage'));
        }

        $user_id = (int) $_SESSION['user_id'];

        if (hasRole('hrd')) {
            return $this->hrdIndex($user_id);
        }

        // Untuk kandidat: tampilkan percakapan dikelompokkan per posisi
        $conversations = $this->getUserConversations($user_id, 'user');

        $data = [
            'title' => 'Pesan - DST Recruitment',
            'page' => 'chat',
            'conversations' => $conversations
        ];

        return view('layouts/header', $data) . view('chat/index', $data) . view('layouts/footer');
    }

    /**
     * HRD Daftar Percakapan - dikelompokkan per posisi
     */
    private function hrdIndex($hrd_id) {
        $conversations = $this->getUserConversations($hrd_id, 'hrd');

        $data = [
            'title' => 'Pesan - DST Recruitment',
            'page' => 'chat',
            'conversations' => $conversations
        ];

        return view('layouts/header', $data) . view('chat/index', $data) . view('layouts/footer');
    }

    /**
     * Get conversations grouped by application (position)
     */
    private function getUserConversations($userId, $role) {
        // Get distinct conversations, grouped by partner + application_id
        $rows = db()->select(
            "SELECT m.id as message_id, m.from_user_id, m.to_user_id, m.application_id,
                    m.content, m.created_at, m.is_read,
                    CASE WHEN m.from_user_id = ? THEN m.to_user_id ELSE m.from_user_id END as partner_id,
                    a.status as application_status, a.cv_file,
                    j.title as job_title, j.department, j.location
             FROM messages m
             LEFT JOIN applications a ON m.application_id = a.id
             LEFT JOIN jobs j ON a.job_id = j.job_id
             WHERE m.from_user_id = ? OR m.to_user_id = ?
             ORDER BY m.created_at DESC",
            [$userId, $userId, $userId]
        );

        // Group by partner + application_id
        $grouped = [];
        foreach ($rows as $row) {
            $partnerId = (int) $row['partner_id'];
            $appId = (int) ($row['application_id'] ?? 0);
            $key = $partnerId . '_' . $appId;

            if (!isset($grouped[$key])) {
                // Get partner info
                $partner = db()->row(
                    "SELECT user_id as id, full_name, role, avatar FROM users WHERE user_id = ?",
                    [$partnerId]
                );

                if (!$partner) continue;

                // Count unread
                $unread = db()->row(
                    "SELECT COUNT(*) as cnt FROM messages
                     WHERE from_user_id = ? AND to_user_id = ? AND is_read = 0
                     " . ($appId > 0 ? "AND application_id = ?" : ""),
                    $appId > 0 ? [$partnerId, $userId, $appId] : [$partnerId, $userId]
                );

                $grouped[$key] = [
                    'partner_id' => $partnerId,
                    'partner_name' => $partner['full_name'] ?? 'Unknown',
                    'partner_role' => $partner['role'] ?? 'user',
                    'partner_avatar' => $partner['avatar'] ?? null,
                    'application_id' => $appId,
                    'application_status' => $row['application_status'] ?? null,
                    'job_title' => $row['job_title'] ?? null,
                    'job_department' => $row['department'] ?? null,
                    'job_location' => $row['location'] ?? null,
                    'last_message' => $row['content'],
                    'last_time' => $row['created_at'],
                    'unread' => (int) ($unread['cnt'] ?? 0),
                ];
            }
        }

        // --- FIX: Remove "orphan" null-application conversations ---
        // If a partner already has conversations with a proper application_id > 0,
        // remove any grouped entry with application_id = 0 for that same partner.
        // These orphan entries (from messages stored without application_id) cause
        // the fallback in room() to pick the wrong chatroom.
        $partnersWithProperApps = [];
        foreach ($grouped as $conv) {
            if ((int) ($conv['application_id'] ?? 0) > 0) {
                $partnersWithProperApps[(int) $conv['partner_id']] = true;
            }
        }
        foreach (array_keys($grouped) as $key) {
            $conv = $grouped[$key];
            if ((int) ($conv['application_id'] ?? 0) === 0 && !empty($partnersWithProperApps[(int) $conv['partner_id']])) {
                unset($grouped[$key]);
            }
        }
        // --- END FIX ---

        $result = array_values($grouped);

        // Enrich with status meta
        foreach ($result as &$conv) {
            $context = null;
            if ($conv['application_id'] > 0) {
                $context = [
                    'application_id' => $conv['application_id'],
                    'status_key' => $conv['application_status'] ?? '',
                    'job_title' => $conv['job_title'] ?? '',
                    'job_department' => $conv['job_department'] ?? '',
                    'job_location' => $conv['job_location'] ?? '',
                ];
            }
            $statusMeta = $this->buildConversationStatusMeta($context);
            $conv['chat_status_label'] = $statusMeta['label'];
            $conv['chat_status_variant'] = $statusMeta['variant'];
            $conv['chat_status_detail'] = $statusMeta['detail'];
            $conv['last_message'] = $this->previewMessage($conv['last_message'] ?? '');
        }
        unset($conv);

        return $result;
    }

    /**
     * Room Percakapan
     */
    public function room($partner_id) {
        if (hasRole('admin')) {
            return redirect()->to(base_url('jobs/manage'));
        }

        $partner_id = (int) $partner_id;
        $application_id = (int) ($_GET['application_id'] ?? 0);

        if ($partner_id <= 0) {
            return redirect()->to(base_url('chat'));
        }

        $partner = $this->findPartner($partner_id);
        if (!$partner) {
            return redirect()->to(base_url('chat'));
        }

        // Determine which application to use.
        // HRD::start() always sends a specific application_id. The fallback below
        // only runs when someone navigates to chat/room/{id} without a query string.
        if ($application_id <= 0) {
            if (hasRole('hrd')) {
                // For HRD: collect all distinct non-null application_ids that already
                // have messages with this partner. If there is exactly one, use it
                // automatically. If there are multiple, send the HRD back to the chat
                // list so they can click the correct specific conversation — this
                // prevents silently landing in the wrong chatroom.
                $appRows = db()->select(
                    "SELECT DISTINCT m.application_id
                     FROM messages m
                     WHERE ((m.from_user_id = ? AND m.to_user_id = ?)
                         OR (m.from_user_id = ? AND m.to_user_id = ?))
                       AND m.application_id IS NOT NULL
                     ORDER BY m.id DESC",
                    [(int) $_SESSION['user_id'], $partner_id, $partner_id, (int) $_SESSION['user_id']]
                );
                if (count($appRows) === 1) {
                    $application_id = (int) $appRows[0]['application_id'];
                } elseif (count($appRows) > 1) {
                    // Multiple applications found — redirect to chat list.
                    // The HRD must click the specific conversation from the list.
                    setFlash('info', 'Pilih percakapan yang ingin dibuka dari daftar pesan.');
                    return redirect()->to(base_url('chat'));
                } else {
                    // No prior messages at all — fall back to latest message approach.
                    $latest = db()->row(
                        "SELECT application_id FROM messages
                         WHERE (from_user_id = ? AND to_user_id = ?)
                            OR (from_user_id = ? AND to_user_id = ?)
                         ORDER BY id DESC LIMIT 1",
                        [(int) $_SESSION['user_id'], $partner_id, $partner_id, (int) $_SESSION['user_id']]
                    );
                    $application_id = (int) (isset($latest['application_id']) ? $latest['application_id'] : 0);
                }
            } else {
                // For user/candidate: collect all distinct non-null application_ids
                // that already have messages with this partner. If there are multiple
                // (user applied to several positions handled by the same HRD), we must
                // NOT guess which room to open — redirect to the chat list so the user
                // can click the correct specific conversation.
                $appRows = db()->select(
                    "SELECT DISTINCT m.application_id
                     FROM messages m
                     WHERE ((m.from_user_id = ? AND m.to_user_id = ?)
                         OR (m.from_user_id = ? AND m.to_user_id = ?))
                       AND m.application_id IS NOT NULL
                     ORDER BY m.id DESC",
                    [(int) $_SESSION['user_id'], $partner_id, $partner_id, (int) $_SESSION['user_id']]
                );
                if (count($appRows) === 1) {
                    $application_id = (int) $appRows[0]['application_id'];
                } elseif (count($appRows) > 1) {
                    setFlash('info', 'Pilih percakapan yang ingin dibuka dari daftar pesan.');
                    return redirect()->to(base_url('chat'));
                } else {
                    // No prior messages at all — use latest message as last resort.
                    $latest = db()->row(
                        "SELECT application_id FROM messages
                         WHERE (from_user_id = ? AND to_user_id = ?)
                            OR (from_user_id = ? AND to_user_id = ?)
                         ORDER BY id DESC LIMIT 1",
                        [(int) $_SESSION['user_id'], $partner_id, $partner_id, (int) $_SESSION['user_id']]
                    );
                    $application_id = (int) (isset($latest['application_id']) ? $latest['application_id'] : 0);
                }
            }
        }

        if (!hasRole('hrd') && !hasRole('admin') && !in_array((string) $partner['role'], ['hrd', 'admin'], true)) {
            setFlash('error', 'Anda hanya dapat berkomunikasi dengan HRD');
            return redirect()->to(base_url('chat'));
        }

        if (!$this->canAccessConversation($partner_id, $application_id)) {
            setFlash('error', 'Anda belum dapat memulai percakapan');
            return redirect()->to(base_url('chat'));
        }

        $user_id = (int) $_SESSION['user_id'];

        // Auto-kirim kartu CV sebagai pesan pertama saat user membuka chat baru
        if (!hasRole('hrd') && !hasRole('admin') && $application_id > 0) {
            $this->autoInsertCvCardIfNew($user_id, $partner_id, $application_id);
        }

        // Get messages filtered by application_id
        $where = "(m.from_user_id = ? AND m.to_user_id = ?) OR (m.from_user_id = ? AND m.to_user_id = ?)";
        $params = [$user_id, $partner_id, $partner_id, $user_id];

        if ($application_id > 0) {
            $where = "($where) AND m.application_id = ?";
            $params[] = $application_id;
        }

        $messages = db()->select(
            "SELECT m.id, m.from_user_id, m.to_user_id, m.content, m.created_at, u.full_name as sender_name
             FROM messages m
             JOIN users u ON m.from_user_id = u.user_id
             WHERE $where
             ORDER BY m.created_at ASC",
            $params
        );

        db()->execute(
            "UPDATE messages SET is_read = 1 WHERE from_user_id = ? AND to_user_id = ?"
            . ($application_id > 0 ? " AND application_id = ?" : ""),
            $application_id > 0 ? [$partner_id, $user_id, $application_id] : [$partner_id, $user_id]
        );

        $latestOwnMessageId = $this->getLatestOwnMessageIdForConversation($user_id, $partner_id, $application_id);
        $messages = $this->normalizeMessages($messages, $partner_id, $latestOwnMessageId);

        $applicationContext = $this->getApplicationContext($application_id, $partner_id, (string) ($partner['role'] ?? ''));

        $data = [
            'title' => 'Percakapan dengan ' . $partner['full_name'] . ' - DST Recruitment',
            'page' => 'chat-room',
            'partner' => $partner,
            'messages' => $messages,
            'application_context' => $applicationContext,
            'application_id' => $application_id
        ];

        return view('layouts/header', $data) . view('chat/room', $data) . view('layouts/footer');
    }

    /**
     * Kirim Pesan
     */
    public function send() {
        if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid request']);
        }

        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Token keamanan tidak valid']);
        }

        $to_user_id = (int) (isset($_POST['to_user_id']) ? $_POST['to_user_id'] : 0);
        $application_id = (int) (isset($_POST['application_id']) ? $_POST['application_id'] : 0);
        $content = sanitize(isset($_POST['content']) ? $_POST['content'] : '');

        if ($to_user_id <= 0 || $content === '') {
            return $this->response->setJSON(['success' => false, 'message' => 'Data tidak lengkap']);
        }

        if (strlen($content) > 1000) {
            return $this->response->setJSON(['success' => false, 'message' => 'Pesan maksimal 1000 karakter']);
        }

        if (!$this->canAccessConversation($to_user_id, $application_id)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tidak dapat mengirim pesan']);
        }

        db()->execute(
            "INSERT INTO messages (from_user_id, to_user_id, application_id, content) VALUES (?, ?, ?, ?)",
            [(int) $_SESSION['user_id'], $to_user_id, $application_id > 0 ? $application_id : null, $content]
        );

        return $this->response->setJSON(['success' => true, 'message' => 'Pesan terkirim']);
    }

    /**
     * Mulai Percakapan (HRD only) - dengan application_id
     * HRD bisa membuka banyak sesi chat secara bersamaan untuk pelamar yang sama.
     */
    public function start($application_id) {
        if (!hasRole('hrd') && !hasRole('admin')) {
            return redirect()->to(base_url('dashboard'));
        }

        $application_id = (int) $application_id;
        if ($application_id <= 0) {
            return redirect()->to(base_url('applications/hrd'));
        }

        // Blokir akses Admin untuk mulai chat
        if (hasRole('admin')) {
            return redirect()->to(base_url('jobs/manage'));
        }

        $application = db()->row(
            "SELECT a.id, a.user_id, u.full_name FROM applications a
             JOIN users u ON a.user_id = u.user_id
             WHERE a.id = ?",
            [$application_id]
        );

        if (!$application) {
            return redirect()->to(base_url('applications/hrd'));
        }

        $hrd_id  = (int) $_SESSION['user_id'];
        $user_id = (int) $application['user_id'];

        setFlash('success', 'Membuka percakapan dengan ' . h((string) $application['full_name']));
        return redirect()->to(base_url('chat/room/' . $user_id . '?application_id=' . $application_id));
    }

    /**
     * Mulai/Buka Percakapan (User/Kandidat) - dengan application_id milik diri sendiri.
     * User tidak perlu menunggu HRD memulai chat lebih dulu.
     */
    public function userStart($application_id) {
        if (!isLoggedIn() || hasRole('hrd') || hasRole('admin')) {
            return redirect()->to(base_url('dashboard'));
        }

        $application_id = (int) $application_id;
        $user_id        = (int) $_SESSION['user_id'];

        // Pastikan lamaran ini milik user yang login, dan job-nya punya HRD
        $app = db()->row(
            "SELECT a.id, j.created_by as hrd_id, j.title as job_title
             FROM applications a
             JOIN jobs j ON a.job_id = j.job_id
             WHERE a.id = ? AND a.user_id = ?",
            [$application_id, $user_id]
        );

        if (!$app || empty($app['hrd_id'])) {
            setFlash('error', 'Tidak dapat menemukan HRD untuk lamaran ini.');
            return redirect()->to(base_url('applications'));
        }

        return redirect()->to(base_url('chat/room/' . (int) $app['hrd_id'] . '?application_id=' . $application_id));
    }

    /**
     * Get Messages (AJAX) - filtered by application_id
     */
    public function getMessages($partner_id) {
        if (!isLoggedIn()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Unauthorized']);
        }

        $partner_id = (int) $partner_id;
        $application_id = (int) ($_GET['application_id'] ?? 0);
        $last_id = (int) ($_GET['last_id'] ?? 0);

        if ($partner_id <= 0 || !$this->canAccessConversation($partner_id, $application_id)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Akses percakapan ditolak']);
        }

        $where = "(m.from_user_id = ? AND m.to_user_id = ?) OR (m.from_user_id = ? AND m.to_user_id = ?)";
        $params = [(int) $_SESSION['user_id'], $partner_id, $partner_id, (int) $_SESSION['user_id']];

        if ($application_id > 0) {
            $where = "($where) AND m.application_id = ?";
            $params[] = $application_id;
        }

        if ($last_id > 0) {
            $where = "($where) AND m.id > ?";
            $params[] = $last_id;
        }

        $messages = db()->select(
            "SELECT m.id, m.from_user_id, m.to_user_id, m.content, m.created_at, u.full_name as sender_name
             FROM messages m
             JOIN users u ON m.from_user_id = u.user_id
             WHERE $where
             ORDER BY m.created_at ASC",
            $params
        );

        db()->execute(
            "UPDATE messages SET is_read = 1 WHERE from_user_id = ? AND to_user_id = ?"
            . ($application_id > 0 ? " AND application_id = ?" : ""),
            $application_id > 0 ? [$partner_id, (int) $_SESSION['user_id'], $application_id] : [$partner_id, (int) $_SESSION['user_id']]
        );

        $latestOwnMessageId = $this->getLatestOwnMessageIdForConversation((int) $_SESSION['user_id'], $partner_id, $application_id);
        $messages = $this->normalizeMessages($messages, $partner_id, $latestOwnMessageId);

        return $this->response->setJSON(['success' => true, 'messages' => $messages]);
    }

    public function editMessage() {
        if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid request']);
        }

        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Token keamanan tidak valid']);
        }

        $message_id = (int) ($_POST['message_id'] ?? 0);
        $to_user_id = (int) ($_POST['to_user_id'] ?? 0);
        $content = sanitize($_POST['content'] ?? '');

        if ($message_id <= 0 || $to_user_id <= 0 || $content === '') {
            return $this->response->setJSON(['success' => false, 'message' => 'Data edit pesan tidak lengkap']);
        }

        if (strlen($content) > 1000) {
            return $this->response->setJSON(['success' => false, 'message' => 'Pesan maksimal 1000 karakter']);
        }

        $message = db()->row(
            "SELECT id, from_user_id, to_user_id, content, created_at FROM messages WHERE id = ?",
            [$message_id]
        );

        if (!$message) {
            return $this->response->setJSON(['success' => false, 'message' => 'Pesan tidak ditemukan']);
        }

        if ((int) $message['from_user_id'] !== (int) $_SESSION['user_id']) {
            return $this->response->setJSON(['success' => false, 'message' => 'Anda hanya dapat mengedit pesan sendiri']);
        }

        if ((int) $message['to_user_id'] !== $to_user_id) {
            return $this->response->setJSON(['success' => false, 'message' => 'Pesan tidak sesuai percakapan aktif']);
        }

        if ($this->isDeletedContent((string) $message['content'])) {
            return $this->response->setJSON(['success' => false, 'message' => 'Pesan yang sudah dihapus tidak dapat diedit']);
        }

        $latestOwnMessageId = $this->getLatestOwnMessageIdForConversation((int) $_SESSION['user_id'], $to_user_id, (int) ($message['application_id'] ?? 0));
        if (!$this->canEditMessageRules($message, $to_user_id, $latestOwnMessageId)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Pesan tidak dapat diedit saat ini.']);
        }

        $updated = db()->execute(
            "UPDATE messages SET content = ? WHERE id = ?",
            [$content, $message_id]
        );

        if (!$updated) {
            return $this->response->setJSON(['success' => false, 'message' => 'Gagal mengedit pesan']);
        }

        $refreshed = db()->row(
            "SELECT id, from_user_id, to_user_id, content, created_at FROM messages WHERE id = ?",
            [$message_id]
        );

        $latestOwnMessageId = $this->getLatestOwnMessageIdForConversation((int) $_SESSION['user_id'], $to_user_id, (int) ($refreshed['application_id'] ?? 0));
        $normalized = $this->normalizeMessage($refreshed, $to_user_id, $latestOwnMessageId);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Pesan berhasil diperbarui',
            'data' => $normalized
        ]);
    }

    public function deleteMessage() {
        if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid request']);
        }

        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Token keamanan tidak valid']);
        }

        $message_id = (int) ($_POST['message_id'] ?? 0);
        $to_user_id = (int) ($_POST['to_user_id'] ?? 0);

        if ($message_id <= 0 || $to_user_id <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Data hapus pesan tidak lengkap']);
        }

        $message = db()->row(
            "SELECT id, from_user_id, to_user_id, content, created_at FROM messages WHERE id = ?",
            [$message_id]
        );

        if (!$message) {
            return $this->response->setJSON(['success' => false, 'message' => 'Pesan tidak ditemukan']);
        }

        if ((int) $message['from_user_id'] !== (int) $_SESSION['user_id']) {
            return $this->response->setJSON(['success' => false, 'message' => 'Anda hanya dapat menghapus pesan sendiri']);
        }

        if ((int) $message['to_user_id'] !== $to_user_id) {
            return $this->response->setJSON(['success' => false, 'message' => 'Pesan tidak sesuai percakapan aktif']);
        }

        if ($this->isDeletedContent((string) $message['content'])) {
            return $this->response->setJSON(['success' => true, 'message' => 'Pesan sudah dihapus sebelumnya']);
        }

        $deleted = db()->execute(
            "UPDATE messages SET content = ? WHERE id = ?",
            [self::DELETED_MARKER, $message_id]
        );
        if (!$deleted) {
            return $this->response->setJSON(['success' => false, 'message' => 'Gagal menghapus pesan']);
        }

        $refreshed = db()->row(
            "SELECT id, from_user_id, to_user_id, content, created_at FROM messages WHERE id = ?",
            [$message_id]
        );

        $latestOwnMessageId = $this->getLatestOwnMessageIdForConversation((int) $_SESSION['user_id'], $to_user_id, (int) ($refreshed['application_id'] ?? 0));
        $normalized = $this->normalizeMessage($refreshed, $to_user_id, $latestOwnMessageId);

        return $this->response->setJSON(['success' => true, 'message' => 'Pesan berhasil dihapus', 'data' => $normalized]);
    }

    private function findPartner($partner_id) {
        return db()->row(
            "SELECT user_id as id, full_name, role, avatar FROM users WHERE user_id = ?",
            [$partner_id]
        );
    }

    private function canAccessConversation($partner_id, $application_id = 0): bool {
        $partner_id = (int) $partner_id;
        if ($partner_id <= 0) {
            return false;
        }

        $partner = db()->row(
            "SELECT user_id, role FROM users WHERE user_id = ?",
            [$partner_id]
        );

        if (!$partner) {
            return false;
        }

        if ((int) $partner['user_id'] === (int) ($_SESSION['user_id'] ?? 0)) {
            return false;
        }

        if (hasRole('hrd')) {
            // HRD hanya boleh chat dengan kandidat yang apply ke job miliknya.
            // HRD diizinkan membuka banyak sesi chat sekaligus.
            if ($application_id > 0) {
                $app = db()->row(
                    "SELECT a.id, j.created_by FROM applications a
                     JOIN jobs j ON a.job_id = j.job_id
                     WHERE a.id = ?",
                    [$application_id]
                );
                if (!$app) return false;
                if ((int) $app['created_by'] !== (int) $_SESSION['user_id']) return false;
            }
            return true;
        }

        if (!in_array((string) ($partner['role'] ?? ''), ['hrd', 'admin'], true)) {
            return false;
        }

        // Kandidat bisa chat selama lamaran valid miliknya (tidak perlu HRD mulai duluan)
        if ($application_id > 0) {
            $app = db()->row(
                "SELECT a.id FROM applications a WHERE a.id = ? AND a.user_id = ?",
                [$application_id, (int) $_SESSION['user_id']]
            );
            return !empty($app);
        }

        return true;
    }

    private function isDeletedContent(string $content): bool {
        return trim($content) === self::DELETED_MARKER;
    }

    private function previewMessage(string $content): string {
        if ($this->isDeletedContent($content)) {
            return 'Pesan telah dihapus';
        }
        if (strncmp($content, '__CV_CARD__:', 12) === 0) {
            return '📎 CV & Profil Pelamar';
        }
        return $content;
    }

    private function parseCvCard(string $content): ?array {
        if (strncmp($content, '__CV_CARD__:', 12) !== 0) {
            return null;
        }
        $json = substr($content, 12);
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Auto-insert kartu CV sebagai pesan pertama saat user membuka chat baru.
     * Hanya dilakukan sekali — jika sudah ada pesan, tidak akan insert lagi.
     */
    private function autoInsertCvCardIfNew(int $userId, int $partnerId, int $applicationId): void {
        // Cek apakah sudah ada pesan sebelumnya
        $existing = db()->row(
            "SELECT id FROM messages
             WHERE application_id = ?
               AND ((from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?))
             LIMIT 1",
            [$applicationId, $userId, $partnerId, $partnerId, $userId]
        );
        if ($existing) {
            return; // Sudah ada pesan, lewati
        }

        // Ambil data profil user untuk kartu CV
        $profile = db()->row(
            "SELECT u.full_name, u.skills, u.education, u.experience_years, u.bio, u.phone, a.cv_file
             FROM users u
             JOIN applications a ON a.user_id = u.user_id
             WHERE a.id = ? AND a.user_id = ?",
            [$applicationId, $userId]
        );
        if (!$profile) {
            return;
        }

        $cvUrl = !empty($profile['cv_file'])
            ? BASE_URL . 'applications/downloadCv/' . $applicationId
            : null;

        $cardData = json_encode([
            'name'             => (string) ($profile['full_name'] ?? ''),
            'phone'            => (string) ($profile['phone'] ?? ''),
            'skills'           => (string) ($profile['skills'] ?? ''),
            'education'        => (string) ($profile['education'] ?? ''),
            'experience_years' => (int)    ($profile['experience_years'] ?? 0),
            'bio'              => (string) ($profile['bio'] ?? ''),
            'cv_available'     => !empty($profile['cv_file']),
            'cv_url'           => $cvUrl,
            'application_id'   => $applicationId,
        ], JSON_UNESCAPED_UNICODE);

        db()->execute(
            "INSERT INTO messages (from_user_id, to_user_id, application_id, content) VALUES (?, ?, ?, ?)",
            [$userId, $partnerId, $applicationId, '__CV_CARD__:' . $cardData]
        );
    }

    private function getLatestOwnMessageIdForConversation(int $currentUserId, int $partnerId, int $applicationId = 0): int {
        $sql = "SELECT id FROM messages
                WHERE from_user_id = ? AND to_user_id = ?";
        $params = [$currentUserId, $partnerId];

        if ($applicationId > 0) {
            $sql .= " AND application_id = ?";
            $params[] = $applicationId;
        }

        $sql .= " ORDER BY id DESC LIMIT 1";

        $row = db()->row($sql, $params);
        return (int) ($row['id'] ?? 0);
    }

    private function canEditMessageRules(array $message, int $partnerId, int $latestOwnMessageId): bool {
        if ((int) ($message['id'] ?? 0) <= 0) {
            return false;
        }

        if ((int) ($message['from_user_id'] ?? 0) !== (int) ($_SESSION['user_id'] ?? 0)) {
            return false;
        }

        if ((int) ($message['to_user_id'] ?? 0) !== $partnerId) {
            return false;
        }

        if ((int) ($message['id'] ?? 0) !== $latestOwnMessageId) {
            return false;
        }

        if ($this->isDeletedContent((string) ($message['content'] ?? ''))) {
            return false;
        }

        $createdAtRaw = (string) ($message['created_at'] ?? '');
        $createdAt = strtotime($createdAtRaw);
        if ($createdAt === false) {
            return false;
        }

        $age = time() - $createdAt;
        return $age >= 0; // Remove time limit constraint
    }

    private function normalizeMessage(array $message, int $partnerId, int $latestOwnMessageId): array {
        $rawContent = (string) ($message['content'] ?? '');
        $isCvCard   = strncmp($rawContent, '__CV_CARD__:', 12) === 0;
        $isDeleted  = !$isCvCard && $this->isDeletedContent($rawContent);

        // Kartu CV dan pesan terhapus tidak bisa diedit/dihapus
        $canEdit   = !$isCvCard && !$isDeleted && $this->canEditMessageRules($message, $partnerId, $latestOwnMessageId);
        $canDelete = !$isCvCard && !$isDeleted
                     && (int) ($message['from_user_id'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0);

        $displayContent = $isDeleted ? 'Pesan telah dihapus' : $rawContent;

        return [
            'id'           => (int)    ($message['id'] ?? 0),
            'from_user_id' => (int)    ($message['from_user_id'] ?? 0),
            'to_user_id'   => (int)    ($message['to_user_id'] ?? 0),
            'created_at'   => (string) ($message['created_at'] ?? ''),
            'sender_name'  => (string) ($message['sender_name'] ?? ''),
            'content'      => $displayContent,
            'raw_content'  => ($isDeleted || $isCvCard) ? '' : $rawContent,
            'is_deleted'   => $isDeleted,
            'is_cv_card'   => $isCvCard,
            'cv_card_data' => $isCvCard ? $this->parseCvCard($rawContent) : null,
            'can_edit'     => $canEdit,
            'can_delete'   => $canDelete,
        ];
    }

    private function normalizeMessages(array $messages, int $partnerId, int $latestOwnMessageId): array {
        $normalized = [];
        foreach ($messages as $message) {
            $normalized[] = $this->normalizeMessage($message, $partnerId, $latestOwnMessageId);
        }
        return $normalized;
    }

    private function buildConversationStatusMeta($applicationContext): array {
        if (empty($applicationContext)) {
            return [
                'label' => 'Chat Dimulai',
                'variant' => 'info',
                'detail' => 'Percakapan telah dimulai.'
            ];
        }

        $statusKey = (string) ($applicationContext['status_key'] ?? '');
        $jobTitle = trim((string) ($applicationContext['job_title'] ?? ''));
        $companyName = trim((string) ($applicationContext['company_name'] ?? ''));
        $detail = $jobTitle !== ''
            ? 'Melamar posisi: ' . $jobTitle . ($companyName !== '' ? ' - ' . $companyName : '')
            : 'Percakapan terkait lamaran sudah aktif.';

        if (hasRole('hrd') || hasRole('admin')) {
            $staffMap = [
                'pending' => ['label' => 'Lamaran Baru', 'variant' => 'info'],
                'screening' => ['label' => 'Screening', 'variant' => 'info'],
                'interview' => ['label' => 'Interview', 'variant' => 'primary'],
                'accepted' => ['label' => 'Diterima', 'variant' => 'success'],
                'rejected' => ['label' => 'Ditolak', 'variant' => 'neutral']
            ];

            $meta = $staffMap[$statusKey] ?? ['label' => 'Status Lamaran', 'variant' => 'info'];
            $meta['detail'] = $detail;
            return $meta;
        }

        $candidateMap = [
            'pending' => ['label' => 'Lamaran Baru', 'variant' => 'info'],
            'screening' => ['label' => 'Screening', 'variant' => 'info'],
            'interview' => ['label' => 'Interview', 'variant' => 'primary'],
            'accepted' => ['label' => 'Diterima', 'variant' => 'success'],
            'rejected' => ['label' => 'Ditolak', 'variant' => 'neutral']
        ];

        $meta = $candidateMap[$statusKey] ?? ['label' => 'Chat Dimulai', 'variant' => 'info'];
        $meta['detail'] = $detail;
        return $meta;
    }

    /**
     * Get application context by application_id
     */
    private function getApplicationContext(int $applicationId, int $partnerId, string $partnerRole) {
        if ($applicationId <= 0) {
            return null;
        }

        $application = db()->row(
            "SELECT a.id as application_id, a.status, a.cv_file, a.applied_at,
                    j.title as job_title, j.location, j.type, j.salary_min, j.salary_max, j.department
             FROM applications a
             JOIN jobs j ON a.job_id = j.job_id
             WHERE a.id = ?",
            [$applicationId]
        );

        if (!$application) {
            return null;
        }

        $statusLabels = [
            'pending' => 'Lamaran Baru',
            'screening' => 'Screening',
            'interview' => 'Interview',
            'accepted' => 'Diterima',
            'rejected' => 'Ditolak'
        ];

        $statusKey = (string) ($application['status'] ?? '');
        $statusText = $statusLabels[$statusKey] ?? ucfirst($statusKey);

        $benefits = [];
        $salaryMin = trim((string) ($application['salary_min'] ?? ''));
        $salaryMax = trim((string) ($application['salary_max'] ?? ''));
        if ($salaryMin !== '' || $salaryMax !== '') {
            if ($salaryMin !== '' && $salaryMax !== '') {
                $benefits[] = 'Rentang gaji: ' . $this->formatRupiah($salaryMin) . ' - ' . $this->formatRupiah($salaryMax);
            } else {
                $benefits[] = 'Rentang gaji: ' . $this->formatRupiah($salaryMin !== '' ? $salaryMin : $salaryMax);
            }
        }

        $type = trim((string) ($application['type'] ?? ''));
        if ($type !== '') {
            $benefits[] = 'Tipe kerja: ' . $type;
        }

        $department = trim((string) ($application['department'] ?? ''));
        if ($department !== '') {
            $benefits[] = 'Departemen: ' . $department;
        }

        if (empty($benefits)) {
            $benefits[] = 'Benefit mengikuti kebijakan perusahaan.';
        }

        return [
            'application_id' => (int) ($application['application_id'] ?? 0),
            'status_key' => $statusKey,
            'status_label' => $statusText,
            'status' => $statusText,
            'job_title' => (string) ($application['job_title'] ?? ''),
            'company_name' => 'PT Digdaya Solusi Teknologi',
            'location' => (string) ($application['location'] ?? ''),
            'benefits' => $benefits,
            'cv_available' => !empty($application['cv_file']),
            'cv_url' => !empty($application['cv_file']) && !empty($application['application_id'])
                ? BASE_URL . 'applications/downloadCv/' . (int) $application['application_id']
                : null
        ];
    }

    private function formatRupiah(string $value): string {
        $clean = preg_replace('/[^0-9]/', '', $value);
        if ($clean === '' || !is_numeric($clean)) {
            return $value;
        }

        return 'Rp ' . number_format((float) $clean, 0, ',', '.');
    }
}
