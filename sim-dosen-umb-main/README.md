# SIM Dosen Universitas Muhammadiyah Bengkulu

Aplikasi Sistem Informasi Manajemen Dosen berbasis PHP dan MySQL, dilengkapi REST API.

## Teknologi
- PHP
- MySQL / MariaDB
- HTML, CSS, JavaScript, Bootstrap
- REST API berbasis JSON

## Fitur
- Login dan autentikasi pengguna
- Dashboard statistik dosen
- Pengelolaan data dosen (CRUD)
- Master data
- Pengelolaan pengguna dan role
- Laporan data dosen
- REST API data dosen

## Persyaratan
- XAMPP atau lingkungan PHP dan MySQL/MariaDB
- PHP yang mendukung password hashing
- Browser web

## Instalasi lokal menggunakan XAMPP
1. Salin folder proyek ke direktori `htdocs`.
2. Buat database bernama `sim_dosen` melalui phpMyAdmin.
3. Impor file `database_sim_dosen.sql` melalui phpMyAdmin.
4. Sesuaikan konfigurasi koneksi database di `koneksi.php`.
5. Jalankan Apache dan MySQL.
6. Buka `http://localhost/simdosen/` melalui browser.

## Catatan keamanan
- File SQL tidak menyediakan akun login bawaan.
- Buat kata sandi menggunakan `password_hash()` PHP dan verifikasi menggunakan `password_verify()`.
- Jangan mengunggah kata sandi asli, file konfigurasi rahasia, atau data dosen pribadi.
- Gunakan akun database dengan hak akses terbatas pada lingkungan produksi.

## API
Dokumentasi endpoint tersedia di `api_docs.php`.
