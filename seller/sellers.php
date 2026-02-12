<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../config/db.php';

// Cek Keamanan
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller') {
    header("Location: ../auth/login.php");
    exit;
}

$seller_id = $_SESSION['user_id'];
$seller_name = $_SESSION['full_name'];

// --- 1. LOGIKA NOTIFIKASI ---
include 'includes/notification_logic.php';

// --- 2. AMBIL DATA PENJUAL LAIN ---
$query_sellers = mysqli_query($conn, "SELECT * FROM users WHERE role = 'seller' AND id != '$seller_id'");
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Daftar Penjual - Libraria Seller</title>

<script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
<script src="../assets/js/theme-config.js"></script>
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
    body { font-family: 'Quicksand', sans-serif; }
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
<body class="bg-background-light dark:bg-background-dark text-stone-800 dark:text-stone-200 overflow-x-hidden transition-colors duration-300">

<div class="flex min-h-screen">

    <aside id="sidebar" class="w-64 bg-white dark:bg-stone-900 border-r border-[var(--border-color)] dark:border-stone-700 flex flex-col fixed h-full z-30 overflow-hidden shadow-lg lg:shadow-none transition-colors duration-300">
        <div id="sidebar-header" class="h-28 flex items-center border-b border-[var(--border-color)] dark:border-stone-700 shrink-0">
            <img id="sidebar-logo" src="../assets/images/logo.png" alt="Libraria Logo" class="object-contain flex-shrink-0">
            <div class="sidebar-text-wrapper flex flex-col justify-center">
                <h1 class="text-2xl font-bold text-[var(--deep-forest)] dark:text-[var(--warm-tan)] tracking-tight font-logo leading-none">LIBRARIA</h1>
                <p class="text-xs font-bold tracking-[0.2em] text-[var(--warm-tan)] mt-1 uppercase">Seller Panel</p>
            </div>
        </div>

        <nav class="flex-1 px-3 space-y-2 mt-6 overflow-y-auto overflow-x-hidden">
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 dark:text-stone-400 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] dark:hover:text-[var(--warm-tan)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">dashboard</span>
                <span class="font-medium menu-text whitespace-nowrap">Dashboard</span>
            </a>

            <a href="categories.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 dark:text-stone-400 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] dark:hover:text-[var(--warm-tan)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">category</span>
                <span class="font-medium menu-text whitespace-nowrap">Kategori</span>
            </a>

            <a href="products.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 dark:text-stone-400 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] dark:hover:text-[var(--warm-tan)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">inventory_2</span>
                <span class="font-medium menu-text whitespace-nowrap">Produk Saya</span>
            </a>

            <a href="orders.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 dark:text-stone-400 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] dark:hover:text-[var(--warm-tan)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">shopping_cart_checkout</span>
                <span class="font-medium menu-text whitespace-nowrap">Pesanan Masuk</span>
                <?php if($total_new_orders > 0): ?>
                <span class="ml-auto bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full menu-text animate-pulse"><?= $total_new_orders ?></span>
                <?php endif; ?>
            </a>

            <a href="reports.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 dark:text-stone-400 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] dark:hover:text-[var(--warm-tan)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">bar_chart</span>
                <span class="font-medium menu-text whitespace-nowrap">Laporan</span>
            </a>

            <a href="chat.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 dark:text-stone-400 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] dark:hover:text-[var(--warm-tan)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">chat_bubble</span>
                <span class="font-medium menu-text whitespace-nowrap">Chat</span>
                <?php if($total_unread_chat > 0): ?>
                <span class="ml-auto bg-blue-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full menu-text"><?= $total_unread_chat ?></span>
                <?php endif; ?>
            </a>

            <a href="help.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 dark:text-stone-400 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] dark:hover:text-[var(--warm-tan)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">help</span>
                <span class="font-medium menu-text whitespace-nowrap">Bantuan</span>
            </a>

            <a href="sellers.php" class="flex items-center gap-3 px-4 py-3 sidebar-active rounded-2xl transition-all group shadow-md shadow-green-900/10">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">storefront</span>
                <span class="font-semibold menu-text whitespace-nowrap">Daftar Penjual</span>
            </a>
        </nav>

        <div class="p-3 border-t border-[var(--border-color)] dark:border-stone-700">
            <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-3 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-2xl transition-colors group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">logout</span>
                <span class="font-medium menu-text whitespace-nowrap">Sign Out</span>
            </a>
        </div>
    </aside>

    <main id="main-content" class="flex-1 ml-64 p-4 lg:p-8 transition-all duration-300">
        
        <header class="flex justify-between items-center mb-8 bg-white/50 dark:bg-stone-800/50 backdrop-blur-sm p-4 rounded-3xl border border-[var(--border-color)] dark:border-stone-700 sticky top-4 z-20 shadow-sm" data-aos="fade-down">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl hover:bg-[var(--light-sage)] text-[var(--deep-forest)] dark:text-[var(--warm-tan)] transition-colors focus:outline-none">
                    <span class="material-symbols-outlined">menu_open</span>
                </button>
                <div><h2 class="text-xl lg:text-2xl title-font text-[var(--text-dark)] dark:text-stone-200 hidden md:block">Daftar Penjual</h2></div>
            </div>
            
            <div class="flex items-center gap-4 relative">
                
                <button onclick="toggleDarkMode()" class="w-10 h-10 rounded-full bg-white dark:bg-stone-700 border border-[var(--border-color)] dark:border-stone-600 flex items-center justify-center text-[var(--deep-forest)] dark:text-[var(--warm-tan)] hover:text-[var(--deep-forest)] hover:shadow-md transition-all">
                    <span class="material-symbols-outlined" id="dark-mode-icon">dark_mode</span>
                </button>

                <button onclick="toggleDropdown('notificationDropdown')" class="w-10 h-10 rounded-full bg-white dark:bg-stone-700 border border-[var(--border-color)] dark:border-stone-600 flex items-center justify-center text-[var(--text-muted)] dark:text-stone-400 hover:text-[var(--deep-forest)] hover:shadow-md transition-all relative">
                    <span class="material-symbols-outlined">notifications</span>
                    <?php if($total_notif > 0): ?>
                        <span class="absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full border-2 border-white dark:border-stone-700 animate-ping"></span>
                        <span class="absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full border-2 border-white dark:border-stone-700"></span>
                    <?php endif; ?>
                </button>

                <div id="notificationDropdown" class="absolute right-16 top-14 w-80 bg-white dark:bg-stone-800 rounded-2xl shadow-xl border border-[var(--border-color)] dark:border-stone-700 py-2 hidden transform origin-top-right transition-all z-50">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-stone-700 flex justify-between items-center">
                        <h4 class="font-bold text-[var(--deep-forest)] dark:text-[var(--warm-tan)]">Notifikasi</h4>
                        <?php if($total_notif > 0): ?>
                            <span class="text-[10px] bg-red-100 text-red-600 px-2 py-1 rounded-full font-bold"><?= $total_notif ?> Baru</span>
                        <?php endif; ?>
                    </div>
                    <div class="max-h-64 overflow-y-auto custom-scroll">
                        <?php if(!empty($notif_list)): foreach($notif_list as $n): ?>
                            <a href="<?= $n['link'] ?>" class="flex items-start gap-3 px-4 py-3 hover:bg-[var(--cream-bg)] dark:hover:bg-stone-700 transition-colors border-b border-gray-50 dark:border-stone-700 last:border-0">
                                <div class="p-2 bg-<?= $n['color'] ?>-100 text-<?= $n['color'] ?>-600 rounded-full"><span class="material-symbols-outlined text-lg"><?= $n['icon'] ?></span></div>
                                <div><p class="text-sm font-bold text-gray-800 dark:text-stone-200"><?= $n['title'] ?></p><p class="text-xs text-gray-500 dark:text-stone-400"><?= $n['text'] ?></p></div>
                            </a>
                        <?php endforeach; else: ?>
                            <div class="text-center py-6 text-gray-400 text-xs italic">Tidak ada notifikasi baru.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <button onclick="toggleDropdown('profileDropdown')" class="flex items-center gap-3 bg-white dark:bg-stone-700 p-1.5 pr-4 rounded-full border border-[var(--border-color)] dark:border-stone-600 card-shadow hover:shadow-md transition-all focus:outline-none">
                    <div class="w-9 h-9 rounded-full bg-[var(--warm-tan)] text-white flex items-center justify-center font-bold text-sm border-2 border-[var(--cream-bg)] dark:border-stone-600"><?= strtoupper(substr($seller_name, 0, 1)) ?></div>
                    <div class="text-left hidden sm:block">
                        <p class="text-xs font-bold leading-none title-font text-[var(--text-dark)] dark:text-stone-200"><?= $seller_name ?></p>
                        <p class="text-[10px] text-[var(--text-muted)] dark:text-stone-400 leading-none mt-1 font-bold uppercase">Seller</p>
                    </div>
                    <span class="material-symbols-outlined text-[18px] text-[var(--text-muted)] dark:text-stone-400">expand_more</span>
                </button>

                <div id="profileDropdown" class="absolute right-0 top-14 w-56 bg-white dark:bg-stone-800 rounded-2xl shadow-xl border border-[var(--border-color)] dark:border-stone-700 py-2 hidden transform origin-top-right transition-all z-50">
                    <a href="profile.php" class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700 dark:text-stone-300 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] dark:hover:text-[var(--warm-tan)] transition-colors bg-[var(--light-sage)]/20 font-bold"><span class="material-symbols-outlined text-[20px]">store</span> Profil Toko</a>
                    <div class="border-t border-gray-100 dark:border-stone-700 my-1"></div>
                    <a href="../auth/logout.php" class="flex items-center gap-2 px-4 py-3 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"><span class="material-symbols-outlined text-[20px]">logout</span> Log Out</a>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" data-aos="fade-up">
            <?php if(mysqli_num_rows($query_sellers) > 0): ?>
                <?php while($s = mysqli_fetch_assoc($query_sellers)):
                    $s_img = !empty($s['profile_image']) ? "../assets/uploads/profiles/".$s['profile_image'] : "../assets/images/default_profile.png";
                ?>
                <div class="bg-white dark:bg-stone-800 p-6 rounded-[2rem] border border-[var(--border-color)] dark:border-stone-700 card-shadow flex flex-col items-center text-center hover:-translate-y-1 transition-transform group relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-20 bg-[var(--cream-bg)] dark:bg-stone-700"></div>

                    <div class="relative z-10 w-24 h-24 p-1 bg-white dark:bg-stone-800 rounded-full border-2 border-[var(--border-color)] dark:border-stone-600 mb-4">
                        <img src="<?= $s_img ?>" class="w-full h-full rounded-full object-cover">
                    </div>

                    <h3 class="text-lg font-bold text-[var(--text-dark)] dark:text-stone-200 mb-1"><?= $s['full_name'] ?></h3>
                    <p class="text-xs text-[var(--text-muted)] dark:text-stone-400 flex items-center gap-1 mb-4">
                        <span class="material-symbols-outlined text-sm">location_on</span> <?= !empty($s['address']) ? $s['address'] : 'Lokasi tidak tersedia' ?>
                    </p>

                    <a href="chat.php?uid=<?= $s['id'] ?>" class="px-6 py-2 bg-[var(--deep-forest)] text-white rounded-xl text-sm font-bold hover:bg-[var(--chocolate-brown)] transition-colors shadow-md w-full flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-sm">chat</span> Chat Penjual
                    </a>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full py-20 text-center">
                    <p class="text-stone-500">Belum ada penjual lain.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="../assets/js/theme-manager.js"></script>
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
