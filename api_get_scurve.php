<?php
// File: api_get_scurve.php
session_start();
header('Content-Type: application/json');
require 'db_user.php'; // Pastikan file pdo terhubung

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Akses ditolak."]);
    exit;
}

$project_id = $_GET['project_id'] ?? '';

if (empty($project_id)) {
    echo json_encode(["status" => "error", "message" => "Project ID diperlukan."]);
    exit;
}

try {
    if (($_SESSION['role'] ?? '') !== 'Admin') {
        $stmtAccess = $pdo->prepare("
            SELECT COUNT(*)
            FROM project_assignments
            WHERE project_id = ?
              AND user_id = ?
              AND role_assigned = ?
        ");
        $stmtAccess->execute([$project_id, $_SESSION['user_id'], $_SESSION['role']]);

        if ((int)$stmtAccess->fetchColumn() === 0) {
            echo json_encode(["status" => "error", "message" => "Project tidak ter-assign ke user ini."]);
            exit;
        }
    }

    // 1. Ambil data rencana vs realisasi berkala
    $stmtCurve = $pdo->prepare("
        SELECT periode_ke, tanggal_target, rencana_kumulatif, realisasi_kumulatif 
        FROM project_scurve 
        WHERE project_id = ? 
        ORDER BY periode_ke ASC
    ");
    $stmtCurve->execute([$project_id]);
    $curveData = $stmtCurve->fetchAll(PDO::FETCH_ASSOC);

    // 2. Kalkulasi Dampak Waktu akibat Perubahan (Change Request yang Approved)
    // Asumsi: Kolom timeImpact menyimpan angka jumlah hari keterlambatan (misal: 10, 14, dst)
    $stmtImpact = $pdo->prepare("
        SELECT SUM(CAST(timeImpact AS SIGNED)) as total_delay_days 
        FROM change_requests 
        WHERE projectArea = ? AND UPPER(status) = 'APPROVED'
    ");
    $stmtImpact->execute([$project_id]);
    $impactRow = $stmtImpact->fetch(PDO::FETCH_ASSOC);
    $totalDelay = (int)($impactRow['total_delay_days'] ?? 0);

    echo json_encode([
        "status" => "success",
        "project_id" => $project_id,
        "total_delay_days" => $totalDelay,
        "curve_data" => $curveData
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
