<?php
include '../../config/koneksi.php';

// Proteksi: Hanya Admin yang boleh akses
$REQUIRE_ADMIN = true;
include '../auth/validate_token.php'; 
// (Sekarang kita punya variabel $ADMIN_ID yang valid)

$method = $_SERVER['REQUEST_METHOD'];

// === GET: Ambil Daftar Router ===
if ($method === 'GET') {
    $stmt = $conn->prepare("SELECT id, nama_router, ip_address, username_mikrotik, port_api FROM routers WHERE admin_id = ?");
    $stmt->execute([$ADMIN_ID]);
    $data = $stmt->fetchAll();
    sendResponse(true, 'Data Router berhasil diambil', $data);
}

// === POST: Tambah / Edit Router ===
else if ($method === 'POST') {
    // Ambil input
    $action = $_POST['action'] ?? 'add'; // 'add' atau 'edit'
    $nama   = $_POST['nama_router'] ?? '';
    $ip     = $_POST['ip_address'] ?? '';
    $user   = $_POST['username_mikrotik'] ?? '';
    $pass   = $_POST['password_mikrotik'] ?? '';
    $port   = $_POST['port_api'] ?? 8728;
    
    // Validasi sederhana
    if (empty($nama) || empty($ip) || empty($user) || empty($pass)) {
        sendResponse(false, 'Mohon lengkapi semua data router');
    }

    if ($action === 'add') {
        // Tambah Baru
        $sql = "INSERT INTO routers (admin_id, nama_router, ip_address, username_mikrotik, password_mikrotik, port_api) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$ADMIN_ID, $nama, $ip, $user, $pass, $port]);
        sendResponse(true, 'Router berhasil ditambahkan');
    } 
    
    else if ($action === 'edit') {
        // Edit Existing (Butuh ID Router)
        $router_id = $_POST['router_id'] ?? 0;
        
        // Pastikan router ini milik Admin yang sedang login (Security Check)
        $cek = $conn->prepare("SELECT id FROM routers WHERE id = ? AND admin_id = ?");
        $cek->execute([$router_id, $ADMIN_ID]);
        if ($cek->rowCount() == 0) sendResponse(false, 'Router tidak ditemukan atau bukan milik Anda');

        $sql = "UPDATE routers SET nama_router=?, ip_address=?, username_mikrotik=?, password_mikrotik=?, port_api=? WHERE id=? AND admin_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$nama, $ip, $user, $pass, $port, $router_id, $ADMIN_ID]);
        sendResponse(true, 'Router berhasil diupdate');
    }
}

// === POST (DELETE): Hapus Router ===
// Kodular kadang susah kirim method DELETE, jadi kita pakai POST dengan action='delete'
if ($method === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $router_id = $_POST['router_id'] ?? 0;
    
    $stmt = $conn->prepare("DELETE FROM routers WHERE id = ? AND admin_id = ?");
    $stmt->execute([$router_id, $ADMIN_ID]);
    
    if ($stmt->rowCount() > 0) {
        sendResponse(true, 'Router berhasil dihapus');
    } else {
        sendResponse(false, 'Gagal menghapus (Data tidak ditemukan)');
    }
}
?>