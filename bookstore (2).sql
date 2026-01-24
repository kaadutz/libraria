-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 24, 2026 at 12:25 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bookstore`
--

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `author` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `cost_price` decimal(15,2) NOT NULL,
  `sell_price` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `seller_id`, `category_id`, `title`, `author`, `description`, `image`, `stock`, `cost_price`, `sell_price`, `created_at`) VALUES
(2, 2, 2, 'Psychology of Money', 'Morgan Housel', 'Pelajaran abadi mengenai kekayaan, ketamakan, dan kebahagiaan.', 'book_1768550370_6969efe214ac4.jpg', 20, 65000.00, 98000.00, '2025-01-10 02:05:00'),
(5, 3, 3, 'Atomic Habits', NULL, 'Perubahan kecil yang memberikan hasil luar biasa.', 'atomic_habits.jpg', 12, 70000.00, 105000.00, '2025-01-12 04:15:00'),
(7, 2, 1, 'Bumi Manusia', 'Pramoedya Ananta Toer', 'Karya sastra klasik Pramoedya Ananta Toer.', 'book_1768533397_6969ad95daafc.jpg', 8, 60000.00, 110000.00, '2025-01-14 02:00:00'),
(8, 3, 2, 'Rich Dad Poor Dad', NULL, 'Apa yang diajarkan orang kaya pada anak-anak mereka tentang uang.', 'rich_dad.jpg', 25, 55000.00, 88000.00, '2025-01-14 02:30:00'),
(9, 2, 1, 'The Alchemist', 'Paul Coelho', 'The Alchemist is a novel by Brazilian author Paulo Coelho which was first published in 1988. Originally written in Portuguese, it became a widely translated international bestseller.', 'book_1768438223_696839cf73211.jpg', 2, 80000.00, 90000.00, '2026-01-15 00:50:23');

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`id`, `buyer_id`, `book_id`, `qty`) VALUES
(2, 5, 5, 1);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `created_by`, `created_at`) VALUES
(1, 'Fiksi & Novel', 1, '2025-01-01 01:30:00'),
(2, 'Bisnis & Ekonomi', 1, '2025-01-01 01:31:00'),
(3, 'Sains & Teknologi', 1, '2025-01-01 01:32:00'),
(4, 'Sejarah & Budaya', 1, '2025-01-01 01:33:00'),
(5, 'Komik & Manga', 1, '2025-01-01 01:34:00');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message`, `is_read`, `created_at`) VALUES
(1, 4, 2, 'Halo\r\n', 1, '2026-01-16 02:35:01'),
(2, 2, 4, 'Halo ada yang bisa di bantu?\r\n', 1, '2026-01-16 02:37:46'),
(3, 4, 2, 'Mau nanya dong tapi boong', 1, '2026-01-16 02:38:56'),
(4, 4, 2, 'Kentang', 1, '2026-01-16 09:45:08');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `total_price` decimal(15,2) NOT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `status` enum('pending','waiting_approval','approved','shipping','finished','rejected','refund','refunded') DEFAULT 'pending',
  `expedition_name` varchar(50) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `refund_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `invoice_number`, `buyer_id`, `total_price`, `payment_proof`, `status`, `expedition_name`, `tracking_number`, `order_date`, `refund_time`) VALUES
(7, 'INV/20260116/696A00FB7B4D1', 4, 630000.00, '', 'rejected', NULL, NULL, '2026-01-16 09:12:27', NULL),
(8, 'INV/20260116/696A01BF248E6', 4, 90000.00, 'proof_8_1768554992.png', 'rejected', NULL, NULL, '2026-01-16 09:15:43', NULL),
(9, 'INV/20260116/696A024B0720F', 4, 90000.00, 'proof_9_1768555107.jpeg', 'refunded', NULL, NULL, '2026-01-16 09:18:03', '2026-01-16 16:34:36'),
(10, 'INV/20260116/696A02AB673FE', 4, 110000.00, 'proof_10_1768555569.png', 'finished', 'Shopee Express', 'SPX182831923', '2026-01-16 09:19:39', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `book_id` int(11) DEFAULT NULL,
  `seller_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `price_at_transaction` decimal(15,2) NOT NULL,
  `cost_at_transaction` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `book_id`, `seller_id`, `qty`, `price_at_transaction`, `cost_at_transaction`) VALUES
