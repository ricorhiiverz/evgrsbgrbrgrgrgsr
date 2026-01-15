<?php
// File: api/mitra/buy_voucher.php

include '../../config/koneksi.php';
include '../../libs/routeros_api.class.php';
include '../auth/validate_token.php'; 

// Set Header JSON
header('Content-Type: application/json');

// 1. Validasi Role Mitra
if ($USER_ROLE !== 'mitra') {
    sendResponse(false, 'Hanya Mitra yang bisa melakukan transaksi');
}

// 2. Validasi Input ID Profil
$profile_id = filter_input(INPUT_POST, 'profile_id', FILTER_SANITIZE_NUMBER_INT);
if (empty($profile_id)) sendResponse(false, 'Pilih paket terlebih dahulu');

// 3. Ambil Data Paket & Router dari Database
$sql = "SELECT p.harga, p.nama_mikrotik, p.validity,
               r.ip_address, r.username_mikrotik, r.password_mikrotik, r.port_api, r.id as router_id
        FROM profiles p
        JOIN routers r ON p.router_id = r.id
        WHERE p.id = ?";

$stmt = $conn->prepare($sql);
$stmt->execute([$profile_id]);
$dataPaket = $stmt->fetch();

if (!$dataPaket) {
    sendResponse(false, 'Paket tidak ditemukan');
}

$harga_beli = $dataPaket['harga'];

// 4. Inisialisasi API & Test Koneksi Mikrotik
$API = new RouterosAPI();
// $API->debug = false; 

// [VALIDASI 1] Test Koneksi Sebelum Proses Apapun
if (!$API->connect($dataPaket['ip_address'], $dataPaket['username_mikrotik'], $dataPaket['password_mikrotik'], (int)$dataPaket['port_api'])) {
    sendResponse(false, 'Gagal terhubung ke Router Mikrotik. Cek koneksi internet router atau konfigurasi API.');
}


// Pastikan Mitra punya prefix (Jaga-jaga data lama)
$mitra_prefix = $currentUser['prefix'];
if (empty($mitra_prefix)) {
    sendResponse(false, 'Akun Mitra ini belum disetting Prefix. Hubungi Admin.');
}

// 5. Fungsi Generator Kode (Prefix + 8 Angka)
function generateVoucherCode($prefix) {
    // Menghasilkan 8 digit angka acak (00000000 - 99999999)
    // str_pad memastikan kalau dapat angka "123", jadinya "00000123"
    $random_number = str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
    return $prefix . $random_number;
}

// [VALIDASI 3] Pastikan Kode Unik
$user_vc = '';
$pass_vc = '';
$is_unique = false;
$max_retries = 10;
$attempt = 0;

do {
    $temp_code = generateVoucherCode($mitra_prefix);
    
    // Cek di database
    $stmtCek = $conn->prepare("SELECT COUNT(*) FROM transactions WHERE kode_voucher = ?");
    $stmtCek->execute([$temp_code]);
    $count = $stmtCek->fetchColumn();

    if ($count == 0) {
        $user_vc = $temp_code;
        $pass_vc = $temp_code; // Username & Pass sama
        $is_unique = true;
    }
    
    $attempt++;
} while (!$is_unique && $attempt < $max_retries);

if (!$is_unique) {
    $API->disconnect();
    sendResponse(false, 'Gagal membuat kode unik. Silakan coba lagi.');
}

// 6. [VALIDASI 2] Buat User di Mikrotik
$comment_vc = "";

$api_result = $API->comm("/ip/hotspot/user/add", [
    "name"     => $user_vc,
    "password" => $pass_vc,
    "profile"  => $dataPaket['nama_mikrotik'],
    "comment"  => $comment_vc
]);

// Cek Error Response Mikrotik
if (isset($api_result['!trap'])) {
    $API->disconnect();
    sendResponse(false, 'Mikrotik Error: ' . $api_result['!trap'][0]['message']);
}

// 7. Simpan Transaksi ke Database (Atomic Transaction)
try {
    $conn->beginTransaction();

    // A. Update Tagihan Mitra (Tambah Hutang)
    $stmtUpdate = $conn->prepare("UPDATE users SET tagihan = tagihan + ? WHERE id = ?");
    $stmtUpdate->execute([$harga_beli, $currentUser['id']]);

    // B. Insert Log Transaksi
    $waktu_sekarang = date('Y-m-d H:i:s'); 

    $stmtLog = $conn->prepare("INSERT INTO transactions 
        (mitra_id, admin_id, router_id, profile_id, kode_voucher, harga_jual, status, waktu_transaksi) 
        VALUES (?, ?, ?, ?, ?, ?, 'belum diaktifkan', ?)"); // Ubah NOW() jadi ?
    
    $stmtLog->execute([
        $currentUser['id'],
        $currentUser['parent_id'],
        $dataPaket['router_id'],
        $profile_id,
        $user_vc,
        $harga_beli,
        $waktu_sekarang // Masukkan variabel waktu di urutan terakhir
    ]);

    $conn->commit();

    // Ambil Info Tagihan Terbaru
    $stmtTagihan = $conn->prepare("SELECT tagihan FROM users WHERE id = ?");
    $stmtTagihan->execute([$currentUser['id']]);
    $tagihan_terbaru = $stmtTagihan->fetchColumn();

    $API->disconnect(); // Tutup koneksi Mikrotik

    // [VALIDASI 4] Respon Hasil, Pembuat, dan Tanggal
    $responseData = [
        'kode_voucher'  => $user_vc,
        'password'      => $pass_vc,
        'validity'      => $dataPaket['validity'],
        'harga'         => $harga_beli,
        'total_tagihan' => $tagihan_terbaru,
        'created_by'    => $currentUser['nama_lengkap'] ?? $currentUser['username'],
        'created_at'    => date('d-m-Y H:i:s'),
        'qr_code'       => "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . $user_vc
    ];

    sendResponse(true, 'Voucher Berhasil Dibuat!', $responseData);

} catch (PDOException $e) {
    // 8. Rollback: Jika DB Gagal, Batalkan Transaksi & Hapus di Mikrotik
    $conn->rollBack();

    // Hapus user di Mikrotik agar tidak jadi "Voucher Hantu"
    // Menggunakan koneksi $API yang masih terbuka
    $findUser = $API->comm("/ip/hotspot/user/print", ["?name" => $user_vc]);
    if (isset($findUser[0]['.id'])) {
        $API->comm("/ip/hotspot/user/remove", [".id" => $findUser[0]['.id']]);
    }
    
    $API->disconnect();
    sendResponse(false, 'Gagal menyimpan database (Transaksi Dibatalkan): ' . $e->getMessage());
}
?>