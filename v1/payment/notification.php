<?php
// File: v1/payment/notification.php
// VERSI: 10 DIGIT ANGKA (User = Password)

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../../config/koneksi.php';
include_once '../../libs/routeros_api.class.php';

function writeLog($msg) {
    $logFile = 'notification.log';
    $date = date('Y-m-d H:i:s');
    $remote = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    file_put_contents($logFile, "[$date] [$remote] $msg" . PHP_EOL, FILE_APPEND);
}

try {
    $json_result = file_get_contents('php://input');
    $notif = json_decode($json_result, true);

    if (!$notif) {
        echo "Notification System Ready.";
        exit;
    }

    $trx_id = $notif['order_id'] ?? 'Unknown';
    $transaction_status = $notif['transaction_status'] ?? '';
    $fraud_status = $notif['fraud_status'] ?? '';

    // 1. Cek Order
    $stmt = $conn->prepare("SELECT * FROM online_orders WHERE trx_id = ? LIMIT 1");
    $stmt->execute([$trx_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        writeLog("Order ID tidak ditemukan: $trx_id");
        http_response_code(404);
        exit;
    }

    if ($order['status'] == 'paid' && !empty($order['voucher_user'])) {
        exit("Order sudah lunas & voucher sudah ada.");
    }

    // 2. Status Pembayaran
    $is_paid = false;
    if ($transaction_status == 'capture') {
        if ($fraud_status == 'accept') $is_paid = true;
    } else if ($transaction_status == 'settlement') {
        $is_paid = true;
    } else if (in_array($transaction_status, ['cancel', 'deny', 'expire'])) {
        $status_db = ($transaction_status == 'expire') ? 'expired' : 'failed';
        $conn->prepare("UPDATE online_orders SET status = ? WHERE id = ?")->execute([$status_db, $order['id']]);
        writeLog("Transaksi $trx_id STATUS: $transaction_status");
        exit;
    }

    // ======================================================
    // LOGIKA GENERATE VOUCHER (10 DIGIT)
    // ======================================================
    if ($is_paid) {
        $conn->prepare("UPDATE online_orders SET status = 'paid' WHERE id = ?")->execute([$order['id']]);
        writeLog("Pembayaran $trx_id LUNAS. Memulai proses Mikrotik...");

        // Ambil Data Router
        $sqlRouter = "SELECT r.ip_address, r.username_mikrotik, r.password_mikrotik, r.port_api, 
                             p.nama_mikrotik, p.harga
                      FROM profiles p
                      JOIN routers r ON p.router_id = r.id
                      WHERE p.id = ?";
        $stmtR = $conn->prepare($sqlRouter);
        $stmtR->execute([$order['profile_id']]);
        $data = $stmtR->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            writeLog("Data Profile hilang untuk $trx_id");
            exit;
        }

        // --- Parsing IP & Port (Fix Port) ---
        $raw_ip = $data['ip_address'];
        $host_clean = $raw_ip;
        $port_final = 8728; 

        if (strpos($raw_ip, ':') !== false) {
            $parts = explode(':', $raw_ip);
            $host_clean = $parts[0];
            $port_final = (int)$parts[1]; 
        } else if (!empty($data['port_api'])) {
            $port_final = (int)$data['port_api'];
        }

        writeLog("Target: $host_clean | Port: $port_final");

        // --- REVISI: GENERATE 10 DIGIT ANGKA ---
        $gen_user = (string) rand(1000000000, 9999999999); 
        // Password disamakan dengan User (untuk mode Voucher Code Only)
        $gen_pass = $gen_user; 

        // --- KONEKSI ---
        $API = new RouterosAPI();
        $API->debug = false;
        $API->port = $port_final; 

        if ($API->connect($host_clean, $data['username_mikrotik'], $data['password_mikrotik'])) {
            
            // Buat User di Mikrotik
            // Kita set password sama dengan name agar user mudah login
            $API->comm("/ip/hotspot/user/add", [
                "server"   => "all",
                "profile"  => $data['nama_mikrotik'],
                "name"     => $gen_user,
                "password" => $gen_pass, // Password diisi kode yang sama
                "comment"  => "VC-ONLINE: " . $trx_id . " (" . $order['no_wa'] . ")"
            ]);
            $API->disconnect();

            // REVISI SQL: HAPUS update ke kolom 'voucher_pass'
            $conn->prepare("UPDATE online_orders SET voucher_user = ? WHERE id = ?")
                 ->execute([$gen_user, $order['id']]);
            
            // Catat Transaksi Admin
            $conn->prepare("INSERT INTO transactions (mitra_id, admin_id, router_id, profile_id, kode_voucher, harga_jual, status, waktu_transaksi) VALUES (0, ?, ?, ?, ?, ?, 'lunas', NOW())")
                 ->execute([$order['admin_id'], $order['router_id'], $order['profile_id'], $gen_user, $data['harga']]);

            writeLog("SUKSES: Voucher $gen_user berhasil dibuat.");

        } else {
            writeLog("GAGAL KONEK ke $host_clean port $port_final.");
        }
    }

} catch (Exception $e) {
    writeLog("SYSTEM ERROR: " . $e->getMessage());
    http_response_code(500);
}
?>