(8, 7, 9, 2, 7, 90000.00, 0.00),
(9, 8, 9, 2, 1, 90000.00, 0.00),
(10, 9, 9, 2, 1, 90000.00, 0.00),
(11, 10, 7, 2, 1, 110000.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `qna`
--

CREATE TABLE `qna` (
  `id` int(11) NOT NULL,
  `target` enum('buyer','seller','all') NOT NULL DEFAULT 'all',
  `question` text NOT NULL,
  `answer` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `qna`
--

INSERT INTO `qna` (`id`, `target`, `question`, `answer`) VALUES
(1, 'buyer', 'Bagaimana cara memesan buku?', 'Pilih buku di halaman Beranda, klik \"Tambah Keranjang\". Buka menu Keranjang, lalu klik \"Checkout\". Lakukan pembayaran dan upload bukti transfer di menu \"Pesanan Saya\".'),
(2, 'buyer', 'Metode pembayaran apa saja yang tersedia?', 'Kami menerima Transfer Bank (BCA, Mandiri, BRI). Nomor rekening tujuan akan muncul setelah Anda menekan tombol Checkout.'),
(3, 'buyer', 'Bagaimana cara melacak pesanan saya?', 'Buka menu \"Pesanan Saya\". Jika status pesanan sudah \"Dikirim\", nomor resi akan muncul. Anda bisa menyalin nomor resi tersebut untuk mengecek di website ekspedisi terkait.'),
(4, 'buyer', 'Apa yang harus dilakukan jika pesanan ditolak?', 'Jika pesanan ditolak (misal stok habis), status akan berubah menjadi \"Refund\". Silakan klik tombol \"Ajukan Refund\" pada pesanan tersebut untuk menghubungi Admin.'),
(5, 'buyer', 'Apakah saya bisa membatalkan pesanan?', 'Pembatalan hanya bisa dilakukan jika status masih \"Pending\" (Belum Bayar). Jika sudah \"Diproses\", pembatalan tergantung persetujuan Penjual.'),
(6, 'seller', 'Bagaimana cara mulai berjualan?', 'Pastikan akun Anda sudah terdaftar sebagai Seller. Masuk ke menu \"Produk Saya\", lalu klik tombol \"Tambah Produk\". Isi detail buku lengkap dengan foto sampul.'),
(7, 'seller', 'Apa yang harus dilakukan jika ada pesanan masuk?', 'Buka menu \"Pesanan Masuk\". Cek bukti pembayaran pembeli. Jika valid, klik \"Terima Pesanan\". Setelah itu, kemas barang dan input Nomor Resi untuk mengubah status menjadi \"Dikirim\".'),
(8, 'seller', 'Kapan dana hasil penjualan akan cair?', 'Dana akan diteruskan ke saldo Anda setelah pembeli mengonfirmasi \"Pesanan Diterima\" atau 2x24 jam setelah status terkirim.'),
(9, 'seller', 'Bagaimana cara menarik dana (Withdraw)?', 'Untuk saat ini penarikan dana dilakukan secara manual. Silakan hubungi Admin melalui menu Chat dan sertakan bukti saldo serta nomor rekening tujuan.'),
(10, 'seller', 'Bagaimana cara menghapus produk?', 'Masuk ke menu \"Produk Saya\", pilih produk yang ingin dihapus, lalu klik ikon tempat sampah (Hapus). Produk yang sedang dalam proses pesanan tidak dapat dihapus.'),
(11, 'all', 'Lupa password akun, bagaimana solusinya?', 'Silakan hubungi Customer Service kami melalui email support@libraria.com untuk bantuan reset kata sandi.'),
(12, 'all', 'Bagaimana cara mengganti foto profil?', 'Masuk ke menu \"Profil\" (klik foto di pojok kanan atas), lalu pilih \"Edit Profil\". Anda dapat mengunggah foto baru di sana.'),
(13, 'all', 'Apakah data saya aman di Libraria?', 'Ya, kami menjaga kerahasiaan data pengguna dan tidak membagikannya ke pihak ketiga tanpa persetujuan Anda.');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `nik` varchar(16) DEFAULT NULL,
  `role` enum('admin','seller','buyer') NOT NULL,
  `address` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires_at` datetime DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `nik`, `role`, `address`, `profile_image`, `reset_token`, `reset_expires_at`, `last_activity`, `created_at`) VALUES
(1, 'admin@gmail.com', '1', 'Super Administrator', NULL, 'admin', 'Kantor Pusat Libraria', 'profile_1_1768436781.png', NULL, NULL, '2026-01-17 10:59:31', '2025-01-01 01:00:00'),
(2, 'rakaanjay73@gmail.com', '1', 'Toko Buku Sejahtera', '3201123456789001', 'seller', 'Jl. Merdeka No. 10, Jakarta', NULL, NULL, NULL, '2026-01-22 17:59:14', '2025-01-02 02:00:00'),
(3, 'seller2@bookstore.com', 'password', 'Pustaka Abadi', '', 'seller', 'Jl. Sudirman No. 45, Bandung', NULL, NULL, NULL, '2026-01-14 15:00:00', '2025-01-03 03:00:00'),
(4, 'buyer@gmail.com', '1', 'Budi Santoso', '3202987654321002', 'buyer', 'Perumahan Griya Asri Blok A1', 'profile_4_1768556821.jpeg', NULL, NULL, '2026-01-22 15:19:05', '2025-01-05 04:00:00'),
(5, 'buyer2@bookstore.com', 'password', 'Siti Aminah', NULL, 'buyer', 'Apartemen Melati Lt. 12', NULL, NULL, NULL, '2026-01-13 20:00:00', '2025-01-06 07:00:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `buyer_id` (`buyer_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `buyer_id` (`buyer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indexes for table `qna`
--
ALTER TABLE `qna`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `unique_nik` (`nik`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `qna`
--
ALTER TABLE `qna`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `books`
--
ALTER TABLE `books`
  ADD CONSTRAINT `books_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `books_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_ibfk_1` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `carts_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
