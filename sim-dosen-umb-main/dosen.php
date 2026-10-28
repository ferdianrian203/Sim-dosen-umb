<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/koneksi.php';

// Proteksi akses RBAC: DOSEN tidak boleh mengakses halaman manajemen penuh ini jika dibatasi, 
// tapi jika dosen boleh melihat data dirinya/list, kita sesuaikan. Berdasarkan aturan sebelumnya: DOSEN hanya lihat data diri/dashboard.
require_login();
$role = (string)($_SESSION['role'] ?? '');
if ($role === 'DOSEN') {
    http_response_code(403);
    exit('403 Forbidden: Anda tidak memiliki hak akses untuk mengelola data dosen.');
}

// Cek hak tulis (ADMIN dan OPERATOR_PRODI)
$can_write = in_array($role, ['ADMIN', 'OPERATOR_PRODI'], true);

$pesan_sukses = '';
$pesan_error = '';

$aksi = $_GET['aksi'] ?? 'index';

// Proses Hapus Data (Hanya untuk yang memiliki hak tulis)
if ($aksi === 'hapus' && $can_write) {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
    if (!csrf_validate(is_string($token) ? $token : null)) {
        $pesan_error = 'Token CSRF tidak valid atau kedaluwarsa.';
    } else {
        $id_dosen = (int)($_GET['id'] ?? 0);
        if ($id_dosen > 0) {
            $stmt = $conn->prepare("DELETE FROM dosen WHERE id_dosen = ?");
            if ($stmt) {
                $stmt->bind_param('i', $id_dosen);
                if ($stmt->execute()) {
                    $pesan_sukses = 'Data dosen berhasil dihapus.';
                } else {
                    $pesan_error = 'Gagal menghapus data dosen: ' . $conn->error;
                }
                $stmt->close();
            }
        }
    }
    $aksi = 'index';
}

