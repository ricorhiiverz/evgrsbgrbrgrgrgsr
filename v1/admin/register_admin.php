<?php
// Load koneksi
include '../../config/koneksi.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method tidak diizinkan');
}

// Ambil Input
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$nama     = $_POST['nama_lengkap'] ?? '';
$no_wa    = $_POST['no_wa'] ?? ''; // <--- Tambahan No WA

// Validasi
if (empty($username) || empty($password) || empty($nama) || empty($no_wa)) {
    sendResponse(false, 'Username, Password, Nama Lengkap, dan No WA wajib diisi');
}

// Validasi No WA
if (!is_numeric($no_wa)) {
    sendResponse(false, 'Nomor WhatsApp harus berupa angka');
}

// Cek Username atau No WA sudah ada?
// (Tidak perlu cek Prefix karena Admin tidak pakai Prefix)
$check = $conn->prepare("SELECT id FROM users WHERE username = ? OR no_wa = ?");
$check->execute([$username, $no_wa]);

if ($check->rowCount() > 0) {
    sendResponse(false, 'Username atau Nomor WA sudah digunakan');
}

$hashed_pass = password_hash($password, PASSWORD_DEFAULT);

try {
    // Insert Data Admin
    // Kolom 'prefix' dibiarkan NULL (atau tidak disebut di query)
    $sql = "INSERT INTO users (username, password, nama_lengkap, role, tagihan, no_wa) 
            VALUES (?, ?, ?, 'admin', 0, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$username, $hashed_pass, $nama, $no_wa]);

    sendResponse(true, 'Admin berhasil didaftarkan');

} catch (PDOException $e) {
    sendResponse(false, 'Gagal mendaftar: ' . $e->getMessage());
}
?>