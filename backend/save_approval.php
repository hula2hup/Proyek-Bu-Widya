<?php
header('Content-Type: application/json');
// Hubungkan ke file koneksi PDO milik Anda
require 'db_user.php'; 
require_once 'knowledge_base_auto.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Menangkap data dari request POST JavaScript
    $changeId      = $_POST['changeId'] ?? '';
    $reviewId      = $_POST['reviewId'] ?? '';
    $approvalDate  = $_POST['approvalDate'] ?? '';
    $approvalNotes = $_POST['approvalNotes'] ?? '';
    $costImpactRaw = $_POST['costImpact'] ?? '0';
    $timeImpactRaw = $_POST['timeImpact'] ?? '0';
    
    // Nilai dari dropdown ('PENDING', 'APPROVED', atau 'REJECTED') ditampung ke variabel $status
    $status        = $_POST['approvalDecision'] ?? ''; 

    // Validasi sederhana
    if (empty($changeId)) {
        echo json_encode(["status" => "error", "message" => "Change ID tidak boleh kosong!"]);
        exit;
    }
    
    if (empty($status)) {
        echo json_encode(["status" => "error", "message" => "Keputusan (Pending/Approved/Rejected) tidak boleh kosong!"]);
        exit;
    }
    
    if (empty($approvalDate)) {
        echo json_encode(["status" => "error", "message" => "Tanggal Keputusan tidak boleh kosong!"]);
        exit;
    }
    
    if (empty($reviewId)) {
        echo json_encode(["status" => "error", "message" => "Review ID tidak boleh kosong!"]);
        exit;
    }

    try {
        // Validasi format status
        $status = strtoupper($status);
        if (!in_array($status, ['PENDING', 'APPROVED', 'REJECTED'])) {
            echo json_encode(["status" => "error", "message" => "Status tidak valid. Gunakan PENDING, APPROVED, atau REJECTED."]);
            exit;
        }

        $costImpact = is_numeric($costImpactRaw) ? (float)$costImpactRaw : 0;
        $timeImpact = is_numeric($timeImpactRaw) ? (int)$timeImpactRaw : 0;
        if ($costImpact < 0 || $timeImpact < 0) {
            echo json_encode(["status" => "error", "message" => "Impact Analysis biaya dan waktu tidak boleh bernilai negatif."]);
            exit;
        }
        
        // Validasi format tanggal
        $dateTime = DateTime::createFromFormat('Y-m-d', $approvalDate);
        if ($dateTime === false) {
            echo json_encode(["status" => "error", "message" => "Format tanggal tidak valid. Gunakan format YYYY-MM-DD."]);
            exit;
        }
        
        $pdo->beginTransaction();

        // Update change_requests dengan data approval
        $sql = "UPDATE change_requests 
                SET reviewId = ?, 
                    approvalDate = ?, 
                    approvalNotes = ?,
                    costImpact = ?,
                    timeImpact = ?,
                    status = ?
                WHERE changeId = ?";
                
        $stmt = $pdo->prepare($sql);
        
        // Eksekusi dengan urutan parameter yang sesuai dengan tanda tanya (?) di atas
        $success = $stmt->execute([
            $reviewId, 
            $approvalDate, 
            $approvalNotes, 
            $costImpact,
            $timeImpact,
            $status, // PENDING, APPROVED, atau REJECTED
            $changeId
        ]);
        
        $existsStmt = $pdo->prepare("SELECT COUNT(*) FROM change_requests WHERE changeId = ?");
        $existsStmt->execute([$changeId]);
        $changeRequestExists = (int)$existsStmt->fetchColumn() > 0;

        if ($success && $changeRequestExists) {
            $knowledgeResult = null;
            if ($status === 'APPROVED') {
                $knowledgeResult = kb_create_lesson_from_change_request($pdo, $changeId);
            }

            $pdo->commit();

            $knowledgeMessage = '';
            if ($knowledgeResult) {
                $knowledgeMessage = !empty($knowledgeResult['docId'])
                    ? " Knowledge Base terkait: {$knowledgeResult['docId']}."
                    : " Knowledge Base belum dibuat: " . ($knowledgeResult['reason'] ?? 'tidak ada pasangan repository.');
            }

            echo json_encode([
                "status" => "success", 
                "message" => "Keputusan approval untuk {$changeId} berhasil disimpan dengan status {$status}!{$knowledgeMessage}",
                "knowledge" => $knowledgeResult
            ]);
        } else if (!$changeRequestExists) {
            $pdo->rollBack();
            echo json_encode(["status" => "error", "message" => "Change Request dengan ID {$changeId} tidak ditemukan di database."]);
        } else {
            $pdo->rollBack();
            echo json_encode(["status" => "error", "message" => "Gagal memperbarui data di database."]);
        }
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Metode tidak diizinkan. Gunakan POST."]);
}
?>
