-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Waktu pembuatan: 14 Jan 2026 pada 03.47
-- Versi server: 11.8.3-MariaDB-log
-- Versi PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u424989546_VoucherKu`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `online_orders`
--

CREATE TABLE `online_orders` (
  `id` int(11) NOT NULL,
  `trx_id` varchar(50) NOT NULL COMMENT 'Kode Unik Transaksi untuk User',
  `profile_id` int(11) NOT NULL COMMENT 'ID Paket yang dibeli',
  `router_id` int(11) NOT NULL COMMENT 'ID Router tujuan pembuatan voucher',
  `admin_id` int(11) NOT NULL COMMENT 'ID Pemilik Router/Paket (Multi-Admin)',
  `no_wa` varchar(20) NOT NULL COMMENT 'Nomor WA Pembeli',
  `amount` decimal(10,2) NOT NULL COMMENT 'Nominal Bayar',
  `status` enum('pending','paid','expired','failed') DEFAULT 'pending',
  `snap_token` varchar(255) DEFAULT NULL COMMENT 'Token dari Midtrans',
  `voucher_user` varchar(50) DEFAULT NULL COMMENT 'Diisi setelah status PAID',
  `voucher_pass` varchar(50) DEFAULT NULL COMMENT 'Diisi setelah status PAID',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `profiles`
--

CREATE TABLE `profiles` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `router_id` int(11) NOT NULL,
  `nama_tampil` varchar(50) NOT NULL,
  `nama_mikrotik` varchar(50) NOT NULL,
  `harga` decimal(10,2) NOT NULL,
  `validity` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `routers`
--

CREATE TABLE `routers` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `nama_router` varchar(50) NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  `username_mikrotik` varchar(50) NOT NULL,
  `password_mikrotik` varchar(255) NOT NULL,
  `port_api` int(5) DEFAULT 8728
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `mitra_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `router_id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `kode_voucher` varchar(50) NOT NULL,
  `harga_jual` decimal(10,2) NOT NULL,
  `status` varchar(50) DEFAULT 'belum diaktifkan',
  `is_paid` tinyint(1) DEFAULT 0 COMMENT '0=Belum Setor, 1=Sudah Setor',
  `expire` datetime DEFAULT NULL,
  `waktu_transaksi` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','mitra') NOT NULL DEFAULT 'mitra',
  `parent_id` int(11) DEFAULT NULL,
  `tagihan` decimal(15,2) DEFAULT 0.00,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `prefix` char(2) DEFAULT NULL,
  `no_wa` varchar(20) DEFAULT NULL,
  `api_token` varchar(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `online_orders`
--
ALTER TABLE `online_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `trx_id` (`trx_id`),
  ADD KEY `status` (`status`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indeks untuk tabel `profiles`
--
ALTER TABLE `profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indeks untuk tabel `routers`
--
ALTER TABLE `routers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indeks untuk tabel `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mitra_id` (`mitra_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `waktu_transaksi` (`waktu_transaksi`),
  ADD KEY `status` (`status`),
  ADD KEY `profile_id` (`profile_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `prefix` (`prefix`),
  ADD KEY `parent_id` (`parent_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `online_orders`
--
ALTER TABLE `online_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `profiles`
--
ALTER TABLE `profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `routers`
--
ALTER TABLE `routers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
