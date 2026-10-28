<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

require_login();
$role = (string)($_SESSION['role'] ?? '');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dokumentasi REST API | SIM Dosen UMB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --umb-blue: #1458b8;
            --umb-blue-dark: #0b2d59;
            --sidebar-bg: #0b2d59;
            --text-main: #182538;
            --text-muted: #65748a;
        }
        body { font-family: Inter, system-ui, -apple-system, sans-serif; color: var(--text-main); background: #f7faff; overflow-x: hidden; }
        .top-navbar { height: 70px; background: #fff; border-bottom: 1px solid #e9eef5; position: fixed; top: 0; right: 0; left: 0; z-index: 1030; }
        .brand-mark { display: inline-grid; place-items: center; width: 38px; height: 38px; border-radius: 10px; color: white; background: var(--umb-blue); font-size: 1.2rem; }
        .sidebar { width: 260px; position: fixed; top: 70px; bottom: 0; left: 0; z-index: 1025; background: var(--sidebar-bg); color: rgba(255,255,255,.8); overflow-y: auto; padding: 20px 15px; border-right: 1px solid rgba(255,255,255,.1); }
        .sidebar .menu-category { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; color: rgba(255,255,255,.5); margin: 18px 0 8px 10px; font-weight: 700; }
        .sidebar .nav-link { color: rgba(255,255,255,.8); padding: 10px 12px; border-radius: 8px; font-weight: 500; font-size: 0.92rem; display: flex; align-items: center; gap: 10px; margin-bottom: 4px; text-decoration: none; transition: .2s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,0.1); }
        .main-content { margin-left: 260px; margin-top: 70px; padding: 30px; }
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }
        .card { border: 1px solid #e7edf5; border-radius: 16px; box-shadow: 0 10px 30px rgba(26,58,100,.04); }
        .badge-get { background-color: #198754; color: white; font-weight: 600; }
        .badge-post { background-color: #0d6efd; color: white; font-weight: 600; }
        .badge-put { background-color: #ffc107; color: #000; font-weight: 600; }
        .badge-patch { background-color: #0dcaf0; color: #000; font-weight: 600; }
        .badge-delete { background-color: #dc3545; color: white; font-weight: 600; }
        pre { background: #0b2d59; color: #e9eef5; padding: 15px; border-radius: 10px; font-size: 0.85rem; }
    </style>
</head>
<body>

<!-- Top Navbar -->
<nav class="top-navbar navbar navbar-expand px-4">
    <div class="container-fluid justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-secondary d-lg-none btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMobile">
                <i class="bi bi-list"></i>
            </button>
            <a class="navbar-brand d-flex align-items-center gap-2 m-0 text-decoration-none" href="dashboard.php">
                <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
                <div>
                    <div style="font-weight: 800; font-size: 1rem; color: var(--umb-blue-dark); line-height: 1.1;">SIM DOSEN</div>
                    <div style="font-size: 0.68rem; color: var(--text-muted); font-weight: 500;">Universitas Muhammadiyah Bengkulu</div>
                </div>
            </a>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <div class="fw-bold text-dark small"><?= e($_SESSION['nama_lengkap'] ?? $_SESSION['username']) ?></div>
                <div class="text-muted" style="font-size: 0.72rem;">Role: <span class="badge bg-secondary"><?= e($role) ?></span></div>
            </div>
            <form action="logout.php" method="POST" class="m-0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger btn-sm px-3" style="border-radius: 8px;">
                    <i class="bi bi-box-arrow-right me-1"></i>Keluar
                </button>
            </form>
        </div>
    </div>
</nav>

<!-- Sidebar Desktop -->
<aside class="sidebar d-none d-lg-block">
    <div class="menu-category">Menu Utama</div>
    <ul class="nav flex-column"><li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-grid-fill"></i> Dashboard</a></li></ul>
    <div class="menu-category">Data Akademik</div>
    <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link" href="dosen.php"><i class="bi bi-person-vcard-fill"></i> Data Dosen</a></li>
        <li class="nav-item"><a class="nav-link" href="master_data.php"><i class="bi bi-folder2-open"></i> Master Data</a></li>
    </ul>
    <div class="menu-category">Manajemen Sistem</div>
    <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link" href="pengguna.php"><i class="bi bi-shield-lock-fill"></i> Pengguna & Role (RBAC)</a></li>
        <li class="nav-item"><a class="nav-link" href="laporan.php"><i class="bi bi-file-earmark-bar-graph-fill"></i> Laporan</a></li>
        <li class="nav-item"><a class="nav-link active" href="api_docs.php"><i class="bi bi-code-slash"></i> API</a></li>
    </ul>
    <div class="menu-category">Akun</div>
    <ul class="nav flex-column"><li class="nav-item"><a class="nav-link" href="profil.php"><i class="bi bi-person-circle"></i> Profil</a></li></ul>
</aside>

<!-- Main Content -->
<main class="main-content">
    <div class="container-fluid px-0">
        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1">Dokumentasi REST API SIM Dosen</h1>
            <p class="text-secondary small mb-0">Panduan lengkap integrasi dan pengujian layanan Web Service REST API berbasis PHP Native & MySQLi.</p>
        </div>

        <!-- Base URL Card -->
        <div class="card border-0 p-4 mb-4">
            <h5 class="fw-bold mb-2" style="color: var(--umb-blue-dark);"><i class="bi bi-globe me-2"></i>Base URL Endpoint</h5>
            <p class="text-muted small mb-2">Seluruh endpoint REST API diakses melalui direktori dasar berikut pada server lokal Anda:</p>
            <code>http://localhost/simdosen/api/dosen.php</code>
        </div>

        <!-- HTTP Status Codes Table -->
        <div class="card border-0 p-4 mb-4">
            <h5 class="fw-bold mb-3" style="color: var(--umb-blue-dark);"><i class="bi bi-table me-2"></i>Tabel Kode HTTP (HTTP Status Codes)</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 100px;">Kode</th>
                            <th style="width: 150px;">Status</th>
                            <th>Keterangan / Penggunaan pada API</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-bold text-success">200</td>
                            <td>OK</td>
                            <td>Permintaan berhasil diproses (digunakan pada GET daftar dosen, GET detail, GET master, PUT, PATCH, dan DELETE).</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-primary">201</td>
                            <td>Created</td>
                            <td>Sumber daya baru berhasil dibuat (digunakan pada metode POST saat sukses menambah dosen).</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-warning">400</td>
                            <td>Bad Request</td>
                            <td>Permintaan tidak valid, parameter kurang, atau validasi gagal.</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-danger">403</td>
                            <td>Forbidden</td>
                            <td>Aksi ditolak, misalnya percobaan menghapus data dosen ID 1 yang dilindungi dari penghapusan.</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-warning">404</td>
                            <td>Not Found</td>
                            <td>Data dosen atau ID yang diminta tidak ditemukan di database.</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-warning">405</td>
                            <td>Method Not Allowed</td>
                            <td>Metode HTTP yang digunakan tidak diizinkan oleh endpoint.</td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-danger">500</td>
                            <td>Internal Server Error</td>
                            <td>Terjadi kesalahan internal pada server atau koneksi database gagal.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 1. GET ALL DOSEN -->
        <div class="card border-0 p-4 mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge badge-get px-3 py-2">GET</span>
                <h5 class="fw-bold m-0">Menampilkan Daftar Seluruh Dosen</h5>
            </div>
            <p class="text-muted small">Mengambil seluruh data rekapitulasi dosen beserta relasi tabel master.</p>
            <code>GET http://localhost/simdosen/api/dosen.php</code>
            <div class="mt-3 mb-2 fw-bold small">Contoh cURL:</div>
            <pre>curl -X GET "http://localhost/simdosen/api/dosen.php"</pre>
            <div class="mt-3 mb-2 fw-bold small">Contoh Respons JSON (HTTP 200 OK):</div>
            <pre>{
  "status": true,
  "message": "Daftar dosen berhasil diambil.",
  "total": 1,
  "data": [
    {
      "id_dosen": 1,
      "nomor_registrasi": "0015058701",
      "nuptk": "1234567890123456",
      "nama": "Dr. Andi Pratama, M.Kom.",
      "nip": "198705152015041001",
      "status_serdos": "Belum",
      "status_aktivitas": "Aktif",
      "keterangan": "Data uji coba",
      "nama_jabatan": "Lektor",
      "nama_pangkat": "Penata Muda",
      "golongan": "III/a",
      "pendidikan_terakhir": "S3",
      "nama_rumpun": "Informatika",
      "status_pegawai": "Dosen Tetap",
      "ikatan_kerja": "Penuh Waktu",
      "universitas": "Universitas Muhammadiyah Bengkulu",
      "fakultas": "Teknik",
      "program_studi": "Teknik Informatika"
    }
  ]
}</pre>
        </div>

        <!-- 2. GET DETAIL DOSEN -->
        <div class="card border-0 p-4 mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge badge-get px-3 py-2">GET</span>
                <h5 class="fw-bold m-0">Menampilkan Detail Dosen Berdasarkan ID</h5>
            </div>
            <p class="text-muted small">Mengambil profil lengkap satu orang dosen spesifik berdasarkan parameter ID.</p>
            <code>GET http://localhost/simdosen/api/dosen.php?id={id_dosen}</code>
            <div class="mt-3 mb-2 fw-bold small">Contoh cURL:</div>
            <pre>curl -X GET "http://localhost/simdosen/api/dosen.php?id=1"</pre>
            <div class="mt-3 mb-2 fw-bold small">Contoh Respons JSON (HTTP 200 OK):</div>
            <pre>{
  "status": true,
  "message": "Detail dosen ditemukan.",
  "data": {
    "id_dosen": 1,
    "nomor_registrasi": "0015058701",
    "nuptk": "1234567890123456",
    "nama": "Dr. Andi Pratama, M.Kom.",
    "nip": "198705152015041001",
    "status_serdos": "Belum",
    "status_aktivitas": "Aktif",
    "keterangan": "Data uji coba"
  }
}</pre>
        </div>

        <!-- 3. GET MASTER DATA -->
        <div class="card border-0 p-4 mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge badge-get px-3 py-2">GET</span>
                <h5 class="fw-bold m-0">Menampilkan Data Master Pendukung</h5>
            </div>
            <p class="text-muted small">Mengambil daftar referensi master (jabatan fungsional, pendidikan, status kepegawaian, dan penempatan prodi).</p>
            <code>GET http://localhost/simdosen/api/dosen.php?action=master</code>
            <div class="mt-3 mb-2 fw-bold small">Contoh cURL:</div>
            <pre>curl -X GET "http://localhost/simdosen/api/dosen.php?action=master"</pre>
            <div class="mt-3 mb-2 fw-bold small">Contoh Respons JSON (HTTP 200 OK):</div>
            <pre>{
  "status": true,
  "message": "Data master berhasil diambil.",
  "data": {
    "jabatan_fungsional": [
      {
        "id_jabatan": 1,
        "nama_jabatan": "Lektor"
      }
    ],
    "pendidikan": [
      {
        "id_pendidikan": 1,
        "jenjang": "S3"
      }
    ],
    "status_kepegawaian": [],
    "penempatan": []
  }
}</pre>
        </div>

        <!-- 4. POST TAMBAH DOSEN -->
        <div class="card border-0 p-4 mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge badge-post px-3 py-2">POST</span>
                <h5 class="fw-bold m-0">Menambahkan Data Dosen Baru</h5>
            </div>
            <p class="text-muted small">Menyimpan data dosen baru melalui body berformat JSON.</p>
            <code>POST http://localhost/simdosen/api/dosen.php</code>
            <div class="mt-3 mb-2 fw-bold small">Contoh Request cURL:</div>
            <pre>curl -X POST "http://localhost/simdosen/api/dosen.php" \
-H "Content-Type: application/json" \
-d '{
  "nama": "Dr. H. Ahmad Fauzi, M.T.",
  "nip": "197512122005011002",
  "status_serdos": "Sudah",
  "status_aktivitas": "Aktif"
}'</pre>
            <div class="mt-3 mb-2 fw-bold small">Contoh Respons JSON (HTTP 201 Created):</div>
            <pre>{
  "status": true,
  "message": "Data dosen berhasil ditambahkan.",
  "id_dosen": 2
}</pre>
        </div>

        <!-- 5. PUT UPDATE DOSEN -->
        <div class="card border-0 p-4 mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge badge-put px-3 py-2">PUT</span>
                <h5 class="fw-bold m-0">Memperbarui Data Dosen (PUT)</h5>
            </div>
            <p class="text-muted small">Memperbarui informasi profil dosen. Field yang tidak dikirim tetap memakai nilai lama.</p>
            <code>PUT http://localhost/simdosen/api/dosen.php?id=2</code>
            <div class="mt-3 mb-2 fw-bold small">Contoh Request cURL:</div>
            <pre>curl -X PUT "http://localhost/simdosen/api/dosen.php?id=2" \
-H "Content-Type: application/json" \
-d '{
  "nama": "Dr. H. Ahmad Fauzi, M.T. (Updated)",
  "status_serdos": "Belum"
}'</pre>
            <div class="mt-3 mb-2 fw-bold small">Contoh Respons JSON (HTTP 200 OK):</div>
            <pre>{
  "status": true,
  "message": "Data dosen berhasil diperbarui."
}</pre>
        </div>

        <!-- 6. PATCH UPDATE DOSEN -->
        <div class="card border-0 p-4 mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge badge-patch px-3 py-2">PATCH</span>
                <h5 class="fw-bold m-0">Memperbarui Sebagian Data Dosen (PATCH)</h5>
            </div>
            <p class="text-muted small">Memperbarui sebagian atribut (misalnya hanya nama) pada data dosen berdasarkan ID.</p>
            <code>PATCH http://localhost/simdosen/api/dosen.php?id=4</code>
            <div class="mt-3 mb-2 fw-bold small">Contoh Request cURL:</div>
            <pre>curl -X PATCH "http://localhost/simdosen/api/dosen.php?id=4" \
-H "Content-Type: application/json" \
-d '{
  "nama": "Dr. H. Ahmad Fauzi, M.T. (Nama Baru)"
}'</pre>
            <div class="mt-3 mb-2 fw-bold small">Contoh Respons JSON (HTTP 200 OK):</div>
            <pre>{
  "status": true,
  "message": "Data dosen berhasil diperbarui."
}</pre>
        </div>

        <!-- 7. DELETE HAPUS DOSEN -->
        <div class="card border-0 p-4 mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge badge-delete px-3 py-2">DELETE</span>
                <h5 class="fw-bold m-0">Menghapus Data Dosen</h5>
            </div>
            <p class="text-muted small">Menghapus data dosen berdasarkan ID. (Catatan: Dosen ID 1 dilindungi dan tidak dapat dihapus).</p>
            <code>DELETE http://localhost/simdosen/api/dosen.php?id={id_dosen}</code>
            <div class="mt-3 mb-2 fw-bold small">Contoh Request cURL:</div>
            <pre>curl -X DELETE "http://localhost/simdosen/api/dosen.php?id=2"</pre>
            <div class="mt-3 mb-2 fw-bold small">Contoh Respons JSON Sukses (HTTP 200 OK):</div>
            <pre>{
  "status": true,
  "message": "Data dosen berhasil dihapus."
}</pre>
            <div class="mt-3 mb-2 fw-bold small">Contoh Respons JSON Proteksi ID 1 (HTTP 403 Forbidden):</div>
            <pre>{
  "status": false,
  "message": "Aksi ditolak: Data dosen ID 1 dilindungi dan tidak boleh dihapus."
}</pre>
        </div>

        <!-- FORMAT ERROR -->
        <div class="card border-0 p-4 mb-4">
            <h5 class="fw-bold mb-3" style="color: var(--umb-blue-dark);"><i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>Format Penanganan Error</h5>
            <p class="text-muted small">Jika terjadi kesalahan parameter, data tidak ditemukan, akses terlarang (seperti penghapusan ID 1), atau kegagalan server, API akan mengembalikan HTTP Status Code yang sesuai (400, 403, 404, 405, 500) beserta respons JSON:</p>
            <pre>{
  "status": false,
  "message": "Deskripsi pesan kesalahan yang aman."
}</pre>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>