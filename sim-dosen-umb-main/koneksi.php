<?php
// koneksi.php
// Koneksi database SIM Dosen UMB

mysqli_report(MYSQLI_REPORT_OFF);

$host     = "sql106.infinityfree.com";
$username = "if0_42987834";
$password = "Rian1234Rian";
$database = "if0_42987834_sim_dosen_umb";

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);

if ($conn->connect_error) {
    error_log("Koneksi SIM Dosen gagal: " . $conn->connect_error);
    http_response_code(500);
    exit("Koneksi database gagal. Periksa konfigurasi database.");
}

$conn->set_charset("utf8mb4");
?>