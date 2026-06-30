<?php
header('Content-Type: application/json');
session_start();

// Validasi Akses: Izinkan Project Manager DAN Admin untuk mengakses data ini
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Project Manager', 'Admin'])) {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Silakan login."]);
    exit();
}

// Konfigurasi Database
$host = 'localhost';
$db   = 'db_data_proyek';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Matikan error ONLY_FULL_GROUP_BY secara dinamis untuk session ini
    $pdo->exec("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''))");
    
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Koneksi database gagal: " . $e->getMessage()]);
    exit();
}

// Ambil Project ID jika dikirim dari frontend (opsional untuk mendukung global/all projects view)
$projectId = $_GET['project_id'] ?? $_GET['projectId'] ?? '';

// Siapkan parameter array untuk PDO binding secara dinamis
$params = [];
if (!empty($projectId)) {
    $params['project_id'] = $projectId;
}

try {
    // 1. Hitung data "Under Review" (Status PENDING)
    $sqlReview = "SELECT COUNT(*) as total FROM change_requests " . (!empty($projectId) ? "WHERE projectArea = :project_id AND status = 'PENDING'" : "WHERE status = 'PENDING'");
    $stmtReview = $pdo->prepare($sqlReview);
    $stmtReview->execute($params);
    $underReviewCount = $stmtReview->fetch()['total'];

    // 2. Hitung data "Need Mitigation" (Status PENDING & Risk >= 7)
    $sqlMitigation = "SELECT COUNT(*) as total FROM change_requests " . (!empty($projectId) ? "WHERE projectArea = :project_id AND status = 'PENDING' AND CAST(risk AS UNSIGNED) >= 7" : "WHERE status = 'PENDING' AND CAST(risk AS UNSIGNED) >= 7");
    $stmtMitigation = $pdo->prepare($sqlMitigation);
    $stmtMitigation->execute($params);
    $needMitigationCount = $stmtMitigation->fetch()['total'];

    // 3. Hitung data "Approved" (Status APPROVED)
    $sqlApproved = "SELECT COUNT(*) as total FROM change_requests " . (!empty($projectId) ? "WHERE projectArea = :project_id AND status = 'APPROVED'" : "WHERE status = 'APPROVED'");
    $stmtApproved = $pdo->prepare($sqlApproved);
    $stmtApproved->execute($params);
    $approvedCount = $stmtApproved->fetch()['total'];

    // 4. Hitung data "Rejected" (Status REJECTED)
    $sqlRejected = "SELECT COUNT(*) as total FROM change_requests " . (!empty($projectId) ? "WHERE projectArea = :project_id AND status = 'REJECTED'" : "WHERE status = 'REJECTED'");
    $stmtRejected = $pdo->prepare($sqlRejected);
    $stmtRejected->execute($params);
    $rejectedCount = $stmtRejected->fetch()['total'];

    // 5. PERBAIKAN: Menambahkan kembali klausa FROM dan menghapus koma gantung
    $whereRecent = !empty($projectId) ? "WHERE cr.projectArea = :project_id" : "";
    $stmtRecent = $pdo->prepare("
        SELECT 
            cr.changeId, 
            MAX(cr.submittedBy) as submittedBy, 
            MAX(cr.changeDate) as changeDate, 
            MAX(cr.changeCategory) as changeCategory, 
            MAX(cr.status) as status, 
            MAX(cr.risk) as risk,
            MAX(cr.riskVariable) as riskVariable
        FROM change_requests cr
        $whereRecent
        GROUP BY cr.changeId
        ORDER BY MAX(cr.changeDate) DESC 
        LIMIT 5
    ");
    $stmtRecent->execute($params);
    $recentActivities = $stmtRecent->fetchAll();

    // 6. Ambil data Change Request yang berstatus PENDING (DENGAN INSIGHT AI)
    $wherePending = !empty($projectId) ? "WHERE cr.projectArea = :project_id AND UPPER(cr.status) = 'PENDING'" : "WHERE UPPER(cr.status) = 'PENDING'";
    $stmtPending = $pdo->prepare("
        SELECT 
            cr.changeId, 
            MAX(cr.changeCategory) as changeCategory, 
            MAX(cr.submittedBy) as submittedBy, 
            MAX(cr.changeDate) as changeDate,
            MAX(cr.risk) as risk,
            MAX(cr.riskVariable) as riskVariable,
            MAX(cr.description) as description,
            MAX(cr.wbsLevel4) as wbsLevel4,
            MAX(cr.wbsLevel5) as wbsLevel5,
            MAX(cr.wbsLevel6) as wbsLevel6,
            MAX(cr.costImpact) as costImpact,
            MAX(cr.timeImpact) as timeImpact,
            MAX(cr.status) as status,
            MAX(kr.insight) as insight,
            MAX(kr.saran) as saran,
            MAX(cr.bimObjectId) as bimObjectId
        FROM change_requests cr
        LEFT JOIN knowledge_repository kr 
            ON TRIM(cr.riskVariable) COLLATE utf8mb4_unicode_ci = TRIM(kr.risk_kode) COLLATE utf8mb4_unicode_ci
            AND SUBSTRING(TRIM(cr.wbsLevel5), 1, 7) COLLATE utf8mb4_unicode_ci = TRIM(kr.wbs_kode) COLLATE utf8mb4_unicode_ci
        $wherePending
        GROUP BY cr.changeId
        ORDER BY MAX(cr.changeDate) DESC
    ");
    $stmtPending->execute($params);
    $pendingRequests = $stmtPending->fetchAll();

    // Kembalikan semua data dalam satu paket JSON lengkap ke frontend
    echo json_encode([
        "status" => "success",
        "data" => [
            "under_review" => (int)$underReviewCount,
            "need_mitigation" => (int)$needMitigationCount,
            "approved" => (int)$approvedCount,          
            "rejected" => (int)$rejectedCount,          
            "recent_activities" => $recentActivities,
            "pending_requests" => $pendingRequests
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Query Error: " . $e->getMessage()]);
}
?>