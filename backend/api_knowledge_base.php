<?php
// ========================================================
// WYLCORE / RQ12 — api_knowledge_base.php
// T5: Knowledge Base Management API with Curation Lifecycle
// BAB XII §§ 12.5.5, 12.7 (Prioritas P0-02, P0-04, P0-06, P1-12)
// ========================================================

header('Content-Type: application/json');
require_once __DIR__ . '/db_user.php';
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/knowledge_base_auto.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    // 1. Curation / Review Workflow (T5-03, P0-04)
    if ($action === 'curate') {
        $curatorUser = auth_require_role(['Project Manager', 'Admin']);
        $docId = trim($_POST['docId'] ?? '');
        $targetStatus = trim($_POST['targetStatus'] ?? 'VALIDATED');
        $notes = trim($_POST['curationNotes'] ?? '');

        if ($docId === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Doc ID wajib diisi.']);
            exit;
        }

        $res = kb_curate_candidate($pdo, $docId, $curatorUser, $targetStatus, $notes);
        if (!$res['success']) {
            http_response_code(422);
        }
        echo json_encode($res);
        exit;
    }

    // 2. Query Reusable Lessons (T5-02, P0-06: strictly excludes DRAFT and REJECTED)
    if ($action === 'reusable') {
        $wbs = trim($_GET['wbs'] ?? '');
        $risk = trim($_GET['risk'] ?? '');
        $reusable = kb_get_reusable_lessons($pdo, $wbs ?: null, $risk ?: null);

        echo json_encode([
            'status' => 'success',
            'contract_status' => 'CURATED',
            'count' => count($reusable),
            'data' => $reusable
        ]);
        exit;
    }

    // 3. Delete Document
    if ($action === 'delete') {
        auth_require_role(['Admin']);
        $docId = trim($_POST['docId'] ?? '');
        if ($docId === '') {
            echo json_encode(['status' => 'error', 'message' => 'Doc ID wajib diisi.']);
            exit;
        }

        $pdo->beginTransaction();
        $clearCr = $pdo->prepare("UPDATE change_requests SET associatedKnowledge = NULL WHERE associatedKnowledge = :docId");
        $clearCr->execute(['docId' => $docId]);

        $delete = $pdo->prepare("DELETE FROM knowledge_base WHERE docId = :docId");
        $delete->execute(['docId' => $docId]);
        $pdo->commit();

        echo json_encode(['status' => 'success', 'message' => "Dokumen {$docId} berhasil dihapus."]);
        exit;
    }

    // 4. Save / Edit Document
    if ($action === 'save') {
        $user = auth_require_role(['Project Manager', 'Admin']);
        $docId = trim($_POST['docId'] ?? '');
        $category = trim($_POST['knowledgeCategory'] ?? '');
        $documentName = trim($_POST['documentName'] ?? '');
        $submittedBy = trim($_POST['submittedBy'] ?? $user['full_name']);

        if ($docId === '' || $category === '' || $documentName === '' || $submittedBy === '') {
            echo json_encode(['status' => 'error', 'message' => 'Doc ID, kategori, judul, dan penyusun wajib diisi.']);
            exit;
        }

        $existing = $pdo->prepare("SELECT repository_reference_id FROM knowledge_base WHERE docId = :docId");
        $existing->execute([$docId]);
        $repoId = $existing->fetchColumn();

        if (!$repoId) {
            $repoId = $pdo->query("SELECT id FROM knowledge_repository ORDER BY id LIMIT 1")->fetchColumn();
        }

        $sql = "
            INSERT INTO knowledge_base (
                docId,
                repository_reference_id,
                changeDate,
                documentName,
                knowledgeCategory,
                submittedBy,
                validationStatus,
                description
            ) VALUES (
                :docId,
                :repository_reference_id,
                :changeDate,
                :documentName,
                :knowledgeCategory,
                :submittedBy,
                :validationStatus,
                :description
            )
            ON DUPLICATE KEY UPDATE
                changeDate = VALUES(changeDate),
                documentName = VALUES(documentName),
                knowledgeCategory = VALUES(knowledgeCategory),
                submittedBy = VALUES(submittedBy),
                validationStatus = VALUES(validationStatus),
                description = VALUES(description)
        ";

        $stmt = $pdo->prepare($sql);
        $changeDate = $_POST['changeDate'] ?? '';

        $stmt->execute([
            'docId' => $docId,
            'repository_reference_id' => $repoId,
            'changeDate' => $changeDate !== '' ? $changeDate : date('Y-m-d'),
            'documentName' => $documentName,
            'knowledgeCategory' => $category,
            'submittedBy' => $submittedBy,
            'validationStatus' => $_POST['validationStatus'] ?? 'DRAFT',
            'description' => $_POST['description'] ?? '',
        ]);

        echo json_encode(['status' => 'success', 'message' => "Dokumen {$docId} berhasil disimpan."]);
        exit;
    }

    // Default: List All Documents (for admin/PM dashboard with status filter)
    $statusFilter = $_GET['status'] ?? null;
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
            kb.curator_name,
            kb.curated_at,
            kb.curation_notes,
            kb.evidence_verified,
            cr.projectArea,
            kr.wbs_kode,
            kr.wbs_nama,
            kr.risk_kode,
            kr.risk_nama,
            kr.risk_kategori,
            kr.insight,
            kr.saran
        FROM knowledge_base kb
        LEFT JOIN change_requests cr ON cr.changeId = kb.changeRequestLink
        LEFT JOIN knowledge_repository kr ON kr.id = kb.repository_reference_id
    ";

    $params = [];
    if ($statusFilter) {
        $query .= " WHERE kb.validationStatus = :status";
        $params['status'] = strtoupper($statusFilter);
    }
    $query .= " ORDER BY kb.changeDate DESC, kb.docId DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'count' => count($rows),
        'data' => $rows,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Knowledge Base API Error: ' . $e->getMessage(),
    ]);
}
