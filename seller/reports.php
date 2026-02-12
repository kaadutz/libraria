<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');
include '../config/db.php';

// Cek Keamanan
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller') {
    header("Location: ../auth/login.php");
    exit;
}

$seller_id = $_SESSION['user_id'];
$seller_name = $_SESSION['full_name'];

// --- 1. LOGIKA FILTER TANGGAL ---
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// --- 2. QUERY LAPORAN ---
$query_report = "
    SELECT
        o.invoice_number,
        o.order_date,
        u.full_name AS buyer_name,
        b.title AS book_title,
        oi.qty,
        oi.price_at_transaction,
        (oi.qty * oi.price_at_transaction) AS subtotal
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN books b ON oi.book_id = b.id
    JOIN users u ON o.buyer_id = u.id
    WHERE oi.seller_id = '$seller_id'
    AND o.status = 'finished'
    AND DATE(o.order_date) BETWEEN '$start_date' AND '$end_date'
    ORDER BY o.order_date DESC
";

$reports = mysqli_query($conn, $query_report);

// --- 3. PENGOLAHAN DATA UNTUK STATISTIK & GRAFIK ---
$total_revenue = 0;
$total_sold_items = 0;
$data_rows = [];

// Array untuk Grafik
$daily_revenue = []; // Untuk Line Chart
$book_sales = [];    // Untuk Pie Chart

while ($row = mysqli_fetch_assoc($reports)) {
    $total_revenue += $row['subtotal'];
    $total_sold_items += $row['qty'];
    $data_rows[] = $row;

    // Data Grafik Pendapatan Harian
    $date_key = date('Y-m-d', strtotime($row['order_date']));
    if (!isset($daily_revenue[$date_key])) $daily_revenue[$date_key] = 0;
    $daily_revenue[$date_key] += $row['subtotal'];

    // Data Grafik Buku Terlaris
    $book_key = $row['book_title'];
    if (!isset($book_sales[$book_key])) $book_sales[$book_key] = 0;
    $book_sales[$book_key] += $row['qty'];
}

$total_transactions = count($data_rows);

// Urutkan Tanggal (Ascending) untuk Grafik Garis
ksort($daily_revenue);

// Urutkan Buku Terlaris (Descending) dan Ambil Top 5
arsort($book_sales);
$book_sales_top5 = array_slice($book_sales, 0, 5);

// Siapkan JSON untuk JavaScript Chart
$json_dates = json_encode(array_keys($daily_revenue));
$json_revenues = json_encode(array_values($daily_revenue));
$json_books = json_encode(array_keys($book_sales_top5));
$json_qtys = json_encode(array_values($book_sales_top5));

// --- 4. DATA NOTIFIKASI ---
$query_orders = mysqli_query($conn, "SELECT COUNT(DISTINCT o.id) as total FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE oi.seller_id = '$seller_id' AND (o.status = 'pending' OR o.status = 'waiting_approval')");
$total_new_orders = mysqli_fetch_assoc($query_orders)['total'];
$query_unread = mysqli_query($conn, "SELECT COUNT(*) as total FROM messages WHERE receiver_id = '$seller_id' AND is_read = 0");
$total_unread_chat = mysqli_fetch_assoc($query_unread)['total'];
$total_notif = $total_new_orders + $total_unread_chat;
?>

