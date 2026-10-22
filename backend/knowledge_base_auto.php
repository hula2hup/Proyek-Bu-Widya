<?php
// ========================================================
// WYLCORE / RQ12 — knowledge_base_auto.php
// T5: Approval -> Knowledge Information Contract Enforcement
// BAB XII §§ 12.5.5, 12.7 (Prioritas P0-02, P0-04, P0-06, P1-12)
// ========================================================

require_once __DIR__ . '/recommendation_engine.php';

function kb_next_doc_id(PDO $pdo): string {
    $stmt = $pdo->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING(docId, 4) AS UNSIGNED)), 0) + 1 AS next_num
        FROM knowledge_base
        WHERE docId REGEXP '^KM-[0-9]+$'
    ");
    $next = (int)($stmt->fetchColumn() ?: 1);
    return 'KM-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}

function kb_build_lesson_description(array $cr, array $repo): string {
    $parts = [];
    if (!empty($cr['description'])) {
        $parts[] = "Ringkasan: " . trim($cr['description']);
    }
    if (!empty($cr['descriptionDetail'])) {
        $parts[] = "Detail Akar Penyebab & Lapangan:\n" . trim($cr['descriptionDetail']);
    }
    if (!empty($repo['insight'])) {
        $parts[] = "Insight Referensial:\n" . trim($repo['insight']);
    }
    return implode("\n\n", $parts);
}

function kb_build_actual_impact(array $cr): string {
    $parts = [];
    if (($cr['timeImpact'] ?? null) !== null && $cr['timeImpact'] !== '') {
        $parts[] = 'Dampak waktu persetujuan: ' . $cr['timeImpact'] . ' hari (indikatif)';
    }
    if (($cr['costImpact'] ?? null) !== null && $cr['costImpact'] !== '') {
        $parts[] = 'Dampak biaya persetujuan: Rp ' . number_format((float)$cr['costImpact'], 2, ',', '.');
    }
    if (!empty($cr['impactScope'])) {
        $parts[] = 'Dampak lingkup: ' . $cr['impactScope'];
    }
    if (!empty($cr['impactQuality'])) {
        $parts[] = 'Dampak mutu: ' . $cr['impactQuality'];
    }
    if (!empty($cr['impactSafety'])) {
        $parts[] = 'Dampak K3: ' . $cr['impactSafety'];
    }
    return implode("\n\n", $parts);
}

/**
 * Check if the CR has complete evidence and root cause input (T5-04).
 */
function kb_check_cr_completeness(array $cr): bool {
    $hasDescription = !empty(trim((string)($cr['description'] ?? '')));
    $hasDetailOrRootCause = !empty(trim((string)($cr['descriptionDetail'] ?? '')))
        || !empty(trim((string)($cr['changeDrivers'] ?? '')));
    
    return $hasDescription && $hasDetailOrRootCause;
}

/**
 * Generate a knowledge candidate from an approved Change Request.
 * Enforces T5-01: Candidate is ALWAYS created with validationStatus = 'DRAFT', NEVER PUBLISHED!
 */
