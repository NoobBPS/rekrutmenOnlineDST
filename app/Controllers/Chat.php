<?php
/**
 * Chat Controller - DST Recruitment System
 */

class Chat extends Controller {
    private const DELETED_MARKER = '__MSG_DELETED__';
    private const EDIT_WINDOW_SECONDS = 10;

    public function __construct() {
        parent::__construct();
    }

    /**
     * Daftar Percakapan
     */
    public function index() {
        if (!isLoggedIn()) {
            redirect('auth/login');
        }

        // Admin tidak bisa akses chat
        if (hasRole('admin')) {
            redirect('jobs/manage');
        }

        $user_id = (int) $_SESSION['user_id'];

        if (hasRole('hrd')) {
            $this->hrdIndex($user_id);
            return;
        }

        // Untuk kandidat: tampilkan percakapan dikelompokkan per posisi
        $conversations = $this->getUserConversations($user_id, 'user');

        $data = [
            'title' => 'Pesan - DST Recruitment',
            'page' => 'chat',
            'conversations' => $conversations
        ];

        $this->view('layouts/header', $data);
        $this->view('chat/index', $data);
        $this->view('layouts/footer');
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

        $this->view('layouts/header', $data);
        $this->view('chat/index', $data);
        $this->view('layouts/footer');
    }

    /**
     * Get conversations grouped by application (position)
     */
    private function getUserConversations(int $userId, string $role): array {
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
        if (!isLoggedIn()) {
            redirect('auth/login');
        }

        if (hasRole('admin')) {
            redirect('jobs/manage');
        }

        $partner_id = (int) $partner_id;
        $application_id = (int) ($_GET['application_id'] ?? 0);

        if ($partner_id <= 0) {
            redirect('chat');
        }

        $partner = $this->findPartner($partner_id);
        if (!$partner) {
            redirect('chat');
        }

        // Determine which application to use
        if ($application_id <= 0) {
            // Ambil application_id dari conversation terakhir dengan partner ini
            $latest = db()->row(
                "SELECT application_id FROM messages
                 WHERE (from_user_id = ? AND to_user_id = ?)
                    OR (from_user_id = ? AND to_user_id = ?)
                 ORDER BY id DESC LIMIT 1",
                [(int) $_SESSION['user_id'], $partner_id, $partner_id, (int) $_SESSION['user_id']]
            );
            $application_id = (int) ($latest['application_id'] ?? 0);
        }

        if (!hasRole('hrd') && !hasRole('admin') && !in_array((string) $partner['role'], ['hrd', 'admin'], true)) {
            setFlash('error', 'Anda hanya dapat berkomunikasi dengan HRD');
            redirect('chat');
        }

        if (!$this->canAccessConversation($partner_id, $application_id)) {
            setFlash('error', 'Anda belum dapat memulai percakapan');
            redirect('chat');
        }

        $user_id = (int) $_SESSION['user_id'];

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

        $this->view('layouts/header', $data);
        $this->view('chat/room', $data);
        $this->view('layouts/footer');
    }

