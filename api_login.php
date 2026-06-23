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

// =================================================================
// 🚀 SISIPAN KODE BYPASS DI SISI BACKEND (Aman & Sinkron)
// =================================================================
if ($username === "manager" && $password === "manager123") {
    // Buat sesi dummy agar diakui oleh API PHP lainnya
    $_SESSION['user_id']   = 998; 
    $_SESSION['username']  = "manager";
    $_SESSION['full_name'] = "PM Tester";
    $_SESSION['role']      = "Project Manager";

    echo json_encode([
        "status"    => "success",
        "full_name" => "PM Tester",
        "role"      => "Project Manager",
        "redirect"  => "project-manager.html"
    ]);
    exit;
}

if ($username === "engineer" && $password === "engineer123") {
    // Buat sesi dummy agar diakui oleh API PHP lainnya
    $_SESSION['user_id']   = 999; 
    $_SESSION['username']  = "engineer";
    $_SESSION['full_name'] = "SE Tester";
    $_SESSION['role']      = "Site Engineer";

    echo json_encode([
        "status"    => "success",
        "full_name" => "SE Tester",
        "role"      => "Site Engineer",
        "redirect"  => "site-engineer.html"
    ]);
    exit;
}

if ($username === "admin" && $password === "admin123") {
    // Buat sesi dummy agar diakui oleh API PHP lainnya
    $_SESSION['user_id']   = 1000; 
    $_SESSION['username']  = "admin";
    $_SESSION['full_name'] = "Administrator";
    $_SESSION['role']      = "Admin";

    echo json_encode([
        "status"    => "success",
        "full_name" => "Administrator",
        "role"      => "Admin",
        "redirect"  => "admin.html"
    ]);
    exit;
}
// =================================================================
// AKHIR KODE BYPASS
// =================================================================

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
        if ($user['role'] === 'Admin') {
            $redirect_page === 'admin.html';
        } if ($user['role'] === 'Project Manager') {
            $redirect_page = 'project-manager.html';
        } if ($user['role'] === 'Site Engineer') {
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