function kb_create_lesson_from_change_request(PDO $pdo, string $changeId): array {
    $stmt = $pdo->prepare("SELECT * FROM change_requests WHERE changeId = :changeId LIMIT 1");
    $stmt->execute(['changeId' => $changeId]);
    $cr = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cr) {
        return ['created' => false, 'status' => 'NOT_FOUND', 'reason' => 'Change Request tidak ditemukan.'];
    }

    if (strtoupper(trim((string)($cr['status'] ?? ''))) !== 'APPROVED') {
        return ['created' => false, 'status' => 'INVALID_STATE', 'reason' => 'Change Request belum berstatus APPROVED.'];
    }

    // Check if knowledge candidate already exists
    $existing = $pdo->prepare("SELECT docId, validationStatus FROM knowledge_base WHERE changeRequestLink = :changeId LIMIT 1");
    $existing->execute(['changeId' => $changeId]);
    $existingRow = $existing->fetch(PDO::FETCH_ASSOC);

    if ($existingRow) {
        return [
            'created' => false,
            'docId' => $existingRow['docId'],
            'validationStatus' => $existingRow['validationStatus'],
            'contract_status' => 'DRAFT',
            'reason' => 'Knowledge Base sudah ada sebelumnya.'
        ];
    }

    // Match recommendation repository
    $recResult = rec_match_candidates($pdo, $cr['wbsLevel5'] ?: $cr['wbsLevel4'], $cr['riskVariable']);
    $repo = $recResult['selected_rule'];

    if (!$repo) {
        return [
            'created' => false,
            'status' => 'NO_MATCH',
            'reason' => 'Tidak ada pasangan WBS dan kode risiko di knowledge_repository.'
        ];
    }

    $isComplete = kb_check_cr_completeness($cr);
    $docId = kb_next_doc_id($pdo);
    $documentName = sprintf(
        'Lesson Learned %s - %s',
        $cr['changeId'],
        $repo['risk_nama'] ?: $repo['risk_kode']
    );

    // Initial status: Strictly DRAFT (T5-01)
    $validationStatus = 'DRAFT';
    $curationNotes = $isComplete 
        ? 'Kandidat awal dibentuk otomatis dari approval CR; menunggu kurasi ahli.'
        : 'INCOMPLETE_EVIDENCE: CR kekurangan rincian akar penyebab / detail tindakan; dilarang dipromosikan ke VALIDATED.';

    $insert = $pdo->prepare("
        INSERT INTO knowledge_base (
            docId,
            repository_reference_id,
            changeDate,
            documentName,
            knowledgeCategory,
            submittedBy,
            validationStatus,
            description,
            changeRequestLink,
            riskVariableLink,
            documentFile,
            actual_impact,
            applied_solution,
            curation_notes,
            evidence_verified
        ) VALUES (
            :docId,
            :repository_reference_id,
            :changeDate,
            :documentName,
            'Lesson Learned',
            :submittedBy,
            :validationStatus,
            :description,
            :changeRequestLink,
            :riskVariableLink,
            :documentFile,
            :actual_impact,
            :applied_solution,
            :curation_notes,
            :evidence_verified
        )
    ");

    $insert->execute([
        'docId' => $docId,
        'repository_reference_id' => $repo['id'],
        'changeDate' => $cr['approvalDate'] ?: ($cr['changeDate'] ?: date('Y-m-d')),
        'documentName' => $documentName,
        'submittedBy' => $cr['submittedBy'] ?: 'Project Manager',
        'validationStatus' => $validationStatus,
        'description' => kb_build_lesson_description($cr, $repo),
        'changeRequestLink' => $cr['changeId'],
        'riskVariableLink' => $repo['risk_kode'],
        'documentFile' => $cr['photoEvidence'] ?? null,
        'actual_impact' => kb_build_actual_impact($cr),
        'applied_solution' => trim(($repo['saran'] ?? '') . (!empty($cr['approvalNotes']) ? "\n\nCatatan Persetujuan PM: " . $cr['approvalNotes'] : '')),
        'curation_notes' => $curationNotes,
        'evidence_verified' => $isComplete ? 1 : 0
    ]);

    // Update reference link on change_requests
    $updateCr = $pdo->prepare("UPDATE change_requests SET associatedKnowledge = :docId WHERE changeId = :changeId");
    $updateCr->execute(['docId' => $docId, 'changeId' => $cr['changeId']]);

    return [
        'created' => true,
        'docId' => $docId,
        'validationStatus' => 'DRAFT',
        'contract_status' => $isComplete ? 'DRAFT' : 'INCOMPLETE_EVIDENCE',
        'repositoryId' => $repo['id'],
        'completeness' => $isComplete ? 'COMPLETE' : 'INCOMPLETE_EVIDENCE'
    ];
}

/**
 * Curate and validate/publish a knowledge draft (T5-03).
 * Workflow: DRAFT -> UNDER_REVIEW -> VALIDATED or REJECTED.
 */
