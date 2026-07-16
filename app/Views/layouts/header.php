<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title ?? 'DST Recruitment') ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/logoDST.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<?php
$isLoggedIn = isLoggedIn();
$isHRD = $isLoggedIn && hasRole('hrd');
$isAdmin = $isLoggedIn && hasRole('admin');
$isStaff = $isHRD || $isAdmin;
$hasMobileBottomNav = $isLoggedIn;
$currentPage = $page ?? '';
$bodyClasses = [];
if ($hasMobileBottomNav) {
    $bodyClasses[] = 'has-mobile-bottom-nav';
}
if ($isLoggedIn) {
    $bodyClasses[] = 'is-authenticated';
}
if ($currentPage === 'chat-room') {
    $bodyClasses[] = 'chat-room-page';
}
?>
<body class="<?= h(implode(' ', $bodyClasses)) ?>">
    <?php
    $currentRoute = trim((string) ($_GET['route'] ?? 'dashboard'), '/');
    $currentRoute = $currentRoute === '' ? 'dashboard' : $currentRoute;
    $routeSegments = explode('/', strtolower($currentRoute));
    $currentSection = $routeSegments[0] ?? 'dashboard';
    $currentAction = $routeSegments[1] ?? 'index';

    // Keep only one top-level nav item active at a time.
    $activeNavKey = 'dashboard';
    if (in_array($currentPage, ['manage-jobs', 'job-form'], true) || ($currentSection === 'jobs' && $currentAction === 'manage')) {
        $activeNavKey = 'manage-jobs';
    } elseif (in_array($currentPage, ['jobs', 'job-detail', 'apply'], true) || $currentSection === 'jobs') {
        $activeNavKey = 'jobs';
    } elseif (in_array($currentPage, ['chat', 'chat-room'], true) || $currentSection === 'chat') {
        $activeNavKey = 'chat';
    } elseif (in_array($currentPage, ['profile', 'profile-edit', 'change-password'], true) || $currentSection === 'profile') {
        $activeNavKey = 'profile';
    } elseif ($currentPage === 'application-detail') {
        $activeNavKey = $isStaff ? 'pelamar' : 'applications';
    } elseif ($currentPage === 'my-applications') {
        $activeNavKey = 'applications';
    } elseif (in_array($currentPage, ['hrd-applications'], true)) {
        $activeNavKey = 'pelamar';
    } elseif ($currentSection === 'applications') {
        $activeNavKey = $isStaff ? 'pelamar' : 'applications';
    } elseif (in_array($currentPage, ['dashboard', 'dashboard-hrd'], true) || $currentSection === 'dashboard') {
        $activeNavKey = 'dashboard';
    }
    ?>
    <?php
    // Calculate unread messages count for badge (safe: only when logged-in)
    $unreadCount = 0;
    $currentUserAvatarUrl = null;
    if ($isLoggedIn) {
        try {
            $row = db()->row("SELECT COUNT(*) as cnt FROM messages WHERE to_user_id = ? AND is_read = 0", [$_SESSION['user_id']]);
            $unreadCount = (int) ($row['cnt'] ?? 0);
        } catch (Throwable $e) {
            $unreadCount = 0;
        }

        try {
            $row = db()->row("SELECT avatar FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
            $currentUserAvatarUrl = avatarUrl($row['avatar'] ?? null);
        } catch (Throwable $e) {
            $currentUserAvatarUrl = null;
        }
    }
    $currentUserName = (string) ($_SESSION['full_name'] ?? 'Akun');
    $currentUserInitial = avatarInitial($currentUserName);
    ?>

    <nav class="navbar">
        <div class="container nav-wrap">
            <?php if (!$isLoggedIn): ?>
            <div class="guest-nav">
                <a href="<?= BASE_URL ?>auth/login" class="guest-login-btn">Login</a>
                <a href="<?= BASE_URL ?>" class="brand mx-auto text-center">DST Recruitment</a>
                <a href="<?= BASE_URL ?>auth/register" class="btn btn-primary guest-register-btn">Daftar</a>
            </div>
            <?php else: ?>
            <a href="<?= BASE_URL ?>" class="brand">DST Recruitment</a>
            <?php endif; ?>
            <?php if ($isLoggedIn && !$isAdmin): ?>
            <a href="<?= BASE_URL ?>chat" class="mobile-chat-shortcut <?= $activeNavKey === 'chat' ? 'is-active' : '' ?>" aria-label="Buka chat">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 4C7.03 4 3 7.47 3 11.75C3 13.87 3.99 15.78 5.6 17.12L5.13 20.25L8.15 18.85C9.34 19.25 10.64 19.5 12 19.5C16.97 19.5 21 16.03 21 11.75C21 7.47 16.97 4 12 4Z" />
                    <path d="M8.5 11.75H15.5" />
                    <path d="M8.5 14.5H12.75" />
                </svg>
                <?php if ($unreadCount > 0): ?>
                <span class="mobile-chat-shortcut__badge" aria-hidden="true"><?= (int) $unreadCount ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>

            <?php if ($isLoggedIn): ?>
            <button class="menu-toggle" id="menu-toggle" aria-label="Buka menu" aria-expanded="false" aria-controls="nav-menu">
                <span class="menu-toggle__icon" aria-hidden="true">&#9776;</span>
            </button>
            <?php endif; ?>

            <?php if ($isLoggedIn && $currentPage !== 'chat-room'): ?>
            <div class="mobile-profile-dropdown dropdown" data-dropdown>
                <button
                    type="button"
                    class="mobile-profile-trigger"
                    aria-label="Buka menu akun"
                    aria-expanded="false"
                    aria-controls="mobile-profile-menu"
                    data-dropdown-toggle
                >
                    <?php if ($currentUserAvatarUrl): ?>
                    <img src="<?= h($currentUserAvatarUrl) ?>" class="mobile-profile-avatar-image" alt="Foto profil <?= h($currentUserName) ?>">
                    <?php else: ?>
                    <span class="mobile-profile-avatar-fallback"><?= h($currentUserInitial) ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-content mobile-profile-menu" id="mobile-profile-menu" data-dropdown-menu>
                    <?php if ($isAdmin): ?>
                    <a href="<?= BASE_URL ?>jobs/manage">Kelola Lowongan</a>
                    <?php endif; ?>
                    <?php if ($isHRD): ?>
                    <a href="<?= BASE_URL ?>applications/hrd">Pelamar</a>
                    <?php endif; ?>
                    <?php if (!$isStaff): ?>
                    <a href="<?= BASE_URL ?>jobs">Lowongan</a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>profile">Profil Saya</a>
                    <a href="<?= BASE_URL ?>auth/logout">Logout</a>
                </div>
            </div>

            <ul class="nav-menu" id="nav-menu">
                <?php if (!$isAdmin): ?>
                <li><a href="<?= BASE_URL ?>dashboard" class="<?= $activeNavKey === 'dashboard' ? 'active' : '' ?>"><?= $isHRD ? 'Dashboard' : 'Home' ?></a></li>
                <?php endif; ?>
                <?php if (!$isStaff): ?>
                <li><a href="<?= BASE_URL ?>jobs" class="<?= $activeNavKey === 'jobs' ? 'active' : '' ?>">Lowongan</a></li>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                <li><a href="<?= BASE_URL ?>jobs/manage" class="<?= $activeNavKey === 'manage-jobs' ? 'active' : '' ?>">Kelola Lowongan</a></li>
                <?php endif; ?>
                <?php if ($isHRD): ?>
                <li><a href="<?= BASE_URL ?>applications/hrd" class="<?= $activeNavKey === 'pelamar' ? 'active' : '' ?>">Pelamar</a></li>
                <?php endif; ?>
                <?php if (!$isStaff): ?>
                <li><a href="<?= BASE_URL ?>applications" class="<?= $activeNavKey === 'applications' ? 'active' : '' ?>">Lamaran Saya</a></li>
                <?php endif; ?>
                <?php if (!$isAdmin): ?>
                <li>
                    <a href="<?= BASE_URL ?>chat" class="nav-chat-link <?= $activeNavKey === 'chat' ? 'active' : '' ?>">
                        <span class="nav-chat-link__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M12 4C7.03 4 3 7.47 3 11.75C3 13.87 3.99 15.78 5.6 17.12L5.13 20.25L8.15 18.85C9.34 19.25 10.64 19.5 12 19.5C16.97 19.5 21 16.03 21 11.75C21 7.47 16.97 4 12 4Z" />
                                <path d="M8.5 11.75H15.5" />
                                <path d="M8.5 14.5H12.75" />
                            </svg>
                            <?php if ($unreadCount > 0): ?>
                            <span class="nav-chat-link__badge" aria-hidden="true"><?= (int) $unreadCount ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="nav-chat-link__label">Pesan</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="dropdown nav-account-dropdown" data-dropdown>
                    <button
                        type="button"
                        class="dropbtn <?= $activeNavKey === 'profile' ? 'active' : '' ?>"
                        aria-expanded="false"
                        aria-controls="desktop-profile-menu"
                        data-dropdown-toggle
                    >
                        <?= h($currentUserName) ?>
                    </button>
                    <div class="dropdown-content" id="desktop-profile-menu" data-dropdown-menu>
                        <a href="<?= BASE_URL ?>profile" class="<?= $activeNavKey === 'profile' ? 'active' : '' ?>">Profil</a>
                        <a href="<?= BASE_URL ?>auth/logout">Logout</a>
                    </div>
                </li>
            </ul>
            <?php endif; ?>
        </div>
    </nav>

    <?php $flash = getFlash(); ?>
    <?php if (!empty($flash)): ?>
    <div class="container mt-16">
        <?php foreach ($flash as $type => $message): ?>
        <div class="alert alert-<?= h($type) ?>"><?= h($message) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <main class="main-content">
        <div class="container">
