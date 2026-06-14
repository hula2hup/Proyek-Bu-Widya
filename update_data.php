<?php
header('Content-Type: application/json');

// 1. KONFIGURASI KONEKSI DATABASE
$host = 'localhost';
$db   = 'db_data_proyek';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Koneksi database gagal: " . $e->getMessage()]);
    exit();
}

// 2. PROSES UPDATE DATA (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $changeId = $_POST['changeId'] ?? '';

    if (empty($changeId)) {
        echo json_encode(["status" => "error", "message" => "Change ID wajib disertakan."]);
        exit();
    }

    // 🛑 VALIDASI BACKEND: Cek status data saat ini di database sebelum eksekusi!
    $stmtCheck = $pdo->prepare("SELECT status, photoEvidence FROM change_requests WHERE changeId = :changeId");
    $stmtCheck->execute(['changeId' => $changeId]);
    $currentData = $stmtCheck->fetch();

    if (!$currentData) {
        echo json_encode(["status" => "error", "message" => "Data tidak ditemukan di database."]);
        exit();
    }

    // Proteksi utama: Jika status sudah APPROVED, tolak update apa pun dari luar!
    $currentStatus = strtoupper($currentData['status']);
    if ($currentStatus === 'APPROVED') {
        echo json_encode(["status" => "error", "message" => "Akses ditolak. Data berstatus APPROVED tidak dapat dimodifikasi!"]);
        exit();
    }

    // 🔄 ALUR STATUS: Jika status sebelumnya REJECTED, otomatis kembalikan ke PENDING setelah di-edit agar bisa ditinjau ulang
    $newStatus = $currentStatus;
    if ($currentStatus === 'REJECTED') {
        $newStatus = 'PENDING';
    }

    // 3. PROSES INPUT FILE BUKTI BARU (Jika Ada)
    $target_dir = "uploads/";
    $photo_evidence = $currentData['photoEvidence']; // Gunakan file lama sebagai default

    if (isset($_FILES['photo']) && !empty($_FILES['photo']['name'][0])) {
        $uploaded_files = [];
        foreach ($_FILES['photo']['name'] as $key => $name) {
            if ($_FILES['photo']['error'][$key] == 0) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $new_filename = time() . '_update_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'][$key], $target_dir . $new_filename)) {
                    $uploaded_files[] = $new_filename;
                }
            }
        }
        if (!empty($uploaded_files)) {
            $photo_evidence = implode(',', $uploaded_files); // Ganti string bukti dengan yang baru jika ada upload baru
        }
    }

    // 4. QUERY UPDATE SQL (Sudah dilengkapi dengan seluruh kolom Langkah 1 & Langkah 2 menu New Change)
    $sql = "UPDATE change_requests SET 
                changeDate        = :changeDate,
                wbsLevel4         = :wbsLevel4,
                wbsLevel5         = :wbsLevel5,
                wbsLevel6         = :wbsLevel6,
                changeCategory    = :changeCategory,
                priority          = :priority,
                risk              = :risk,
                projectArea       = :projectArea,
                location          = :location,
                bimObjectId       = :bimObjectId,
                riskCategory      = :riskCategory,       -- 🆕 Sebelumnya Kurang
                riskVariable      = :riskVariable,       -- 🆕 Sebelumnya Kurang
                description       = :description,
                changeType        = :changeType,         -- 🆕 Sebelumnya Kurang
                siteCondition     = :siteCondition,       -- 🆕 Sebelumnya Kurang
                ownerRequest      = :ownerRequest,       -- 🆕 Sebelumnya Kurang
                materialChange    = :materialChange,     -- 🆕 Sebelumnya Kurang
                methodChange      = :methodChange,       -- 🆕 Sebelumnya Kurang
                scheduleChange    = :scheduleChange,     -- 🆕 Sebelumnya Kurang
                safetyChange      = :safetyChange,       -- 🆕 Sebelumnya Kurang
                impactArea        = :impactArea,         -- 🆕 Sebelumnya Kurang
                descriptionDetail = :descriptionDetail,
                photoEvidence     = :photoEvidence,
                status            = :status              -- 🔄 Pembaruan status otomatis (REJECTED -> PENDING)
            WHERE changeId = :changeId";

    try {
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            'changeDate'        => $_POST['changeDate'] ?? date('Y-m-d'),
            'wbsLevel4'         => $_POST['wbsLevel4'] ?? null,
            'wbsLevel5'         => $_POST['wbsLevel5'] ?? null,
            'wbsLevel6'         => $_POST['wbsLevel6'] ?? null,
            'changeCategory'    => $_POST['changeCategory'] ?? null,
            'priority'          => $_POST['priority'] ?? null,
            'risk'              => $_POST['risk'] ?? null,
            'projectArea'       => $_POST['projectArea'] ?? null,
            'location'          => $_POST['location'] ?? null,
            'bimObjectId'       => $_POST['bimObjectId'] ?? null,
            'riskCategory'      => $_POST['riskCategory'] ?? null,       // 🆕 Dilengkapi
            'riskVariable'      => $_POST['riskVariable'] ?? null,       // 🆕 Dilengkapi
            'description'       => $_POST['description'] ?? null,
            'changeType'        => $_POST['changeType'] ?? null,         // 🆕 Dilengkapi
            'siteCondition'     => $_POST['siteCondition'] ?? null,       // 🆕 Dilengkapi
            'ownerRequest'      => $_POST['ownerRequest'] ?? null,       // 🆕 Dilengkapi
            'materialChange'    => $_POST['materialChange'] ?? null,     // 🆕 Dilengkapi
            'methodChange'      => $_POST['methodChange'] ?? null,       // 🆕 Dilengkapi
            'scheduleChange'    => $_POST['scheduleChange'] ?? null,     // 🆕 Dilengkapi
            'safetyChange'      => $_POST['safetyChange'] ?? null,       // 🆕 Dilengkapi
            'impactArea'        => $_POST['impactArea'] ?? null,         // 🆕 Dilengkapi
            'descriptionDetail' => $_POST['descriptionDetail'] ?? null,
            'photoEvidence'     => $photo_evidence,
            'status'            => $newStatus,                           // 🔄 Mengikat status baru
            'changeId'          => $changeId
        ]);

        if ($success) {
            echo json_encode([
                "status" => "success", 
                "message" => "Data change request berhasil diperbarui." . ($currentStatus === 'REJECTED' ? " Status dikembalikan ke PENDING untuk ditinjau ulang." : "")
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal mengeksekusi pembaruan data ke database."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
}
?>