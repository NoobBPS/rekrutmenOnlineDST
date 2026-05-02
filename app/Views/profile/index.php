<div class="profile-container">
    <div class="profile-header">
        <h1>Profil Saya</h1>
        <a href="<?= BASE_URL ?>profile/edit" class="btn btn-primary">Edit Profil</a>
    </div>

    <div class="profile-grid">
        <div class="profile-card">
            <div class="profile-avatar">
                <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
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

    <div class="profile-actions">
        <a href="<?= BASE_URL ?>profile/password" class="btn btn-secondary">Ganti Password</a>
    </div>
</div>