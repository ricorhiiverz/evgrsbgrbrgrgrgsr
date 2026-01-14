<?php
// File: v1/payment/create_order.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// Handle Preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

include_once '../../config/koneksi.php';

// Konfigurasi Midtrans
$MIDTRANS_SERVER_KEY = getenv('MIDTRANS_SERVER_KEY');
$IS_PRODUCTION       = getenv('MIDTRANS_IS_PRODUCTION') === 'true';

if (!$MIDTRANS_SERVER_KEY) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Server Key belum dikonfigurasi."]);
    exit;
}

$api_url = $IS_PRODUCTION 
    ? 'https://app.midtrans.com/snap/v1/transactions' 
    : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

// Input
$params = [];
$raw_input = file_get_contents("php://input");
$json_data = json_decode($raw_input, true);
if (is_array($json_data)) $params = array_merge($params, $json_data);

$profile_id = isset($params['profile_id']) ? (int)$params['profile_id'] : 0;
$no_wa      = isset($params['no_wa']) ? preg_replace('/[^0-9]/', '', $params['no_wa']) : '';

if (empty($profile_id) || empty($no_wa)) {
    echo json_encode(["status" => false, "message" => "Data tidak lengkap."]);
    exit;
}

try {
    // FIX QUERY: Gunakan nama_tampil sebagai nama
    $sql = "SELECT id, nama_tampil as nama, harga, router_id, admin_id 
            FROM profiles 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$profile_id]);
    $paket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paket) throw new Exception("Paket tidak ditemukan.");

    // Buat TRX ID
    $trx_id = "TRX-" . date("ymd") . "-" . rand(1000, 9999);
    $gross_amount = (int)$paket['harga'];

    // Insert Order
    $sqlInsert = "INSERT INTO online_orders 
                  (trx_id, profile_id, router_id, admin_id, no_wa, amount, status) 
                  VALUES (?, ?, ?, ?, ?, ?, 'pending')";
    $conn->prepare($sqlInsert)->execute([
        $trx_id, $paket['id'], $paket['router_id'], $paket['admin_id'], $no_wa, $gross_amount
    ]);

    // Midtrans Payload
    $midtrans_params = [
        'transaction_details' => ['order_id' => $trx_id, 'gross_amount' => $gross_amount],
        'item_details'        => [[
            'id' => $paket['id'], 
            'price' => $gross_amount, 
            'quantity' => 1, 
            'name' => substr($paket['nama'], 0, 50)
        ]],
        'customer_details'    => [
            'first_name' => "User", 
            'last_name' => $no_wa, 
            'email' => "guest@voucherku.id", 
            'phone' => $no_wa
        ],
        'credit_card'         => ['secure' => true],
        'custom_expiry'       => ['expiry_duration' => 60, 'unit' => 'minute']
    ];

    // Request CURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($midtrans_params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($MIDTRANS_SERVER_KEY . ':')
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseMidtrans = json_decode($result, true);

    if ($httpCode != 201 || empty($responseMidtrans['token'])) {
        // Hapus order gagal
        $conn->prepare("DELETE FROM online_orders WHERE trx_id = ?")->execute([$trx_id]);
        throw new Exception("Gagal Midtrans: " . ($responseMidtrans['error_messages'][0] ?? 'Unknown Error'));
    }

    $snap_token = $responseMidtrans['token'];
    
    // Simpan Token
    $conn->prepare("UPDATE online_orders SET snap_token = ? WHERE trx_id = ?")
         ->execute([$snap_token, $trx_id]);

    echo json_encode([
        "status" => true,
        "message" => "OK",
        "data" => [
            "trx_id" => $trx_id,
            "snap_token" => $snap_token,
            "redirect_url" => $responseMidtrans['redirect_url']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}
?>