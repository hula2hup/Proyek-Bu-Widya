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
        // Tambahkan field lainnya di sini jika ada...

        // 3. QUERY INSERT KE TABEL 'data_proyek_risk'
        // Sesuaikan nama kolom di dalam tanda kurung dengan kolom di database kamu
        $sql = "INSERT INTO data_proyek_risk (id, timestamp, risk_id, risk_identification_date) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        // Eksekusi query dengan memasukkan data
        $saved = $stmt->execute([$id, $timestamp, $risk_id, $risk_identification_date]);

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