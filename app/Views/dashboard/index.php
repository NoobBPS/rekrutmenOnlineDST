<div class="dashboard-container">
    <h1>Dashboard Kandidat</h1>
    <p>Selamat datang, <strong><?= h($user['full_name']) ?></strong>.</p>

    <?php if (!empty($accepted_job)): ?>
    <div class="alert alert-success">
        <strong>Selamat anda diterima oleh perusahaan DST</strong> dengan posisi <strong><?= h($accepted_job['job_title']) ?></strong>.
    </div>
    <?php endif; ?>

    <?php if (!empty($rejected_job)): ?>
    <div class="alert alert-error">
        <strong>Maaf, anda kurang cocok di posisi ini</strong>: <?= h($rejected_job['job_title']) ?>.
    </div>
    <?php endif; ?>

    <?php if (!empty($final_decisions)): ?>
    <section class="dashboard-section">
        <h2>Alasan Hasil Seleksi</h2>
        <div class="decision-grid">
            <?php foreach ($final_decisions as $decision): ?>
            <?php
                $saw = $decision['decision_saw_display'] ?? [];
                $components = $saw['components'] ?? [];
            ?>
            <article class="decision-card <?= $decision['status'] === 'accepted' ? 'decision-accepted' : 'decision-rejected' ?>">
                <div class="decision-head">
                    <h3><?= h($decision['job_title']) ?></h3>
                    <span class="badge <?= $decision['status'] === 'accepted' ? 'badge-success' : 'badge-danger' ?>">
                        <?= $decision['status'] === 'accepted' ? 'Diterima' : 'Ditolak' ?>
                    </span>
                </div>
                <p class="decision-reason"><?= h($decision['decision_reason_display']) ?></p>
                <p class="decision-meta">
                    SAW: <strong><?= number_format((float) ($saw['score'] ?? 0), 2) ?>%</strong>
                    <?php if (!empty($saw['rank']) && !empty($saw['total_candidates'])): ?>
                    | Ranking #<?= (int) $saw['rank'] ?> dari <?= (int) $saw['total_candidates'] ?> kandidat
                    <?php endif; ?>
                </p>
                <p class="decision-breakdown">
                    Ringkas SAW: Skill <?= number_format((float) ($components['skill'] ?? 0), 1) ?>,
                    Pendidikan <?= number_format((float) ($components['education'] ?? 0), 1) ?>,
                    Pengalaman <?= number_format((float) ($components['experience'] ?? 0), 1) ?>,
                    Aktivitas CV <?= number_format((float) ($components['activity'] ?? 0), 1) ?>.
                </p>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

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
