<div class="page-header between">
    <div>
        <h1>Kelola Lowongan</h1>
        <p>Atur lowongan aktif untuk kandidat DST.</p>
    </div>
    <a href="<?= BASE_URL ?>jobs/form" class="btn btn-primary">Tambah Lowongan</a>
</div>

<?php if (empty($jobs)): ?>
<div class="empty-state card">
    <p>Belum ada lowongan yang dibuat.</p>
</div>
<?php else: ?>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Posisi</th>
                <th>Lokasi</th>
                <th>Tipe</th>
                <th>Pelamar</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($jobs as $job): ?>
            <tr>
                <td><strong><?= h($job['title']) ?></strong></td>
                <td><?= h($job['location']) ?></td>
                <td><?= h($job['type']) ?></td>
                <td><?= (int) $job['applicant_count'] ?></td>
                <td>
                    <span class="badge badge-<?= $job['status'] === 'open' ? 'success' : 'secondary' ?>">
                        <?= $job['status'] === 'open' ? 'Aktif' : 'Tutup' ?>
                    </span>
                </td>
                <td class="action-row">
                    <a href="<?= BASE_URL ?>jobs/form/<?= (int) $job['id'] ?>" class="btn btn-sm">Edit</a>

                    <form action="<?= BASE_URL ?>jobs/toggleStatus/<?= (int) $job['id'] ?>" method="POST" class="inline-form">
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn-sm btn-secondary">
                            <?= $job['status'] === 'open' ? 'Tutup' : 'Buka' ?>
                        </button>
                    </form>

                    <form action="<?= BASE_URL ?>jobs/delete/<?= (int) $job['id'] ?>" method="POST" class="inline-form" onsubmit="return confirm('Yakin ingin menghapus lowongan ini?')">
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>