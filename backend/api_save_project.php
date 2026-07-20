<?php
// File: api_save_project.php
header('Content-Type: application/json');
require 'db_user.php';

// Ambil data JSON dari Javascript
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Data kosong"]);
    exit;
}

$project_id = $data['project_id'];
$project_name = $data['project_name'];
$status = $data['status'] ?? 'Pending';
$progress = $data['progress'] ?? 0;
$pm_names = $data['pm_tags']; // Array berisi nama-nama PM
$se_names = $data['se_tags']; // Array berisi nama-nama SE

try {
    $pdo->beginTransaction();

    // 1. Simpan atau Update data ke tabel projects
    $stmt = $pdo->prepare("INSERT INTO projects (project_id, project_name, status, progress) 
                           VALUES (?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE project_name=?, status=?, progress=?");
    $stmt->execute([$project_id, $project_name, $status, $progress, $project_name, $status, $progress]);

    // 2. Hapus assignment lama (agar tidak double saat edit)
    $stmtDelete = $pdo->prepare("DELETE FROM project_assignments WHERE project_id = ?");
    $stmtDelete->execute([$project_id]);

    // Fungsi internal untuk mencari user_id dari nama lengkap dan menyimpan ke tabel relasi
    function assignUsers($pdo, $namesArray, $role, $projectId) {
        $stmtFindUser = $pdo->prepare("SELECT id FROM users WHERE full_name = ? AND role = ? LIMIT 1");
        $stmtAssign = $pdo->prepare("INSERT INTO project_assignments (project_id, user_id, role_assigned) VALUES (?, ?, ?)");
        
        foreach ($namesArray as $name) {
            $stmtFindUser->execute([$name, $role]);
            $user = $stmtFindUser->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $stmtAssign->execute([$projectId, $user['id'], $role]);
            }
        }
    }

    // 3. Masukkan PM dan SE yang baru ke tabel relasi
    assignUsers($pdo, $pm_names, 'Project Manager', $project_id);
    assignUsers($pdo, $se_names, 'Site Engineer', $project_id);

    $pdo->commit();
    echo json_encode(["status" => "success", "message" => "Data proyek berhasil disimpan"]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => "Gagal menyimpan: " . $e->getMessage()]);
}
?>