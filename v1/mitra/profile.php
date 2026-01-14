<?php
// File: v1/mitra/profile.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");

include_once '../../config/koneksi.php';
include_once '../auth/validate_token.php'; 

// 1. KEAMANAN: Pastikan yang akses adalah MITRA
if ($currentUser['role'] != 'mitra') {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => "Akses ditolak."]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$mitra_id = $currentUser['id'];

try {
    // =================================================================
    // MODE: LIHAT PROFIL (GET)
    // =================================================================
    if ($method == 'GET') {
        
        // Sesuai struktur tabel users Anda:
        // id, username, password, role, parent_id, tagihan, nama_lengkap, prefix, no_wa, api_token, created_at
        $sql = "SELECT id, username, nama_lengkap, no_wa, prefix, tagihan, created_at 
                FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$mitra_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            // Format Tagihan
            $data['tagihan_rp'] = "Rp " . number_format($data['tagihan'], 0, ',', '.');
            
            // Format Tanggal Gabung
            $data['joined_at'] = date("d M Y", strtotime($data['created_at']));

            echo json_encode([
                "status" => true,
                "data"   => $data
            ]);
        } else {
            http_response_code(404);
            echo json_encode(["status" => false, "message" => "User tidak ditemukan."]);
        }
    }

    // =================================================================
    // MODE: UPDATE PROFIL (POST)
    // =================================================================
    elseif ($method == 'POST') {
        
        // Terima Input
        $params = [];
        $raw = file_get_contents("php://input");
        $json = json_decode($raw, true);
        if (is_array($json)) $params = array_merge($params, $json);
        if (!empty($_POST))  $params = array_merge($params, $_POST);

        // Validasi Input (Nama & No WA)
        $nama = isset($params['nama_lengkap']) ? trim($params['nama_lengkap']) : null;
        $wa   = isset($params['no_wa']) ? trim($params['no_wa']) : null;

        if (empty($nama) || empty($wa)) {
            http_response_code(400);
            echo json_encode(["status" => false, "message" => "Nama Lengkap dan No WA wajib diisi."]);
            exit;
        }

        // Eksekusi Update
        $sqlUpdate = "UPDATE users SET nama_lengkap = ?, no_wa = ? WHERE id = ?";
        $stmtUpd = $conn->prepare($sqlUpdate);
        $result = $stmtUpd->execute([$nama, $wa, $mitra_id]);

        if ($result) {
            echo json_encode([
                "status" => true, 
                "message" => "Profil berhasil diperbarui.",
                "data" => [
                    "nama_lengkap" => $nama,
                    "no_wa" => $wa
                ]
            ]);
        } else {
            throw new Exception("Gagal mengupdate database.");
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}
?>