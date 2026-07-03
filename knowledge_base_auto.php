<?php

function kb_extract_code($value) {
    $value = trim((string)($value ?? ''));
    if ($value === '') {
        return '';
    }

    if (preg_match('/^([A-Z]?\d+(?:\.\d+)*|R\d+(?:\.\d+)?)/i', $value, $matches)) {
        return strtoupper($matches[1]);
    }

    if (preg_match('/\b(R\d+(?:\.\d+)?)\b/i', $value, $matches)) {
        return strtoupper($matches[1]);
    }

    return $value;
}

function kb_next_doc_id(PDO $pdo) {
    $stmt = $pdo->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING(docId, 4) AS UNSIGNED)), 0) + 1 AS next_num
        FROM knowledge_base
        WHERE docId REGEXP '^KM-[0-9]+$'
    ");
    $next = (int)($stmt->fetchColumn() ?: 1);
    return 'KM-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}

function kb_find_repository_match(PDO $pdo, array $cr) {
    $wbsCode = kb_extract_code($cr['wbsLevel5'] ?? '');
    $riskCode = kb_extract_code($cr['riskVariable'] ?? '');

    if ($wbsCode === '' || $riskCode === '') {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM knowledge_repository
        WHERE TRIM(wbs_kode) COLLATE utf8mb4_unicode_ci = :wbs_code COLLATE utf8mb4_unicode_ci
          AND TRIM(risk_kode) COLLATE utf8mb4_unicode_ci = :risk_code COLLATE utf8mb4_unicode_ci
        LIMIT 1
    ");
    $stmt->execute([
        'wbs_code' => $wbsCode,
        'risk_code' => $riskCode,
    ]);

    $repo = $stmt->fetch(PDO::FETCH_ASSOC);
    return $repo ?: null;
}

function kb_build_lesson_description(array $cr, array $repo) {
    $parts = [];
    $parts[] = 'Change Request: ' . ($cr['changeId'] ?? '-');
    $parts[] = 'WBS: ' . ($cr['wbsLevel5'] ?? $repo['wbs_kode']);
    $parts[] = 'Risiko: ' . ($repo['risk_kode'] ?? '-') . ' - ' . ($repo['risk_nama'] ?? '-');

    if (!empty($cr['description'])) {
        $parts[] = 'Ringkasan CR: ' . $cr['description'];
    }
    if (!empty($cr['descriptionDetail'])) {
        $parts[] = 'Catatan lapangan: ' . $cr['descriptionDetail'];
    }

    $parts[] = 'Insight: ' . ($repo['insight'] ?? '-');
    $parts[] = 'Saran: ' . ($repo['saran'] ?? '-');

    if (!empty($cr['approvalNotes'])) {
        $parts[] = 'Catatan PM: ' . $cr['approvalNotes'];
    }

    return implode("\n\n", $parts);
}

function kb_build_actual_impact(array $cr) {
    $parts = [];

    if (($cr['timeImpact'] ?? null) !== null && $cr['timeImpact'] !== '') {
        $parts[] = 'Dampak waktu aktual: ' . $cr['timeImpact'] . ' hari';
    }
    if (($cr['costImpact'] ?? null) !== null && $cr['costImpact'] !== '') {
        $parts[] = 'Dampak biaya aktual: ' . $cr['costImpact'];
    }
    if (!empty($cr['impactTime'])) {
        $parts[] = 'Estimasi dampak waktu: ' . $cr['impactTime'];
    }
    if (!empty($cr['impactCost'])) {
        $parts[] = 'Estimasi dampak biaya: ' . $cr['impactCost'];
    }
    if (!empty($cr['impactQuality'])) {
        $parts[] = 'Dampak mutu: ' . $cr['impactQuality'];
    }
    if (!empty($cr['impactSafety'])) {
        $parts[] = 'Dampak K3: ' . $cr['impactSafety'];
    }

    return implode("\n\n", $parts);
}

function kb_create_lesson_from_change_request(PDO $pdo, $changeId) {
    $stmt = $pdo->prepare("SELECT * FROM change_requests WHERE changeId = :changeId LIMIT 1");
    $stmt->execute(['changeId' => $changeId]);
    $cr = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cr) {
        return ['created' => false, 'reason' => 'Change Request tidak ditemukan.'];
    }

    if (strtoupper(trim((string)($cr['status'] ?? ''))) !== 'APPROVED') {
        return ['created' => false, 'reason' => 'Change Request belum berstatus APPROVED.'];
    }

    $existing = $pdo->prepare("
        SELECT docId
        FROM knowledge_base
        WHERE changeRequestLink = :changeId
        LIMIT 1
    ");
    $existing->execute(['changeId' => $changeId]);
    $existingDocId = $existing->fetchColumn();

    if ($existingDocId) {
        if (($cr['associatedKnowledge'] ?? '') !== $existingDocId) {
            $updateCr = $pdo->prepare("UPDATE change_requests SET associatedKnowledge = :docId WHERE changeId = :changeId");
            $updateCr->execute(['docId' => $existingDocId, 'changeId' => $changeId]);
        }

        return ['created' => false, 'docId' => $existingDocId, 'reason' => 'Knowledge Base sudah ada.'];
    }

    $repo = kb_find_repository_match($pdo, $cr);
    if (!$repo) {
        return ['created' => false, 'reason' => 'Tidak ada pasangan WBS dan kode risiko di knowledge_repository.'];
    }

    $docId = kb_next_doc_id($pdo);
    $documentName = sprintf(
        'Lesson Learned %s - %s',
        $cr['changeId'],
        $repo['risk_nama'] ?: $repo['risk_kode']
    );

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
            applied_solution
        ) VALUES (
            :docId,
            :repository_reference_id,
            :changeDate,
            :documentName,
            'Lesson Learned',
            :submittedBy,
            'PUBLISHED',
            :description,
            :changeRequestLink,
            :riskVariableLink,
            :documentFile,
            :actual_impact,
            :applied_solution
        )
    ");

    $insert->execute([
        'docId' => $docId,
        'repository_reference_id' => $repo['id'],
        'changeDate' => $cr['approvalDate'] ?: $cr['changeDate'],
        'documentName' => $documentName,
        'submittedBy' => $cr['submittedBy'] ?: 'Project Manager',
        'description' => kb_build_lesson_description($cr, $repo),
        'changeRequestLink' => $cr['changeId'],
        'riskVariableLink' => $repo['risk_kode'],
        'documentFile' => $cr['photoEvidence'] ?? null,
        'actual_impact' => kb_build_actual_impact($cr),
        'applied_solution' => trim(($repo['saran'] ?? '') . (!empty($cr['approvalNotes']) ? "\n\nCatatan PM: " . $cr['approvalNotes'] : '')),
    ]);

    $updateCr = $pdo->prepare("UPDATE change_requests SET associatedKnowledge = :docId WHERE changeId = :changeId");
    $updateCr->execute(['docId' => $docId, 'changeId' => $cr['changeId']]);

    return ['created' => true, 'docId' => $docId, 'repositoryId' => $repo['id']];
}

function kb_sync_approved_lessons(PDO $pdo) {
    $stmt = $pdo->query("
        SELECT changeId
        FROM change_requests
        WHERE UPPER(TRIM(COALESCE(status, ''))) = 'APPROVED'
        ORDER BY COALESCE(approvalDate, changeDate), changeId
    ");

    $created = [];
    $skipped = [];

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $changeId) {
        $result = kb_create_lesson_from_change_request($pdo, $changeId);
        if (!empty($result['created'])) {
            $created[] = ['changeId' => $changeId, 'docId' => $result['docId']];
        } else {
            $skipped[] = [
                'changeId' => $changeId,
                'docId' => $result['docId'] ?? null,
                'reason' => $result['reason'] ?? 'Dilewati',
            ];
        }
    }

    return ['created' => $created, 'skipped' => $skipped];
}

