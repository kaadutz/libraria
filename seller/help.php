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
$query_orders = mysqli_query($conn, "SELECT COUNT(DISTINCT o.id) as total FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE oi.seller_id = '$seller_id' AND (o.status = 'pending' OR o.status = 'waiting_approval')");
$total_new_orders = mysqli_fetch_assoc($query_orders)['total'];

$query_unread = mysqli_query($conn, "SELECT COUNT(*) as total FROM messages WHERE receiver_id = '$seller_id' AND is_read = 0");
$total_unread_chat = mysqli_fetch_assoc($query_unread)['total'];

$total_notif = $total_new_orders + $total_unread_chat;

// --- 2. AMBIL DATA FAQ (Target: Seller & All) ---
$query_faq = mysqli_query($conn, "SELECT * FROM qna WHERE target IN ('seller', 'all') ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
<title>Pusat Bantuan - Libraria Seller</title>

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
                <div><h2 class="text-xl lg:text-2xl title-font text-stone-800 dark:text-stone-200 hidden md:block">Pusat Bantuan</h2></div>
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
                        <?php if($total_notif > 0): ?><span class="text-[10px] bg-red-100 text-red-600 px-2 py-1 rounded-full font-bold"><?= $total_notif ?> Baru</span><?php endif; ?>
                    </div>
                    <div class="max-h-64 overflow-y-auto">
                        <?php if($total_new_orders > 0): ?>
                        <a href="orders.php" class="flex items-start gap-3 px-4 py-3 hover:bg-cream dark:bg-stone-800 transition-colors border-b border-gray-50">
                            <div class="p-2 bg-orange-100 text-orange-600 rounded-full"><span class="material-symbols-outlined text-lg">shopping_bag</span></div>
                            <div>
                                <p class="text-sm font-bold text-gray-800">Pesanan Baru!</p>
                                <p class="text-xs text-gray-500">Ada <?= $total_new_orders ?> pesanan menunggu konfirmasi.</p>
                            </div>
                        </a>
                        <?php endif; ?>
                        <?php if($total_unread_chat > 0): ?>
                        <a href="chat.php" class="flex items-start gap-3 px-4 py-3 hover:bg-cream dark:bg-stone-800 transition-colors border-b border-gray-50">
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8" data-aos="fade-up">

            <div class="lg:col-span-2 space-y-6">
                <div class="bg-primary text-white p-8 rounded-[2.5rem] shadow-xl mb-8 relative overflow-hidden">
                    <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
                    <h3 class="text-2xl font-bold mb-2 title-font">Halo, Seller! 👋</h3>
                    <p class="text-white/80 text-sm">Temukan solusi untuk mengelola tokomu dengan lebih baik di sini.</p>
                </div>

                <h3 class="text-lg font-bold text-stone-800 dark:text-stone-200 mb-4 px-2">Pertanyaan Sering Diajukan</h3>

                <?php if(mysqli_num_rows($query_faq) > 0): ?>
                    <?php while($faq = mysqli_fetch_assoc($query_faq)): ?>
                    <div class="faq-item bg-white rounded-2xl border border-tan/20 dark:border-stone-800 overflow-hidden cursor-pointer group hover:shadow-md transition-all" onclick="toggleFaq(this)">
                        <div class="flex justify-between items-center p-5 bg-white">
                            <h4 class="font-bold text-primary dark:text-sage text-sm"><?= htmlspecialchars($faq['question']) ?></h4>
                            <span class="material-symbols-outlined text-stone-500 dark:text-stone-400 icon-rotator transition-transform duration-300">expand_more</span>
                        </div>
                        <div class="faq-content bg-cream dark:bg-stone-800/30 text-stone-500 dark:text-stone-400 text-sm px-5">
                            <div class="pb-5 pt-0 leading-relaxed">
                                <?= nl2br(htmlspecialchars($faq['answer'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="p-8 text-center bg-white rounded-2xl border border-dashed border-tan/20 dark:border-stone-800">
                        <p class="text-stone-500">Belum ada data FAQ.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="space-y-6">
                <div class="bg-white p-6 rounded-[2rem] border border-tan/20 dark:border-stone-800 card-shadow sticky top-32">
                    <h4 class="font-bold text-primary dark:text-sage mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined">support_agent</span> Kontak Admin
                    </h4>
                    <p class="text-xs text-stone-500 dark:text-stone-400 mb-6">Butuh bantuan lebih lanjut terkait teknis atau penarikan dana?</p>

                    <a href="https://wa.me/628123456789" target="_blank" class="flex items-center gap-3 bg-cream dark:bg-stone-800 p-3 rounded-xl mb-3 hover:bg-sage transition-colors">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg" class="w-6 h-6">
                        <span class="text-sm font-bold text-stone-800 dark:text-stone-200">+62 812-3456-7890</span>
                    </a>

                    <a href="mailto:support@libraria.com" class="flex items-center gap-3 bg-cream dark:bg-stone-800 p-3 rounded-xl hover:bg-sage transition-colors">
                        <span class="material-symbols-outlined text-chocolate dark:text-tan">mail</span>
                        <span class="text-sm font-bold text-stone-800 dark:text-stone-200">support@libraria.com</span>
                    </a>
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

    // Accordion Logic
    function toggleFaq(element) {
        const allItems = document.querySelectorAll('.faq-item');
        allItems.forEach(item => {
            if (item !== element) {
                item.classList.remove('active');
            }
        });
        element.classList.toggle('active');
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