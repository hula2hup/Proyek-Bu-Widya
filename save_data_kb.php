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
        
        $file_sql_eksternal = 'data_proyek.sql'; // Nama file SQL kamu

        if (file_exists($file_sql_eksternal)) {
            // Membaca seluruh text/query di dalam file setup.sql
            $query_dari_file = file_get_contents($file_sql_eksternal);
            
            // Mengeksekusi query tersebut ke MySQL Laragon
            $pdo->exec($query_dari_file);
        } else {
            die("Error: File script SQL '$file_sql_eksternal' tidak ditemukan!");
        }
        // ============================================================

        // 2. MENYIAPKAN DATA
        $id = uniqid();
        $timestamp = date('Y-m-d H:i:s');
        $target_dir = "uploads/";

        // Membuat folder 'uploads' jika belum ada
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // ============================================================
        // PROSES UPLOAD FILE: supporting_document
        // ============================================================
        $supporting_name = null;
        if (isset($_FILES['supporting_document']) && $_FILES['supporting_document']['error'] == 0) {
            $ext = pathinfo($_FILES["supporting_document"]["name"], PATHINFO_EXTENSION);
            $new_filename = time() . '_evidence_' . uniqid() . '.' . $ext;
            
            if (move_uploaded_file($_FILES["supporting_document"]["tmp_name"], $target_dir . $new_filename)) {
                $supporting_name = $new_filename;
            }
        }
        
        $supporting_document = $supporting_name;
        // ============================================================
        
        // Sesuaikan variabel di bawah ini dengan atribut 'name' pada tag <input> di HTML kamu
        // Contoh jika di HTML ada <input name="nama_proyek"> dan <input name="lokasi">
        $knowledge_id      = $_POST['knowledge_id'] ?? null;
        $knowledge_title      = $_POST['knowledge_title'] ?? null;
        $project_id      = $_POST['project_id'] ?? null;
        $project_name      = $_POST['project_name'] ?? null;
        $source_type      = $_POST['source_type'] ?? null;
        $cr_id      = $_POST['cr_id'] ?? null;
        $risk_id      = $_POST['risk_id'] ?? null;
        $wbs_code      = $_POST['wbs_code'] ?? null;
        $activity_name      = $_POST['activity_name'] ?? null;
        $bim_object_id      = $_POST['bim_object_id'] ?? null;
        $bim_element      = $_POST['bim_element'] ?? null;
        $change_category      = $_POST['change_category'] ?? null;
        $risk_category      = $_POST['risk_category'] ?? null;
        $keywords      = $_POST['keywords'] ?? null;
        $problem_summary      = $_POST['problem_summary'] ?? null;
        $root_cause      = $_POST['root_cause'] ?? null;
        $case_chronology      = $_POST['case_chronology'] ?? null;
        $time_impact      = $_POST['time_impact'] ?? null;
        $cost_impact      = $_POST['cost_impact'] ?? null;
        $quality_impact      = $_POST['quality_impact'] ?? null;
        $risk_impact      = $_POST['risk_impact'] ?? null;
        $critical_path_impact      = $_POST['critical_path_impact'] ?? null;
        $decision_taken      = $_POST['decision_taken'] ?? null;
        $technical_solution      = $_POST['technical_solution'] ?? null;
        $mitigation_action      = $_POST['mitigation_action'] ?? null;
        $implementation_result      = $_POST['implementation_result'] ?? null;
        $solution_effectiveness      = $_POST['solution_effectiveness'] ?? null;
        $effectiveness_notes      = $_POST['effectiveness_notes'] ?? null;
        $lesson_learned      = $_POST['lesson_learned'] ?? null;
        $preventive_recommendation      = $_POST['preventive_recommendation'] ?? null;
        $reuse_condition      = $_POST['reuse_condition'] ?? null;
        $similarity_criteria      = $_POST['similarity_criteria'] ?? null;
        $knowledge_type      = $_POST['knowledge_type'] ?? null;
        $seci_category      = $_POST['seci_category'] ?? null;
        $validation_status      = $_POST['validation_status'] ?? null;
        $validation_id      = $_POST['validation_id'] ?? null;
        $validation_notes      = $_POST['validation_notes'] ?? null;
        $validation_date      = $_POST['validation_date'] ?? null;
        $supporting_document      = $supporting_name;
        $version      = $_POST['version'] ?? null;
        $retrieval_count      = $_POST['retrieval_count'] ?? null;
        $reused_in_cr_id      = $_POST['reused_in_cr_id'] ?? null;
        // Tambahkan field lainnya di sini jika ada...

        // 3. QUERY INSERT KE TABEL 'data_proyek_kb'
        // Sesuaikan nama kolom di dalam tanda kurung dengan kolom di database kamu
        $sql = "INSERT INTO data_proyek_kb (id, timestamp, knowledge_id, knowledge_title, project_id, project_name, source_type, cr_id, risk_id, wbs_code, activity_name, bim_object_id, bim_element, change_category, risk_category, keywords, problem_summary, root_cause, case_chronology, time_impact, cost_impact, quality_impact, risk_impact, critical_path_impact, decision_taken, technical_solution, mitigation_action, implementation_result, solution_effectiveness, effectiveness_notes, lesson_learned, preventive_recommendation, reuse_condition, similarity_criteria, knowledge_type, seci_category, validation_status, validation_id, validation_notes, validation_date, supporting_document, version, retrieval_count, reused_in_cr_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        // Eksekusi query dengan memasukkan data
        $saved = $stmt->execute([$id, $timestamp, $knowledge_id, $knowledge_title, $project_id, $project_name, $source_type, $cr_id, $risk_id, $wbs_code, $activity_name, $bim_object_id, $bim_element, $change_category, $risk_category, $keywords, $problem_summary, $root_cause, $case_chronology, $time_impact, $cost_impact, $quality_impact, $risk_impact, $critical_path_impact, $decision_taken, $technical_solution, $mitigation_action, $implementation_result, $solution_effectiveness, $effectiveness_notes, $lesson_learned, $preventive_recommendation, $reuse_condition, $similarity_criteria, $knowledge_type, $seci_category, $validation_status, $validation_id, $validation_notes, $validation_date, $supporting_document, $version, $retrieval_count, $reused_in_cr_id]);

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