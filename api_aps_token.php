<?php
// File: api_aps_token.php
// GET — mengembalikan APS 2-legged access token pakai kredensial server-side dari
// .env (FORGE_CLIENT_ID / FORGE_CLIENT_SECRET). Tidak perlu input dari client lagi.
require_once __DIR__ . '/env.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$clientId     = getenv('FORGE_CLIENT_ID');
$clientSecret = getenv('FORGE_CLIENT_SECRET');

header('Content-Type: application/json');
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
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($code ?: 500);
echo $res;
