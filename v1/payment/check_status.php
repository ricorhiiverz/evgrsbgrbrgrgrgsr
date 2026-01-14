<?php
// File: v1/payment/check_status.php
// VERSI: TAMPILAN KODE VOUCHER SAJA (Tanpa Password)

include_once '../../config/koneksi.php';

$trx_id = isset($_GET['trx_id']) ? $_GET['trx_id'] : '';
$order = null;
$default_wa = "6281234567890"; // Ganti No WA Admin

if ($trx_id) {
    $stmt = $conn->prepare("SELECT o.*, p.nama_tampil, p.validity 
                            FROM online_orders o
                            JOIN profiles p ON o.profile_id = p.id
                            WHERE o.trx_id = ?");
    $stmt->execute([$trx_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
}

$clientKey = getenv('MIDTRANS_CLIENT_KEY');
$isProduction = getenv('MIDTRANS_IS_PRODUCTION') === 'true';
$midtransJs = $isProduction 
    ? "https://app.midtrans.com/snap/snap.js" 
    : "https://app.sandbox.midtrans.com/snap/snap.js";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Voucher</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: sans-serif; }
        .card-status { border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .voucher-box { background: #fff; border: 2px dashed #0d6efd; border-radius: 10px; padding: 25px; position: relative; }
        .voucher-code { font-size: 2.5rem; font-weight: 800; letter-spacing: 3px; color: #0d6efd; }
    </style>
</head>
<body>

<div class="container py-5" style="max-width: 500px;">
    
    <div class="card card-status p-4 text-center">
        
        <?php if (!$order): ?>
            <h4 class="mb-4">Cek Status Pesanan</h4>
            <form action="" method="GET">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" name="trx_id" placeholder="Masukkan Kode TRX..." value="<?php echo htmlspecialchars($trx_id); ?>" required>
                    <button class="btn btn-primary" type="submit">Cari</button>
                </div>
            </form>
            <?php if ($trx_id): ?>
                <div class="alert alert-danger">Transaksi tidak ditemukan.</div>
            <?php endif; ?>

        <?php else: ?>
            
            <div class="mb-4">
                <?php if ($order['status'] == 'paid'): ?>
                    <i class="fas fa-check-circle text-success fa-3x mb-2"></i>
                    <h4 class="text-success fw-bold">Pembayaran Berhasil</h4>
                <?php elseif ($order['status'] == 'pending'): ?>
                    <i class="fas fa-clock text-warning fa-3x mb-2"></i>
                    <h4 class="text-warning fw-bold">Menunggu Pembayaran</h4>
                <?php else: ?>
                    <i class="fas fa-times-circle text-danger fa-3x mb-2"></i>
                    <h4 class="text-danger">Transaksi Gagal</h4>
                <?php endif; ?>
                <p class="text-muted mb-0"><?php echo $order['nama_tampil']; ?></p>
            </div>

            <?php if ($order['status'] == 'paid'): ?>
                
                <?php if (!empty($order['voucher_user'])): ?>
                    <div class="voucher-box mb-3">
                        <div class="text-uppercase text-muted small fw-bold mb-2">Kode Voucher Anda</div>
                        <div class="voucher-code" id="voucherCode"><?php echo $order['voucher_user']; ?></div>
                        
                        <button class="btn btn-primary mt-3 w-100" onclick="copyVoucher()">
                            <i class="fas fa-copy me-2"></i> Salin Kode
                        </button>
                    </div>
                    <div class="alert alert-info small text-start">
                        <i class="fas fa-info-circle me-1"></i> Masukkan kode ini di halaman login WiFi.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <b>Voucher Belum Terbit</b><br>
                        Pembayaran diterima tapi gagal terhubung ke router.
                    </div>
                    <a href="https://wa.me/<?php echo $default_wa; ?>" class="btn btn-success w-100">Hubungi Admin</a>
                <?php endif; ?>

            <?php elseif ($order['status'] == 'pending'): ?>
                <button id="pay-button" class="btn btn-primary w-100 btn-lg">Bayar Sekarang</button>
                <script src="<?php echo $midtransJs; ?>" data-client-key="<?php echo $clientKey; ?>"></script>
                <script>
                  document.getElementById('pay-button').onclick = function(){
                    window.snap.pay('<?php echo $order['snap_token']; ?>', {
                        onSuccess: function(result){ location.reload(); },
                        onPending: function(result){ location.reload(); },
                        onError: function(result){ location.reload(); }
                    });
                  };
                </script>
            <?php endif; ?>
            
            <div class="mt-4">
                <a href="order.php?u=RJ.NET-SEIPIRING" class="text-decoration-none text-muted small">
                    <i class="fas fa-arrow-left"></i> Kembali ke Menu Utama
                </a>
            </div>

        <?php endif; ?>

    </div>
</div>

<script>
function copyVoucher() {
    var copyText = document.getElementById("voucherCode");
    navigator.clipboard.writeText(copyText.innerText).then(function() {
        alert("Kode Voucher berhasil disalin!");
    });
}
<?php if (isset($order) && $order['status'] == 'pending'): ?>
    setTimeout(function(){ location.reload(); }, 5000); 
<?php endif; ?>
</script>
</body>
</html>