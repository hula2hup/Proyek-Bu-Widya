<?php
// ========================================================
// WYLCORE / RQ12 — save_approval.php
// T4: CR -> Approval Contract Enforcement
// BAB XII §§ 12.4.2, 12.5.2, 12.7 (Prioritas P0-01, P0-07, P1-11)
// ========================================================

header('Content-Type: application/json');

require_once __DIR__ . '/db_user.php';
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/traceability.php';
require_once __DIR__ . '/recommendation_engine.php';
require_once __DIR__ . '/knowledge_base_auto.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Metode tidak diizinkan. Gunakan POST."]);
    exit;
}

// 1. Validasi Otorisasi: Hanya PM atau Admin yang berhak approve/reject CR (P0-01, P0-08)
$currentUser = auth_require_role(['Project Manager', 'Admin']);

$changeId      = trim($_POST['changeId'] ?? '');
$reviewId      = trim($_POST['reviewId'] ?? '');
$approvalDate  = trim($_POST['approvalDate'] ?? '');
$approvalNotes = trim($_POST['approvalNotes'] ?? '');
$costImpactRaw = $_POST['costImpact'] ?? '0';
$timeImpactRaw = $_POST['timeImpact'] ?? '0';
$status        = strtoupper(trim($_POST['approvalDecision'] ?? ''));
$expectedLock  = isset($_POST['lock_version']) ? (int)$_POST['lock_version'] : null;

// Validasi input dasar
if (empty($changeId)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Change ID tidak boleh kosong!"]);
    exit;
}

if (!in_array($status, ['PENDING', 'APPROVED', 'REJECTED'], true)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "error_code" => "INVALID_STATE",
        "message" => "Status keputusan tidak valid. Gunakan PENDING, APPROVED, atau REJECTED."
    ]);
    exit;
}

if (empty($approvalDate)) {
    $approvalDate = date('Y-m-d');
}

if (empty($reviewId)) {
    $reviewId = 'REV-' . time();
}

