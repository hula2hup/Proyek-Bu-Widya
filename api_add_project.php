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
$progress     = $data['progress'] ?? 0;

// 3. Tangkap data Tags sebagai ARRAY
// Sesuaikan key 'se_tags' dan 'pm_tags' dengan nama properti JSON yang dikirim dari JS frontend Anda
$se_names = isset($data['se_tags']) && is_array($data['se_tags']) ? $data['se_tags'] : [];
$pm_names = isset($data['pm_tags']) && is_array($data['pm_tags']) ? $data['pm_tags'] : [];

try {
    // Mulai proses database (Transaction) agar aman
    $pdo->beginTransaction();

    // 1. Masukkan data ke tabel utama `projects`
    $stmt = $pdo->prepare("INSERT INTO projects (project_id, project_name, status, progress) VALUES (?, ?, ?, ?)");
    $stmt->execute([$project_id, $project_name, $status, $progress]);

    // Siapkan Query pencari ID User (Asumsi isi tags adalah 'full_name', ubah ke 'username' jika tags isinya username)
    $stmtFindUser = $pdo->prepare("SELECT id FROM users WHERE full_name = ? LIMIT 1");
    
    // Siapkan Query untuk Insert ke tabel relasi
    $stmtAssign = $pdo->prepare("INSERT INTO project_assignments (project_id, user_id, role_assigned) VALUES (?, ?, ?)");

    // 2 & 3. Fungsi internal untuk melakukan looping pada array tags dan memasukkannya ke DB
    function assignMultipleUsers($stmtFind, $stmtAssign, $namesArray, $role, $projectId) {
        foreach ($namesArray as $name) {
            // Cari apakah user dengan nama tersebut ada di database
            $stmtFind->execute([$name]);
            $user = $stmtFind->fetch(PDO::FETCH_ASSOC);
            
            // Jika user ditemukan, masukkan ke tabel project_assignments
            if ($user) {
                $stmtAssign->execute([$projectId, $user['id'], $role]);
            }
        }
    }

    // Eksekusi perulangan untuk Site Engineer
    if (!empty($se_names)) {
        assignMultipleUsers($stmtFindUser, $stmtAssign, $se_names, 'Site Engineer', $project_id);
    }

    // Eksekusi perulangan untuk Project Manager
    if (!empty($pm_names)) {
        assignMultipleUsers($stmtFindUser, $stmtAssign, $pm_names, 'Project Manager', $project_id);
    }

    // Simpan semua perubahan
    $pdo->commit();

    echo json_encode(["status" => "success", "message" => "Proyek baru dan tim berhasil ditambahkan!"]);
} catch (PDOException $e) {
    // Jika gagal atau ID duplikat, batalkan semua proses
    $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => "Gagal menyimpan proyek: " . $e->getMessage()]);
}
?>