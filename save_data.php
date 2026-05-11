<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $file_path = 'data_proyek.json';
    $new_data = $_POST;
    
    // Tambahkan metadata
    $new_data['id'] = uniqid();
    $new_data['timestamp'] = date('Y-m-d H:i:s');

    // Baca data lama
    $current_data = [];
    if (file_exists($file_path)) {
        $json_content = file_get_contents($file_path);
        $current_data = json_decode($json_content, true) ?? [];
    }

    // Append data baru
    $current_data[] = $new_data;

    // Simpan kembali
    if (file_put_contents($file_path, json_encode($current_data, JSON_PRETTY_PRINT))) {
        header('Location: site-engineer.html?status=success');
        exit();
    }
}
?>