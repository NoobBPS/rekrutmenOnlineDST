<?php
/**
 * Chat Controller - DST Recruitment System
 */

class Chat extends Controller {
    private const DELETED_MARKER = '__MSG_DELETED__';
    private const EDIT_WINDOW_SECONDS = 600;

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

        $user_id = (int) $_SESSION['user_id'];

        if (hasRole('hrd') || hasRole('admin')) {
            $this->hrdIndex($user_id);
            return;
        }

        $conversations = db()->select(
            "SELECT DISTINCT
                    CASE WHEN m.from_user_id = ? THEN m.to_user_id ELSE m.from_user_id END as partner_id,
                    u.full_name as partner_name, u.role, u.avatar as partner_avatar,
                    (SELECT content FROM messages WHERE
                        (from_user_id = ? AND to_user_id = u.user_id) OR
                        (from_user_id = u.user_id AND to_user_id = ?)
                     ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM messages WHERE
                        (from_user_id = ? AND to_user_id = u.user_id) OR
                        (from_user_id = u.user_id AND to_user_id = ?)
                     ORDER BY created_at DESC LIMIT 1) as last_time,
                    (SELECT COUNT(*) FROM messages WHERE from_user_id = u.user_id AND to_user_id = ? AND is_read = 0) as unread
             FROM messages m
             JOIN users u ON u.user_id = CASE WHEN m.from_user_id = ? THEN m.to_user_id ELSE m.from_user_id END
             WHERE m.from_user_id = ? OR m.to_user_id = ?
             ORDER BY last_time DESC",
            [$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]
        );

