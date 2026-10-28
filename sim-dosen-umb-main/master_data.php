<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/koneksi.php';

// Proteksi RBAC: Hanya ADMIN yang diizinkan mengelola Master Data
require_login();
$role = (string)($_SESSION['role'] ?? '');
if ($role !== 'ADMIN') {
    http_response_code(403);
    exit('403 Forbidden: Hanya akun dengan role ADMIN yang dapat mengakses Master Data.');
}

$pesan_sukses = '';
$pesan_error = '';

$tab = $_GET['tab'] ?? 'jabatan';
$aksi = $_GET['aksi'] ?? 'index';

// Validasi tabel yang diizinkan untuk mencegah SQL Injection via parameter tab
$allowed_tabs = [
    'jabatan' => 'jabatan_fungsional',
    'pangkat' => 'kepangkatan',
    'pendidikan' => 'pendidikan',
    'rumpun' => 'rumpun_ilmu',
    'status' => 'status_kepegawaian',
    'penempatan' => 'penempatan'
];

if (!array_key_exists($tab, $allowed_tabs)) {
    $tab = 'jabatan';
}
$active_table = $allowed_tabs[$tab];

// ----------------PROSES HAPUS----------------
if ($aksi === 'hapus') {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
    if (!csrf_validate(is_string($token) ? $token : null)) {
        $pesan_error = 'Token CSRF tidak valid atau kedaluwarsa.';
    } else {
        $id = (int)($_GET['id'] ?? 0);
        $id_column = [
            'jabatan_fungsional' => 'id_jabatan',
            'kepangkatan' => 'id_kepangkatan',
            'pendidikan' => 'id_pendidikan',
            'rumpun_ilmu' => 'id_rumpun_ilmu',
            'status_kepegawaian' => 'id_status_kepegawaian',
            'penempatan' => 'id_penempatan'
        ][$active_table];

        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM {$active_table} WHERE {$id_column} = ?");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $pesan_sukses = 'Data master berhasil dihapus.';
                } else {
                    $pesan_error = 'Gagal menghapus data (kemungkinan sedang digunakan oleh data dosen).';
                }
                $stmt->close();
            }
        }
    }
    $aksi = 'index';
}

