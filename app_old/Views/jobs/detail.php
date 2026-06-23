<div class="job-detail">
    <div class="job-header">
        <h1><?= h($job['title']) ?></h1>
        <div class="job-meta">
            <span><?= h($job['location']) ?></span>
            <span><?= h($job['type']) ?></span>
            <?php if (!empty($job['department'])): ?><span><?= h($job['department']) ?></span><?php endif; ?>
        </div>
        <?php if (!empty($job['salary_min']) || !empty($job['salary_max'])): ?>
        <div class="job-salary">
            <?= !empty($job['salary_min']) ? 'Rp ' . number_format((int) $job['salary_min']) : '' ?>
            <?= !empty($job['salary_max']) ? ' - Rp ' . number_format((int) $job['salary_max']) : '' ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!hasRole('hrd') && !hasRole('admin')): ?>
    <div class="apply-section">
        <?php if (!empty($applied)): ?>
        <div class="alert alert-<?= $applied['status'] === 'accepted' ? 'success' : ($applied['status'] === 'rejected' ? 'error' : 'info') ?>">
            Status lamaran Anda: <strong><?= ucfirst(h($applied['status'])) ?></strong>
        </div>
        <?php else: ?>
        <a href="<?= BASE_URL ?>jobs/apply/<?= (int) $job['id'] ?>" class="btn btn-primary btn-lg">Apply / Lamar Posisi Ini</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="job-section">
        <h2>Deskripsi Pekerjaan</h2>
        <p><?= nl2br(h($job['description'])) ?></p>
    </div>

    <?php if (!empty($job['requirements'])): ?>
    <div class="job-section">
        <h2>Kualifikasi</h2>
        <p><?= nl2br(h($job['requirements'])) ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($job['skills_array'])): ?>
    <div class="job-section">
        <h2>Skill yang Dibutuhkan</h2>
        <div class="skills-list">
            <?php foreach ($job['skills_array'] as $skill): ?>
            <span class="skill-tag"><?= h(trim($skill)) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="job-info">
        <p>Diposting oleh <strong><?= h($job['created_by_name']) ?></strong> pada <?= formatDate($job['created_at']) ?></p>
    </div>
</div>