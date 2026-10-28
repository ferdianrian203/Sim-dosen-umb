<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/koneksi.php';

// Wajib login untuk mengakses halaman profil
require_login();

$id_pengguna = (int)($_SESSION['id_pengguna'] ?? 0);
$username_session = $_SESSION['username'] ?? '';
$role = (string)($_SESSION['role'] ?? '');

$pesan_sukses = '';
$pesan_error = '';

// Proses pembaruan profil atau password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!csrf_validate(is_string($token) ? $token : null)) {
        $pesan_error = 'Token CSRF tidak valid atau kedaluwarsa.';
    } else {
        $nama_tampilan = trim($_POST['nama_tampilan'] ?? '');
        $email = trim($_POST['email'] ?? '') !== '' ? trim($_POST['email']) : null;
        $password_baru = $_POST['password_baru'] ?? '';

        if ($nama_tampilan === '') {
            $pesan_error = 'Nama tampilan wajib diisi.';
        } else {
            if ($password_baru !== '') {
                $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE pengguna SET nama_tampilan = ?, email = ?, password_hash = ? WHERE id_pengguna = ?");
                $stmt->bind_param('sssi', $nama_tampilan, $email, $password_hash, $id_pengguna);
            } else {
                $stmt = $conn->prepare("UPDATE pengguna SET nama_tampilan = ?, email = ? WHERE id_pengguna = ?");
                $stmt->bind_param('ssi', $nama_tampilan, $email, $id_pengguna);
            }

            if ($stmt && $stmt->execute()) {
                $pesan_sukses = 'Profil berhasil diperbarui.';
                $_SESSION['nama_lengkap'] = $nama_tampilan; // Perbarui session nama agar konsisten di navbar
            } else {
                $pesan_error = 'Gagal memperbarui profil: ' . $conn->error;
            }
            if ($stmt) {
                $stmt->close();
            }
        }
    }
}

// Ambil informasi terbaru akun pengguna dari database
$akun_data = null;
$stmt_user = $conn->prepare("SELECT p.*, r.nama_role, r.keterangan AS ket_role FROM pengguna p INNER JOIN roles r ON p.id_role = r.id_role WHERE p.id_pengguna = ?");
if ($stmt_user) {
    $stmt_user->bind_param('i', $id_pengguna);
    $stmt_user->execute();
    $res_user = $stmt_user->get_result();
    if ($res_user) {
        $akun_data = $res_user->fetch_assoc();
    }
    $stmt_user->close();
}

// Ambil nama lengkap/tampilan utama yang konsisten untuk Header Navbar maupun Ringkasan Profil
$nama_tampilan_utama = !empty($_SESSION['nama_lengkap']) 
    ? $_SESSION['nama_lengkap'] 
    : (!empty($akun_data['nama_tampilan']) ? $akun_data['nama_tampilan'] : $username_session);

// Jika role DOSEN, ambil data dari tabel dosen berdasarkan $_SESSION['id_dosen']
$dosen_data = null;
$id_dosen_session = $_SESSION['id_dosen'] ?? null;