$costImpact = is_numeric($costImpactRaw) ? (float)$costImpactRaw : 0;
$timeImpact = is_numeric($timeImpactRaw) ? (int)$timeImpactRaw : 0;
if ($costImpact < 0 || $timeImpact < 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Impact Analysis biaya dan waktu tidak boleh negatif."]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 2. Lock baris CR untuk proteksi konkurensi (T4-05)
    $stmtLock = $pdo->prepare("
        SELECT *
        FROM change_requests
        WHERE changeId = ?
        FOR UPDATE
    ");
    $stmtLock->execute([$changeId]);
    $cr = $stmtLock->fetch(PDO::FETCH_ASSOC);

    if (!$cr) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Change Request {$changeId} tidak ditemukan."]);
        exit;
    }

    // 3. T4-02: Security check - PM harus ditugaskan pada proyek CR ini
    $projectId = $cr['projectArea'];
    if ($currentUser['role'] !== 'Admin') {
        $stmtAccess = $pdo->prepare("
            SELECT COUNT(*)
            FROM project_assignments
            WHERE project_id = ?
              AND user_id = ?
              AND role_assigned = 'Project Manager'
        ");
        $stmtAccess->execute([$projectId, $currentUser['id']]);
        if ((int)$stmtAccess->fetchColumn() === 0) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode([
                "status" => "error",
                "error_code" => "UNAUTHORIZED",
                "message" => sprintf(
                    "Akses ditolak [UNAUTHORIZED]: Project Manager %s tidak memiliki wewenang approval pada project %s.",
                    $currentUser['username'],
                    $projectId
                )
            ]);
            exit;
        }
    }

    // 4. T4-03: Idempotency & State Transition Guard
    $currentStatus = strtoupper(trim((string)($cr['status'] ?? '')));
    if (in_array($currentStatus, ['APPROVED', 'REJECTED'], true)) {
        // Jika permintaan approval sama persis dengan yang sudah tercatat -> Idempotent success
        if ($currentStatus === $status && ($cr['reviewId'] === $reviewId || empty($_POST['reviewId']))) {
            $pdo->rollBack();
            echo json_encode([
                "status" => "success",
                "contract_status" => "DECISION_COMMITTED",
                "idempotent_retry" => true,
                "message" => "Permintaan approval identik telah tercatat sebelumnya [IDEMPOTENT_RETRY]. Tidak ada duplikasi rekaman.",
                "data" => [
                    "changeId" => $changeId,
                    "status" => $currentStatus,
                    "reviewId" => $cr['reviewId'],
                    "approvalDate" => $cr['approvalDate']
                ]
            ]);
            exit;
        }

        // Jika mencoba mengubah keputusan yang sudah closed tanpa reset
        if ($currentStatus !== $status && $currentUser['role'] !== 'Admin') {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode([
                "status" => "error",
                "error_code" => "INVALID_STATE",
                "message" => sprintf(
                    "Status tidak valid [INVALID_STATE]: CR %s sudah berstatus %s dan tidak dapat diubah menjadi %s.",
                    $changeId,
                    $currentStatus,
                    $status
                )
            ]);
            exit;
        }
    }

    // T4-05: Concurrency check via lock_version (Optimistic check)
    if ($expectedLock !== null && (int)$cr['lock_version'] !== $expectedLock) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            "status" => "error",
            "error_code" => "STALE_CONTEXT",
            "message" => "Data telah diubah oleh sesi lain saat proses approval berlangsung [STALE_CONTEXT]."
        ]);
        exit;
    }

    // 5. Update CR dengan status keputusan
    $updateSql = "
        UPDATE change_requests
        SET reviewId = :reviewId,
            approvalDate = :approvalDate,
            approvalNotes = :approvalNotes,
            costImpact = :costImpact,
            timeImpact = :timeImpact,
            status = :status,
            lock_version = lock_version + 1
        WHERE changeId = :changeId
    ";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([
        'reviewId' => $reviewId,
        'approvalDate' => $approvalDate,
        'approvalNotes' => $approvalNotes,
        'costImpact' => $costImpact,
        'timeImpact' => $timeImpact,
        'status' => $status,
        'changeId' => $changeId
    ]);

    // 6. Snapshot rekomendasi saat review untuk audit provenance
    $recResult = rec_match_candidates($pdo, $cr['wbsLevel5'] ?: $cr['wbsLevel4'], $cr['riskVariable']);
    $selectedRule = $recResult['selected_rule'] ?? null;

    // 7. T4-01 & T4-04: Catat Decision Provenance secara atomik dalam transaksi yang sama
    $traceContext = [
        'wbs_code' => $recResult['wbs_code'] ?: ($cr['wbsLevel5'] ?? null),
        'wbs_version' => $cr['wbs_version'] ?? 'v1.0',
        'risk_code' => $recResult['risk_code'] ?: ($cr['riskVariable'] ?? null),
        'bim_object' => $cr['bimObjectId'] ?? null,
        'bim_model_urn' => $cr['bim_model_urn'] ?? null,
        'bim_model_version' => $cr['bim_model_version'] ?? 'v1',
        'recommendation_id' => $selectedRule['id'] ?? null,
        'recommendation_status' => $recResult['status'],
        '__SIMULATE_TRACE_FAILURE' => !empty($_POST['__SIMULATE_TRACE_FAILURE'])
    ];

    $traceRecord = trace_record_decision(
        $pdo,
        $changeId,
        $projectId,
        $status,
        $currentUser,
        $approvalNotes,
        $costImpact,
        $timeImpact,
        $traceContext
    );

    // 8. T5-01: Pembentukan kandidat knowledge base jika APPROVED
    // P0-02: HANYA berstatus DRAFT, BUKAN langsung PUBLISHED / VALIDATED!
    $knowledgeResult = null;
    if ($status === 'APPROVED') {
        $knowledgeResult = kb_create_lesson_from_change_request($pdo, $changeId);
    }

    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "contract_status" => "DECISION_COMMITTED",
        "message" => sprintf("Keputusan %s untuk CR %s berhasil dicatat secara resmi.", $status, $changeId),
        "trace" => $traceRecord,
        "knowledge" => $knowledgeResult
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "error_code" => "TX_FAILURE",
        "message" => "Transaksi database dibatalkan [TX_FAILURE]: " . $e->getMessage()
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "error_code" => "TX_FAILURE",
        "message" => "Kesalahan transaksi [TX_FAILURE]: " . $e->getMessage()
    ]);
}
