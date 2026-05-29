# DST Recruitment Web App

Aplikasi rekrutmen calon pegawai untuk **PT Digdaya Solusi Teknologi (DST)**.

## Fitur Utama

- Registrasi dan login kandidat
- Role akun: `user`, `hrd`, `admin`
- Kandidat melihat lowongan dan apply pekerjaan
- CV **wajib upload**, cover letter opsional
- Validasi CV anti-kosong (dokumen terlalu minim ditolak saat apply)
- Tracking status: `pending`, `screening`, `interview`, `accepted`, `rejected`
- Untuk status final (`accepted/rejected`), kandidat melihat alasan keputusan + ringkasan SAW
- Penilaian SAW mempertimbangkan profil + relevansi isi CV, dengan penalti otomatis jika CV kosong/tidak relevan
- Status lamaran di chat ditampilkan di bawah nama HRD (gaya status percakapan)
- Dashboard HRD untuk monitoring pipeline
- Admin mengelola lowongan (tambah, edit, hapus, buka/tutup)
- HRD review pelamar + filter skill/pendidikan + approve/reject lamaran
- Chat kandidat-HRD, dengan aturan: **chat pertama hanya bisa dimulai HRD/Admin**
- Realtime chat saat ini menggunakan AJAX polling (bukan WebSocket)
- Komponen CodeIgniter tersedia di folder `codeigniter4/` (CI `4.7.3-dev`) dan dependency `codeigniter4/framework` di `composer.json`

## Keamanan yang Sudah Diterapkan

- Password hashing (`password_hash`)
- Prepared statement di query database
- CSRF token di semua form POST
- Validasi dan sanitasi input
- Validasi MIME/ekstensi file upload
- Pembatasan upload size dan type
- Session cookie `httponly` + `samesite`
- Security headers dasar
- Blokir eksekusi script di folder upload (`uploads/.htaccess`)

## Setup Lokal (XAMPP)

1. Letakkan project di `C:\xampp\htdocs\dst-recruitment`
2. Buat database `dst_recruitment`
3. Import file `database.sql`
4. Pastikan Apache + MySQL aktif
5. Buka:
   - `http://localhost/dst-recruitment/`

## Akun Default

- HRD: `hrd@dst.co.id`
- Admin: `admin@dst.co.id`
- Password: `password123`
- Admin tambahan: `admin.rekrutmen@dst.co.id`
- Password admin tambahan: `AdminDst2026!`

## Catatan Konfigurasi

- Gunakan `.env.example` sebagai template `.env`
- Query path menggunakan rewrite `.htaccess`

## Akun

1. Admin
- id: admin.rekrutmen@dst.co.id
- ps: AdminDst2026!

2. HRD
- id: hrd@dst.co.id
- ps: password123
