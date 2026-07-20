<?php
session_start();
header('Content-Type: application/json');
require 'db_user.php'; 

// Proteksi Keamanan
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Sesi Anda bukan Admin."]);
    exit;
}

$project_id = $_GET['project_id'] ?? '';

try {
    // 1. Ambil data dasar Proyek (tanpa memanggil project_manager / site_engineer)
    $stmt = $pdo->prepare("SELECT project_id, project_name, status, progress FROM projects WHERE project_id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($project) {
        // 2. Ambil Tag Project Manager dari tabel project_assignments
        $stmtPM = $pdo->prepare("
            SELECT u.full_name 
            FROM project_assignments pa 
            JOIN users u ON pa.user_id = u.id 
            WHERE pa.project_id = ? AND pa.role_assigned = 'Project Manager'
        ");
        $stmtPM->execute([$project_id]);
        // Kembalikan dalam bentuk Array 1 Dimensi (contoh: ["Budi Santoso", "Andi"])
        $project['pm_tags'] = $stmtPM->fetchAll(PDO::FETCH_COLUMN) ?: [];

        // 3. Ambil Tag Site Engineer dari tabel project_assignments
        $stmtSE = $pdo->prepare("
            SELECT u.full_name 
            FROM project_assignments pa 
            JOIN users u ON pa.user_id = u.id 
            WHERE pa.project_id = ? AND pa.role_assigned = 'Site Engineer'
        ");
        $stmtSE->execute([$project_id]);
        $project['se_tags'] = $stmtSE->fetchAll(PDO::FETCH_COLUMN) ?: [];
        
        echo json_encode(["status" => "success", "data" => $project]);
    } else {
        echo json_encode(["status" => "error", "message" => "Proyek tidak ditemukan."]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Error DB: " . $e->getMessage()]);
}
?>