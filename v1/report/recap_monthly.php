<?php
// File: v1/report/recap_monthly.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../../config/koneksi.php';
include_once '../auth/validate_token.php'; 

// 1. Cukup Parameter Bulan Saja
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m'); 

// Validasi Format
if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])$/", $bulan)) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Format bulan salah (YYYY-MM)"]);
    exit;
}

try {
    // Siapkan Filter Waktu
    $where_sql = " WHERE DATE_FORMAT(t.waktu_transaksi, '%Y-%m') = ? ";
    $params = [$bulan];

    $summary_data = [];
    $list_data = [];
    $list_key_name = ""; 

    // =================================================================
    // LOGIKA 1: JIKA MITRA (Filter Data Sendiri)
    // =================================================================
    if ($currentUser['role'] == 'mitra') {
        
        $where_sql .= " AND t.mitra_id = ? ";
        $params[] = $currentUser['id'];

        $sqlList = "SELECT 
                        p.nama_tampil as nama,
                        COUNT(t.id) as qty,
                        SUM(t.harga_jual) as total
                    FROM transactions t
                    JOIN profiles p ON t.profile_id = p.id
                    $where_sql
                    GROUP BY t.profile_id
                    ORDER BY qty DESC
                    LIMIT 5";
        
        $list_key_name = "produk_terlaris";
    }

    // =================================================================
    // LOGIKA 2: JIKA ADMIN (Data Global & Peringkat Mitra)
    // =================================================================
    elseif ($currentUser['role'] == 'admin') {
        
        // QUERY LIST PERINGKAT MITRA (+ MITRA ID)
        $sqlList = "SELECT 
                        u.id as mitra_id,   -- <--- INI TAMBAHAN PENTING
                        u.nama_lengkap, u.username,
                        COUNT(t.id) as qty,
                        SUM(t.harga_jual) as total,
                        COALESCE(SUM(CASE WHEN t.is_paid = 0 THEN t.harga_jual ELSE 0 END), 0) as hutang
                    FROM transactions t
                    JOIN users u ON t.mitra_id = u.id
                    $where_sql
                    GROUP BY t.mitra_id
                    ORDER BY total DESC";

        $list_key_name = "peringkat_mitra";
    }

    // =================================================================
    // EKSEKUSI QUERY 1: RINGKASAN (Global / Personal)
    // =================================================================
    $sqlSummary = "SELECT 
                    COUNT(t.id) as total_pcs,
                    COALESCE(SUM(t.harga_jual), 0) as total_omset,
                    COALESCE(SUM(CASE WHEN t.is_paid = 0 THEN t.harga_jual ELSE 0 END), 0) as belum_setor_rp,
                    COUNT(CASE WHEN t.is_paid = 0 THEN 1 END) as belum_setor_pcs,
                    COALESCE(SUM(CASE WHEN t.is_paid = 1 THEN t.harga_jual ELSE 0 END), 0) as sudah_setor_rp,
                    COUNT(CASE WHEN t.is_paid = 1 THEN 1 END) as sudah_setor_pcs
                   FROM transactions t 
                   $where_sql";

    $stmt = $conn->prepare($sqlSummary);
    $stmt->execute($params);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Status Text
    $status_txt = "NIHIL";
    if ($summary['total_omset'] > 0) {
        $status_txt = ($summary['belum_setor_rp'] > 0) ? "BELUM LUNAS" : "LUNAS";
    }

    $summary_data = [
        'status_pembayaran' => $status_txt,
        'total_penjualan'   => "Rp " . number_format($summary['total_omset'], 0, ',', '.'),
        'total_pcs'         => (int)$summary['total_pcs'],
        'belum_setor_rp'    => "Rp " . number_format($summary['belum_setor_rp'], 0, ',', '.'),
        'belum_setor_pcs'   => (int)$summary['belum_setor_pcs'],
        'sudah_setor_rp'    => "Rp " . number_format($summary['sudah_setor_rp'], 0, ',', '.'),
        'sudah_setor_pcs'   => (int)$summary['sudah_setor_pcs']
    ];

    // =================================================================
    // EKSEKUSI QUERY 2: LIST DETAIL
    // =================================================================
    $stmtList = $conn->prepare($sqlList);
    $stmtList->execute($params);
    $rows = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        if ($currentUser['role'] == 'mitra') {
            // -- FORMAT ITEM MITRA --
            $list_data[] = [
                'nama' => $r['nama'],
                'pcs'  => (int)$r['qty'],
                'total_rp' => "Rp " . number_format($r['total'], 0, ',', '.')
            ];
        } else {
            // -- FORMAT ITEM ADMIN --
            $status_mitra = ($r['hutang'] > 0) ? "BELUM LUNAS" : "LUNAS";
            if ($r['total'] == 0) $status_mitra = "NIHIL";

            $list_data[] = [
                'mitra_id' => (int)$r['mitra_id'], // <--- SUDAH MASUK DISINI
                'mitra' => $r['nama_lengkap'] ?: $r['username'],
                'status_bayar' => $status_mitra,
                'pcs'   => (int)$r['qty'],
                'omset' => "Rp " . number_format($r['total'], 0, ',', '.'),
                'belum_setor' => "Rp " . number_format($r['hutang'], 0, ',', '.')
            ];
        }
    }

    // =================================================================
    // OUTPUT JSON
    // =================================================================
    echo json_encode([
        "status" => true,
        "periode" => $bulan,
        "role" => $currentUser['role'], 
        "data" => $summary_data,        
        $list_key_name => $list_data    
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}
?>