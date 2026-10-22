<?php
// ========================================================
// WYLCORE / RQ12 — wbs_validator.php
// T1: WBS -> CR Information Contract Enforcement
// BAB XII §§ 12.2.2, 12.4.4, 12.5.1 (Prioritas P1-09)
// ========================================================

/**
 * Extract normalized code from WBS string.
 * Handles forms like:
 * - '1.7.2.1 Pembuatan kantor K3' -> '1.7.2.1'
 * - 'W-ERECT-001' -> 'W-ERECT-001'
 * - '1.1.1.1' -> '1.1.1.1'
 */
function wbs_extract_code(?string $value): string {
    $value = trim((string)($value ?? ''));
    if ($value === '') {
        return '';
    }

    // Pattern 1: Alphanumeric prefix like W-ERECT-001 or PRJ-WBS-12
    if (preg_match('/^([A-Z0-9_\-]+)/i', $value, $matches)) {
        // If it starts with numeric dotted like 1.7.2.1
        if (preg_match('/^(\d+(?:\.\d+)+)/', $value, $numMatches)) {
            return $numMatches[1];
        }
        return strtoupper($matches[1]);
    }

    // Pattern 2: Dotted decimal like 1.7.2.1
    if (preg_match('/^(\d+(?:\.\d+)+)/', $value, $matches)) {
        return $matches[1];
    }

    return $value;
}

/**
 * Validate WBS reference against controlled project repository and dictionary.
 * Enforces:
 * - VALIDATED_REFERENCE: Code exists in project dictionary.
 * - INVALID_WBS: Code does not exist anywhere in controlled reference.
 * - CROSS_PROJECT: Code belongs to a different project; mutation forbidden.
 *
 * @return array ['valid' => bool, 'contract_status' => string, 'code' => string, 'version' => string, 'error' => ?string]
 */
function wbs_validate_reference(PDO $pdo, string $projectId, ?string $wbsInput): array {
    $code = wbs_extract_code($wbsInput);

    if ($code === '') {
        return [
            'valid' => false,
            'contract_status' => 'INVALID_WBS',
            'code' => '',
            'version' => 'v1.0',
            'error' => 'Kode WBS tidak boleh kosong.'
        ];
    }

    // 1. Check if the code belongs to a DIFFERENT project (Cross-project mutation guard)
    $stmtOther = $pdo->prepare("
        SELECT project_id, version, wbs_name
        FROM wbs_dictionary
        WHERE wbs_code = ? AND project_id <> ?
        LIMIT 1
    ");
    $stmtOther->execute([$code, $projectId]);
    $otherProjectRow = $stmtOther->fetch(PDO::FETCH_ASSOC);

    // 2. Check if the code is valid for THIS project in wbs_dictionary
    $stmtThis = $pdo->prepare("
        SELECT project_id, wbs_code, wbs_name, version, is_active
        FROM wbs_dictionary
        WHERE wbs_code = ? AND project_id = ?
        LIMIT 1
    ");
    $stmtThis->execute([$code, $projectId]);
    $thisProjectRow = $stmtThis->fetch(PDO::FETCH_ASSOC);

    if ($thisProjectRow) {
        return [
            'valid' => true,
            'contract_status' => 'VALIDATED_REFERENCE',
            'code' => $thisProjectRow['wbs_code'],
            'wbs_name' => $thisProjectRow['wbs_name'],
            'version' => $thisProjectRow['version'],
            'error' => null
        ];
    }

    // If not found in this project, but exists in another project -> CROSS_PROJECT
    if ($otherProjectRow) {
        return [
            'valid' => false,
            'contract_status' => 'CROSS_PROJECT',
            'code' => $code,
            'version' => $otherProjectRow['version'],
            'error' => sprintf(
                'Akses silang WBS ditolak [CROSS_PROJECT]: WBS %s terdaftar pada project %s, bukan %s.',
                $code,
                $otherProjectRow['project_id'],
                $projectId
            )
        ];
    }

    // 3. Fallback check in knowledge_repository for standard global code
    $stmtRepo = $pdo->prepare("
        SELECT wbs_kode, wbs_nama
        FROM knowledge_repository
        WHERE TRIM(wbs_kode) = ?
        LIMIT 1
    ");
    $stmtRepo->execute([$code]);
    $repoRow = $stmtRepo->fetch(PDO::FETCH_ASSOC);

    if ($repoRow) {
        // Auto-register to project dictionary as active v1.0 reference
        $insert = $pdo->prepare("
            INSERT IGNORE INTO wbs_dictionary (project_id, wbs_code, wbs_level, wbs_name, version, is_active)
            VALUES (?, ?, 5, ?, 'v1.0', 1)
        ");
        $insert->execute([$projectId, $code, $repoRow['wbs_nama']]);

        return [
            'valid' => true,
            'contract_status' => 'VALIDATED_REFERENCE',
            'code' => $code,
            'wbs_name' => $repoRow['wbs_nama'],
            'version' => 'v1.0',
            'error' => null
        ];
    }

    // Not found anywhere in controlled reference
    return [
        'valid' => false,
        'contract_status' => 'INVALID_WBS',
        'code' => $code,
        'version' => 'v1.0',
        'error' => sprintf('Kode WBS "%s" tidak ditemukan pada basis data referensi terkontrol [INVALID_WBS].', $code)
    ];
}

/**
 * Reconstruct original decision WBS reference when source WBS changed (T1-04).
 */
function wbs_reconstruct_decision_reference(PDO $pdo, string $changeId): array {
    $stmt = $pdo->prepare("
        SELECT wbs_code_snapshot, wbs_version_snapshot, timestamp
        FROM decision_trace
        WHERE changeId = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$changeId]);
    $trace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trace || empty($trace['wbs_code_snapshot'])) {
        return [
            'status' => 'UNRESOLVED_VERSION',
            'message' => 'Versi WBS pada saat keputusan tidak dapat direkonstruksi.'
        ];
    }

    return [
        'status' => 'VALIDATED_REFERENCE',
        'wbs_code' => $trace['wbs_code_snapshot'],
        'wbs_version' => $trace['wbs_version_snapshot'],
        'decision_time' => $trace['timestamp']
    ];
}

