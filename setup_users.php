<?php
// File: setup_users.php
require 'db_user.php';

$users = [
    ['admin', 'admin123', 'Administrator', 'Admin'],
    ['manager', 'manager123', 'Budi Santoso', 'Project Manager'],
    ['engineer', 'engineer123', 'Budi Setiawan', 'Site Engineer']
];

foreach ($users as $u) {
    $hashed_password = password_hash($u[1], PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$u[0], $hashed_password, $u[2], $u[3]]);
        echo "User {$u[0]} berhasil dibuat!<br>";
    } catch (Exception $e) {
        echo "Gagal/User {$u[0]} sudah ada.<br>";
    }
}
?>