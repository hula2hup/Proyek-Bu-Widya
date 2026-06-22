<?php
// File: api_add_project.php
session_start();
header('Content-Type: application/json');
require 'db_user.php'; // Menggunakan koneksi database yang sudah ada

// 1. Verifikasi Keamanan: Hanya Admin yang boleh menambah proyek
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Hanya Admin yang dapat menambah proyek."]);
    exit;
}

// 2. Tangkap Data JSON dari Frontend (admin.html)
$data = json_decode(file_get_contents("php://input"), true);

// Pastikan data inti tidak kosong
if (empty($data['id']) || empty($data['name'])) {
    echo json_encode(["status" => "error", "message" => "ID Proyek dan Nama Proyek wajib diisi!"]);
    exit;
}

$project_id   = $data['id'];
$project_name = $data['name'];
$status       = $data['status'] ?? 'Pending';

// Asumsi frontend mengirimkan username untuk SE dan PM
$se_username  = $data['engineer'] ?? '';
$pm_username  = $data['manager'] ?? '';

try {
    // Mulai proses database (Transaction) agar aman
    $pdo->beginTransaction();

    // 1. Masukkan data ke tabel utama `projects`
    $stmt = $pdo->prepare("INSERT INTO projects (project_id, project_name, status) VALUES (?, ?, ?)");
    $stmt->execute([$project_id, $project_name, $status]);

    // 2. Masukkan relasi untuk Site Engineer ke tabel `project_assignments` (Jika Dipilih)
    if (!empty($se_username)) {
        $stmtSE = $pdo->prepare("
            INSERT INTO project_assignments (project_id, user_id, role_assigned) 
            SELECT ?, id, 'Site Engineer' FROM users WHERE username = ? LIMIT 1
        ");
        $stmtSE->execute([$project_id, $se_username]);
    }

    // 3. Masukkan relasi untuk Project Manager ke tabel `project_assignments` (Jika Dipilih)
    if (!empty($pm_username)) {
        $stmtPM = $pdo->prepare("
            INSERT INTO project_assignments (project_id, user_id, role_assigned) 
            SELECT ?, id, 'Project Manager' FROM users WHERE username = ? LIMIT 1
        ");
        $stmtPM->execute([$project_id, $pm_username]);
    }

    // Simpan semua perubahan
    $pdo->commit();

    echo json_encode(["status" => "success", "message" => "Proyek baru berhasil ditambahkan!"]);
} catch (PDOException $e) {
    // Jika gagal atau ID duplikat, batalkan semua proses
    $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => "Gagal menyimpan proyek (ID mungkin sudah terpakai): " . $e->getMessage()]);
}
?>