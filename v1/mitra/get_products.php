<?php
include '../../config/koneksi.php';
include '../auth/validate_token.php'; // Otomatis dapat $ADMIN_ID (User ID) dan $USER_ROLE

// Cek Role, harus Mitra
if ($USER_ROLE !== 'mitra') {
    sendResponse(false, 'Hanya Mitra yang bisa mengakses menu ini');
}

// Ambil ID Admin dari Mitra ini (parent_id)
// Ingat: Di validate_token.php, $currentUser berisi data user yang login
$id_bos_mitra = $currentUser['parent_id'];

if (empty($id_bos_mitra)) {
    sendResponse(false, 'Akun Anda tidak terhubung dengan Admin manapun.');
}

// Ambil Profil Paket milik Bos-nya
// Kita join dengan tabel routers agar Mitra tahu ini paket untuk router mana
$sql = "SELECT p.id as profile_id, p.nama_tampil, p.harga, p.validity, r.nama_router 
        FROM profiles p 
        JOIN routers r ON p.router_id = r.id 
        WHERE p.admin_id = ?";

$stmt = $conn->prepare($sql);
$stmt->execute([$id_bos_mitra]);
$products = $stmt->fetchAll();

sendResponse(true, 'Daftar Paket Tersedia', $products);
?>