<?php
// File: v1/admin/process_payment.php

// 1. Setup Header
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../../config/koneksi.php';
include_once '../auth/validate_token.php'; 

// 2. Keamanan: Hanya ADMIN yang boleh akses
if ($currentUser['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => "Akses ditolak. Khusus Admin."]);
    exit;
}

// =============================================================================
// 3. LOGIKA INPUT FLEXIBLE & SANITIZATION (PEMBERSIH)
// =============================================================================

$params = [];

// A. Gabungkan Input dari JSON (Raw) dan Form Data ($_POST)
$raw_input = file_get_contents("php://input");
$json_data = json_decode($raw_input, true);

if (is_array($json_data)) {
    $params = array_merge($params, $json_data);
}
if (!empty($_POST)) {
    $params = array_merge($params, $_POST);
}

// B. Ambil Data
$mitra_id_raw = isset($params['mitra_id']) ? $params['mitra_id'] : null;
$bulan_raw    = isset($params['bulan']) ? $params['bulan'] : null;

// C. Fitur AUTO-CLEAN (Penting!)
// Membersihkan tanda kutip (") (') dan spasi yang tidak sengaja terkirim
$mitra_id = $mitra_id_raw ? trim(str_replace(['"', "'"], '', $mitra_id_raw)) : null;
$bulan    = $bulan_raw ? trim(str_replace(['"', "'"], '', $bulan_raw)) : null;

// D. Validasi Akhir
if (empty($mitra_id) || empty($bulan)) {
    http_response_code(400);
    echo json_encode([
        "status" => false, 
        "message" => "Parameter 'mitra_id' dan 'bulan' wajib diisi."
    ]);
    exit;
}

// =============================================================================
// 4. EKSEKUSI PEMBAYARAN (TRANSACTION)
// =============================================================================

try {
    $conn->beginTransaction();

    // A. Cek Total Tagihan Bulan Tersebut (Yang Belum Lunas)
    $sqlCek = "SELECT COALESCE(SUM(harga_jual), 0) as total_akan_dibayar
               FROM transactions 
               WHERE mitra_id = ? 
               AND DATE_FORMAT(waktu_transaksi, '%Y-%m') = ? 
               AND is_paid = 0"; 
    
    $stmt = $conn->prepare($sqlCek);
    $stmt->execute([$mitra_id, $bulan]);
    $total_bayar = $stmt->fetchColumn();

    if ($total_bayar <= 0) {
        throw new Exception("Tidak ada tagihan yang perlu dibayar untuk bulan $bulan (Mungkin sudah lunas).");
    }

    // B. Update Transaksi Bulan Itu Menjadi LUNAS (is_paid = 1)
    $sqlUpdateTrx = "UPDATE transactions 
                     SET is_paid = 1 
                     WHERE mitra_id = ? 
                     AND DATE_FORMAT(waktu_transaksi, '%Y-%m') = ? 
                     AND is_paid = 0";
    
    $stmtUpd = $conn->prepare($sqlUpdateTrx);
    $stmtUpd->execute([$mitra_id, $bulan]);

    // C. HITUNG ULANG (Recalculate) Total Hutang User
    // Menghitung sisa voucher (bulan lain) yang masih is_paid=0
    $sqlRecalc = "SELECT COALESCE(SUM(harga_jual), 0) 
                  FROM transactions 
                  WHERE mitra_id = ? AND is_paid = 0";
    
    $stmtRecalc = $conn->prepare($sqlRecalc);
    $stmtRecalc->execute([$mitra_id]);
    $saldo_baru = $stmtRecalc->fetchColumn();

    // D. Update Saldo Tagihan di Tabel User
    $sqlUpdateUser = "UPDATE users SET tagihan = ? WHERE id = ?";
    $stmtUser = $conn->prepare($sqlUpdateUser);
    $stmtUser->execute([$saldo_baru, $mitra_id]);

    $conn->commit();

    // Response Sukses
    echo json_encode([
        "status" => true,
        "message" => "Pembayaran berhasil diproses.",
        "data" => [
            "mitra_id"      => $mitra_id,
            "bulan_lunas"   => $bulan,
            "jumlah_dibayar"=> "Rp " . number_format($total_bayar, 0, ',', '.'),
            "sisa_tagihan"  => "Rp " . number_format($saldo_baru, 0, ',', '.')
        ]
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}
?>