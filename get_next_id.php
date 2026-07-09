<?php
header('Content-Type: application/json');

require_once __DIR__ . '/db_user.php';

try {
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
