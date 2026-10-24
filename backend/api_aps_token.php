<?php
// File: api_aps_token.php
// GET — mengembalikan APS 2-legged access token pakai kredensial server-side dari
// .env (FORGE_CLIENT_ID / FORGE_CLIENT_SECRET). Tidak perlu input dari client lagi.
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'method_not_allowed', 'message' => 'Metode tidak diizinkan.']);
    exit;
}
auth_require_login();

$clientId     = getenv('FORGE_CLIENT_ID');
$clientSecret = getenv('FORGE_CLIENT_SECRET');

if (!$clientId || !$clientSecret) {
    http_response_code(503);
    echo json_encode(['error' => 'not_configured', 'message' => 'FORGE_CLIENT_ID / FORGE_CLIENT_SECRET belum diset di .env']);
    exit;
}

$ch = curl_init('https://developer.api.autodesk.com/authentication/v2/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'grant_type' => 'client_credentials',
        'scope'      => 'data:read viewables:read',
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Basic ' . base64_encode("$clientId:$clientSecret"),
    ],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 30,
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($res === false || $error !== '') {
    http_response_code(502);
    echo json_encode(['error' => 'aps_unavailable', 'message' => 'Gagal menghubungi layanan Autodesk APS.']);
    exit;
}
http_response_code($code ?: 500);
echo $res;
