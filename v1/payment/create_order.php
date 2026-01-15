<?php
// File: v1/payment/create_order.php
// VERSI: CORE API QRIS (Murni QR Code String)

ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

function loadEnvManual($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        if (!getenv(trim($name))) putenv(trim($name) . '=' . trim($value));
    }
}

try {
    include_once '../../config/koneksi.php';
    if (!isset($conn)) throw new Exception("Koneksi database gagal.");

    if (!getenv('MIDTRANS_SERVER_KEY')) loadEnvManual(__DIR__ . '/../.env');
    $MIDTRANS_SERVER_KEY = getenv('MIDTRANS_SERVER_KEY');
    $IS_PRODUCTION = getenv('MIDTRANS_IS_PRODUCTION') === 'true';

    // ENDPOINT CORE API (Bukan Snap!)
    $api_url = $IS_PRODUCTION 
        ? 'https://api.midtrans.com/v2/charge' 
        : 'https://api.sandbox.midtrans.com/v2/charge';

    $input = json_decode(file_get_contents("php://input"), true);
    $profile_id = $input['profile_id'] ?? 0;
    $no_wa = preg_replace('/[^0-9]/', '', $input['no_wa'] ?? '');

    if (empty($profile_id) || empty($no_wa)) throw new Exception("Data tidak lengkap.");

    $stmt = $conn->prepare("SELECT * FROM profiles WHERE id = ?");
    $stmt->execute([$profile_id]);
    $paket = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$paket) throw new Exception("Paket tidak ditemukan.");

    $trx_id = "TRX-" . date("ymdHis") . rand(100, 999);
    $gross_amount = (int)$paket['harga'];

    // Simpan Order (Pending)
    $conn->prepare("INSERT INTO online_orders (trx_id, profile_id, router_id, admin_id, no_wa, amount, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')")
         ->execute([$trx_id, $paket['id'], $paket['router_id'], $paket['admin_id'], $no_wa, $gross_amount]);
    $order_db_id = $conn->lastInsertId();

    // PAYLOAD CORE API (QRIS)
    $params = [
        'payment_type' => 'qris', // Tipe Pembayaran Langsung QRIS
        'transaction_details' => [
            'order_id' => $trx_id,
            'gross_amount' => $gross_amount,
        ],
        'item_details' => [[
            'id' => $paket['id'],
            'price' => $gross_amount,
            'quantity' => 1,
            'name' => substr($paket['nama_tampil'], 0, 50)
        ]],
        'customer_details' => [
            'first_name' => "Pelanggan",
            'last_name' => "WiFi",
            'phone' => $no_wa
        ],
        'qris' => [
            'acquirer' => 'gopay' // Menggunakan acquirer GoPay (Support semua QRIS)
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($MIDTRANS_SERVER_KEY . ':')
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $response = json_decode($result, true);

    // Cek Hasil Core API
    // Midtrans Core API sukses biasanya 200/201 dan ada 'qr_string' atau 'actions'
    // Cek Hasil Core API
    if ($httpCode >= 200 && $httpCode < 300 && (isset($response['qr_string']) || isset($response['actions']))) {
        
        $qr_string = $response['qr_string'] ?? '';
        
        if (empty($qr_string) && isset($response['actions'])) {
            foreach ($response['actions'] as $action) {
                if ($action['name'] == 'generate-qr-code') {
                    $qr_string = $action['url'];
                }
            }
        }

        // [REVISI DISINI] Simpan QR STRING ke kolom 'snap_token' agar bisa dipanggil lagi nanti
        $conn->prepare("UPDATE online_orders SET snap_token = ? WHERE id = ?")->execute([$qr_string, $order_db_id]);

        echo json_encode([
            "status" => true,
            "data" => [
                "trx_id" => $trx_id,
                "qr_string" => $qr_string
            ]
        ]);

    } else {
        // Gagal
        $conn->prepare("DELETE FROM online_orders WHERE id = ?")->execute([$order_db_id]);
        $msg = $response['status_message'] ?? 'Gagal membuat QRIS';
        throw new Exception("Midtrans Error: " . $msg);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}
?>