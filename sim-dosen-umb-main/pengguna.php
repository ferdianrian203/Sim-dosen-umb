<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/koneksi.php';

// Proteksi RBAC: Hanya ADMIN yang diizinkan mengakses Manajemen Pengguna
require_login();
$role = (string)($_SESSION['role'] ?? '');
if ($role !== 'ADMIN') {
    http_response_code(403);
    exit('403 Forbidden: Hanya akun dengan role ADMIN yang dapat mengelola pengguna sistem.');
}

$pesan_sukses = '';
$pesan_error = '';
$aksi = $_GET['aksi'] ?? 'index';

// Proses Hapus / Nonaktifkan Pengguna
if ($aksi === 'hapus') {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
    if (!csrf_validate(is_string($token) ? $token : null)) {
        $pesan_error = 'Token CSRF tidak valid atau kedaluwarsa.';
    } else {
        $id_pengguna = (int)($_GET['id'] ?? 0);
        
        // Cek apakah pengguna yang akan dihapus adalah admin aktif terakhir
        $check_admin = $conn->prepare(
            "SELECT COUNT(*) AS total_admin 
             FROM pengguna p 
             JOIN roles r ON p.id_role = r.id_role 
             WHERE r.nama_role = 'ADMIN' AND p.aktif = 1 AND p.id_pengguna != ?"
        );
        $check_admin->bind_param('i', $id_pengguna);
        $check_admin->execute();
        $res_admin = $check_admin->get_result()->fetch_assoc();
        $check_admin->close();

        // Cek role target yang dihapus
        $target_role = $conn->prepare("SELECT r.nama_role FROM pengguna p JOIN roles r ON p.id_role = r.id_role WHERE p.id_pengguna = ?");
        $target_role->bind_param('i', $id_pengguna);
        $target_role->execute();
        $res_target = $target_role->get_result()->fetch_assoc();
        $target_role->close();

        if ($res_target && $res_target['nama_role'] === 'ADMIN' && (int)$res_admin['total_admin'] < 1) {
            $pesan_error = 'Penghapusan ditolak: Sistem memerlukan minimal satu akun ADMIN aktif.';
        } else {
            $stmt = $conn->prepare("DELETE FROM pengguna WHERE id_pengguna = ?");
            if ($stmt) {
                $stmt->bind_param('i', $id_pengguna);
                if ($stmt->execute()) {
                    $pesan_sukses = 'Pengguna berhasil dihapus.';
                } else {
                    $pesan_error = 'Gagal menghapus pengguna: ' . $conn->error;
                }
                $stmt->close();
            }
        }
    }
    $aksi = 'index';
}

