<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../koneksi.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Koneksi database gagal."]);
    exit;
}
$conn->set_charset("utf8mb4");

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

function getJsonInput() {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (is_array($input)) {
        return $input;
    }
    return $_POST;
}

try {
    switch ($method) {
        case 'GET':
            if ($action === 'master') {
                $master_data = [];
                $res_j = $conn->query("SELECT id_jabatan, nama_jabatan FROM jabatan_fungsional WHERE aktif = 1");
                $master_data['jabatan_fungsional'] = $res_j ? $res_j->fetch_all(MYSQLI_ASSOC) : [];

                $res_p = $conn->query("SELECT id_pendidikan, jenjang FROM pendidikan ORDER BY urutan ASC");
                $master_data['pendidikan'] = $res_p ? $res_p->fetch_all(MYSQLI_ASSOC) : [];

                $res_s = $conn->query("SELECT id_status_kepegawaian, status_pegawai, ikatan_kerja FROM status_kepegawaian");
                $master_data['status_kepegawaian'] = $res_s ? $res_s->fetch_all(MYSQLI_ASSOC) : [];

                $res_pn = $conn->query("SELECT id_penempatan, universitas, fakultas, program_studi FROM penempatan WHERE aktif = 1");
                $master_data['penempatan'] = $res_pn ? $res_pn->fetch_all(MYSQLI_ASSOC) : [];

                http_response_code(200);
                echo json_encode(["status" => true, "message" => "Data master berhasil diambil.", "data" => $master_data], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($id > 0) {
                $stmt = $conn->prepare(
                    "SELECT d.*, j.nama_jabatan, k.nama_pangkat, k.golongan, k.ruang, 
                            pd.jenjang, ri.nama_rumpun, sk.status_pegawai, sk.ikatan_kerja, 
                            pn.universitas, pn.fakultas, pn.program_studi 
                     FROM dosen d
                     LEFT JOIN jabatan_fungsional j ON d.id_jabatan = j.id_jabatan
                     LEFT JOIN kepangkatan k ON d.id_kepangkatan = k.id_kepangkatan
                     LEFT JOIN pendidikan pd ON d.id_pendidikan = pd.id_pendidikan
                     LEFT JOIN rumpun_ilmu ri ON d.id_rumpun_ilmu = ri.id_rumpun_ilmu
                     LEFT JOIN status_kepegawaian sk ON d.id_status_kepegawaian = sk.id_status_kepegawaian
                     LEFT JOIN penempatan pn ON d.id_penempatan = pn.id_penempatan
                     WHERE d.id_dosen = ? LIMIT 1"
                );
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $dosen = $result ? $result->fetch_assoc() : null;
                $stmt->close();

                if ($dosen) {
                    http_response_code(200);
                    echo json_encode(["status" => true, "message" => "Detail dosen ditemukan.", "data" => $dosen], JSON_UNESCAPED_UNICODE);
                } else {
                    http_response_code(404);
                    echo json_encode(["status" => false, "message" => "Data dosen tidak ditemukan."]);
                }
                exit;
            }

            $sql = "SELECT d.id_dosen, d.nomor_registrasi, d.nuptk, d.nama, d.nip, 
                           d.status_serdos, d.status_aktivitas, d.keterangan,
                           j.nama_jabatan, k.nama_pangkat, k.golongan, 
                           pd.jenjang AS pendidikan_terakhir, ri.nama_rumpun, 
                           sk.status_pegawai, sk.ikatan_kerja, 
                           pn.universitas, pn.fakultas, pn.program_studi 
                    FROM dosen d
                    LEFT JOIN jabatan_fungsional j ON d.id_jabatan = j.id_jabatan
                    LEFT JOIN kepangkatan k ON d.id_kepangkatan = k.id_kepangkatan
                    LEFT JOIN pendidikan pd ON d.id_pendidikan = pd.id_pendidikan
                    LEFT JOIN rumpun_ilmu ri ON d.id_rumpun_ilmu = ri.id_rumpun_ilmu
                    LEFT JOIN status_kepegawaian sk ON d.id_status_kepegawaian = sk.id_status_kepegawaian
                    LEFT JOIN penempatan pn ON d.id_penempatan = pn.id_penempatan
                    ORDER BY d.nama ASC";
            $result = $conn->query($sql);
            $dosen_list = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

            http_response_code(200);
            echo json_encode(["status" => true, "message" => "Daftar dosen berhasil diambil.", "total" => count($dosen_list), "data" => $dosen_list], JSON_UNESCAPED_UNICODE);
            break;

        case 'POST':
            $data = getJsonInput();
            $nama = trim($data['nama'] ?? '');
            
            if ($nama === '') {
                http_response_code(400);
                echo json_encode(["status" => false, "message" => "Nama dosen wajib diisi."]);
                exit;
            }

            $nomor_registrasi = trim($data['nomor_registrasi'] ?? '') !== '' ? "'" . $conn->real_escape_string(trim($data['nomor_registrasi'])) . "'" : "NULL";
            $nuptk = trim($data['nuptk'] ?? '') !== '' ? "'" . $conn->real_escape_string(trim($data['nuptk'])) . "'" : "NULL";
            $nip = trim($data['nip'] ?? '') !== '' ? "'" . $conn->real_escape_string(trim($data['nip'])) . "'" : "NULL";
            $nama_esc = $conn->real_escape_string($nama);

            $id_jabatan = !empty($data['id_jabatan']) ? (int)$data['id_jabatan'] : "NULL";
            $id_kepangkatan = !empty($data['id_kepangkatan']) ? (int)$data['id_kepangkatan'] : "NULL";
            $id_pendidikan = !empty($data['id_pendidikan']) ? (int)$data['id_pendidikan'] : "NULL";
            $id_rumpun_ilmu = !empty($data['id_rumpun_ilmu']) ? (int)$data['id_rumpun_ilmu'] : "NULL";
            $id_status_kepegawaian = !empty($data['id_status_kepegawaian']) ? (int)$data['id_status_kepegawaian'] : "NULL";
            $id_penempatan = !empty($data['id_penempatan']) ? (int)$data['id_penempatan'] : "NULL";

            $allowed_serdos = ['Sudah', 'Belum', 'Tidak Berlaku', 'Belum Diketahui'];
            $input_serdos = trim($data['status_serdos'] ?? 'Belum Diketahui');
            $status_serdos = $conn->real_escape_string(in_array($input_serdos, $allowed_serdos, true) ? $input_serdos : 'Belum Diketahui');

            $allowed_aktivitas = ['Aktif', 'Tidak Aktif', 'Cuti', 'Pensiun', 'Belum Diketahui'];
            $input_aktivitas = trim($data['status_aktivitas'] ?? 'Aktif');
            $status_aktivitas = $conn->real_escape_string(in_array($input_aktivitas, $allowed_aktivitas, true) ? $input_aktivitas : 'Aktif');

            $keterangan = trim($data['keterangan'] ?? '') !== '' ? "'" . $conn->real_escape_string(trim($data['keterangan'])) . "'" : "NULL";

            $query = "INSERT INTO dosen (nomor_registrasi, nuptk, nama, nip, id_jabatan, id_kepangkatan, id_pendidikan, id_rumpun_ilmu, id_status_kepegawaian, id_penempatan, status_serdos, status_aktivitas, keterangan) 
                      VALUES ($nomor_registrasi, $nuptk, '$nama_esc', $nip, $id_jabatan, $id_kepangkatan, $id_pendidikan, $id_rumpun_ilmu, $id_status_kepegawaian, $id_penempatan, '$status_serdos', '$status_aktivitas', $keterangan)";

            if ($conn->query($query)) {
                $new_id = $conn->insert_id;
                http_response_code(201);
                echo json_encode(["status" => true, "message" => "Data dosen berhasil ditambahkan.", "id_dosen" => $new_id], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(400);
                echo json_encode(["status" => false, "message" => "Gagal menyimpan data: " . $conn->error]);
            }
            break;

        case 'PUT':
        case 'PATCH':
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(["status" => false, "message" => "ID Dosen diperlukan untuk pembaruan data."]);
                exit;
            }

            $stmt_old = $conn->prepare("SELECT * FROM dosen WHERE id_dosen = ?");
            $stmt_old->bind_param('i', $id);
            $stmt_old->execute();
            $old_data = $stmt_old->get_result()->fetch_assoc();
            $stmt_old->close();

            if (!$old_data) {
                http_response_code(404);
                echo json_encode(["status" => false, "message" => "Data dosen tidak ditemukan."]);
                exit;
            }

            $data = getJsonInput();
            $nama = trim($data['nama'] ?? $old_data['nama']);
            $nama_esc = $conn->real_escape_string($nama);

            $nomor_registrasi = array_key_exists('nomor_registrasi', $data) ? (trim($data['nomor_registrasi']) !== '' ? "'" . $conn->real_escape_string(trim($data['nomor_registrasi'])) . "'" : "NULL") : ($old_data['nomor_registrasi'] !== null ? "'" . $conn->real_escape_string($old_data['nomor_registrasi']) . "'" : "NULL");
            $nuptk = array_key_exists('nuptk', $data) ? (trim($data['nuptk']) !== '' ? "'" . $conn->real_escape_string(trim($data['nuptk'])) . "'" : "NULL") : ($old_data['nuptk'] !== null ? "'" . $conn->real_escape_string($old_data['nuptk']) . "'" : "NULL");
            $nip = array_key_exists('nip', $data) ? (trim($data['nip']) !== '' ? "'" . $conn->real_escape_string(trim($data['nip'])) . "'" : "NULL") : ($old_data['nip'] !== null ? "'" . $conn->real_escape_string($old_data['nip']) . "'" : "NULL");
            
            $id_jabatan = array_key_exists('id_jabatan', $data) ? (!empty($data['id_jabatan']) ? (int)$data['id_jabatan'] : "NULL") : ($old_data['id_jabatan'] !== null ? (int)$old_data['id_jabatan'] : "NULL");
            $id_kepangkatan = array_key_exists('id_kepangkatan', $data) ? (!empty($data['id_kepangkatan']) ? (int)$data['id_kepangkatan'] : "NULL") : ($old_data['id_kepangkatan'] !== null ? (int)$old_data['id_kepangkatan'] : "NULL");
            $id_pendidikan = array_key_exists('id_pendidikan', $data) ? (!empty($data['id_pendidikan']) ? (int)$data['id_pendidikan'] : "NULL") : ($old_data['id_pendidikan'] !== null ? (int)$old_data['id_pendidikan'] : "NULL");
            $id_rumpun_ilmu = array_key_exists('id_rumpun_ilmu', $data) ? (!empty($data['id_rumpun_ilmu']) ? (int)$data['id_rumpun_ilmu'] : "NULL") : ($old_data['id_rumpun_ilmu'] !== null ? (int)$old_data['id_rumpun_ilmu'] : "NULL");
            $id_status_kepegawaian = array_key_exists('id_status_kepegawaian', $data) ? (!empty($data['id_status_kepegawaian']) ? (int)$data['id_status_kepegawaian'] : "NULL") : ($old_data['id_status_kepegawaian'] !== null ? (int)$old_data['id_status_kepegawaian'] : "NULL");
            $id_penempatan = array_key_exists('id_penempatan', $data) ? (!empty($data['id_penempatan']) ? (int)$data['id_penempatan'] : "NULL") : ($old_data['id_penempatan'] !== null ? (int)$old_data['id_penempatan'] : "NULL");

            $allowed_serdos = ['Sudah', 'Belum', 'Tidak Berlaku', 'Belum Diketahui'];
            $input_serdos = trim($data['status_serdos'] ?? $old_data['status_serdos']);
            $status_serdos = $conn->real_escape_string(in_array($input_serdos, $allowed_serdos, true) ? $input_serdos : 'Belum Diketahui');

            $allowed_aktivitas = ['Aktif', 'Tidak Aktif', 'Cuti', 'Pensiun', 'Belum Diketahui'];
            $input_aktivitas = trim($data['status_aktivitas'] ?? $old_data['status_aktivitas']);
            $status_aktivitas = $conn->real_escape_string(in_array($input_aktivitas, $allowed_aktivitas, true) ? $input_aktivitas : 'Aktif');

            $keterangan = array_key_exists('keterangan', $data) ? (trim($data['keterangan']) !== '' ? "'" . $conn->real_escape_string(trim($data['keterangan'])) . "'" : "NULL") : ($old_data['keterangan'] !== null ? "'" . $conn->real_escape_string($old_data['keterangan']) . "'" : "NULL");

            $query = "UPDATE dosen SET 
                        nomor_registrasi = $nomor_registrasi, 
                        nuptk = $nuptk, 
                        nama = '$nama_esc', 
                        nip = $nip, 
                        id_jabatan = $id_jabatan, 
                        id_kepangkatan = $id_kepangkatan, 
                        id_pendidikan = $id_pendidikan, 
                        id_rumpun_ilmu = $id_rumpun_ilmu, 
                        id_status_kepegawaian = $id_status_kepegawaian, 
                        id_penempatan = $id_penempatan, 
                        status_serdos = '$status_serdos', 
                        status_aktivitas = '$status_aktivitas', 
                        keterangan = $keterangan 
                      WHERE id_dosen = $id";

            if ($conn->query($query)) {
                http_response_code(200);
                echo json_encode(["status" => true, "message" => "Data dosen berhasil diperbarui."], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(400);
                echo json_encode(["status" => false, "message" => "Gagal memperbarui data: " . $conn->error]);
            }
            break;

        case 'DELETE':
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(["status" => false, "message" => "ID Dosen diperlukan untuk menghapus data."]);
                exit;
            }

            if ($id === 1) {
                http_response_code(403);
                echo json_encode(["status" => false, "message" => "Aksi ditolak: Data dosen ID 1 dilindungi dan tidak boleh dihapus."]);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM dosen WHERE id_dosen = ?");
            $stmt->bind_param('i', $id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    http_response_code(200);
                    echo json_encode(["status" => true, "message" => "Data dosen berhasil dihapus."], JSON_UNESCAPED_UNICODE);
                } else {
                    http_response_code(404);
                    echo json_encode(["status" => false, "message" => "Data dosen dengan ID tersebut tidak ditemukan."]);
                }
            } else {
                http_response_code(400);
                echo json_encode(["status" => false, "message" => "Gagal menghapus data: " . $stmt->error]);
            }
            $stmt->close();
            break;

        default:
            http_response_code(405);
            echo json_encode(["status" => false, "message" => "Metode HTTP tidak diizinkan."]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Terjadi kesalahan internal pada server."]);
}
?>