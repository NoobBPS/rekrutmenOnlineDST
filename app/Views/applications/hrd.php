<div class="page-header between">
    <div>
        <h1>Daftar Pelamar</h1>
        <p>Pilih kandidat terbaik dan lanjutkan proses interview.</p>
    </div>
</div>

<div class="stats-grid compact">
    <div class="stat-card"><div class="stat-value"><?= (int) $stats['all'] ?></div><div class="stat-label">Total</div></div>
    <div class="stat-card"><div class="stat-value"><?= (int) $stats['pending'] ?></div><div class="stat-label">Lamaran Baru</div></div>
    <div class="stat-card"><div class="stat-value"><?= (int) $stats['screening'] ?></div><div class="stat-label">Screening</div></div>
    <div class="stat-card"><div class="stat-value"><?= (int) $stats['interview'] ?></div><div class="stat-label">Interview</div></div>
    <div class="stat-card stat-success"><div class="stat-value"><?= (int) $stats['accepted'] ?></div><div class="stat-label">Diterima</div></div>
    <div class="stat-card stat-danger"><div class="stat-value"><?= (int) $stats['rejected'] ?></div><div class="stat-label">Ditolak</div></div>
</div>

<form method="GET" action="<?= BASE_URL ?>applications/hrd" class="search-form">
    <div class="search-row">
        <select name="status">
            <option value="">Semua Status</option>
            <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Lamaran Baru</option>
            <option value="screening" <?= $filter_status === 'screening' ? 'selected' : '' ?>>Screening</option>
            <option value="interview" <?= $filter_status === 'interview' ? 'selected' : '' ?>>Interview</option>
            <option value="accepted" <?= $filter_status === 'accepted' ? 'selected' : '' ?>>Diterima</option>
            <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
        </select>
        <select name="job_id">
            <option value="">Semua Posisi</option>
            <?php foreach ($jobs as $job): ?>
            <option value="<?= (int) $job['id'] ?>" <?= (string) $filter_job === (string) $job['id'] ? 'selected' : '' ?>><?= h($job['title']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="skill" placeholder="Filter skill (contoh: Figma)" value="<?= h($filter_skill ?? '') ?>">
        <input type="text" name="education" placeholder="Filter pendidikan" value="<?= h($filter_education ?? '') ?>">
        <button class="btn btn-primary" type="submit">Filter</button>
    </div>
</form>

<?php if (!empty($saw_weights)): ?>
<div class="card recommendation-card">
    <h3>Konfigurasi SAW</h3>
    <p class="recommendation-meta">
        Bobot kriteria: Skill <?= (int) (($saw_weights['skill'] ?? 0) * 100) ?>%,
        Pendidikan <?= (int) (($saw_weights['education'] ?? 0) * 100) ?>%,
        Pengalaman <?= (int) (($saw_weights['experience'] ?? 0) * 100) ?>%,
        Aktivitas CV (magang/organisasi/proyek) <?= (int) (($saw_weights['activity'] ?? 0) * 100) ?>%.
    </p>
    <small>SAW formula uses: S = Skill, K = Knowledge (Education), E = Experience, A = Activity. Skor akhir juga dipenalti bila isi CV kosong/tidak relevan.</small>
</div>
<?php endif; ?>

<?php if (!empty($saw_recommendations)): ?>
<div class="recommendation-grid">
    <?php foreach ($saw_recommendations as $recommendation): ?>
    <div class="recommendation-card card">
        <h3>Rekomendasi SAW: <?= h($recommendation['job_title'] ?? '-') ?></h3>
        <?php if (!empty($recommendation['application_id'])): ?>
        <p><strong><?= h($recommendation['candidate_name']) ?></strong> direkomendasikan sebagai kandidat terbaik.</p>
        <p class="recommendation-meta">
            Skor SAW: <strong><?= number_format((float) $recommendation['saw_score'], 2) ?>%</strong>
            | Total kandidat: <?= (int) ($recommendation['total_candidates'] ?? 0) ?>
        </p>
        <?php if (!empty($recommendation['can_auto_apply'])): ?>
        <form action="<?= BASE_URL ?>applications/applyRecommendation" method="POST" class="recommendation-form">
            <?= csrfField() ?>
            <input type="hidden" name="job_id" value="<?= (int) ($recommendation['job_id'] ?? 0) ?>">
            <select name="target_status" required>
                <option value="accepted">Jadikan Diterima</option>
                <option value="interview">Naikkan ke Interview</option>
                <option value="screening">Naikkan ke Screening</option>
            </select>
            <button class="btn btn-primary btn-sm" type="submit">Terapkan Rekomendasi</button>
        </form>
        <small>HRD tetap dapat memilih kandidat secara manual dari tabel pelamar.</small>
        <?php elseif (!empty($recommendation['reason'])): ?>
        <small><?= h($recommendation['reason']) ?></small>
        <?php endif; ?>
        <?php else: ?>
        <p class="recommendation-meta">
            <?= h($recommendation['reason'] ?? 'Belum ada kandidat yang lolos validasi CV untuk rekomendasi otomatis.') ?>
        </p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (empty($applications)): ?>
<div class="empty-state card">
    <p>Belum ada pelamar dengan filter saat ini.</p>
</div>
<?php else: ?>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Kandidat</th>
                <th>Posisi</th>
                <th>Skill Match</th>
                <th>Skor SAW</th>
                <th>Status</th>
                <th>Tanggal Lamar</th>
                <th>Aksi Cepat</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($applications as $app): ?>
            <tr>
                <td>
                    <?php $candidateAvatarUrl = avatarUrl($app['candidate_avatar'] ?? null); ?>
                    <div class="candidate-identity">
                        <div class="candidate-avatar">
                            <?php if ($candidateAvatarUrl): ?>
                            <img src="<?= h($candidateAvatarUrl) ?>" class="candidate-avatar-image" alt="Foto profil <?= h($app['candidate_name']) ?>">
                            <?php else: ?>
                            <?= h(avatarInitial($app['candidate_name'] ?? '')) ?>
                            <?php endif; ?>
                        </div>
                        <div class="candidate-text">
                            <strong><?= h($app['candidate_name']) ?></strong><br>
                            <small><?= h($app['candidate_email']) ?></small><br>
                            <small><?= h($app['candidate_education'] ?? '-') ?></small>
                        </div>
                    </div>
                </td>
                <td><?= h($app['job_title']) ?></td>
                <td>
                    <strong><?= (int) $app['score'] ?>%</strong><br>
                    <?php if (!empty($app['saw_components'])): ?>
                    <small class="saw-breakdown">
                        S: <?= number_format((float) ($app['saw_components']['skill'] ?? 0), 1) ?> |
                        K: <?= number_format((float) ($app['saw_components']['education'] ?? 0), 1) ?> |
                        E: <?= number_format((float) ($app['saw_components']['experience'] ?? 0), 1) ?> |
                        A: <?= number_format((float) ($app['saw_components']['activity'] ?? 0), 1) ?>
                    </small>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="saw-score"><?= number_format((float) ($app['saw_score'] ?? 0), 2) ?>%</span><br>
                    <small>Rank #<?= (int) ($app['saw_rank'] ?? 0) ?> / <?= (int) ($app['saw_total_candidates'] ?? 0) ?></small><br>
                    <?php if (!empty($app['is_saw_recommended'])): ?>
                    <span class="badge badge-success mt-8">Rekomendasi SAW</span>
                    <?php endif; ?>
                    <?php if (!empty($app['saw_cv_disqualified'])): ?>
                    <br><span class="badge badge-warning mt-8">CV Perlu Revisi</span>
                    <br><small><?= h($app['saw_cv_summary'] ?? 'CV tidak memenuhi evaluasi otomatis') ?></small>
                    <?php elseif (!empty($app['saw_cv_summary'])): ?>
                    <br><small><?= h($app['saw_cv_summary']) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= statusLabel($app['status']) ?></td>
                <td><?= timeAgo($app['applied_at']) ?></td>
                <td class="action-row">
                    <a href="<?= BASE_URL ?>applications/detail/<?= (int) $app['id'] ?>" class="btn btn-sm">Detail</a>
                    <a href="<?= BASE_URL ?>chat/start/<?= (int) $app['user_id'] ?>" class="btn btn-sm btn-secondary">Chat</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
