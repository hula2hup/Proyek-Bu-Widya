<?php
header('Content-Type: application/json');
require 'db_user.php';
require_once 'knowledge_base_auto.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    if ($action === 'sync') {
        $pdo->beginTransaction();
        $syncResult = kb_sync_approved_lessons($pdo);
        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => count($syncResult['created']) . ' lesson learned baru disinkronkan.',
            'data' => $syncResult,
        ]);
        exit;
    }

    if ($action === 'delete') {
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

    if ($action === 'save') {
        $docId = trim($_POST['docId'] ?? '');
        $category = trim($_POST['knowledgeCategory'] ?? '');
        $documentName = trim($_POST['documentName'] ?? '');
        $submittedBy = trim($_POST['submittedBy'] ?? '');

        if ($docId === '' || $category === '' || $documentName === '' || $submittedBy === '') {
            echo json_encode(['status' => 'error', 'message' => 'Doc ID, kategori, judul, dan penyusun wajib diisi.']);
            exit;
        }

        $existing = $pdo->prepare("SELECT repository_reference_id FROM knowledge_base WHERE docId = :docId");
        $existing->execute(['docId' => $docId]);
        $repoId = $existing->fetchColumn();

        if (!$repoId) {
            $repoId = $pdo->query("SELECT id FROM knowledge_repository ORDER BY id LIMIT 1")->fetchColumn();
        }

        if (!$repoId) {
            echo json_encode(['status' => 'error', 'message' => 'knowledge_repository belum memiliki data referensi.']);
            exit;
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

    $stmt = $pdo->query("
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
        LEFT JOIN change_requests cr ON cr.changeId = kb.changeRequestLink
        LEFT JOIN knowledge_repository kr ON kr.id = kb.repository_reference_id
        ORDER BY kb.changeDate DESC, kb.docId DESC
    ");

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

    echo json_encode([
        'status' => 'error',
        'message' => 'Knowledge Base API Error: ' . $e->getMessage(),
    ]);
}
