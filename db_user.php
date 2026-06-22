<?php
// File: db_user.php
$host = 'localhost';
$db   = 'db_data_proyek'; 
$user = 'root';
$pass = ''; 
$charset = 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die(json_encode(["status" => "error", "message" => "Koneksi database gagal: " . $e->getMessage()]));
}
?>