<?php
// File: v1/admin/dashboard.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../../config/koneksi.php';
// Pastikan path ini benar sesuai lokasi library Anda
include_once '../../libs/routeros_api.class.php'; 
include_once '../auth/validate_token.php'; 

// 1. Keamanan: Khusus Admin
if ($currentUser['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => "Akses ditolak."]);
    exit;
}

try {
    // Setup Waktu
    $bulan_ini_str = date('Y-m');    // Format: 2026-01
    $hari_ini_str  = date('Y-m-d');  // Format: 2026-01-10
    $tanggal_skrg  = (int)date('d'); // Angka tanggal (1-31)

    // =================================================================
    // 1. PENDAPATAN BULAN INI
    // =================================================================
    $sqlBulan = "SELECT COALESCE(SUM(harga_jual), 0) 
                 FROM transactions 
                 WHERE DATE_FORMAT(waktu_transaksi, '%Y-%m') = ?";
    $stmt = $conn->prepare($sqlBulan);
    $stmt->execute([$bulan_ini_str]);
    $total_bulan = $stmt->fetchColumn();

    // =================================================================
    // 2. PENDAPATAN HARI INI
    // =================================================================
    $sqlHari = "SELECT COALESCE(SUM(harga_jual), 0) 
                FROM transactions 
                WHERE DATE(waktu_transaksi) = ?";
    $stmt = $conn->prepare($sqlHari);
    $stmt->execute([$hari_ini_str]);
    $total_hari = $stmt->fetchColumn();

    // =================================================================
    // 3. RATA-RATA PER HARI
    // =================================================================
    // Rumus: Total Bulan Ini dibagi Tanggal Hari Ini
    // Contoh: Tgl 10 dapat 1 Juta, berarti rata-rata 100rb/hari.
    $avg_harian = 0;
    if ($tanggal_skrg > 0) {
        $avg_harian = $total_bulan / $tanggal_skrg;
    }

    // =================================================================
    // 4. TOTAL USER ONLINE (REAL-TIME MIKROTIK)
    // =================================================================
    $total_online = 0;

    // Ambil daftar router (Kolom disesuaikan dengan DB Anda)
    $sqlRouters = "SELECT ip_address, 
                          username_mikrotik, 
                          password_mikrotik, 
                          port_api 
                   FROM routers";
    $stmtR = $conn->query($sqlRouters);
    $routers = $stmtR->fetchAll(PDO::FETCH_ASSOC);

    $API = new RouterosAPI();
    $API->timeout = 2; // Timeout cepat 2 detik
    $API->attempts = 1;

    foreach ($routers as $r) {
        // Fallback port jika kosong
        $port = !empty($r['port_api']) ? $r['port_api'] : 8728;

        if ($API->connect($r['ip_address'], $r['username_mikrotik'], $r['password_mikrotik'], $port)) {
            
            // Perintah ringan untuk hitung user aktif
            $activeUsers = $API->comm("/ip/hotspot/active/print", [
                "count-only" => "true"
            ]);
            
            // Validasi hasil (karena library php mikrotik kadang return array kosong atau string)
            $jumlah = 0;
            if (is_array($activeUsers)) {
                $jumlah = count($activeUsers); // Jika return array list user
            } else {
                $jumlah = (int)$activeUsers;   // Jika return angka langsung
            }

            $total_online += $jumlah;
            $API->disconnect();
        } 
    }

    // =================================================================
    // OUTPUT JSON
    // =================================================================
    echo json_encode([
        "status" => true,
        "data" => [
            "pendapatan_bulan_ini" => (int)$total_bulan,
            "pendapatan_hari_ini"  => (int)$total_hari,
            "rata_rata_perhari"    => (int)$avg_harian,
            "total_user_online"    => (int)$total_online
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Error Sistem: " . $e->getMessage()]);
}
?>