<?php
header('Content-Type: application/json');

require_once __DIR__ . '/db_user.php';

function changeRequestColumnExists(PDO $pdo, string $columnName): bool {
    static $columns = null;
    if ($columns === null) {
        $columns = [];
        $stmt = $pdo->query("SHOW COLUMNS FROM change_requests");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[$column['Field']] = true;
        }
    }
    return isset($columns[$columnName]);
}

// 2. PROSES UPDATE DATA (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $changeId = $_POST['changeId'] ?? '';

    if (empty($changeId)) {
        echo json_encode(["status" => "error", "message" => "Change ID wajib disertakan."]);
        exit();
    }

    // 🛑 VALIDASI BACKEND: Cek status data saat ini di database sebelum eksekusi!
    $stmtCheck = $pdo->prepare("SELECT status, submittedBy, photoEvidence FROM change_requests WHERE changeId = :changeId");
    $stmtCheck->execute(['changeId' => $changeId]);
    $currentData = $stmtCheck->fetch();

    if (!$currentData) {
        echo json_encode(["status" => "error", "message" => "Data tidak ditemukan di database."]);
        exit();
    }

    // Proteksi utama: data APPROVED hanya bisa dioverride dari Admin.
    $currentStatus = strtoupper($currentData['status']);
    $isAdminOverrideApproved = ($_POST['adminOverrideApproved'] ?? '') === '1';
    if ($currentStatus === 'APPROVED' && !$isAdminOverrideApproved) {
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

    // ✅ PERBAIKAN DI SINI: Tambahkan is_array() agar tidak crash jika format form salah
    if (isset($_FILES['photo']) && is_array($_FILES['photo']['name']) && !empty($_FILES['photo']['name'][0])) {
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

    // Menangkap Array Checkbox dari frontend
    $changeDrivers = isset($_POST['changeDrivers']) ? (is_array($_POST['changeDrivers']) ? implode(',', $_POST['changeDrivers']) : $_POST['changeDrivers']) : null;
    $locationFormatColumn = changeRequestColumnExists($pdo, 'locationFormat')
        ? 'locationFormat'
        : (changeRequestColumnExists($pdo, 'location_format') ? 'location_format' : null);
    $locationFormatSql = $locationFormatColumn ? "{$locationFormatColumn}    = :locationFormat,\n                " : "";

    // 4. QUERY UPDATE SQL
    $sql = "UPDATE change_requests SET 
                changeDate        = :changeDate,
                submittedBy       = :submittedBy,
                wbsLevel4         = :wbsLevel4,
                wbsLevel5         = :wbsLevel5,
                wbsLevel6         = :wbsLevel6,
                changeCategory    = :changeCategory,
                priority          = :priority,
                risk              = :risk,
                projectArea       = :projectArea,
                $locationFormatSql
                location          = :location,
                bimObjectId       = :bimObjectId,
                riskCategory      = :riskCategory,
                riskVariable      = :riskVariable,
                riskDescription   = :riskDescription,
                description       = :description,
                ownerRequest      = :ownerRequest,
                changeDrivers     = :changeDrivers,
                impactCost        = :impactCost,
                impactTime        = :impactTime,
                impactScope       = :impactScope,
                impactQuality     = :impactQuality,
                impactSafety      = :impactSafety,
                descriptionDetail = :descriptionDetail,
                photoEvidence     = :photoEvidence,
                status            = :status
            WHERE changeId = :changeId";

    try {
        $stmt = $pdo->prepare($sql);
        $params = [
            'changeDate'        => $_POST['changeDate'] ?? date('Y-m-d'),
            'submittedBy'       => $_POST['submittedBy'] ?? $currentData['submittedBy'],
            'wbsLevel4'         => $_POST['wbsLevel4'] ?? null,
            'wbsLevel5'         => $_POST['wbsLevel5'] ?? null,
            'wbsLevel6'         => $_POST['wbsLevel6'] ?? null,
            'changeCategory'    => $_POST['changeCategory'] ?? null,
            'priority'          => $_POST['priority'] ?? null,
            'risk'              => $_POST['risk'] ?? null,
            'projectArea'       => $_POST['projectArea'] ?? null,
            'location'          => $_POST['location'] ?? null,
            'bimObjectId'       => $_POST['bimObjectId'] ?? null,
            'riskCategory'      => $_POST['riskCategory'] ?? null,
            'riskVariable'      => $_POST['riskVariable'] ?? null,
            'riskDescription'   => $_POST['riskDescription'] ?? null,
            'description'       => $_POST['description'] ?? null,
            'ownerRequest'      => $_POST['ownerRequest'] ?? null,
            'changeDrivers'     => $changeDrivers,
            'impactCost'        => $_POST['impactCost'] ?? null,
            'impactTime'        => $_POST['impactTime'] ?? null,
            'impactScope'       => $_POST['impactScope'] ?? null,
            'impactQuality'     => $_POST['impactQuality'] ?? null,
            'impactSafety'      => $_POST['impactSafety'] ?? null,
            'descriptionDetail' => $_POST['descriptionDetail'] ?? null,
            'photoEvidence'     => $photo_evidence,
            'status'            => $newStatus,                           
            'changeId'          => $changeId
        ];
        if ($locationFormatColumn) {
            $params['locationFormat'] = $_POST['locationFormat'] ?? null;
        }
        $success = $stmt->execute($params);

        if ($success) {
            echo json_encode([
                "status" => "success", 
                "message" => "Data change request berhasil diperbarui." . ($currentStatus === 'REJECTED' ? " Status dikembalikan ke PENDING untuk ditinjau ulang." : "") . ($currentStatus === 'APPROVED' && $isAdminOverrideApproved ? " Override admin untuk status APPROVED berhasil diterapkan." : "")
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal mengeksekusi pembaruan data ke database."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    // Jika diakses selain menggunakan metode POST
    echo json_encode(["status" => "error", "message" => "Metode tidak diizinkan. Gunakan POST."]);
}
?>
