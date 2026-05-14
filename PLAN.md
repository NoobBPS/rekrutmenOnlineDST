# Plan: Integrasi CodeIgniter 4

## Objective
Mengintegrasikan CodeIgniter 4 ke project tanpa mengubah file utama (controllers, views, PHP logic).

---

## Pemahaman Struktur Saat Ini

### Controllers (6 file):
- `app/Controllers/Auth.php` - Login, Register, Logout
- `app/Controllers/Dashboard.php` - Dashboard user & HRD
- `app/Controllers/Jobs.php` - Job listings & application
- `app/Controllers/Applications.php` - Application management + SAW algorithm
- `app/Controllers/Profile.php` - Profile management
- `app/Controllers/Chat.php` - Messaging system

### Core:
- `app/core/Controller.php` - Base Controller
- `app/core/Model.php` - Base Model dengan PDO connection

### Views:
- `app/Views/` - Semua view files (layouts, auth, dashboard, jobs, applications, profile, chat)

### Entry Point:
- `index.php` - Custom routing sederhana

---

## Strategi Implementasi

### Approach: Hybrid Integration
Gabungkan CI4 sambil mempertahankan existing code structure.

### Langkah-langkah:

#### 1. Install CodeIgniter 4 via Composer
```bash
composer require codeigniter4/framework
```

#### 2. Konfigurasi CI4
- Copy `vendor/codeigniter4/framework/app/Config/` files ke `app/Config/`
- Setup `app/Config/App.php` (baseURL, timezone)
- Setup `app/Config/Database.php` - sesuaikan dengan existing DB config
- Setup `app/Config/Routes.php` - mapping ke existing controllers

#### 3. Modifikasi index.php
- Replace current index.php dengan CI4 entry point
- Konfigurasi PATH constants (APPPATH, BASEPATH, etc.)

#### 4. Adapter untuk Existing Controllers
- Buat CI4-compatible base controller yang extends CI4\Controller
- Existing controllers tetap berfungsi dengan minchanges

#### 5. Keep Views As-Is
- Configure CI4 View paths ke `app/Views/`
- Tidak perlu perubahan di view files

#### 6. Leverage CI4 Features
- Database: Gunakan CI4 Database invece of custom PDO
- Session: Gunakan CI4 Sessions
- Validation: CI4 Form Validation

---

## File yang Perlu Dibuat/Modifikasi

| File | Action |
|------|--------|
| `composer.json` | Create - Add CI4 dependency |
| `index.php` | Replace - CI4 entry point |
| `app/Config/App.php` | Create - CI4 config |
| `app/Config/Database.php` | Modify - CI4 DB config |
| `app/Config/Routes.php` | Create - Route mapping |
| `app/Config/Controller.php` | Create - Base controller |
| `app/Config/Model.php` | Create - Base model |
| `app/Config/Services.php` | Create - Service container |
| `app/Config/View.php` | Create - View config |
| `app/Config/Filters.php` | Create - CSRF, auth filters |
| `app/Controllers/BaseController.php` | Create - CI4 base |
| `app/core/Controller.php` | Modify - Extend CI4 |
| `app/core/Model.php` | Modify - Extend CI4 Model |

---

## Verifikasi

1. Run `composer install`
2. Akses halaman login: `http://localhost/dst-recruitment/`
3. Test login functionality
4. Test dashboard access
5. Test job listing dan application
6. Verify database connection works

---

## Catatan Penting

- **TIDAK** mengubah logic di controllers
- **TIDAK** mengubah view files
- Hanya menggantikan "infrastructure" (routing, DB, session) ke CI4
- Existing Model.php & Controller.php akan di-extend ke CI4 classes