// Proses Tambah / Edit Data (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_write && ($aksi === 'tambah' || $aksi === 'edit')) {
    $token = $_POST['csrf_token'] ?? null;
    if (!csrf_validate(is_string($token) ? $token : null)) {
        $pesan_error = 'Token CSRF tidak valid.';
    } else {
        $id_dosen             = (int)($_POST['id_dosen'] ?? 0);
        $nomor_registrasi     = trim($_POST['nomor_registrasi'] ?? '') !== '' ? $conn->real_escape_string(trim($_POST['nomor_registrasi'])) : null;
        $nuptk                = trim($_POST['nuptk'] ?? '') !== '' ? $conn->real_escape_string(trim($_POST['nuptk'])) : null;
        $nama                 = $conn->real_escape_string(trim($_POST['nama'] ?? ''));
        $nip                  = trim($_POST['nip'] ?? '') !== '' ? $conn->real_escape_string(trim($_POST['nip'])) : null;

        // Sanitasi ID Master (Pastikan integer atau NULL)
        $id_jabatan           = !empty($_POST['id_jabatan']) ? (int)$_POST['id_jabatan'] : "NULL";
        $id_kepangkatan       = !empty($_POST['id_kepangkatan']) ? (int)$_POST['id_kepangkatan'] : "NULL";
        $id_pendidikan        = !empty($_POST['id_pendidikan']) ? (int)$_POST['id_pendidikan'] : "NULL";
        $id_rumpun_ilmu       = !empty($_POST['id_rumpun_ilmu']) ? (int)$_POST['id_rumpun_ilmu'] : "NULL";
        $id_status_kepegawaian= !empty($_POST['id_status_kepegawaian']) ? (int)$_POST['id_status_kepegawaian'] : "NULL";
        $id_penempatan        = !empty($_POST['id_penempatan']) ? (int)$_POST['id_penempatan'] : "NULL";
        
        // Validasi ketat enum status_serdos & status_aktivitas
        $allowed_serdos = ['Sudah', 'Belum', 'Tidak Berlaku', 'Belum Diketahui'];
        $input_serdos = $_POST['status_serdos'] ?? 'Belum Diketahui';
        $status_serdos = $conn->real_escape_string(in_array($input_serdos, $allowed_serdos, true) ? $input_serdos : 'Belum Diketahui');

        $allowed_aktivitas = ['Aktif', 'Tidak Aktif', 'Cuti', 'Pensiun', 'Belum Diketahui'];
        $input_aktivitas = $_POST['status_aktivitas'] ?? 'Aktif';
        $status_aktivitas = $conn->real_escape_string(in_array($input_aktivitas, $allowed_aktivitas, true) ? $input_aktivitas : 'Aktif');

        $keterangan           = trim($_POST['keterangan'] ?? '') !== '' ? "'" . $conn->real_escape_string(trim($_POST['keterangan'])) . "'" : "NULL";

        // Format helper untuk string SQL (jika null tulis NULL, jika ada isi beri tanda kutip)
        $sql_nomor_reg = $nomor_registrasi !== null ? "'$nomor_registrasi'" : "NULL";
        $sql_nuptk     = $nuptk !== null ? "'$nuptk'" : "NULL";
        $sql_nip       = $nip !== null ? "'$nip'" : "NULL";

        if ($nama === '') {
            $pesan_error = 'Nama dosen wajib diisi.';
        } else {
            if ($aksi === 'tambah') {
                $query_str = "INSERT INTO dosen (
                    nomor_registrasi, nuptk, nama, nip, 
                    id_jabatan, id_kepangkatan, id_pendidikan, id_rumpun_ilmu, id_status_kepegawaian, id_penempatan, 
                    status_serdos, status_aktivitas, keterangan
                ) VALUES (
                    $sql_nomor_reg, $sql_nuptk, '$nama', $sql_nip, 
                    $id_jabatan, $id_kepangkatan, $id_pendidikan, $id_rumpun_ilmu, $id_status_kepegawaian, $id_penempatan, 
                    '$status_serdos', '$status_aktivitas', $keterangan
                )";

                if ($conn->query($query_str)) {
                    $pesan_sukses = 'Data dosen berhasil ditambahkan.';
                    $aksi = 'index';
                } else {
                    $pesan_error = 'Gagal menyimpan data: ' . $conn->error;
                }
            } elseif ($aksi === 'edit' && $id_dosen > 0) {
                $query_str = "UPDATE dosen SET 
                    nomor_registrasi = $sql_nomor_reg, 
                    nuptk = $sql_nuptk, 
                    nama = '$nama', 
                    nip = $sql_nip, 
                    id_jabatan = $id_jabatan, 
                    id_kepangkatan = $id_kepangkatan, 
                    id_pendidikan = $id_pendidikan, 
                    id_rumpun_ilmu = $id_rumpun_ilmu, 
                    id_status_kepegawaian = $id_status_kepegawaian, 
                    id_penempatan = $id_penempatan, 
                    status_serdos = '$status_serdos', 
                    status_aktivitas = '$status_aktivitas', 
                    keterangan = $keterangan 
                 WHERE id_dosen = $id_dosen";

                if ($conn->query($query_str)) {
                    $pesan_sukses = 'Data dosen berhasil diperbarui.';
                    $aksi = 'index';
                } else {
                    $pesan_error = 'Gagal memperbarui data: ' . $conn->error;
                }
            }
        }
    }
}

// Ambil data untuk form dropdown master
$opt_jabatan = $conn->query("SELECT * FROM jabatan_fungsional WHERE aktif = 1 ORDER BY nama_jabatan ASC");
$opt_pangkat = $conn->query("SELECT * FROM kepangkatan ORDER BY nama_pangkat ASC");
$opt_pendidikan = $conn->query("SELECT * FROM pendidikan ORDER BY urutan ASC");
$opt_rumpun = $conn->query("SELECT * FROM rumpun_ilmu ORDER BY nama_rumpun ASC");
$opt_status = $conn->query("SELECT * FROM status_kepegawaian ORDER BY status_pegawai ASC");
$opt_penempatan = $conn->query("SELECT * FROM penempatan WHERE aktif = 1 ORDER BY program_studi ASC");

