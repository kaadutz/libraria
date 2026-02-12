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

// --- LOGIKA UPDATE STATUS ---
if (isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $status   = mysqli_real_escape_string($conn, $_POST['status']);

    // Siapkan SQL Extra untuk alasan penolakan
    $reject_reason_sql = "";
    if ($status == 'rejected' && isset($_POST['reject_reason'])) {
        $reason = mysqli_real_escape_string($conn, $_POST['reject_reason']);
        $reject_reason_sql = ", reject_reason = '$reason'";
    }

    // 1. LOGIKA TOLAK PESANAN / TOLAK REFUND (BALIKIN STOK)
    if ($status == 'rejected') {
        $cek = mysqli_query($conn, "SELECT status FROM orders WHERE id = '$order_id'");
        $data_order = mysqli_fetch_assoc($cek);

        // Hanya balikin stok jika status sebelumnya bukan rejected/refunded/finished
        if ($data_order && !in_array($data_order['status'], ['rejected', 'refunded', 'finished'])) {
            $q_items = mysqli_query($conn, "SELECT book_id, qty FROM order_items WHERE order_id = '$order_id'");
            while ($item = mysqli_fetch_assoc($q_items)) {
                $b_id = intval($item['book_id']);
                $qty  = intval($item['qty']);
                mysqli_query($conn, "UPDATE books SET stock = stock + $qty WHERE id = '$b_id'");
            }
        }
    }

    // 2. LOGIKA KIRIM BARANG (INPUT RESI)
    $sql_extra = "";
    if ($status == 'shipping') {
        $resi = mysqli_real_escape_string($conn, $_POST['tracking_number']);
        $ekspedisi = mysqli_real_escape_string($conn, $_POST['expedition_name']);
        $sql_extra = ", tracking_number='$resi', expedition_name='$ekspedisi'";
    }

    // 3. LOGIKA HAPUS TRANSAKSI
    if ($status == 'delete') {
        $query = "DELETE FROM orders WHERE id = '$order_id'";
    } else {
        // Gabungkan update status dan update alasan (jika ada)
        $query = "UPDATE orders SET status = '$status' $sql_extra $reject_reason_sql WHERE id = '$order_id'";
    }

    if (mysqli_query($conn, $query)) {
        $redirect_filter = isset($_GET['filter']) ? "?filter=".$_GET['filter'] : "";
        echo "<script>alert('Status berhasil diperbarui!'); window.location.href='orders.php$redirect_filter';</script>";
        exit;
    } else {
        die("Gagal update status: " . mysqli_error($conn));
    }
}

// --- LOGIKA FILTER PESANAN ---
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filter_sql = "";
switch ($filter) {
    case 'unpaid': $filter_sql = "AND (o.status = 'pending' OR o.status = 'waiting_approval')"; break;
    case 'approved': $filter_sql = "AND o.status = 'approved'"; break;
    case 'shipping': $filter_sql = "AND o.status = 'shipping'"; break;
    case 'finished': $filter_sql = "AND o.status = 'finished'"; break;
    case 'refund': $filter_sql = "AND (o.status = 'refund' OR o.status = 'refunded')"; break;
    case 'rejected': $filter_sql = "AND o.status = 'rejected'"; break;
    default: $filter_sql = ""; break;
}

// --- AMBIL DATA PESANAN ---
$query_orders = "
    SELECT DISTINCT o.*, u.full_name as buyer_name, u.address as buyer_address
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN users u ON o.buyer_id = u.id
    WHERE oi.seller_id = '$seller_id'
    $filter_sql
    ORDER BY o.order_date DESC
";
$orders = mysqli_query($conn, $query_orders);

// --- DATA NOTIFIKASI ---
$q_pending = mysqli_query($conn, "SELECT COUNT(DISTINCT o.id) as total FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE oi.seller_id = '$seller_id' AND (o.status = 'pending' OR o.status = 'waiting_approval')");
$total_new_orders = mysqli_fetch_assoc($q_pending)['total'];
$q_chat = mysqli_query($conn, "SELECT COUNT(*) as total FROM messages WHERE receiver_id = '$seller_id' AND is_read = 0");
$total_unread_chat = mysqli_fetch_assoc($q_chat)['total'];
$total_notif = $total_new_orders + $total_unread_chat;
?>

