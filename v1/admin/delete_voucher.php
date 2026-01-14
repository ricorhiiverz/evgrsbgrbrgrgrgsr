<?php
// File: v1/admin/delete_voucher.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../../config/koneksi.php';
include_once '../../libs/routeros_api.class.php'; 
include_once '../auth/validate_token.php'; 

// 1. Keamanan: Hanya ADMIN
if ($currentUser['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => "Akses ditolak. Khusus Admin."]);
    exit;
}

// 2. Ambil Input
$params = [];
$raw_input = file_get_contents("php://input");
$json_data = json_decode($raw_input, true);
if (is_array($json_data)) $params = array_merge($params, $json_data);
if (!empty($_POST)) $params = array_merge($params, $_POST);

$trx_id = isset($params['transaction_id']) ? $params['transaction_id'] : null;

if (empty($trx_id)) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Transaction ID wajib diisi."]);
    exit;
}

try {
    // 3. Ambil Detail Voucher & Info Router (FIXED COLUMNS)
    // Menggunakan ALIAS (AS) agar variabel di bawah tidak perlu diubah
    $sql = "SELECT 
                t.id, 
                t.kode_voucher as username, -- [FIX] Ubah kode_voucher jadi username agar script bawah tetap jalan
                t.mitra_id, 
                t.harga_jual,
                t.is_paid,
                r.ip_address, 
                r.username_mikrotik as r_user, -- [FIX] Sesuaikan nama kolom DB
                r.password_mikrotik as r_pass, -- [FIX] Sesuaikan nama kolom DB
                r.port_api as port             -- [FIX] Sesuaikan nama kolom DB
            FROM transactions t
            LEFT JOIN routers r ON t.router_id = r.id
            WHERE t.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$trx_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        throw new Exception("Data transaksi tidak ditemukan.");
    }

    $voucher_user = $voucher['username'];
    $logs = []; 

    // 4. HAPUS DI MIKROTIK (Jika Router Tersedia)
    if (!empty($voucher['ip_address'])) {
        $API = new RouterosAPI();
        $API->timeout = 2; // Timeout cepat
        
        // Gunakan port default 8728 jika kosong
        $port_mikrotik = !empty($voucher['port']) ? $voucher['port'] : 8728;

        if ($API->connect($voucher['ip_address'], $voucher['r_user'], $voucher['r_pass'], $port_mikrotik)) {
            
            // A. Hapus User Hotspot
            $getUsers = $API->comm("/ip/hotspot/user/print", ["?name" => $voucher_user]);
            if (count($getUsers) > 0) {
                $API->comm("/ip/hotspot/user/remove", [".id" => $getUsers[0]['.id']]);
                $logs[] = "User Mikrotik berhasil dihapus.";
            }

            // B. Tendang User Aktif (Kick)
            $getActive = $API->comm("/ip/hotspot/active/print", ["?user" => $voucher_user]);
            if (count($getActive) > 0) {
                $API->comm("/ip/hotspot/active/remove", [".id" => $getActive[0]['.id']]);
                $logs[] = "Sesi aktif user diputus.";
            }

            $API->disconnect();
        } else {
            $logs[] = "Gagal konek Mikrotik, tapi proses hapus DB tetap dilanjutkan.";
        }
    }

    // 5. DATABASE UPDATE (REFUND & DELETE)
    $conn->beginTransaction();

    // --- [HAPUS TRANSAKSI DULU] ---
    $sqlDel = "DELETE FROM transactions WHERE id = ?";
    $stmtDel = $conn->prepare($sqlDel);
    $stmtDel->execute([$trx_id]);

    // --- [HITUNG ULANG TAGIHAN MITRA (Lebih Aman)] ---
    // Daripada kurangi manual, lebih baik hitung total sisa hutang yang real
    $sqlRecalc = "SELECT COALESCE(SUM(harga_jual), 0) 
                  FROM transactions 
                  WHERE mitra_id = ? AND is_paid = 0";
    
    $stmtRecalc = $conn->prepare($sqlRecalc);
    $stmtRecalc->execute([$voucher['mitra_id']]);
    $tagihan_baru = $stmtRecalc->fetchColumn();

    // Update User
    $sqlUpdateUser = "UPDATE users SET tagihan = ? WHERE id = ?";
    $stmtUpd = $conn->prepare($sqlUpdateUser);
    $stmtUpd->execute([$tagihan_baru, $voucher['mitra_id']]);

    $logs[] = "Saldo Mitra diperbarui (Sisa Tagihan: Rp " . number_format($tagihan_baru,0,',','.') . ")";

    $conn->commit();

    echo json_encode([
        "status" => true,
        "message" => "Voucher berhasil dihapus.",
        "data" => [
            "mitra_id" => $voucher['mitra_id'],
            "sisa_tagihan" => $tagihan_baru
        ],
        "logs" => $logs
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Error Sistem: " . $e->getMessage()]);
}
?>