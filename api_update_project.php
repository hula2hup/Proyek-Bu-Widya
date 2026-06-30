<?php
session_start();
header('Content-Type: application/json');
require 'db_user.php';

// Proteksi Keamanan
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak."]);
    exit;
}

// Tangkap Payload JSON
$input = json_decode(file_get_contents('php://input'), true);

if ($input) {
    $id = $input['id'] ?? '';
    $name = $input['name'] ?? '';
    $status = $input['status'] ?? 'Pending';
    $progress = $input['progress'] ?? 0;
    
    $pm_tags = $input['pm_tags'] ?? []; // Array berisi nama PM dari HTML
    $se_tags = $input['se_tags'] ?? []; // Array berisi nama SE dari HTML

    try {
        // Mulai Transaksi agar aman
        $pdo->beginTransaction();

        // 1. UPDATE DATA UTAMA KE TABEL PROJECTS
        $stmt = $pdo->prepare("UPDATE projects SET project_name = ?, status = ?, progress = ? WHERE project_id = ?");
        $stmt->execute([$name, $status, $progress, $id]);

        // 2. BERSIHKAN ALOKASI LAMA DI TABEL ASSIGNMENTS
        // Hapus PM & SE lama untuk proyek ini, lalu kita ganti dengan yang baru dari Tags
        $stmtDelete = $pdo->prepare("DELETE FROM project_assignments WHERE project_id = ?");
        $stmtDelete->execute([$id]);

        // 3. INSERT ALOKASI PM BARU BERDASARKAN TAGS
        if (!empty($pm_tags)) {
            // Mencari user_id berdasarkan full_name yang diketik
            $stmtInsertPM = $pdo->prepare("
                INSERT INTO project_assignments (project_id, user_id, role_assigned) 
                SELECT ?, id, 'Project Manager' FROM users WHERE full_name = ? LIMIT 1
            ");
            foreach ($pm_tags as $pm_name) {
                $stmtInsertPM->execute([$id, $pm_name]);
            }
        }

        // 4. INSERT ALOKASI SE BARU BERDASARKAN TAGS
        if (!empty($se_tags)) {
            $stmtInsertSE = $pdo->prepare("
                INSERT INTO project_assignments (project_id, user_id, role_assigned) 
                SELECT ?, id, 'Site Engineer' FROM users WHERE full_name = ? LIMIT 1
            ");
            foreach ($se_tags as $se_name) {
                $stmtInsertSE->execute([$id, $se_name]);
            }
        }

        // Simpan semua perubahan secara permanen
        $pdo->commit();

        echo json_encode(["status" => "success", "message" => "Data proyek beserta alokasi anggota berhasil diperbarui!"]);
    } catch (Exception $e) {
        // Batalkan jika ada yang gagal
        $pdo->rollBack();
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Payload data tidak valid."]);
}
?>