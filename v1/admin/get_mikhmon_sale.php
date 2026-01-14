<?php
// File: v1/admin/get_mikhmon_sales.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../../config/koneksi.php';
include_once '../../libs/routeros_api.class.php'; 
include_once '../auth/validate_token.php'; 

// 1. Keamanan: Khusus Admin
if ($currentUser['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => "Akses ditolak."]);
    exit;
}

// 2. Ambil Parameter Bulan (Format: YYYY-MM)
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');

if (strlen($filter_bulan) !== 7) {
    $filter_bulan = date('Y-m');
}

try {
    $total_penjualan_mikhmon = 0;
    
    // Ambil semua router
    $sqlRouters = "SELECT ip_address, username_mikrotik, password_mikrotik, port_api FROM routers";
    $stmt = $conn->query($sqlRouters);
    $routers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $API = new RouterosAPI();
    $API->timeout = 3; 
    $API->attempts = 1;

    foreach ($routers as $r) {
        $port = !empty($r['port_api']) ? $r['port_api'] : 8728;

        if ($API->connect($r['ip_address'], $r['username_mikrotik'], $r['password_mikrotik'], $port)) {
            
            // Ambil script dari Mikrotik
            $scripts = $API->comm("/system/script/print");
            
            foreach ($scripts as $script) {
                $raw_name = isset($script['name']) ? $script['name'] : '';

                // Filter berdasarkan Bulan & Format Mikhmon (-|-)
                if (strpos($raw_name, $filter_bulan) === 0 && strpos($raw_name, '-|-') !== false) {
                    
                    $parts = explode("-|-", $raw_name);

                    // Ambil harga (elemen ke-4)
                    if (isset($parts[3])) {
                        $harga = (int)$parts[3];
                        $total_penjualan_mikhmon += $harga;
                    }
                }
            }
            
            $API->disconnect();
        }
    }

    // =================================================================
    // LOGIKA HITUNG RATA-RATA HARIAN
    // =================================================================
    $bulan_ini = date('Y-m');
    $pembagi_hari = 1; // Default hindari division by zero

    if ($filter_bulan == $bulan_ini) {
        // A. Jika filter bulan ini, bagi dengan tanggal hari ini (Real performance)
        $pembagi_hari = (int)date('d'); 
    } else {
        // B. Jika bulan lalu, bagi dengan total hari bulan tersebut (misal: 30/31/28)
        $pembagi_hari = (int)date('t', strtotime($filter_bulan . "-01"));
    }

    // Koreksi jika pembagi 0 (misal tgl 1 belum lewat jam 00:00)
    if ($pembagi_hari < 1) $pembagi_hari = 1;

    $rata_rata = $total_penjualan_mikhmon / $pembagi_hari;

    // Output JSON
    echo json_encode([
        "status"  => true,
        "periode" => $filter_bulan,
        "total"   => "Rp " . number_format($total_penjualan_mikhmon, 0, ',', '.'),
        "total_raw" => $total_penjualan_mikhmon,
        
        // Data Baru: Rata-rata
        "rata_rata" => "Rp " . number_format($rata_rata, 0, ',', '.'),
        "rata_rata_raw" => round($rata_rata),
        "hari_terhitung" => $pembagi_hari // Info tambahan: Dibagi berapa hari?
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}
?>