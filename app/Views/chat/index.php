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
        <?php $partnerAvatarUrl = avatarUrl($conv['partner_avatar'] ?? null); ?>
        <a href="<?= BASE_URL ?>chat/room/<?= (int) $conv['partner_id'] ?>" class="conversation-item">
            <div class="conv-avatar">
                <?php if ($partnerAvatarUrl): ?>
                <img src="<?= h($partnerAvatarUrl) ?>" class="conv-avatar-image" alt="Foto profil <?= h($conv['partner_name']) ?>">
                <?php else: ?>
                <?= h(avatarInitial($conv['partner_name'] ?? '')) ?>
                <?php endif; ?>
            </div>
            <div class="conv-content">
                <div class="conv-header">
                    <div class="conv-header-main">
                        <div class="conv-name-stack">
                            <strong><?= h($conv['partner_name']) ?></strong>
                            <?php if (!empty($conv['chat_status_label'])): ?>
                            <span class="chat-status-badge chat-status-<?= h($conv['chat_status_variant'] ?? 'info') ?>">
                                <?= h($conv['chat_status_label']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="conv-meta-end">
                        <span class="conv-time"><?= timeAgo($conv['last_time'] ?? '') ?></span>
                        <?php if ((int) $conv['unread'] > 0): ?>
                        <span class="unread-badge"><?= (int) $conv['unread'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($conv['chat_status_detail'])): ?>
                <p class="conv-application"><?= h($conv['chat_status_detail']) ?></p>
                <?php endif; ?>
                <p class="conv-preview"><?= h($conv['last_message'] ?? 'Belum ada pesan') ?></p>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
