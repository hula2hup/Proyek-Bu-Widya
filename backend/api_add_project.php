<?php
// File: api_add_project.php
session_start();
header('Content-Type: application/json');
require 'db_user.php'; 

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

$projectId       = $data['id'];
$projectName     = $data['name'];
$projectStatus   = $data['status'] ?? 'Pending';
$progressPercent = $data['progress'] ?? 0;

// Tangkap data Tags sebagai ARRAY yang benar
$siteEngineers   = isset($data['se_tags']) && is_array($data['se_tags']) ? $data['se_tags'] : [];
$projectManagers = isset($data['pm_tags']) && is_array($data['pm_tags']) ? $data['pm_tags'] : [];

try {
    // Mulai proses database (Transaction) agar aman
    $pdo->beginTransaction();

    // 1. Masukkan data ke tabel utama `projects` (TANPA memasukkan variabel array langsung ke sini)
    $stmt = $pdo->prepare("INSERT INTO projects (project_id, project_name, status, progress) VALUES (?, ?, ?, ?)");
    $stmt->execute([$projectId, $projectName, $projectStatus, $progressPercent]);

    // 2. Siapkan query pencarian User (Fleksibel: Bisa mendeteksi berdasarkan Username ATAU Full Name)
    $stmtFindUser = $pdo->prepare("SELECT id FROM users WHERE username = ? OR full_name = ? LIMIT 1");
    
    // 3. Siapkan query insert untuk tabel relasi assignments
    $stmtAssign = $pdo->prepare("INSERT INTO project_assignments (project_id, user_id, role_assigned) VALUES (?, ?, ?)");

    // Fungsi internal untuk melakukan looping pada array tags secara aman
    function assignMultipleUsers($stmtFindUser, $stmtAssign, $namesArray, $role, $projectId) {
        foreach ($namesArray as $name) {
            $trimmedName = trim($name);
            if (empty($trimmedName)) continue;

            // Jalankan pencarian (Parameter dimasukkan dua kali untuk mengecek username dan full_name)
            $stmtFindUser->execute([$trimmedName, $trimmedName]);
            $user = $stmtFindUser->fetch(PDO::FETCH_ASSOC);
            
            // Jika user ditemukan di database, masukkan ke tabel project_assignments
            if ($user) {
                $stmtAssign->execute([$projectId, $user['id'], $role]);
            }
        }
    }

    // Eksekusi perulangan untuk Site Engineer dengan nama variabel yang BENAR
    if (!empty($siteEngineers)) {
        assignMultipleUsers($stmtFindUser, $stmtAssign, $siteEngineers, 'Site Engineer', $projectId);
    }

    // Eksekusi perulangan untuk Project Manager dengan nama variabel yang BENAR
    if (!empty($projectManagers)) {
        assignMultipleUsers($stmtFindUser, $stmtAssign, $projectManagers, 'Project Manager', $projectId);
    }

    // Simpan semua perubahan jika tidak ada error
    $pdo->commit();

    echo json_encode(["status" => "success", "message" => "Proyek baru dan tim berhasil ditambahkan!"]);
    exit;

} catch (PDOException $e) {
    // Jika ada yang gagal, batalkan semua data yang sempat masuk agar database tidak kotor
    $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => "Gagal menyimpan ke database: " . $e->getMessage()]);
    exit;
}
?>