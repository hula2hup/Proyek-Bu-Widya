<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. KONFIGURASI DATABASE (Laragon Default)
    $host = 'localhost';
    $db   = 'db_data_proyek'; // Ganti dengan nama database yang kamu buat di Laragon
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
        // Membuat koneksi ke MySQL
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        // 2. MENYIAPKAN DATA
        $id = uniqid();
        $timestamp = date('Y-m-d H:i:s');
        
        // Sesuaikan variabel di bawah ini dengan atribut 'name' pada tag <input> di HTML kamu
        // Contoh jika di HTML ada <input name="nama_proyek"> dan <input name="lokasi">
        $risk_id      = $_POST['risk_id'] ?? null;
        $risk_identification_date      = $_POST['risk_identification_date'] ?? null;
        $risk_owner_id      = $_POST['risk_owner_id'] ?? null;
        $wbs_code      = $_POST['wbs_code'] ?? null;
        $activity_name      = $_POST['activity_name'] ?? null;
        $critical_activity      = $_POST['critical_activity'] ?? null;
        $bim_object_id      = $_POST['bim_object_id'] ?? null;
        $bim_element_name      = $_POST['bim_element_name'] ?? null;
        $risk_location      = $_POST['risk_location'] ?? null;
        $risk_category      = $_POST['risk_category'] ?? null;
        $risk_subcategory      = $_POST['risk_subcategory'] ?? null;
        $risk_event      = $_POST['risk_event'] ?? null;
        $risk_cause      = $_POST['risk_cause'] ?? null;
        $risk_impact_description      = $_POST['risk_impact_description'] ?? null;
        $change_trigger_potential      = $_POST['change_trigger_potential'] ?? null;
        $probability_score      = $_POST['probability_score'] ?? null;
        $risk_score      = $_POST['risk_score'] ?? null;
        $risk_level      = $_POST['risk_level'] ?? null;
        $risk_priority      = $_POST['risk_priority'] ?? null;
        $mitigation_status      = $_POST['mitigation_status'] ?? null;
        $mitigation_plan      = $_POST['mitigation_plan'] ?? null;
        $mitigation_owner_id      = $_POST['mitigation_owner_id'] ?? null;
        $mitigation_due_date      = $_POST['mitigation_due_date'] ?? null;
        $mitigation_cost      = $_POST['mitigation_cost'] ?? null;
        $residual_probability      = $_POST['residual_probability'] ?? null;
        $residual_impact      = $_POST['residual_impact'] ?? null;
        $residual_risk_score      = $_POST['residual_risk_score'] ?? null;
        $early_warning_rule      = $_POST['early_warning_rule'] ?? null;
        $risk_status      = $_POST['risk_status'] ?? null;
        $generate_cr      = $_POST['generate_cr'] ?? null;
        $linked_cr_id      = $_POST['linked_cr_id'] ?? null;
        $related_knowledge_id      = $_POST['related_knowledge_id'] ?? null;
        $preventive_recommendation      = $_POST['preventive_recommendation'] ?? null;
        $risk_evidence      = $_POST['risk_evidence'] ?? null;
        $monitoring_notes      = $_POST['monitoring_notes'] ?? null;

        // 3. QUERY INSERT KE TABEL 'data_proyek_risk'
        // Sesuaikan nama kolom di dalam tanda kurung dengan kolom di database kamu
        $sql = "INSERT INTO data_proyek_risk (id, timestamp, risk_id, risk_identification_date, risk_owner_id, wbs_code, activity_name, critical_activity, bim_object_id, bim_element_name, risk_location, risk_category, risk_subcategory, risk_event, risk_cause, risk_impact_description, change_trigger_potential, probability_score, risk_score, risk_level, risk_priority, mitigation_status, mitigation_plan, mitigation_owner_id, mitigation_due_date, mitigation_cost, residual_probability, residual_impact, residual_risk_score, early_warning_rule, risk_status, generate_cr, linked_cr_id, related_knowledge_id, preventive_recommendation, risk_evidence, monitoring_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        // Eksekusi query dengan memasukkan data
        $saved = $stmt->execute([$id, $timestamp, $risk_id, $risk_identification_date, $risk_owner_id, $wbs_code, $activity_name, $critical_activity, $bim_object_id, $bim_element_name, $risk_location, $risk_category, $risk_subcategory, $risk_event, $risk_cause, $risk_impact_description, $change_trigger_potential, $probability_score, $risk_score, $risk_level, $risk_priority, $mitigation_status, $mitigation_plan, $mitigation_owner_id, $mitigation_due_date, $mitigation_cost, $residual_probability, $residual_impact, $residual_risk_score, $early_warning_rule, $risk_status, $generate_cr, $linked_cr_id, $related_knowledge_id, $preventive_recommendation, $risk_evidence, $monitoring_notes]);

        if ($saved) {
            // Jika berhasil, redirect kembali ke halaman utama
            header("Location: site-engineer.html?status=success");
            exit();
        } else {
            echo "Gagal menyimpan data.";
        }

    } catch (\PDOException $e) {
        // Menampilkan error jika koneksi atau query gagal
        die("Error Database: " . $e->getMessage());
    }
}
?>