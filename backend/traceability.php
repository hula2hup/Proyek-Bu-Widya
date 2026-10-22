<?php
// ========================================================
// WYLCORE / RQ12 — traceability.php
// Decision Provenance & Atomic Audit Trail Module
// BAB XII §§ 12.4.2, 12.5.2, 12.7 (Prioritas P0-07, P1-11, P1-14)
// ========================================================

/**
 * Record an immutable decision provenance entry.
 * Must be executed within the active database transaction of the decision.
 *
 * @throws Exception If trace insertion fails (to trigger outer transaction rollback)
 */
function trace_record_decision(
    PDO $pdo,
    string $changeId,
    string $projectId,
    string $decision,
    array $actor,
    ?string $decisionNotes,
    float $costImpact,
    int $timeImpact,
    array $context = []
): array {
    // Generate deterministic hash of provenance payload
    $timestamp = date('Y-m-d H:i:s');
    $hashPayload = json_encode([
        'changeId' => $changeId,
        'projectId' => $projectId,
        'decision' => $decision,
        'actorId' => $actor['id'] ?? 0,
        'actorUsername' => $actor['username'] ?? '',
        'costImpact' => $costImpact,
        'timeImpact' => $timeImpact,
        'wbsCode' => $context['wbs_code'] ?? null,
        'wbsVersion' => $context['wbs_version'] ?? 'v1.0',
        'riskCode' => $context['risk_code'] ?? null,
        'bimObject' => $context['bim_object'] ?? null,
        'bimModelUrn' => $context['bim_model_urn'] ?? null,
        'bimModelVersion' => $context['bim_model_version'] ?? 'v1',
        'timestamp' => $timestamp
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $txHash = hash('sha256', $hashPayload);

    // Simulated failure injection for T4-04 atomicity verification
    if (!empty($context['__SIMULATE_TRACE_FAILURE'])) {
        throw new PDOException("Simulated trace storage failure for atomicity testing [TX_FAILURE]");
    }

    $stmt = $pdo->prepare("
        INSERT INTO decision_trace (
            changeId, project_id, decision, actor_user_id, actor_username, actor_role,
            decision_notes, cost_impact, time_impact,
            wbs_code_snapshot, wbs_version_snapshot,
            risk_code_snapshot, bim_object_snapshot,
            bim_model_urn_snapshot, bim_model_version_snapshot,
            recommendation_id_snapshot, recommendation_status_snapshot,
            timestamp, tx_hash
        ) VALUES (
            :changeId, :project_id, :decision, :actor_user_id, :actor_username, :actor_role,
            :decision_notes, :cost_impact, :time_impact,
            :wbs_code, :wbs_version,
            :risk_code, :bim_object,
            :bim_model_urn, :bim_model_version,
            :recommendation_id, :recommendation_status,
            :timestamp, :tx_hash
        )
    ");

    $success = $stmt->execute([
        'changeId' => $changeId,
        'project_id' => $projectId,
        'decision' => $decision,
        'actor_user_id' => $actor['id'] ?? 0,
        'actor_username' => $actor['username'] ?? 'system',
        'actor_role' => $actor['role'] ?? 'System',
        'decision_notes' => $decisionNotes,
        'cost_impact' => $costImpact,
        'time_impact' => $timeImpact,
        'wbs_code' => $context['wbs_code'] ?? null,
        'wbs_version' => $context['wbs_version'] ?? 'v1.0',
        'risk_code' => $context['risk_code'] ?? null,
        'bim_object' => $context['bim_object'] ?? null,
        'bim_model_urn' => $context['bim_model_urn'] ?? null,
        'bim_model_version' => $context['bim_model_version'] ?? 'v1',
        'recommendation_id' => $context['recommendation_id'] ?? null,
        'recommendation_status' => $context['recommendation_status'] ?? null,
        'timestamp' => $timestamp,
        'tx_hash' => $txHash
    ]);

    if (!$success) {
        throw new PDOException("Gagal mencatat audit trace keputusan.");
    }

    $traceId = (int)$pdo->lastInsertId();

    return [
        'trace_id' => $traceId,
        'tx_hash' => $txHash,
        'timestamp' => $timestamp
    ];
}

/**
 * Reconstruct historical decision provenance for a Change Request.
 * Allows independent audit verification of original WBS/Risk/BIM context.
 */
function trace_get_provenance(PDO $pdo, string $changeId): ?array {
    $stmt = $pdo->prepare("
        SELECT *
        FROM decision_trace
        WHERE changeId = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$changeId]);
    $trace = $stmt->fetch(PDO::FETCH_ASSOC);

    return $trace ?: null;
}

