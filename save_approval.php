<?php
header('Content-Type: application/json');
// Hubungkan ke file koneksi PDO milik Anda
require 'db_user.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Menangkap data dari request POST JavaScript
    $changeId      = $_POST['changeId'] ?? '';
    $reviewId      = $_POST['reviewId'] ?? '';
    $approvalDate  = $_POST['approvalDate'] ?? '';
    $approvalNotes = $_POST['approvalNotes'] ?? '';
    
    // Nilai dari dropdown ('approve' atau 'reject') ditampung ke variabel $status
    $status        = $_POST['approvalDecision'] ?? ''; 

    // Validasi sederhana
    if (empty($changeId) || empty($status)) {
        echo json_encode(["status" => "error", "message" => "Change ID dan Keputusan tidak boleh kosong!"]);
        exit;
    }

    try {
        // Kolom approvalDecision dihapus, nilai keputusan langsung dimasukkan ke kolom status
        $sql = "UPDATE change_requests 
                SET reviewId = ?, 
                    approvalDate = ?, 
                    approvalNotes = ?,
                    status = ?
                WHERE changeId = ?";
                
        $stmt = $pdo->prepare($sql);
        
        // Eksekusi dengan urutan parameter yang sesuai dengan tanda tanya (?) di atas
        $success = $stmt->execute([
            $reviewId, 
            $approvalDate, 
            $approvalNotes, 
            $status, // Mengisi langsung nilai 'approve' atau 'reject' ke kolom status
            $changeId
        ]);
        
        if ($success) {
            echo json_encode(["status" => "success", "message" => "Keputusan berhasil disimpan langsung ke kolom status!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal memperbarui data status di database."]);
        }
        
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Metode tidak diizinkan. Gunakan POST."]);
}
?>