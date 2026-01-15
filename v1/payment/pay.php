<?php
// File: v1/payment/pay.php
// VERSI: FULL FIXED LAYOUT (Header & Footer Diam, Tengah Scroll)

include_once '../../config/koneksi.php';

$trx_id = $_GET['trx_id'] ?? '';
$qr_string = $_GET['qr'] ?? '';

if (empty($trx_id) || empty($qr_string)) die("Data pembayaran tidak valid.");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Scan QRIS - <?php echo htmlspecialchars($trx_id); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        /* 1. SETUP DASAR */
        :root {
            --primary-gradient: linear-gradient(135deg, #05a04e 0%, #0099ff 100%);
            --bg-color: #f0f2f5;
        }
        
        body {
            background-color: var(--bg-color);
            font-family: 'Poppins', sans-serif;
            margin: 0; padding: 0;
        }

        /* 2. HEADER FIXED (DIAM DI ATAS) */
        .header-bg {
            position: fixed; /* Kunci posisi */
            top: 0; left: 0; right: 0; /* Tempel ke atas */
            
            background: var(--primary-gradient);
            padding: 30px 20px 40px 20px;
            color: white;
            text-align: center;
            border-bottom-left-radius: 30px;
            border-bottom-right-radius: 30px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            z-index: 1000; /* Pastikan di atas konten */
        }

        /* 3. CONTAINER TENGAH (SCROLLABLE) */
        .main-wrapper {
            max-width: 480px;
            margin: 0 auto;
            padding: 0 20px;
            
            /* PENTING: Padding agar konten tidak tertutup Header/Footer */
            padding-top: 150px; /* Tinggi Header + Spasi */
            padding-bottom: 120px; /* Tinggi Footer + Spasi */
            
            position: relative;
            z-index: 10;
        }

        /* 4. KARTU KONTEN */
        .card-custom {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #fff;
        }

        /* 5. QRIS AREA */
        #qrcode {
            margin: 15px auto;
            display: flex; justify-content: center;
        }
        #qrcode img {
            padding: 5px;
            border: 1px solid #eee;
            border-radius: 5px;
        }

        /* 6. ORDER ID BOX */
        .order-box {
            background: #f0f9ff;
            border: 2px dashed #0099ff;
            border-radius: 10px;
            padding: 10px;
            margin-top: 10px;
            cursor: pointer;
            transition: transform 0.1s;
        }
        .order-box:active { transform: scale(0.98); background: #e0f2fe; }

        /* 7. FOOTER FIXED (DIAM DI BAWAH) */
        .fixed-bottom-bar {
            position: fixed; /* Kunci posisi */
            bottom: 0; left: 0; right: 0; /* Tempel ke bawah */
            
            background: white;
            padding: 20px 20px;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.1);
            z-index: 1000; /* Pastikan di atas konten */
            display: flex; justify-content: center;
            border-top-left-radius: 25px;
            border-top-right-radius: 25px;
        }

        .btn-wrapper { width: 100%; max-width: 480px; }

        .btn-bayar {
            width: 100%;
            background: var(--primary-gradient);
            border: none; color: white;
            font-weight: 700; padding: 15px;
            border-radius: 50px; font-size: 1.1rem;
            box-shadow: 0 4px 15px rgba(0, 153, 255, 0.3);
            text-transform: uppercase; letter-spacing: 1px;
            text-decoration: none; display: block; text-align: center;
        }
        .btn-bayar:active { transform: scale(0.98); opacity: 0.9; }

    </style>
</head>
<body>

    <div class="header-bg">
        <h2 class="fw-bold mb-0">Scan QRIS</h2>
        <p class="small opacity-75 mb-0">Selesaikan Pembayaran</p>
    </div>

    <div class="main-wrapper">
        
        <div class="card-custom">
            <h6 class="fw-bold text-dark mb-1">🆔 ORDER ID</h6>
            <div class="text-muted small mb-2">Simpan ID ini untuk klaim voucher</div>
            
            <div class="order-box" onclick="copyTrxId()">
                <div class="fs-4 fw-bold text-primary" id="trxIdText"><?php echo htmlspecialchars($trx_id); ?></div>
                <div class="small text-muted mt-1"><i class="fas fa-copy"></i> Ketuk Salin</div>
            </div>
            <div class="small text-danger fw-bold mt-2">
                *Kode Voucher TIDAK dikirim ke WA
            </div>
        </div>

        <div class="card-custom">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a2/Logo_QRIS.svg/1200px-Logo_QRIS.svg.png" style="height: 30px; opacity: 0.8;" alt="QRIS">
            
            <div id="qrcode"></div>
            
            <div class="text-muted small fw-bold mt-2">
                Scan via E-Wallet / M-Banking
            </div>
        </div>
        
        <div class="text-center text-muted small pb-2">
            Terima kasih telah berlangganan
        </div>

    </div>

    <div class="fixed-bottom-bar">
        <div class="btn-wrapper">
            <a href="check_status.php?trx_id=<?php echo $trx_id; ?>" class="btn-bayar">
                <i class="fas fa-check-circle me-2"></i> SAYA SUDAH BAYAR
            </a>
        </div>
    </div>

    <script>
        // Generate QR
        var qrString = "<?php echo $qr_string; ?>";
        if(qrString) {
            new QRCode(document.getElementById("qrcode"), {
                text: qrString,
                width: 220,
                height: 220,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.M
            });
        }

        // Copy Fungsi
        function copyTrxId() {
            var text = document.getElementById("trxIdText").innerText;
            navigator.clipboard.writeText(text).then(function() {
                Swal.fire({
                    toast: true, position: 'top', icon: 'success', 
                    title: 'Disalin!', showConfirmButton: false, timer: 1000
                });
            });
        }
    </script>

</body>
</html>