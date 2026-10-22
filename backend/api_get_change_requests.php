<?php
// File: api_get_change_requests.php
// API untuk mengambil Change Request beserta evaluasi rekomendasi AI/Knowledge Repository (T2)
// BAB XII § 12.5.4 (Prioritas P0-05)

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db_user.php';
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/recommendation_engine.php';

// Validasi otorisasi: Admin atau Project Manager
$currentUser = auth_require_role(['Admin', 'Project Manager']);

try {
    $projectId = $_GET['project_id'] ?? '';

    // Project Manager hanya bisa melihat project yang di-assign padanya
    if ($currentUser['role'] === 'Project Manager' && !empty($projectId)) {
        auth_require_project_access($pdo, $projectId, $currentUser);
    }

    if (empty($projectId)) {
        if ($currentUser['role'] === 'Project Manager') {
            // Filter hanya proyek yang di-assign
            $stmtAssigned = $pdo->prepare("SELECT project_id FROM project_assignments WHERE user_id = ? AND role_assigned = 'Project Manager'");
            $stmtAssigned->execute([$currentUser['id']]);
            $assignedProjects = $stmtAssigned->fetchAll(PDO::FETCH_COLUMN);

            if (empty($assignedProjects)) {
                echo json_encode(["status" => "success", "count" => 0, "data" => []]);
                exit;
            }

            $inClause = implode(',', array_fill(0, count($assignedProjects), '?'));
            $query = "SELECT * FROM change_requests WHERE projectArea IN ($inClause) ORDER BY changeDate DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute($assignedProjects);
        } else {
            // Admin: Semua
            $query = "SELECT * FROM change_requests ORDER BY changeDate DESC";
            $stmt = $pdo->query($query);
        }
    } else {
        $query = "SELECT * FROM change_requests WHERE projectArea = :project_id ORDER BY changeDate DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['project_id' => $projectId]);
    }

    $changeRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // T2: Evaluasi deterministic recommendation matching untuk setiap Change Request
    foreach ($changeRequests as &$cr) {
        $cr['id'] = $cr['changeId'];
        $cr['projectId'] = $cr['projectArea'];
        $cr['desc'] = $cr['description'];
        $cr['sender'] = $cr['submittedBy'];
        $cr['role'] = 'Site Engineer';
        $cr['waktuTs'] = strtotime($cr['changeDate'] ?? 'now');
        $cr['risikoScore'] = (int)($cr['risk'] ?? 0);

        $status = strtoupper(trim($cr['status'] ?? ''));
        if (!in_array($status, ['PENDING', 'APPROVED', 'REJECTED'], true)) {
            $status = 'PENDING';
        }
        $cr['status'] = $status;
        $cr['approvalDecision'] = strtolower($status);

        // Evaluasi kontrak T2 melalui recommendation engine
        $rec = rec_match_candidates($pdo, $cr['wbsLevel5'] ?: $cr['wbsLevel4'], $cr['riskVariable']);
        
        $cr['rec_contract_status'] = $rec['status']; // MATCH, NO_MATCH, AMBIGUOUS, INVALID_RULE
        $cr['rec_candidate_count'] = $rec['candidate_count'];
        $cr['rec_tie_rule'] = $rec['tie_rule'];

        if ($rec['selected_rule']) {
            $cr['insight'] = $rec['selected_rule']['insight'];
            $cr['saran'] = $rec['selected_rule']['saran'];
            $cr['matched_rule_id'] = $rec['selected_rule']['id'];
        } else {
            $cr['insight'] = 'Data insight tidak ditemukan pada basis referensi.';
            $cr['saran'] = $rec['message'];
            $cr['matched_rule_id'] = null;
        }

        if ($rec['status'] === 'AMBIGUOUS') {
            $cr['ambiguous_candidates'] = array_map(function($c) {
                return ['id' => $c['id'], 'risk_nama' => $c['risk_nama'], 'saran' => $c['saran']];
            }, $rec['candidates']);
        }
    }

    echo json_encode([
        "status" => "success",
        "count" => count($changeRequests),
        "data" => $changeRequests
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
?>