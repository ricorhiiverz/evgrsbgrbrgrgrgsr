<?php
// File: v1/payment/notification.php
// VERSI: PRIORITY DOMAIN PORT (Mengutamakan Port di string IP Address)

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include '../../config/koneksi.php';
include '../../libs/routeros_api.class.php';

function writeLog($msg) {
    file_put_contents('notification.log', "[" . date('Y-m-d H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
}

try {
    // 1. Terima Data
    $json_result = file_get_contents('php://input');
    $notif = json_decode($json_result, true);

    if (!$notif) { echo "Notification System Ready."; exit; }

    $trx_id = $notif['order_id'] ?? '';
    $transaction_status = $notif['transaction_status'] ?? '';
    $fraud_status = $notif['fraud_status'] ?? '';

    // 2. Ambil Data Order
    $stmt = $conn->prepare("SELECT * FROM online_orders WHERE trx_id = ? LIMIT 1");
    $stmt->execute([$trx_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        writeLog("Order ID Missing: $trx_id");
        http_response_code(404); exit;
    }

    if ($order['status'] == 'paid' && !empty($order['voucher_user'])) {
        exit("OK: Done.");
    }

    // 3. Cek Pembayaran
    $is_paid = false;
    if ($transaction_status == 'capture') {
        if ($fraud_status == 'accept') $is_paid = true;
    } else if ($transaction_status == 'settlement') {
        $is_paid = true;
    } else if (in_array($transaction_status, ['cancel', 'deny', 'expire'])) {
        $conn->prepare("UPDATE online_orders SET status = 'failed' WHERE id = ?")->execute([$order['id']]);
        exit("OK: Failed.");
    }

    if ($is_paid) {
        $conn->prepare("UPDATE online_orders SET status = 'paid' WHERE id = ?")->execute([$order['id']]);
        writeLog("Status $trx_id -> PAID.");
        
        // Buat Voucher
        createVoucherIfNotExists($conn, $order);
    }

    http_response_code(200);

} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
    http_response_code(500);
}

function createVoucherIfNotExists($conn, $order) {
    
    // Ambil Data Router
    $sql = "SELECT r.ip_address, r.username_mikrotik, r.password_mikrotik, r.port_api, 
                   p.nama_mikrotik, p.harga
            FROM routers r
            JOIN profiles p ON p.id = ? 
            WHERE r.id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([$order['profile_id'], $order['router_id']]);
    $dataPaket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dataPaket) return false;

    // Bersihkan Data
    $raw_ip   = trim($dataPaket['ip_address']); // Isinya "ttn.rictech.my.id:1043"
    $user     = trim($dataPaket['username_mikrotik']);
    $pass     = trim($dataPaket['password_mikrotik']);
    
    // Default Port dari Database (8728)
    $port     = (int)$dataPaket['port_api']; 

    // [LOGIKA BARU] Deteksi Port di dalam String IP Address
    // Jika ada tanda ':', kita ambil port dari situ dan ABAIKAN default port database
    if (strpos($raw_ip, ':') !== false) {
        list($host_domain, $port_custom) = explode(':', $raw_ip);
        $host_domain = trim($host_domain); // "ttn.rictech.my.id"
        $port        = (int)$port_custom;  // "1043" (Ini yang kita pakai!)
    } else {
        $host_domain = $raw_ip;
    }

    // Bypass DNS (Ubah Domain jadi IP Angka biar gak Timeout)
    $host_ip = gethostbyname($host_domain);

    writeLog("Connecting: $host_domain ($host_ip) Port: $port User: $user");

    $gen_user = "ONL".rand(11111111, 99999999);
    $gen_pass = $gen_user; 

    $API = new RouterosAPI();
    $API->debug = false;
    $API->timeout = 5; 
    
    // Set Port Manual (Wajib untuk Library Lama)
    $API->port = $port; 

    // Connect (Cukup 3 Parameter: IP, User, Pass)
    if ($API->connect($host_ip, $user, $pass)) {
        
        $API->comm("/ip/hotspot/user/add", [
            "server"   => "all",
            "profile"  => trim($dataPaket['nama_mikrotik']),
            "name"     => $gen_user,
            "password" => $gen_pass,
            "comment"  => "LUNAS: " . $order['trx_id'] . " (" . $order['no_wa'] . ")"
        ]);
        $API->disconnect();

        // Update DB
        $conn->prepare("UPDATE online_orders SET voucher_user = ? WHERE id = ?")
             ->execute([$gen_user, $order['id']]);

        // Insert Transaksi
        $waktu = date('Y-m-d H:i:s');
        $conn->prepare("INSERT INTO transactions (mitra_id, admin_id, router_id, profile_id, kode_voucher, harga_jual, status, waktu_transaksi) VALUES (0, ?, ?, ?, ?, ?, 'lunas', ?)")
             ->execute([$order['admin_id'], $order['router_id'], $order['profile_id'], $gen_user, $dataPaket['harga'], $waktu]);
        
        writeLog("SUKSES: Voucher $gen_user dibuat.");
        return true;

    } else {
        writeLog("GAGAL KONEK. IP: $host_ip Port: $port");
        return false;
    }
}
?>