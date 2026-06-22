<?php
// File: api_login.php
session_start(); // Wajib ada untuk memulai sesi
header('Content-Type: application/json');
require 'db_user.php';

// Ambil data JSON yang dikirim oleh JavaScript fetch()
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['username']) || empty($data['password'])) {
    echo json_encode(["status" => "error", "message" => "Username dan Password harus diisi"]);
    exit;
}

$username = $data['username'];
$password = $data['password'];

try {
    // Cari user berdasarkan username
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verifikasi apakah user ditemukan dan password-nya cocok
    if ($user && password_verify($password, $user['password'])) {
        // Simpan data ke Session PHP
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];

        // Tentukan halaman redirect berdasarkan Role di database
        $redirect_page = 'admin.html'; // Default jika Admin
        if ($user['role'] === 'Project Manager') {
            $redirect_page = 'project-manager.html';
        } elseif ($user['role'] === 'Site Engineer') {
            $redirect_page = 'site-engineer.html';
        }

        // Kirim respons sukses dalam format JSON murni
        echo json_encode([
            "status"    => "success",
            "full_name" => $user['full_name'],
            "role"      => $user['role'],
            "redirect"  => $redirect_page
        ]);
        exit;
    } else {
        // Jika username salah atau password tidak cocok
        echo json_encode(["status" => "error", "message" => "Username atau Password salah!"]);
        exit;
    }

} catch (PDOException $e) {
    // Jika ada error internal pada query/database
    echo json_encode(["status" => "error", "message" => "Terjadi kesalahan pada server database."]);
    exit;
}
?>