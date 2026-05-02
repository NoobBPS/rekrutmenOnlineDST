<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title ?? 'DST Recruitment') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<?php $hasMobileBottomNav = isLoggedIn() && !hasRole('hrd') && !hasRole('admin'); ?>
<body class="<?= $hasMobileBottomNav ? 'has-mobile-bottom-nav' : '' ?>">
    <?php
    $currentRoute = trim((string) ($_GET['route'] ?? 'dashboard'), '/');
    $currentRoute = $currentRoute === '' ? 'dashboard' : $currentRoute;
    $routeSegments = explode('/', strtolower($currentRoute));
    $currentSection = $routeSegments[0] ?? 'dashboard';
    $currentPage = $page ?? '';

    $isStaff = isLoggedIn() && (hasRole('hrd') || hasRole('admin'));
    $isDashboardActive = in_array($currentPage, ['dashboard', 'dashboard-hrd'], true) || $currentSection === 'dashboard';
    $isJobsActive = in_array($currentPage, ['jobs', 'job-detail', 'apply'], true) || $currentSection === 'jobs';
    $isApplicationsActive = in_array($currentPage, ['my-applications', 'application-detail', 'hrd-applications'], true) || $currentSection === 'applications';
    $isChatActive = in_array($currentPage, ['chat', 'chat-room'], true) || $currentSection === 'chat';
    $isProfileActive = in_array($currentPage, ['profile', 'profile-edit', 'change-password'], true) || $currentSection === 'profile';
    ?>
    <nav class="navbar">
        <div class="container nav-wrap">
            <a href="<?= BASE_URL ?>" class="brand">DST Recruitment</a>

            <button class="menu-toggle" id="menu-toggle" aria-label="Buka menu">Menu</button>

            <?php if (isLoggedIn()): ?>
            <ul class="nav-menu" id="nav-menu">
                <li><a href="<?= BASE_URL ?>dashboard" class="<?= $isDashboardActive ? 'active' : '' ?>"><?= $isStaff ? 'Dashboard' : 'Home' ?></a></li>
                <li><a href="<?= BASE_URL ?>jobs" class="<?= $isJobsActive ? 'active' : '' ?>">Lowongan</a></li>
                <?php if (hasRole('hrd') || hasRole('admin')): ?>
                <li><a href="<?= BASE_URL ?>jobs/manage" class="<?= in_array($currentPage, ['manage-jobs', 'job-form'], true) ? 'active' : '' ?>">Kelola Lowongan</a></li>
                <li><a href="<?= BASE_URL ?>applications/hrd" class="<?= $isApplicationsActive ? 'active' : '' ?>">Pelamar</a></li>
                <?php else: ?>
                <li><a href="<?= BASE_URL ?>applications" class="<?= $isApplicationsActive ? 'active' : '' ?>">Lamaran Saya</a></li>
                <?php endif; ?>
                <li><a href="<?= BASE_URL ?>chat" class="<?= $isChatActive ? 'active' : '' ?>">Pesan</a></li>
                <li class="dropdown">
                    <a href="#" class="dropbtn <?= $isProfileActive ? 'active' : '' ?>"><?= h($_SESSION['full_name'] ?? 'Akun') ?></a>
                    <div class="dropdown-content">
                        <a href="<?= BASE_URL ?>profile" class="<?= $isProfileActive ? 'active' : '' ?>">Profil</a>
                        <a href="<?= BASE_URL ?>auth/logout">Logout</a>
                    </div>
                </li>
            </ul>
            <?php else: ?>
            <ul class="nav-menu" id="nav-menu">
                <li><a href="<?= BASE_URL ?>auth/login">Login</a></li>
                <li><a href="<?= BASE_URL ?>auth/register" class="btn btn-primary">Daftar</a></li>
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