// ----------------PROSES TAMBAH / EDIT (POST)----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($aksi === 'tambah' || $aksi === 'edit')) {
    $token = $_POST['csrf_token'] ?? null;
    if (!csrf_validate(is_string($token) ? $token : null)) {
        $pesan_error = 'Token CSRF tidak valid.';
    } else {
        $id = (int)($_POST['id'] ?? 0);

        if ($active_table === 'jabatan_fungsional') {
            $nama_jabatan = trim($_POST['nama_jabatan'] ?? '');
            $aktif = isset($_POST['aktif']) ? 1 : 0;
            if ($nama_jabatan === '') {
                $pesan_error = 'Nama jabatan wajib diisi.';
            } else {
                if ($aksi === 'tambah') {
                    $stmt = $conn->prepare("INSERT INTO jabatan_fungsional (nama_jabatan, aktif) VALUES (?, ?)");
                    $stmt->bind_param('si', $nama_jabatan, $aktif);
                } else {
                    $stmt = $conn->prepare("UPDATE jabatan_fungsional SET nama_jabatan = ?, aktif = ? WHERE id_jabatan = ?");
                    $stmt->bind_param('sii', $nama_jabatan, $aktif, $id);
                }
                execute_stmt($stmt, $pesan_sukses, $pesan_error, $aksi);
            }
        } elseif ($active_table === 'kepangkatan') {
            $nama_pangkat = trim($_POST['nama_pangkat'] ?? '');
            $golongan = trim($_POST['golongan'] ?? '') !== '' ? trim($_POST['golongan']) : null;
            $ruang = trim($_POST['ruang'] ?? '') !== '' ? trim($_POST['ruang']) : null;
            if ($nama_pangkat === '') {
                $pesan_error = 'Nama pangkat wajib diisi.';
            } else {
                if ($aksi === 'tambah') {
                    $stmt = $conn->prepare("INSERT INTO kepangkatan (nama_pangkat, golongan, ruang) VALUES (?, ?, ?)");
                    $stmt->bind_param('sss', $nama_pangkat, $golongan, $ruang);
                } else {
                    $stmt = $conn->prepare("UPDATE kepangkatan SET nama_pangkat = ?, golongan = ?, ruang = ? WHERE id_kepangkatan = ?");
                    $stmt->bind_param('sssi', $nama_pangkat, $golongan, $ruang, $id);
                }
                execute_stmt($stmt, $pesan_sukses, $pesan_error, $aksi);
            }
        } elseif ($active_table === 'pendidikan') {
            $jenjang = trim($_POST['jenjang'] ?? '');
            $urutan = (int)($_POST['urutan'] ?? 0);
            if ($jenjang === '') {
                $pesan_error = 'Jenjang pendidikan wajib diisi.';
            } else {
                if ($aksi === 'tambah') {
                    $stmt = $conn->prepare("INSERT INTO pendidikan (jenjang, urutan) VALUES (?, ?)");
                    $stmt->bind_param('si', $jenjang, $urutan);
                } else {
                    $stmt = $conn->prepare("UPDATE pendidikan SET jenjang = ?, urutan = ? WHERE id_pendidikan = ?");
                    $stmt->bind_param('sii', $jenjang, $urutan, $id);
                }
                execute_stmt($stmt, $pesan_sukses, $pesan_error, $aksi);
            }
        } elseif ($active_table === 'rumpun_ilmu') {
            $nama_rumpun = trim($_POST['nama_rumpun'] ?? '');
            if ($nama_rumpun === '') {
                $pesan_error = 'Nama rumpun ilmu wajib diisi.';
            } else {
                if ($aksi === 'tambah') {
                    $stmt = $conn->prepare("INSERT INTO rumpun_ilmu (nama_rumpun) VALUES (?)");
                    $stmt->bind_param('s', $nama_rumpun);
                } else {
                    $stmt = $conn->prepare("UPDATE rumpun_ilmu SET nama_rumpun = ? WHERE id_rumpun_ilmu = ?");
                    $stmt->bind_param('si', $nama_rumpun, $id);
                }
                execute_stmt($stmt, $pesan_sukses, $pesan_error, $aksi);
            }
        } elseif ($active_table === 'status_kepegawaian') {
            $status_pegawai = trim($_POST['status_pegawai'] ?? '');
            $ikatan_kerja = trim($_POST['ikatan_kerja'] ?? '');
            if ($status_pegawai === '' || $ikatan_kerja === '') {
                $pesan_error = 'Status pegawai dan ikatan kerja wajib diisi.';
            } else {
                if ($aksi === 'tambah') {
                    $stmt = $conn->prepare("INSERT INTO status_kepegawaian (status_pegawai, ikatan_kerja) VALUES (?, ?)");
                    $stmt->bind_param('ss', $status_pegawai, $ikatan_kerja);
                } else {
                    $stmt = $conn->prepare("UPDATE status_kepegawaian SET status_pegawai = ?, ikatan_kerja = ? WHERE id_status_kepegawaian = ?");
                    $stmt->bind_param('ssi', $status_pegawai, $ikatan_kerja, $id);
                }
                execute_stmt($stmt, $pesan_sukses, $pesan_error, $aksi);
            }
        } elseif ($active_table === 'penempatan') {
            $universitas = trim($_POST['universitas'] ?? '');
            $fakultas = trim($_POST['fakultas'] ?? '');
            $program_studi = trim($_POST['program_studi'] ?? '');
            $aktif = isset($_POST['aktif']) ? 1 : 0;
            if ($universitas === '' || $fakultas === '' || $program_studi === '') {
                $pesan_error = 'Semua field penempatan wajib diisi.';
            } else {
                if ($aksi === 'tambah') {
                    $stmt = $conn->prepare("INSERT INTO penempatan (universitas, fakultas, program_studi, aktif) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param('sssi', $universitas, $fakultas, $program_studi, $aktif);
                } else {
                    $stmt = $conn->prepare("UPDATE penempatan SET universitas = ?, fakultas = ?, program_studi = ?, aktif = ? WHERE id_penempatan = ?");
                    $stmt->bind_param('sssii', $universitas, $fakultas, $program_studi, $aktif, $id);
                }
                execute_stmt($stmt, $pesan_sukses, $pesan_error, $aksi);
            }
        }
    }
}

// Helper kecil untuk eksekusi statement
function execute_stmt($stmt, &$pesan_sukses, &$pesan_error, &$aksi) {
    if ($stmt) {
        if ($stmt->execute()) {
            $pesan_sukses = 'Data master berhasil disimpan.';
            $aksi = 'index';
        } else {
            $pesan_error = 'Gagal menyimpan data (duplikat atau kesalahan database): ' . $stmt->error;
        }
        $stmt->close();
    }
}