// Proses Tambah / Edit Pengguna (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($aksi === 'tambah' || $aksi === 'edit')) {
    $token = $_POST['csrf_token'] ?? null;
    if (!csrf_validate(is_string($token) ? $token : null)) {
        $pesan_error = 'Token CSRF tidak valid.';
    } else {
        $id_pengguna   = (int)($_POST['id_pengguna'] ?? 0);
        $username      = trim($_POST['username'] ?? '');
        $nama_tampilan = trim($_POST['nama_tampilan'] ?? '');
        $email         = trim($_POST['email'] ?? '') !== '' ? trim($_POST['email']) : null;
        $id_role       = (int)($_POST['id_role'] ?? 0);
        $aktif         = isset($_POST['aktif']) ? 1 : 0;
        $password      = $_POST['password'] ?? '';

        if ($username === '' || $id_role <= 0) {
            $pesan_error = 'Username dan Role wajib diisi dengan benar.';
        } else {
            if ($aksi === 'tambah') {
                if ($password === '') {
                    $pesan_error = 'Password wajib diisi untuk pengguna baru.';
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare(
                        "INSERT INTO pengguna (username, password_hash, nama_tampilan, email, id_role, aktif) 
                         VALUES (?, ?, ?, ?, ?, ?)"
                    );
                    if ($stmt) {
                        $stmt->bind_param('ssssii', $username, $password_hash, $nama_tampilan, $email, $id_role, $aktif);
                        if ($stmt->execute()) {
                            $pesan_sukses = 'Pengguna baru berhasil ditambahkan.';
                            $aksi = 'index';
                        } else {
                            $pesan_error = 'Gagal menyimpan (kemungkinan username atau email sudah digunakan): ' . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
            } elseif ($aksi === 'edit' && $id_pengguna > 0) {
                // Jika password diisi, perbarui password. Jika kosong, pertahankan password lama.
                if ($password !== '') {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare(
                        "UPDATE pengguna SET username = ?, password_hash = ?, nama_tampilan = ?, email = ?, id_role = ?, aktif = ? 
                         WHERE id_pengguna = ?"
                    );
                    $stmt->bind_param('ssssiii', $username, $password_hash, $nama_tampilan, $email, $id_role, $aktif, $id_pengguna);
                } else {
                    $stmt = $conn->prepare(
                        "UPDATE pengguna SET username = ?, nama_tampilan = ?, email = ?, id_role = ?, aktif = ? 
                         WHERE id_pengguna = ?"
                    );
                    $stmt->bind_param('sssiii', $username, $nama_tampilan, $email, $id_role, $aktif, $id_pengguna);
                }

                if ($stmt) {
                    if ($stmt->execute()) {
                        $pesan_sukses = 'Data pengguna berhasil diperbarui.';
                        $aksi = 'index';
                    } else {
                        $pesan_error = 'Gagal memperbarui data: ' . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Ambil daftar roles untuk pilihan dropdown
$roles_list = [];
$res_roles = $conn->query("SELECT * FROM roles ORDER BY nama_role ASC");
if ($res_roles) {
    while ($r = $res_roles->fetch_assoc()) {
        $roles_list[] = $r;
    }
}

// Ambil daftar pengguna beserta nama rolenya
$pengguna_list = [];
$query_pengguna = 
    "SELECT p.*, r.nama_role 
     FROM pengguna p 
     INNER JOIN roles r ON p.id_role = r.id_role 
     ORDER BY p.username ASC";
$res_pengguna = $conn->query($query_pengguna);
if ($res_pengguna) {
    while ($row = $res_pengguna->fetch_assoc()) {
        $pengguna_list[] = $row;
    }
}

// Ambil data spesifik jika mode edit
$edit_data = null;
if ($aksi === 'edit' && isset($_GET['id'])) {
    $id_get = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM pengguna WHERE id_pengguna = ?");
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
    <title>Manajemen Pengguna | SIM Dosen UMB</title>
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
            <li class="nav-item"><a class="nav-link" href="master_data.php"><i class="bi bi-folder2-open"></i> Master Data</a></li>
        <?php endif; ?>
    </ul>
    <div class="menu-category">Manajemen Sistem</div>
    <ul class="nav flex-column">
        <?php if ($role === 'ADMIN'): ?>
            <li class="nav-item"><a class="nav-link active" href="pengguna.php"><i class="bi bi-shield-lock-fill"></i> Pengguna & Role (RBAC)</a></li>
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
                <li class="nav-item"><a class="nav-link" href="master_data.php"><i class="bi bi-folder2-open me-2"></i> Master Data</a></li>
            <?php endif; ?>
        </ul>
        <div class="menu-category">Manajemen Sistem</div>
        <ul class="nav flex-column">
            <?php if ($role === 'ADMIN'): ?>
                <li class="nav-item"><a class="nav-link active" href="pengguna.php"><i class="bi bi-shield-lock-fill me-2"></i> Pengguna & Role (RBAC)</a></li>
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
                <h1 class="h3 fw-bold mb-1">Manajemen Pengguna & Role (RBAC)</h1>
                <p class="text-secondary small mb-0">Kelola akun login aplikasi, hak akses role, serta pembaruan kata sandi.</p>
            </div>
            <?php if ($aksi === 'index'): ?>
                <a href="pengguna.php?aksi=tambah" class="btn btn-primary"><i class="bi bi-person-plus-fill me-1"></i>Tambah Pengguna Baru</a>
            <?php else: ?>
                <a href="pengguna.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
            <?php endif; ?>
        </div>

        <!-- KONDISI FORM TAMBAH / EDIT -->
        <?php if ($aksi === 'tambah' || $aksi === 'edit'): ?>
            <div class="card border-0 p-4">
                <h3 class="h5 fw-bold mb-3"><?= $aksi === 'tambah' ? 'Tambah Akun Pengguna' : 'Edit Akun Pengguna' ?></h3>
                <form method="POST" action="pengguna.php?aksi=<?= $aksi ?><?= $aksi === 'edit' ? '&id=' . ($_GET['id'] ?? 0) : '' ?>">
                    <?= csrf_field() ?>
                    <?php if ($aksi === 'edit'): ?>
                        <input type="hidden" name="id_pengguna" value="<?= $edit_data['id_pengguna'] ?? 0 ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" value="<?= e($edit_data['username'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Nama Tampilan (Display Name)</label>
                        <input type="text" class="form-control" name="nama_tampilan" value="<?= e($edit_data['nama_tampilan'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Email</label>
                        <input type="email" class="form-control" name="email" value="<?= e($edit_data['email'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Role Akses (RBAC) <span class="text-danger">*</span></label>
                        <select class="form-select" name="id_role" required>
                            <option value="">-- Pilih Role --</option>
                            <?php foreach ($roles_list as $r): ?>
                                <option value="<?= $r['id_role'] ?>" <?= (isset($edit_data['id_role']) && $edit_data['id_role'] == $r['id_role']) ? 'selected' : '' ?>>
                                    <?= e($r['nama_role']) ?> (<?= e($r['keterangan'] ?? '-') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Password <?= $aksi === 'edit' ? '(Kosongkan jika tidak ingin mengubah password)' : '<span class="text-danger">*</span>' ?></label>
                        <input type="password" class="form-control" name="password" <?= $aksi === 'tambah' ? 'required' : '' ?>>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="aktif" name="aktif" value="1" <?= (!isset($edit_data) || !empty($edit_data['aktif'])) ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-bold" for="aktif">Akun Aktif</label>
                    </div>

                    <div class="text-end">
                        <a href="pengguna.php" class="btn btn-light px-4 me-2">Batal</a>
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Simpan Pengguna</button>
                    </div>
                </form>
            </div>

        <!-- KONDISI TABEL DAFTAR PENGGUNA (INDEX) -->
        <?php else: ?>
            <div class="card border-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-custom">
                        <thead>
                            <tr>
                                <th class="ps-4" style="width: 60px;">No</th>
                                <th>Username & Nama</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Login Terakhir</th>
                                <th class="text-end pe-4" style="width: 120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pengguna_list)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-secondary">Belum ada data pengguna terdaftar.</td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($pengguna_list as $p): ?>
                                    <tr>
                                        <td class="ps-4"><?= $no++ ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($p['username']) ?></div>
                                            <div class="text-muted small"><?= e($p['nama_tampilan'] ?? '-') ?></div>
                                        </td>
                                        <td><?= e($p['email'] ?? '-') ?></td>
                                        <td><span class="badge bg-info text-dark"><?= e($p['nama_role']) ?></span></td>
                                        <td><span class="badge bg-<?= $p['aktif'] ? 'success' : 'secondary' ?>"><?= $p['aktif'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                                        <td class="small text-muted"><?= e($p['login_terakhir'] ?? 'Belum pernah') ?></td>
                                        <td class="text-end pe-4">
                                            <a href="pengguna.php?aksi=edit&id=<?= $p['id_pengguna'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                            <a href="pengguna.php?aksi=hapus&id=<?= $p['id_pengguna'] ?>&csrf_token=<?= csrf_token() ?>" class="btn btn-sm btn-outline-danger" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna <?= e($p['username']) ?>?');"><i class="bi bi-trash"></i></a>
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