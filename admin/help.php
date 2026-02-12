<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../config/db.php';

// Cek Keamanan: Harus Login & Harus Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'];

// --- 1. AMBIL FOTO PROFIL ADMIN (Agar Navbar Sinkron) ---
$query_admin_profile = mysqli_query($conn, "SELECT profile_image FROM users WHERE id = '$admin_id'");
$data_admin = mysqli_fetch_assoc($query_admin_profile);
$profile_pic = !empty($data_admin['profile_image']) ? "../assets/uploads/profiles/" . $data_admin['profile_image'] : "../assets/images/default_profile.png";

// --- DATA FAQ ---
$faqs = [
    [
        "question" => "Bagaimana cara memblokir pengguna?",
        "answer" => "Masuk ke menu 'Kelola User', cari nama pengguna, lalu klik tombol Hapus/Blokir."
    ],
    [
        "question" => "Bagaimana cara menambah Kategori Buku?",
        "answer" => "Pergi ke menu 'Kategori Buku', isi nama kategori baru pada form yang tersedia, lalu klik Simpan."
    ],
    [
        "question" => "Apakah saya bisa melihat password pengguna?",
        "answer" => "Tidak. Demi keamanan privasi, password pengguna terenkripsi dan tidak bisa dilihat oleh Admin."
    ],
    [
        "question" => "Bagaimana jika ada transaksi bermasalah?",
        "answer" => "Admin dapat menengahi chat antara Penjual dan Pembeli, atau membatalkan pesanan melalui database jika diperlukan."
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Bantuan Admin - Libraria</title>

<script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
<script src="../assets/js/theme-config.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
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
    body {
        font-family: 'Quicksand', sans-serif;
    }
    .title-font { font-weight: 700; }

    .card-shadow {
        box-shadow: 0 10px 40px -10px rgba(62, 75, 28, 0.08);
    }

    /* Sidebar Active State */
    .sidebar-active {
        background-color: var(--sidebar-active);
        color: white;
        box-shadow: 0 4px 12px rgba(62, 75, 28, 0.3);
    }

    #sidebar, #main-content, #sidebar-logo, .sidebar-text-wrapper, .menu-text {
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* FAQ Animation */
    .faq-content { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
    .faq-open .faq-content { max-height: 500px; }
    .faq-open .icon-rotate { transform: rotate(180deg); }

    /* Sidebar Logic same as Index */
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
                <h1 class="text-2xl font-bold text-[var(--deep-forest)] dark:text-[var(--warm-tan)] tracking-tight title-font leading-none">LIBRARIA</h1>
                <p class="text-xs font-bold tracking-[0.2em] text-[var(--warm-tan)] mt-1 uppercase">Admin Panel</p>
            </div>
        </div>

        <nav class="flex-1 px-3 space-y-2 mt-6 overflow-y-auto overflow-x-hidden">
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 dark:text-stone-400 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] dark:hover:text-[var(--warm-tan)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">dashboard</span>
                <span class="font-medium menu-text whitespace-nowrap">Dashboard</span>
            </a>

            <a href="manage_users.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 dark:text-stone-400 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] dark:hover:text-[var(--warm-tan)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">group</span>
                <span class="font-medium menu-text whitespace-nowrap">Kelola User</span>
            </a>

            <a href="categories.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 dark:text-stone-400 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] dark:hover:text-[var(--warm-tan)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">category</span>
                <span class="font-medium menu-text whitespace-nowrap">Kategori Buku</span>
            </a>

            <a href="help.php" class="flex items-center gap-3 px-4 py-3 sidebar-active rounded-2xl transition-all group shadow-md shadow-green-900/10">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">help</span>
                <span class="font-semibold menu-text whitespace-nowrap">Bantuan</span>
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

        <header class="flex justify-between items-center mb-10 bg-white/50 dark:bg-stone-800/50 backdrop-blur-sm p-4 rounded-3xl border border-[var(--border-color)] dark:border-stone-700 sticky top-4 z-20 shadow-sm" data-aos="fade-down">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl hover:bg-[var(--light-sage)] text-[var(--deep-forest)] dark:text-[var(--warm-tan)] transition-colors focus:outline-none">
                    <span class="material-symbols-outlined">menu_open</span>
                </button>

                <div>
                    <h2 class="text-xl lg:text-2xl title-font text-[var(--text-dark)] dark:text-stone-200 hidden md:block">Pusat Bantuan</h2>
                </div>
            </div>

            <div class="flex items-center gap-4 relative">
                <button onclick="toggleDarkMode()" class="w-10 h-10 rounded-full bg-white dark:bg-stone-700 border border-[var(--border-color)] dark:border-stone-600 flex items-center justify-center text-[var(--deep-forest)] dark:text-[var(--warm-tan)] hover:text-[var(--deep-forest)] hover:shadow-md transition-all">
                    <span class="material-symbols-outlined" id="dark-mode-icon">dark_mode</span>
                </button>

                <button onclick="toggleProfileDropdown()" class="flex items-center gap-3 bg-white dark:bg-stone-700 p-1.5 pr-4 rounded-full border border-[var(--border-color)] dark:border-stone-600 card-shadow hover:shadow-md transition-all focus:outline-none">
                    <img src="<?= $profile_pic ?>" alt="Admin Profile" class="w-9 h-9 rounded-full object-cover border-2 border-[var(--cream-bg)] dark:border-stone-600">
                    <div class="text-left hidden sm:block">
                        <p class="text-xs font-bold leading-none title-font text-[var(--text-dark)] dark:text-stone-200"><?= $admin_name ?></p>
                        <p class="text-[10px] text-[var(--warm-tan)] leading-none mt-1 font-bold uppercase">Super Admin</p>
                    </div>
                    <span class="material-symbols-outlined text-[18px] text-[var(--text-muted)] dark:text-stone-400">expand_more</span>
                </button>

                <div id="profileDropdown" class="absolute right-0 top-14 w-56 bg-white dark:bg-stone-800 rounded-2xl shadow-xl border border-[var(--border-color)] dark:border-stone-700 py-2 hidden transform origin-top-right transition-all z-50">
                    <div class="px-4 py-2 border-b border-gray-100 dark:border-stone-700">
                        <p class="text-xs text-gray-500 dark:text-stone-400">Signed in as</p>
                        <p class="text-sm font-bold text-[var(--deep-forest)] dark:text-[var(--warm-tan)] truncate"><?= $admin_name ?></p>
                    </div>
                    <a href="profileadmin.php" class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700 dark:text-stone-300 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] dark:hover:text-[var(--warm-tan)] transition-colors">
                        <span class="material-symbols-outlined text-[20px]">person</span>
                        My Profile
                    </a>
                    <div class="border-t border-gray-100 dark:border-stone-700 my-1"></div>
                    <a href="../auth/logout.php" class="flex items-center gap-2 px-4 py-3 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                        <span class="material-symbols-outlined text-[20px]">logout</span>
                        Log Out
                    </a>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8" data-aos="fade-up">

            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-stone-800 rounded-[2.5rem] p-8 border border-[var(--border-color)] dark:border-stone-700 card-shadow">
                    <h3 class="text-xl font-bold text-[var(--deep-forest)] dark:text-[var(--warm-tan)] mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined">quiz</span> Pertanyaan Umum
                    </h3>

                    <div class="space-y-3">
                        <?php foreach($faqs as $index => $faq): ?>
                        <div class="faq-item border border-[var(--border-color)] dark:border-stone-700 rounded-2xl overflow-hidden bg-[var(--cream-bg)]/30 dark:bg-stone-700/30">
                            <button onclick="toggleFaq(this)" class="w-full flex justify-between items-center p-5 text-left focus:outline-none hover:bg-[var(--light-sage)]/20 dark:hover:bg-stone-600 transition-colors">
                                <span class="font-bold text-[var(--text-dark)] dark:text-stone-200 text-sm"><?= $faq['question'] ?></span>
                                <span class="material-symbols-outlined text-[var(--text-muted)] dark:text-stone-400 icon-rotate transition-transform duration-300">expand_more</span>
                            </button>
                            <div class="faq-content">
                                <div class="px-5 pb-5 pt-0 text-sm text-[var(--text-muted)] dark:text-stone-300 leading-relaxed border-t border-dashed border-[var(--border-color)] dark:border-stone-600 bg-white dark:bg-stone-800 p-4">
                                    <?= $faq['answer'] ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-[var(--deep-forest)] text-white rounded-[2.5rem] p-8 shadow-xl relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full blur-3xl -mr-10 -mt-10"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-[var(--light-sage)]/20 rounded-full blur-2xl -ml-5 -mb-5"></div>

                    <div class="relative z-10">
                        <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center mb-4 backdrop-blur-sm">
                            <span class="material-symbols-outlined text-2xl">code</span>
                        </div>
                        <h3 class="text-xl font-bold mb-2 title-font">Kontak Developer</h3>
                        <p class="text-white/80 text-sm mb-6 leading-relaxed font-light">
                            Jika terjadi error sistem (bug) atau membutuhkan fitur tambahan yang kompleks, silakan hubungi tim pengembang.
                        </p>

                        <div class="space-y-3">
                            <a href="mailto:dev@libraria.com" class="flex items-center gap-3 bg-black/20 p-3 rounded-2xl hover:bg-black/30 transition-colors border border-white/10">
                                <span class="material-symbols-outlined text-sm">mail</span>
                                <span class="text-xs font-bold">dev@libraria.com</span>
                            </a>
                            <a href="#" class="flex items-center gap-3 bg-black/20 p-3 rounded-2xl hover:bg-black/30 transition-colors border border-white/10">
                                <span class="material-symbols-outlined text-sm">call</span>
                                <span class="text-xs font-bold">+62 812-3456-7890</span>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-stone-800 rounded-[2.5rem] p-6 border border-[var(--border-color)] dark:border-stone-700 card-shadow text-center">
                    <span class="material-symbols-outlined text-4xl text-[var(--light-sage)] mb-2">security</span>
                    <h4 class="font-bold text-[var(--deep-forest)] dark:text-[var(--warm-tan)] mb-1">Keamanan Data</h4>
                    <p class="text-xs text-[var(--text-muted)] dark:text-stone-400">Pastikan selalu Log Out setelah selesai mengelola sistem.</p>
                </div>
            </div>

        </div>

        <footer class="mt-12 text-center text-[var(--text-muted)] dark:text-stone-500 text-xs body-font pb-4">
            <p>© 2026 Sari Anggrek Bookstore Management.</p>
        </footer>
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
            sidebar.classList.remove('w-64');
            sidebar.classList.add('w-20');
            sidebar.classList.add('sidebar-collapsed');
            mainDiv.classList.remove('ml-64');
            mainDiv.classList.add('ml-20');
        } else {
            sidebar.classList.remove('w-20');
            sidebar.classList.remove('sidebar-collapsed');
            sidebar.classList.add('w-64');
            mainDiv.classList.remove('ml-20');
            mainDiv.classList.add('ml-64');
        }
        isSidebarOpen = !isSidebarOpen;
    }

    function toggleProfileDropdown() {
        const dropdown = document.getElementById('profileDropdown');
        if (dropdown.classList.contains('hidden')) {
            dropdown.classList.remove('hidden');
            setTimeout(() => {
                dropdown.classList.remove('opacity-0', 'scale-95');
                dropdown.classList.add('opacity-100', 'scale-100');
            }, 10);
        } else {
            dropdown.classList.remove('opacity-100', 'scale-100');
            dropdown.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                dropdown.classList.add('hidden');
            }, 150);
        }
    }

    window.addEventListener('click', function(e) {
        const button = document.querySelector('button[onclick="toggleProfileDropdown()"]');
        const dropdown = document.getElementById('profileDropdown');

        if (button && dropdown && !button.contains(e.target) && !dropdown.contains(e.target)) {
            if (!dropdown.classList.contains('hidden')) {
                dropdown.classList.remove('opacity-100', 'scale-100');
                dropdown.classList.add('opacity-0', 'scale-95');
                setTimeout(() => {
                    dropdown.classList.add('hidden');
                }, 150);
            }
        }
    });

    // Accordion Logic
    function toggleFaq(button) {
        const item = button.parentElement;
        const isActive = item.classList.contains('faq-open');

        // Close all others (optional)
        document.querySelectorAll('.faq-item').forEach(el => {
            el.classList.remove('faq-open');
        });

        if (!isActive) {
            item.classList.add('faq-open');
        }
    }
</script>

</body>
</html>