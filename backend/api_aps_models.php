<?php
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan.']);
    exit;
}

auth_require_login();

$models = [
    [
        'id' => 'overpass',
        'label' => 'Overpass Lingkar Pekanbaru — STA 200+780',
        'fileName' => 'OVERPASS PROYEK LINGKAR PEKANBARU STA 200+780.rvt.rvt',
        'urn' => getenv('APS_MODEL_OVERPASS_URN') ?: null,
    ],
    [
        'id' => 'underpass',
        'label' => 'Underpass Betung-Jambi Seksi 4 — STA 161+650',
        'fileName' => 'JEMBATAN UNDERPASS PROYEK BETUNG JAMBI SEKSI 4 STA 161+650 (1).rvt',
        'urn' => getenv('APS_MODEL_UNDERPASS_URN') ?: null,
    ],
];

foreach ($models as &$model) {
    $model['configured'] = is_string($model['urn']) && $model['urn'] !== '';
}
unset($model);

echo json_encode(['status' => 'success', 'models' => $models]);
