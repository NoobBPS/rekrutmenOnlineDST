<div class="page-header">
    <h1>Dashboard HRD</h1>
    <p>Pantau pipeline rekrutmen PT Digdaya Solusi Teknologi.</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= (int) $stats['total_jobs'] ?></div>
        <div class="stat-label">Lowongan Aktif</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= (int) $stats['total_applications'] ?></div>
        <div class="stat-label">Total Lamaran</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= (int) $stats['pending'] ?></div>
        <div class="stat-label">Lamaran Baru</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= (int) $stats['interview'] ?></div>
        <div class="stat-label">Interview</div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-value"><?= (int) $stats['accepted'] ?></div>
        <div class="stat-label">Diterima</div>
    </div>
    <div class="stat-card stat-danger">
        <div class="stat-value"><?= (int) $stats['rejected'] ?></div>
        <div class="stat-label">Ditolak</div>
    </div>
</div>

<section class="dashboard-section">
    <h2>Pipeline Rekrutmen</h2>
    <div class="pipeline">
        <?php foreach ($pipeline as $stage): ?>
        <div class="pipeline-stage">
            <div class="stage-count"><?= (int) $stage['count'] ?></div>
            <div class="stage-label"><?= h($stage['label']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="dashboard-section">
    <h2>Lamaran Terbaru</h2>
    <?php if (empty($recent_applications)): ?>
    <div class="empty-state card">
        <p>Belum ada lamaran masuk.</p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Kandidat</th>
                    <th>Posisi</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_applications as $app): ?>
                <tr>
                    <td>
                        <strong><?= h($app['candidate_name']) ?></strong><br>
                        <small><?= h($app['candidate_email']) ?></small>
                    </td>
                    <td><?= h($app['job_title']) ?></td>
                    <td><?= statusLabel($app['status']) ?></td>
                    <td><?= timeAgo($app['applied_at']) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>applications/detail/<?= (int) $app['id'] ?>" class="btn btn-sm">Lihat</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>