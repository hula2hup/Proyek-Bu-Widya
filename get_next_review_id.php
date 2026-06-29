<?php
header('Content-Type: application/json');
// 1. Hubungkan ke file koneksi PDO milik Anda
require 'db_user.php'; 

try {
    // 2. Cari reviewId terakhir yang menggunakan format 'REV-PM-'
    $query = "SELECT reviewId FROM change_requests 
              WHERE reviewId LIKE 'REV-PM-%' 
              ORDER BY reviewId DESC LIMIT 1";
              
    $stmt = $pdo->query($query);
    $row = $stmt->fetch(PDO::FETCH_ASSOC); // Mengambil data dengan gaya PDO
    
    $nextId = "REV-PM-01"; // Default jika database masih kosong

    if ($row) {
        $lastId = $row['reviewId']; // Contoh: "REV-PM-02"
        
        // 3. Ambil angka di paling belakang (pecah berdasarkan karakter '-')
        $parts = explode('-', $lastId);
        $lastNumber = (int) end($parts); 
        
        // 4. Tambahkan 1, lalu format ulang menjadi 2 digit (03, 04, dst)
        $nextNumber = $lastNumber + 1;
        $paddedNumber = str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
        
        $nextId = "REV-PM-" . $paddedNumber;
    }

    echo json_encode(["status" => "success", "nextReviewId" => $nextId]);

} catch (PDOException $e) {
    // Tangkap error spesifik PDO jika terjadi masalah query
    echo json_encode(["status" => "error", "message" => "Query Error: " . $e->getMessage()]);
}
?>