<?php
header('Content-Type: application/json');
session_start();

// Perubahan di sini: Izinkan Project Manager DAN Admin untuk mengakses data ini
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
    
    // TAMBAHKAN BARIS INI UNTUK MEMATIKAN ERROR FULL_GROUP_BY
    $pdo->exec("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''))");
    
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Koneksi database gagal: " . $e->getMessage()]);
    exit();
}

$projectId = $_GET['project_id'] ?? '';

if (empty($projectId)) {
    echo json_encode(["status" => "error", "message" => "Project ID diperlukan."]);
    exit();
}

try {
    // 1. Hitung data "Under Review" (Status PENDING)
    $stmtReview = $pdo->prepare("SELECT COUNT(*) as total FROM change_requests WHERE projectArea = :project_id AND status = 'PENDING'");
    $stmtReview->execute(['project_id' => $projectId]);
    $underReviewCount = $stmtReview->fetch()['total'];

    // 2. Hitung data "Need Mitigation" (Status PENDING & Risk >= 7)
    $stmtMitigation = $pdo->prepare("SELECT COUNT(*) as total FROM change_requests WHERE projectArea = :project_id AND status = 'PENDING' AND CAST(risk AS UNSIGNED) >= 7");
    $stmtMitigation->execute(['project_id' => $projectId]);
    $needMitigationCount = $stmtMitigation->fetch()['total'];

    // 3. Hitung data "Approved" (Status APPROVED)
    $stmtApproved = $pdo->prepare("SELECT COUNT(*) as total FROM change_requests WHERE projectArea = :project_id AND status = 'APPROVED'");
    $stmtApproved->execute(['project_id' => $projectId]);
    $approvedCount = $stmtApproved->fetch()['total'];

    // 4. Hitung data "Rejected" (Status REJECTED)
    $stmtRejected = $pdo->prepare("SELECT COUNT(*) as total FROM change_requests WHERE projectArea = :project_id AND status = 'REJECTED'");
    $stmtRejected->execute(['project_id' => $projectId]);
    $rejectedCount = $stmtRejected->fetch()['total'];

    // 5. PERBAIKAN QUERY: Sesuai standar ONLY_FULL_GROUP_BY untuk Recent Activities
    $stmtRecent = $pdo->prepare("
        SELECT 
            cr.changeId, 
            MAX(cr.submittedBy) as submittedBy, 
            MAX(cr.changeDate) as changeDate, 
            MAX(cr.changeCategory) as changeCategory, 
            MAX(cr.status) as status, 
            MAX(cr.risk) as risk,
            MAX(cr.riskVariable) as riskVariable,
            MAX(cr.wbsLevel4) as wbsLevel4,
            MAX(cr.wbsLevel5) as wbsLevel5,
            MAX(cr.wbsLevel6) as wbsLevel6,
            MAX(kr.risk_kategori) as risk_kategori,
            MAX(kr.risk_nama) as risk_nama,
            MAX(kr.insight) as insight,
            MAX(kr.saran) as saran,
            MAX(cr.bimObjectId) as bimObjectId
        FROM change_requests cr
        LEFT JOIN knowledge_repository kr 
            ON TRIM(cr.riskVariable) COLLATE utf8mb4_unicode_ci = TRIM(kr.risk_kode) COLLATE utf8mb4_unicode_ci
            AND SUBSTRING(TRIM(cr.wbsLevel5), 1, 7) COLLATE utf8mb4_unicode_ci = TRIM(kr.wbs_kode) COLLATE utf8mb4_unicode_ci
        WHERE cr.projectArea = :project_id 
        GROUP BY cr.changeId
        ORDER BY MAX(cr.changeDate) DESC 
        LIMIT 5
    ");
    $stmtRecent->execute(['project_id' => $projectId]);
    $recentActivities = $stmtRecent->fetchAll();

    // 6. 🆕 TAMBAHAN BARU: Ambil data Change Request yang berstatus PENDING (DENGAN INSIGHT AI)
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
        WHERE cr.projectArea = :project_id AND UPPER(cr.status) = 'PENDING'
        GROUP BY cr.changeId
        ORDER BY MAX(cr.changeDate) DESC
    ");
    $stmtPending->execute(['project_id' => $projectId]);
    $pendingRequests = $stmtPending->fetchAll();

    // Kembalikan semua data dalam satu paket JSON lengkap
    echo json_encode([
        "status" => "success",
        "data" => [
            "under_review" => (int)$underReviewCount,
            "need_mitigation" => (int)$needMitigationCount,
            "approved" => (int)$approvedCount,          
            "rejected" => (int)$rejectedCount,          
            "recent_activities" => $recentActivities,
            "pending_requests" => $pendingRequests // <--- Data tabel Review & Approval disisipkan di sini
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Query Error: " . $e->getMessage()]);
}
?>