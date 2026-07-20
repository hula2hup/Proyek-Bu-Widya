<?php
// File: api_logout.php
session_start();
session_unset();    // Hapus semua variabel sesi
session_destroy();  // Hancurkan sesi

echo json_encode(["status" => "success", "message" => "Logout berhasil"]);
?>