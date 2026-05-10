<div class="auth-container">
    <div class="auth-card">
        <h2>Login</h2>
        <form action="<?= BASE_URL ?>auth/doLogin" method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required placeholder="email@example.com">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="********">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Masuk</button>
        </form>
        <p class="auth-link">Belum punya akun? <a href="<?= BASE_URL ?>auth/register">Daftar di sini</a></p>
    </div>
</div>
