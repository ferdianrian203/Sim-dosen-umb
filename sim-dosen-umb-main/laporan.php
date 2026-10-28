<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/koneksi.php';

// Proteksi Autentikasi dan RBAC: DOSEN dilarang mengakses laporan umum
require_login();
$role = (string)($_SESSION['role'] ?? '');
if ($role === 'DOSEN') {
    http_response_code(403);
    exit('403 Forbidden: Anda tidak memiliki hak akses untuk melihat laporan umum.');
}

// Ambil pilihan filter dari GET
$filter_prodi = (int)($_GET['prodi'] ?? 0);
$filter_serdos = trim($_GET['serdos'] ?? '');

// Ambil data pilihan program studi untuk filter dropdown
$prodi_list = [];
$res_prodi = $conn->query("SELECT id_penempatan, fakultas, program_studi FROM penempatan WHERE aktif = 1 ORDER BY program_studi ASC");
if ($res_prodi) {
    while ($p = $res_prodi->fetch_assoc()) {
        $prodi_list[] = $p;
    }
}

// Query dasar dengan JOIN ke seluruh tabel master
$sql = "SELECT d.*, 
               j.nama_jabatan, 
               k.nama_pangkat, k.golongan, k.ruang, 
               pd.jenjang, 
               ri.nama_rumpun, 
               sk.status_pegawai, sk.ikatan_kerja, 
               pn.universitas, pn.fakultas, pn.program_studi 
        FROM dosen d
        LEFT JOIN jabatan_fungsional j ON d.id_jabatan = j.id_jabatan
        LEFT JOIN kepangkatan k ON d.id_kepangkatan = k.id_kepangkatan
        LEFT JOIN pendidikan pd ON d.id_pendidikan = pd.id_pendidikan
        LEFT JOIN rumpun_ilmu ri ON d.id_rumpun_ilmu = ri.id_rumpun_ilmu
        LEFT JOIN status_kepegawaian sk ON d.id_status_kepegawaian = sk.id_status_kepegawaian
        LEFT JOIN penempatan pn ON d.id_penempatan = pn.id_penempatan
        WHERE d.status_aktivitas = 'Aktif'";

$params = [];
$types = "";

if ($filter_prodi > 0) {
    $sql .= " AND d.id_penempatan = ?";
    $params[] = $filter_prodi;
    $types .= "i";
}

if ($filter_serdos !== '') {
    $sql .= " AND d.status_serdos = ?";
    $params[] = $filter_serdos;
    $types .= "s";
}

$sql .= " ORDER BY d.nama ASC";

