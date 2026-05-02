<div class="chat-container">
    <h1>Pesan</h1>

    <?php if (empty($conversations)): ?>
    <div class="empty-state card">
        <p>Belum ada percakapan.</p>
        <?php if (hasRole('hrd') || hasRole('admin')): ?>
        <p>Anda bisa memulai chat dari halaman pelamar.</p>
        <?php else: ?>
        <p>HRD akan menghubungi Anda jika profil Anda cocok.</p>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="conversations-list">
        <?php foreach ($conversations as $conv): ?>
        <a href="<?= BASE_URL ?>chat/room/<?= (int) $conv['partner_id'] ?>" class="conversation-item">
            <div class="conv-avatar">
                <?= strtoupper(substr($conv['partner_name'], 0, 1)) ?>
            </div>
            <div class="conv-content">
                <div class="conv-header">
                    <strong><?= h($conv['partner_name']) ?></strong>
                    <?php if ((int) $conv['unread'] > 0): ?>
                    <span class="unread-badge"><?= (int) $conv['unread'] ?></span>
                    <?php endif; ?>
                </div>
                <p class="conv-preview"><?= h($conv['last_message'] ?? 'Belum ada pesan') ?></p>
                <span class="conv-time"><?= timeAgo($conv['last_time'] ?? '') ?></span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>