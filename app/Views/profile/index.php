<?php
// Ensure $user is defined. Normally the controller passes it, but some code paths may render this view
// without providing $user (causing undefined variable notices). If it's missing and the user is
// logged in, fetch from the database as a safe fallback.
if (!isset($user) || $user === null) {
    $user = [];
    if (function_exists('isLoggedIn') && isLoggedIn()) {
        $uid = $_SESSION['user_id'] ?? null;
        if ($uid) {
            try {
                $fetched = db()->row("SELECT * FROM users WHERE user_id = ?", [$uid]);
                if (!empty($fetched) && is_array($fetched)) {
                    $user = $fetched;
                }
            } catch (Throwable $e) {
                // If DB isn't available here, silently continue with empty user to avoid breaking the view.
            }
        }
    }
}

$profileAvatarUrl = avatarUrl($user['avatar'] ?? null);
?>
<div class="profile-container">
    <div class="profile-header">
        <h1>Profil Saya</h1>
        <a href="<?= BASE_URL ?>profile/edit" class="btn btn-primary">Edit Profil</a>
    </div>

    <div class="profile-grid">
        <div class="profile-card">
            <div class="profile-avatar">
                <?php if ($profileAvatarUrl): ?>
                <img src="<?= h($profileAvatarUrl) ?>" class="profile-avatar-image" alt="Foto profil <?= h($user['full_name']) ?>">
                <?php else: ?>
                <?= h(avatarInitial($user['full_name'] ?? '')) ?>
                <?php endif; ?>
            </div>
            <h2><?= h($user['full_name']) ?></h2>
            <p class="profile-email"><?= h($user['email']) ?></p>
        </div>

        <div class="profile-info">
            <div class="info-item">
                <label>Telepon</label>
                <p><?= h($user['phone'] ?? '-') ?></p>
            </div>
            <div class="info-item">
                <label>Pendidikan</label>
                <p><?= h($user['education'] ?? '-') ?></p>
            </div>
            <div class="info-item">
                <label>Pengalaman</label>
                <p><?= (int) ($user['experience_years'] ?? 0) ?> tahun</p>
            </div>
        </div>
    </div>

    <div class="profile-section profile-photo-section">
        <h3>Foto Profil</h3>
        <form action="<?= BASE_URL ?>profile/updateAvatar" method="POST" enctype="multipart/form-data" class="profile-photo-form">
            <?= csrfField() ?>
            <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" required>
            <div class="profile-photo-actions">
                <button type="submit" class="btn btn-primary">Upload Foto</button>
                <a href="<?= BASE_URL ?>profile/edit" class="btn btn-secondary">Edit Profil Lengkap</a>
            </div>
            <small>Maksimal 2MB. Format: JPG, PNG, WEBP.</small>
        </form>
    </div>

    <?php if (!empty($user['skills'])): ?>
    <div class="profile-section">
        <h3>Skills</h3>
        <div class="skills-list">
            <?php foreach (explode(',', $user['skills']) as $skill): ?>
            <span class="skill-tag"><?= h(trim($skill)) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($user['bio'])): ?>
    <div class="profile-section">
        <h3>Tentang Saya</h3>
        <p><?= nl2br(h($user['bio'])) ?></p>
    </div>
    <?php endif; ?>

    <div class="profile-actions" style="margin-top: 40px;">
        <a href="<?= BASE_URL ?>profile/password" class="btn btn-secondary">Ganti Password</a>
    </div>
</div>