// Ambil data untuk tabel aktif
$data_list = [];
$result = $conn->query("SELECT * FROM {$active_table}");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data_list[] = $row;
    }
}

// Ambil data spesifik jika mode edit
$edit_data = null;
if ($aksi === 'edit' && isset($_GET['id'])) {
    $id_get = (int)$_GET['id'];
    $id_column = [
        'jabatan_fungsional' => 'id_jabatan',
        'kepangkatan' => 'id_kepangkatan',
        'pendidikan' => 'id_pendidikan',
        'rumpun_ilmu' => 'id_rumpun_ilmu',
        'status_kepegawaian' => 'id_status_kepegawaian',
        'penempatan' => 'id_penempatan'
    ][$active_table];

    $stmt = $conn->prepare("SELECT * FROM {$active_table} WHERE {$id_column} = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id_get);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $edit_data = $res->fetch_assoc();
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Master Data | SIM Dosen UMB</title>
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
        .table-custom th { background-color: #f4f8ff; color: var(--umb-blue-dark); font-weight: 700; border-bottom: 2px solid #e1eaf5; }
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

<!-- Sidebar Desktop Berdasarkan Role -->
<aside class="sidebar d-none d-lg-block">
    <div class="menu-category">Menu Utama</div>
    <ul class="nav flex-column"><li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-grid-fill"></i> Dashboard</a></li></ul>
    <div class="menu-category">Data Akademik</div>
    <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link" href="dosen.php"><i class="bi bi-person-vcard-fill"></i> Data Dosen</a></li>
        <?php if ($role === 'ADMIN'): ?>
            <li class="nav-item"><a class="nav-link active" href="master_data.php"><i class="bi bi-folder2-open"></i> Master Data</a></li>
        <?php endif; ?>
    </ul>
    <div class="menu-category">Manajemen Sistem</div>
    <ul class="nav flex-column">
        <?php if ($role === 'ADMIN'): ?>
            <li class="nav-item"><a class="nav-link" href="pengguna.php"><i class="bi bi-shield-lock-fill"></i> Pengguna & Role (RBAC)</a></li>
        <?php endif; ?>
        <?php if ($role !== 'DOSEN'): ?>
            <li class="nav-item"><a class="nav-link" href="laporan.php"><i class="bi bi-file-earmark-bar-graph-fill"></i> Laporan</a></li>
        <?php endif; ?>
        <?php if (in_array($role, ['ADMIN', 'OPERATOR_PRODI'], true)): ?>
            <li class="nav-item"><a class="nav-link" href="api_docs.php"><i class="bi bi-code-slash"></i> API</a></li>
        <?php endif; ?>
    </ul>
    <div class="menu-category">Akun</div>
    <ul class="nav flex-column"><li class="nav-item"><a class="nav-link" href="profil.php"><i class="bi bi-person-circle"></i> Profil</a></li></ul>
</aside>

<!-- Offcanvas Sidebar Mobile Berdasarkan Role -->
<div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="sidebarMobile" style="background-color: var(--sidebar-bg) !important;">
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
                <li class="nav-item"><a class="nav-link active" href="master_data.php"><i class="bi bi-folder2-open me-2"></i> Master Data</a></li>
            <?php endif; ?>
        </ul>
        <div class="menu-category">Manajemen Sistem</div>
        <ul class="nav flex-column">
            <?php if ($role === 'ADMIN'): ?>
                <li class="nav-item"><a class="nav-link" href="pengguna.php"><i class="bi bi-shield-lock-fill me-2"></i> Pengguna & Role (RBAC)</a></li>
            <?php endif; ?>
            <?php if ($role !== 'DOSEN'): ?>
                <li class="nav-item"><a class="nav-link" href="laporan.php"><i class="bi bi-file-earmark-bar-graph-fill me-2"></i> Laporan</a></li>
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

        <?php if ($pesan_sukses !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= e($pesan_sukses) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($pesan_error !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($pesan_error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Master Data Referensi</h1>
                <p class="text-secondary small mb-0">Kelola referensi akademik (Jabatan Fungsional, Kepangkatan, Pendidikan, dll).</p>
            </div>
            <?php if ($aksi === 'index'): ?>
                <a href="master_data.php?tab=<?= $tab ?>&aksi=tambah" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah Data Baru</a>
            <?php else: ?>
                <a href="master_data.php?tab=<?= $tab ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
            <?php endif; ?>
        </div>

        <!-- Nav Tabs Master -->
        <?php if ($aksi === 'index'): ?>
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item"><a class="nav-link <?= $tab === 'jabatan' ? 'active fw-bold' : '' ?>" href="master_data.php?tab=jabatan">Jabatan Fungsional</a></li>
                <li class="nav-item"><a class="nav-link <?= $tab === 'pangkat' ? 'active fw-bold' : '' ?>" href="master_data.php?tab=pangkat">Kepangkatan</a></li>
                <li class="nav-item"><a class="nav-link <?= $tab === 'pendidikan' ? 'active fw-bold' : '' ?>" href="master_data.php?tab=pendidikan">Pendidikan</a></li>
                <li class="nav-item"><a class="nav-link <?= $tab === 'rumpun' ? 'active fw-bold' : '' ?>" href="master_data.php?tab=rumpun">Rumpun Ilmu</a></li>
                <li class="nav-item"><a class="nav-link <?= $tab === 'status' ? 'active fw-bold' : '' ?>" href="master_data.php?tab=status">Status Kepegawaian</a></li>
                <li class="nav-item"><a class="nav-link <?= $tab === 'penempatan' ? 'active fw-bold' : '' ?>" href="master_data.php?tab=penempatan">Penempatan</a></li>
            </ul>
        <?php endif; ?>

        <!-- KONDISI FORM TAMBAH / EDIT -->
        <?php if ($aksi === 'tambah' || $aksi === 'edit'): ?>
            <div class="card border-0 p-4">
                <h3 class="h5 fw-bold mb-3"><?= $aksi === 'tambah' ? 'Tambah Data Master Baru' : 'Edit Data Master' ?></h3>
                <form method="POST" action="master_data.php?tab=<?= $tab ?>&aksi=<?= $aksi ?><?= $aksi === 'edit' ? '&id=' . ($_GET['id'] ?? 0) : '' ?>">
                    <?= csrf_field() ?>
                    <?php if ($aksi === 'edit'): ?>
                        <input type="hidden" name="id" value="<?= $_GET['id'] ?? 0 ?>">
                    <?php endif; ?>

                    <?php if ($active_table === 'jabatan_fungsional'): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Nama Jabatan Fungsional</label>
                            <input type="text" class="form-control" name="nama_jabatan" value="<?= e($edit_data['nama_jabatan'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="aktif" name="aktif" value="1" <?= (!isset($edit_data) || !empty($edit_data['aktif'])) ? 'checked' : '' ?>>
                            <label class="form-check-label small fw-bold" for="aktif">Aktif</label>
                        </div>

                    <?php elseif ($active_table === 'kepangkatan'): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Nama Pangkat</label>
                            <input type="text" class="form-control" name="nama_pangkat" value="<?= e($edit_data['nama_pangkat'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Golongan</label>
                            <input type="text" class="form-control" name="golongan" value="<?= e($edit_data['golongan'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Ruang</label>
                            <input type="text" class="form-control" name="ruang" value="<?= e($edit_data['ruang'] ?? '') ?>">
                        </div>

                    <?php elseif ($active_table === 'pendidikan'): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Jenjang Pendidikan</label>
                            <input type="text" class="form-control" name="jenjang" value="<?= e($edit_data['jenjang'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Urutan (Prioritas)</label>
                            <input type="number" class="form-control" name="urutan" value="<?= (int)($edit_data['urutan'] ?? 0) ?>" required>
                        </div>

                    <?php elseif ($active_table === 'rumpun_ilmu'): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Nama Rumpun Ilmu</label>
                            <input type="text" class="form-control" name="nama_rumpun" value="<?= e($edit_data['nama_rumpun'] ?? '') ?>" required>
                        </div>

                    <?php elseif ($active_table === 'status_kepegawaian'): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Status Pegawai</label>
                            <input type="text" class="form-control" name="status_pegawai" value="<?= e($edit_data['status_pegawai'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Ikatan Kerja</label>
                            <input type="text" class="form-control" name="ikatan_kerja" value="<?= e($edit_data['ikatan_kerja'] ?? '') ?>" required>
                        </div>

                    <?php elseif ($active_table === 'penempatan'): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Universitas</label>
                            <input type="text" class="form-control" name="universitas" value="<?= e($edit_data['universitas'] ?? 'Universitas Muhammadiyah Bengkulu') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Fakultas</label>
                            <input type="text" class="form-control" name="fakultas" value="<?= e($edit_data['fakultas'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Program Studi</label>
                            <input type="text" class="form-control" name="program_studi" value="<?= e($edit_data['program_studi'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="aktif_p" name="aktif" value="1" <?= (!isset($edit_data) || !empty($edit_data['aktif'])) ? 'checked' : '' ?>>
                            <label class="form-check-label small fw-bold" for="aktif_p">Aktif</label>
                        </div>
                    <?php endif; ?>

                    <div class="text-end">
                        <a href="master_data.php?tab=<?= $tab ?>" class="btn btn-light px-4 me-2">Batal</a>
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Simpan</button>
                    </div>
                </form>
            </div>

        <!-- KONDISI TABEL DATA (INDEX) -->
        <?php else: ?>
            <div class="card border-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-custom">
                        <thead>
                            <tr>
                                <th class="ps-4" style="width: 60px;">No</th>
                                <?php if ($active_table === 'jabatan_fungsional'): ?>
                                    <th>Nama Jabatan</th>
                                    <th>Status</th>
                                <?php elseif ($active_table === 'kepangkatan'): ?>
                                    <th>Nama Pangkat</th>
                                    <th>Golongan</th>
                                    <th>Ruang</th>
                                <?php elseif ($active_table === 'pendidikan'): ?>
                                    <th>Jenjang</th>
                                    <th>Urutan</th>
                                <?php elseif ($active_table === 'rumpun_ilmu'): ?>
                                    <th>Nama Rumpun Ilmu</th>
                                <?php elseif ($active_table === 'status_kepegawaian'): ?>
                                    <th>Status Pegawai</th>
                                    <th>Ikatan Kerja</th>
                                <?php elseif ($active_table === 'penempatan'): ?>
                                    <th>Universitas</th>
                                    <th>Fakultas</th>
                                    <th>Program Studi</th>
                                    <th>Status</th>
                                <?php endif; ?>
                                <th class="text-end pe-4" style="width: 120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data_list)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-secondary">Belum ada data pada master ini.</td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $no = 1; 
                                $id_col = [
                                    'jabatan_fungsional' => 'id_jabatan',
                                    'kepangkatan' => 'id_kepangkatan',
                                    'pendidikan' => 'id_pendidikan',
                                    'rumpun_ilmu' => 'id_rumpun_ilmu',
                                    'status_kepegawaian' => 'id_status_kepegawaian',
                                    'penempatan' => 'id_penempatan'
                                ][$active_table];

                                foreach ($data_list as $row): 
                                ?>
                                    <tr>
                                        <td class="ps-4"><?= $no++ ?></td>
                                        <?php if ($active_table === 'jabatan_fungsional'): ?>
                                            <td class="fw-bold"><?= e($row['nama_jabatan']) ?></td>
                                            <td><span class="badge bg-<?= $row['aktif'] ? 'success' : 'secondary' ?>"><?= $row['aktif'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                                        <?php elseif ($active_table === 'kepangkatan'): ?>
                                            <td class="fw-bold"><?= e($row['nama_pangkat']) ?></td>
                                            <td><?= e($row['golongan'] ?? '-') ?></td>
                                            <td><?= e($row['ruang'] ?? '-') ?></td>
                                        <?php elseif ($active_table === 'pendidikan'): ?>
                                            <td class="fw-bold"><?= e($row['jenjang']) ?></td>
                                            <td><?= (int)$row['urutan'] ?></td>
                                        <?php elseif ($active_table === 'rumpun_ilmu'): ?>
                                            <td class="fw-bold"><?= e($row['nama_rumpun']) ?></td>
                                        <?php elseif ($active_table === 'status_kepegawaian'): ?>
                                            <td class="fw-bold"><?= e($row['status_pegawai']) ?></td>
                                            <td><?= e($row['ikatan_kerja']) ?></td>
                                        <?php elseif ($active_table === 'penempatan'): ?>
                                            <td><?= e($row['universitas']) ?></td>
                                            <td><?= e($row['fakultas']) ?></td>
                                            <td class="fw-bold"><?= e($row['program_studi']) ?></td>
                                            <td><span class="badge bg-<?= $row['aktif'] ? 'success' : 'secondary' ?>"><?= $row['aktif'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                                        <?php endif; ?>
                                        <td class="text-end pe-4">
                                            <a href="master_data.php?tab=<?= $tab ?>&aksi=edit&id=<?= $row[$id_col] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                            <a href="master_data.php?tab=<?= $tab ?>&aksi=hapus&id=<?= $row[$id_col] ?>&csrf_token=<?= csrf_token() ?>" class="btn btn-sm btn-outline-danger" title="Hapus" onclick="return confirm('Yakin ingin menghapus data ini?');"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>