<!DOCTYPE html>
<html lang="id">
<head>
<title>Laporan Penjualan - Libraria Seller</title>

    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>

    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=DM+Serif+Display&family=Inter:wght@300;400;500;600;700&family=Material+Icons+Outlined&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet"/>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
    <script>
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              primary: "#3a5020",
              "primary-light": "#537330",
              "chocolate": "#633d0c",
              "chocolate-light": "#8a5a1b",
              "tan": "#b08144",
              "sand": "#e6e2dd",
              "sage": "#d1d6a7",
              "sage-dark": "#aeb586",
              "cream": "#fefbe9",
              "background-light": "#fefbe9",
              "background-dark": "#1a1c18",
            },
            fontFamily: {
              display: ["DM Serif Display", "serif"],
              sans: ["Inter", "sans-serif"],
              logo: ["Cinzel", "serif"],
            },
            boxShadow: {
                'card': '0 20px 40px -5px rgba(58, 80, 32, 0.08)',
                'glow': '0 0 20px rgba(176, 129, 68, 0.4)',
                'paper': '2px 4px 12px rgba(99, 61, 12, 0.08)',
                'book-3d': '5px 5px 15px rgba(0,0,0,0.2), 10px 10px 25px rgba(0,0,0,0.1)',
            }
          },
        },
      };
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'DM Serif Display', serif; }
        .material-icons-outlined, .material-symbols-outlined { vertical-align: middle; }
    </style>

</head>
<body class="bg-background-light dark:bg-background-dark text-stone-800 dark:text-stone-200 transition-colors duration-500 antialiased selection:bg-tan selection:text-white overflow-x-hidden">

<div class="flex min-h-screen">



    <aside id="sidebar" class="w-64 bg-cream dark:bg-stone-900 border-r border-tan/20 dark:border-stone-800 flex flex-col fixed h-full z-30 overflow-hidden shadow-lg lg:shadow-none transition-colors duration-300">

        <div id="sidebar-header" class="h-28 flex items-center border-b border-tan/20 dark:border-stone-800 shrink-0 px-6">
            <img id="sidebar-logo" src="../assets/images/logo.png" alt="Libraria Logo" class="h-12 w-auto object-contain flex-shrink-0">
            <div class="sidebar-text-wrapper flex flex-col justify-center ml-3">
                <h1 class="text-xl font-bold text-primary dark:text-sage tracking-tight font-logo leading-none">LIBRARIA</h1>
                <p class="text-[10px] font-bold tracking-[0.2em] text-tan mt-1 uppercase">Seller Panel</p>
            </div>
        </div>

        <nav class="flex-1 px-4 space-y-2 mt-6 overflow-y-auto overflow-x-hidden">
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">dashboard</span>
                <span class="menu-text whitespace-nowrap">Dashboard</span>
            </a>

            <a href="categories.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">category</span>
                <span class="menu-text whitespace-nowrap">Kategori</span>
            </a>

            <a href="products.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">inventory_2</span>
                <span class="menu-text whitespace-nowrap">Produk Saya</span>
            </a>

            <a href="orders.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">shopping_cart_checkout</span>
                <span class="menu-text whitespace-nowrap">Pesanan Masuk</span>
            </a>

            <a href="reports.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">bar_chart</span>
                <span class="menu-text whitespace-nowrap">Laporan</span>
            </a>

            <a href="chat.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">chat_bubble</span>
                <span class="menu-text whitespace-nowrap">Chat</span>
            </a>

            <a href="sellers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">storefront</span>
                <span class="menu-text whitespace-nowrap">Daftar Penjual</span>
            </a>

            <a href="help.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">help</span>
                <span class="menu-text whitespace-nowrap">Bantuan</span>
            </a>
        </nav>

        <div class="p-4 border-t border-tan/20 dark:border-stone-800">
            <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-3 text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-xl transition-colors group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">logout</span>
                <span class="font-medium menu-text whitespace-nowrap">Sign Out</span>
            </a>
        </div>
    </aside>



    <main id="main-content" class="flex-1 ml-64 p-4 lg:p-8 transition-all duration-300">

        <header class="flex justify-between items-center mb-8 bg-white/50 backdrop-blur-sm p-4 rounded-3xl border border-tan/20 dark:border-stone-800 sticky top-4 z-20 shadow-sm" data-aos="fade-down">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl hover:bg-sage text-primary dark:text-sage transition-colors focus:outline-none">
                    <span class="material-symbols-outlined">menu_open</span>
                </button>
                <div><h2 class="text-xl lg:text-2xl title-font text-stone-800 dark:text-stone-200 hidden md:block">Laporan & Statistik</h2></div>
            </div>

            <div class="flex items-center gap-4 relative">

            <div class="flex items-center gap-4 relative">
                <!-- DARK MODE TOGGLE -->
                <button onclick="toggleDarkMode()" class="w-10 h-10 flex items-center justify-center rounded-full text-stone-500 dark:text-stone-400 hover:bg-primary hover:text-white dark:hover:bg-stone-800 transition-all duration-300">
                    <span class="material-icons-outlined text-xl">dark_mode</span>
                </button>

