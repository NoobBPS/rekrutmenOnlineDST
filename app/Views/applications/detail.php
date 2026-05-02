<div class="application-detail">
    <div class="detail-header">
        <h1>Detail Lamaran</h1>
        <a href="<?= BASE_URL ?><?= hasRole('hrd') || hasRole('admin') ? 'applications/hrd' : 'applications' ?>" class="btn btn-secondary">Kembali</a>
    </div>

    <div class="detail-grid">
        <div class="detail-card">
            <h3>Informasi Pelamar</h3>
            <p><strong><?= h($candidate['full_name']) ?></strong></p>
            <p><?= h($candidate['email']) ?></p>
            <p><?= h($candidate['phone'] ?? '-') ?></p>
            <p>Pendidikan: <?= h($candidate['education'] ?? '-') ?></p>
            <p>Pengalaman: <?= (int) ($candidate['experience_years'] ?? 0) ?> tahun</p>
        </div>

        <div class="detail-card">
            <h3>Posisi</h3>
            <p><strong><?= h($application['job_title']) ?></strong></p>
            <p><?= h($application['location']) ?></p>
            <p><?= h($application['type']) ?></p>
        </div>

        <div class="detail-card">
            <h3>Status Lamaran</h3>
            <p class="status-current"><?= statusLabel($application['status']) ?></p>
            <p>Tanggal Lamar: <?= formatDate($application['applied_at']) ?></p>
            <p>Skill Match: <?= (int) $application['score'] ?>%</p>
            <?php if (!empty($application_saw)): ?>
            <p>Skor SAW: <strong><?= number_format((float) ($application_saw['saw_score'] ?? 0), 2) ?>%</strong></p>
            <p>Ranking SAW: #<?= (int) ($application_saw['saw_rank'] ?? 0) ?> dari <?= (int) ($application_saw['saw_total_candidates'] ?? 0) ?> kandidat</p>
            <?php if (!empty($application_saw['is_saw_recommended'])): ?>
            <span class="badge badge-success">Kandidat Rekomendasi SAW</span>
            <?php elseif (!empty($job_saw_recommendation['candidate_name'])): ?>
            <small>Rekomendasi SAW saat ini: <?= h($job_saw_recommendation['candidate_name']) ?></small>
            <?php endif; ?>
            <?php endif; ?>
            <?php if (hasRole('hrd') || hasRole('admin')): ?>
            <a href="<?= BASE_URL ?>chat/start/<?= (int) $application['user_id'] ?>" class="btn btn-sm btn-primary mt-8">Mulai Chat Kandidat</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($candidate['skills'])): ?>
    <div class="detail-section">
        <h3>Skills</h3>
        <div class="skills-list">
            <?php foreach (explode(',', $candidate['skills']) as $skill): ?>
            <span class="skill-tag"><?= h(trim($skill)) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($application['cover_letter'])): ?>
    <div class="detail-section">
        <h3>Cover Letter</h3>
        <p><?= nl2br(h($application['cover_letter'])) ?></p>
    </div>
    <?php endif; ?>

    <?php if (in_array($application['status'], ['accepted', 'rejected'], true)): ?>
    <div class="detail-section decision-box">
        <h3>Alasan Keputusan HRD</h3>
        <p><?= h($decision_reason_display ?? '-') ?></p>
        <?php if (!empty($decision_saw_display)): ?>
        <?php $decisionSaw = $decision_saw_display; $decisionComponents = $decisionSaw['components'] ?? []; ?>
        <p class="decision-meta">
            SAW: <strong><?= number_format((float) ($decisionSaw['score'] ?? 0), 2) ?>%</strong>
            <?php if (!empty($decisionSaw['rank']) && !empty($decisionSaw['total_candidates'])): ?>
            | Ranking #<?= (int) $decisionSaw['rank'] ?> dari <?= (int) $decisionSaw['total_candidates'] ?> kandidat
            <?php endif; ?>
        </p>
        <p class="decision-breakdown">
            Ringkasan SAW sederhana: Skill <?= number_format((float) ($decisionComponents['skill'] ?? 0), 1) ?>,
            Pendidikan <?= number_format((float) ($decisionComponents['education'] ?? 0), 1) ?>,
            Pengalaman <?= number_format((float) ($decisionComponents['experience'] ?? 0), 1) ?>,
            Aktivitas CV <?= number_format((float) ($decisionComponents['activity'] ?? 0), 1) ?>.
        </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($application['cv_file'])): ?>
    <div class="detail-section">
        <h3>CV</h3>
        <a href="<?= BASE_URL ?>applications/downloadCv/<?= (int) $application['id'] ?>" class="btn" target="_blank" rel="noopener noreferrer">Lihat / Download CV</a>
    </div>
    <?php endif; ?>

    <?php if (hasRole('hrd') || hasRole('admin')): ?>
    <div class="detail-section">
        <h3>Update Status</h3>
        <form action="<?= BASE_URL ?>applications/updateStatus" method="POST" class="status-form">
            <?= csrfField() ?>
            <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>">
            <select name="status" required>
                <option value="">Pilih Status</option>
                <option value="pending" <?= $application['status'] === 'pending' ? 'selected' : '' ?>>Baru</option>
                <option value="screening" <?= $application['status'] === 'screening' ? 'selected' : '' ?>>Screening</option>
                <option value="interview" <?= $application['status'] === 'interview' ? 'selected' : '' ?>>Interview</option>
                <option value="accepted" <?= $application['status'] === 'accepted' ? 'selected' : '' ?>>Diterima</option>
                <option value="rejected" <?= $application['status'] === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
            </select>
            <input type="text" name="decision_reason" placeholder="Alasan keputusan (wajib jika diterima/ditolak)" value="<?= h($application['decision_reason'] ?? '') ?>">
            <input type="text" name="notes" placeholder="Catatan status (opsional)">
            <button type="submit" class="btn btn-primary">Update</button>
        </form>
        <small>Jika memilih status diterima/ditolak, alasan wajib diisi agar kandidat melihat penjelasannya.</small>
    </div>

    <div class="detail-section">
        <h3>Catatan HRD</h3>
        <form action="<?= BASE_URL ?>applications/saveNotes" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>">
            <textarea name="notes" rows="5" placeholder="Tambahkan catatan..."><?= h($application['notes'] ?? '') ?></textarea>
            <button type="submit" class="btn btn-primary">Simpan Catatan</button>
        </form>
        <?php if (!empty($application['notes'])): ?>
        <div class="notes-history">
            <h4>Riwayat Catatan</h4>
            <pre><?= h($application['notes']) ?></pre>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
