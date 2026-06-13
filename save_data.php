<?php
// 1. KONFIGURASI DATABASE (Laragon Default)
$host = 'localhost';
$db   = 'db_data_proyek'; // Pastikan nama database ini sudah Anda buat di phpMyAdmin Laragon
$user = 'root';
$pass = ''; // Default Laragon adalah kosong/tanpa password
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

    $file_sql_eksternal = 'data_proyek.sql'; // Nama file SQL kamu

    if (file_exists($file_sql_eksternal)) {
        // Membaca seluruh text/query di dalam file setup.sql
        $query_dari_file = file_get_contents($file_sql_eksternal);
        
        // Mengeksekusi query tersebut ke MySQL Laragon
        $pdo->exec($query_dari_file);
    } else {
        die("Error: File script SQL '$file_sql_eksternal' tidak ditemukan!");
    }
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// 2. PROSES DATA SAAT FORM DI-SUBMIT (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Tentukan direktori penyimpanan file upload (bukti foto/dokumen)
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // Proses multi-upload untuk file 'photo' (sesuai input id="photo" di HTML)
    $uploaded_files = [];
    if (isset($_FILES['photo']) && !empty($_FILES['photo']['name'][0])) {
        foreach ($_FILES['photo']['name'] as $key => $name) {
            if ($_FILES['photo']['error'][$key] == 0) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                // Generate nama file unik agar tidak saling menimpa
                $new_filename = time() . '_evidence_' . uniqid() . '.' . $ext;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'][$key], $target_dir . $new_filename)) {
                    $uploaded_files[] = $new_filename;
                }
            }
        }
    }
    // Gabungkan nama-nama file menjadi string dipisahkan koma untuk disimpan di satu kolom database
    $photo_evidence = implode(',', $uploaded_files);

    // Ambil data input secara terstruktur dan berikan nilai fallback (null) jika kosong
    $data = [
        'changeId'          => $_POST['changeId'] ?? uniqid('CR-'),
        'changeDate'        => $_POST['changeDate'] ?? date('Y-m-d'),
        'submittedBy'       => $_POST['submittedBy'] ?? 'SITE ENGINEER',
        'wbsLevel4'          => $_POST['wbsLevel4'] ?? null,
        'wbsLevel5'      => $_POST['wbsLevel5'] ?? null,
        'wbsLevel6'    => $_POST['wbsLevel6'] ?? null,
        'changeCategory' => $_POST['changeCategory'] ?? null,
        'priority'           => $_POST['priority'] ?? null,
        'risk'               => $_POST['risk'] ?? null,
        'projectArea'       => $_POST['projectArea'] ?? null,
        'location'           => $_POST['location'] ?? null,
        'bimObjectId'      => $_POST['bimObjectId'] ?? null,
        'riskCategory'    => $_POST['riskCategory'] ?? null,
        'riskVariable'    => $_POST['riskVariable'] ?? null,
        'description'        => $_POST['description'] ?? null,
        'changeType'        => $_POST['changeType'] ?? null,
        'siteCondition'     => $_POST['siteCondition'] ?? null,
        'ownerRequest'      => $_POST['ownerRequest'] ?? null,
        'materialChange'    => $_POST['materialChange'] ?? null,
        'methodChange'      => $_POST['methodChange'] ?? null,
        'scheduleChange'    => $_POST['scheduleChange'] ?? null,
        'safetyChange'      => $_POST['safetyChange'] ?? null,
        'impactArea'        => $_POST['impactArea'] ?? null,
        'descriptionDetail' => $_POST['descriptionDetail'] ?? null,
        'photoEvidence'     => $photo_evidence,
        'status'             => 'PENDING' // Menandakan request awal berstatus pending
    ];

    // Query INSERT yang mendefinisikan kolom secara eksplisit (Sangat Aman & Fleksibel)
    $sql = "INSERT INTO change_requests (
                changeId, changeDate, submittedBy, wbsLevel4, wbsLevel5, 
                wbsLevel6, changeCategory, priority, risk, projectArea, 
                location, bimObjectId, riskCategory, riskVariable, description, 
                changeType, siteCondition, ownerRequest, materialChange, methodChange, 
                scheduleChange, safetyChange, impactArea, descriptionDetail, photoEvidence, status
            ) VALUES (
                :changeId, :changeDate, :submittedBy, :wbsLevel4, :wbsLevel5, 
                :wbsLevel6, :changeCategory, :priority, :risk, :projectArea, 
                :location, :bimObjectId, :riskCategory, :riskVariable, :description, 
                :changeType, :siteCondition, :ownerRequest, :materialChange, :methodChange, 
                :scheduleChange, :safetyChange, :impactArea, :descriptionDetail, :photoEvidence, :status
            )";

    try {
        $stmt = $pdo->prepare($sql);
        $saved = $stmt->execute($data);

        if ($saved) {
            // HAPUS ATAU KOMENTARI BARIS INI:
            // header("Location: site-engineer.html?status=success");
            
            // GANTI DENGAN INI (Balasan JSON untuk ditangkap JavaScript):
            echo json_encode(["status" => "success", "message" => "Data berhasil disimpan"]);
            exit();
        }
    } catch (PDOException $e) {
        // Balas juga dengan JSON jika ada error database
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        exit();
    }
}