if ($role === 'DOSEN' && !empty($id_dosen_session)) {
    $stmt_dosen = $conn->prepare(
        "SELECT d.*, 
                j.nama_jabatan, 
                p.nama_pangkat, p.golongan, p.ruang, 
                pd.jenjang, 
                ri.nama_rumpun, 
                sk.status_pegawai, sk.ikatan_kerja, 
                pn.universitas, pn.fakultas, pn.program_studi 
         FROM dosen d
         LEFT JOIN jabatan_fungsional j ON d.id_jabatan = j.id_jabatan
         LEFT JOIN kepangkatan p ON d.id_kepangkatan = p.id_kepangkatan
         LEFT JOIN pendidikan pd ON d.id_pendidikan = pd.id_pendidikan
         LEFT JOIN rumpun_ilmu ri ON d.id_rumpun_ilmu = ri.id_rumpun_ilmu
         LEFT JOIN status_kepegawaian sk ON d.id_status_kepegawaian = sk.id_status_kepegawaian
         LEFT JOIN penempatan pn ON d.id_penempatan = pn.id_penempatan
         WHERE d.id_dosen = ?"
    );
    if ($stmt_dosen) {
        $id_dosen_val = (int)$id_dosen_session;
        $stmt_dosen->bind_param('i', $id_dosen_val);
        $stmt_dosen->execute();
        $res_dosen = $stmt_dosen->get_result();
        if ($res_dosen) {
            $dosen_data = $res_dosen->fetch_assoc();
        }
        $stmt_dosen->close();
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profil Pengguna | SIM Dosen Universitas Muhammadiyah Bengkulu</title>
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
        /* Top Navbar */
        .top-navbar {
            height: 70px;
            background: #fff;
            border-bottom: 1px solid #e9eef5;
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            z-index: 1030;
        }
        .brand-mark {
            display: inline-grid; place-items: center;
            width: 38px; height: 38px; border-radius: 10px;
            color: white; background: var(--umb-blue);
            font-size: 1.2rem;
        }
        /* Sidebar */
        .sidebar {
            width: 260px;
            position: fixed;
            top: 70px;
            bottom: 0;
            left: 0;
            z-index: 1025;
            background: var(--sidebar-bg);
            color: rgba(255,255,255,.8);
            overflow-y: auto;
            padding: 20px 15px;
            border-right: 1px solid rgba(255,255,255,.1);
        }
        .sidebar .menu-category {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(255,255,255,.5);
            margin: 18px 0 8px 10px;
            font-weight: 700;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.8);
            padding: 10px 12px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.92rem;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 4px;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #fff;
            background: rgba(255,255,255,0.1);
        }
        .sidebar .nav-link i {
            font-size: 1.1rem;
        }
        /* Main Content wrapper */
        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 30px;
        }
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
        .card {
            border: 1px solid #e7edf5;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(26,58,100,.04);
            transition: .2s ease;
        }
        .card:hover { box-shadow: 0 14px 35px rgba(26,58,100,.08); }
        .btn-primary {
            background: var(--umb-blue); border-color: var(--umb-blue);
            border-radius: 10px; font-weight: 700;
        }
        .btn-primary:hover {
            background: #104a9b; border-color: #104a9b;
        }
        .profile-avatar-sm {
            width: 72px; height: 72px; background: var(--umb-blue-soft);
            color: var(--umb-blue); display: grid; place-items: center;
            font-size: 2.3rem; border-radius: 50%;
        }
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
                <div class="fw-bold text-dark small" style="line-height: 1.2;"><?= e($nama_tampilan_utama) ?></div>
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
        <li class="nav-item">
            <a class="nav-link" href="dashboard.php"><i class="bi bi-grid-fill"></i> Dashboard</a>
        </li>
    </ul>

    <div class="menu-category">Data Akademik</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link" href="dosen.php"><i class="bi bi-person-vcard-fill"></i> Data Dosen</a>
        </li>
        <?php if ($role === 'ADMIN'): ?>
            <li class="nav-item">
                <a class="nav-link" href="master_data.php"><i class="bi bi-folder2-open"></i> Master Data</a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="menu-category">Manajemen Sistem</div>
    <ul class="nav flex-column">
        <?php if ($role === 'ADMIN'): ?>
            <li class="nav-item">
                <a class="nav-link" href="pengguna.php"><i class="bi bi-shield-lock-fill"></i> Pengguna & Role (RBAC)</a>
            </li>
        <?php endif; ?>
        <?php if ($role !== 'DOSEN'): ?>
            <li class="nav-item">
                <a class="nav-link" href="laporan.php"><i class="bi bi-file-earmark-bar-graph-fill"></i> Laporan</a>
            </li>
        <?php endif; ?>
        <?php if (in_array($role, ['ADMIN', 'OPERATOR_PRODI'], true)): ?>
            <li class="nav-item">
                <a class="nav-link" href="api_docs.php"><i class="bi bi-code-slash"></i> API</a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="menu-category">Akun</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link active" href="profil.php"><i class="bi bi-person-circle"></i> Profil</a>
        </li>
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
                <li class="nav-item"><a class="nav-link" href="laporan.php"><i class="bi bi-file-earmark-bar-graph-fill me-2"></i> Laporan</a></li>
            <?php endif; ?>
            <?php if (in_array($role, ['ADMIN', 'OPERATOR_PRODI'], true)): ?>
                <li class="nav-item"><a class="nav-link" href="api_docs.php"><i class="bi bi-code-slash me-2"></i> API</a></li>
            <?php endif; ?>
        </ul>
        <div class="menu-category">Akun</div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link active" href="profil.php"><i class="bi bi-person-circle me-2"></i> Profil</a></li>
        </ul>
    </div>
</div>

<!-- Main Content -->
<main class="main-content">
    <div class="container-fluid px-0">

        <!-- Notifikasi Pesan -->
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

        <!-- Header Halaman -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Profil Pengguna</h1>
                <p class="text-secondary small mb-0">Kelola informasi akun login dan data kepegawaian Anda.</p>
            </div>
        </div>

        <!-- Kartu Tunggal Terpadu Rata Kiri (Clean & Professional) -->
        <div class="card border-0 p-4">
            <!-- Header Ringkasan Profil Rata Kiri -->
            <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center gap-3 pb-4 border-bottom">
                <div class="profile-avatar-sm flex-shrink-0">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="flex-grow-1 text-start">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
        <h5 class="fw-bold mb-0 text-dark"><?= e($nama_tampilan_utama) ?></h5>
        <span class="badge bg-primary px-2 py-1 d-inline-flex align-items-center" style="font-size: 0.7rem; height: 20px;"><?= e($akun_data['nama_role'] ?? $role) ?></span>
    </div>
    <p class="text-muted mb-1" style="font-size: 0.85rem;">@<?= e($akun_data['username'] ?? $username_session) ?> <?= !empty($akun_data['email']) ? '• ' . e($akun_data['email']) : '' ?></p>
    <p class="text-secondary fst-italic mb-0" style="font-size: 0.82rem;"><?= e($akun_data['ket_role'] ?? 'Pengguna Sistem SIM Dosen UMB') ?></p>
