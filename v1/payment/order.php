<?php
// File: v1/payment/order.php
// VERSI: USERNAME ONLY (Header & Footer) + FITUR ASLI UTUH

include_once '../../config/koneksi.php';

// 1. Ambil Username
$username = isset($_GET['u']) ? preg_replace('/[^a-zA-Z0-9-.]/', '', $_GET['u']) : '';
if (empty($username)) die("<center style='margin-top:50px; font-family:sans-serif;'><h3>Toko Tidak Ditemukan</h3></center>");

// 2. Ambil Data Admin (TAMBAH SELECT USERNAME)
try {
    // Ambil 'username' dan 'nama_lengkap' (untuk fallback jika perlu)
    $stmt = $conn->prepare("SELECT id, username, nama_lengkap FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin) die("<center style='margin-top:50px; font-family:sans-serif;'><h3>User Tidak Ditemukan</h3></center>");
} catch (Exception $e) { die("Database Error"); }

// 3. Ambil Paket (Filter Harga >= 1000)
$stmt = $conn->prepare("SELECT id, nama_tampil, harga, validity FROM profiles WHERE admin_id = ? AND harga >= 5000 ORDER BY harga ASC");
$stmt->execute([$admin['id']]);
$pakets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>@<?php echo htmlspecialchars($admin['username']); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css" rel="stylesheet">

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #05a04e 0%, #0099ff 100%);
            --app-bg: #ffffff;
            --body-bg: #ffffff;
        }
        
        html, body { 
            height: 100%; margin: 0; padding: 0; overflow: hidden; 
            font-family: 'Poppins', sans-serif; background-color: var(--body-bg); 
        }

        .app-container {
            width: 100%; height: 100%; display: flex; flex-direction: column; 
            position: relative; background-color: var(--app-bg); max-width: 100%;
        }

        /* HEADER */
        .header-section {
            flex: 0 0 auto; background: var(--primary-gradient); color: white;
            padding: 30px 20px 45px 20px; text-align: center;
            border-bottom-left-radius: 30px; border-bottom-right-radius: 30px;
            z-index: 20; box-shadow: 0 4px 15px rgba(0, 153, 255, 0.15);
        }
        /* Style Judul */
        .header-section .title { 
            font-weight: 700; font-size: 1.5rem; margin-bottom: 2px; 
            text-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            letter-spacing: 0.5px;
        }
        .header-section .subtitle { font-size: 0.9rem; opacity: 0.95; font-weight: 300; }

        /* CONTENT */
        .content-container {
            flex: 1 1 auto; overflow-y: auto; padding: 20px;
            margin-top: -30px; padding-top: 35px; -webkit-overflow-scrolling: touch; z-index: 10;
        }
        .content-container::-webkit-scrollbar { width: 0px; background: transparent; }
        .content-wrapper { max-width: 600px; margin: 0 auto; }

        /* ITEMS */
        .paket-item {
            background: white; border-radius: 15px; padding: 15px 20px; margin-bottom: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05); cursor: pointer;
            transition: transform 0.2s; border: 1px solid #f0f0f0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .paket-item:hover { transform: translateY(-2px); border-color: #0099ff; }
        .paket-info { flex: 1; }
        .paket-name { font-weight: 700; font-size: 1rem; color: #333; margin-bottom: 4px; }
        .paket-desc { font-size: 0.8rem; color: #666; display: flex; align-items: center; gap: 5px; }
        .paket-price { font-weight: 700; color: #0099ff; font-size: 1rem; background: #f0f9ff; padding: 6px 12px; border-radius: 8px; }
        .badge-terlaris { font-size: 0.65rem; background: #ffc107; color: #000; padding: 2px 6px; border-radius: 4px; font-weight: bold; margin-right: 6px; }

        /* FOOTER */
        .footer-check-status {
            flex: 0 0 auto; background: var(--primary-gradient); color: white;
            padding: 25px 20px 20px 20px; text-align: center; z-index: 20;
            border-top-left-radius: 30px; border-top-right-radius: 30px;
            box-shadow: 0 -4px 15px rgba(0, 153, 255, 0.15); margin-top: -1px;
        }
        .footer-wrapper { max-width: 600px; margin: 0 auto; }
        .input-cek-status {
            border: none; border-radius: 50px; padding: 12px 20px; font-size: 0.9rem;
            text-align: center; width: 100%; margin-bottom: 10px; background: #ffffff; color: #333;
        }
        .input-cek-status:focus { outline: none; box-shadow: 0 0 0 4px rgba(255,255,255,0.25); }
        .copyright-text { font-size: 0.75rem; color: rgba(255,255,255,0.9); margin-top: 5px; display: block; font-weight: 300; }

        /* FORM INPUT */
        #customer-data-form { display: none; margin-top: 10px; padding-bottom: 30px; }
        .form-info-box { background: #eafff2; border: 1px dashed #05a04e; padding: 20px; border-radius: 15px; margin-bottom: 25px; }

        /* SweetAlert Custom Font */
        .swal2-popup { font-family: 'Poppins', sans-serif; border-radius: 20px; }
        .swal2-title { font-size: 1.2rem !important; }
        .swal2-html-container { font-size: 0.9rem !important; }
    </style>
</head>
<body>

<div class="app-container">

    <div class="header-section">
        <h1 class="title"><?php echo htmlspecialchars($admin['username']); ?></h1>
        <p class="subtitle">Internet Unlimited Tanpa Kuota</p>
    </div>

    <div class="content-container">
        <div class="content-wrapper">
            <div style="height: 10px;"></div>

            <div id="paket-selection">
                <?php if(empty($pakets)): ?>
                    <div class="text-center py-5 text-muted small">Tidak ada paket tersedia.<br>(Min. Rp 5.000)</div>
                <?php else: ?>
                    <?php foreach($pakets as $p): ?>
                    <div class="paket-item buy-button" 
                        data-id="<?php echo $p['id']; ?>" 
                        data-nama="<?php echo htmlspecialchars($p['nama_tampil']); ?>" 
                        data-harga="<?php echo $p['harga']; ?>">
                        <div class="paket-info">
                            <div class="paket-name">
                                <?php if($p['validity'] == '30d') echo '<span class="badge-terlaris">HOT</span>'; ?>
                                <?php echo htmlspecialchars($p['nama_tampil']); ?>
                            </div>
                            <div class="paket-desc">⏳ Masa Aktif: <?php echo htmlspecialchars($p['validity']); ?></div>
                        </div>
                        <div class="paket-price">💵 Rp <?php echo number_format($p['harga'], 0, ',', '.'); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div style="height: 10px;"></div>
            </div>

            <div id="customer-data-form">
                <div class="form-info-box text-center">
                    <h6 class="fw-bold mb-2 text-success" id="disp-nama-paket">Nama Paket</h6>
                    <div class="text-dark fs-4 fw-bold" id="disp-harga-paket">Rp 0</div>
                </div>

                <input type="hidden" id="selected-id">
                
                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted ps-1">NAMA LENGKAP</label>
                    <input type="text" id="cust-name" class="form-control form-control-lg shadow-sm border-0" style="background:#f9f9f9;" placeholder="Contoh: Budi" required>
                </div>
                
                <div class="mb-5">
                    <label class="form-label small fw-bold text-muted ps-1">NOMOR WHATSAPP</label>
                    <div class="input-group">
                        <span class="input-group-text border-0 text-success" style="background:#f9f9f9;"><i class="fab fa-whatsapp fa-lg"></i></span>
                        <input type="tel" id="cust-wa" class="form-control form-control-lg shadow-sm border-0" style="background:#f9f9f9;" placeholder="08xxxxxxxxxx" inputmode="numeric" required>
                    </div>
                    <div class="form-text small text-end mt-1">Order ID muncul setelah konfirmasi.</div>
                </div>

                <div class="d-grid gap-3">
                    <button type="button" id="btn-pay" class="btn btn-success fw-bold shadow py-3 rounded-pill" style="background: var(--primary-gradient); border: none; font-size: 1.1rem;">
                        💵 BAYAR SEKARANG
                    </button>
                    <button type="button" id="btn-back" class="btn btn-light text-muted py-2 rounded-pill">
                        ❌ Batal / Pilih Paket Lain
                    </button>
                </div>
                <div style="height: 30px;"></div>
            </div>
            
        </div>
    </div>

    <div class="footer-check-status" id="main-footer">
        <div class="footer-wrapper">
            Sudah bayar? Cek Order ID Disini yah 👇
            <form action="check_status.php" method="GET">
                <div class="position-relative">
                    <input type="text" name="trx_id" class="input-cek-status" placeholder="Cek Status (Tempel Order ID)" required>
                    <button type="submit" class="btn btn-sm position-absolute end-0 top-0 m-1 rounded-circle d-flex align-items-center justify-content-center" style="height: 38px; width: 38px; color: #05a04e; background: transparent;">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
            <span class="copyright-text">
                &copy; 2026 <?php echo htmlspecialchars($admin['username']); ?> - VoucherKu System
            </span>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // UI Elements
    const viewPaket = document.getElementById('paket-selection');
    const viewForm = document.getElementById('customer-data-form');
    const footer = document.getElementById('main-footer');
    
    const inputId = document.getElementById('selected-id');
    const inputName = document.getElementById('cust-name');
    const inputWa = document.getElementById('cust-wa');
    const dispNama = document.getElementById('disp-nama-paket');
    const dispHarga = document.getElementById('disp-harga-paket');
    const contentContainer = document.querySelector('.content-container');

    // 1. Pilih Paket
    document.querySelectorAll('.buy-button').forEach(item => {
        item.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nama = this.getAttribute('data-nama');
            const harga = this.getAttribute('data-harga');

            inputId.value = id;
            dispNama.innerText = nama;
            dispHarga.innerText = 'Rp ' + parseInt(harga).toLocaleString('id-ID');

            viewPaket.style.display = 'none';
            footer.style.display = 'none'; 
            viewForm.style.display = 'block';
            contentContainer.scrollTop = 0; 
            inputName.focus();
        });
    });

    // 2. Kembali
    document.getElementById('btn-back').addEventListener('click', function() {
        viewForm.style.display = 'none';
        viewPaket.style.display = 'block';
        footer.style.display = 'block';
    });

    // 3. Tombol Bayar (Dengan SweetAlert)
    document.getElementById('btn-pay').addEventListener('click', async function() {
        const btn = this;
        const id = inputId.value;
        const name = inputName.value;
        const wa = inputWa.value;

        if(!name || wa.length < 9) {
            Swal.fire({
                icon: 'error',
                title: 'Data Belum Lengkap',
                text: 'Harap isi Nama dan Nomor WhatsApp dengan benar.',
                confirmButtonColor: '#05a04e'
            });
            return;
        }

        // Tampilkan SweetAlert Warning
        const result = await Swal.fire({
            title: '⚠️ PENTING! ⚠️',
            html: `
                <div class="text-start small">
                    <p class="mb-2"><b>Mohon diperhatikan sebelum membayar:</b></p>
                    <ul style="padding-left: 20px;">
                        <li>Kode Voucher <b>TIDAK DIKIRIM</b> via WhatsApp.</li>
                        <li>Silakan <b>Salin Order ID</b> di halaman selanjutnya untuk mengecek kode voucher/status transaksi.</li>
                    </ul>
                    <hr>
                    <p class="text-muted" style="font-size:0.75rem;">
                        <i>Dengan menekan tombol Ya Saya Paham, maka saya telah memahami dan menerima resiko yang ada jika saya tidak melakukan sesuai petunjuk yang ada.</i>
                    </p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'YA, SAYA PAHAM',
            cancelButtonText: 'BATAL',
            confirmButtonColor: '#05a04e',
            cancelButtonColor: '#d33',
            reverseButtons: true
        });

        if (!result.isConfirmed) return;

        // Proses Pembayaran
        btn.disabled = true;
        btn.innerText = "Membuat Pesanan...";

        try {
            const req = await fetch('create_order.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ profile_id: id, no_wa: wa })
            });
            const text = await req.text();
            let json;
            try { json = JSON.parse(text); } catch(e) { throw new Error(text); }

            if (json.status && json.data.qr_string) {
                // REDIRECT KE PAY.PHP BAWA QR STRING
                let targetUrl = `pay.php?trx_id=${json.data.trx_id}&qr=${encodeURIComponent(json.data.qr_string)}`;
                window.location.href = targetUrl;
            } else {
                Swal.fire('Gagal', json.message || "Gagal membuat QRIS", 'error');
                btn.disabled = false;
                btn.innerText = "BAYAR SEKARANG";
            }
        } catch (err) {
            console.error(err);
            Swal.fire('Error', "Gagal koneksi ke server", 'error');
            btn.disabled = false;
            btn.innerText = "BAYAR SEKARANG";
        }
    });

    inputWa.addEventListener('input', function() { this.value = this.value.replace(/[^0-9]/g, ''); });
</script>

</body>
</html>