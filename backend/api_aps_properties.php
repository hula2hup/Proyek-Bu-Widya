<?php
// ========================================================
// WYLCORE / RQ12 — api_aps_properties.php
// T3: BIM Object -> CR Information Contract API
// BAB XII § 12.5.6 (Prioritas P1-10)
// ========================================================

require_once __DIR__ . '/db_user.php';
require_once __DIR__ . '/aps_adapter.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$bimObjectId = trim($_GET['bimObjectId'] ?? '');
$dbId        = trim($_GET['dbId'] ?? '');
$token       = trim($_GET['token'] ?? '');
$urn         = trim($_GET['urn'] ?? '');
$projectId   = trim($_GET['project_id'] ?? $_GET['projectId'] ?? 'PRJ-001');
$simulateOffline = ($_GET['simulate_offline'] ?? '') === '1';

// T3 Contract Evaluation via aps_adapter
$res = aps_resolve_object(
    $pdo,
    $projectId,
    $bimObjectId,
    $dbId ?: null,
    $urn ?: null,
    $token ?: null,
    ['__SIMULATE_APS_OFFLINE' => $simulateOffline]
);

$statusCode = 200;
if ($res['status'] === 'OBJECT_NOT_FOUND') {
    $statusCode = 404;
} elseif ($res['status'] === 'APS_OFFLINE') {
    $statusCode = 503;
}

http_response_code($statusCode);
echo json_encode([
    'status' => $res['status'] === 'OBJECT_LINKED' ? 'success' : 'error',
    'contract_status' => $res['status'], // OBJECT_LINKED, OBJECT_NOT_FOUND, APS_OFFLINE
    'bimObjectId' => $res['bimObjectId'],
    'object' => $res['object'],
    'linkedChanges' => $res['linkedChanges'],
    'message' => $res['message']
]);