<button onclick="toggleDropdown('notificationDropdown')" class="w-10 h-10 rounded-full bg-white border border-tan/20 dark:border-stone-800 flex items-center justify-center text-stone-500 dark:text-stone-400 hover:text-primary dark:text-sage hover:shadow-md transition-all relative">
                    <span class="material-symbols-outlined">notifications</span>
                    <?php if($total_notif > 0): ?>
                        <span class="absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full border-2 border-white animate-ping"></span>
                        <span class="absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full border-2 border-white"></span>
                    <?php endif; ?>
                </button>

                <div id="notificationDropdown" class="absolute right-16 top-14 w-80 bg-white rounded-2xl shadow-xl border border-tan/20 dark:border-stone-800 py-2 hidden transform origin-top-right transition-all z-50">
                    <div class="px-4 py-3 border-b border-gray-100 flex justify-between items-center">
                        <h4 class="font-bold text-primary dark:text-sage">Notifikasi</h4>
                        <?php if($total_notif > 0): ?>
                            <span class="text-[10px] bg-red-100 text-red-600 px-2 py-1 rounded-full font-bold"><?= $total_notif ?> Baru</span>
                        <?php endif; ?>
                    </div>
                    <div class="max-h-64 overflow-y-auto">
                        <?php if($total_new_orders > 0): ?>
                        <a href="orders.php" class="flex items-start gap-3 px-4 py-3 hover:bg-cream dark:bg-stone-800 transition-colors border-b border-gray-50">
                            <div class="p-2 bg-orange-100 text-orange-600 rounded-full"><span class="material-symbols-outlined text-lg">shopping_bag</span></div>
                            <div><p class="text-sm font-bold text-gray-800">Pesanan Baru!</p><p class="text-xs text-gray-500">Ada <?= $total_new_orders ?> pesanan menunggu.</p></div>
                        </a>
                        <?php endif; ?>

                        <?php if($total_unread_chat > 0): ?>
                        <a href="chat.php" class="flex items-start gap-3 px-4 py-3 hover:bg-cream dark:bg-stone-800 transition-colors border-b border-gray-50">
                            <div class="p-2 bg-blue-100 text-blue-600 rounded-full"><span class="material-symbols-outlined text-lg">chat</span></div>
                            <div><p class="text-sm font-bold text-gray-800">Pesan Masuk</p><p class="text-xs text-gray-500">Ada <?= $total_unread_chat ?> pesan belum dibaca.</p></div>
                        </a>
                        <?php endif; ?>

                        <?php if($total_notif == 0): ?>
                            <div class="text-center py-6 text-gray-400 text-xs italic">Tidak ada notifikasi baru.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <button onclick="toggleDropdown('profileDropdown')" class="flex items-center gap-3 bg-white p-1.5 pr-4 rounded-full border border-tan/20 dark:border-stone-800 card-shadow hover:shadow-md transition-all focus:outline-none">
                    <div class="w-9 h-9 rounded-full bg-tan text-white flex items-center justify-center font-bold text-sm border-2 border-[var(--cream-bg)]"><?= strtoupper(substr($seller_name, 0, 1)) ?></div>
                    <div class="text-left hidden sm:block">
                        <p class="text-xs font-bold leading-none title-font"><?= $seller_name ?></p>
                        <p class="text-[10px] text-stone-500 dark:text-stone-400 leading-none mt-1 font-bold uppercase">Seller</p>
                    </div>
                    <span class="material-symbols-outlined text-[18px] text-stone-500 dark:text-stone-400">expand_more</span>
                </button>

                <div id="profileDropdown" class="absolute right-0 top-14 w-56 bg-white rounded-2xl shadow-xl border border-tan/20 dark:border-stone-800 py-2 hidden transform origin-top-right transition-all z-50">
                    <a href="profile.php" class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700 hover:bg-sage/30 hover:text-primary dark:text-sage transition-colors"><span class="material-symbols-outlined text-[20px]">store</span> Profil Toko</a>
                    <div class="border-t border-gray-100 my-1"></div>
                    <a href="../auth/logout.php" class="flex items-center gap-2 px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors"><span class="material-symbols-outlined text-[20px]">logout</span> Log Out</a>
                </div>
            </div>
        </header>

        <div class="flex flex-col md:flex-row justify-between items-end gap-4 mb-8" data-aos="fade-up">
            <form method="GET" class="flex flex-wrap items-end gap-3 w-full md:w-auto bg-white p-4 rounded-[2rem] border border-tan/20 dark:border-stone-800 card-shadow">
                <div>
                    <label class="block text-[10px] font-bold text-stone-400 uppercase mb-1">Dari Tanggal</label>
                    <input type="date" name="start_date" value="<?= $start_date ?>" class="px-4 py-2.5 rounded-xl bg-cream dark:bg-stone-800 border-none text-sm focus:ring-1 focus:ring-[var(--warm-tan)]">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-stone-400 uppercase mb-1">Sampai Tanggal</label>
                    <input type="date" name="end_date" value="<?= $end_date ?>" class="px-4 py-2.5 rounded-xl bg-cream dark:bg-stone-800 border-none text-sm focus:ring-1 focus:ring-[var(--warm-tan)]">
                </div>
                <button type="submit" class="px-6 py-2.5 bg-primary text-white rounded-xl text-sm font-bold hover:bg-chocolate transition-colors shadow-lg">Filter Data</button>
            </form>

            <div class="flex gap-2">
                <button onclick="exportTableToExcel('reportTable', 'Laporan_Penjualan_<?= date('Ymd') ?>')" class="px-6 py-3 bg-green-600 text-white rounded-2xl text-sm font-bold hover:bg-green-700 transition-colors shadow-lg flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">table_view</span> Excel
                </button>
                <a href="export_pdf.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" target="_blank" class="px-6 py-3 bg-red-600 text-white rounded-2xl text-sm font-bold hover:bg-red-700 transition-colors shadow-lg flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">picture_as_pdf</span> PDF
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8" data-aos="fade-up" data-aos-delay="100">
            <div class="bg-white p-6 rounded-[2.5rem] border border-tan/20 dark:border-stone-800 card-shadow flex items-center justify-between hover:-translate-y-1 transition-transform">
                <div>
                    <p class="text-xs font-bold text-stone-400 uppercase tracking-widest">Total Pendapatan</p>
                    <h3 class="text-2xl font-bold text-primary dark:text-sage mt-1">Rp <?= number_format($total_revenue, 0, ',', '.') ?></h3>
                </div>
                <div class="w-14 h-14 rounded-full bg-green-100 flex items-center justify-center text-green-700">
                    <span class="material-symbols-outlined text-2xl">payments</span>
                </div>
            </div>
            <div class="bg-white p-6 rounded-[2.5rem] border border-tan/20 dark:border-stone-800 card-shadow flex items-center justify-between hover:-translate-y-1 transition-transform">
                <div>
                    <p class="text-xs font-bold text-stone-400 uppercase tracking-widest">Produk Terjual</p>
                    <h3 class="text-2xl font-bold text-primary dark:text-sage mt-1"><?= $total_sold_items ?> <span class="text-sm font-normal">Pcs</span></h3>
                </div>
                <div class="w-14 h-14 rounded-full bg-blue-100 flex items-center justify-center text-blue-700">
                    <span class="material-symbols-outlined text-2xl">shopping_cart</span>
                </div>
            </div>
            <div class="bg-white p-6 rounded-[2.5rem] border border-tan/20 dark:border-stone-800 card-shadow flex items-center justify-between hover:-translate-y-1 transition-transform">
                <div>
                    <p class="text-xs font-bold text-stone-400 uppercase tracking-widest">Total Transaksi</p>
                    <h3 class="text-2xl font-bold text-primary dark:text-sage mt-1"><?= $total_transactions ?></h3>
                </div>
                <div class="w-14 h-14 rounded-full bg-orange-100 flex items-center justify-center text-orange-700">
                    <span class="material-symbols-outlined text-2xl">receipt_long</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8" data-aos="fade-up" data-aos-delay="200">
            <div class="bg-white p-6 rounded-[2.5rem] border border-tan/20 dark:border-stone-800 card-shadow">
                <h4 class="text-sm font-bold text-stone-800 dark:text-stone-200 uppercase mb-4 tracking-wider flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg text-tan">trending_up</span> Tren Pendapatan
                </h4>
                <div class="h-64">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <div class="bg-white p-6 rounded-[2.5rem] border border-tan/20 dark:border-stone-800 card-shadow">
                <h4 class="text-sm font-bold text-stone-800 dark:text-stone-200 uppercase mb-4 tracking-wider flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg text-tan">pie_chart</span> 5 Buku Terlaris
                </h4>
                <div class="h-64 flex justify-center">
                    <canvas id="productsChart"></canvas>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] p-8 border border-tan/20 dark:border-stone-800 card-shadow overflow-hidden" data-aos="fade-up" data-aos-delay="300">
            <h3 class="text-xl font-bold text-stone-800 dark:text-stone-200 mb-6 title-font">Rincian Transaksi</h3>
            <div class="overflow-x-auto">
                <table id="reportTable" class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-stone-500 dark:text-stone-400 text-sm border-b border-tan/20 dark:border-stone-800 bg-stone-50/50">
                            <th class="p-4 pl-6 font-bold uppercase tracking-wider">Tanggal</th>
                            <th class="p-4 font-bold uppercase tracking-wider">No. Invoice</th>
                            <th class="p-4 font-bold uppercase tracking-wider">Pembeli</th>
                            <th class="p-4 font-bold uppercase tracking-wider">Buku</th>
                            <th class="p-4 font-bold uppercase tracking-wider text-center">Qty</th>
                            <th class="p-4 font-bold uppercase tracking-wider text-right">Harga Satuan</th>
                            <th class="p-4 font-bold uppercase tracking-wider text-right pr-6">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-stone-800 dark:text-stone-200">
                        <?php if (count($data_rows) > 0): ?>
                            <?php foreach ($data_rows as $row): ?>
                            <tr class="border-b border-tan/20 dark:border-stone-800 hover:bg-cream dark:bg-stone-800/50 transition-colors">
                                <td class="p-4 pl-6 text-stone-500"><?= date('d/m/Y', strtotime($row['order_date'])) ?></td>
                                <td class="p-4 font-mono font-bold text-primary dark:text-sage"><?= $row['invoice_number'] ?></td>
                                <td class="p-4"><?= $row['buyer_name'] ?></td>
                                <td class="p-4 font-bold"><?= $row['book_title'] ?></td>
                                <td class="p-4 text-center"><?= $row['qty'] ?></td>
                                <td class="p-4 text-right">Rp <?= number_format($row['price_at_transaction'], 0, ',', '.') ?></td>
                                <td class="p-4 text-right pr-6 font-bold text-chocolate dark:text-tan">Rp <?= number_format($row['subtotal'], 0, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="bg-stone-50/80 font-bold">
                                <td colspan="6" class="p-4 text-right uppercase tracking-wider text-primary dark:text-sage">Total Pendapatan</td>
                                <td class="p-4 text-right text-lg text-chocolate dark:text-tan pr-6">Rp <?= number_format($total_revenue, 0, ',', '.') ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="p-10 text-center text-stone-400 italic">Tidak ada data penjualan pada periode ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({ once: true, duration: 800, offset: 50 });

    // Sidebar Logic
    let isSidebarOpen = true;
    const sidebar = document.getElementById('sidebar');
    const mainDiv = document.getElementById('main-content');
    function toggleSidebar() {
        if (isSidebarOpen) {
            sidebar.classList.remove('w-64'); sidebar.classList.add('w-20', 'sidebar-collapsed');
            mainDiv.classList.remove('ml-64'); mainDiv.classList.add('ml-20');
        } else {
            sidebar.classList.remove('w-20', 'sidebar-collapsed'); sidebar.classList.add('w-64');
            mainDiv.classList.remove('ml-20'); mainDiv.classList.add('ml-64');
        }
        isSidebarOpen = !isSidebarOpen;
    }

    // Dropdown Logic
    function toggleDropdown(id) {
        const dropdown = document.getElementById(id);
        const allDropdowns = document.querySelectorAll('[id$="Dropdown"]');
        allDropdowns.forEach(dd => { if(dd.id !== id) dd.classList.add('hidden'); });
        if (dropdown) dropdown.classList.toggle('hidden');
    }

    window.onclick = function(event) {
        if (!event.target.closest('button')) {
            const dropdowns = document.querySelectorAll('[id$="Dropdown"]');
            dropdowns.forEach(dd => dd.classList.add('hidden'));
        }
    }

    // Export Excel
    function exportTableToExcel(tableID, filename = ''){
        var downloadLink;
        var dataType = 'application/vnd.ms-excel';
        var tableSelect = document.getElementById(tableID);
        var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');

        filename = filename ? filename + '.xls' : 'excel_data.xls';
        downloadLink = document.createElement("a");
        document.body.appendChild(downloadLink);

        if(navigator.msSaveOrOpenBlob){
            var blob = new Blob(['\ufeff', tableHTML], { type: dataType });
            navigator.msSaveOrOpenBlob( blob, filename);
        }else{
            downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
            downloadLink.download = filename;
            downloadLink.click();
        }
    }

    // --- CHART.JS CONFIGURATION ---
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const productsCtx = document.getElementById('productsChart').getContext('2d');

    // Data dari PHP
    const chartDates = <?= $json_dates ?>;
    const chartRevenues = <?= $json_revenues ?>;
    const chartBooks = <?= $json_books ?>;
    const chartQtys = <?= $json_qtys ?>;

    // Line Chart (Pendapatan)
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: chartDates,
            datasets: [{
                label: 'Pendapatan (Rp)',
                data: chartRevenues,
                borderColor: '#3E4B1C', // Deep Forest
                backgroundColor: 'rgba(62, 75, 28, 0.1)',
                borderWidth: 2,
                tension: 0.4,
                pointBackgroundColor: '#B18143', // Warm Tan
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f3f4f6' } },
                x: { grid: { display: false } }
            }
        }
    });

    // Doughnut Chart (Buku Terlaris)
    new Chart(productsCtx, {
        type: 'doughnut',
        data: {
            labels: chartBooks,
            datasets: [{
                data: chartQtys,
                backgroundColor: [
                    '#3E4B1C', // Deep Forest
                    '#B18143', // Warm Tan
                    '#663F05', // Chocolate
                    '#DCE3AC', // Light Sage
                    '#A8A29E'  // Stone Gray
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } }
            }
        }
    });
</script>


    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        function toggleDarkMode() {
            const html = document.documentElement;
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                html.classList.add('dark');
                localStorage.theme = 'dark';
            }
        }
    </script>


    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        function toggleDarkMode() {
            const html = document.documentElement;
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                html.classList.add('dark');
                localStorage.theme = 'dark';
            }
        }
    </script>

</body>
</html>
