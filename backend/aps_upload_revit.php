<?php
require_once __DIR__ . '/env.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const APS_API = 'https://developer.api.autodesk.com';
const MODEL_DERIVATIVE_TIMEOUT = 1800;

function apsRequest(string $method, string $url, string $token, ?array $body = null): array
{
    $headers = ['Authorization: Bearer ' . $token, 'Accept: application/json'];
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 120,
    ];
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_THROW_ON_ERROR);
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false || $error !== '') {
        throw new RuntimeException("Koneksi Autodesk gagal: $error");
    }
    $data = json_decode($response, true);
    if ($status < 200 || $status >= 300) {
        $message = is_array($data) ? ($data['developerMessage'] ?? $data['detail'] ?? $data['reason'] ?? '') : '';
        throw new RuntimeException("Autodesk API HTTP $status" . ($message !== '' ? ": $message" : '.'));
    }
    return ['status' => $status, 'data' => is_array($data) ? $data : []];
}

function getApsToken(string $clientId, string $clientSecret): string
{
    $curl = curl_init(APS_API . '/authentication/v2/token');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'client_credentials',
            'scope' => 'bucket:create bucket:read data:read data:write data:create viewables:read',
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode("$clientId:$clientSecret"),
        ],
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false || $error !== '') {
        throw new RuntimeException("Gagal menghubungi layanan autentikasi Autodesk: $error");
    }
    $data = json_decode($response, true);
    if ($status < 200 || $status >= 300 || empty($data['access_token'])) {
        throw new RuntimeException("Autodesk menolak token APS (HTTP $status). Periksa kredensial dan scopes aplikasi.");
    }
    return $data['access_token'];
}

function uploadRvt(string $path, string $objectName, string $bucketKey, string $token): void
{
    $stream = fopen($path, 'rb');
    if ($stream === false) {
        throw new RuntimeException("File tidak dapat dibuka: $path");
    }

    $url = APS_API . '/oss/v2/buckets/' . rawurlencode($bucketKey) . '/objects/' . rawurlencode($objectName);
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_UPLOAD => true,
        CURLOPT_INFILE => $stream,
        CURLOPT_INFILESIZE => filesize($path),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/octet-stream',
            'Accept: application/json',
        ],
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT => 1800,
    ]);
    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    fclose($stream);

    if ($response === false || $error !== '') {
        throw new RuntimeException("Unggah ke Autodesk gagal: $error");
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("Unggah ke Autodesk ditolak (HTTP $status).");
    }
}

function base64Url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function storeModelUrn(string $key, string $urn): void
{
    $path = __DIR__ . '/.env';
    if (!is_file($path) || !is_writable($path)) {
        throw new RuntimeException('Buat backend/.env dan pastikan dapat ditulis sebelum mengunggah model.');
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    $found = false;
    foreach ($lines as &$line) {
        if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/', $line)) {
            $line = $key . '=' . $urn;
            $found = true;
        }
    }
    unset($line);
    if (!$found) $lines[] = $key . '=' . $urn;

    $temporaryPath = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (file_put_contents($temporaryPath, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Gagal menyimpan referensi model ke backend/.env.');
    }
    if (!rename($temporaryPath, $path)) {
        unlink($temporaryPath);
        throw new RuntimeException('Gagal memperbarui backend/.env.');
    }
}

function processModel(string $path, string $modelId, string $bucketKey, string $token): void
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException("File RVT tidak ditemukan atau tidak dapat dibaca: $path");
    }
    if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'rvt') {
        throw new RuntimeException("File harus berekstensi .rvt: $path");
    }
    $size = filesize($path);
    if ($size === false || $size > 2 * 1024 * 1024 * 1024) {
        throw new RuntimeException("Ukuran file tidak valid atau melebihi batas 2 GB: $path");
    }

    $objectName = $modelId . '-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.rvt';
    $urn = 'urn:adsk.objects:os.object:' . $bucketKey . '/' . $objectName;
    $encodedUrn = base64Url($urn);

    printf("Mengunggah %s (%.1f MB)...%s", basename($path), $size / 1048576, PHP_EOL);
    uploadRvt($path, $objectName, $bucketKey, $token);
    apsRequest('POST', APS_API . '/modelderivative/v2/designdata/job', $token, [
        'input' => ['urn' => $encodedUrn],
        'output' => ['formats' => [['type' => 'svf2', 'views' => ['2d', '3d']]]],
    ]);

    $deadline = time() + MODEL_DERIVATIVE_TIMEOUT;
    do {
        sleep(10);
        $manifest = apsRequest(
            'GET',
            APS_API . '/modelderivative/v2/designdata/' . rawurlencode($encodedUrn) . '/manifest',
            $token
        )['data'];
        $status = strtolower((string)($manifest['status'] ?? ''));
        $progress = $manifest['progress'] ?? 'menunggu';
        printf("Konversi %s: %s (%s)%s", $modelId, $status ?: 'diproses', $progress, PHP_EOL);

        if ($status === 'success') {
            $envKey = $modelId === 'overpass' ? 'APS_MODEL_OVERPASS_URN' : 'APS_MODEL_UNDERPASS_URN';
            storeModelUrn($envKey, $urn);
            echo "Model $modelId selesai dan siap ditampilkan di Autodesk Viewer." . PHP_EOL;
            return;
        }
        if (in_array($status, ['failed', 'timeout'], true)) {
            $messages = [];
            foreach (($manifest['derivatives'] ?? []) as $derivative) {
                foreach (($derivative['messages'] ?? []) as $message) {
                    if (isset($message['message'])) $messages[] = $message['message'];
                }
            }
            throw new RuntimeException('Konversi ' . $modelId . ' gagal: ' . implode('; ', $messages));
        }
    } while (time() < $deadline);

    throw new RuntimeException("Batas waktu konversi model $modelId terlampaui; model mungkin masih diproses di APS.");
}

try {
    if ($argc !== 3) {
        throw new RuntimeException('Pemakaian: php backend/aps_upload_revit.php "<file-overpass.rvt>" "<file-underpass.rvt>"');
    }

    $clientId = getenv('FORGE_CLIENT_ID') ?: '';
    $clientSecret = getenv('FORGE_CLIENT_SECRET') ?: '';
    if ($clientId === '' || $clientSecret === '') {
        throw new RuntimeException('Atur FORGE_CLIENT_ID dan FORGE_CLIENT_SECRET di backend/.env terlebih dahulu.');
    }

    $token = getApsToken($clientId, $clientSecret);
    $bucketKey = getenv('APS_BUCKET_KEY') ?: 'wylcore-bimwbs-' . substr(hash('sha256', $clientId), 0, 16);
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,127}$/', $bucketKey)) {
        throw new RuntimeException('APS_BUCKET_KEY tidak sesuai format bucket APS.');
    }

    try {
        apsRequest('POST', APS_API . '/oss/v2/buckets', $token, [
            'bucketKey' => $bucketKey,
            'policyKey' => 'persistent',
        ]);
    } catch (RuntimeException $error) {
        if (!str_contains($error->getMessage(), 'HTTP 409')) throw $error;
    }

    processModel($argv[1], 'overpass', $bucketKey, $token);
    processModel($argv[2], 'underpass', $bucketKey, $token);
} catch (Throwable $error) {
    fwrite(STDERR, 'Gagal: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
