# WYLCORE - Construction Management Platform

WYLCORE adalah aplikasi web berbasis HTML, PHP, dan MySQL untuk mendukung manajemen perubahan proyek konstruksi. Sistem ini memadukan dashboard proyek, Change Request, WBS, risiko, knowledge base, approval Project Manager, serta integrasi BIM/Autodesk Platform Services.

## Fitur Utama

- Landing page dan halaman login.
- Workspace berdasarkan role: Admin, Project Manager, dan Site Engineer.
- Pengajuan dan pembaruan Change Request.
- Approval Change Request oleh Project Manager/Admin.
- Dashboard statistik proyek, status perubahan, risiko, dan S-Curve.
- Knowledge Base dan lesson learned otomatis.
- Manajemen user dan assignment proyek.
- Viewer BIM representatif dengan Three.js dan integrasi Autodesk APS.

## Struktur Project

```text
.
|-- assets/                         # Gambar, ikon, dan video publik
|   |-- jembatan.jpg
|   |-- parkirmotor.jpg
|   |-- Video Home Page.mp4
|   `-- wylcore.png
|-- backend/                        # Endpoint dan helper PHP server-side
|   |-- api_*.php
|   |-- db_user.php
|   |-- env.php
|   |-- get_*.php
|   |-- save_*.php
|   `-- update_data.php
|-- admin.html                      # Dashboard Admin
|-- index.html                      # Landing page
|-- login.html                      # Login pengguna
|-- project-manager.html            # Workspace Project Manager
|-- site-engineer.html              # Workspace Site Engineer
|-- bim_wbs_change_risk_km_prototype.html
|-- contohdashboard.html
|-- .htaccess                       # Rewrite URL dan proteksi server
`-- .gitignore
```

## Halaman Utama

- `/` atau `/index` - Landing page.
- `/login` - Login sistem.
- `/admin` - Dashboard Admin.
- `/project-manager` - Workspace Project Manager.
- `/site-engineer` - Form dan workspace Site Engineer.

Ekstensi `.html` disembunyikan melalui aturan rewrite di `.htaccess`, sehingga URL seperti `/login.html` akan diarahkan ke `/login`.

## Backend

Semua file PHP sudah dikumpulkan di folder `backend/`. Halaman HTML memanggil endpoint dengan path `backend/...`, misalnya:

- `backend/api_login.php`
- `backend/api_get_projects.php`
- `backend/api_get_change_requests.php`
- `backend/api_get_dashboard_stats.php`
- `backend/save_data.php`
- `backend/update_data.php`
- `backend/save_approval.php`

File helper sensitif seperti `db_user.php`, `env.php`, `knowledge_base_auto.php`, `setup_users.php`, dan `test_session.php` diblokir dari akses publik lewat `.htaccess`.

## Assets

Asset publik ditempatkan di folder `assets/`. Saat ini yang digunakan langsung oleh HTML:

- `assets/wylcore.png` untuk favicon.
- `assets/Video Home Page.mp4` untuk video di landing page.

File `jembatan.jpg` dan `parkirmotor.jpg` tersimpan sebagai asset publik, tetapi saat ini belum direferensikan langsung oleh HTML.

## Konfigurasi Environment

File `.env` tidak ikut masuk git. Backend akan mencoba membaca konfigurasi dari:

1. `.env` di root project.
2. `backend/.env` sebagai fallback.

Contoh variabel:

```env
APP_ENV=local
DB_HOST=localhost
DB_PORT=3306
DB_NAME=db_data_proyek
DB_USER=root
DB_PASS=
FORGE_CLIENT_ID=your_aps_client_id
FORGE_CLIENT_SECRET=your_aps_client_secret
```

`APP_ENV=local` atau akses melalui `localhost` akan memakai konfigurasi lokal. Untuk hosting, gunakan environment variable server atau `.env` agar kredensial tidak perlu ditulis di dokumentasi maupun commit baru.

## Kebutuhan Server

- Web server Apache dengan `mod_rewrite` aktif.
- PHP dengan PDO MySQL.
- Database MySQL/MariaDB.
- Browser modern untuk menjalankan halaman HTML, Chart.js, Three.js, dan viewer APS.

## Menjalankan Lokal

1. Siapkan database MySQL lokal.
2. Buat file `.env` di root project.
3. Pastikan document root Apache mengarah ke folder project ini.
4. Aktifkan `mod_rewrite` dan izinkan `.htaccess`.
5. Buka `http://localhost/nama-folder/` atau virtual host lokal yang digunakan.

## Catatan Keamanan

- `.env`, `backend/.env`, folder upload runtime, file log, dan file temporary diabaikan oleh `.gitignore`.
- Directory listing dimatikan dengan `Options -Indexes`.
- File backend helper diblokir dari akses publik.
- Endpoint API tetap dapat diakses oleh halaman HTML karena aplikasi membutuhkan koneksi tersebut untuk login, dashboard, CRUD, approval, dan integrasi BIM.

## Catatan Pengembangan

- Saat menambah endpoint PHP baru, simpan di `backend/`.
- Saat menambah asset publik baru, simpan di `assets/`.
- Saat menambah halaman HTML baru, gunakan URL extensionless yang kompatibel dengan `.htaccess`.
- Jangan commit kredensial, file `.env`, upload runtime, atau log.
