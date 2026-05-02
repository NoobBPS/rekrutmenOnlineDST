<div class="auth-container">
    <div class="auth-card">
        <h2>Lamar <?= h($job['title']) ?></h2>
        <form action="<?= BASE_URL ?>jobs/doApply" method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">

            <div class="form-group">
                <label>Nama</label>
                <input type="text" value="<?= h($user['full_name'] ?? '') ?>" disabled>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="text" value="<?= h($user['email'] ?? '') ?>" disabled>
            </div>
            <div class="form-group">
                <label>CV (PDF/Word) <span class="required">*</span></label>
                <input type="file" name="cv_file" accept=".pdf,.doc,.docx" required>
                <small>Wajib diisi, ukuran maksimal 5MB.</small>
            </div>
            <div class="form-group">
                <label>Cover Letter (Opsional)</label>
                <textarea name="cover_letter" rows="5" placeholder="Tuliskan motivasi Anda..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Kirim Lamaran</button>
            <a href="<?= BASE_URL ?>jobs/detail/<?= (int) $job['id'] ?>" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>