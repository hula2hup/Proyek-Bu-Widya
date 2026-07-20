<?php
// File: api_aps_properties.php
// GET ?dbId=1234&token=xxx&urn=xxx
// Ambil properti elemen dari model APS (RVT hasil translate), lalu coba
// cocokkan dengan Change Request lokal via parameter "BIM Object ID" / "Mark"
// yang ditandai di Revit (kalau ada), supaya klik elemen di viewer APS
// bisa langsung terhubung ke Change Request yang sama seperti mode Three.js.
require_once __DIR__ . '/db_user.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$dbId  = $_GET['dbId']  ?? '';
$token = $_GET['token'] ?? '';
$urn   = $_GET['urn']   ?? '';

if (!$dbId || !$token || !$urn) {
    http_response_code(400);
    echo json_encode(['error' => 'dbId, token, urn required']);
    exit;
}

$encodedUrn = rtrim(strtr(base64_encode($urn), '+/', '-_'), '=');

$metaUrl = "https://developer.api.autodesk.com/modelderivative/v2/designdata/{$encodedUrn}/metadata";
$ch = curl_init($metaUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token"],
]);
$metaRes = json_decode(curl_exec($ch), true);
curl_close($ch);

$guid = $metaRes['data']['metadata'][0]['guid'] ?? null;

$props = null;
if ($guid) {
    $propsUrl = "https://developer.api.autodesk.com/modelderivative/v2/designdata/{$encodedUrn}/metadata/{$guid}/properties?objectid={$dbId}";
    $ch = curl_init($propsUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token"],
    ]);
    $props = json_decode(curl_exec($ch), true);
    curl_close($ch);
}

$element = $props['data']['collection'][0] ?? null;
$allProps = $element['properties'] ?? [];

// Cari parameter Revit yang menyimpan referensi BIM Object ID (mis. "BIM Object ID" atau "Mark")
$bimObjectId = null;
foreach ($allProps as $group) {
    foreach ($group as $key => $value) {
        if (in_array(strtolower($key), ['bim object id', 'mark'], true) && trim((string) $value) !== '') {
            $bimObjectId = trim((string) $value);
            break 2;
        }
    }
}

$linkedChanges = [];
if ($bimObjectId) {
    $stmt = $pdo->prepare("SELECT changeId, description, status, wbsLevel4, wbsLevel5, wbsLevel6 FROM change_requests WHERE bimObjectId = ? ORDER BY changeDate DESC");
    $stmt->execute([$bimObjectId]);
    $linkedChanges = $stmt->fetchAll();
}

// Kalau belum ada tag BIM Object ID, tetap tampilkan properti Revit asli yang
// relevan (Type Name, Volume, dan parameter konstruksi custom proyek seperti
// "2_Cons_*" / "0_BD_*" — vendor, tanggal fabrikasi/instalasi/inspeksi, dll)
// supaya panel di viewer tidak kosong/generik meski elemen belum di-tag WBS.
$detail = [];
foreach ($allProps as $groupName => $group) {
    foreach ($group as $key => $value) {
        $val = trim((string) $value);
        if ($val === '') continue;
        if ($groupName === 'Identity Data' && in_array($key, ['Type Name', 'Mark', 'Comments'], true)) {
            $detail[$key] = $val;
        } elseif ($groupName === 'Dimensions') {
            $detail[$key] = $val;
        } elseif ($groupName === 'Text' && (str_starts_with($key, '2_Cons_') || str_starts_with($key, '0_BD_'))) {
            $detail[$key] = $val;
        }
    }
}

echo json_encode([
    'dbId'          => $dbId,
    'name'          => $element['name'] ?? null,
    'externalId'    => $element['externalId'] ?? null,
    'properties'    => $allProps ?: null,
    'detail'        => $detail,
    'bimObjectId'   => $bimObjectId,
    'linkedChanges' => $linkedChanges,
]);
