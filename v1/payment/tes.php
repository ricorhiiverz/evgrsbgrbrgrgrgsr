<?php
// File: v1/payment/debug_login.php
// FUNGSI: Mendiagnosa kenapa Login API Gagal padahal Koneksi Sukses

include '../../config/koneksi.php';
include '../../libs/routeros_api.class.php'; // Pastikan path ini benar!

// HARDCODE CREDENTIALS (Sesuai Log Terakhir Anda)
// Kita tes manual tanpa database dulu untuk isolasi masalah
$host = "103.125.174.141"; // IP hasil resolve tadi
$port = 1043;
$user = "APK-VOUCHER";
$pass = "CALONSULTAN!"; // TULIS PASSWORD ASLI ANDA DISINI (Ganti CA*** dengan pass asli)

echo "<h2>🕵️ DEBUGGING API MIKROTIK</h2>";
echo "Target: <b>$host:$port</b><br>";
echo "User: <b>$user</b><br><hr>";

$API = new RouterosAPI();
$API->debug = true; // KITA NYALAKAN DEBUG AGAR KELUAR SEMUA TEXT
$API->timeout = 10;
$API->attempts = 1;

echo "<h3>1. Mencoba Connect...</h3>";
echo "<pre style='background:#eee; padding:10px; border:1px solid #ccc;'>";

if ($API->connect($host, $user, $pass, $port)) {
    echo "</pre>";
    echo "<h3 style='color:green'>🎉 LOGIN SUKSES!</h3>";
    echo "Masalahnya bukan di Network/User, tapi mungkin di cara ambil data database.";
    $API->disconnect();
} else {
    echo "</pre>";
    echo "<h3 style='color:red'>💀 LOGIN GAGAL!</h3>";
    echo "<b>Analisa Penyebab:</b><br>";
    echo "<ul>";
    echo "<li>Jika muncul <i>'Connection timed out'</i>: Firewall/IP salah.</li>";
    echo "<li>Jika muncul text aneh/kosong setelah 'Login': <b>Library routeros_api.class.php Anda KADALUARSA.</b></li>";
    echo "<li>Jika muncul <i>'cannot log in'</i>: Password salah atau User tidak punya akses API.</li>";
    echo "</ul>";
}
?>