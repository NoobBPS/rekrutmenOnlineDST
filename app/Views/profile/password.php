<div class="auth-container">
    <div class="auth-card">
        <h2>Ganti Password</h2>
        <form action="<?= BASE_URL ?>profile/updatePassword" method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label>Password Saat Ini</label>
                <input type="password" name="current_password" required>
            </div>
            <div class="form-group">
                <label>Password Baru</label>
                <input type="password" name="new_password" minlength="6" required>
            </div>
            <div class="form-group" style="margin-bottom: 5px;">
                <label>Konfirmasi Password Baru</label>
                <input type="password" name="confirm_password" minlength="6" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 0px;">Simpan Password Baru</button>
            <a href="<?= BASE_URL ?>profile" class="btn btn-secondary" style="margin-top: 10px; display: block;">Kembali</a>
        </form>
    </div>
</div>