<?php
// File: api/mitra/get_summary.php

header('Content-Type: application/json');
include '../../config/koneksi.php';
include '../auth/validate_token.php'; 

// Validasi Token/Login
if (!isset($currentUser)) {
    sendResponse(false, 'Akses ditolak');
}

try {
    // 1. Query Agregat (Satu kali jalan untuk performa)
    $sql = "SELECT 
                -- Total Penjualan (Semua Waktu)
                COALESCE(SUM(harga_jual), 0) as total_omset,
                
                -- Penjualan Hari Ini
                COALESCE(SUM(CASE WHEN DATE(waktu_transaksi) = CURDATE() THEN harga_jual ELSE 0 END), 0) as omset_hari_ini,
                
                -- Tanggal Transaksi Pertama (Untuk hitung pembagi rata-rata)
                MIN(waktu_transaksi) as tgl_pertama
            FROM transactions 
            WHERE mitra_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$currentUser['id']]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Hitung Rata-Rata Penjualan Per Hari
    $avg_daily = 0;
    
    // Pastikan ada transaksi minimal 1 kali
    if ($data['tgl_pertama']) {
        $first_date = new DateTime($data['tgl_pertama']);
        $now        = new DateTime();
        
        // Hitung selisih hari (+1 agar hari pertama dihitung sebagai 1 hari, bukan 0)
        $days_active = $first_date->diff($now)->days + 1;
        
        // Rumus: Total Pendapatan / Jumlah Hari Aktif
        $avg_daily = floor($data['total_omset'] / $days_active);
    }

    // 3. Susun Respon Summary Saja
    $summary = [
        'total_penjualan'    => (int)$data['total_omset'],
        'penjualan_hari_ini' => (int)$data['omset_hari_ini'],
        'rata_rata_per_hari' => (int)$avg_daily
    ];

    sendResponse(true, 'Data summary berhasil dimuat', $summary);

} catch (PDOException $e) {
    sendResponse(false, 'Database Error: ' . $e->getMessage());
}
?>