<!DOCTYPE html>
<html lang="id">
<head>
<title>Pesanan Masuk - Libraria Seller</title>

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
                <div><h2 class="text-xl lg:text-2xl title-font text-stone-800 dark:text-stone-200 hidden md:block">Kelola Pesanan</h2></div>
            </div>

            <div class="flex items-center gap-4 relative">
                <button onclick="toggleDarkMode()" class="w-10 h-10 flex items-center justify-center rounded-full text-stone-500 dark:text-stone-400 hover:bg-primary hover:text-white dark:hover:bg-stone-800 transition-all duration-300">
                    <span class="material-icons-outlined text-xl">dark_mode</span>
                </button>

                <button onclick="toggleDropdown('notificationDropdown')" class="w-10 h-10 rounded-full bg-white dark:bg-stone-900 border border-tan/20 dark:border-stone-700 flex items-center justify-center text-stone-500 dark:text-stone-400 hover:text-primary dark:hover:text-sage hover:shadow-md transition-all relative">
                    <span class="material-symbols-outlined">notifications</span>
                    <?php if($total_notif > 0): ?>
                        <span class="absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full border-2 border-white dark:border-stone-900 animate-ping"></span>
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

        <div class="mb-6 flex overflow-x-auto no-scrollbar gap-3 pb-2" data-aos="fade-right">
             <a href="?filter=all" class="px-5 py-2 rounded-full text-sm font-bold border whitespace-nowrap transition-all <?= $filter == 'all' ? 'bg-primary dark:bg-sage text-white dark:text-primary border-primary dark:border-sage shadow-md' : 'bg-white dark:bg-stone-900 text-stone-500 dark:text-stone-400 border-tan/20 dark:border-stone-700 hover:border-primary dark:hover:border-sage' ?>">Semua</a>
            <a href="?filter=unpaid" class="px-5 py-2 rounded-full text-sm font-bold border whitespace-nowrap transition-all <?= $filter == 'unpaid' ? 'bg-primary dark:bg-sage text-white dark:text-primary border-primary dark:border-sage shadow-md' : 'bg-white dark:bg-stone-900 text-stone-500 dark:text-stone-400 border-tan/20 dark:border-stone-700 hover:border-primary dark:hover:border-sage' ?>">Menunggu</a>
            <a href="?filter=approved" class="px-5 py-2 rounded-full text-sm font-bold border whitespace-nowrap transition-all <?= $filter == 'approved' ? 'bg-primary dark:bg-sage text-white dark:text-primary border-primary dark:border-sage shadow-md' : 'bg-white dark:bg-stone-900 text-stone-500 dark:text-stone-400 border-tan/20 dark:border-stone-700 hover:border-primary dark:hover:border-sage' ?>">Perlu Dikirim</a>
            <a href="?filter=shipping" class="px-5 py-2 rounded-full text-sm font-bold border whitespace-nowrap transition-all <?= $filter == 'shipping' ? 'bg-primary dark:bg-sage text-white dark:text-primary border-primary dark:border-sage shadow-md' : 'bg-white dark:bg-stone-900 text-stone-500 dark:text-stone-400 border-tan/20 dark:border-stone-700 hover:border-primary dark:hover:border-sage' ?>">Dikirim</a>
            <a href="?filter=finished" class="px-5 py-2 rounded-full text-sm font-bold border whitespace-nowrap transition-all <?= $filter == 'finished' ? 'bg-primary dark:bg-sage text-white dark:text-primary border-primary dark:border-sage shadow-md' : 'bg-white dark:bg-stone-900 text-stone-500 dark:text-stone-400 border-tan/20 dark:border-stone-700 hover:border-primary dark:hover:border-sage' ?>">Selesai</a>
            <a href="?filter=refund" class="px-5 py-2 rounded-full text-sm font-bold border whitespace-nowrap transition-all <?= $filter == 'refund' ? 'bg-primary dark:bg-sage text-white dark:text-primary border-primary dark:border-sage shadow-md' : 'bg-white dark:bg-stone-900 text-stone-500 dark:text-stone-400 border-tan/20 dark:border-stone-700 hover:border-primary dark:hover:border-sage' ?>">Komplain/Refund</a>
            <a href="?filter=rejected" class="px-5 py-2 rounded-full text-sm font-bold border whitespace-nowrap transition-all <?= $filter == 'rejected' ? 'bg-primary dark:bg-sage text-white dark:text-primary border-primary dark:border-sage shadow-md' : 'bg-white dark:bg-stone-900 text-stone-500 dark:text-stone-400 border-tan/20 dark:border-stone-700 hover:border-primary dark:hover:border-sage' ?>">Dibatalkan</a>
        </div>

        <div class="space-y-6" data-aos="fade-up">
            <?php if(mysqli_num_rows($orders) > 0): ?>
                <?php while($order = mysqli_fetch_assoc($orders)):
                    $order_id = $order['id'];
                    $items_q = mysqli_query($conn, "SELECT oi.*, b.title, b.image FROM order_items oi JOIN books b ON oi.book_id = b.id WHERE oi.order_id = '$order_id' AND oi.seller_id = '$seller_id'");

                    // Status Badge
                    $status_class = 'bg-gray-100 text-gray-600';
                    $status_label = ucfirst($order['status']);
                    if($order['status'] == 'pending') { $status_class = 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400'; $status_label = 'Menunggu Pembayaran'; }
                    elseif($order['status'] == 'waiting_approval') { $status_class = 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400'; $status_label = 'Perlu Konfirmasi'; }
                    elseif($order['status'] == 'approved') { $status_class = 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400'; $status_label = 'Dikemas'; }
                    elseif($order['status'] == 'shipping') { $status_class = 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400'; $status_label = 'Dikirim'; }
                    elseif($order['status'] == 'finished') { $status_class = 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'; $status_label = 'Selesai'; }
                    elseif($order['status'] == 'rejected') { $status_class = 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'; $status_label = 'Ditolak'; }
                    elseif($order['status'] == 'refund') { $status_class = 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400'; $status_label = 'Pengajuan Refund'; }
                    elseif($order['status'] == 'refunded') { $status_class = 'bg-stone-200 dark:bg-stone-700 text-stone-600 dark:text-stone-400 line-through'; $status_label = 'Refund Selesai'; }
                ?>
                <div class="bg-white dark:bg-stone-900 rounded-[2.5rem] p-6 border border-tan/20 dark:border-stone-800 card-shadow">

                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-dashed border-tan/20 dark:border-stone-800 pb-4 mb-4 gap-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-cream dark:bg-stone-800 rounded-xl text-primary dark:text-sage"><span class="material-symbols-outlined">receipt_long</span></div>
                            <div>
                                <h3 class="font-bold text-stone-800 dark:text-stone-200"><?= $order['invoice_number'] ?></h3>
                                <p class="text-xs text-stone-500 dark:text-stone-400"><?= date('d F Y, H:i', strtotime($order['order_date'])) ?> • <?= $order['buyer_name'] ?></p>
                            </div>
                        </div>
                        <span class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider <?= $status_class ?>">
                            <?= $status_label ?>
                        </span>
                    </div>

                    <div class="space-y-4 mb-6">
                        <?php
                        $subtotal_omset = 0;
                        while($item = mysqli_fetch_assoc($items_q)):
                            $img_src = !empty($item['image']) ? "../assets/uploads/books/".$item['image'] : "../assets/images/book_placeholder.png";
                            $subtotal_omset += ($item['price_at_transaction'] * $item['qty']);
                        ?>
                        <div class="flex gap-4">
                            <div class="w-16 h-20 bg-gray-100 dark:bg-stone-800 rounded-lg overflow-hidden border border-tan/20 dark:border-stone-700 shrink-0">
                                <img src="<?= $img_src ?>" class="w-full h-full object-cover">
                            </div>
                            <div>
                                <h4 class="font-bold text-sm text-stone-800 dark:text-stone-200 line-clamp-1"><?= $item['title'] ?></h4>
                                <p class="text-xs text-stone-500 dark:text-stone-400">Qty: <?= $item['qty'] ?> x Rp <?= number_format($item['price_at_transaction'], 0, ',', '.') ?></p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>

                    <div class="bg-gray-50 dark:bg-stone-800/50 p-4 rounded-2xl mb-6 text-sm">
                        <p class="font-bold text-stone-800 dark:text-stone-200 mb-1">Alamat Pengiriman:</p>
                        <p class="text-stone-600 dark:text-stone-400 mb-3"><?= $order['buyer_address'] ?></p>
                        <?php if(!empty($order['payment_proof'])): ?>
                            <a href="../assets/uploads/proofs/<?= $order['payment_proof'] ?>" target="_blank" class="text-blue-600 dark:text-blue-400 text-xs font-bold hover:underline flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">image</span> Lihat Bukti Transfer
                            </a>
                        <?php endif; ?>

                        <?php if(!empty($order['reject_reason'])): ?>
                            <div class="mt-3 pt-3 border-t border-red-100 dark:border-red-900/30">
                                <p class="text-xs font-bold text-red-500 dark:text-red-400 uppercase">Alasan Penolakan:</p>
                                <p class="text-red-700 dark:text-red-300"><?= $order['reject_reason'] ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                        <div class="text-left w-full md:w-auto">
                            <p class="text-xs text-stone-500 dark:text-stone-400 uppercase">Total Pesanan</p>
                            <p class="text-xl font-bold text-chocolate dark:text-tan">Rp <?= number_format($subtotal_omset, 0, ',', '.') ?></p>
                        </div>

                        <div class="flex gap-2 w-full md:w-auto flex-wrap justify-end">

                            <?php if($order['status'] == 'waiting_approval' || $order['status'] == 'pending'): ?>
                                <button onclick="openRejectModal(<?= $order['id'] ?>)" class="px-6 py-2.5 bg-red-100 text-red-600 font-bold rounded-xl hover:bg-red-200 transition-all text-sm w-full md:w-auto">Tolak</button>
                                <form method="POST">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" name="update_status" class="px-6 py-2.5 bg-primary text-white font-bold rounded-xl hover:bg-chocolate transition-all text-sm w-full md:w-auto">Terima Pesanan</button>
                                </form>

                            <?php elseif($order['status'] == 'approved'): ?>
                                <button onclick="openResiModal(<?= $order['id'] ?>)" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition-all text-sm w-full md:w-auto flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-lg">local_shipping</span> Input Resi
                                </button>

                            <?php elseif($order['status'] == 'refund'): ?>
                                <form method="POST" onsubmit="return confirm('Konfirmasi uang sudah dikembalikan ke pembeli?')">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="status" value="refunded">
                                    <button type="submit" name="update_status" class="px-6 py-2.5 bg-green-600 text-white font-bold rounded-xl hover:bg-green-700 transition-all text-sm w-full md:w-auto flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">check_circle</span> Terima Refund
                                    </button>
                                </form>
                                <button onclick="openRejectModal(<?= $order['id'] ?>)" class="px-6 py-2.5 bg-red-100 text-red-600 font-bold rounded-xl hover:bg-red-200 transition-all text-sm w-full md:w-auto">Tolak Refund</button>

                            <?php elseif($order['status'] == 'rejected'): ?>
                                <form method="POST" onsubmit="return confirm('Hapus transaksi ini dari riwayat?')">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="status" value="delete">
                                    <button type="submit" name="update_status" class="px-6 py-2.5 bg-red-600 text-white font-bold rounded-xl hover:bg-red-700 transition-all text-sm w-full md:w-auto flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">delete</span> Hapus Transaksi
                                    </button>
                                </form>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-20 bg-white dark:bg-stone-900 rounded-[2.5rem] border-2 border-dashed border-tan/20 dark:border-stone-800">
                    <span class="material-symbols-outlined text-6xl text-stone-400 mb-4 opacity-50">shopping_bag</span>
                    <h3 class="text-xl font-bold text-stone-800 dark:text-stone-200">Belum ada pesanan</h3>
                    <p class="text-stone-500 dark:text-stone-400">
                        <?php if($filter == 'all'): ?>Promosikan bukumu agar lebih banyak pembeli!<?php else: ?>Tidak ada pesanan dengan status "<?= ucfirst($filter) ?>"<?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<div id="resiModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
    <div class="modal-overlay absolute w-full h-full bg-black/50 backdrop-blur-sm" onclick="toggleModal('resiModal')"></div>
    <div class="modal-container bg-white dark:bg-stone-900 w-11/12 md:max-w-md mx-auto rounded-[2rem] shadow-2xl z-50 p-8 transform scale-95 transition-all border border-tan/20 dark:border-stone-700">
        <h3 class="text-xl font-bold text-primary dark:text-sage mb-4 title-font">Input Nomor Resi</h3>
        <form method="POST">
            <input type="hidden" name="order_id" id="resi_order_id">
            <input type="hidden" name="status" value="shipping">
            <div class="mb-4">
                <label class="block text-xs font-bold text-stone-500 dark:text-stone-400 uppercase mb-1">Nama Ekspedisi</label>
                <select name="expedition_name" required class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:ring-0 text-sm appearance-none cursor-pointer text-stone-800 dark:text-stone-200">
                    <option value="" disabled selected>Pilih Kurir</option>
                    <option value="JNE">JNE</option>
                    <option value="Shopee Express">Shopee Express</option>
                    <option value="J&T">J&T</option>
                </select>
            </div>
            <div class="mb-6">
                <label class="block text-xs font-bold text-stone-500 dark:text-stone-400 uppercase mb-1">Nomor Resi</label>
                <input type="text" name="tracking_number" placeholder="Masukkan no resi..." required class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:ring-0 text-sm font-bold text-stone-800 dark:text-stone-200">
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="toggleModal('resiModal')" class="flex-1 py-3 bg-gray-100 dark:bg-stone-800 text-gray-600 dark:text-stone-400 font-bold rounded-xl hover:bg-gray-200 dark:hover:bg-stone-700">Batal</button>
                <button type="submit" name="update_status" class="flex-1 py-3 bg-primary text-white font-bold rounded-xl hover:opacity-90">Kirim Barang</button>
            </div>
        </form>
    </div>
</div>

<div id="rejectModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
    <div class="modal-overlay absolute w-full h-full bg-black/50 backdrop-blur-sm" onclick="toggleModal('rejectModal')"></div>
    <div class="modal-container bg-white dark:bg-stone-900 w-11/12 md:max-w-md mx-auto rounded-[2rem] shadow-2xl z-50 p-8 transform scale-95 transition-all border border-tan/20 dark:border-stone-700">
        <h3 class="text-xl font-bold text-red-600 dark:text-red-400 mb-4 title-font">Alasan Penolakan</h3>
        <form method="POST">
            <input type="hidden" name="order_id" id="reject_order_id">
            <input type="hidden" name="status" value="rejected">

            <div class="mb-6">
                <label class="block text-xs font-bold text-stone-500 dark:text-stone-400 uppercase mb-1">Kenapa ditolak?</label>
                <textarea name="reject_reason" required rows="3" placeholder="Contoh: Stok habis, Bukti pembayaran tidak valid, dll..." class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:ring-0 text-sm resize-none text-stone-800 dark:text-stone-200"></textarea>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="toggleModal('rejectModal')" class="flex-1 py-3 bg-gray-100 dark:bg-stone-800 text-gray-600 dark:text-stone-400 font-bold rounded-xl hover:bg-gray-200 dark:hover:bg-stone-700">Batal</button>
                <button type="submit" name="update_status" class="flex-1 py-3 bg-red-600 text-white font-bold rounded-xl hover:opacity-90">Kirim Penolakan</button>
            </div>
        </form>
    </div>
</div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({ once: true, duration: 800, offset: 50 });

    // ... (Script Sidebar & Dropdown sama seperti sebelumnya) ...
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
    function toggleDropdown(id) {
        const element = document.getElementById(id);
        if (element) element.classList.toggle('hidden');
    }
    window.onclick = function(event) {
        if (!event.target.closest('button')) {
            document.querySelectorAll('[id$="Dropdown"]').forEach(dd => dd.classList.add('hidden'));
        }
    }

    function toggleModal(modalID) {
        const modal = document.getElementById(modalID);
        const container = modal.querySelector('.modal-container');
        modal.classList.toggle('opacity-0');
        modal.classList.toggle('pointer-events-none');
        document.body.classList.toggle('modal-active');
        if (!modal.classList.contains('opacity-0')) {
            setTimeout(() => { container.classList.remove('scale-95'); container.classList.add('scale-100'); }, 10);
        } else {
            container.classList.remove('scale-100'); container.classList.add('scale-95');
        }
    }

    function openResiModal(id) {
        document.getElementById('resi_order_id').value = id;
        toggleModal('resiModal');
    }

    // Fungsi Baru untuk Buka Modal Reject
    function openRejectModal(id) {
        document.getElementById('reject_order_id').value = id;
        toggleModal('rejectModal');
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
