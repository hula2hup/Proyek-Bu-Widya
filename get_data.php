<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Mengizinkan request antar halaman jika diperlukan

// 1. KONFIGURASI DATABASE (Sesuaikan jika nanti di hosting)
$host = 'localhost';
$db   = 'db_data_proyek'; 
$user = 'root';
$pass = ''; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // 1. Tangkap user dari parameter URL, jika tidak ada pasang default kosong
    $currentUser = isset($_GET['user']) ? $_GET['user'] : '';

    // 2. Modifikasi Query SQL dengan Klausa WHERE
    // Hanya mengambil data jika kolom 'submittedBy' cocok dengan user yang login
    if (!empty($currentUser)) {
        $stmt = $pdo->prepare("SELECT * FROM change_requests WHERE submittedBy = :user ORDER BY changeDate DESC");
        $stmt->execute(['user' => $currentUser]);
    } else {
        // Jika parameter user kosong, jangan tampilkan data apa pun demi keamanan
        $stmt = $pdo->prepare("SELECT * FROM change_requests WHERE 1=0");
        $stmt->execute();
    }
    
    $results = $stmt->fetchAll();
    
    // 3. Kirim data sukses ke Frontend
    echo json_encode([
        'status' => 'success',
        'count' => count($results),
        'data' => $results
    ]);

} catch (PDOException $e) {
    // Kirim pesan error jika koneksi gagal
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal mengambil data dari database: ' . $e->getMessage()
    ]);
}
?>