// Pencarian dan Pagination Sederhana
$keyword = trim($_GET['q'] ?? '');
$where_sql = "";
$params = [];
$types = "";

if ($keyword !== '') {
    $where_sql = " WHERE d.nama LIKE ? OR d.nip LIKE ? OR d.nuptk LIKE ? OR d.nomor_registrasi LIKE ?";
    $like = "%{$keyword}%";
    $params = [$like, $like, $like, $like];
    $types = "ssss";
}

$query_sql = 
    "SELECT d.*, 
            j.nama_jabatan, 
            p.nama_pangkat, p.golongan, 
            pd.jenjang, 
            r.nama_rumpun, 
            sk.status_pegawai, sk.ikatan_kerja, 
            pn.program_studi, pn.fakultas 
     FROM dosen d
     LEFT JOIN jabatan_fungsional j ON d.id_jabatan = j.id_jabatan
     LEFT JOIN kepangkatan p ON d.id_kepangkatan = p.id_kepangkatan
     LEFT JOIN pendidikan pd ON d.id_pendidikan = pd.id_pendidikan
     LEFT JOIN rumpun_ilmu r ON d.id_rumpun_ilmu = r.id_rumpun_ilmu
     LEFT JOIN status_kepegawaian sk ON d.id_status_kepegawaian = sk.id_status_kepegawaian
     LEFT JOIN penempatan pn ON d.id_penempatan = pn.id_penempatan" . $where_sql . " ORDER BY d.nama ASC";

$dosen_list = [];
if ($keyword !== '') {
    $stmt = $conn->prepare($query_sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $dosen_list[] = $row;
            }
        }
        $stmt->close();
    }
} else {
    $result = $conn->query($query_sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $dosen_list[] = $row;
        }
    }
}

// Ambil data spesifik jika mode edit / detail
$edit_data = null;
if (($aksi === 'edit' || $aksi === 'detail') && isset($_GET['id'])) {
    $id_get = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM dosen WHERE id_dosen = ?");
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
    <title>Data Dosen | SIM Dosen UMB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --umb-blue: #1458b8;
            --umb-blue-dark: #0b2d59;
            --umb-blue-soft: #edf4ff;
            --text-main: #182538;
            --text-muted: #65748a;
            --sidebar-bg: #0b2d59;
        }
        body {
            font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif;
            color: var(--text-main);
            background: #f7faff;
            overflow-x: hidden;
        }
        .top-navbar {
            height: 70px; background: #fff; border-bottom: 1px solid #e9eef5;
            position: fixed; top: 0; right: 0; left: 0; z-index: 1030;
        }
        .brand-mark {
            display: inline-grid; place-items: center; width: 38px; height: 38px;
            border-radius: 10px; color: white; background: var(--umb-blue); font-size: 1.2rem;
        }
        .sidebar {
            width: 260px; position: fixed; top: 70px; bottom: 0; left: 0;
            z-index: 1025; background: var(--sidebar-bg); color: rgba(255,255,255,.8);
            overflow-y: auto; padding: 20px 15px; border-right: 1px solid rgba(255,255,255,.1);
        }
        .sidebar .menu-category {
            font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em;
            color: rgba(255,255,255,.5); margin: 18px 0 8px 10px; font-weight: 700;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.8); padding: 10px 12px; border-radius: 8px;
            font-weight: 500; font-size: 0.92rem; display: flex; align-items: center; gap: 10px;
            margin-bottom: 4px; transition: all 0.2s ease; text-decoration: none;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #fff; background: rgba(255,255,255,0.1);
        }
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
                <div class="fw-bold text-dark small" style="line-height: 1.2;"><?= e($_SESSION['nama_lengkap'] ?? $_SESSION['username']) ?></div>
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
    <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-grid-fill"></i> Dashboard</a></li>
    </ul>
    <div class="menu-category">Data Akademik</div>
    <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link active" href="dosen.php"><i class="bi bi-person-vcard-fill"></i> Data Dosen</a></li>
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
            <li class="nav-item"><a class="nav-link" href="laporan.php"><i class="bi bi-file-earmark-bar-graph-fill"></i> Laporan</a></li>
        <?php endif; ?>
        <?php if (in_array($role, ['ADMIN', 'OPERATOR_PRODI'], true)): ?>
            <li class="nav-item"><a class="nav-link" href="api_docs.php"><i class="bi bi-code-slash"></i> API</a></li>
        <?php endif; ?>
    </ul>
    <div class="menu-category">Akun</div>
    <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link" href="profil.php"><i class="bi bi-person-circle"></i> Profil</a></li>
    </ul>
