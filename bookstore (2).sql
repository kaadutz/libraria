-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 11, 2026 at 07:45 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

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
(5, 3, 5, 'Ghibliverse: Studio Ghibli Beyond the Films (Ghibliotheque Guides)', 'Jake Cunningham', 'Hailed as perhaps the greatest animation studio in the world, Studio Ghibli\'s influence extends far further than the cinema screen. Ghibliverse plots a course through the universe outside the films, showcasing a wonderful web of inspirations and influences for Ghibli fans old and new to enjoy.', 'book_1769509854_697893de7fe5f.jpg', 10, 70000.00, 105000.00, '2025-01-12 04:15:00'),
(7, 2, 1, 'Bumi Manusia', 'Pramoedya Ananta Toer', 'Karya sastra klasik Pramoedya Ananta Toer.', 'book_1768533397_6969ad95daafc.jpg', 7, 60000.00, 110000.00, '2025-01-14 02:00:00'),
(8, 3, 5, 'Studio Ghibli: The Films of Hayao Miyazaki and Isao Takahata', 'Collin Odell', 'The animations of Japanâ€™s Studio Ghibli are amongst the highest regarded in the movie industry. Their delightful films rank alongside the most popular non-English language films ever made, with each new eagerly-anticipated release a guaranteed box-office smash. Yet this highly profitable studio has remained fiercely independent, producing a stream of imaginative and individual animations. The studioâ€™s founders, long-time animators Isao Takahata and Hayao Miyazaki, have created timeless masterpieces. Although their films are distinctly Japanese their themes are universalâ€”humanity, community, and a love for the environment. No other film studio, animation or otherwise, comes close to matching Ghibli for pure cinematic experience. All their major works are examined here, as well the early output of Hayao Miyazaki and Isao Takahata, exploring the cultural and thematic threads that bind these films together.', 'book_1769509686_69789336c2afc.jpg', 25, 55000.00, 88000.00, '2025-01-14 02:30:00'),
(9, 2, 1, 'The Alchemist', 'Paul Coelho', 'The Alchemist is a novel by Brazilian author Paulo Coelho which was first published in 1988. Originally written in Portuguese, it became a widely translated international bestseller.', 'book_1768438223_696839cf73211.jpg', 1, 80000.00, 90000.00, '2026-01-15 00:50:23'),
(10, 3, 1, 'Little Women', 'Louisa May Alcott', 'As a New England mother struggles to support her family in the wake of her husband’s service in the Civil War, her four daughters struggle, too—caught between childhood dreams and the realities of burgeoning adulthood. For Meg, Jo, Beth, and Amy March, raised in integrity and virtue, negotiating the right path in life means making choices that will either narrow or expand their destinies.', 'book_1770790023_698c1c876c650.jpg', 5, 80000.00, 150000.00, '2026-02-11 06:07:03');

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
(5, 'Komik & Manga', 1, '2025-01-01 01:34:00'),
(6, 'Science Fiction', 1, '2026-01-30 06:15:03');

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
(4, 4, 2, 'Kentang', 1, '2026-01-16 09:45:08'),
(5, 4, 2, 'Halo kak, saya tertarik dengan buku *Bumi Manusia*. Apakah stok masih tersedia?', 1, '2026-01-30 02:57:35'),
(6, 2, 3, 'Halo mas', 1, '2026-02-01 06:04:23'),
(7, 2, 4, 'Ya masih', 1, '2026-02-09 15:42:49'),
(8, 3, 2, 'y', 1, '2026-02-11 01:36:21'),
(9, 4, 3, 'Halo kak, saya tertarik dengan buku *Studio Ghibli: The Films of Hayao Miyazaki and Isao Takahata*. Apakah stok masih tersedia?', 1, '2026-02-11 01:52:23'),
(10, 3, 4, 'y', 1, '2026-02-11 01:52:34'),
(11, 4, 3, 'Halo kak, kenapa pesanan *INV/20260211/698BE589A58A6* saya ditolak? Mohon infonya. ', 0, '2026-02-11 05:37:49');

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
  `reject_reason` text DEFAULT NULL,
  `expedition_name` varchar(50) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `refund_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `invoice_number`, `buyer_id`, `total_price`, `payment_proof`, `status`, `reject_reason`, `expedition_name`, `tracking_number`, `order_date`, `refund_time`) VALUES
(7, 'INV/20260116/696A00FB7B4D1', 4, 630000.00, '', 'rejected', NULL, NULL, NULL, '2026-01-16 09:12:27', NULL),
(8, 'INV/20260116/696A01BF248E6', 4, 90000.00, 'proof_8_1768554992.png', 'rejected', NULL, NULL, NULL, '2026-01-16 09:15:43', NULL),
(9, 'INV/20260116/696A024B0720F', 4, 90000.00, 'proof_9_1768555107.jpeg', 'refunded', NULL, NULL, NULL, '2026-01-16 09:18:03', '2026-01-16 16:34:36'),
(10, 'INV/20260116/696A02AB673FE', 4, 110000.00, 'proof_10_1768555569.png', 'finished', NULL, 'Shopee Express', 'SPX182831923', '2026-01-16 09:19:39', NULL),
(11, 'INV/20260127/6978135745A42', 4, 90000.00, 'proof_4_2_1769476951.png', 'finished', NULL, 'JNE', 'JNE14526371', '2026-01-27 01:22:31', NULL),
(12, 'INV/20260127/697896EFB9950', 4, 110000.00, 'proof_4_2_1769510639.png', 'shipping', NULL, 'Shopee Express', 'SPX434534', '2026-01-27 10:43:59', NULL),
(13, 'INV/20260127/697896EFBA7F4', 4, 88000.00, 'proof_4_3_1769510639.png', 'finished', NULL, 'Shopee Express', 'SPX434534', '2026-01-27 10:43:59', NULL),
(15, 'INV/20260130/697C4EB07163F', 4, 88000.00, 'proof_4_3_1769754288.png', 'finished', NULL, 'JNE', 'JNE14526371', '2026-01-30 06:24:48', NULL),
(17, 'INV/20260209/6989CD5677078', 4, 90000.00, 'proof_4_2_1770638678.png', 'refunded', NULL, NULL, NULL, '2026-02-09 12:04:38', '2026-02-09 21:40:34'),
(18, 'INV/20260209/698A02202423F', 4, 110000.00, 'proof_4_2_1770652192.png', 'refunded', NULL, NULL, NULL, '2026-02-09 15:49:52', '2026-02-09 22:50:13'),
(19, 'INV/20260211/698BE2FA8F572', 4, 110000.00, 'proof_4_2_1770775290.jpg', 'rejected', 'Bukti Pembayaran Tidak Valid', NULL, NULL, '2026-02-11 02:01:30', NULL),
(20, 'INV/20260211/698BE30FDE975', 4, 88000.00, 'proof_4_3_1770775311.jpg', 'finished', NULL, 'JNE', 'JNE1233134', '2026-02-11 02:01:51', NULL),
(21, 'INV/20260211/698BE3983FC76', 4, 88000.00, 'proof_4_3_1770775448.jpg', 'refunded', NULL, NULL, NULL, '2026-02-11 02:04:08', '2026-02-11 09:04:38'),
(23, 'INV/20260211/698BE4399D0F0', 4, 88000.00, 'proof_4_3_1770775609.jpg', 'shipping', NULL, 'Shopee Express', 'SPX9471203', '2026-02-11 02:06:49', NULL),
(24, 'INV/20260211/698BE589A58A6', 4, 176000.00, 'proof_4_3_1770775945.jpg', 'refund', 'TIDAK VALID', NULL, NULL, '2026-02-11 02:12:25', '2026-02-11 12:54:46');

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
(11, 10, 7, 2, 1, 110000.00, 0.00),
(12, 11, 9, 2, 1, 90000.00, 0.00),
(13, 12, 7, 2, 1, 110000.00, 0.00),
(14, 13, 8, 3, 1, 88000.00, 0.00),
(16, 15, 8, 3, 1, 88000.00, 0.00),
(18, 17, 9, 2, 1, 90000.00, 0.00),
(19, 18, 7, 2, 1, 110000.00, 0.00),
(20, 19, 7, 2, 1, 110000.00, 0.00),
(21, 20, 8, 3, 1, 88000.00, 0.00),
(22, 21, 8, 3, 1, 88000.00, 0.00),
(24, 23, 8, 3, 1, 88000.00, 0.00),
(25, 24, 8, 3, 2, 88000.00, 0.00);

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
  `bank_info` varchar(100) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
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

INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `nik`, `bank_info`, `bank_account`, `role`, `address`, `profile_image`, `reset_token`, `reset_expires_at`, `last_activity`, `created_at`) VALUES
(1, 'admin@gmail.com', '1', 'Super Administrator', NULL, NULL, NULL, 'admin', 'Kantor Pusat Libraria', 'profile_1_1768436781.png', NULL, NULL, '2026-02-08 13:05:59', '2025-01-01 01:00:00'),
(2, 'rakaanjay73@gmail.com', '1', 'Toko Buku Sejahtera', '3201123456789001', '(Gopay) Raka Anugrah Satya', '081807852840', 'seller', 'Jl. Merdeka No. 10, Jakarta', NULL, NULL, NULL, '2026-02-11 12:23:51', '2025-01-02 02:00:00'),
(3, 'seller@gmail.com', '1', 'Pustaka Abadi', '', '(Gopay) Raditya Nugroho', '081807852450', 'seller', 'Jl. Sudirman No. 45, Bandung', NULL, NULL, NULL, '2026-02-11 13:08:07', '2025-01-03 03:00:00'),
(4, 'buyer@gmail.com', '1', 'Budi Santoso', '3202987654321002', NULL, NULL, 'buyer', 'Perumahan Griya Asri Blok A1', 'profile_4_1768556821.jpeg', NULL, NULL, '2026-02-11 07:08:40', '2025-01-05 04:00:00'),
(5, 'buyer2@bookstore.com', 'password', 'Siti Aminah', NULL, NULL, NULL, 'buyer', 'Apartemen Melati Lt. 12', NULL, NULL, NULL, '2026-01-13 20:00:00', '2025-01-06 07:00:00'),
(6, 'rakaanugrah2012@gmail.com', '1', 'Raka Anugrah Satya', '1234567891011', NULL, NULL, 'buyer', 'Priuk', NULL, NULL, NULL, NULL, '2026-01-30 06:10:15');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `qna`
--
ALTER TABLE `qna`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
