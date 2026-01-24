<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../config/db.php';

// 1. Cek Keamanan
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller') {
    header("Location: ../auth/login.php");
    exit;
}

$seller_id = $_SESSION['user_id'];
$seller_name = $_SESSION['full_name'];

// --- 2. LOGIKA DATA DASHBOARD ---

// A. Total Pendapatan
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

// C. Total Produk Aktif
$q_products = mysqli_query($conn, "SELECT COUNT(id) as total FROM books WHERE seller_id = '$seller_id'");
$product_count = mysqli_fetch_assoc($q_products)['total'];

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

// E. Chat Terbaru
$q_chat = mysqli_query($conn, "
    SELECT m.*, u.full_name, u.profile_image 
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE m.receiver_id = '$seller_id' 
    GROUP BY m.sender_id
    ORDER BY m.created_at DESC LIMIT 3
");

// --- 3. FITUR BARU: NOTIFIKASI PENTING ---

// F. Cek Stok Menipis (Kurang dari 5)
$q_low_stock = mysqli_query($conn, "SELECT title, stock FROM books WHERE seller_id = '$seller_id' AND stock <= 5 AND stock > 0 LIMIT 3");

// G. Cek Pesanan Selesai (Terbaru)
$q_finished_orders = mysqli_query($conn, "
    SELECT DISTINCT o.invoice_number 
    FROM orders o 
    JOIN order_items oi ON o.id = oi.order_id 
    WHERE oi.seller_id = '$seller_id' AND o.status = 'finished' 
    ORDER BY o.order_date DESC LIMIT 3
");

// H. Cek Pesanan Ditolak/Refund (Terbaru)
$q_rejected_orders = mysqli_query($conn, "
    SELECT DISTINCT o.invoice_number, o.status 
    FROM orders o 
    JOIN order_items oi ON o.id = oi.order_id 
    WHERE oi.seller_id = '$seller_id' AND (o.status = 'rejected' OR o.status = 'refunded') 
    ORDER BY o.order_date DESC LIMIT 3
");


// --- 4. LOGIKA NOTIFIKASI NAVBAR ---
$total_new_orders = $pending_count;
$query_unread = mysqli_query($conn, "SELECT COUNT(*) as total FROM messages WHERE receiver_id = '$seller_id' AND is_read = 0");
$total_unread_chat = mysqli_fetch_assoc($query_unread)['total'];

$total_notif = $total_new_orders + $total_unread_chat;

// Data Grafik Dummy
$chart_data = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Dashboard - Libraria Seller</title>

<script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 
<link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&family=Cinzel:wght@700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

<style type="text/tailwindcss">
    :root {
        --deep-forest: #3E4B1C;
        --chocolate-brown: #663F05;
        --warm-tan: #B18143;
        --light-sage: #DCE3AC;
        --cream-bg: #FEF9E6;
        --sidebar-active: var(--deep-forest);
        --text-dark: #2D2418;
        --text-muted: #6B6155;
        --border-color: #E6E1D3;
    }
    body { font-family: 'Quicksand', sans-serif; background-color: var(--cream-bg); color: var(--text-dark); }
    .font-logo { font-family: 'Cinzel', serif; }
    .title-font { font-weight: 700; }
    .card-shadow { box-shadow: 0 10px 40px -10px rgba(62, 75, 28, 0.08); }
    .sidebar-active { background-color: var(--sidebar-active); color: white; box-shadow: 0 4px 12px rgba(62, 75, 28, 0.3); }
    
    #sidebar, #main-content, #sidebar-logo, .sidebar-text-wrapper, .menu-text { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    #sidebar-header { justify-content: flex-start; padding-left: 1.5rem; padding-right: 1.5rem; }
    #sidebar-logo { height: 5rem; width: auto; }
    .sidebar-text-wrapper { opacity: 1; width: auto; margin-left: 0.75rem; overflow: hidden; white-space: nowrap; }
    .menu-text { opacity: 1; width: auto; display: inline-block; }

    .sidebar-collapsed #sidebar-header { justify-content: center !important; padding-left: 0 !important; padding-right: 0 !important; }
    .sidebar-collapsed #sidebar-logo { height: 3.5rem !important; width: auto; margin: 0 auto; }
    .sidebar-collapsed .sidebar-text-wrapper { opacity: 0 !important; width: 0 !important; margin-left: 0 !important; pointer-events: none; }
    .sidebar-collapsed .menu-text { opacity: 0 !important; width: 0 !important; display: none; }
    .sidebar-collapsed nav a { justify-content: center; padding-left: 0; padding-right: 0; }
</style>
</head>
<body class="overflow-x-hidden">

<div class="flex min-h-screen">
    
    <aside id="sidebar" class="w-64 bg-white border-r border-[var(--border-color)] flex flex-col fixed h-full z-30 overflow-hidden shadow-lg lg:shadow-none">
        
        <div id="sidebar-header" class="h-28 flex items-center border-b border-[var(--border-color)] shrink-0">
            <img id="sidebar-logo" src="../assets/images/logo.png" alt="Libraria Logo" class="object-contain flex-shrink-0">
            <div class="sidebar-text-wrapper flex flex-col justify-center">
                <h1 class="text-2xl font-bold text-[var(--deep-forest)] tracking-tight font-logo leading-none">LIBRARIA</h1>
                <p class="text-xs font-bold tracking-[0.2em] text-[var(--warm-tan)] mt-1 uppercase">Seller Panel</p>
            </div>
        </div>
        
        <nav class="flex-1 px-3 space-y-2 mt-6 overflow-y-auto overflow-x-hidden">
            
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 sidebar-active rounded-2xl transition-all group shadow-md shadow-green-900/10">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">dashboard</span>
                <span class="font-semibold menu-text whitespace-nowrap">Dashboard</span>
            </a>
            
            <a href="categories.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">category</span>
                <span class="font-medium menu-text whitespace-nowrap">Kategori</span>
            </a>

            <a href="products.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">inventory_2</span>
                <span class="font-medium menu-text whitespace-nowrap">Produk Saya</span>
            </a>

            <a href="orders.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">shopping_cart_checkout</span>
                <span class="font-medium menu-text whitespace-nowrap">Pesanan Masuk</span>
                <?php if($total_new_orders > 0): ?>
                <span class="ml-auto bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full menu-text animate-pulse"><?= $total_new_orders ?></span>
                <?php endif; ?>
            </a>

            <a href="reports.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">bar_chart</span>
                <span class="font-medium menu-text whitespace-nowrap">Laporan</span>
            </a>

            <a href="chat.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">chat_bubble</span>
                <span class="font-medium menu-text whitespace-nowrap">Chat</span>
                <?php if($total_unread_chat > 0): ?>
                <span class="ml-auto bg-blue-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full menu-text"><?= $total_unread_chat ?></span>
                <?php endif; ?>
            </a>

            <a href="help.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">help</span>
                <span class="font-medium menu-text whitespace-nowrap">Bantuan</span>
            </a>
        </nav>
        
        <div class="p-3 border-t border-[var(--border-color)]">
            <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-3 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-2xl transition-colors group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">logout</span>
                <span class="font-medium menu-text whitespace-nowrap">Sign Out</span>
            </a>
        </div>
    </aside>

    <main id="main-content" class="flex-1 ml-64 p-4 lg:p-8 transition-all duration-300">
        
        <header class="flex justify-between items-center mb-10 bg-white/50 backdrop-blur-sm p-4 rounded-3xl border border-[var(--border-color)] sticky top-4 z-20 shadow-sm" data-aos="fade-down">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl hover:bg-[var(--light-sage)] text-[var(--deep-forest)] transition-colors focus:outline-none">
                    <span class="material-symbols-outlined">menu_open</span>
                </button>
                <div><h2 class="text-xl lg:text-2xl title-font text-[var(--text-dark)] hidden md:block">Dashboard</h2></div>
            </div>
            
            <div class="flex items-center gap-4 relative">
                
                <button onclick="toggleDropdown('notificationDropdown')" class="w-10 h-10 rounded-full bg-white border border-[var(--border-color)] flex items-center justify-center text-[var(--text-muted)] hover:text-[var(--deep-forest)] hover:shadow-md transition-all relative">
                    <span class="material-symbols-outlined">notifications</span>
                    <?php if($total_notif > 0): ?>
                        <span class="absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full border-2 border-white animate-ping"></span>
                        <span class="absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full border-2 border-white"></span>
                    <?php endif; ?>
                </button>

                <div id="notificationDropdown" class="absolute right-16 top-14 w-80 bg-white rounded-2xl shadow-xl border border-[var(--border-color)] py-2 hidden transform origin-top-right transition-all z-50">
                    <div class="px-4 py-3 border-b border-gray-100 flex justify-between items-center">
                        <h4 class="font-bold text-[var(--deep-forest)]">Notifikasi</h4>
                        <?php if($total_notif > 0): ?>
                            <span class="text-[10px] bg-red-100 text-red-600 px-2 py-1 rounded-full font-bold"><?= $total_notif ?> Baru</span>
                        <?php endif; ?>
                    </div>
                    <div class="max-h-64 overflow-y-auto">
                        <?php if($total_new_orders > 0): ?>
                        <a href="orders.php" class="flex items-start gap-3 px-4 py-3 hover:bg-[var(--cream-bg)] transition-colors border-b border-gray-50">
                            <div class="p-2 bg-orange-100 text-orange-600 rounded-full"><span class="material-symbols-outlined text-lg">shopping_bag</span></div>
                            <div>
                                <p class="text-sm font-bold text-gray-800">Pesanan Baru!</p>
                                <p class="text-xs text-gray-500">Ada <?= $total_new_orders ?> pesanan menunggu konfirmasi.</p>
                            </div>
                        </a>
                        <?php endif; ?>
                        
                        <?php if($total_unread_chat > 0): ?>
                        <a href="chat.php" class="flex items-start gap-3 px-4 py-3 hover:bg-[var(--cream-bg)] transition-colors border-b border-gray-50">
                            <div class="p-2 bg-blue-100 text-blue-600 rounded-full"><span class="material-symbols-outlined text-lg">chat</span></div>
                            <div>
                                <p class="text-sm font-bold text-gray-800">Pesan Masuk</p>
                                <p class="text-xs text-gray-500">Ada <?= $total_unread_chat ?> pesan belum dibaca.</p>
                            </div>
                        </a>
                        <?php endif; ?>

                        <?php if($total_notif == 0): ?>
                            <div class="text-center py-6 text-gray-400 text-xs italic">Tidak ada notifikasi baru.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <button onclick="toggleDropdown('profileDropdown')" class="flex items-center gap-3 bg-white p-1.5 pr-4 rounded-full border border-[var(--border-color)] card-shadow hover:shadow-md transition-all focus:outline-none">
                    <div class="w-9 h-9 rounded-full bg-[var(--warm-tan)] text-white flex items-center justify-center font-bold text-sm border-2 border-[var(--cream-bg)]"><?= strtoupper(substr($seller_name, 0, 1)) ?></div>
                    <div class="text-left hidden sm:block">
                        <p class="text-xs font-bold leading-none title-font"><?= $seller_name ?></p>
                        <p class="text-[10px] text-[var(--text-muted)] leading-none mt-1 font-bold uppercase">Seller</p>
                    </div>
                    <span class="material-symbols-outlined text-[18px] text-[var(--text-muted)]">expand_more</span>
                </button>

                <div id="profileDropdown" class="absolute right-0 top-14 w-56 bg-white rounded-2xl shadow-xl border border-[var(--border-color)] py-2 hidden transform origin-top-right transition-all z-50">
                    <a href="profile.php" class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] transition-colors"><span class="material-symbols-outlined text-[20px]">store</span> Profil Toko</a>
                    <div class="border-t border-gray-100 my-1"></div>
                    <a href="../auth/logout.php" class="flex items-center gap-2 px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors"><span class="material-symbols-outlined text-[20px]">logout</span> Log Out</a>
                </div>
            </div>
        </header>

        <?php if(mysqli_num_rows($q_low_stock) > 0 || mysqli_num_rows($q_finished_orders) > 0 || mysqli_num_rows($q_rejected_orders) > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8" data-aos="fade-up">
            
            <?php if(mysqli_num_rows($q_low_stock) > 0): ?>
            <div class="bg-red-50 p-6 rounded-[2rem] border border-red-100 card-shadow relative overflow-hidden">
                <div class="flex items-center gap-3 mb-3">
                    <span class="material-symbols-outlined text-red-600 bg-white p-2 rounded-full shadow-sm">inventory_2</span>
                    <h3 class="font-bold text-red-800 text-sm">Stok Menipis!</h3>
                </div>
                <ul class="space-y-2">
                    <?php while($ls = mysqli_fetch_assoc($q_low_stock)): ?>
                    <li class="text-xs text-red-700 flex justify-between">
                        <span class="truncate max-w-[70%]"><?= $ls['title'] ?></span>
                        <span class="font-bold bg-white px-2 py-0.5 rounded-md border border-red-200">Sisa: <?= $ls['stock'] ?></span>
                    </li>
                    <?php endwhile; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if(mysqli_num_rows($q_finished_orders) > 0): ?>
            <div class="bg-green-50 p-6 rounded-[2rem] border border-green-100 card-shadow relative overflow-hidden">
                <div class="flex items-center gap-3 mb-3">
                    <span class="material-symbols-outlined text-green-600 bg-white p-2 rounded-full shadow-sm">check_circle</span>
                    <h3 class="font-bold text-green-800 text-sm">Barang Sampai!</h3>
                </div>
                <ul class="space-y-2">
                    <?php while($fo = mysqli_fetch_assoc($q_finished_orders)): ?>
                    <li class="text-xs text-green-700 flex justify-between">
                        <span><?= $fo['invoice_number'] ?></span>
                        <span class="font-bold text-[10px] bg-green-200 px-2 py-0.5 rounded text-green-800">Selesai</span>
                    </li>
                    <?php endwhile; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if(mysqli_num_rows($q_rejected_orders) > 0): ?>
            <div class="bg-orange-50 p-6 rounded-[2rem] border border-orange-100 card-shadow relative overflow-hidden">
                <div class="flex items-center gap-3 mb-3">
                    <span class="material-symbols-outlined text-orange-600 bg-white p-2 rounded-full shadow-sm">cancel</span>
                    <h3 class="font-bold text-orange-800 text-sm">Pesanan Dibatalkan</h3>
                </div>
                <ul class="space-y-2">
                    <?php while($ro = mysqli_fetch_assoc($q_rejected_orders)): ?>
                    <li class="text-xs text-orange-700 flex justify-between">
                        <span><?= $ro['invoice_number'] ?></span>
                        <span class="font-bold text-[10px] bg-orange-200 px-2 py-0.5 rounded text-orange-800 uppercase"><?= $ro['status'] ?></span>
                    </li>
                    <?php endwhile; ?>
                </ul>
            </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8" data-aos="fade-up" data-aos-delay="100">
            <div class="bg-white p-6 rounded-[2.5rem] border border-[var(--border-color)] card-shadow flex flex-col gap-2 hover:-translate-y-1 transition-transform">
                <div class="flex justify-between items-start">
                    <div><p class="text-xs font-bold text-[var(--text-muted)] uppercase tracking-widest">Total Pendapatan</p><h3 class="text-2xl font-bold text-[var(--deep-forest)] mt-1">Rp <?= number_format($revenue, 0, ',', '.') ?></h3></div>
                    <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center text-green-700"><span class="material-symbols-outlined text-2xl">payments</span></div>
                </div>
                <span class="text-xs text-green-600 bg-green-50 px-2 py-1 rounded-lg w-fit mt-2 font-bold flex items-center gap-1"><span class="material-symbols-outlined text-sm">trending_up</span> +12% Bulan ini</span>
            </div>
            <div class="bg-white p-6 rounded-[2.5rem] border border-[var(--border-color)] card-shadow flex flex-col gap-2 hover:-translate-y-1 transition-transform">
                <div class="flex justify-between items-start">
                    <div><p class="text-xs font-bold text-[var(--text-muted)] uppercase tracking-widest">Pesanan Baru</p><h3 class="text-2xl font-bold text-[var(--deep-forest)] mt-1"><?= $pending_count ?></h3></div>
                    <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-700"><span class="material-symbols-outlined text-2xl">shopping_cart</span></div>
                </div>
                <span class="text-xs text-stone-500 mt-2">Perlu diproses segera</span>
            </div>
            <div class="bg-white p-6 rounded-[2.5rem] border border-[var(--border-color)] card-shadow flex flex-col gap-2 hover:-translate-y-1 transition-transform">
                <div class="flex justify-between items-start">
                    <div><p class="text-xs font-bold text-[var(--text-muted)] uppercase tracking-widest">Total Produk</p><h3 class="text-2xl font-bold text-[var(--deep-forest)] mt-1"><?= $product_count ?></h3></div>
                    <div class="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center text-orange-700"><span class="material-symbols-outlined text-2xl">inventory_2</span></div>
                </div>
                <span class="text-xs text-stone-500 mt-2">Buku aktif dijual</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <div class="lg:col-span-2 bg-white rounded-[2.5rem] p-8 border border-[var(--border-color)] card-shadow" data-aos="fade-up" data-aos-delay="200">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-[var(--text-dark)] title-font">Grafik Penjualan</h3>
                    <select class="text-xs border-none bg-[var(--cream-bg)] rounded-lg px-3 py-1 font-bold text-[var(--deep-forest)] focus:ring-0">
                        <option>Tahun 2025</option>
                    </select>
                </div>
                <div class="w-full h-64">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div class="lg:col-span-1 bg-[var(--deep-forest)] rounded-[2.5rem] p-8 card-shadow text-white relative overflow-hidden" data-aos="fade-up" data-aos-delay="300">
                <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/5 rounded-full blur-2xl"></div>
                
                <div class="flex justify-between items-center mb-6 relative z-10">
                    <h3 class="text-xl font-bold title-font">Chat Terbaru</h3>
                    <a href="chat.php" class="p-2 bg-white/10 rounded-full hover:bg-white/20 transition-all">
                        <span class="material-symbols-outlined text-sm">open_in_new</span>
                    </a>
                </div>
                
                <div class="space-y-4 relative z-10">
                    <?php if(mysqli_num_rows($q_chat) > 0): ?>
                        <?php while($chat = mysqli_fetch_assoc($q_chat)): 
                            $sender_img = !empty($chat['profile_image']) ? "../assets/uploads/profiles/".$chat['profile_image'] : "../assets/images/default_profile.png";
                        ?>
                        <div class="flex items-start gap-3 p-3 bg-white/10 rounded-2xl hover:bg-white/15 transition-all cursor-pointer border border-white/5" onclick="window.location='chat.php?uid=<?= $chat['sender_id'] ?>'">
                            <img src="<?= $sender_img ?>" class="w-10 h-10 rounded-full object-cover border border-white/20 flex-shrink-0">
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-center mb-0.5">
                                    <h4 class="text-sm font-bold truncate"><?= $chat['full_name'] ?></h4>
                                    <span class="text-[10px] text-white/60"><?= date('H:i', strtotime($chat['created_at'])) ?></span>
                                </div>
                                <p class="text-xs text-white/80 truncate"><?= $chat['message'] ?></p>
                            </div>
                            <?php if($chat['is_read'] == 0): ?><span class="w-2 h-2 rounded-full bg-blue-400 mt-2"></span><?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-white/50 text-sm">Belum ada pesan masuk.</div>
                    <?php endif; ?>
                </div>
                <div class="mt-6 text-center">
                    <a href="chat.php" class="text-xs font-bold text-[var(--light-sage)] hover:text-white transition-colors">Lihat Semua Pesan</a>
                </div>
            </div>
        </div>

        <div class="mt-8 bg-white rounded-[2.5rem] p-8 border border-[var(--border-color)] card-shadow" data-aos="fade-up" data-aos-delay="400">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-[var(--text-dark)] title-font">Pesanan Terbaru</h3>
                <a href="orders.php" class="text-sm font-bold text-[var(--deep-forest)] hover:underline">Lihat Semua</a>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-xs text-[var(--text-muted)] uppercase border-b border-[var(--border-color)]">
                            <th class="px-4 py-3 font-bold">Invoice</th>
                            <th class="px-4 py-3 font-bold">Pembeli</th>
                            <th class="px-4 py-3 font-bold">Tanggal</th>
                            <th class="px-4 py-3 font-bold">Total Omset</th>
                            <th class="px-4 py-3 font-bold">Status</th>
                            <th class="px-4 py-3 font-bold text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php if(mysqli_num_rows($q_recent) > 0): ?>
                            <?php while($order = mysqli_fetch_assoc($q_recent)): 
                                $status_color = 'bg-gray-100 text-gray-600';
                                if($order['status'] == 'pending') $status_color = 'bg-yellow-100 text-yellow-700';
                                elseif($order['status'] == 'waiting_approval') $status_color = 'bg-blue-100 text-blue-700';
                                elseif($order['status'] == 'approved') $status_color = 'bg-indigo-100 text-indigo-700';
                                elseif($order['status'] == 'shipping') $status_color = 'bg-purple-100 text-purple-700';
                                elseif($order['status'] == 'finished') $status_color = 'bg-green-100 text-green-700';
                                elseif($order['status'] == 'rejected') $status_color = 'bg-red-100 text-red-700';
                            ?>
                            <tr class="hover:bg-[var(--cream-bg)]/30 transition-colors border-b border-gray-50 last:border-0">
                                <td class="px-4 py-4 font-bold text-[var(--deep-forest)]"><?= $order['invoice_number'] ?></td>
                                <td class="px-4 py-4"><?= $order['buyer_name'] ?></td>
                                <td class="px-4 py-4 text-stone-500"><?= date('d M Y', strtotime($order['order_date'])) ?></td>
                                <td class="px-4 py-4 font-bold">Rp <?= number_format($order['total_omset'], 0, ',', '.') ?></td>
                                <td class="px-4 py-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold <?= $status_color ?>">
                                        <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <a href="orders.php" class="p-2 rounded-lg bg-[var(--cream-bg)] text-[var(--deep-forest)] hover:bg-[var(--deep-forest)] hover:text-white transition-all inline-flex">
                                        <span class="material-symbols-outlined text-sm">visibility</span>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="px-4 py-8 text-center text-stone-400 italic">Belum ada pesanan masuk.</td></tr>
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

    // Chart.js Setup
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
            datasets: [{
                label: 'Penjualan',
                data: [<?= implode(',', $chart_data) ?>],
                borderColor: '#3E4B1C',
                backgroundColor: 'rgba(220, 227, 172, 0.2)',
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
                y: { beginAtZero: true, grid: { color: '#f0f0f0' } },
                x: { grid: { display: false } }
            }
        }
    });
</script>

</body>
</html>
