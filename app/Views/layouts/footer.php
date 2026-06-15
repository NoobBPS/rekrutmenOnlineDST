        </div>
    </main>

    <?php
    $showMobileBottomNav = isLoggedIn();
    $isHRD = hasRole('hrd');
    $isAdmin = hasRole('admin');
    $unreadCount = 0;
    if (isLoggedIn()) {
        try {
            $row = db()->row("SELECT COUNT(*) as cnt FROM messages WHERE to_user_id = ? AND is_read = 0", [$_SESSION['user_id']]);
            $unreadCount = (int) ($row['cnt'] ?? 0);
        } catch (Throwable $e) {
            $unreadCount = 0;
        }
    }
    $currentRoute = trim((string) ($_GET['route'] ?? 'dashboard'), '/');
    $currentRoute = $currentRoute === '' ? 'dashboard' : $currentRoute;
    $routeSegments = explode('/', strtolower($currentRoute));
    $currentSection = $routeSegments[0] ?? 'dashboard';

    $mobileActivePage = 'home';
    if ($currentSection === 'applications' || ($isAdmin && $currentSection === 'jobs')) {
        $mobileActivePage = 'applied';
    } elseif ($currentSection === 'chat') {
        $mobileActivePage = 'chat';
    } elseif ($currentSection === 'profile') {
        $mobileActivePage = 'profile';
    }

    $mobileHomeUrl = BASE_URL . 'dashboard';
    $mobileAppliedUrl = BASE_URL . 'applications';
    $mobileAppliedLabel = 'Applied';
    if ($isHRD) {
        $mobileHomeUrl = BASE_URL . 'dashboard/hrd';
        $mobileAppliedUrl = BASE_URL . 'applications/hrd';
        $mobileAppliedLabel = 'Pelamar';
    } elseif ($isAdmin) {
        $mobileHomeUrl = BASE_URL . 'jobs/manage';
        $mobileAppliedUrl = BASE_URL . 'jobs/manage';
        $mobileAppliedLabel = 'Lowongan';
    }
    ?>

    <?php if ($showMobileBottomNav): ?>
    <nav class="mobile-bottom-nav" aria-label="Navigasi utama mobile">
        <a href="<?= h($mobileHomeUrl) ?>" class="mobile-bottom-nav__item <?= $mobileActivePage === 'home' ? 'is-active' : '' ?>" <?= $mobileActivePage === 'home' ? 'aria-current="page"' : '' ?>>
            <span class="mobile-bottom-nav__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M3.5 10.5L12 3.75L20.5 10.5V19.25C20.5 19.94 19.94 20.5 19.25 20.5H4.75C4.06 20.5 3.5 19.94 3.5 19.25V10.5Z" />
                    <path d="M9 20.5V14.25C9 13.56 9.56 13 10.25 13H13.75C14.44 13 15 13.56 15 14.25V20.5" />
                </svg>
            </span>
            <span class="mobile-bottom-nav__label">Home</span>
        </a>
        <a href="<?= h($mobileAppliedUrl) ?>" class="mobile-bottom-nav__item <?= $mobileActivePage === 'applied' ? 'is-active' : '' ?>" <?= $mobileActivePage === 'applied' ? 'aria-current="page"' : '' ?>>
            <span class="mobile-bottom-nav__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M8.5 6V4.75C8.5 4.06 9.06 3.5 9.75 3.5H14.25C14.94 3.5 15.5 4.06 15.5 4.75V6" />
                    <path d="M4 7.5H20V18.25C20 19.49 18.99 20.5 17.75 20.5H6.25C5.01 20.5 4 19.49 4 18.25V7.5Z" />
                    <path d="M4 10.25C6.62 11.51 9.28 12.12 12 12.12C14.72 12.12 17.38 11.51 20 10.25" />
                </svg>
            </span>
            <span class="mobile-bottom-nav__label"><?= h($mobileAppliedLabel) ?></span>
        </a>
        <?php if (!$isAdmin): ?>
        <a href="<?= BASE_URL ?>chat" class="mobile-bottom-nav__item <?= $mobileActivePage === 'chat' ? 'is-active' : '' ?>" <?= $mobileActivePage === 'chat' ? 'aria-current="page"' : '' ?>>
            <span class="mobile-bottom-nav__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 4C7.03 4 3 7.47 3 11.75C3 13.87 3.99 15.78 5.6 17.12L5.13 20.25L8.15 18.85C9.34 19.25 10.64 19.5 12 19.5C16.97 19.5 21 16.03 21 11.75C21 7.47 16.97 4 12 4Z" />
                    <path d="M8.5 11.75H15.5" />
                    <path d="M8.5 14.5H12.75" />
                </svg>
            </span>
            <span class="mobile-bottom-nav__label">Chat</span>
            <?php if (!empty($unreadCount) && $unreadCount > 0): ?>
            <span class="nav-unread-badge" aria-hidden="true"><?= (int) $unreadCount ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>profile" class="mobile-bottom-nav__item <?= $mobileActivePage === 'profile' ? 'is-active' : '' ?>" <?= $mobileActivePage === 'profile' ? 'aria-current="page"' : '' ?>>
            <span class="mobile-bottom-nav__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 12.25C14.35 12.25 16.25 10.35 16.25 8C16.25 5.65 14.35 3.75 12 3.75C9.65 3.75 7.75 5.65 7.75 8C7.75 10.35 9.65 12.25 12 12.25Z" />
                    <path d="M4 20C4 16.96 7.58 14.5 12 14.5C16.42 14.5 20 16.96 20 20" />
                </svg>
            </span>
            <span class="mobile-bottom-nav__label">Profile</span>
        </a>
    </nav>
    <?php endif; ?>

    <footer class="footer <?= (($page ?? '') === 'chat-room') ? 'footer-chat-page' : '' ?>">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <h3>DST Recruitment</h3>
                    <p>Platform rekrutmen digital PT Digdaya Solusi Teknologi. Kami menghubungkan talenta terbaik dengan peluang karir cemerlang di bidang teknologi informasi.</p>
                    <div class="footer-socials">
                        <a href="https://instagram.com" target="_blank" aria-label="Instagram">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.051.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/>
                            </svg>
                        </a>
                        <a href="https://linkedin.com" target="_blank" aria-label="LinkedIn">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.779-1.75-1.75s.784-1.75 1.75-1.75 1.75.779 1.75 1.75-.784 1.75-1.75 1.75zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                            </svg>
                        </a>
                        <a href="https://facebook.com" target="_blank" aria-label="Facebook">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                <path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/>
                            </svg>
                        </a>
                        <a href="https://x.com" target="_blank" aria-label="X">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                            </svg>
                        </a>
                    </div>
                </div>
                <div class="footer-links">
                    <h4>Pencari Kerja</h4>
                    <ul>
                        <li><a href="<?= BASE_URL ?>jobs">Cari Lowongan</a></li>
                        <li><a href="<?= BASE_URL ?>profile">Profil Saya</a></li>
                        <li><a href="<?= BASE_URL ?>applications">Status Lamaran</a></li>
                        <li><a href="<?= BASE_URL ?>chat">Hubungi HRD</a></li>
                    </ul>
                </div>
                <div class="footer-links">
                    <h4>Pemberi Kerja / HRD</h4>
                    <ul>
                        <li><a href="<?= BASE_URL ?>dashboard/hrd">Dashboard HRD</a></li>
                        <li><a href="<?= BASE_URL ?>applications/hrd">Review Pelamar</a></li>
                        <li><a href="<?= BASE_URL ?>jobs/manage">Kelola Lowongan</a></li>
                        <li><a href="mailto:hrd@dst.co.id">Hubungi Support</a></li>
                    </ul>
                </div>
                <div class="footer-links">
                    <h4>PT Digdaya Solusi Teknologi</h4>
                    <ul>
                        <li><a href="<?= BASE_URL ?>about">Tentang Kami</a></li>
                        <li><a href="<?= BASE_URL ?>privacy">Kebijakan Privasi</a></li>
                        <li><a href="<?= BASE_URL ?>terms">Syarat & Ketentuan</a></li>
                        <li class="footer-verified">
                            <span class="verified-badge">Terdaftar & Diawasi</span>
                            <div class="verified-logos">
                                <span class="logo-text">KOMINFO</span>
                                <span class="logo-text">KEMENAKER</span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="footer-bottom-flex">
                    <p>&copy; 2026 PT Digdaya Solusi Teknologi. All rights reserved.</p>
                    <p class="footer-credit">Solusi Rekrutmen Terpercaya</p>
                </div>
            </div>
        </div>
    </footer>

    <script>
    (function () {
        var toggle = document.getElementById('menu-toggle');
        var menu = document.getElementById('nav-menu');
        var dropdowns = document.querySelectorAll('[data-dropdown]');

        function closeTabletMenu() {
            if (!menu || !toggle) return;
            menu.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        }

        if (toggle && menu) {
            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
                menu.classList.toggle('open');
                var expanded = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            });
        }

        function closeAllDropdowns(exceptDropdown) {
            dropdowns.forEach(function (dropdown) {
                if (exceptDropdown && dropdown === exceptDropdown) {
                    return;
                }

                dropdown.classList.remove('is-open');
                var trigger = dropdown.querySelector('[data-dropdown-toggle]');
                if (trigger) {
                    trigger.setAttribute('aria-expanded', 'false');
                }
            });
        }

        dropdowns.forEach(function (dropdown) {
            var trigger = dropdown.querySelector('[data-dropdown-toggle]');
            var panel = dropdown.querySelector('[data-dropdown-menu]');
            if (!trigger || !panel) return;

            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                var isOpen = dropdown.classList.contains('is-open');
                closeAllDropdowns(dropdown);
                dropdown.classList.toggle('is-open', !isOpen);
                trigger.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            });

            panel.addEventListener('click', function (event) {
                event.stopPropagation();
            });
        });

        document.addEventListener('click', function () {
            closeAllDropdowns();
            closeTabletMenu();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeAllDropdowns();
                closeTabletMenu();
            }
        });

        window.addEventListener('resize', function () {
            var width = window.innerWidth || document.documentElement.clientWidth;
            if (width <= 760 || width > 1024) {
                closeTabletMenu();
            }
        });
    })();
    </script>
</body>
</html>
