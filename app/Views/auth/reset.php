<div class="auth-container">
    <div class="auth-card">
        <div class="auth-logo">
            <img src="<?= BASE_URL ?>assets/images/logoDST.png" alt="Logo DST">
        </div>
        <h2>Reset Password</h2>
        <p class="text-muted">Masukkan password baru untuk akun <strong><?= h($email) ?></strong>.</p>
        <form action="<?= BASE_URL ?>auth/doResetPassword" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="token" value="<?= h($token) ?>">
            <div class="form-group">
                <label>Password Baru</label>
                <input type="password" name="password" required minlength="6" placeholder="Minimal 6 karakter">
            </div>
            <div class="form-group">
                <label>Konfirmasi Password</label>
                <input type="password" name="confirm_password" required placeholder="Ulangi password baru">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Ubah Password</button>
        </form>
        <p class="auth-link"><a href="<?= BASE_URL ?>auth/login">Kembali ke Login</a></p>
    </div>
</div>
