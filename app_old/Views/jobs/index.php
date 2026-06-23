<div class="page-header">
    <h1>Lowongan Pekerjaan</h1>
    <p>Temukan karier impian Anda di PT Digdaya Solusi Teknologi.</p>
</div>

<form class="search-form" method="GET" action="<?= BASE_URL ?>jobs">
    <div class="search-row">
        <input type="text" name="q" placeholder="Cari posisi, skill, atau kata kunci..." value="<?= h($search ?? '') ?>">
        <select name="location">
            <option value="">Semua Lokasi</option>
            <?php foreach ($locations as $loc): ?>
            <option value="<?= h($loc['location']) ?>" <?= ($selected_location ?? '') === $loc['location'] ? 'selected' : '' ?>><?= h($loc['location']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Cari</button>
    </div>
</form>

<?php if (empty($jobs)): ?>
<div class="empty-state card">
    <p>Tidak ada lowongan yang ditemukan.</p>
</div>
<?php else: ?>
<div class="jobs-list">
    <?php foreach ($jobs as $job): ?>
    <div class="job-item">
        <div class="job-content">
            <h3><a href="<?= BASE_URL ?>jobs/detail/<?= (int) $job['id'] ?>"><?= h($job['title']) ?></a></h3>
            <p class="job-meta">
                <span><?= h($job['location']) ?></span>
                <span><?= h($job['type']) ?></span>
                <?php if (!empty($job['department'])): ?><span><?= h($job['department']) ?></span><?php endif; ?>
            </p>
            <p class="job-description"><?= h(substr($job['description'], 0, 170)) ?>...</p>
            <div class="job-footer">
                <span class="applicant-count"><?= (int) $job['applicant_count'] ?> pelamar</span>
                <span class="job-date">Diposting <?= timeAgo($job['created_at']) ?></span>
            </div>
        </div>
        <div class="job-action">
            <a href="<?= BASE_URL ?>jobs/detail/<?= (int) $job['id'] ?>" class="btn btn-primary">Apply / Lamar</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
