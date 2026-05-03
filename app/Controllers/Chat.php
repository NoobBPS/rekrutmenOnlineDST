<?php
/**
 * Chat Controller - DST Recruitment System
 */

class Chat extends Controller {
    
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
        
        $user_id = $_SESSION['user_id'];
        
        if (hasRole('hrd') || hasRole('admin')) {
            $this->hrdIndex($user_id);
            return;
        }
        
        $conversations = db()->select(
            "SELECT DISTINCT 
                    CASE WHEN m.from_user_id = ? THEN m.to_user_id ELSE m.from_user_id END as partner_id,
                    u.full_name as partner_name, u.role, u.avatar as partner_avatar,
                    (SELECT content FROM messages WHERE 
                        (from_user_id = ? AND to_user_id = u.id) OR 
                        (from_user_id = u.id AND to_user_id = ?) 
                     ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM messages WHERE 
                        (from_user_id = ? AND to_user_id = u.id) OR 
                        (from_user_id = u.id AND to_user_id = ?) 
                     ORDER BY created_at DESC LIMIT 1) as last_time,
                    (SELECT COUNT(*) FROM messages WHERE from_user_id = u.id AND to_user_id = ? AND is_read = 0) as unread
             FROM messages m
             JOIN users u ON u.id = CASE WHEN m.from_user_id = ? THEN m.to_user_id ELSE m.from_user_id END
             WHERE m.from_user_id = ? OR m.to_user_id = ?
             ORDER BY last_time DESC",
            [$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]
        );
        
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
                    u.id as partner_id, u.full_name as partner_name, u.role, u.avatar as partner_avatar,
                    (SELECT content FROM messages WHERE 
                        (from_user_id = ? AND to_user_id = u.id) OR 
                        (from_user_id = u.id AND to_user_id = ?) 
                     ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM messages WHERE 
                        (from_user_id = ? AND to_user_id = u.id) OR 
                        (from_user_id = u.id AND to_user_id = ?) 
                     ORDER BY created_at DESC LIMIT 1) as last_time,
                    (SELECT COUNT(*) FROM messages WHERE from_user_id = u.id AND to_user_id = ? AND is_read = 0) as unread
             FROM messages m
             JOIN users u ON u.id = CASE WHEN m.from_user_id = ? THEN m.to_user_id ELSE m.from_user_id END
             WHERE u.role = 'user' AND (m.from_user_id = ? OR m.to_user_id = ?)
             ORDER BY last_time DESC",
            [$hrd_id, $hrd_id, $hrd_id, $hrd_id, $hrd_id, $hrd_id, $hrd_id, $hrd_id]
        );
        
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
        
        $partner_id = intval($partner_id);
        
        if ($partner_id <= 0) {
            redirect('chat');
        }
        
        // HRD only can initiate - user can't start
        if (!hasRole('hrd') && !hasRole('admin') && !db()->row("SELECT id FROM messages WHERE (from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)", 
            [$_SESSION['user_id'], $partner_id, $partner_id, $_SESSION['user_id']])) {
            setFlash('error', 'Anda belum dapat memulai percakapan');
            redirect('chat');
        }
        
        $partner = db()->row("SELECT id, full_name, role, avatar FROM users WHERE id = ?", [$partner_id]);
        
        if (!$partner) {
            redirect('chat');
        }

        if (!hasRole('hrd') && !hasRole('admin') && !in_array($partner['role'], ['hrd', 'admin'], true)) {
            setFlash('error', 'Anda hanya dapat berkomunikasi dengan HRD');
            redirect('chat');
        }
        
        $user_id = $_SESSION['user_id'];
        
        // Get messages
        $messages = db()->select(
            "SELECT m.*, u.full_name as sender_name 
             FROM messages m
             JOIN users u ON m.from_user_id = u.id
             WHERE (m.from_user_id = ? AND m.to_user_id = ?) OR (m.from_user_id = ? AND m.to_user_id = ?)
             ORDER BY m.created_at ASC",
            [$user_id, $partner_id, $partner_id, $user_id]
        );
        
        // Mark as read
        db()->execute(
            "UPDATE messages SET is_read = 1 WHERE from_user_id = ? AND to_user_id = ?",
            [$partner_id, $user_id]
        );
        
        $data = [
            'title' => 'Percakapan dengan ' . $partner['full_name'] . ' - DST Recruitment',
            'page' => 'chat-room',
            'partner' => $partner,
            'messages' => $messages
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
        
        $to_user_id = intval($_POST['to_user_id']);
        $content = sanitize($_POST['content'] ?? '');
        
        if ($to_user_id <= 0 || empty($content)) {
            $this->json(['success' => false, 'message' => 'Data tidak lengkap']);
        }

        if (strlen($content) > 1000) {
            $this->json(['success' => false, 'message' => 'Pesan maksimal 1000 karakter']);
        }
        
        // HRD can message anyone, user can only reply to HRD
        if (!hasRole('hrd') && !hasRole('admin')) {
            $check = db()->row(
                "SELECT id FROM messages WHERE from_user_id = ? AND to_user_id = ?",
                [$to_user_id, $_SESSION['user_id']]
            );
            if (!$check) {
                $this->json(['success' => false, 'message' => 'Tidak dapat mengirim pesan']);
            }
        }
        
        db()->execute(
            "INSERT INTO messages (from_user_id, to_user_id, content) VALUES (?, ?, ?)",
            [$_SESSION['user_id'], $to_user_id, $content]
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
        
        $user_id = intval($user_id);
        
        if ($user_id <= 0) {
            redirect('applications/hrd');
        }
        
        $user = db()->row("SELECT id, full_name FROM users WHERE id = ?", [$user_id]);
        
        if (!$user) {
            redirect('applications/hrd');
        }
        
        // Check if conversation exists
        $existing = db()->row(
            "SELECT id FROM messages WHERE (from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)",
            [$_SESSION['user_id'], $user_id, $user_id, $_SESSION['user_id']]
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
        
        $partner_id = intval($partner_id);
        $last_id = intval($_GET['last_id'] ?? 0);
        
        $where = "(m.from_user_id = ? AND m.to_user_id = ?) OR (m.from_user_id = ? AND m.to_user_id = ?)";
        $params = [$_SESSION['user_id'], $partner_id, $partner_id, $_SESSION['user_id']];
        
        if ($last_id > 0) {
            $where = "($where) AND m.id > ?";
            $params[] = $last_id;
        }
        
        $messages = db()->select(
            "SELECT m.id, m.content, m.from_user_id, m.created_at, u.full_name as sender_name
             FROM messages m
             JOIN users u ON m.from_user_id = u.id
             WHERE $where
             ORDER BY m.created_at ASC",
            $params
        );
        
        // Mark as read
        db()->execute(
            "UPDATE messages SET is_read = 1 WHERE from_user_id = ? AND to_user_id = ?",
            [$partner_id, $_SESSION['user_id']]
        );
        
        $this->json(['success' => true, 'messages' => $messages]);
    }
}
