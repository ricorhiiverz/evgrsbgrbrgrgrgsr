<?php
// File: v1/callback/webhook_login.php

// 1. AKTIFKAN DEBUGGING (Agar ketahuan errornya apa jika 500 lagi)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Load Koneksi
// Pastikan path ini BENAR. Jika file ini ada di v1/callback/
// dan koneksi ada di config/, maka naik 2 level (../../) sudah benar.
if (!file_exists(__DIR__ . '/../../config/koneksi.php')) {
    die("Error: File koneksi.php tidak ditemukan di path yang ditentukan.");
}
require_once __DIR__ . '/../../config/koneksi.php';

// 3. Ambil Data
$kode_voucher = $_GET['user'] ?? '';

if (empty($kode_voucher)) {
    http_response_code(400);
    die("Error: Parameter user kosong.");
}

try {
    // 4. Cari Voucher & Validity
    $sql = "SELECT t.id, t.status, p.validity 
            FROM transactions t 
            JOIN profiles p ON t.profile_id = p.id 
            WHERE t.kode_voucher = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$kode_voucher]);
    $trx = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trx) {
        // Voucher tidak ada di DB, tapi ada di Mikrotik (Aneh, tapi return OK saja biar Mikrotik tidak error)
        echo "Info: Voucher tidak ditemukan di Database.";
        exit;
    }

    // 5. Cek Status & Update
    // Hanya update jika status BELUM mengandung kata "Aktif"
    // Logika: strpos(status, 'Aktif') === false
    
    $status_db = $trx['status'];
    $is_already_active = (strpos($status_db, 'Aktif') !== false);

    if ($status_db == 'belum diaktifkan' || !$is_already_active) {
        
        $now = date('Y-m-d H:i:s');
        
        // Hitung Expire
        $expire_date = calculateExpire($trx['validity']);

        // Format Status Baru
        $status_baru = "Aktif @ " . $now;

        // [FIXED] Update SQL - Hapus kolom waktu_aktif yang tidak ada
        $update = $conn->prepare("UPDATE transactions SET status = ?, expire = ? WHERE id = ?");
        $update->execute([$status_baru, $expire_date, $trx['id']]);

        echo "Sukses: Voucher $kode_voucher diaktifkan. Expire: $expire_date";
    } else {
        echo "Info: Voucher sudah aktif sebelumnya.";
    }

} catch (PDOException $e) {
    // Tangkap Error Database
    http_response_code(500);
    die("Database Error: " . $e->getMessage());
} catch (Exception $e) {
    // Tangkap Error Lainnya
    http_response_code(500);
    die("General Error: " . $e->getMessage());
}


// --- Helper Function ---
function calculateExpire($validity) {
    $date = new DateTime(); 
    
    $satuan = substr($validity, -1); 
    $angka  = (int) substr($validity, 0, -1); 

    if ($satuan == 'd') {
        $date->modify("+$angka days");
    } elseif ($satuan == 'h') {
        $date->modify("+$angka hours");
    } elseif ($satuan == 'm') {
        $date->modify("+$angka minutes");
    } else {
        $date->modify("+1 day"); // Default
    }
    
    return $date->format('Y-m-d H:i:s');
}
?>