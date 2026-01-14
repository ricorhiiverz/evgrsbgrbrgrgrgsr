<?php
// Pastikan koneksi sudah di-include di file utama yang memanggil ini

// 1. Ambil Token dari Header
$headers = getallheaders();
$token = '';

if (isset($headers['Authorization'])) {
    $token = $headers['Authorization']; 
} elseif (isset($headers['authorization'])) { // Jaga-jaga server pakai huruf kecil
    $token = $headers['authorization'];
} elseif (isset($_POST['token'])) { // Opsi cadangan lewat Body
    $token = $_POST['token'];
}

if (empty($token)) {
    sendResponse(false, 'Akses Ditolak: Token tidak ditemukan');
}

// 2. Cek Validitas Token di Database
$stmtAuth = $conn->prepare("SELECT * FROM users WHERE api_token = ? LIMIT 1");
$stmtAuth->execute([$token]);
$currentUser = $stmtAuth->fetch();

if (!$currentUser) {
    sendResponse(false, 'Akses Ditolak: Token tidak valid atau sesi berakhir');
}

// 3. Simpan data user ke variabel global agar bisa dipakai di file lain
$ADMIN_ID = $currentUser['id'];
$USER_ROLE = $currentUser['role'];

// 4. Cek apakah dia Admin (Khusus folder v1/admin/)
// Jika file pemanggil mendefinisikan variable $REQUIRE_ADMIN = true
if (isset($REQUIRE_ADMIN) && $REQUIRE_ADMIN === true && $USER_ROLE !== 'admin') {
    sendResponse(false, 'Akses Ditolak: Anda bukan Admin');
}
?>