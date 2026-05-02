<div class="auth-container">
    <div class="auth-card">
        <h2>Daftar Akun Baru</h2>
        <form action="<?= BASE_URL ?>auth/doRegister" method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="full_name" required placeholder="Nama Anda">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required placeholder="email@example.com">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required minlength="6" placeholder="Minimal 6 karakter">
            </div>
            <div class="form-group">
                <label>Konfirmasi Password</label>
                <input type="password" name="confirm_password" required placeholder="Ulangi password">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Daftar</button>
        </form>
        <p class="auth-link">Sudah punya akun? <a href="<?= BASE_URL ?>auth/login">Masuk di sini</a></p>
    </div>
</div>