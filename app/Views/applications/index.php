<div class="page-header">
    <h1>Lamaran Saya</h1>
    <p>Pantau proses rekrutmen Anda secara real-time.</p>
</div>

<?php if (empty($applications)): ?>
<div class="empty-state card">
    <p>Anda belum memiliki lamaran. Yuk cari posisi yang cocok.</p>
    <a href="<?= BASE_URL ?>jobs" class="btn btn-primary">Lihat Lowongan</a>
</div>
<?php else: ?>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Posisi</th>
                <th>Lokasi</th>
                <th>Status</th>
                <th>Tanggal Lamar</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($applications as $app): ?>
            <tr>
                <td>
                    <strong><?= h($app['job_title']) ?></strong>
                    <p class="job-type"><?= h($app['type']) ?></p>
                </td>
                <td><?= h($app['location']) ?></td>
                <td><?= statusLabel($app['status']) ?></td>
                <td><?= timeAgo($app['applied_at']) ?></td>
                <td>
                    <a href="<?= BASE_URL ?>applications/detail/<?= (int) $app['id'] ?>" class="btn btn-sm">Lihat Detail</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>