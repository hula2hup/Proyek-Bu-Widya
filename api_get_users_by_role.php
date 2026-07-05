<?php
header('Content-Type: application/json');
session_start();
require 'db_user.php';

if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak."]);
    exit;
}

$role = trim($_GET['role'] ?? '');
$allowedRoles = ['Project Manager', 'Site Engineer'];

if (!in_array($role, $allowedRoles, true)) {
    echo json_encode(["status" => "error", "message" => "Role tidak valid."]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, username, full_name, role
        FROM users
        WHERE role = :role
          AND full_name IS NOT NULL
          AND TRIM(full_name) <> ''
        ORDER BY full_name ASC
    ");
    $stmt->execute(['role' => $role]);

    echo json_encode([
        "status" => "success",
        "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
