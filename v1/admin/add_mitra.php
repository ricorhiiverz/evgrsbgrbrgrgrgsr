<?php
include '../../config/koneksi.php';

$REQUIRE_ADMIN = true;
include '../auth/validate_token.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method salah');
}

// Ambil Input
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$nama     = $_POST['nama_lengkap'] ?? '';
$prefix   = $_POST['prefix'] ?? ''; // Input Prefix
$no_wa    = $_POST['no_wa'] ?? '';  // Input Nomor WA (Baru)

// --- Validasi Input ---
if (empty($username) || empty($password) || empty($prefix) || empty($no_wa)) {
    sendResponse(false, 'Username, Password, Prefix, dan No WA wajib diisi');
}

// Validasi Prefix (2 Huruf)
if (strlen($prefix) !== 2 || !ctype_alpha($prefix)) {
    sendResponse(false, 'Prefix harus 2 huruf (A-Z)');
}
$prefix = strtoupper($prefix); // Paksa jadi huruf besar

// Validasi No WA (Harus Angka)
if (!is_numeric($no_wa)) {
    sendResponse(false, 'Nomor WhatsApp harus berupa angka');
}

// --- Cek Duplikasi Data ---
// Kita cek apakah Username, Prefix, atau No WA sudah dipakai orang lain
$cek = $conn->prepare("SELECT id FROM users WHERE username = ? OR prefix = ? OR no_wa = ?");
$cek->execute([$username, $prefix, $no_wa]);

if ($cek->rowCount() > 0) {
    sendResponse(false, 'Username, Prefix, atau Nomor WA sudah digunakan mitra lain');
}

$hashed_pass = password_hash($password, PASSWORD_DEFAULT);

try {
    // Simpan ke Database
    $sql = "INSERT INTO users (username, password, nama_lengkap, role, parent_id, tagihan, prefix, no_wa) 
            VALUES (?, ?, ?, 'mitra', ?, 0, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$username, $hashed_pass, $nama, $ADMIN_ID, $prefix, $no_wa]);

    sendResponse(true, 'Mitra berhasil ditambahkan.', [
        'username' => $username,
        'prefix'   => $prefix,
        'no_wa'    => $no_wa
    ]);

} catch (PDOException $e) {
    sendResponse(false, 'Error Database: ' . $e->getMessage());
}
?>