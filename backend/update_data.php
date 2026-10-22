<?php
header('Content-Type: application/json');

require_once __DIR__ . '/db_user.php';
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/wbs_validator.php';

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
    
    $currentUser = auth_require_role(['Site Engineer', 'Project Manager', 'Admin']);

    $changeId = $_POST['changeId'] ?? '';

    if (empty($changeId)) {
        echo json_encode(["status" => "error", "message" => "Change ID wajib disertakan."]);
        exit();
    }

    // 🛑 VALIDASI BACKEND: Cek data saat ini di database
    $stmtCheck = $pdo->prepare("SELECT status, projectArea, submittedBy, photoEvidence, lock_version FROM change_requests WHERE changeId = :changeId");
    $stmtCheck->execute(['changeId' => $changeId]);
    $currentData = $stmtCheck->fetch();

    if (!$currentData) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Data tidak ditemukan di database."]);
        exit();
    }

    $projectArea = !empty($_POST['projectArea']) ? trim($_POST['projectArea']) : $currentData['projectArea'];
    auth_require_project_access($pdo, $projectArea, $currentUser);

    // T1: Validasi WBS jika diubah atau diperbarui
    $wbsCandidate = !empty($_POST['wbsLevel5']) ? $_POST['wbsLevel5'] : ($_POST['wbsLevel4'] ?? '');
    if ($wbsCandidate !== '') {
        $wbsValidation = wbs_validate_reference($pdo, $projectArea, $wbsCandidate);
        if (!$wbsValidation['valid']) {
            http_response_code(422);
            echo json_encode([
                "status" => "error",
                "error_code" => $wbsValidation['contract_status'], // INVALID_WBS atau CROSS_PROJECT
                "message" => $wbsValidation['error']
            ]);
            exit();
        }
    }

    // Proteksi utama: data APPROVED hanya bisa dioverride dari Admin
    $currentStatus = strtoupper($currentData['status']);
    $isAdminOverrideApproved = ($_POST['adminOverrideApproved'] ?? '') === '1';
    if ($currentStatus === 'APPROVED' && !$isAdminOverrideApproved && $currentUser['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "error_code" => "INVALID_STATE",
            "message" => "Akses ditolak. Data berstatus APPROVED tidak dapat dimodifikasi tanpa override Admin!"
        ]);
        exit();
    }

    // 🔄 ALUR STATUS: Jika status sebelumnya REJECTED, otomatis kembalikan ke PENDING setelah di-edit
    $newStatus = $currentStatus;
    if ($currentStatus === 'REJECTED') {
        $newStatus = 'PENDING';
    }

    // 3. PROSES INPUT FILE BUKTI BARU (Jika Ada)
    $target_dir = __DIR__ . "/../uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $photo_evidence = $currentData['photoEvidence'];

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
            $photo_evidence = implode(',', $uploaded_files);
        }
    }

    $changeDrivers = isset($_POST['changeDrivers']) ? (is_array($_POST['changeDrivers']) ? implode(',', $_POST['changeDrivers']) : $_POST['changeDrivers']) : null;
    $locationFormatColumn = changeRequestColumnExists($pdo, 'locationFormat')
        ? 'locationFormat'
        : (changeRequestColumnExists($pdo, 'location_format') ? 'location_format' : null);
    $locationFormatSql = $locationFormatColumn ? "{$locationFormatColumn}    = :locationFormat,\n                " : "";

    // 4. QUERY UPDATE SQL dengan optimistic lock version increment
    $sql = "UPDATE change_requests SET 
                changeDate        = :changeDate,
                submittedBy       = :submittedBy,
                wbsLevel4         = :wbsLevel4,
                wbsLevel5         = :wbsLevel5,
                wbsLevel6         = :wbsLevel6,
                wbs_version       = :wbs_version,
                changeCategory    = :changeCategory,
                priority          = :priority,
                risk              = :risk,
                projectArea       = :projectArea,
                $locationFormatSql
                location          = :location,
                bimObjectId       = :bimObjectId,
                bim_model_urn     = :bim_model_urn,
                bim_model_version = :bim_model_version,
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
                status            = :status,
                lock_version      = lock_version + 1
            WHERE changeId = :changeId";

    try {
        $stmt = $pdo->prepare($sql);
        $params = [
            'changeDate'        => $_POST['changeDate'] ?? date('Y-m-d'),
            'submittedBy'       => $_POST['submittedBy'] ?? $currentData['submittedBy'],
            'wbsLevel4'         => $_POST['wbsLevel4'] ?? null,
            'wbsLevel5'         => $_POST['wbsLevel5'] ?? null,
            'wbsLevel6'         => $_POST['wbsLevel6'] ?? null,
            'wbs_version'       => $wbsValidation['version'] ?? 'v1.0',
            'changeCategory'    => $_POST['changeCategory'] ?? null,
            'priority'          => $_POST['priority'] ?? null,
            'risk'              => $_POST['risk'] ?? null,
            'projectArea'       => $projectArea,
            'location'          => $_POST['location'] ?? null,
            'bimObjectId'       => $_POST['bimObjectId'] ?? null,
            'bim_model_urn'     => $_POST['bim_model_urn'] ?? null,
            'bim_model_version' => $_POST['bim_model_version'] ?? 'v1',
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
                "contract_status" => "VALIDATED_REFERENCE",
                "message" => "Data change request berhasil diperbarui." . ($currentStatus === 'REJECTED' ? " Status dikembalikan ke PENDING untuk ditinjau ulang." : "")
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal mengeksekusi pembaruan data ke database."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Metode tidak diizinkan. Gunakan POST."]);
}
?>