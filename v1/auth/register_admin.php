<?php
// Load koneksi
include '../../config/koneksi.php';

// Cek method request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method tidak diizinkan');
}

// Ambil data JSON atau Form Data
// Kodular biasanya kirim Form Data, tapi kita siapkan terima JSON juga
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$nama     = $_POST['nama_lengkap'] ?? '';

// Validasi input
if (empty($username) || empty($password) || empty($nama)) {
    sendResponse(false, 'Username, Password, dan Nama Lengkap wajib diisi');
}

// Cek apakah username sudah ada
$check = $conn->prepare("SELECT id FROM users WHERE username = ?");
$check->execute([$username]);

if ($check->rowCount() > 0) {
    sendResponse(false, 'Username sudah digunakan, cari yang lain');
}

// Hash Password (Wajib untuk keamanan!)
$hashed_pass = password_hash($password, PASSWORD_DEFAULT);

try {
    // Simpan Admin Baru
    // role otomatis 'admin', parent_id NULL
    $sql = "INSERT INTO users (username, password, nama_lengkap, role, saldo) VALUES (?, ?, ?, 'admin', 0)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$username, $hashed_pass, $nama]);

    sendResponse(true, 'Admin berhasil didaftarkan');

} catch (PDOException $e) {
    sendResponse(false, 'Gagal mendaftar: ' . $e->getMessage());
}
?>