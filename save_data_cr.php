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
        $cr_id      = $_POST['cr_id'] ?? null;
        $project_id      = $_POST['project_id'] ?? null;
        $cr_date      = $_POST['cr_date'] ?? null;
        $requester_id      = $_POST['requester_id'] ?? null;
        $requester_role      = $_POST['requester_role'] ?? null;
        $change_category      = $_POST['change_category'] ?? null;
        $change_type      = $_POST['change_type'] ?? null;
        $change_trigger      = $_POST['change_trigger'] ?? null;
        $change_description      = $_POST['change_description'] ?? null;
        $root_cause      = $_POST['root_cause'] ?? null;
        $document_reference      = $_POST['document_reference'] ?? null;
        $document_link      = $_POST['document_link'] ?? null;
        $wbs_code      = $_POST['wbs_code'] ?? null;
        $activity_name      = $_POST['activity_name'] ?? null;
        $critical_path_status      = $_POST['critical_path_status'] ?? null;
        $bim_object_id      = $_POST['bim_object_id'] ?? null;
        $bim_element_name      = $_POST['bim_element_name'] ?? null;
        $object_location      = $_POST['object_location'] ?? null;
        $risk_id      = $_POST['risk_id'] ?? null;
        $risk_category      = $_POST['risk_category'] ?? null;
        $risk_score_at_cr      = $_POST['risk_score_at_cr'] ?? null;
        $estimated_time_impact_days      = $_POST['estimated_time_impact_days'] ?? null;
        $estimated_cost_impact      = $_POST['estimated_cost_impact'] ?? null;
        $quality_impact      = $_POST['quality_impact'] ?? null;
        $risk_score_change      = $_POST['risk_score_change'] ?? null;
        $affected_successors      = $_POST['affected_successors'] ?? null;
        $rework_potential      = $_POST['rework_potential'] ?? null;
        $impact_summary      = $_POST['impact_summary'] ?? null;
        $system_recommendation      = $_POST['system_recommendation'] ?? null;
        $alternative_actions      = $_POST['alternative_actions'] ?? null;
        $priority_level    = $_POST['priority_level'] ?? null;
        $cr_status      = $_POST['cr_status'] ?? null;
        $approval_level      = $_POST['approval_level'] ?? null;
        $approval_decision      = $_POST['approval_decision'] ?? null;
        $approval_notes      = $_POST['approval_notes'] ?? null;
        $approval_date      = $_POST['approval_date'] ?? null;
        $implementation_status      = $_POST['implementation_status'] ?? null;
        $implementation_progress      = $_POST['implementation_progress'] ?? null;
        $evidence_file      = $_POST['evidence_file'] ?? null;
        $actual_time_impact_days      = $_POST['actual_time_impact_days'] ?? null;
        $convert_to_knowledge      = $_POST['convert_to_knowledge'] ?? null;
        // Tambahkan field lainnya di sini jika ada...

        // 3. QUERY INSERT KE TABEL 'data_proyek_cr'
        // Sesuaikan nama kolom di dalam tanda kurung dengan kolom di database kamu
        $sql = "INSERT INTO data_proyek_cr (id, timestamp, cr_id, project_id, cr_date, requester_id, requester_role, change_category, change_type, change_trigger, change_description, root_cause, document_reference, document_link, wbs_code, activity_name, critical_path_status, bim_object_id, bim_element_name, object_location, risk_id, risk_category, risk_score_at_cr, estimated_time_impact_days, estimated_cost_impact, quality_impact, risk_score_change, affected_successors, rework_potential, impact_summary, system_recommendation, alternative_actions, priority_level, cr_status, approval_level, approval_decision, approval_notes, approval_date, implementation_status, implementation_progress, evidence_file, actual_time_impact_days, convert_to_knowledge) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        // Eksekusi query dengan memasukkan data
        $saved = $stmt->execute([$id, $timestamp, $cr_id, $project_id, $cr_date, $requester_id, $requester_role, $change_category, $change_type, $change_trigger, $change_description, $root_cause, $document_reference, $document_link, $wbs_code, $activity_name, $critical_path_status, $bim_object_id, $bim_element_name, $object_location, $risk_id, $risk_category, $risk_score_at_cr, $estimated_time_impact_days, $estimated_cost_impact, $quality_impact, $risk_score_change, $affected_successors, $rework_potential, $impact_summary, $system_recommendation, $alternative_actions, $priority_level, $cr_status, $approval_level, $approval_decision, $approval_notes, $approval_date, $implementation_status, $implementation_progress, $evidence_file, $actual_time_impact_days, $convert_to_knowledge]);
 
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