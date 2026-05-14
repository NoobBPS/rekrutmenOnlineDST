# DST Recruitment Web App

Aplikasi rekrutmen calon pegawai untuk **PT Digdaya Solusi Teknologi (DST)**.

## Fitur Utama

- Registrasi dan login kandidat
- Role akun: `user`, `hrd`, `admin`
- Kandidat melihat lowongan dan apply pekerjaan
- CV **wajib upload**, cover letter opsional
- Tracking status: `pending`, `screening`, `interview`, `accepted`, `rejected`
- Untuk status final (`accepted/rejected`), kandidat melihat alasan keputusan + ringkasan SAW
- Dashboard kandidat menampilkan pesan diterima/ditolak
- Dashboard HRD untuk monitoring pipeline
- Admin mengelola lowongan (tambah, edit, hapus, buka/tutup)
- HRD review pelamar + filter skill/pendidikan + approve/reject lamaran
- Chat kandidat-HRD, dengan aturan: **chat pertama hanya bisa dimulai HRD/Admin**

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

## Catatan Konfigurasi

- Gunakan `.env.example` sebagai template `.env`
- Query path menggunakan rewrite `.htaccess`
