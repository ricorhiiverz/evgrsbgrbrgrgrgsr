<?php
// File: v1/mitra/history.php

// 1. Setup
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../../config/koneksi.php';

// Fungsi helper response (Jaga-jaga)
if (!function_exists('sendResponse')) {
    function sendResponse($success, $message, $data = null) {
        echo json_encode(['status' => $success, 'message' => $message, 'data' => $data]);
        exit;
    }
}

include_once '../auth/validate_token.php'; 
// Output: $currentUser (Array Data User) & $USER_ROLE

// 2. Parameter
$case    = isset($_GET['case']) ? $_GET['case'] : 'all';
$page    = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit   = 50; 
$offset  = ($page - 1) * $limit;

try {
    // 3. BASE QUERY
    // [PERUBAHAN DISINI]: Mengambil u.nama_lengkap alih-alih u.username
    $query = "SELECT t.id, t.kode_voucher, t.status, t.expire, t.waktu_transaksi,
                     p.nama_tampil as nama_paket, p.harga,
                     r.nama_router,
                     u.nama_lengkap as nama_mitra  
              FROM transactions t
              JOIN profiles p ON t.profile_id = p.id
              JOIN routers r ON t.router_id = r.id
              JOIN users u ON t.mitra_id = u.id
              WHERE 1=1 "; 

    $params = []; 

    // -----------------------------------------------------------
    // 4. LOGIKA KEAMANAN (ROLE FILTER)
    // -----------------------------------------------------------
    $role_sekarang = $currentUser['role'];
    $id_sekarang   = $currentUser['id'];

    if ($role_sekarang == 'mitra') {
        // MITRA: Hanya lihat data miliknya
        $query .= " AND t.mitra_id = ? ";
        $params[] = $id_sekarang;
        
    } else if ($role_sekarang == 'admin') {
        // ADMIN: Bisa filter per mitra tertentu
        if (isset($_GET['mitra_id']) && !empty($_GET['mitra_id'])) {
            $query .= " AND t.mitra_id = ? ";
            $params[] = $_GET['mitra_id'];
        }
    }

    // -----------------------------------------------------------
    // 5. FILTER FITUR
    // -----------------------------------------------------------
    switch ($case) {
        case 'search':
            $keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
            if (!empty($keyword)) {
                $query .= " AND t.kode_voucher LIKE ? ";
                $params[] = "%$keyword%";
            }
            break;

        case 'active':
            $query .= " AND t.status LIKE ? ";
            $params[] = "Aktif%";
            break;

        case 'pending': 
            $query .= " AND t.status = ? ";
            $params[] = "belum diaktifkan";
            break;

        case 'all':
        default:
            break;
    }

    // 6. Sorting & Pagination
    $query .= " ORDER BY t.waktu_transaksi DESC LIMIT $limit OFFSET $offset ";

    // 7. Eksekusi
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. Formatting Data
    $data_output = [];
    $now = date('Y-m-d H:i:s');

    foreach ($results as $row) {
        
        $status_final = $row['status'];
        $is_expired = false;

        // Logic Status
        if ($row['expire'] != NULL && $now > $row['expire']) {
            $status_final = "Expired (Waktu Habis)";
            $is_expired = true;
        } elseif ($row['status'] == 'belum diaktifkan') {
            $status_final = "Belum Diaktifkan";
        }
        
        $harga_fmt = "Rp " . number_format($row['harga'], 0, ',', '.');

        $item = [
            'id' => $row['id'],
            'kode_voucher' => $row['kode_voucher'],
            'nama_paket' => $row['nama_paket'],
            'harga' => $harga_fmt,
            'router' => $row['nama_router'],
            
            // Nama Lengkap Mitra (Budi Santoso, Warung Berkah, dll)
            'nama_mitra' => $row['nama_mitra'], 

            // Status UI
            'status_text' => $status_final,
            'status_raw'  => $row['status'],
            'is_expired'  => $is_expired,
            
            'expire_date' => $row['expire'],
            'tgl_transaksi' => $row['waktu_transaksi']
        ];

        $data_output[] = $item;
    }

    echo json_encode([
        "status" => true,
        "role_request" => $role_sekarang,
        "mode"   => $case,
        "count"  => count($data_output),
        "data"   => $data_output
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Db Error: " . $e->getMessage()]);
}
?>