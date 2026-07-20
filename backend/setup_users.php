<?php
// File: setup_users.php
require 'db_user.php';

//$users = [
//    ['admin', 'admin123', 'Administrator', 'Admin'],
//    ['manager', 'manager123', 'PM Tester', 'Project Manager'],
//    ['engineer', 'engineer123', 'SE Tester', 'Site Engineer'],
//    ['budisantoso', 'budisantoso', 'Budi Santoso', 'Project Manager'],
//    ['naufaliman', 'naufaliman', 'Naufal Iman', 'Project Manager'],
//    ['agungfirmansyah', 'agungfirmansyah', 'Agung Firmansyah', 'Project Manager'],
//    ['setyoeko', 'setyoeko', 'Setyo Eko', 'Project Manager'],
//    ['ahmadfauzi', 'ahmadfauzi', 'Ahmad Fauzi', 'Site Engineer'],
//    ['aniwijaya', 'aniwijaya', 'Ani Wijaya', 'Site Engineer'],
//    ['briannugraha', 'briannugraha', 'Brian Nugraha', 'Site Engineer'],
//    ['budisetiawan', 'budisetiawan', 'Budi Setiawan', 'Site Engineer'],
//    ['dedikurnia', 'dedikurnia', 'Dedi Kurnia', 'Site Engineer'],
//    ['galuhrizkiya', 'galuhrizkiya', 'Galuh Rizkiya', 'Site Engineer'],
//    ['liasusanti', 'liasusanti', 'Lia Susanti', 'Site Engineer'],
//    ['riniwidyanti', 'riniwidyanti', 'Rini Widyanti', 'Site Engineer']
//];

$users = [
    ['nasywaafifa', 'nasywaafifa123', 'Nasywa Afifa', 'Site Engineer'],
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