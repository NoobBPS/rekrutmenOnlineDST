<div class="dashboard-container">
    <h1>Dashboard Kandidat</h1>
    <p>Selamat datang, <strong><?= h($user['full_name']) ?></strong>.</p>
    <div class="alert alert-info">
        Status lamaran kini ditampilkan di menu <strong>Chat</strong> pada setiap percakapan dengan HRD.
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= (int) $stats['total_jobs'] ?></div>
            <div class="stat-label">Lowongan Tersedia</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= (int) $stats['my_applications'] ?></div>
            <div class="stat-label">Lamaran Saya</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= (int) $stats['interview'] ?></div>
            <div class="stat-label">Interview</div>
        </div>
        <div class="stat-card stat-success">
            <div class="stat-value"><?= (int) $stats['accepted'] ?></div>
            <div class="stat-label">Diterima</div>
        </div>
    </div>

    <section class="dashboard-section">
        <h2>Lowongan Terbaru</h2>
        <?php if (empty($recent_jobs)): ?>
        <div class="empty-state card">
            <p>Saat ini belum ada lowongan aktif.</p>
        </div>
        <?php else: ?>
        <div class="jobs-grid">
            <?php foreach ($recent_jobs as $job): ?>
            <div class="job-card">
                <h3><?= h($job['title']) ?></h3>
                <p class="job-info"><?= h($job['location']) ?> | <?= h($job['type']) ?></p>
                <a href="<?= BASE_URL ?>jobs/detail/<?= (int) $job['id'] ?>" class="btn btn-sm">Lihat Detail</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>jobs" class="btn btn-primary">Lihat Semua Lowongan</a>
    </section>
</div>
