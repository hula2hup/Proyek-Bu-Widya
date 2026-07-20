<?php
session_start();
header('Content-Type: application/json');
require 'db_user.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Project Manager') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Hanya Project Manager yang dapat mengakses Knowledge Base proyek."]);
    exit;
}

$userId = $_SESSION['user_id'];
$projectId = trim($_GET['project_id'] ?? '');

try {
    $assignedStmt = $pdo->prepare("
        SELECT project_id
        FROM project_assignments
        WHERE user_id = ?
          AND role_assigned = 'Project Manager'
    ");
    $assignedStmt->execute([$userId]);
    $assignedProjectIds = $assignedStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($assignedProjectIds)) {
        echo json_encode(["status" => "success", "count" => 0, "data" => []]);
        exit;
    }

    if ($projectId !== '' && !in_array($projectId, $assignedProjectIds, true)) {
        echo json_encode(["status" => "error", "message" => "Anda tidak ter-assign ke proyek ini."]);
        exit;
    }

    $filterProjectIds = $projectId !== '' ? [$projectId] : $assignedProjectIds;
    $placeholders = implode(',', array_fill(0, count($filterProjectIds), '?'));

    $query = "
        SELECT
            kb.docId,
            kb.changeDate,
            kb.documentName,
            kb.knowledgeCategory,
            kb.submittedBy,
            kb.validationStatus,
            kb.description,
            kb.changeRequestLink,
            kb.riskVariableLink,
            kb.documentFile,
            kb.actual_impact,
            kb.applied_solution,
            cr.projectArea,
            cr.impactCost AS cr_impact_cost,
            cr.impactTime AS cr_impact_time,
            cr.impactScope AS cr_impact_scope,
            cr.impactQuality AS cr_impact_quality,
            cr.impactSafety AS cr_impact_safety,
            cr.timeImpact AS cr_time_impact,
            cr.costImpact AS cr_cost_impact,
            kr.wbs_kode,
            kr.wbs_nama,
            kr.risk_kode,
            kr.risk_nama,
            kr.risk_kategori,
            kr.insight,
            kr.saran
        FROM knowledge_base kb
        INNER JOIN change_requests cr ON cr.changeId = kb.changeRequestLink
        LEFT JOIN knowledge_repository kr ON kr.id = kb.repository_reference_id
        WHERE cr.projectArea IN ($placeholders)
        ORDER BY kb.changeDate DESC, kb.docId DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($filterProjectIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "count" => count($rows),
        "data" => $rows,
        "project_ids" => $filterProjectIds,
    ]);
} catch (Throwable $e) {
    echo json_encode(["status" => "error", "message" => "PM Knowledge Base API Error: " . $e->getMessage()]);
}

