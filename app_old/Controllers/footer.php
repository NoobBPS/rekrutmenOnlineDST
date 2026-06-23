    </main> <!-- Closing main tag from header -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-copyright">
                    &copy; 2026 <strong>PT Digdaya Solusi Teknologi</strong>. All rights reserved.
                </div>
                <div class="footer-links">
                    <a href="<?= BASE_URL ?>jobs">Lowongan</a>
                    <?php if (isLoggedIn()): ?>
                        <a href="<?= BASE_URL ?>profile">Profil</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>