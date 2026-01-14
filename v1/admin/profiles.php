<?php
include '../../config/koneksi.php';

$REQUIRE_ADMIN = true;
include '../auth/validate_token.php';

$method = $_SERVER['REQUEST_METHOD'];

// === GET: Lihat Daftar Profil ===
if ($method === 'GET') {
    // Bisa filter by router_id kalau mau
    $router_id = $_GET['router_id'] ?? null;
    
    $sql = "SELECT * FROM profiles WHERE admin_id = ?";
    $params = [$ADMIN_ID];
    
    if ($router_id) {
        $sql .= " AND router_id = ?";
        $params[] = $router_id;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    sendResponse(true, 'List Profil', $stmt->fetchAll());
}

// === POST: Tambah / Edit / Hapus ===
else if ($method === 'POST') {
    $action = $_POST['action'] ?? 'add';
    
    if ($action === 'delete') {
        $id = $_POST['profile_id'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM profiles WHERE id = ? AND admin_id = ?");
        $stmt->execute([$id, $ADMIN_ID]);
        sendResponse(true, 'Profil dihapus');
    }
    
    // Ambil data input untuk Add/Edit
    $router_id = $_POST['router_id'] ?? 0;
    $nama_tampil = $_POST['nama_tampil'] ?? '';     // Cont: "Paket 5 Jam"
    $nama_mikrotik = $_POST['nama_mikrotik'] ?? ''; // Cont: "prof_5h"
    $harga = $_POST['harga'] ?? 0;
    $validity = $_POST['validity'] ?? '';           // Cont: "1 Hari"
    
    if ($action === 'add') {
        $sql = "INSERT INTO profiles (admin_id, router_id, nama_tampil, nama_mikrotik, harga, validity) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$ADMIN_ID, $router_id, $nama_tampil, $nama_mikrotik, $harga, $validity]);
        sendResponse(true, 'Profil berhasil dibuat');
    }
    
    else if ($action === 'edit') {
        $id = $_POST['profile_id'] ?? 0;
        $sql = "UPDATE profiles SET router_id=?, nama_tampil=?, nama_mikrotik=?, harga=?, validity=? WHERE id=? AND admin_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$router_id, $nama_tampil, $nama_mikrotik, $harga, $validity, $id, $ADMIN_ID]);
        sendResponse(true, 'Profil berhasil diupdate');
    }
}
?>