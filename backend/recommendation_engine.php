<?php
// ========================================================
// WYLCORE / RQ12 — recommendation_engine.php
// T2: CR + Risk -> Recommendation Contract Enforcement
// BAB XII § 12.5.4 (Prioritas P0-05)
// ========================================================

require_once __DIR__ . '/wbs_validator.php';

/**
 * Extract normalized risk code (e.g. 'R3.2 - Perubahan...' -> 'R3.2', 'R-WIND-01' -> 'R-WIND-01').
 */
function risk_extract_code(?string $value): string {
    $value = trim((string)($value ?? ''));
    if ($value === '') {
        return '';
    }

    if (preg_match('/^([A-Z0-9_\-]+)/i', $value, $matches)) {
        return strtoupper($matches[1]);
    }

    return $value;
}

/**
 * Find recommendation candidates strictly matching WBS code and Risk code.
 *
 * Enforces contract outcomes:
 * - MATCH: Exactly 1 repository candidate found.
 * - NO_MATCH: 0 repository candidate found; returns explicit abstention.
 * - AMBIGUOUS: >1 repository candidates found; returns all candidates with justified tie rule.
 * - INVALID_RULE: Missing or malformed WBS / Risk code.
 */
function rec_match_candidates(PDO $pdo, ?string $wbsInput, ?string $riskInput): array {
    $wbsCode = wbs_extract_code($wbsInput);
    $riskCode = risk_extract_code($riskInput);

    if ($wbsCode === '' || $riskCode === '') {
        return [
            'status' => 'INVALID_RULE',
            'wbs_code' => $wbsCode,
            'risk_code' => $riskCode,
            'candidate_count' => 0,
            'candidates' => [],
            'selected_rule' => null,
            'tie_rule' => null,
            'message' => 'Kode WBS atau variabel risiko tidak valid [INVALID_RULE].'
        ];
    }

    // Exact match on normalized codes (NO fuzzy substring truncating)
    $stmt = $pdo->prepare("
        SELECT id, wbs_kode, wbs_nama, risk_kode, risk_nama, risk_kategori, insight, saran
        FROM knowledge_repository
        WHERE TRIM(wbs_kode) = :wbs_code
          AND TRIM(risk_kode) = :risk_code
        ORDER BY id ASC
    ");
    $stmt->execute([
        'wbs_code' => $wbsCode,
        'risk_code' => $riskCode
    ]);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = count($candidates);

    if ($count === 0) {
        return [
            'status' => 'NO_MATCH',
            'wbs_code' => $wbsCode,
            'risk_code' => $riskCode,
            'candidate_count' => 0,
            'candidates' => [],
            'selected_rule' => null,
            'tie_rule' => null,
            'message' => 'Tidak ditemukan rekomendasi di repository untuk pasangan WBS dan risiko ini [NO_MATCH]. Diperlukan peninjauan mandiri secara saksama.'
        ];
    }

    if ($count === 1) {
        return [
            'status' => 'MATCH',
            'wbs_code' => $wbsCode,
            'risk_code' => $riskCode,
            'candidate_count' => 1,
            'candidates' => $candidates,
            'selected_rule' => $candidates[0],
            'tie_rule' => null,
            'message' => 'Rekomendasi repository ditemukan secara tepat [MATCH].'
        ];
    }

    // Ambiguity handling (count > 1)
    // T2-03: AMBIGUOUS or deterministic justified tie rule; never silently arbitrary row
    return [
        'status' => 'AMBIGUOUS',
        'wbs_code' => $wbsCode,
        'risk_code' => $riskCode,
        'candidate_count' => $count,
        'candidates' => $candidates,
        'selected_rule' => $candidates[0], // deterministic lowest-id tie breaker
        'tie_rule' => 'JUSTIFIED_DETERMINISTIC_MIN_ID',
        'message' => sprintf(
            'Ditemukan %d kandidat aturan yang sama untuk pasangan %s / %s [AMBIGUOUS]. Memerlukan verifikasi kurator.',
            $count,
            $wbsCode,
            $riskCode
        )
    ];
}

