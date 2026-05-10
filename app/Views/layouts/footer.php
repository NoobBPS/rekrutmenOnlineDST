        </div>
    </main>

    <?php
    $showMobileBottomNav = isLoggedIn() && !hasRole('hrd') && !hasRole('admin');
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
    if ($currentSection === 'applications') {
        $mobileActivePage = 'applied';
    } elseif ($currentSection === 'chat') {
        $mobileActivePage = 'chat';
    } elseif ($currentSection === 'profile') {
        $mobileActivePage = 'profile';
    }
    ?>

    <?php if ($showMobileBottomNav): ?>
    <nav class="mobile-bottom-nav" aria-label="Navigasi utama mobile">
        <a href="<?= BASE_URL ?>dashboard" class="mobile-bottom-nav__item <?= $mobileActivePage === 'home' ? 'is-active' : '' ?>" <?= $mobileActivePage === 'home' ? 'aria-current="page"' : '' ?>>
            <span class="mobile-bottom-nav__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M3.5 10.5L12 3.75L20.5 10.5V19.25C20.5 19.94 19.94 20.5 19.25 20.5H4.75C4.06 20.5 3.5 19.94 3.5 19.25V10.5Z" />
                    <path d="M9 20.5V14.25C9 13.56 9.56 13 10.25 13H13.75C14.44 13 15 13.56 15 14.25V20.5" />
                </svg>
            </span>
            <span class="mobile-bottom-nav__label">Home</span>
        </a>
        <a href="<?= BASE_URL ?>applications" class="mobile-bottom-nav__item <?= $mobileActivePage === 'applied' ? 'is-active' : '' ?>" <?= $mobileActivePage === 'applied' ? 'aria-current="page"' : '' ?>>
            <span class="mobile-bottom-nav__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M8.5 6V4.75C8.5 4.06 9.06 3.5 9.75 3.5H14.25C14.94 3.5 15.5 4.06 15.5 4.75V6" />
                    <path d="M4 7.5H20V18.25C20 19.49 18.99 20.5 17.75 20.5H6.25C5.01 20.5 4 19.49 4 18.25V7.5Z" />
                    <path d="M4 10.25C6.62 11.51 9.28 12.12 12 12.12C14.72 12.12 17.38 11.51 20 10.25" />
                </svg>
            </span>
            <span class="mobile-bottom-nav__label">Applied</span>
        </a>
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

    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> PT Digdaya Solusi Teknologi</p>
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