    /**
     * Kirim Pesan
     */
    public function send() {
        if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request']);
        }

        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Token keamanan tidak valid']);
        }

        $to_user_id = (int) ($_POST['to_user_id'] ?? 0);
        $application_id = (int) ($_POST['application_id'] ?? 0);
        $content = sanitize($_POST['content'] ?? '');

        if ($to_user_id <= 0 || $content === '') {
            $this->json(['success' => false, 'message' => 'Data tidak lengkap']);
        }

        if (strlen($content) > 1000) {
            $this->json(['success' => false, 'message' => 'Pesan maksimal 1000 karakter']);
        }

        if (!$this->canAccessConversation($to_user_id, $application_id)) {
            $this->json(['success' => false, 'message' => 'Tidak dapat mengirim pesan']);
        }

        db()->execute(
            "INSERT INTO messages (from_user_id, to_user_id, application_id, content) VALUES (?, ?, ?, ?)",
            [(int) $_SESSION['user_id'], $to_user_id, $application_id > 0 ? $application_id : null, $content]
        );

        $this->json(['success' => true, 'message' => 'Pesan terkirim']);
    }

    /**
     * Mulai Percakapan (HRD only) - dengan application_id
     */
    public function start($application_id) {
        if (!hasRole('hrd') && !hasRole('admin')) {
            redirect('dashboard');
        }

        $application_id = (int) $application_id;
        if ($application_id <= 0) {
            redirect('applications/hrd');
        }

        // Get application and user
        $application = db()->row(
            "SELECT a.id, a.user_id, u.full_name FROM applications a
             JOIN users u ON a.user_id = u.user_id
             WHERE a.id = ?",
            [$application_id]
        );

        if (!$application) {
            redirect('applications/hrd');
        }

        // Untuk admin, tidak boleh chat
        if (hasRole('admin')) {
            redirect('jobs/manage');
        }

        $hrd_id = (int) $_SESSION['user_id'];
        $user_id = (int) $application['user_id'];

        // Check if there's already a conversation for this application
        $existing = db()->row(
            "SELECT id FROM messages
             WHERE application_id = ?
             AND ((from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?))
             LIMIT 1",
            [$application_id, $hrd_id, $user_id, $user_id, $hrd_id]
        );

        // Selalu direct ke room dengan application_id - tidak reuse conversation lama
        setFlash('success', 'Membuka percakapan dengan ' . $application['full_name']);
        redirect('chat/room/' . $user_id . '?application_id=' . $application_id);
    }

    /**
     * Get Messages (AJAX) - filtered by application_id
     */
    public function getMessages($partner_id) {
        if (!isLoggedIn()) {
            $this->json(['success' => false, 'message' => 'Unauthorized']);
        }

        $partner_id = (int) $partner_id;
        $application_id = (int) ($_GET['application_id'] ?? 0);
        $last_id = (int) ($_GET['last_id'] ?? 0);

        if ($partner_id <= 0 || !$this->canAccessConversation($partner_id, $application_id)) {
            $this->json(['success' => false, 'message' => 'Akses percakapan ditolak']);
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

        $this->json(['success' => true, 'messages' => $messages]);
    }

    public function editMessage() {
        if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request']);
        }

        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Token keamanan tidak valid']);
        }

        $message_id = (int) ($_POST['message_id'] ?? 0);
        $to_user_id = (int) ($_POST['to_user_id'] ?? 0);
        $content = sanitize($_POST['content'] ?? '');

        if ($message_id <= 0 || $to_user_id <= 0 || $content === '') {
            $this->json(['success' => false, 'message' => 'Data edit pesan tidak lengkap']);
        }

        if (strlen($content) > 1000) {
            $this->json(['success' => false, 'message' => 'Pesan maksimal 1000 karakter']);
        }

        $message = db()->row(
            "SELECT id, from_user_id, to_user_id, content, created_at FROM messages WHERE id = ?",
            [$message_id]
        );

        if (!$message) {
            $this->json(['success' => false, 'message' => 'Pesan tidak ditemukan']);
        }

        if ((int) $message['from_user_id'] !== (int) $_SESSION['user_id']) {
            $this->json(['success' => false, 'message' => 'Anda hanya dapat mengedit pesan sendiri']);
        }

        if ((int) $message['to_user_id'] !== $to_user_id) {
            $this->json(['success' => false, 'message' => 'Pesan tidak sesuai percakapan aktif']);
        }

        if ($this->isDeletedContent((string) $message['content'])) {
            $this->json(['success' => false, 'message' => 'Pesan yang sudah dihapus tidak dapat diedit']);
        }

        $latestOwnMessageId = $this->getLatestOwnMessageIdForConversation((int) $_SESSION['user_id'], $to_user_id, (int) ($message['application_id'] ?? 0));
        if (!$this->canEditMessageRules($message, $to_user_id, $latestOwnMessageId)) {
            $this->json(['success' => false, 'message' => 'Pesan hanya bisa diedit dalam 10 detik setelah dikirim']);
        }

        $updated = db()->execute(
            "UPDATE messages SET content = ? WHERE id = ?",
            [$content, $message_id]
        );

        if (!$updated) {
            $this->json(['success' => false, 'message' => 'Gagal mengedit pesan']);
        }

        $refreshed = db()->row(
            "SELECT id, from_user_id, to_user_id, content, created_at FROM messages WHERE id = ?",
            [$message_id]
        );

        $latestOwnMessageId = $this->getLatestOwnMessageIdForConversation((int) $_SESSION['user_id'], $to_user_id, (int) ($refreshed['application_id'] ?? 0));
        $normalized = $this->normalizeMessage($refreshed, $to_user_id, $latestOwnMessageId);

        $this->json([
            'success' => true,
            'message' => 'Pesan berhasil diperbarui',
            'data' => $normalized
        ]);
    }

    public function deleteMessage() {
        if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request']);
        }

        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Token keamanan tidak valid']);
        }

        $message_id = (int) ($_POST['message_id'] ?? 0);
        $to_user_id = (int) ($_POST['to_user_id'] ?? 0);

        if ($message_id <= 0 || $to_user_id <= 0) {
            $this->json(['success' => false, 'message' => 'Data hapus pesan tidak lengkap']);
        }

        $message = db()->row(
            "SELECT id, from_user_id, to_user_id, content, created_at FROM messages WHERE id = ?",
            [$message_id]
        );

        if (!$message) {
            $this->json(['success' => false, 'message' => 'Pesan tidak ditemukan']);
        }

        if ((int) $message['from_user_id'] !== (int) $_SESSION['user_id']) {
            $this->json(['success' => false, 'message' => 'Anda hanya dapat menghapus pesan sendiri']);
        }

        if ((int) $message['to_user_id'] !== $to_user_id) {
            $this->json(['success' => false, 'message' => 'Pesan tidak sesuai percakapan aktif']);
        }

        if ($this->isDeletedContent((string) $message['content'])) {
            $this->json(['success' => true, 'message' => 'Pesan sudah dihapus sebelumnya']);
        }

        $deleted = db()->execute(
            "UPDATE messages SET content = ? WHERE id = ?",
            [self::DELETED_MARKER, $message_id]
        );
        if (!$deleted) {
            $this->json(['success' => false, 'message' => 'Gagal menghapus pesan']);
        }

        $refreshed = db()->row(
            "SELECT id, from_user_id, to_user_id, content, created_at FROM messages WHERE id = ?",
            [$message_id]
        );

        $latestOwnMessageId = $this->getLatestOwnMessageIdForConversation((int) $_SESSION['user_id'], $to_user_id, (int) ($refreshed['application_id'] ?? 0));
        $normalized = $this->normalizeMessage($refreshed, $to_user_id, $latestOwnMessageId);

        $this->json(['success' => true, 'message' => 'Pesan berhasil dihapus', 'data' => $normalized]);
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
            // HRD hanya boleh chat dengan kandidat yang apply ke job miliknya
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

        // Kandidat hanya bisa chat dengan HRD yang handle job yang dilamar
        if ($application_id > 0) {
            $existing = db()->row(
                "SELECT id FROM messages
                 WHERE application_id = ?
                 AND ((from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?))
                 LIMIT 1",
                [$application_id, (int) $_SESSION['user_id'], $partner_id, $partner_id, (int) $_SESSION['user_id']]
            );
            return !empty($existing);
        }

        return true;
    }

    private function isDeletedContent(string $content): bool {
        return trim($content) === self::DELETED_MARKER;
    }

    private function previewMessage(string $content): string {
        return $this->isDeletedContent($content) ? 'Pesan telah dihapus' : $content;
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
        return $age >= 0 && $age <= self::EDIT_WINDOW_SECONDS;
    }

    private function normalizeMessage(array $message, int $partnerId, int $latestOwnMessageId): array {
        $isDeleted = $this->isDeletedContent((string) ($message['content'] ?? ''));
        $displayContent = $isDeleted ? 'Pesan telah dihapus' : (string) ($message['content'] ?? '');
        $canEdit = $this->canEditMessageRules($message, $partnerId, $latestOwnMessageId);
        $canDelete = (int) ($message['from_user_id'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0) && !$isDeleted;

        return [
            'id' => (int) ($message['id'] ?? 0),
            'from_user_id' => (int) ($message['from_user_id'] ?? 0),
            'to_user_id' => (int) ($message['to_user_id'] ?? 0),
            'created_at' => (string) ($message['created_at'] ?? ''),
            'sender_name' => (string) ($message['sender_name'] ?? ''),
            'content' => $displayContent,
            'raw_content' => $isDeleted ? '' : (string) ($message['content'] ?? ''),
            'is_deleted' => $isDeleted,
            'can_edit' => $canEdit,
            'can_delete' => $canDelete
        ];
    }

    private function normalizeMessages(array $messages, int $partnerId, int $latestOwnMessageId): array {
        $normalized = [];
        foreach ($messages as $message) {
            $normalized[] = $this->normalizeMessage($message, $partnerId, $latestOwnMessageId);
        }
        return $normalized;
    }

    private function buildConversationStatusMeta(?array $applicationContext): array {
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
    private function getApplicationContext(int $applicationId, int $partnerId, string $partnerRole): ?array {
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
