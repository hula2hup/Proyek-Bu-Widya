<?php
// File: api_get_projects.php
session_start();
header('Content-Type: application/json');
require 'db_user.php';

// Pastikan user memiliki sesi aktif
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Silakan login."]);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    // KITA BUAT BASE QUERY DI SINI DENGAN PENYESUAIAN NAMA KOLOM
    $baseQuery = "
        SELECT 
            p.*,
            -- Mengambil nama berdasarkan role_assigned di tabel project_assignments
            (SELECT u.full_name FROM project_assignments pa 
             JOIN users u ON pa.user_id = u.id 
             WHERE pa.project_id = p.project_id AND pa.role_assigned = 'Site Engineer' LIMIT 1) AS site_engineer_name,
             
            (SELECT u.full_name FROM project_assignments pa 
             JOIN users u ON pa.user_id = u.id 
             WHERE pa.project_id = p.project_id AND pa.role_assigned = 'Project Manager' LIMIT 1) AS project_manager_name,
            
            -- Menghitung KPI dari tabel change_requests (menggunakan projectId dan 'Pending')
            (SELECT COUNT(*) FROM change_requests cr WHERE cr.projectArea = p.project_id) AS total_changes,
            (SELECT COUNT(*) FROM change_requests cr WHERE cr.projectArea = p.project_id AND cr.status = 'Pending') AS open_changes,
            (SELECT COUNT(*) FROM change_requests cr WHERE cr.projectArea = p.project_id AND cr.status = 'Approved') AS approved_changes
        FROM projects p
    ";

    if ($role === 'Admin') {
        // Admin dapat melihat SEMUA proyek
        $query = $baseQuery . " ORDER BY p.project_name ASC";
        
        $stmt = $pdo->query($query);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // PM dan SE HANYA melihat proyek yang di-assign kepada mereka
        $query = $baseQuery . " 
            JOIN project_assignments pa_filter ON p.project_id = pa_filter.project_id
            WHERE pa_filter.user_id = ?
            ORDER BY p.project_name ASC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Kembalikan data dalam bentuk JSON
    echo json_encode([
        "status" => "success", 
        "data" => $projects,
        "user_role" => $role,
        "user_name" => $_SESSION['full_name']
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Gagal mengambil data: " . $e->getMessage()]);
}
?>