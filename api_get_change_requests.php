<?php
// File: api_get_change_requests.php
// API untuk mengambil Change Request berdasarkan project_id (untuk Admin)

session_start();
header('Content-Type: application/json');
require 'db_user.php';

// Pastikan user memiliki sesi aktif dan role Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Hanya Admin yang dapat mengakses."]);
    exit;
}

try {
    $projectId = $_GET['project_id'] ?? '';

    if (empty($projectId)) {
        // Jika tidak ada project_id, ambil SEMUA change requests
        $query = "
            SELECT 
                cr.*,
                cr.changeId as id,
                cr.projectArea as projectId,
                cr.description as `desc`,
                cr.submittedBy as sender,
                'Site Engineer' as role,
                cr.changeDate as waktuTs,
                cr.risk as risikoScore,
                kr.insight,
                kr.saran
            FROM change_requests cr
            LEFT JOIN knowledge_repository kr 
                ON TRIM(cr.riskVariable) COLLATE utf8mb4_unicode_ci = TRIM(kr.risk_kode) COLLATE utf8mb4_unicode_ci
                AND SUBSTRING(TRIM(cr.wbsLevel5), 1, 7) COLLATE utf8mb4_unicode_ci = TRIM(kr.wbs_kode) COLLATE utf8mb4_unicode_ci
            ORDER BY cr.changeDate DESC
        ";
        $stmt = $pdo->query($query);
    } else {
        // Jika ada project_id, ambil CR khusus project tersebut
        $query = "
            SELECT 
                cr.*,
                cr.changeId as id,
                cr.projectArea as projectId,
                cr.description as `desc`,
                cr.submittedBy as sender,
                'Site Engineer' as role,
                cr.changeDate as waktuTs,
                cr.risk as risikoScore,
                kr.insight,
                kr.saran
            FROM change_requests cr
            LEFT JOIN knowledge_repository kr 
                ON TRIM(cr.riskVariable) COLLATE utf8mb4_unicode_ci = TRIM(kr.risk_kode) COLLATE utf8mb4_unicode_ci
                AND SUBSTRING(TRIM(cr.wbsLevel5), 1, 7) COLLATE utf8mb4_unicode_ci = TRIM(kr.wbs_kode) COLLATE utf8mb4_unicode_ci
            WHERE cr.projectArea = :project_id
            ORDER BY cr.changeDate DESC
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['project_id' => $projectId]);
    }

    $changeRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Konversi waktuTs menjadi format timestamp untuk sorting dan tambahkan field saran default jika kosong
    foreach ($changeRequests as &$cr) {
        $cr['waktuTs'] = strtotime($cr['waktuTs']);
        $cr['risikoScore'] = (int)$cr['risikoScore'];

        $status = strtoupper(trim($cr['status'] ?? ''));
        if (!in_array($status, ['PENDING', 'APPROVED', 'REJECTED'], true)) {
            $status = 'PENDING';
        }
        $cr['status'] = $status;
        $cr['approvalDecision'] = strtolower($status);
        
        // Cek jika saran dari knowledge_repository kosong
        if (!isset($cr['saran']) || empty(trim($cr['saran']))) {
            $cr['saran'] = 'Tidak ada rekomendasi khusus di basis data AI untuk kombinasi risiko dan WBS ini. Diperlukan peninjauan mandiri secara saksama.';
        }
        
        // Cek jika insight dari knowledge_repository kosong
        if (!isset($cr['insight']) || empty(trim($cr['insight']))) {
            $cr['insight'] = 'Data insight tidak ditemukan.';
        }
    }

    echo json_encode([
        "status" => "success",
        "count" => count($changeRequests),
        "data" => $changeRequests
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
?>