// Eksekusi query dengan prepared statements
$dosen_laporan = [];
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $dosen_laporan[] = $row;
            }
        }
        $stmt->close();
    }
} else {
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $dosen_laporan[] = $row;
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Data Dosen | SIM Dosen UMB</title>
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
        .btn-primary { background: var(--umb-blue); border-color: var(--umb-blue); border-radius: 10px; font-weight: 700; }
        .btn-primary:hover { background: #104a9b; border-color: #104a9b; }
        .table-custom th { background-color: #f4f8ff; color: var(--umb-blue-dark); font-weight: 700; border-bottom: 2px solid #e1eaf5; font-size: 0.82rem; vertical-align: middle; text-align: center;}
        .table-custom td { font-size: 0.82rem;  vertical-align: middle; }

        /* Pengaturan Cetak / Print */
        @media print {
            .top-navbar, .sidebar, .no-print { display: none !important; }
            .main-content { margin: 0 !important; padding: 0 !important; }
            .card { border: none !important; box-shadow: none !important; }
            body { background: #fff !important; color: #000 !important; }
        }
    </style>
</head>
<body>

<!-- Top Navbar -->
<nav class="top-navbar navbar navbar-expand px-4 no-print">
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

<!-- Sidebar Desktop Berdasarkan Role -->
<aside class="sidebar d-none d-lg-block no-print">
    <div class="menu-category">Menu Utama</div>
    <ul class="nav flex-column"><li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-grid-fill"></i> Dashboard</a></li></ul>
    <div class="menu-category">Data Akademik</div>
    <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link" href="dosen.php"><i class="bi bi-person-vcard-fill"></i> Data Dosen</a></li>
        <?php if ($role === 'ADMIN'): ?>
            <li class="nav-item"><a class="nav-link" href="master_data.php"><i class="bi bi-folder2-open"></i> Master Data</a></li>
        <?php endif; ?>
    </ul>
    <div class="menu-category">Manajemen Sistem</div>
    <ul class="nav flex-column">
        <?php if ($role === 'ADMIN'): ?>
            <li class="nav-item"><a class="nav-link" href="pengguna.php"><i class="bi bi-shield-lock-fill"></i> Pengguna & Role (RBAC)</a></li>
        <?php endif; ?>
        <?php if ($role !== 'DOSEN'): ?>
            <li class="nav-item"><a class="nav-link active" href="laporan.php"><i class="bi bi-file-earmark-bar-graph-fill"></i> Laporan</a></li>
        <?php endif; ?>
        <?php if (in_array($role, ['ADMIN', 'OPERATOR_PRODI'], true)): ?>
            <li class="nav-item"><a class="nav-link" href="api_docs.php"><i class="bi bi-code-slash"></i> API</a></li>
        <?php endif; ?>
    </ul>
    <div class="menu-category">Akun</div>
    <ul class="nav flex-column"><li class="nav-item"><a class="nav-link" href="profil.php"><i class="bi bi-person-circle"></i> Profil</a></li></ul>
</aside>

<!-- Offcanvas Sidebar Mobile Berdasarkan Role -->
<div class="offcanvas offcanvas-start text-bg-dark no-print" tabindex="-1" id="sidebarMobile" style="background-color: var(--sidebar-bg) !important;">
    <div class="offcanvas-header border-bottom border-secondary">
        <h5 class="offcanvas-title fw-bold">SIM Dosen UMB</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <div class="menu-category">Menu Utama</div>
        <ul class="nav flex-column"><li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-grid-fill me-2"></i> Dashboard</a></li></ul>
        <div class="menu-category">Data Akademik</div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="dosen.php"><i class="bi bi-person-vcard-fill me-2"></i> Data Dosen</a></li>
            <?php if ($role === 'ADMIN'): ?>
                <li class="nav-item"><a class="nav-link" href="master_data.php"><i class="bi bi-folder2-open me-2"></i> Master Data</a></li>
            <?php endif; ?>
        </ul>
        <div class="menu-category">Manajemen Sistem</div>
        <ul class="nav flex-column">
            <?php if ($role === 'ADMIN'): ?>
                <li class="nav-item"><a class="nav-link" href="pengguna.php"><i class="bi bi-shield-lock-fill me-2"></i> Pengguna & Role (RBAC)</a></li>
            <?php endif; ?>
            <?php if ($role !== 'DOSEN'): ?>
                <li class="nav-item"><a class="nav-link active" href="laporan.php"><i class="bi bi-file-earmark-bar-graph-fill me-2"></i> Laporan</a></li>
            <?php endif; ?>
            <?php if (in_array($role, ['ADMIN', 'OPERATOR_PRODI'], true)): ?>
                <li class="nav-item"><a class="nav-link" href="api_docs.php"><i class="bi bi-code-slash me-2"></i> API</a></li>
            <?php endif; ?>
        </ul>
        <div class="menu-category">Akun</div>
        <ul class="nav flex-column"><li class="nav-item"><a class="nav-link" href="profil.php"><i class="bi bi-person-circle me-2"></i> Profil</a></li></ul>
    </div>
</div>

<!-- Main Content -->
<main class="main-content">
    <div class="container-fluid px-0">

        <!-- KOP LAPORAN (Tanpa Card/Layout Kotak) -->
        <div class="mb-4">
            <div class="text-center mb-3">
                <h4 class="fw-bold text-uppercase mb-1" style="color: var(--umb-blue-dark);">Universitas Muhammadiyah Bengkulu</h4>
                <h5 class="fw-bold text-secondary mb-1">Fakultas Teknik — Program Studi Teknik Informatika</h5>
                <h2 class="h4 fw-bold mt-2">Laporan Daftar Dosen Aktif</h2>
                <p class="text-muted small mb-0">Dicetak pada: <?= date('d-m-Y H:i:s') ?> oleh <?= e($_SESSION['nama_lengkap'] ?? $_SESSION['username']) ?></p>
            </div>

            <!-- Filter & Aksi Cetak (Tanpa Card/Layout Kotak) -->
            <div class="no-print pt-3 mt-3 border-top">
                <form method="GET" action="laporan.php" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Filter Penempatan (Prodi)</label>
                        <select class="form-select form-select-sm" name="prodi">
                            <option value="0">-- Semua Program Studi --</option>
                            <?php foreach ($prodi_list as $p): ?>
                                <option value="<?= $p['id_penempatan'] ?>" <?= $filter_prodi == $p['id_penempatan'] ? 'selected' : '' ?>>
                                    <?= e($p['program_studi'] . ' (' . $p['fakultas'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Status Serdos</label>
                        <select class="form-select form-select-sm" name="serdos">
                            <option value="">-- Semua Status Serdos --</option>
                            <?php foreach (['Sudah', 'Belum', 'Tidak Berlaku', 'Belum Diketahui'] as $s): ?>
                                <option value="<?= $s ?>" <?= $filter_serdos === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-filter me-1"></i>Filter</button>
                        <a href="laporan.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                        <button type="button" onclick="window.print();" class="btn btn-success btn-sm ms-auto"><i class="bi bi-printer me-1"></i>Cetak Laporan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- TABEL LAPORAN -->
        <div class="card border-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle mb-0 table-custom">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 40px;">No.</th>
                            <th>Nomor Registrasi (NIDN)</th>
                            <th>NUPTK</th>
                            <th>Nama & Gelar</th>
                            <th>NIP</th>
                            <th>Jabatan Fungsional</th>
                            <th>Kepangkatan</th>
                            <th>Pendidikan Terakhir</th>
                            <th>Rumpun Ilmu</th>
                            <th>Status Serdos</th>
                            <th>Status Pegawai</th>
                            <th>Ikatan Kerja</th>
                            <th>Status Aktivitas</th>
                            <th>Penempatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dosen_laporan)): ?>
                            <tr>
                                <td colspan="14" class="text-center py-4 text-secondary">Tidak ada data dosen aktif yang sesuai dengan filter.</td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($dosen_laporan as $d): ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td><?= e($d['nomor_registrasi'] ?? '-') ?></td>
                                    <td><?= e($d['nuptk'] ?? '-') ?></td>
                                    <td class="fw-bold"><?= e($d['nama']) ?></td>
                                    <td><?= e($d['nip'] ?? '-') ?></td>
                                    <td><?= e($d['nama_jabatan'] ?? '-') ?></td>
                                    <td><?= e($d['nama_pangkat'] ?? '-') ?> <?= $d['golongan'] ? '(' . e($d['golongan']) . '/' . e($d['ruang'] ?? '-') . ')' : '' ?></td>
                                    <td><?= e($d['jenjang'] ?? '-') ?></td>
                                    <td><?= e($d['nama_rumpun'] ?? '-') ?></td>
                                    <td><span class="badge bg-<?= ($d['status_serdos'] === 'Sudah') ? 'success' : 'secondary' ?>"><?= e($d['status_serdos']) ?></span></td>
                                    <td><?= e($d['status_pegawai'] ?? '-') ?></td>
                                    <td><?= e($d['ikatan_kerja'] ?? '-') ?></td>
                                    <td><span class="badge bg-success"><?= e($d['status_aktivitas']) ?></span></td>
                                    <td><?= e($d['program_studi'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>