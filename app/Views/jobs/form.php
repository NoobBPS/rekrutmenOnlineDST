<div class="auth-container wide">
    <div class="auth-card">
        <h2><?= $job ? 'Edit' : 'Tambah' ?> Lowongan</h2>
        <form action="<?= BASE_URL ?>jobs/save" method="POST">
            <?= csrfField() ?>
            <?php if ($job): ?>
            <input type="hidden" name="id" value="<?= (int) $job['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Posisi <span class="required">*</span></label>
                <input type="text" name="title" required value="<?= h($job['title'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Departemen</label>
                <input type="text" name="department" value="<?= h($job['department'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Lokasi <span class="required">*</span></label>
                <input type="text" name="location" required value="<?= h($job['location'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Tipe</label>
                <select name="type">
                    <option value="Full-time" <?= ($job['type'] ?? '') === 'Full-time' ? 'selected' : '' ?>>Full-time</option>
                    <option value="Part-time" <?= ($job['type'] ?? '') === 'Part-time' ? 'selected' : '' ?>>Part-time</option>
                    <option value="Contract" <?= ($job['type'] ?? '') === 'Contract' ? 'selected' : '' ?>>Contract</option>
                    <option value="Internship" <?= ($job['type'] ?? '') === 'Internship' ? 'selected' : '' ?>>Internship</option>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Gaji Min</label>
                    <input type="number" name="salary_min" min="0" value="<?= h($job['salary_min'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Gaji Max</label>
                    <input type="number" name="salary_max" min="0" value="<?= h($job['salary_max'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Deskripsi <span class="required">*</span></label>
                <textarea name="description" rows="5" required><?= h($job['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label>Requirements</label>
                <textarea name="requirements" rows="3"><?= h($job['requirements'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label>Skills (pisahkan dengan koma)</label>
                <input type="text" name="skills" value="<?= h($job['skills'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Simpan</button>
            <a href="<?= BASE_URL ?>jobs/manage" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>