<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_login();

$nama = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Pengguna';
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';

// Ambil koneksi database
require_once __DIR__ . '/koneksi.php';

// Menghitung statistik berdasarkan data asli di database sim_dosen
$total_dosen = 0;
$total_pengguna = 0;
$total_prodi = 0;

if (isset($conn) && $conn instanceof mysqli) {
    if ($res = $conn->query("SELECT COUNT(*) AS total FROM dosen")) {
        $row = $res->fetch_assoc();
        $total_dosen = (int)($row['total'] ?? 0);
        $res->free();
    }
    if ($res = $conn->query("SELECT COUNT(*) AS total FROM pengguna WHERE aktif = 1")) {
        $row = $res->fetch_assoc();
        $total_pengguna = (int)($row['total'] ?? 0);
        $res->free();
    }
    if ($res = $conn->query("SELECT COUNT(*) AS total FROM penempatan WHERE aktif = 1")) {
        $row = $res->fetch_assoc();
        $total_prodi = (int)($row['total'] ?? 0);
        $res->free();
    }
}

function e_dashboard(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | SIM Dosen Universitas Muhammadiyah Bengkulu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --umb-blue: #1458b8;
            --umb-blue-dark: #0b2d59;
            --umb-blue-soft: #edf4ff;
            --umb-gold: #f2b544;
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
        .stat-card-icon {
            width: 48px; height: 48px; display: grid; place-items: center;
            border-radius: 12px; font-size: 1.25rem;
        }
        .icon-blue { color: #1458b8; background: #e8f1ff; }
        .icon-green { color: #13836b; background: #e3f7f0; }
        .icon-orange { color: #bd6a0b; background: #fff1dc; }
        .btn-primary {
            background: var(--umb-blue); border-color: var(--umb-blue);
            border-radius: 10px; font-weight: 700;
        }
        .btn-primary:hover {
            background: #104a9b; border-color: #104a9b;
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
                <div class="fw-bold text-dark small" style="line-height: 1.2;"><?= e_dashboard((string)$nama) ?></div>
                <div class="text-muted" style="font-size: 0.72rem;">Role: <span class="badge bg-secondary"><?= e_dashboard((string)$role) ?></span></div>
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
            <a class="nav-link active" href="dashboard.php"><i class="bi bi-grid-fill"></i> Dashboard</a>
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
            <a class="nav-link" href="profil.php"><i class="bi bi-person-circle"></i> Profil</a>
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
            <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="bi bi-grid-fill me-2"></i> Dashboard</a></li>
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
            <li class="nav-item"><a class="nav-link" href="profil.php"><i class="bi bi-person-circle me-2"></i> Profil</a></li>
        </ul>
    </div>
</div>

<!-- Main Content -->
<main class="main-content">
    <div class="container-fluid px-0">
        <!-- Welcome Banner -->
        <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #0b2d59, #1458b8); color: white;">
            <div class="card-body p-4 p-md-4">
                <span class="badge mb-2" style="background: rgba(255,255,255,0.2);">Dashboard Utama</span>
                <h1 class="h3 fw-bold mb-1">Selamat datang, <?= e_dashboard((string)$nama) ?>!</h1>
                <p class="mb-0 opacity-75 small" style="max-width: 650px;">
                    Sistem Informasi Manajemen Dosen Universitas Muhammadiyah Bengkulu. Kelola data dosen, referensi akademik, dan laporan terintegrasi.
                </p>
            </div>
        </div>

        <!-- Statistik Ringkas Berdasarkan Data Asli -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-card-icon icon-blue"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <div class="text-muted small">Total Data Dosen</div>
                            <h3 class="h4 fw-bold mb-0"><?= $total_dosen ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-card-icon icon-green"><i class="bi bi-diagram-3-fill"></i></div>
                        <div>
                            <div class="text-muted small">Penempatan / Prodi</div>
                            <h3 class="h4 fw-bold mb-0"><?= $total_prodi ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-card-icon icon-orange"><i class="bi bi-person-badge-fill"></i></div>
                        <div>
                            <div class="text-muted small">Pengguna Aktif</div>
                            <h3 class="h4 fw-bold mb-0"><?= $total_pengguna ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informasi Sistem -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3 text-dark"><i class="bi bi-info-circle me-2 text-primary"></i>Informasi Sistem</h2>
                <div class="row g-3 small text-secondary">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3">
                            <span class="d-block text-muted mb-1">Universitas</span>
                            <strong class="text-dark fs-6">Universitas Muhammadiyah Bengkulu</strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3">
                            <span class="d-block text-muted mb-1">Role Akun</span>
                            <strong class="text-dark fs-6"><?= e_dashboard((string)$role) ?></strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3">
                            <span class="d-block text-muted mb-1">Username</span>
                            <strong class="text-dark fs-6"><?= e_dashboard((string)$username) ?></strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3">
                            <span class="d-block text-muted mb-1">Status Sesi</span>
                            <strong class="text-success fs-6"><i class="bi bi-check-circle-fill me-1"></i>Aktif & Terautentikasi</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>