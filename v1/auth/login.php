<?php
include '../../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method tidak diizinkan');
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    sendResponse(false, 'Silakan isi username dan password');
}

$stmt = $conn->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    
    $newToken = bin2hex(random_bytes(32));
    $update = $conn->prepare("UPDATE users SET api_token = ? WHERE id = ?");
    $update->execute([$newToken, $user['id']]);

    // [FIX] Sesuaikan response data dengan kolom database baru
    $data_login = [
        'id' => $user['id'],
        'username' => $user['username'],
        'nama_lengkap' => $user['nama_lengkap'],
        'role' => $user['role'],
        'tagihan' => $user['tagihan'], // Ganti saldo jadi tagihan
        'token' => $newToken,
        'parent_id' => $user['parent_id']
    ];

    sendResponse(true, 'Login Berhasil', $data_login);

} else {
    sendResponse(false, 'Username atau Password salah');
}
?>