</aside>

<!-- Offcanvas Sidebar Mobile Berdasarkan Role -->
<div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="sidebarMobile" style="background-color: var(--sidebar-bg) !important;">
    <div class="offcanvas-header border-bottom border-secondary">
        <h5 class="offcanvas-title fw-bold">SIM Dosen UMB</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <div class="menu-category">Menu Utama</div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-grid-fill me-2"></i> Dashboard</a></li>
        </ul>
        <div class="menu-category">Data Akademik</div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link active" href="dosen.php"><i class="bi bi-person-vcard-fill me-2"></i> Data Dosen</a></li>
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
                <li class="nav-item"><a class="nav-link" href="laporan.php"><i class="bi bi-file-earmark-bar-graph-fill me-2"></i> Laporan</a></li>
            <?php endif; ?>
            <?php if (in_array($role, ['ADMIN', 'OPERATOR_PRODI'], true)): ?>
                <li class="nav-item"><a class="nav-link" href="api_docs.php"><i class="bi bi-code-slash me-2"></i> API</a></li>
            <?php endif; ?>
        </ul>
        <div class="menu-category">Akun</div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="profil.php"><i class="bi bi-person-circle me-2"></i> Profil</a></li>
        </ul>
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

        <!-- KONDISI 1: FORM TAMBAH / EDIT -->
        <?php if (($aksi === 'tambah' || $aksi === 'edit') && $can_write): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1"><?= $aksi === 'tambah' ? 'Tambah Data Dosen' : 'Edit Data Dosen' ?></h1>
                    <p class="text-secondary small mb-0">Formulir pengelolaan informasi pokok dan akademik dosen.</p>
                </div>
                <a href="dosen.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
            </div>

            <div class="card border-0 p-4">
                <form method="POST" action="dosen.php?aksi=<?= $aksi ?><?= $aksi === 'edit' ? '&id=' . ($edit_data['id_dosen'] ?? 0) : '' ?>">
                    <?= csrf_field() ?>
                    <?php if ($aksi === 'edit'): ?>
                        <input type="hidden" name="id_dosen" value="<?= $edit_data['id_dosen'] ?? 0 ?>">
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Nama Lengkap & Gelar <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama" value="<?= e($edit_data['nama'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">NIP</label>
                            <input type="text" class="form-control" name="nip" value="<?= e($edit_data['nip'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">NIDN / Nomor Registrasi</label>
                            <input type="text" class="form-control" name="nomor_registrasi" value="<?= e($edit_data['nomor_registrasi'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">NUPTK</label>
                            <input type="text" class="form-control" name="nuptk" value="<?= e($edit_data['nuptk'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Jabatan Fungsional</label>
                            <select class="form-select" name="id_jabatan">
                                <option value="">-- Pilih Jabatan --</option>
                                <?php while ($j = $opt_jabatan->fetch_assoc()): ?>
                                    <option value="<?= $j['id_jabatan'] ?>" <?= (isset($edit_data['id_jabatan']) && $edit_data['id_jabatan'] == $j['id_jabatan']) ? 'selected' : '' ?>><?= e($j['nama_jabatan']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Kepangkatan / Golongan</label>
                            <select class="form-select" name="id_kepangkatan">
                                <option value="">-- Pilih Pangkat --</option>
                                <?php while ($p = $opt_pangkat->fetch_assoc()): ?>
                                    <option value="<?= $p['id_kepangkatan'] ?>" <?= (isset($edit_data['id_kepangkatan']) && $edit_data['id_kepangkatan'] == $p['id_kepangkatan']) ? 'selected' : '' ?>><?= e($p['nama_pangkat'] . ' (' . ($p['golongan'] ?? '-') . '/' . ($p['ruang'] ?? '-') . ')') ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Pendidikan Terakhir</label>
                            <select class="form-select" name="id_pendidikan">
                                <option value="">-- Pilih Jenjang --</option>
                                <?php while ($pd = $opt_pendidikan->fetch_assoc()): ?>
                                    <option value="<?= $pd['id_pendidikan'] ?>" <?= (isset($edit_data['id_pendidikan']) && $edit_data['id_pendidikan'] == $pd['id_pendidikan']) ? 'selected' : '' ?>><?= e($pd['jenjang']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Rumpun Ilmu</label>
                            <select class="form-select" name="id_rumpun_ilmu">
                                <option value="">-- Pilih Rumpun Ilmu --</option>
                                <?php while ($r = $opt_rumpun->fetch_assoc()): ?>
                                    <option value="<?= $r['id_rumpun_ilmu'] ?>" <?= (isset($edit_data['id_rumpun_ilmu']) && $edit_data['id_rumpun_ilmu'] == $r['id_rumpun_ilmu']) ? 'selected' : '' ?>><?= e($r['nama_rumpun']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Status Kepegawaian</label>
                            <select class="form-select" name="id_status_kepegawaian">
                                <option value="">-- Pilih Status --</option>
                                <?php while ($sk = $opt_status->fetch_assoc()): ?>
                                    <option value="<?= $sk['id_status_kepegawaian'] ?>" <?= (isset($edit_data['id_status_kepegawaian']) && $edit_data['id_status_kepegawaian'] == $sk['id_status_kepegawaian']) ? 'selected' : '' ?>><?= e($sk['status_pegawai'] . ' - ' . $sk['ikatan_kerja']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Penempatan (Prodi / Fakultas)</label>
                            <select class="form-select" name="id_penempatan">
                                <option value="">-- Pilih Penempatan --</option>
                                <?php while ($pn = $opt_penempatan->fetch_assoc()): ?>
                                    <option value="<?= $pn['id_penempatan'] ?>" <?= (isset($edit_data['id_penempatan']) && $edit_data['id_penempatan'] == $pn['id_penempatan']) ? 'selected' : '' ?>><?= e($pn['program_studi'] . ' (' . $pn['fakultas'] . ')') ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Status Sertifikasi (Serdos)</label>
                            <select class="form-select" name="status_serdos">
                                <?php foreach (['Belum Diketahui', 'Sudah', 'Belum', 'Tidak Berlaku'] as $st): ?>
                                    <option value="<?= $st ?>" <?= (isset($edit_data['status_serdos']) && $edit_data['status_serdos'] === $st) ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Status Aktivitas</label>
                            <select class="form-select" name="status_aktivitas">
                                <?php foreach (['Aktif', 'Tidak Aktif', 'Cuti', 'Pensiun', 'Belum Diketahui'] as $sa): ?>
                                    <option value="<?= $sa ?>" <?= (isset($edit_data['status_aktivitas']) && $edit_data['status_aktivitas'] === $sa) ? 'selected' : '' ?>><?= $sa ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Keterangan / Catatan</label>
                            <textarea class="form-control" name="keterangan" rows="3"><?= e($edit_data['keterangan'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12 text-end mt-4">
                            <a href="dosen.php" class="btn btn-light px-4 me-2">Batal</a>
                            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Simpan Data</button>
                        </div>
                    </div>
                </form>
            </div>

        <!-- KONDISI 2: DETAIL DOSEN -->
        <?php elseif ($aksi === 'detail' && $edit_data): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Detail Profil Dosen</h1>
                    <p class="text-secondary small mb-0">Informasi lengkap data pokok dan akademik.</p>
                </div>
                <a href="dosen.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
            </div>

            <div class="card border-0 p-4">
                <div class="row g-3">
                    <div class="col-md-6"><strong>Nama Lengkap:</strong> <?= e($edit_data['nama']) ?></div>
                    <div class="col-md-6"><strong>NIP:</strong> <?= e($edit_data['nip'] ?? '-') ?></div>
                    <div class="col-md-6"><strong>NIDN / No. Registrasi:</strong> <?= e($edit_data['nomor_registrasi'] ?? '-') ?></div>
                    <div class="col-md-6"><strong>NUPTK:</strong> <?= e($edit_data['nuptk'] ?? '-') ?></div>
                    <div class="col-md-6"><strong>Status Aktivitas:</strong> <span class="badge bg-success"><?= e($edit_data['status_aktivitas']) ?></span></div>
                    <div class="col-md-6"><strong>Status Serdos:</strong> <?= e($edit_data['status_serdos']) ?></div>
                    <div class="col-12"><hr></div>
                    <div class="col-12"><strong>Keterangan:</strong><p class="text-secondary mt-1"><?= e($edit_data['keterangan'] ?? 'Tidak ada keterangan.') ?></p></div>
                </div>
            </div>

        <!-- KONDISI 3: TABEL DAFTAR DOSEN (INDEX) -->
        <?php else: ?>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Data Dosen</h1>
                    <p class="text-secondary small mb-0">Kelola direktori dosen Universitas Muhammadiyah Bengkulu.</p>
                </div>
                <?php if ($can_write): ?>
                    <a href="dosen.php?aksi=tambah" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah Dosen Baru</a>
                <?php endif; ?>
            </div>

            <!-- Kotak Pencarian -->
            <div class="card border-0 mb-4 p-3">
                <form method="GET" action="dosen.php" class="row g-2">
                    <div class="col-md-10">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control border-start-0" name="q" value="<?= e($keyword) ?>" placeholder="Cari berdasarkan Nama, NIP, NIDN, atau NUPTK...">
                        </div>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary">Cari</button>
                    </div>
                </form>
            </div>

            <!-- Tabel Data -->
            <div class="card border-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-custom">
                        <thead>
                            <tr>
                                <th class="ps-4">No</th>
                                <th>Nama & NIDN/NIP</th>
                                <th>Jabatan & Pangkat</th>
                                <th>Penempatan (Prodi)</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dosen_list)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-secondary">Tidak ada data dosen yang ditemukan.</td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($dosen_list as $d): ?>
                                    <tr>
                                        <td class="ps-4"><?= $no++ ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($d['nama']) ?></div>
                                            <div class="text-muted small">NIDN: <?= e($d['nomor_registrasi'] ?? '-') ?> | NIP: <?= e($d['nip'] ?? '-') ?></div>
                                        </td>
                                        <td>
                                            <div class="small"><?= e($d['nama_jabatan'] ?? '-') ?></div>
                                            <div class="text-muted small"><?= e($d['nama_pangkat'] ?? '-') ?></div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?= e($d['program_studi'] ?? '-') ?></div>
                                            <div class="text-muted" style="font-size:0.75rem;"><?= e($d['fakultas'] ?? '') ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= ($d['status_aktivitas'] === 'Aktif') ? 'success' : 'secondary' ?>"><?= e($d['status_aktivitas']) ?></span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="dosen.php?aksi=detail&id=<?= $d['id_dosen'] ?>" class="btn btn-sm btn-outline-info" title="Detail"><i class="bi bi-eye"></i></a>
                                            <?php if ($can_write): ?>
                                                <a href="dosen.php?aksi=edit&id=<?= $d['id_dosen'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                                <a href="dosen.php?aksi=hapus&id=<?= $d['id_dosen'] ?>&csrf_token=<?= csrf_token() ?>" class="btn btn-sm btn-outline-danger" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus data dosen <?= e($d['nama']) ?>?');"><i class="bi bi-trash"></i></a>
                                            <?php endif; ?>
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