function kb_curate_candidate(
    PDO $pdo,
    string $docId,
    array $curatorUser,
    string $targetStatus,
    ?string $curationNotes = null
): array {
    $targetStatus = strtoupper(trim($targetStatus));
    if (!in_array($targetStatus, ['UNDER_REVIEW', 'VALIDATED', 'REJECTED'], true)) {
        return [
            'success' => false,
            'contract_status' => 'INVALID_STATE',
            'message' => 'Target status kurasi tidak valid. Gunakan UNDER_REVIEW, VALIDATED, atau REJECTED.'
        ];
    }

    $stmt = $pdo->prepare("
        SELECT kb.*, cr.description as cr_desc, cr.descriptionDetail as cr_detail, cr.changeDrivers
        FROM knowledge_base kb
        LEFT JOIN change_requests cr ON cr.changeId = kb.changeRequestLink
        WHERE kb.docId = ?
    ");
    $stmt->execute([$docId]);
    $kb = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$kb) {
        return [
            'success' => false,
            'contract_status' => 'NOT_FOUND',
            'message' => "Dokumen knowledge {$docId} tidak ditemukan."
        ];
    }

    // T5-04 Guard: Cannot promote to VALIDATED if evidence / root cause is incomplete
    if ($targetStatus === 'VALIDATED') {
        $crProxy = [
            'description' => $kb['cr_desc'] ?: $kb['description'],
            'descriptionDetail' => $kb['cr_detail'],
            'changeDrivers' => $kb['changeDrivers']
        ];
        if (!kb_check_cr_completeness($crProxy)) {
            return [
                'success' => false,
                'contract_status' => 'INCOMPLETE_EVIDENCE',
                'message' => 'Promosi ke VALIDATED ditolak: Rekaman knowledge tidak memiliki kelengkapan akar penyebab atau solusi terverifikasi [INCOMPLETE_EVIDENCE].'
            ];
        }
    }

    $curatedAt = date('Y-m-d H:i:s');
    $updateStmt = $pdo->prepare("
        UPDATE knowledge_base
        SET validationStatus = :status,
            curator_user_id = :curator_id,
            curator_name = :curator_name,
            curated_at = :curated_at,
            curation_notes = :notes,
            evidence_verified = :verified
        WHERE docId = :docId
    ");
    $updateStmt->execute([
        'status' => $targetStatus,
        'curator_id' => $curatorUser['id'] ?? 0,
        'curator_name' => $curatorUser['username'] ?? 'Curator',
        'curated_at' => $curatedAt,
        'notes' => $curationNotes ?: ($targetStatus === 'VALIDATED' ? 'Telah ditinjau dan divalidasi oleh kurator.' : 'Kurasi ditolak.'),
        'verified' => $targetStatus === 'VALIDATED' ? 1 : 0,
        'docId' => $docId
    ]);

    return [
        'success' => true,
        'docId' => $docId,
        'validationStatus' => $targetStatus,
        'contract_status' => $targetStatus === 'VALIDATED' ? 'CURATED' : ($targetStatus === 'REJECTED' ? 'CURATION_REJECTED' : 'DRAFT'),
        'curator' => $curatorUser['username'] ?? 'Curator',
        'curated_at' => $curatedAt,
        'message' => "Status knowledge berhasil diubah menjadi {$targetStatus} [CURATED]."
    ];
}

/**
 * Retrieve reusable lessons strictly filtered for validated items (T5-02).
 * DRAFT and REJECTED items are strictly excluded from the reusable recommendation set.
 */
function kb_get_reusable_lessons(PDO $pdo, ?string $wbsCode = null, ?string $riskCode = null): array {
    $sql = "
        SELECT kb.docId, kb.documentName, kb.knowledgeCategory, kb.validationStatus,
               kb.description, kb.applied_solution, kb.actual_impact,
               kb.curator_name, kb.curated_at,
               kr.wbs_kode, kr.risk_kode
        FROM knowledge_base kb
        LEFT JOIN knowledge_repository kr ON kr.id = kb.repository_reference_id
        WHERE kb.validationStatus = 'VALIDATED'
    ";
    $params = [];

    if ($wbsCode) {
        $sql .= " AND (kr.wbs_kode = :wbs OR kb.description LIKE :wbs_like)";
        $params['wbs'] = $wbsCode;
        $params['wbs_like'] = "%{$wbsCode}%";
    }

    if ($riskCode) {
        $sql .= " AND (kr.risk_kode = :risk1 OR kb.riskVariableLink = :risk2)";
        $params['risk1'] = $riskCode;
        $params['risk2'] = $riskCode;
    }

    $sql .= " ORDER BY kb.curated_at DESC, kb.docId DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
