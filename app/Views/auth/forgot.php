<div class="auth-container">
    <div class="auth-card">
        <div class="auth-logo">
            <img src="<?= BASE_URL ?>assets/images/logoDST.png" alt="Logo DST">
        </div>
        <h2>Lupa Password</h2>
        <p class="text-muted">Masukkan email Anda untuk mendapatkan link reset password.</p>
        <form action="<?= BASE_URL ?>auth/doForgot" method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required placeholder="email@example.com">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Kirim Link Reset</button>
        </form>
        <p class="auth-link"><a href="<?= BASE_URL ?>auth/login">Kembali ke Login</a></p>
    </div>
</div>
