<?php

// Koneksi hybrid: otomatis memakai database lokal atau hosting.
// APP_ENV=local/production dapat digunakan untuk memaksa mode tertentu.
$appEnv = strtolower((string) (getenv('APP_ENV') ?: ''));
$requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$hostName = (string) (parse_url('http://' . $requestHost, PHP_URL_HOST) ?: $requestHost);
$endsWith = static function ($value, $suffix) {
    return $suffix === '' || substr($value, -strlen($suffix)) === $suffix;
};

$localHosts = ['localhost', '127.0.0.1', '::1'];
$isLocalHost = in_array($hostName, $localHosts, true)
    || $endsWith($hostName, '.test')
    || $endsWith($hostName, '.local');

// Eksekusi melalui CLI diasumsikan sebagai lingkungan lokal.
$isLocal = $appEnv === 'local'
    || $appEnv === 'development'
    || ($appEnv !== 'production' && $appEnv !== 'live' && ($isLocalHost || PHP_SAPI === 'cli'));

$localConfig = [
    'host' => 'localhost',
    'port' => '3306',
    'name' => 'db_data_proyek',
    'user' => 'root',
    'pass' => '',
];

$hostingConfig = [
    'host' => 'localhost',
    'port' => '3306',
    'name' => 'u646470441_db_gUrYGDQD',
    'user' => 'u646470441_usr_gUrYGDQD',
    'pass' => '.C8.Q!nJa2KNf$5',
];

$config = $isLocal ? $localConfig : $hostingConfig;

// Environment variable mengutamakan konfigurasi server tanpa perlu mengubah kode.
$host = getenv('DB_HOST') ?: $config['host'];
$port = getenv('DB_PORT') ?: $config['port'];
$db = getenv('DB_NAME') ?: $config['name'];
$user = getenv('DB_USER') ?: $config['user'];
$pass = getenv('DB_PASS');
$pass = $pass !== false ? $pass : $config['pass'];
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''))");
} catch (PDOException $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }

    echo json_encode([
        'status' => 'error',
        'message' => 'Koneksi database gagal (' . ($isLocal ? 'Lokal' : 'Hosting') . '): ' . $e->getMessage(),
    ]);
    exit;
}
