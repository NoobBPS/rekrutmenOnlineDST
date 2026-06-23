<!DOCTYPE html>
<html>
<head>
    <title>Kirim Email Test</title>
    <style>
        body {font-family:Arial, sans-serif; max-width:600px; margin:auto; padding:20px;}
        input, textarea {width:100%; padding:8px; margin:6px 0;}
        button {background:#0f5e5e; color:#fff; border:none; padding:10px 20px; cursor:pointer;}
    </style>
</head>
<body>
    <h2>Kirim Email Test</h2>
    <form method="post" action="<?= base_url('kirim_email/send') ?>">
        <label>Email Tujuan:</label>
        <input type="email" name="email" required />
        <label>Subject:</label>
        <input type="text" name="subject" />
        <label>Pesan:</label>
        <textarea name="message" rows="5"></textarea>
        <button type="submit">Kirim</button>
    </form>
    <?php if (isset($_SESSION['flash'])): ?>
        <div style="margin-top:15px; color:<?= $_SESSION['flash']['type'] === 'success' ? 'green' : 'red' ?>;">
            <?= $_SESSION['flash']['message'] ?>
        </div>
    <?php endif; ?>
</body>
</html>
