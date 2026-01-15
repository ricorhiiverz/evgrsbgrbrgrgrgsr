<?php
// File: v1/payment/check_status.php
// VERSI: REVISI TAMPILAN FULL WIDTH & RESPONSIF

include_once '../../config/koneksi.php';
include_once '../../libs/routeros_api.class.php'; 

$trx_id = isset($_GET['trx_id']) ? $_GET['trx_id'] : '';
$order = null;
$link_beranda = "order.php"; // Default Link jika data tidak ditemukan

if ($trx_id) {
    // 1. AMBIL DATA LENGKAP
    $stmt = $conn->prepare("SELECT o.*, 
                                   p.nama_tampil, p.validity, p.harga, p.nama_mikrotik,
                                   r.ip_address, r.username_mikrotik, r.password_mikrotik, r.port_api,
                                   u.username as admin_username 
                            FROM online_orders o
                            JOIN profiles p ON o.profile_id = p.id
                            JOIN routers r ON o.router_id = r.id
                            LEFT JOIN users u ON o.admin_id = u.id 
                            WHERE o.trx_id = ?");
    $stmt->execute([$trx_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    // Set Link Beranda sesuai pemilik toko
    if ($order && !empty($order['admin_username'])) {
        $link_beranda = "order.php?u=" . $order['admin_username'];
    }

    // 2. LOGIC AUTO-RETRY
    if ($order && $order['status'] == 'paid' && empty($order['voucher_user'])) {
        // Bersihkan Data
        $raw_ip   = trim($order['ip_address']);
        $user     = trim($order['username_mikrotik']);
        $pass     = trim($order['password_mikrotik']);
        $port     = (int)$order['port_api'];

        // Deteksi Port di IP
        if (strpos($raw_ip, ':') !== false) {
            list($host_domain, $port_custom) = explode(':', $raw_ip);
            $host_domain = trim($host_domain);
            $port        = (int)$port_custom;
        } else {
            $host_domain = $raw_ip;
        }

        // Bypass DNS
        $host_ip = gethostbyname($host_domain);

        // Generate User Baru
        $gen_user = "ONL".rand(11111111, 99999999);
        $gen_pass = $gen_user;

        // Koneksi ke Mikrotik
        $API = new RouterosAPI();
        $API->debug = false;
        $API->timeout = 3; 
        $API->attempts = 2;
        $API->port = $port; 

        if ($API->connect($host_ip, $user, $pass)) {
            $API->comm("/ip/hotspot/user/add", [
                "server"   => "all",
                "profile"  => trim($order['nama_mikrotik']),
                "name"     => $gen_user,
                "password" => $gen_pass,
                "comment"  => "LUNAS(CLAIM): " . $trx_id
            ]);
            $API->disconnect();

            // Update Database
            $conn->prepare("UPDATE online_orders SET voucher_user = ? WHERE id = ?")
                 ->execute([$gen_user, $order['id']]);
            
            // Catat Transaksi
            $waktu_sekarang = date('Y-m-d H:i:s');
            $conn->prepare("INSERT INTO transactions (mitra_id, admin_id, router_id, profile_id, kode_voucher, harga_jual, status, waktu_transaksi) VALUES (0, ?, ?, ?, ?, ?, 'lunas', ?)")
                 ->execute([$order['admin_id'], $order['router_id'], $order['profile_id'], $gen_user, $order['harga'], $waktu_sekarang]);

            $order['voucher_user'] = $gen_user;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Status Pesanan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        :root { 
            --primary-gradient: linear-gradient(135deg, #05a04e 0%, #0099ff 100%); 
            --app-bg: #f0f2f5; 
            --card-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        
        body { 
            background-color: var(--app-bg); 
            font-family: 'Poppins', sans-serif; 
            margin: 0; 
            padding: 0;
            min-height: 100vh;
        }

        /* Container Utama - Dibuat agar pas tengah di desktop, full di HP */
        .app-container { 
            width: 100%; 
            max-width: 480px; /* Batas lebar seperti aplikasi HP */
            min-height: 100vh; /* Wajib full tinggi layar */
            background-color: #ffffff; 
            margin: 0 auto; /* Tengah secara horizontal */
            display: flex; 
            flex-direction: column; 
            position: relative; 
            box-shadow: 0 0 30px rgba(0,0,0,0.05); 
        }

        .header-section { 
            flex: 0 0 auto; 
            background: var(--primary-gradient); 
            color: white; 
            padding: 30px 20px 50px 20px; 
            text-align: center; 
            border-bottom-left-radius: 30px; 
            border-bottom-right-radius: 30px; 
            z-index: 10; 
            position: relative;
        }

        .content-container { 
            flex: 1 0 auto; /* Mengisi sisa ruang agar footer terdorong ke bawah */
            padding: 0 20px; 
            margin-top: -35px; /* Efek overlap ke header */
            z-index: 20; 
            padding-bottom: 40px;
            width: 100%;
        }

        .status-card { 
            background: white; 
            border-radius: 20px; 
            padding: 30px 25px; 
            text-align: center; 
            box-shadow: var(--card-shadow); 
            border: 1px solid rgba(0,0,0,0.03); 
            margin-bottom: 20px; 
            width: 100%; /* Paksa lebar penuh container */
        }

        .icon-status { 
            width: 80px; 
            height: 80px; 
            border-radius: 50%; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 36px; 
            margin-bottom: 20px; 
        }
        
        .status-paid { background: #eafff2; color: #05a04e; }
        .status-pending { background: #fff8e1; color: #ffc107; }
        .status-failed { background: #fee2e2; color: #ef4444; }

        .voucher-box { 
            background: #f8fbff; 
            border: 2px dashed #0099ff; 
            border-radius: 15px; 
            padding: 20px; 
            margin-top: 25px; 
        }
        
        .voucher-code { 
            font-size: 2.2rem; 
            font-weight: 800; 
            letter-spacing: 3px; 
            color: #0099ff; 
            margin: 10px 0; 
            word-break: break-all;
        }

        /* Footer Sticky di Bawah */
        .footer-nav { 
            flex: 0 0 auto; 
            background: white; 
            padding: 20px; 
            text-align: center; 
            border-top: 1px solid #f0f0f0;
            width: 100%;
        }
        
        .btn-home { 
            background: var(--primary-gradient); 
            color: white; 
            border: none; 
            border-radius: 50px; 
            padding: 14px 0; 
            font-weight: 700; 
            width: 100%; 
            text-decoration: none; 
            display: block; 
            box-shadow: 0 5px 20px rgba(0, 153, 255, 0.25); 
            transition: transform 0.2s;
        }
        
        .btn-home:active { transform: scale(0.98); }
        
        /* Utility text */
        .text-label { color: #8898aa; font-size: 0.85rem; }
        .text-value { color: #32325d; font-weight: 600; font-size: 1rem; }
    </style>
</head>
<body>

<div class="app-container">
    <div class="header-section">
        <h2 class="fw-bold mb-1">Status Pesanan</h2>
        <p class="mb-0 opacity-75 small">Detail transaksi Anda</p>
    </div>

    <div class="content-container">
        <?php if (!$trx_id || !$order): ?>
            <div class="status-card">
                <div class="icon-status status-failed"><i class="fas fa-search"></i></div>
                <h5 class="fw-bold">Tidak Ditemukan</h5>
                <p class="text-muted small">Data pesanan tidak ditemukan atau ID salah.</p>
                <a href="order.php" class="btn btn-sm btn-outline-secondary rounded-pill px-4 mt-3">Kembali</a>
            </div>
        <?php else: ?>
            
            <div class="status-card">
                <?php if ($order['status'] == 'paid'): ?>
                    <div class="icon-status status-paid"><i class="fas fa-check"></i></div>
                    <h4 class="fw-bold text-success mb-1">Pembayaran Lunas</h4>
                    <p class="text-muted small">Terima kasih atas pembayaran Anda</p>
                <?php elseif ($order['status'] == 'pending'): ?>
                    <div class="icon-status status-pending"><i class="fas fa-clock"></i></div>
                    <h4 class="fw-bold text-warning mb-1">Menunggu Bayar</h4>
                    <p class="text-muted small">Silakan selesaikan pembayaran</p>
                <?php else: ?>
                    <div class="icon-status status-failed"><i class="fas fa-times-circle"></i></div>
                    <h4 class="fw-bold text-danger mb-1">Transaksi Gagal</h4>
                    <p class="text-muted small">Pesanan dibatalkan atau kadaluarsa</p>
                <?php endif; ?>

                <hr class="my-4 opacity-10">
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-label">Produk</span>
                    <span class="text-value"><?php echo htmlspecialchars($order['nama_tampil']); ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-label">Total Bayar</span>
                    <span class="text-value text-primary">Rp <?php echo number_format($order['amount'], 0, ',', '.'); ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-0">
                    <span class="text-label">Tanggal</span>
                    <span class="text-value small"><?php echo date('d M Y H:i', strtotime($order['created_at'])); ?></span>
                </div>

                <?php if ($order['status'] == 'paid'): ?>
                    
                    <?php if (!empty($order['voucher_user'])): ?>
                        <div class="voucher-box">
                            <div class="text-uppercase text-muted small fw-bold mb-1">KODE VOUCHER</div>
                            <div class="voucher-code" id="voucherCode"><?php echo $order['voucher_user']; ?></div>
                            <button class="btn btn-sm btn-outline-primary rounded-pill px-4 mt-2" onclick="copyVoucher()">
                                <i class="fas fa-copy me-1"></i> Salin Kode
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger mt-4 small text-start border-0 bg-danger-subtle text-danger rounded-3">
                            <i class="fas fa-exclamation-triangle me-1"></i> <b>Voucher belum muncul?</b><br>
                            Sistem sedang mencoba menghubungi router. Silakan tekan tombol di bawah.
                        </div>
                        <button onclick="location.reload();" class="btn btn-warning w-100 rounded-pill text-white fw-bold shadow-sm py-3 mt-2">
                            <i class="fas fa-sync-alt me-1"></i> GENERATE ULANG
                        </button>
                    <?php endif; ?>

                <?php elseif ($order['status'] == 'pending'): ?>
                    <div class="mt-4 pt-2">
                        <a href="pay.php?trx_id=<?php echo $order['trx_id']; ?>&qr=<?php echo urlencode($order['snap_token']); ?>" 
                           class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow-sm" 
                           style="background: linear-gradient(90deg, #0099ff, #05a04e); border:none;">
                            BAYAR SEKARANG <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    </div>
                <?php endif; ?>

            </div>
        <?php endif; ?>
    </div>

    <div class="footer-nav">
        <a href="<?php echo $link_beranda; ?>" class="btn-home">
            <i class="fas fa-home me-2"></i> KEMBALI KE BERANDA
        </a>
    </div>
</div>

<script>
    function copyVoucher() {
        var copyText = document.getElementById("voucherCode");
        navigator.clipboard.writeText(copyText.innerText).then(function() {
            // Opsional: Ganti icon/text tombol sementara
            alert("Kode Voucher berhasil disalin!");
        }, function(err) {
            alert("Gagal menyalin: " + err);
        });
    }
</script>
</body>
</html>