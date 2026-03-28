# TemuBalik — Lost & Found Web App

TemuBalik adalah aplikasi web Lost & Found berbasis PHP + MySQL untuk mengelola proses pelaporan kehilangan, input barang temuan, pencocokan, hingga verifikasi/penyerahan barang.

## Fitur Utama

### 1) Sisi Publik

- Landing page dan daftar laporan.
- Detail laporan (temuan/kehilangan).
- Data yang sudah selesai diserahkan tidak ditampilkan di halaman publik.

### 2) Sisi Pelapor

- Buat, lihat, edit, hapus laporan kehilangan.
- Lihat status tracking laporan.
- Lihat data kecocokan.
- Dashboard ringkas sesuai data akun pelapor.

### 3) Sisi Petugas

- CRUD barang temuan.
- Kelola laporan masuk.
- CRUD pencocokan barang ↔ laporan.
- CRUD verifikasi/penyerahan.
- Dashboard ringkas operasional.

## Teknologi yang Digunakan

- **Backend:** PHP (mysqli, server-rendered)
- **Database:** MySQL / MariaDB
- **Frontend:** HTML, CSS, JavaScript
- **UI Library:** Bootstrap 5.3
- **Icon:** Lucide
- **Font:** Plus Jakarta Sans

## Struktur Folder (ringkas)

- `index.php` → halaman utama publik
- `laporan.php`, `laporan_detail.php` → daftar & detail laporan publik
- `auth/` → login, logout, registrasi
- `dashboard/` → dashboard petugas/pelapor
- `barang_temuan/` → modul barang temuan
- `laporan_kehilangan/` → modul laporan kehilangan + status
- `matching/` → modul pencocokan
- `verifikasi/` → modul verifikasi/penyerahan
- `config/` → koneksi DB, auth helper, template/helper
- `uploads/` → file gambar upload
- `database.sql` → skema + seed data contoh

## Cara Setup & Menjalankan

## Prasyarat

- PHP 8.x
- MySQL/MariaDB aktif
- Web server lokal (MAMP/XAMPP/Laragon/Apache bawaan)

### Opsi A — Auto bootstrap (disarankan)

Aplikasi sudah punya auto-initialize schema di `config/database.php`.

1. Salin project ke web root (contoh MAMP):
   - `/Applications/MAMP/htdocs/web-lostfound` (macOS default)
2. Pastikan MySQL berjalan.
3. Buka aplikasi di browser:
   - `http://localhost:8888/web-lostfound/` (MAMP default)
4. Saat pertama kali dibuka, database/tabel akan dibuat otomatis jika belum ada.

### Opsi B — Import manual

1. Buat database `lost_found`.
2. Import file `database.sql`.
3. Jalankan aplikasi lewat URL lokal.

## Konfigurasi Database

Koneksi menggunakan `config/database.php` dengan default:

- `DB_HOST=localhost`
- `DB_USER=root`
- `DB_NAME=lost_found`
- `DB_PASS` dicoba dari environment, lalu fallback ke `""` atau `"root"`.

Bisa override via environment variable:

- `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`

## Akun Default

Berdasarkan seed bawaan:

- **Petugas**: `petugas@gmail.com` / `petugas123`
- **Pelapor**: `pelapor@gmail.com` / `pelapor123`

## Alur Singkat Sistem

1. Pelapor membuat laporan kehilangan.
2. Petugas mencatat barang temuan.
3. Petugas membuat pencocokan.
4. Petugas memproses verifikasi/penyerahan.
5. Status data diperbarui (matching, barang, laporan).
6. Data selesai diserahkan disembunyikan dari halaman publik.

## Catatan Penggunaan

- Pastikan folder `uploads/` bisa ditulis (writable) untuk upload foto.
- Jika terjadi error koneksi DB, cek kredensial MySQL atau environment variable.
- Untuk validasi cepat syntax file PHP:
  - `php -l path/to/file.php`

## Lisensi

Digunakan untuk kebutuhan pengembangan/pembelajaran internal proyek TemuBalik.
