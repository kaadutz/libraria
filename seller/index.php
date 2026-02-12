<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');
include '../config/db.php';

// 1. Cek Keamanan
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller') {
    header("Location: ../auth/login.php");
    exit;
}

$seller_id = $_SESSION['user_id'];
$seller_name = $_SESSION['full_name'];

// --- 2. LOGIKA DATA DASHBOARD ---

// A. Total Pendapatan (Finished Orders)
$q_revenue = mysqli_query($conn, "
    SELECT SUM(oi.price_at_transaction * oi.qty) as total 
    FROM order_items oi 
    JOIN orders o ON oi.order_id = o.id 
    WHERE oi.seller_id = '$seller_id' AND o.status = 'finished'
");
$revenue = mysqli_fetch_assoc($q_revenue)['total'] ?? 0;

// B. Pesanan Perlu Proses
$q_pending = mysqli_query($conn, "
    SELECT COUNT(DISTINCT o.id) as total 
    FROM orders o 
    JOIN order_items oi ON o.id = oi.order_id 
    WHERE oi.seller_id = '$seller_id' AND (o.status = 'pending' OR o.status = 'waiting_approval' OR o.status = 'approved')
");
$pending_count = mysqli_fetch_assoc($q_pending)['total'];

// C. Total Produk & Stok Menipis
$q_products = mysqli_query($conn, "SELECT COUNT(id) as total FROM books WHERE seller_id = '$seller_id'");
$product_count = mysqli_fetch_assoc($q_products)['total'];

// Ambil 3 Buku dengan stok <= 5
$q_low_stock = mysqli_query($conn, "SELECT title, stock, image FROM books WHERE seller_id = '$seller_id' AND stock <= 5 ORDER BY stock ASC LIMIT 3");

// D. 5 Pesanan Terbaru
$q_recent = mysqli_query($conn, "
    SELECT DISTINCT o.id, o.invoice_number, o.order_date, o.status, u.full_name as buyer_name,
           (SELECT SUM(qty * price_at_transaction) FROM order_items WHERE order_id = o.id AND seller_id = '$seller_id') as total_omset
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN users u ON o.buyer_id = u.id
    WHERE oi.seller_id = '$seller_id'
    ORDER BY o.order_date DESC
    LIMIT 5
");

// E. 3 Chat Terbaru (FIXED: 1 Pesan Terakhir per User)
$q_chat = mysqli_query($conn, "
    SELECT m.*, u.full_name, u.profile_image 
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE m.id IN (
        SELECT MAX(id) 
        FROM messages 
        WHERE receiver_id = '$seller_id' 
        GROUP BY sender_id
    )
    ORDER BY m.created_at DESC 
    LIMIT 3
");

// --- 3. DATA GRAFIK (REAL TIME PER BULAN) ---
$current_year = date('Y');
$chart_data = array_fill(0, 12, 0); // Inisialisasi array 0 untuk 12 bulan

$q_chart = mysqli_query($conn, "
    SELECT MONTH(o.order_date) as month, SUM(oi.price_at_transaction * oi.qty) as total
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.seller_id = '$seller_id' 
    AND o.status = 'finished' 
    AND YEAR(o.order_date) = '$current_year'
    GROUP BY MONTH(o.order_date)
");

while($row = mysqli_fetch_assoc($q_chart)) {
    // Array index 0 = Januari, maka bulan 1 harus dikurang 1
    $chart_data[$row['month'] - 1] = $row['total'];
}


// --- LOGIKA NOTIFIKASI SELLER ---
// Pesanan Perlu Proses
if(!isset($pending_count)) {
    $q_pending = mysqli_query($conn, "
        SELECT COUNT(DISTINCT o.id) as total
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE oi.seller_id = '$seller_id' AND (o.status = 'pending' OR o.status = 'waiting_approval' OR o.status = 'approved')
    ");
    if ($q_pending) {
        $pending_count = mysqli_fetch_assoc($q_pending)['total'];
    } else {
        $pending_count = 0;
    }
}
$total_new_orders = $pending_count;

// Chat Belum Dibaca
$query_unread = mysqli_query($conn, "SELECT COUNT(*) as total FROM messages WHERE receiver_id = '$seller_id' AND is_read = 0");
if ($query_unread) {
    $total_unread_chat = mysqli_fetch_assoc($query_unread)['total'];
} else {
    $total_unread_chat = 0;
}
$total_notif = $total_new_orders + $total_unread_chat;

?>

<!DOCTYPE html>
<html lang="id">
<head>
<title>Dashboard - Libraria Seller</title>

    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>

    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=DM+Serif+Display&family=Inter:wght@300;400;500;600;700&family=Material+Icons+Outlined&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet"/>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium bg-primary/10 dark:bg-stone-800 text-primary dark:text-sage shadow-md shadow-green-900/10">
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
                <?php if($total_new_orders > 0): ?>
                <span class="ml-auto bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full menu-text animate-pulse"><?= $total_new_orders ?></span>
                <?php endif; ?>
            </a>

            <a href="reports.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">bar_chart</span>
                <span class="menu-text whitespace-nowrap">Laporan</span>
            </a>

            <a href="chat.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">chat_bubble</span>
                <span class="menu-text whitespace-nowrap">Chat</span>
                <?php if($total_unread_chat > 0): ?>
                <span class="ml-auto bg-blue-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full menu-text"><?= $total_unread_chat ?></span>
                <?php endif; ?>
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
        
        <header class="flex justify-between items-center mb-8 bg-white/50 dark:bg-stone-900/50 backdrop-blur-sm p-4 rounded-3xl border border-tan/20 dark:border-stone-800 sticky top-4 z-20 shadow-sm" data-aos="fade-down">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl hover:bg-sage text-primary dark:text-sage transition-colors focus:outline-none">
                    <span class="material-symbols-outlined">menu_open</span>
                </button>
                <div><h2 class="text-xl lg:text-2xl title-font text-stone-800 dark:text-stone-200 hidden md:block">Ringkasan Toko</h2></div>
            </div>
            
            <div class="flex items-center gap-4 relative">

                <button onclick="toggleDarkMode()" class="w-10 h-10 flex items-center justify-center rounded-full text-stone-500 dark:text-stone-400 hover:bg-primary hover:text-white dark:hover:bg-stone-800 transition-all duration-300">
                    <span class="material-icons-outlined text-xl">dark_mode</span>
                </button>

                <button onclick="toggleDropdown('notificationDropdown')" class="w-10 h-10 rounded-full bg-white dark:bg-stone-900 border border-tan/20 dark:border-stone-700 flex items-center justify-center text-stone-500 dark:text-stone-400 hover:text-primary dark:hover:text-sage hover:shadow-md transition-all relative">
                    <span class="material-symbols-outlined">notifications</span>
                    <?php if($total_notif > 0): ?>
                        <span class="absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full border-2 border-white dark:border-stone-900 animate-ping"></span>
                        <span class="absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full border-2 border-white dark:border-stone-900"></span>
                    <?php endif; ?>
                </button>

                <div id="notificationDropdown" class="absolute right-16 top-14 w-80 bg-white dark:bg-stone-900 rounded-2xl shadow-xl border border-tan/20 dark:border-stone-700 py-2 hidden transform origin-top-right transition-all z-50">
                    <div class="px-4 py-3 border-b border-tan/10 dark:border-stone-800 flex justify-between items-center">
                        <h4 class="font-bold text-primary dark:text-sage">Notifikasi</h4>
                        <?php if($total_notif > 0): ?>
                            <span class="text-[10px] bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 px-2 py-1 rounded-full font-bold"><?= $total_notif ?> Baru</span>
                        <?php endif; ?>
                    </div>
                    <div class="max-h-64 overflow-y-auto custom-scroll">
                        <?php if($total_new_orders > 0): ?>
                        <a href="orders.php" class="flex items-start gap-3 px-4 py-3 hover:bg-cream dark:hover:bg-stone-800 transition-colors border-b border-tan/10 dark:border-stone-800">
                            <div class="p-2 bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 rounded-full"><span class="material-symbols-outlined text-lg">shopping_bag</span></div>
                            <div>
                                <p class="text-sm font-bold text-stone-800 dark:text-stone-200">Pesanan Baru!</p>
                                <p class="text-xs text-stone-500 dark:text-stone-400">Ada <?= $total_new_orders ?> pesanan menunggu konfirmasi.</p>
                            </div>
                        </a>
                        <?php endif; ?>
                        
                        <?php if($total_unread_chat > 0): ?>
                        <a href="chat.php" class="flex items-start gap-3 px-4 py-3 hover:bg-cream dark:hover:bg-stone-800 transition-colors border-b border-tan/10 dark:border-stone-800">
                            <div class="p-2 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-full"><span class="material-symbols-outlined text-lg">chat</span></div>
                            <div>
                                <p class="text-sm font-bold text-stone-800 dark:text-stone-200">Pesan Masuk</p>
                                <p class="text-xs text-stone-500 dark:text-stone-400">Ada <?= $total_unread_chat ?> pesan belum dibaca.</p>
                            </div>
                        </a>
                        <?php endif; ?>

                        <?php if($total_notif == 0): ?>
                            <div class="text-center py-6 text-stone-400 text-xs italic">Tidak ada notifikasi baru.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <button onclick="toggleDropdown('profileDropdown')" class="flex items-center gap-3 bg-white dark:bg-stone-900 p-1.5 pr-4 rounded-full border border-tan/20 dark:border-stone-700 card-shadow hover:shadow-md transition-all focus:outline-none">
                    <div class="w-9 h-9 rounded-full bg-tan text-white flex items-center justify-center font-bold text-sm border-2 border-cream dark:border-stone-600"><?= strtoupper(substr($seller_name, 0, 1)) ?></div>
                    <div class="text-left hidden sm:block">
                        <p class="text-xs font-bold leading-none title-font text-stone-800 dark:text-stone-200"><?= $seller_name ?></p>
                        <p class="text-[10px] text-stone-500 dark:text-stone-400 leading-none mt-1 font-bold uppercase">Seller</p>
                    </div>
                    <span class="material-symbols-outlined text-[18px] text-stone-500 dark:text-stone-400">expand_more</span>
                </button>

                <div id="profileDropdown" class="absolute right-0 top-14 w-56 bg-white dark:bg-stone-900 rounded-2xl shadow-xl border border-tan/20 dark:border-stone-700 py-2 hidden transform origin-top-right transition-all z-50">
                    <a href="profile.php" class="flex items-center gap-2 px-4 py-3 text-sm text-stone-700 dark:text-stone-300 hover:bg-sage/30 hover:text-primary transition-colors"><span class="material-symbols-outlined text-[20px]">store</span> Profil Toko</a>
                    <div class="border-t border-tan/10 dark:border-stone-800 my-1"></div>
                    <a href="../auth/logout.php" class="flex items-center gap-2 px-4 py-3 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"><span class="material-symbols-outlined text-[20px]">logout</span> Log Out</a>
                </div>
            </div>
        </header>

        <section class="relative overflow-hidden bg-primary dark:bg-stone-800 rounded-[2.5rem] p-10 text-white mb-8 shadow-xl shadow-primary/20" data-aos="fade-up">
            <div class="absolute right-0 top-0 h-full w-1/2 bg-[url('../assets/images/pattern.png')] opacity-10"></div>
            <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-sage rounded-full blur-3xl opacity-20"></div>
            
            <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6">
                <div>
                    <span class="inline-block px-3 py-1 bg-white/10 rounded-full text-[10px] font-bold uppercase tracking-widest mb-2 border border-white/10">Admin Dashboard</span>
                    <h1 class="text-3xl lg:text-4xl font-bold mb-2 title-font">Halo, <?= $seller_name ?>!</h1>
                    <p class="text-sage text-lg font-light">"Pantau performa tokomu dan tingkatkan penjualan hari ini."</p>
                    <div class="mt-6 flex gap-3">
                        <a href="products.php" class="px-6 py-2.5 bg-tan text-white font-bold rounded-xl hover:opacity-90 transition-all text-sm shadow-lg shadow-tan/30">Kelola Produk</a>
                        <a href="orders.php" class="px-6 py-2.5 bg-white/10 border border-white/20 text-white font-bold rounded-xl hover:bg-white/20 transition-all text-sm">Cek Pesanan</a>
                    </div>
                </div>
                <div class="hidden md:block relative">
                    <div class="absolute inset-0 bg-white/20 blur-xl rounded-full scale-75 animate-pulse"></div>
                    <span class="material-symbols-outlined text-[150px] text-sage opacity-90 relative z-10 drop-shadow-2xl">monitoring</span>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8" data-aos="fade-up" data-aos-delay="100">
            <div class="bg-white dark:bg-stone-900 p-6 rounded-[2.5rem] border border-tan/20 dark:border-stone-800 card-shadow flex flex-col gap-2 hover:-translate-y-1 transition-transform group">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs font-bold text-stone-500 dark:text-stone-400 uppercase tracking-widest group-hover:text-primary dark:group-hover:text-sage transition-colors">Total Pendapatan</p>
                        <h3 class="text-2xl font-bold text-primary dark:text-sage mt-1">Rp <?= number_format($revenue, 0, ',', '.') ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center text-green-700 dark:text-green-400 group-hover:scale-110 transition-transform"><span class="material-symbols-outlined text-2xl">payments</span></div>
                </div>
                <div class="w-full bg-stone-100 dark:bg-stone-800 h-1.5 rounded-full mt-2 overflow-hidden">
                    <div class="bg-green-500 h-full rounded-full" style="width: 70%"></div>
                </div>
                <p class="text-[10px] text-stone-400 dark:text-stone-500 mt-1">Akumulasi penjualan sukses</p>
            </div>

            <div class="bg-white dark:bg-stone-900 p-6 rounded-[2.5rem] border border-tan/20 dark:border-stone-800 card-shadow flex flex-col gap-2 hover:-translate-y-1 transition-transform group">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs font-bold text-stone-500 dark:text-stone-400 uppercase tracking-widest group-hover:text-primary dark:group-hover:text-sage transition-colors">Pesanan Aktif</p>
                        <h3 class="text-2xl font-bold text-primary dark:text-sage mt-1"><?= $pending_count ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-700 dark:text-blue-400 group-hover:scale-110 transition-transform"><span class="material-symbols-outlined text-2xl">shopping_cart</span></div>
                </div>
                <div class="w-full bg-stone-100 dark:bg-stone-800 h-1.5 rounded-full mt-2 overflow-hidden">
                    <div class="bg-blue-500 h-full rounded-full" style="width: 45%"></div>
                </div>
                <p class="text-[10px] text-stone-400 dark:text-stone-500 mt-1">Perlu diproses segera</p>
            </div>

            <div class="bg-white dark:bg-stone-900 p-6 rounded-[2.5rem] border border-tan/20 dark:border-stone-800 card-shadow flex flex-col gap-2 hover:-translate-y-1 transition-transform group">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs font-bold text-stone-500 dark:text-stone-400 uppercase tracking-widest group-hover:text-primary dark:group-hover:text-sage transition-colors">Total Produk</p>
                        <h3 class="text-2xl font-bold text-primary dark:text-sage mt-1"><?= $product_count ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center text-orange-700 dark:text-orange-400 group-hover:scale-110 transition-transform"><span class="material-symbols-outlined text-2xl">inventory_2</span></div>
                </div>
                <div class="w-full bg-stone-100 dark:bg-stone-800 h-1.5 rounded-full mt-2 overflow-hidden">
                    <div class="bg-orange-500 h-full rounded-full" style="width: 60%"></div>
                </div>
                <p class="text-[10px] text-stone-400 dark:text-stone-500 mt-1">Buku aktif di etalase</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8" data-aos="fade-up" data-aos-delay="200">
            
            <div class="lg:col-span-2 bg-white dark:bg-stone-900 rounded-[2.5rem] p-8 border border-tan/20 dark:border-stone-800 card-shadow">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-stone-800 dark:text-stone-200 title-font">Statistik Penjualan</h3>
                        <p class="text-xs text-stone-500 dark:text-stone-400">Performa pendapatan tahun ini</p>
                    </div>
                    <span class="text-xs bg-cream dark:bg-stone-800 px-3 py-1 rounded-lg font-bold text-primary dark:text-sage border border-tan/20 dark:border-stone-700"><?= date('Y') ?></span>
                </div>
                <div class="w-full h-64">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div class="lg:col-span-1 bg-white dark:bg-stone-900 rounded-[2.5rem] p-8 border border-tan/20 dark:border-stone-800 card-shadow relative overflow-hidden">
                <div class="flex items-center gap-2 mb-6">
                    <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                    <h3 class="text-lg font-bold text-stone-800 dark:text-stone-200 title-font">Stok Menipis</h3>
                </div>
                
                <div class="space-y-4">
                    <?php if(mysqli_num_rows($q_low_stock) > 0): ?>
                        <?php while($ls = mysqli_fetch_assoc($q_low_stock)): 
                            $img = !empty($ls['image']) ? "../assets/uploads/books/".$ls['image'] : "../assets/images/book_placeholder.png";
                        ?>
                        <div class="flex items-center gap-3 p-3 rounded-2xl bg-red-50 dark:bg-red-900/10 border border-red-100 dark:border-red-900/30">
                            <img src="<?= $img ?>" class="w-10 h-14 object-cover rounded-lg shadow-sm">
                            <div class="flex-1 min-w-0">
                                <h4 class="text-xs font-bold text-red-800 dark:text-red-300 truncate"><?= $ls['title'] ?></h4>
                                <p class="text-[10px] text-red-600 dark:text-red-400 mt-0.5">Sisa stok: <b><?= $ls['stock'] ?></b></p>
                            </div>
                            <a href="products.php" class="p-1.5 bg-white dark:bg-stone-800 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition-all shadow-sm">
                                <span class="material-symbols-outlined text-base">edit</span>
                            </a>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <span class="material-symbols-outlined text-4xl text-green-500 mb-2">check_circle</span>
                            <p class="text-sm font-bold text-green-700">Stok Aman!</p>
                            <p class="text-xs text-gray-500">Tidak ada produk dengan stok rendah.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if(mysqli_num_rows($q_low_stock) > 0): ?>
                <div class="mt-6 text-center">
                    <a href="products.php" class="text-xs font-bold text-red-500 hover:underline">Lihat Semua</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <div class="lg:col-span-2 bg-white dark:bg-stone-900 rounded-[2.5rem] p-8 border border-tan/20 dark:border-stone-800 card-shadow" data-aos="fade-up" data-aos-delay="300">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-stone-800 dark:text-stone-200 title-font">Pesanan Terbaru</h3>
                    <a href="orders.php" class="text-xs font-bold text-primary dark:text-sage hover:underline flex items-center gap-1">
                        Lihat Semua <span class="material-symbols-outlined text-sm">arrow_forward</span>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[10px] text-stone-500 dark:text-stone-400 uppercase border-b border-tan/20 dark:border-stone-800 tracking-wider">
                                <th class="px-4 py-3 font-bold">Invoice</th>
                                <th class="px-4 py-3 font-bold">Pembeli</th>
                                <th class="px-4 py-3 font-bold text-right">Total</th>
                                <th class="px-4 py-3 font-bold text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <?php if(mysqli_num_rows($q_recent) > 0): ?>
                                <?php while($order = mysqli_fetch_assoc($q_recent)): 
                                    $status_class = match($order['status']) {
                                        'pending' => 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400',
                                        'waiting_approval' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
                                        'approved' => 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400',
                                        'shipping' => 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400',
                                        'finished' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
                                        'rejected' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
                                        default => 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'
                                    };
                                ?>
                                <tr class="hover:bg-cream/30 dark:hover:bg-stone-800/30 transition-colors border-b border-tan/10 dark:border-stone-800 last:border-0 cursor-pointer" onclick="window.location='orders.php'">
                                    <td class="px-4 py-3 font-mono font-bold text-primary dark:text-sage text-xs"><?= $order['invoice_number'] ?></td>
                                    <td class="px-4 py-3 text-xs text-stone-800 dark:text-stone-300"><?= $order['buyer_name'] ?></td>
                                    <td class="px-4 py-3 font-bold text-right text-xs text-stone-800 dark:text-stone-300">Rp <?= number_format($order['total_omset'], 0, ',', '.') ?></td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $status_class ?>">
                                            <?= str_replace('_', ' ', $order['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="px-4 py-8 text-center text-stone-400 italic text-xs">Belum ada pesanan masuk.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="lg:col-span-1 bg-primary dark:bg-stone-800 rounded-[2.5rem] p-8 card-shadow text-white relative overflow-hidden flex flex-col" data-aos="fade-up" data-aos-delay="400">
                <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/5 rounded-full blur-2xl"></div>
                
                <div class="flex justify-between items-center mb-6 relative z-10">
                    <h3 class="text-xl font-bold title-font">Pesan Masuk</h3>
                    <a href="chat.php" class="p-2 bg-white/10 rounded-full hover:bg-white/20 transition-all">
                        <span class="material-symbols-outlined text-sm">chat</span>
                    </a>
                </div>
                
                <div class="space-y-4 relative z-10 flex-1 overflow-y-auto custom-scroll pr-1">
                    <?php if(mysqli_num_rows($q_chat) > 0): ?>
                        <?php while($chat = mysqli_fetch_assoc($q_chat)): 
                            $sender_img = !empty($chat['profile_image']) ? "../assets/uploads/profiles/".$chat['profile_image'] : "../assets/images/default_profile.png";
                        ?>
                        <div class="flex items-start gap-3 p-3 bg-white/10 rounded-2xl hover:bg-white/15 transition-all cursor-pointer border border-white/5" onclick="window.location='chat.php?uid=<?= $chat['sender_id'] ?>'">
                            <img src="<?= $sender_img ?>" class="w-9 h-9 rounded-full object-cover border border-white/20 flex-shrink-0">
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-center mb-0.5">
                                    <h4 class="text-xs font-bold truncate"><?= $chat['full_name'] ?></h4>
                                    <span class="text-[9px] text-white/60"><?= date('H:i', strtotime($chat['created_at'])) ?></span>
                                </div>
                                <p class="text-[10px] text-white/80 truncate"><?= $chat['message'] ?></p>
                            </div>
                            <?php if($chat['is_read'] == 0): ?><span class="w-2 h-2 rounded-full bg-blue-400 mt-2 shrink-0"></span><?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-10 text-white/50 text-xs">Belum ada pesan masuk.</div>
                    <?php endif; ?>
                </div>
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

    // Dropdown
    function toggleDropdown(id) {
        const element = document.getElementById(id);
        if (element) element.classList.toggle('hidden');
    }
    window.onclick = function(event) {
        if (!event.target.closest('button')) {
            document.querySelectorAll('[id$="Dropdown"]').forEach(dd => dd.classList.add('hidden'));
        }
    }

    // Chart.js Setup (Real Data)
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
            datasets: [{
                label: 'Penjualan',
                data: [<?= implode(',', $chart_data) ?>],
                borderColor: '#3E4B1C',
                backgroundColor: (context) => {
                    const ctx = context.chart.ctx;
                    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                    gradient.addColorStop(0, 'rgba(62, 75, 28, 0.2)');
                    gradient.addColorStop(1, 'rgba(62, 75, 28, 0)');
                    return gradient;
                },
                borderWidth: 3,
                tension: 0.4,
                pointBackgroundColor: '#B18143',
                pointRadius: 4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { 
                    beginAtZero: true, 
                    grid: { color: '#f0f0f0', borderDash: [5, 5] },
                    ticks: { font: { size: 10 } }
                },
                x: { 
                    grid: { display: false },
                    ticks: { font: { size: 10 } }
                }
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

</body>
</html>
