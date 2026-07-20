<?php
header('Content-Type: application/json');
session_start();

// Validasi Akses: Izinkan Project Manager DAN Admin untuk mengakses data ini
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Project Manager', 'Admin'])) {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Silakan login."]);
    exit();
}

require_once __DIR__ . '/db_user.php';

function ensureProjectAccess(PDO $pdo, string $projectId): void {
    if ($projectId === '' || $_SESSION['role'] === 'Admin') {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM project_assignments 
        WHERE project_id = :project_id 
          AND user_id = :user_id 
          AND role_assigned = :role
    ");
    $stmt->execute([
        'project_id' => $projectId,
        'user_id' => $_SESSION['user_id'],
        'role' => $_SESSION['role']
    ]);

    if ((int)$stmt->fetchColumn() === 0) {
        echo json_encode(["status" => "error", "message" => "Project tidak ter-assign ke user ini."]);
        exit();
    }
}

function adminDashboardTableExists($pdo, $tableName) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

// Ambil Project ID jika dikirim dari frontend (opsional untuk mendukung global/all projects view)
$projectId = $_GET['project_id'] ?? $_GET['projectId'] ?? '';
ensureProjectAccess($pdo, $projectId);

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
            MAX(cr.projectArea) as projectArea,
            MAX(cr.wbsLevel4) as wbsLevel4,
            MAX(cr.wbsLevel5) as wbsLevel5,
            MAX(cr.wbsLevel6) as wbsLevel6,
            MAX(cr.riskCategory) as riskCategory,
            MAX(cr.riskVariable) as riskVariable,
            MAX(cr.riskDescription) as riskDescription,
            MAX(kr.risk_kategori) as risk_kategori,
            MAX(kr.risk_nama) as risk_nama,
            MAX(kr.insight) as insight,
            MAX(kr.saran) as saran
        FROM change_requests cr
        LEFT JOIN knowledge_repository kr 
            ON TRIM(cr.riskVariable) COLLATE utf8mb4_unicode_ci = TRIM(kr.risk_kode) COLLATE utf8mb4_unicode_ci
            AND SUBSTRING(TRIM(cr.wbsLevel5), 1, 7) COLLATE utf8mb4_unicode_ci = TRIM(kr.wbs_kode) COLLATE utf8mb4_unicode_ci
        $whereRecent
        GROUP BY cr.changeId
        ORDER BY MAX(cr.changeDate) DESC
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
            MAX(cr.projectArea) as projectArea,
            MAX(cr.riskVariable) as riskVariable,
            MAX(cr.riskCategory) as riskCategory,
            MAX(cr.riskDescription) as riskDescription,
            MAX(cr.description) as description,
            MAX(cr.wbsLevel4) as wbsLevel4,
            MAX(cr.wbsLevel5) as wbsLevel5,
            MAX(cr.wbsLevel6) as wbsLevel6,
            MAX(cr.costImpact) as costImpact,
            MAX(cr.timeImpact) as timeImpact,
            MAX(cr.status) as status,
            MAX(kr.risk_kategori) as risk_kategori,
            MAX(kr.risk_nama) as risk_nama,
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

    // 7. Distribusi klasifikasi risiko seluruh Change Request pada proyek aktif
    $whereAllProject = !empty($projectId) ? "WHERE projectArea = :project_id" : "";
    $stmtRiskDistribution = $pdo->prepare("
        SELECT
            SUM(CASE WHEN CAST(risk AS UNSIGNED) >= 9 THEN 1 ELSE 0 END) AS kritis,
            SUM(CASE WHEN CAST(risk AS UNSIGNED) BETWEEN 7 AND 8 THEN 1 ELSE 0 END) AS tinggi,
            SUM(CASE WHEN CAST(risk AS UNSIGNED) BETWEEN 5 AND 6 THEN 1 ELSE 0 END) AS sedang,
            SUM(CASE WHEN CAST(risk AS UNSIGNED) BETWEEN 3 AND 4 THEN 1 ELSE 0 END) AS rendah,
            SUM(CASE WHEN CAST(risk AS UNSIGNED) BETWEEN 1 AND 2 THEN 1 ELSE 0 END) AS aman,
            COUNT(*) AS total
        FROM change_requests
        $whereAllProject
    ");
    $stmtRiskDistribution->execute($params);
    $riskDistribution = $stmtRiskDistribution->fetch() ?: [];

    // 8. Rekap kode WBS terdampak, cukup kode singkat agar mudah discan di dashboard
    $stmtWbsImpacted = $pdo->prepare("
        SELECT
            SUBSTRING_INDEX(TRIM(COALESCE(NULLIF(wbsLevel5, ''), wbsLevel4)), ' ', 1) AS wbs_code,
            MAX(wbsLevel4) AS wbsLevel4,
            MAX(wbsLevel5) AS wbsLevel5,
            MAX(wbsLevel6) AS wbsLevel6,
            COUNT(*) AS total
        FROM change_requests
        $whereAllProject
        GROUP BY wbs_code
        HAVING wbs_code IS NOT NULL AND wbs_code <> ''
        ORDER BY total DESC, wbs_code ASC
        LIMIT 12
    ");
    $stmtWbsImpacted->execute($params);
    $wbsImpacted = $stmtWbsImpacted->fetchAll();

    // 9. Ringkasan adendum/approved impact sebagai indikator perubahan kontraktual
    $stmtAddendum = $pdo->prepare("
        SELECT
            COUNT(*) AS approved_changes,
            SUM(CASE WHEN COALESCE(CAST(timeImpact AS SIGNED), 0) > 0 OR COALESCE(CAST(costImpact AS DECIMAL(18,2)), 0) > 0 THEN 1 ELSE 0 END) AS addendum_candidates,
            COALESCE(SUM(CAST(timeImpact AS SIGNED)), 0) AS total_time_impact,
            COALESCE(SUM(CAST(costImpact AS DECIMAL(18,2))), 0) AS total_cost_impact
        FROM change_requests
        " . (!empty($projectId) ? "WHERE projectArea = :project_id AND UPPER(status) = 'APPROVED'" : "WHERE UPPER(status) = 'APPROVED'") . "
    ");
    $stmtAddendum->execute($params);
    $addendumSummary = $stmtAddendum->fetch() ?: [];

    // 10. Dataset visual risiko: matriks 5x5 dan tornado biaya/waktu
    $stmtRiskVisual = $pdo->prepare("
        SELECT
            cr.changeId,
            MAX(cr.projectArea) AS projectArea,
            MAX(cr.risk) AS risk,
            MAX(cr.riskVariable) AS riskVariable,
            MAX(cr.riskCategory) AS riskCategory,
            MAX(cr.riskDescription) AS riskDescription,
            MAX(cr.costImpact) AS costImpact,
            MAX(cr.timeImpact) AS timeImpact,
            MAX(cr.status) AS status,
            MAX(kr.risk_nama) AS risk_nama,
            MAX(kr.risk_kategori) AS risk_kategori
        FROM change_requests cr
        LEFT JOIN knowledge_repository kr
            ON TRIM(cr.riskVariable) COLLATE utf8mb4_unicode_ci = TRIM(kr.risk_kode) COLLATE utf8mb4_unicode_ci
        $whereAllProject
        GROUP BY cr.changeId
        ORDER BY MAX(cr.changeDate) DESC
    ");
    $stmtRiskVisual->execute($params);
    $riskVisualRows = $stmtRiskVisual->fetchAll();

    $riskMatrix = [];
    $tornadoMap = [];
    foreach ($riskVisualRows as $row) {
        $riskScore = max(0, min(10, (int)($row['risk'] ?? 0)));
        $likelihood = max(1, min(5, (int)ceil(max(1, $riskScore) / 2)));
        $costImpact = (float)($row['costImpact'] ?? 0);
        $timeImpact = (int)($row['timeImpact'] ?? 0);
        $impactBase = max($riskScore, $costImpact > 0 ? 7 : 0, $timeImpact > 0 ? 6 : 0);
        $impact = max(1, min(5, (int)ceil(max(1, $impactBase) / 2)));
        $riskCode = trim((string)($row['riskVariable'] ?? ''));
        $riskName = trim((string)($row['risk_nama'] ?? ''));
        $riskLabel = $riskCode !== '' ? $riskCode : ($riskName !== '' ? $riskName : 'Risiko belum dikodekan');

        $riskMatrix[] = [
            "changeId" => $row['changeId'],
            "projectArea" => $row['projectArea'] ?? '',
            "riskScore" => $riskScore,
            "likelihood" => $likelihood,
            "impact" => $impact,
            "riskVariable" => $riskCode,
            "riskName" => $riskName,
            "riskCategory" => $row['riskCategory'] ?: ($row['risk_kategori'] ?? ''),
            "status" => $row['status'] ?? ''
        ];

        if (!isset($tornadoMap[$riskLabel])) {
            $tornadoMap[$riskLabel] = [
                "riskVariable" => $riskLabel,
                "riskName" => $riskName,
                "riskCategory" => $row['riskCategory'] ?: ($row['risk_kategori'] ?? ''),
                "totalCost" => 0,
                "totalTime" => 0,
                "count" => 0,
                "maxRisk" => 0
            ];
        }
        $tornadoMap[$riskLabel]["totalCost"] += $costImpact;
        $tornadoMap[$riskLabel]["totalTime"] += $timeImpact;
        $tornadoMap[$riskLabel]["count"] += 1;
        $tornadoMap[$riskLabel]["maxRisk"] = max($tornadoMap[$riskLabel]["maxRisk"], $riskScore);
    }

    $tornadoSensitivity = array_values($tornadoMap);
    usort($tornadoSensitivity, function ($a, $b) {
        $scoreA = ($a['totalCost'] / 1000000) + ($a['totalTime'] * 10) + ($a['maxRisk'] * 2);
        $scoreB = ($b['totalCost'] / 1000000) + ($b['totalTime'] * 10) + ($b['maxRisk'] * 2);
        return $scoreB <=> $scoreA;
    });
    $tornadoSensitivity = array_slice($tornadoSensitivity, 0, 10);

    // 11. Statistik ringkas dashboard pengendalian perubahan
    $stmtControlTotals = $pdo->prepare("
        SELECT
            COUNT(*) AS total_changes,
            SUM(CASE WHEN UPPER(status) = 'APPROVED' THEN 1 ELSE 0 END) AS approved_changes,
            SUM(CASE WHEN UPPER(status) = 'PENDING' AND CAST(risk AS UNSIGNED) >= 7 THEN 1 ELSE 0 END) AS active_high_risk,
            COALESCE(SUM(CASE WHEN UPPER(status) = 'APPROVED' THEN GREATEST(CAST(timeImpact AS SIGNED), 0) ELSE 0 END), 0) AS controlled_delay_days,
            SUM(CASE WHEN changeDate >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) THEN 1 ELSE 0 END) AS changes_last_6_months
        FROM change_requests
        $whereAllProject
    ");
    $stmtControlTotals->execute($params);
    $controlTotals = $stmtControlTotals->fetch() ?: [];

    $lessonTotal = 0;
    $lessonRecent = 0;
    try {
        $stmtLessons = $pdo->query("
            SELECT
                SUM(CASE
                    WHEN LOWER(COALESCE(knowledgeCategory, '')) LIKE '%lesson%'
                      OR LOWER(COALESCE(documentName, '')) LIKE '%lesson%'
                    THEN 1 ELSE 0
                END) AS lesson_total,
                SUM(CASE
                    WHEN (
                        LOWER(COALESCE(knowledgeCategory, '')) LIKE '%lesson%'
                        OR LOWER(COALESCE(documentName, '')) LIKE '%lesson%'
                    )
                    AND changeDate >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                    THEN 1 ELSE 0
                END) AS lesson_recent,
                COUNT(*) AS kb_total,
                SUM(CASE WHEN changeDate >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) THEN 1 ELSE 0 END) AS kb_recent
            FROM knowledge_base
        ");
        $lessonRow = $stmtLessons->fetch() ?: [];
        $lessonTotal = (int)($lessonRow['lesson_total'] ?? 0);
        $lessonRecent = (int)($lessonRow['lesson_recent'] ?? 0);
        if ($lessonTotal === 0) {
            $lessonTotal = (int)($lessonRow['kb_total'] ?? 0);
            $lessonRecent = (int)($lessonRow['kb_recent'] ?? 0);
        }
    } catch (Exception $e) {
        $lessonTotal = 0;
        $lessonRecent = 0;
    }

    $avgSpiPercent = null;
    try {
        $stmtSpi = $pdo->query("
            SELECT ROUND(AVG(spi_ratio) * 100, 1) AS avg_spi_percent
            FROM (
                SELECT
                    ps.project_id,
                    CASE
                        WHEN CAST(ps.rencana_kumulatif AS DECIMAL(18,4)) > 0
                        THEN LEAST(GREATEST(CAST(ps.realisasi_kumulatif AS DECIMAL(18,4)) / CAST(ps.rencana_kumulatif AS DECIMAL(18,4)), 0), 1.5)
                        ELSE NULL
                    END AS spi_ratio
                FROM project_scurve ps
                INNER JOIN (
                    SELECT project_id, MAX(periode_ke) AS latest_period
                    FROM project_scurve
                    WHERE realisasi_kumulatif IS NOT NULL
                      AND rencana_kumulatif IS NOT NULL
                      AND CAST(rencana_kumulatif AS DECIMAL(18,4)) > 0
                    GROUP BY project_id
                ) latest
                    ON latest.project_id = ps.project_id
                   AND latest.latest_period = ps.periode_ke
            ) spi_latest
        ");
        $avgSpiPercent = $stmtSpi->fetchColumn();
        $avgSpiPercent = $avgSpiPercent !== null ? (float)$avgSpiPercent : null;
    } catch (Exception $e) {
        $avgSpiPercent = null;
    }

    $stmtTrend = $pdo->prepare("
        SELECT
            DATE_FORMAT(changeDate, '%Y-%m') AS month_key,
            DATE_FORMAT(changeDate, '%b') AS month_label,
            COUNT(*) AS total,
            SUM(CASE WHEN UPPER(status) = 'APPROVED' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN UPPER(status) IN ('APPROVED', 'REJECTED') THEN 1 ELSE 0 END) AS closed
        FROM change_requests
        " . (!empty($whereAllProject) ? $whereAllProject . " AND changeDate >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)" : "WHERE changeDate >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)") . "
        GROUP BY month_key, month_label
        ORDER BY month_key ASC
    ");
    $stmtTrend->execute($params);
    $controlTrend = $stmtTrend->fetchAll();

    $stmtCause = $pdo->prepare("
        SELECT cause, COUNT(*) AS total
        FROM (
            SELECT
                CASE
                    WHEN LOWER(CONCAT_WS(' ', changeCategory, changeDrivers, riskCategory, riskDescription)) REGEXP 'desain|design|gambar|dokumen' THEN 'Desain'
                    WHEN LOWER(CONCAT_WS(' ', changeCategory, changeDrivers, riskCategory, riskDescription)) REGEXP 'lapangan|site|kondisi' THEN 'Kondisi lapangan'
                    WHEN LOWER(CONCAT_WS(' ', changeCategory, changeDrivers, riskCategory, riskDescription)) REGEXP 'owner|konsultan|permintaan|vo' THEN 'Owner'
                    WHEN LOWER(CONCAT_WS(' ', changeCategory, changeDrivers, riskCategory, riskDescription)) REGEXP 'metode|method|erection|lifting' THEN 'Metode'
                    ELSE 'Lainnya'
                END AS cause
            FROM change_requests
            $whereAllProject
        ) cause_data
        GROUP BY cause
        ORDER BY total DESC
    ");
    $stmtCause->execute($params);
    $controlCauses = $stmtCause->fetchAll();

    $stmtTopRisks = $pdo->prepare("
        SELECT
            COALESCE(NULLIF(MAX(kr.risk_nama), ''), NULLIF(MAX(cr.riskDescription), ''), NULLIF(MAX(cr.riskVariable), ''), 'Risiko belum dikodekan') AS risk_name,
            COALESCE(NULLIF(MAX(cr.description), ''), NULLIF(MAX(kr.insight), ''), 'Perlu pemantauan dan mitigasi lanjutan') AS risk_note,
            MAX(CAST(cr.risk AS UNSIGNED)) AS max_risk,
            COUNT(*) AS total
        FROM change_requests cr
        LEFT JOIN knowledge_repository kr
            ON TRIM(cr.riskVariable) COLLATE utf8mb4_unicode_ci = TRIM(kr.risk_kode) COLLATE utf8mb4_unicode_ci
        $whereAllProject
        GROUP BY COALESCE(NULLIF(cr.riskVariable, ''), cr.riskDescription, cr.changeId)
        ORDER BY max_risk DESC, total DESC
        LIMIT 4
    ");
    $stmtTopRisks->execute($params);
    $controlTopRisks = $stmtTopRisks->fetchAll();

    // Kembalikan semua data dalam satu paket JSON lengkap ke frontend
    echo json_encode([
        "status" => "success",
        "data" => [
            "under_review" => (int)$underReviewCount,
            "need_mitigation" => (int)$needMitigationCount,
            "approved" => (int)$approvedCount,          
            "rejected" => (int)$rejectedCount,          
            "recent_activities" => $recentActivities,
            "pending_requests" => $pendingRequests,
            "risk_distribution" => [
                "kritis" => (int)($riskDistribution['kritis'] ?? 0),
                "tinggi" => (int)($riskDistribution['tinggi'] ?? 0),
                "sedang" => (int)($riskDistribution['sedang'] ?? 0),
                "rendah" => (int)($riskDistribution['rendah'] ?? 0),
                "aman" => (int)($riskDistribution['aman'] ?? 0),
                "total" => (int)($riskDistribution['total'] ?? 0)
            ],
            "wbs_impacted" => $wbsImpacted,
            "addendum_summary" => [
                "approved_changes" => (int)($addendumSummary['approved_changes'] ?? 0),
                "addendum_candidates" => (int)($addendumSummary['addendum_candidates'] ?? 0),
                "total_time_impact" => (int)($addendumSummary['total_time_impact'] ?? 0),
                "total_cost_impact" => (float)($addendumSummary['total_cost_impact'] ?? 0)
            ],
            "risk_matrix" => $riskMatrix,
            "tornado_sensitivity" => $tornadoSensitivity,
            "control_dashboard" => [
                "total_change_requests" => (int)($controlTotals['total_changes'] ?? 0),
                "changes_last_6_months" => (int)($controlTotals['changes_last_6_months'] ?? 0),
                "approved_changes" => (int)($controlTotals['approved_changes'] ?? 0),
                "approval_rate" => (float)((int)($controlTotals['total_changes'] ?? 0) > 0 ? round(((int)($controlTotals['approved_changes'] ?? 0) / (int)$controlTotals['total_changes']) * 100, 1) : 0),
                "controlled_delay_days" => (int)($controlTotals['controlled_delay_days'] ?? 0),
                "active_high_risk" => (int)($controlTotals['active_high_risk'] ?? 0),
                "lesson_learned" => $lessonTotal,
                "lesson_recent" => $lessonRecent,
                "avg_spi_percent" => $avgSpiPercent,
                "trend" => $controlTrend,
                "causes" => $controlCauses,
                "top_risks" => $controlTopRisks
            ]
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Query Error: " . $e->getMessage()]);
}
?>
