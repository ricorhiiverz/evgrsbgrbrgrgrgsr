<?php
// Load koneksi
include '../../config/koneksi.php';

header('Content-Type: application/json; charset=utf-8');

// Debugging Marker: Ini untuk memastikan file baru yang jalan
// Nanti bisa dihapus kalau sudah sukses
// echo "DEBUG: File Baru Loaded. "; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method tidak diizinkan');
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$nama     = $_POST['nama_lengkap'] ?? '';

if (empty($username) || empty($password) || empty($nama)) {
    sendResponse(false, 'Username, Password, dan Nama Lengkap wajib diisi');
}

$check = $conn->prepare("SELECT id FROM users WHERE username = ?");
$check->execute([$username]);

if ($check->rowCount() > 0) {
    sendResponse(false, 'Username sudah digunakan, cari yang lain');
}

$hashed_pass = password_hash($password, PASSWORD_DEFAULT);

try {
    // PERHATIKAN: Disini tertulis 'tagihan', BUKAN 'saldo'
    $sql = "INSERT INTO users (username, password, nama_lengkap, role, tagihan) VALUES (?, ?, ?, 'admin', 0)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$username, $hashed_pass, $nama]);

    sendResponse(true, 'Admin berhasil didaftarkan');

} catch (PDOException $e) {
    sendResponse(false, 'Gagal mendaftar: ' . $e->getMessage());
}
?>