</div>
            </div>

            <!-- Form Edit Pengaturan Akun -->
            <div class="pt-4 pb-4 border-bottom text-start">
                <h5 class="h6 fw-bold mb-3 text-dark"><i class="bi bi-gear-fill me-2 text-primary"></i>Pengaturan Akun</h5>
                <form method="POST" action="profil.php">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Nama Tampilan</label>
                            <input type="text" class="form-control" name="nama_tampilan" value="<?= e($nama_tampilan_utama) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Email</label>
                            <input type="email" class="form-control" name="email" value="<?= e($akun_data['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Password Baru <span class="text-muted fw-normal">(Opsional)</span></label>
                            <input type="password" class="form-control" name="password_baru" placeholder="Kosongkan jika tidak ingin mengubah">
                        </div>
                        <div class="col-12 text-end mt-3">
                            <button type="submit" class="btn btn-primary btn-sm px-4"><i class="bi bi-save me-1"></i>Simpan Perubahan</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Bagian Hak Akses Sistem / Detail Dosen -->
            <div class="pt-4 text-start">
                <?php if ($role === 'DOSEN'): ?>
                    <h5 class="h6 fw-bold mb-3 text-dark"><i class="bi bi-person-vcard-fill me-2 text-primary"></i>Data Kepegawaian Dosen</h5>
                    
                    <?php if (empty($id_dosen_session)): ?>
                        <div class="alert alert-warning mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Akun Anda belum terhubung dengan data dosen (ID Dosen pada sesi bernilai kosong). Silakan hubungi Administrator untuk menghubungkan akun Anda dengan direktori dosen.
                        </div>
                    <?php elseif ($dosen_data): ?>
                        <div class="row g-3 small">
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="d-block text-muted mb-1">Nama Lengkap & Gelar</span>
                                    <strong class="text-dark fs-6"><?= e($dosen_data['nama']) ?></strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="d-block text-muted mb-1">Status Aktivitas</span>
                                    <span class="badge bg-success fs-6"><?= e($dosen_data['status_aktivitas']) ?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="d-block text-muted mb-1">NIDN / Nomor Registrasi</span>
                                    <strong class="text-dark"><?= e($dosen_data['nomor_registrasi'] ?? '-') ?></strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="d-block text-muted mb-1">NIP</span>
                                    <strong class="text-dark"><?= e($dosen_data['nip'] ?? '-') ?></strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="d-block text-muted mb-1">NUPTK</span>
                                    <strong class="text-dark"><?= e($dosen_data['nuptk'] ?? '-') ?></strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="d-block text-muted mb-1">Jabatan Fungsional</span>
                                    <strong class="text-dark"><?= e($dosen_data['nama_jabatan'] ?? '-') ?></strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="d-block text-muted mb-1">Kepangkatan / Golongan</span>
                                    <strong class="text-dark"><?= e($dosen_data['nama_pangkat'] ?? '-') ?> <?= $dosen_data['golongan'] ?? '' ?></strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="d-block text-muted mb-1">Pendidikan Terakhir</span>
                                    <strong class="text-dark"><?= e($dosen_data['jenjang'] ?? '-') ?></strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="d-block text-muted mb-1">Rumpun Ilmu</span>
                                    <strong class="text-dark"><?= e($dosen_data['nama_rumpun'] ?? '-') ?></strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="d-block text-muted mb-1">Status Sertifikasi (Serdos)</span>
                                    <strong class="text-dark"><?= e($dosen_data['status_serdos']) ?></strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="d-block text-muted mb-1">Status Kepegawaian</span>
                                    <strong class="text-dark"><?= e($dosen_data['status_pegawai'] ?? '-') ?> (<?= e($dosen_data['ikatan_kerja'] ?? '-') ?>)</strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="d-block text-muted mb-1">Penempatan (Prodi)</span>
                                    <strong class="text-dark"><?= e($dosen_data['program_studi'] ?? '-') ?> — <?= e($dosen_data['fakultas'] ?? '') ?></strong>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="d-block text-muted mb-1">Keterangan / Catatan</span>
                                    <p class="text-dark mb-0"><?= e($dosen_data['keterangan'] ?? 'Tidak ada keterangan.') ?></p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger mb-0" role="alert">
                            <i class="bi bi-exclamation-octagon-fill me-2"></i>
                            Data dosen yang terhubung dengan akun Anda tidak ditemukan di dalam database. Silakan laporkan kepada Administrator.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <h5 class="h6 fw-bold mb-3 text-dark"><i class="bi bi-shield-check me-2 text-primary"></i>Informasi Hak Akses Sistem</h5>
                    <p class="text-secondary small mb-3">
                        Akun Anda terdaftar dengan hak akses <strong><?= e($role) ?></strong>. Berdasarkan aturan RBAC sistem, Anda memiliki wewenang pengelolaan sesuai menu navigasi aktif di panel sebelah kiri.
                    </p>
                    <div class="p-3 bg-light rounded-3">
                        <span class="d-block text-muted small mb-1">Sesi Terverifikasi</span>
                        <strong class="text-dark"><?= e($akun_data['username'] ?? $username_session) ?></strong> (Akun Aktif & Terautentikasi)
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>