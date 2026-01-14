<?php
// File: v1/payment/order.php

// Naik 2 tingkat ke config
include_once '../../config/koneksi.php';

// 1. Ambil Identitas Admin dari URL
// PERBAIKAN: Regex ditambahkan titik (.) agar RJ.NET terbaca
$username = isset($_GET['u']) ? preg_replace('/[^a-zA-Z0-9-.]/', '', $_GET['u']) : '';

if (empty($username)) {
    die("Error: Harap sertakan username toko (Contoh: order.php?u=namauser)");
}

// 2. Cari Admin ID
try {
    // FIX: Gunakan 'nama_lengkap' sesuai database Anda
    $stmt = $conn->prepare("SELECT id, username, nama_lengkap as nama FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        die("Error: Toko dengan username '$username' tidak ditemukan.");
    }
} catch (Exception $e) {
    die("Error Database User: " . $e->getMessage());
}

// 3. Ambil Daftar Paket (Profiles)
try {
    // FIX: Gunakan 'nama_tampil' dan 'validity'
    $stmt = $conn->prepare("SELECT id, nama_tampil as nama, harga, validity 
                            FROM profiles 
                            WHERE admin_id = ? AND harga > 0 
                            ORDER BY harga ASC");
    $stmt->execute([$admin['id']]);
    $pakets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error Database Profile: " . $e->getMessage());
}

// Ambil Client Key dari .env
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
    <title>Beli Voucher - <?php echo htmlspecialchars($admin['nama']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card-paket { 
            cursor: pointer; 
            transition: 0.3s; 
            border: 2px solid transparent;
        }
        .card-paket:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .card-paket.selected { border-color: #0d6efd; background-color: #f0f8ff; }
        .price-tag { font-size: 1.2rem; font-weight: bold; color: #0d6efd; }
    </style>
</head>
<body>

<div class="container py-5" style="max-width: 600px;">
    
    <div class="text-center mb-5">
        <h2><?php echo htmlspecialchars($admin['nama']); ?></h2>
        <p class="text-muted">Pilih paket internet hotspot di bawah ini</p>
    </div>

    <form id="orderForm">
        <input type="hidden" id="profile_id" name="profile_id">
        
        <div class="mb-4">
            <label class="form-label fw-bold">1. Pilih Paket</label>
            <div class="row g-3">
                <?php if(empty($pakets)): ?>
                    <div class="text-center text-muted">Belum ada paket yang tersedia.</div>
                <?php else: ?>
                    <?php foreach ($pakets as $p): ?>
                    <div class="col-6">
                        <div class="card p-3 card-paket h-100" onclick="selectPaket(this, <?php echo $p['id']; ?>)">
                            <div class="fw-bold"><?php echo htmlspecialchars($p['nama']); ?></div>
                            <div class="small text-muted mb-2">
                                Masa Aktif: <?php echo htmlspecialchars($p['validity']); ?>
                            </div>
                            <div class="price-tag">Rp <?php echo number_format($p['harga'], 0, ',', '.'); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div id="paketError" class="text-danger small mt-2 d-none">Silakan pilih paket terlebih dahulu.</div>
        </div>

        <div class="mb-4">
            <label for="no_wa" class="form-label fw-bold">2. Nomor WhatsApp</label>
            <input type="number" class="form-control form-control-lg" id="no_wa" name="no_wa" placeholder="08xxxxxxxxxx" required>
            <div class="form-text">Kode voucher akan dikirim / ditampilkan setelah bayar.</div>
        </div>

        <button type="button" id="btnPay" class="btn btn-primary w-100 btn-lg">Bayar Sekarang</button>
    </form>
    
    <div class="text-center mt-5 text-muted small">
        &copy; <?php echo date('Y'); ?> VoucherKu System
    </div>
</div>

<script src="<?php echo $midtransJs; ?>" data-client-key="<?php echo $clientKey; ?>"></script>
<script>
    function selectPaket(el, id) {
        document.querySelectorAll('.card-paket').forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');
        document.getElementById('profile_id').value = id;
        document.getElementById('paketError').classList.add('d-none');
    }

    const btnPay = document.getElementById('btnPay');
    
    btnPay.addEventListener('click', async function() {
        const profileId = document.getElementById('profile_id').value;
        const noWa = document.getElementById('no_wa').value;

        if (!profileId) {
            document.getElementById('paketError').classList.remove('d-none');
            return;
        }
        if (!noWa || noWa.length < 9) {
            alert("Mohon isi nomor WhatsApp dengan benar.");
            return;
        }

        btnPay.disabled = true;
        btnPay.innerText = "Memproses...";

        try {
            // Fetch ke create_order.php (dalam folder yang sama)
            const response = await fetch('create_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    profile_id: profileId,
                    no_wa: noWa
                })
            });

            // Handle jika response bukan JSON (misal error PHP)
            const textResult = await response.text(); 
            let result;
            try {
                result = JSON.parse(textResult);
            } catch (e) {
                console.error("Server Error:", textResult);
                alert("Terjadi kesalahan di server. Cek console.");
                btnPay.disabled = false;
                return;
            }

            if (result.status && result.data.snap_token) {
                window.snap.pay(result.data.snap_token, {
                    onSuccess: function(res){
                        alert("Pembayaran Berhasil! Voucher akan segera diproses.");
                        console.log(res);
                    },
                    onPending: function(res){ alert("Menunggu Pembayaran..."); },
                    onError: function(res){ 
                        alert("Pembayaran Gagal!"); 
                        btnPay.disabled = false;
                        btnPay.innerText = "Bayar Sekarang";
                    },
                    onClose: function(){ 
                        alert('Pembayaran belum selesai.'); 
                        btnPay.disabled = false;
                        btnPay.innerText = "Bayar Sekarang";
                    }
                });
            } else {
                alert(result.message || "Gagal membuat order.");
                btnPay.disabled = false;
                btnPay.innerText = "Bayar Sekarang";
            }

        } catch (error) {
            console.error(error);
            alert("Koneksi Gagal.");
            btnPay.disabled = false;
            btnPay.innerText = "Bayar Sekarang";
        }
    });
</script>
</body>
</html>