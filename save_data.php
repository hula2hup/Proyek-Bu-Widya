<?php
// Pastikan response selalu berupa JSON
header('Content-Type: application/json');

// 1. KONFIGURASI DATABASE (Laragon Default)
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
    // Membuat koneksi ke MySQL Laragon
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Balas dengan JSON jika koneksi database mati
    echo json_encode(["status" => "error", "message" => "Koneksi database gagal: " . $e->getMessage()]);
    exit();
}

// 2. PROSES DATA SAAT FORM DI-SUBMIT (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Tentukan direktori penyimpanan file upload (bukti foto/dokumen)
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // Proses multi-upload untuk file 'photo'
    // WAJIB: Di HTML harus menggunakan name="photo[]"
    $uploaded_files = [];
    if (isset($_FILES['photo']) && is_array($_FILES['photo']['name']) && !empty($_FILES['photo']['name'][0])) {
        foreach ($_FILES['photo']['name'] as $key => $name) {
            if ($_FILES['photo']['error'][$key] == 0) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $new_filename = time() . '_evidence_' . uniqid() . '.' . $ext;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'][$key], $target_dir . $new_filename)) {
                    $uploaded_files[] = $new_filename;
                }
            }
        }
    }
    // Gabungkan nama-nama file menjadi string dipisahkan koma
    $photo_evidence = implode(',', $uploaded_files);

    // Menangkap Array Checkbox 
    $changeDrivers = isset($_POST['changeDrivers']) ? (is_array($_POST['changeDrivers']) ? implode(',', $_POST['changeDrivers']) : $_POST['changeDrivers']) : null;

    // Ambil data input secara terstruktur
    $data = [
        'changeId'          => $_POST['changeId'] ?? uniqid('CR-'),
        'changeDate'        => $_POST['changeDate'] ?? date('Y-m-d'),
        'submittedBy'       => $_POST['submittedBy'] ?? 'SITE ENGINEER',
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
        'riskDescription'      => $_POST['riskDescription'] ?? null,
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
        'status'            => 'PENDING'
    ];

    // Query INSERT 
    $sql = "INSERT INTO change_requests (
                changeId, changeDate, submittedBy, wbsLevel4, wbsLevel5, 
                wbsLevel6, changeCategory, priority, risk, projectArea, 
                location, bimObjectId, riskCategory, riskVariable, riskDescription, description, 
                ownerRequest, changeDrivers, impactCost, impactTime, 
                impactScope, impactQuality, impactSafety, descriptionDetail, photoEvidence, status
            ) VALUES (
                :changeId, :changeDate, :submittedBy, :wbsLevel4, :wbsLevel5, 
                :wbsLevel6, :changeCategory, :priority, :risk, :projectArea, 
                :location, :bimObjectId, :riskCategory, :riskVariable, :riskDescription, :description, 
                :ownerRequest, :changeDrivers, :impactCost, :impactTime, 
                :impactScope, :impactQuality, :impactSafety, :descriptionDetail, :photoEvidence, :status
            )";

    try {
        $stmt = $pdo->prepare($sql);
        $saved = $stmt->execute($data);

        if ($saved) {
            echo json_encode(["status" => "success", "message" => "Data berhasil disimpan"]);
            exit();
        }
    } catch (PDOException $e) {
        // Balas dengan JSON jika ada error query (misal: tipe data salah, nama kolom tidak cocok)
        echo json_encode(["status" => "error", "message" => "Gagal menyimpan data: " . $e->getMessage()]);
        exit();
    }
} else {
    // Jika file diakses langsung dari URL tanpa disubmit
    echo json_encode(["status" => "error", "message" => "Metode tidak diizinkan. Gunakan POST."]);
    exit();
}
?>