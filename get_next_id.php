<?php
header('Content-Type: application/json');

$host = 'localhost';
$db   = 'db_data_proyek'; 
$user = 'root';
$pass = ''; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Hitung total record yang ada di database saat ini
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM change_requests");
    $row = $stmt->fetch();
    
    $totalKeseluruhan = $row['total']; // <--- Simpan total aslinya
    $nextNumber = $totalKeseluruhan + 1;
    
    // Format angka agar menjadi 3 digit (contoh: 1 -> 001, 12 -> 012)
    $formattedNumber = str_pad($nextNumber, 3, "0", STR_PAD_LEFT);
    
    // Gabungkan dengan prefix 'CR-'
    $nextChangeId = "CR-" . $formattedNumber;
    
    echo json_encode([
        'status' => 'success',
        'next_id' => $nextChangeId,
        'total_all' => $totalKeseluruhan // <--- INI TAMBAHANNYA
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal membuat nomor urut: ' . $e->getMessage()
    ]);
}
?>