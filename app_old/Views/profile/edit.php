<div class="profile-edit-container">
    <div class="page-header">
        <h1>Edit Profil</h1>
    </div>

    <form action="<?= BASE_URL ?>profile/update" method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div class="form-group">
            <label>Nama Lengkap</label>
            <input type="text" name="full_name" required value="<?= h($user['full_name']) ?>">
        </div>
        <div class="form-group">
            <label>Telepon</label>
            <input type="text" name="phone" value="<?= h($user['phone'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Pendidikan</label>
            <input type="text" name="education" placeholder="Contoh: S1 Teknik Informatika" value="<?= h($user['education'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Tahun Pengalaman</label>
            <input type="number" name="experience_years" min="0" value="<?= (int) ($user['experience_years'] ?? 0) ?>">
        </div>
        <div class="form-group">
            <label>Skills (pisahkan dengan koma)</label>
            <input type="text" name="skills" placeholder="PHP, MySQL, JavaScript" value="<?= h($user['skills'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Tentang Saya</label>
            <textarea name="bio" rows="4"><?= h($user['bio'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label>Avatar</label>
            <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp">
            <small>Maksimal 2MB. Format: JPG, PNG, WEBP.</small>
            <?php if (!empty($user['avatar'])): ?>
            <img src="<?= BASE_URL ?>uploads/avatars/<?= h($user['avatar']) ?>" class="avatar-preview" alt="Avatar">
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        <a href="<?= BASE_URL ?>profile" class="btn btn-secondary">Batal</a>
    </form>
</div>