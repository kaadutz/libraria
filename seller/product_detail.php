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

// Cek ID Produk
if (!isset($_GET['id'])) {
    header("Location: products.php");
    exit;
}

$book_id = mysqli_real_escape_string($conn, $_GET['id']);

// Ambil Data Buku (Hanya milik seller yang sedang login)
$query = mysqli_query($conn, "
    SELECT b.*, c.name as category_name
    FROM books b
    JOIN categories c ON b.category_id = c.id
    WHERE b.id = '$book_id' AND b.seller_id = '$seller_id'
");

if (mysqli_num_rows($query) == 0) {
    echo "<script>alert('Buku tidak ditemukan atau bukan milik Anda!'); window.location='products.php';</script>";
    exit;
}

$book = mysqli_fetch_assoc($query);
$img_src = !empty($book['image']) ? "../assets/uploads/books/".$book['image'] : "../assets/images/book_placeholder.png";

// Handling Nama Author (Pastikan kolom author ada di DB)
$author_name = !empty($book['author']) ? $book['author'] : 'Penulis Tidak Diketahui';

// Hitung Keuntungan
$profit = $book['sell_price'] - $book['cost_price'];

// --- DATA NOTIFIKASI ---
$query_orders = mysqli_query($conn, "SELECT COUNT(DISTINCT o.id) as total FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE oi.seller_id = '$seller_id' AND (o.status = 'pending' OR o.status = 'waiting_approval')");
$total_new_orders = mysqli_fetch_assoc($query_orders)['total'];
$query_unread = mysqli_query($conn, "SELECT COUNT(*) as total FROM messages WHERE receiver_id = '$seller_id' AND is_read = 0");
$total_unread_chat = mysqli_fetch_assoc($query_unread)['total'];
$total_notif = $total_new_orders + $total_unread_chat;
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Detail Produk - Libraria Seller</title>

<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
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
<script src="../assets/js/theme-manager.js"></script>
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
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group text-sm">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">dashboard</span>
                <span class="font-medium menu-text whitespace-nowrap">Dashboard</span>
            </a>

            <a href="categories.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group text-sm">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">category</span>
                <span class="font-medium menu-text whitespace-nowrap">Kategori</span>
            </a>

            <a href="products.php" class="flex items-center gap-3 px-4 py-3 sidebar-active rounded-2xl transition-all group shadow-md shadow-green-900/10 text-sm">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">inventory_2</span>
                <span class="font-semibold menu-text whitespace-nowrap">Produk Saya</span>
            </a>

            <a href="orders.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group text-sm">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">shopping_cart_checkout</span>
                <span class="font-medium menu-text whitespace-nowrap">Pesanan Masuk</span>
                <?php if($total_new_orders > 0): ?>
                <span class="ml-auto bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full menu-text animate-pulse"><?= $total_new_orders ?></span>
                <?php endif; ?>
            </a>

            <a href="reports.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group text-sm">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">bar_chart</span>
                <span class="font-medium menu-text whitespace-nowrap">Laporan</span>
            </a>

            <a href="chat.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group text-sm">
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
            <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-3 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-2xl transition-colors group text-sm">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">logout</span>
                <span class="font-medium menu-text whitespace-nowrap">Sign Out</span>
            </a>
        </div>
    </aside>

    <main id="main-content" class="flex-1 ml-64 p-4 lg:p-8 transition-all duration-300">

        <header class="flex justify-between items-center mb-8 bg-white/50 backdrop-blur-sm p-4 rounded-3xl border border-[var(--border-color)] sticky top-4 z-20 shadow-sm" data-aos="fade-down">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl hover:bg-[var(--light-sage)] text-[var(--deep-forest)] transition-colors focus:outline-none">
                    <span class="material-symbols-outlined">menu_open</span>
                </button>

                <div class="hidden md:flex items-center gap-2 text-sm font-bold text-stone-400">
                    <a href="products.php" class="hover:text-[var(--deep-forest)] transition-colors">Produk Saya</a>
                    <span class="material-symbols-outlined text-xs">arrow_forward_ios</span>
                    <span class="text-[var(--deep-forest)]">Detail</span>
                </div>
            </div>

            <div class="flex items-center gap-4 relative">

<button onclick="toggleDarkMode()" class="w-10 h-10 rounded-full bg-white/10 border border-[var(--border-color)] text-[var(--text-muted)] hover:text-[var(--deep-forest)] hover:bg-[var(--light-sage)]/30 transition-all flex items-center justify-center group mr-2" title="Toggle Dark Mode">
    <span class="material-symbols-outlined group-hover:rotate-180 transition-transform duration-500" id="dark-mode-icon">dark_mode</span>
</button>


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
                        <?php if($total_notif > 0): ?><span class="text-[10px] bg-red-100 text-red-600 px-2 py-1 rounded-full font-bold"><?= $total_notif ?> Baru</span><?php endif; ?>
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

                        <?php if($total_notif == 0): ?><div class="text-center py-6 text-gray-400 text-xs italic">Tidak ada notifikasi baru.</div><?php endif; ?>
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

        <div class="bg-white rounded-[2.5rem] p-8 lg:p-10 border border-[var(--border-color)] card-shadow relative overflow-hidden" data-aos="fade-up">

            <div class="flex flex-col lg:flex-row gap-10">

                <div class="lg:w-4/12 flex flex-col gap-4">
                    <div class="aspect-[3/4] rounded-3xl overflow-hidden bg-stone-100 border border-[var(--border-color)] shadow-inner relative group">
                        <img src="<?= $img_src ?>" alt="<?= $book['title'] ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/10 to-transparent"></div>
                    </div>

                    <div class="text-center">
                         <span class="px-4 py-2 bg-[var(--light-sage)]/30 text-[var(--deep-forest)] rounded-full text-xs font-bold uppercase tracking-widest border border-[var(--light-sage)]">
                            <?= $book['category_name'] ?>
                        </span>
                    </div>
                </div>

                <div class="lg:w-8/12 flex flex-col">
                    <div class="mb-6">
                        <h1 class="text-3xl lg:text-4xl font-bold text-[var(--text-dark)] leading-tight mb-2 title-font"><?= $book['title'] ?></h1>

                        <div class="flex items-center gap-2 text-[var(--text-muted)] text-sm mb-2">
                            <span class="material-symbols-outlined text-lg">person</span>
                            <span class="font-semibold">Penulis:</span>
                            <span class="text-[var(--deep-forest)] font-bold"><?= $author_name ?></span>
                        </div>

                        <p class="text-sm text-[var(--text-muted)] flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">calendar_today</span> Diunggah pada <?= date('d M Y', strtotime($book['created_at'])) ?>
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                        <div class="p-5 rounded-3xl bg-[var(--cream-bg)] border border-[var(--border-color)] relative overflow-hidden">
                            <div class="absolute -right-4 -top-4 w-16 h-16 bg-[var(--warm-tan)]/10 rounded-full blur-xl"></div>
                            <p class="text-[10px] font-bold text-stone-500 uppercase tracking-widest mb-1">Harga Jual</p>
                            <p class="text-2xl font-bold text-[var(--chocolate-brown)]">Rp <?= number_format($book['sell_price'], 0, ',', '.') ?></p>
                        </div>

                        <div class="p-5 rounded-3xl bg-white border border-[var(--border-color)]">
                            <p class="text-[10px] font-bold text-stone-500 uppercase tracking-widest mb-1">Harga Modal</p>
                            <p class="text-xl font-bold text-stone-600">Rp <?= number_format($book['cost_price'], 0, ',', '.') ?></p>
                        </div>

                        <div class="p-5 rounded-3xl bg-green-50 border border-green-100">
                            <p class="text-[10px] font-bold text-green-600 uppercase tracking-widest mb-1">Margin Profit</p>
                            <p class="text-xl font-bold text-green-700 flex items-center gap-1">
                                + Rp <?= number_format($profit, 0, ',', '.') ?>
                            </p>
                        </div>
                    </div>

                    <div class="mb-8 p-4 rounded-2xl border border-dashed border-[var(--border-color)] flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-[var(--deep-forest)] flex items-center justify-center text-white">
                                <span class="material-symbols-outlined">inventory_2</span>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-stone-500 uppercase">Stok Tersedia</p>
                                <p class="text-lg font-bold text-[var(--text-dark)]"><?= $book['stock'] ?> Eksemplar</p>
                            </div>
                        </div>
                        <?php if($book['stock'] <= 5): ?>
                            <span class="px-3 py-1 bg-red-100 text-red-600 text-xs font-bold rounded-full animate-pulse">Stok Menipis!</span>
                        <?php endif; ?>
                    </div>

                    <div class="mb-8">
                        <h3 class="text-sm font-bold text-[var(--text-dark)] uppercase mb-3 border-b border-[var(--border-color)] pb-2">Deskripsi Buku</h3>
                        <div class="prose prose-stone text-sm text-stone-600 max-w-none leading-relaxed whitespace-pre-line">
                            <?= $book['description'] ?>
                        </div>
                    </div>

                    <div class="mt-auto flex gap-4">
                        <a href="products.php" class="px-8 py-3 bg-stone-100 text-stone-500 font-bold rounded-2xl hover:bg-stone-200 transition-all text-sm flex items-center gap-2">
                            <span class="material-symbols-outlined">arrow_back</span> Kembali
                        </a>
                        </div>
                </div>

            </div>
        </div>

    </main>
</div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({ once: true, duration: 800, offset: 50 });

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
</script>

</body>
</html>