        foreach ($conversations as &$conversation) {
            $conversation = $this->enrichConversationSummary($conversation);
        }
        unset($conversation);

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
     * HRD Daftar Percakapan
     */
    private function hrdIndex($hrd_id) {
        $conversations = db()->select(
            "SELECT DISTINCT
                    u.user_id as partner_id, u.full_name as partner_name, u.role, u.avatar as partner_avatar,
                    (SELECT content FROM messages WHERE
                        (from_user_id = ? AND to_user_id = u.user_id) OR
                        (from_user_id = u.user_id AND to_user_id = ?)
                     ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM messages WHERE
                        (from_user_id = ? AND to_user_id = u.user_id) OR
                        (from_user_id = u.user_id AND to_user_id = ?)
                     ORDER BY created_at DESC LIMIT 1) as last_time,
                    (SELECT COUNT(*) FROM messages WHERE from_user_id = u.user_id AND to_user_id = ? AND is_read = 0) as unread
             FROM messages m
             JOIN users u ON u.user_id = CASE WHEN m.from_user_id = ? THEN m.to_user_id ELSE m.from_user_id END
             WHERE u.role = 'user' AND (m.from_user_id = ? OR m.to_user_id = ?)
             ORDER BY last_time DESC",
            [$hrd_id, $hrd_id, $hrd_id, $hrd_id, $hrd_id, $hrd_id, $hrd_id, $hrd_id]
        );

        foreach ($conversations as &$conversation) {
            $conversation = $this->enrichConversationSummary($conversation);
        }
        unset($conversation);

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
     * Room Percakapan
     */
    public function room($partner_id) {
        if (!isLoggedIn()) {
            redirect('auth/login');
        }

        $partner_id = (int) $partner_id;
        if ($partner_id <= 0) {
            redirect('chat');
        }

        $partner = $this->findPartner($partner_id);
        if (!$partner) {
            redirect('chat');
        }

        if (!hasRole('hrd') && !hasRole('admin') && !in_array((string) $partner['role'], ['hrd', 'admin'], true)) {
            setFlash('error', 'Anda hanya dapat berkomunikasi dengan HRD');
            redirect('chat');
        }

        if (!$this->canAccessConversation($partner_id)) {
            setFlash('error', 'Anda belum dapat memulai percakapan');
            redirect('chat');
        }

        $user_id = (int) $_SESSION['user_id'];

        $messages = db()->select(
            "SELECT m.id, m.from_user_id, m.to_user_id, m.content, m.created_at, u.full_name as sender_name
             FROM messages m
             JOIN users u ON m.from_user_id = u.user_id
             WHERE (m.from_user_id = ? AND m.to_user_id = ?) OR (m.from_user_id = ? AND m.to_user_id = ?)
             ORDER BY m.created_at ASC",
            [$user_id, $partner_id, $partner_id, $user_id]
        );

        db()->execute(
            "UPDATE messages SET is_read = 1 WHERE from_user_id = ? AND to_user_id = ?",
            [$partner_id, $user_id]
        );

        $latestOwnMessageId = $this->getLatestOwnMessageIdForConversation($user_id, $partner_id);
        $messages = $this->normalizeMessages($messages, $partner_id, $latestOwnMessageId);

        $applicationContext = $this->getConversationApplicationContext($partner_id, (string) ($partner['role'] ?? ''));

        $data = [
            'title' => 'Percakapan dengan ' . $partner['full_name'] . ' - DST Recruitment',
            'page' => 'chat-room',
            'partner' => $partner,
            'messages' => $messages,
            'application_context' => $applicationContext
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
        $content = sanitize($_POST['content'] ?? '');

        if ($to_user_id <= 0 || $content === '') {
            $this->json(['success' => false, 'message' => 'Data tidak lengkap']);
        }

        if (strlen($content) > 1000) {
            $this->json(['success' => false, 'message' => 'Pesan maksimal 1000 karakter']);
        }

        if (!$this->canAccessConversation($to_user_id)) {
            $this->json(['success' => false, 'message' => 'Tidak dapat mengirim pesan']);
        }

        db()->execute(
            "INSERT INTO messages (from_user_id, to_user_id, content) VALUES (?, ?, ?)",
            [(int) $_SESSION['user_id'], $to_user_id, $content]
        );

        $this->json(['success' => true, 'message' => 'Pesan terkirim']);
    }

    /**
     * Mulai Percakapan (HRD only)
     */
    public function start($user_id) {
        if (!hasRole('hrd') && !hasRole('admin')) {
            redirect('dashboard');
        }

        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            redirect('applications/hrd');
        }

        $user = db()->row("SELECT user_id as id, full_name FROM users WHERE user_id = ?", [$user_id]);
        if (!$user) {
            redirect('applications/hrd');
        }

        $existing = db()->row(
            "SELECT id FROM messages WHERE (from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)",
            [(int) $_SESSION['user_id'], $user_id, $user_id, (int) $_SESSION['user_id']]
        );

        if (!$existing) {
            setFlash('success', 'Percakapan dimulai dengan ' . $user['full_name']);
        }

        redirect('chat/room/' . $user_id);
    }

    /**
     * Get Messages (AJAX)
     */
    public function getMessages($partner_id) {
        if (!isLoggedIn()) {
            $this->json(['success' => false, 'message' => 'Unauthorized']);
        }

        $partner_id = (int) $partner_id;
        $last_id = (int) ($_GET['last_id'] ?? 0);

        if ($partner_id <= 0 || !$this->canAccessConversation($partner_id)) {
            $this->json(['success' => false, 'message' => 'Akses percakapan ditolak']);
        }

        $where = "(m.from_user_id = ? AND m.to_user_id = ?) OR (m.from_user_id = ? AND m.to_user_id = ?)";
        $params = [(int) $_SESSION['user_id'], $partner_id, $partner_id, (int) $_SESSION['user_id']];

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
            "UPDATE messages SET is_read = 1 WHERE from_user_id = ? AND to_user_id = ?",
            [$partner_id, (int) $_SESSION['user_id']]
        );

        $latestOwnMessageId = $this->getLatestOwnMessageIdForConversation((int) $_SESSION['user_id'], $partner_id);
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

        if (!$this->canAccessConversation($to_user_id)) {
            $this->json(['success' => false, 'message' => 'Akses percakapan ditolak']);
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

        $latestOwnMessageId = $this->getLatestOwnMessageIdForConversation((int) $_SESSION['user_id'], $to_user_id);
        if (!$this->canEditMessageRules($message, $to_user_id, $latestOwnMessageId)) {
            $this->json(['success' => false, 'message' => 'Pesan hanya bisa diedit untuk pesan terkini dalam 10 menit']);
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

        if (!$refreshed) {
            $this->json(['success' => false, 'message' => 'Pesan tidak ditemukan setelah diperbarui']);
        }

        $latestOwnMessageId = $this->getLatestOwnMessageIdForConversation((int) $_SESSION['user_id'], $to_user_id);
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

        if (!$this->canAccessConversation($to_user_id)) {
            $this->json(['success' => false, 'message' => 'Akses percakapan ditolak']);
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

        if (!$refreshed) {
            $this->json(['success' => false, 'message' => 'Pesan tidak ditemukan setelah dihapus']);
        }

        $latestOwnMessageId = $this->getLatestOwnMessageIdForConversation((int) $_SESSION['user_id'], $to_user_id);
        $normalized = $this->normalizeMessage($refreshed, $to_user_id, $latestOwnMessageId);

        $this->json(['success' => true, 'message' => 'Pesan berhasil dihapus', 'data' => $normalized]);
    }

    private function findPartner($partner_id) {
        return db()->row(
            "SELECT user_id as id, full_name, role, avatar FROM users WHERE user_id = ?",
            [$partner_id]
        );
    }

    private function canAccessConversation($partner_id): bool {
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

        if (hasRole('hrd') || hasRole('admin')) {
            return true;
        }

        if (!in_array((string) ($partner['role'] ?? ''), ['hrd', 'admin'], true)) {
            return false;
        }

        $existing = db()->row(
            "SELECT id FROM messages WHERE (from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?) LIMIT 1",
            [(int) $_SESSION['user_id'], $partner_id, $partner_id, (int) $_SESSION['user_id']]
        );

        return !empty($existing);
    }

    private function isDeletedContent(string $content): bool {
        return trim($content) === self::DELETED_MARKER;
    }

    private function previewMessage(string $content): string {
        return $this->isDeletedContent($content) ? 'Pesan telah dihapus' : $content;
    }

    private function getLatestOwnMessageIdForConversation(int $currentUserId, int $partnerId): int {
        $row = db()->row(
            "SELECT id FROM messages
             WHERE from_user_id = ? AND to_user_id = ?
             ORDER BY id DESC
             LIMIT 1",
            [$currentUserId, $partnerId]
        );

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

    private function enrichConversationSummary(array $conversation): array {
        $conversation['last_message'] = $this->previewMessage((string) ($conversation['last_message'] ?? ''));
        $partnerId = (int) ($conversation['partner_id'] ?? 0);
        $partnerRole = (string) ($conversation['role'] ?? '');

        $context = $partnerId > 0 ? $this->getConversationApplicationContext($partnerId, $partnerRole) : null;
        $statusMeta = $this->buildConversationStatusMeta($context);

        $conversation['chat_status_label'] = (string) ($statusMeta['label'] ?? 'Chat Dimulai');
        $conversation['chat_status_variant'] = (string) ($statusMeta['variant'] ?? 'info');
        $conversation['chat_status_detail'] = (string) ($statusMeta['detail'] ?? '');
        $conversation['application_context'] = $context;

        return $conversation;
    }

    private function buildConversationStatusMeta(?array $applicationContext): array {
        if (empty($applicationContext)) {
            return [
                'label' => 'Terhubung dengan HRD',
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
            'pending' => ['label' => 'Telah Melamar / Chat Dimulai', 'variant' => 'info'],
            'screening' => ['label' => 'Telah Melamar / Chat Dimulai', 'variant' => 'info'],
            'interview' => ['label' => 'Terhubung dengan HRD', 'variant' => 'info'],
            'accepted' => ['label' => 'Terhubung dengan HRD', 'variant' => 'success'],
            'rejected' => ['label' => 'Belum Sesuai', 'variant' => 'neutral']
        ];

        $meta = $candidateMap[$statusKey] ?? ['label' => 'Chat Dimulai', 'variant' => 'info'];
        $meta['detail'] = $detail;
        return $meta;
    }

    private function getConversationApplicationContext(int $partnerId, string $partnerRole): ?array {
        $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
        if ($currentUserId <= 0) {
            return null;
        }

        $sql = "SELECT a.id as application_id, a.status, a.cv_file, a.applied_at,
                       j.title as job_title, j.location, j.type, j.salary_min, j.salary_max, j.department
                FROM applications a
                JOIN jobs j ON a.job_id = j.job_id";
        $params = [];
        $where = [];

        if (hasRole('hrd') || hasRole('admin')) {
            if ($partnerRole !== 'user') {
                return null;
            }

            $where[] = "a.user_id = ?";
            $params[] = $partnerId;

            if (hasRole('hrd') && !hasRole('admin')) {
                $where[] = "j.created_by = ?";
                $params[] = $currentUserId;
            }
        } else {
            $where[] = "a.user_id = ?";
            $params[] = $currentUserId;

            if ($partnerRole === 'hrd') {
                $where[] = "j.created_by = ?";
                $params[] = $partnerId;
            }
        }

        if (empty($where)) {
            return null;
        }

        $sql .= " WHERE " . implode(' AND ', $where) . " ORDER BY a.applied_at DESC LIMIT 1";
        $application = db()->row($sql, $params);

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
