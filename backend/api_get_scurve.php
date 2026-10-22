<?php
// ========================================================
// WYLCORE / RQ12 — api_get_scurve.php
// T6: CR -> Time Monitoring Contract Enforcement
// BAB XII §§ 12.5.7, 12.8.2 (Prioritas P1-13)
// ========================================================

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db_user.php';
require_once __DIR__ . '/auth_guard.php';

$currentUser = auth_require_role(['Admin', 'Project Manager', 'Site Engineer']);
$project_id = trim($_GET['project_id'] ?? '');

if (empty($project_id)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Project ID diperlukan."]);
    exit;
}

auth_require_project_access($pdo, $project_id, $currentUser);

try {
    // 1. Ambil data kurva S target vs realisasi
    $stmtCurve = $pdo->prepare("
        SELECT periode_ke, tanggal_target, rencana_kumulatif, realisasi_kumulatif 
        FROM project_scurve 
        WHERE project_id = ? 
        ORDER BY periode_ke ASC
    ");
    $stmtCurve->execute([$project_id]);
    $curveData = $stmtCurve->fetchAll(PDO::FETCH_ASSOC);

    // T6-03: Deteksi missing baseline atau noncomparable period
    $hasBaseline = !empty($curveData);
    $contractStatus = 'INDICATIVE_MONITORING';
    $periodWarning = null;

    if (!$hasBaseline) {
        $contractStatus = 'MISSING_BASELINE';
        $periodWarning = 'Basis data S-Curve belum memiliki baseline rencana untuk proyek ini [MISSING_BASELINE].';
    }

    // 2. Kalkulasi Dampak Waktu Indikatif dari Change Request yang disetujui
    // T6-04: HANYA yang berstatus APPROVED yang diagregasikan; REJECTED dan PENDING dikecualikan
    $stmtImpact = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CAST(timeImpact AS SIGNED)), 0) as approved_cr_time_impact_days,
            COUNT(*) as approved_cr_count
        FROM change_requests 
        WHERE projectArea = ? AND UPPER(status) = 'APPROVED'
    ");
    $stmtImpact->execute([$project_id]);
    $impactRow = $stmtImpact->fetch(PDO::FETCH_ASSOC);
    $indicativeTimeImpact = (int)($impactRow['approved_cr_time_impact_days'] ?? 0);

    // T6-01 & T6-02: Batas interpretasi saintifik (Tanpa CPM)
    // S-Curve memantau rencana vs aktual secara terpisah; timeImpact CR bersifat indikatif terhadap aktivitas lokal
    echo json_encode([
        "status" => "success",
        "contract_status" => $contractStatus, // INDICATIVE_MONITORING atau MISSING_BASELINE
        "project_id" => $project_id,
        "indicative_cr_time_impact_days" => $indicativeTimeImpact,
        "total_delay_days" => $indicativeTimeImpact, // backward compatibility
        "approved_cr_count" => (int)($impactRow['approved_cr_count'] ?? 0),
        "curve_data" => $curveData,
        "period_warning" => $periodWarning,
        "scientific_boundary" => [
            "cpm_attribution" => false,
            "interpretation_limit" => "Dampak waktu CR sebesar {$indicativeTimeImpact} hari merupakan dampak indikatif pada durasi aktivitas terdampak.",
            "cpm_disclaimer" => "Sistem tidak mengklaim penambahan durasi total proyek sebesar {$indicativeTimeImpact} hari tanpa pemodelan jaringan Critical Path Method (CPM) dan analisis float aktivitas [NO_CPM_ATTRIBUTION]."
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>