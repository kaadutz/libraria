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
<title>Bantuan Admin - Libraria</title>

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
                <p class="text-[10px] font-bold tracking-[0.2em] text-tan mt-1 uppercase">Admin Panel</p>
            </div>
        </div>

        <nav class="flex-1 px-4 space-y-2 mt-6 overflow-y-auto overflow-x-hidden">
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">dashboard</span>
                <span class="menu-text whitespace-nowrap">Dashboard</span>
            </a>

            <a href="manage_users.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">group</span>
                <span class="menu-text whitespace-nowrap">Kelola User</span>
            </a>

            <a href="categories.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">category</span>
                <span class="menu-text whitespace-nowrap">Kategori Buku</span>
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

        <header class="flex justify-between items-center mb-10 bg-white/50 backdrop-blur-sm p-4 rounded-3xl border border-tan/20 dark:border-stone-800 sticky top-4 z-20 shadow-sm" data-aos="fade-down">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl hover:bg-sage text-primary dark:text-sage transition-colors focus:outline-none">
                    <span class="material-symbols-outlined">menu_open</span>
                </button>

                <div>
                    <h2 class="text-xl lg:text-2xl title-font text-stone-800 dark:text-stone-200 hidden md:block">Pusat Bantuan</h2>
                </div>
            </div>

            <div class="flex items-center gap-4 relative">

            <div class="flex items-center gap-4 relative">
                <!-- DARK MODE TOGGLE -->
                <button onclick="toggleDarkMode()" class="w-10 h-10 flex items-center justify-center rounded-full text-stone-500 dark:text-stone-400 hover:bg-primary hover:text-white dark:hover:bg-stone-800 transition-all duration-300">
                    <span class="material-icons-outlined text-xl">dark_mode</span>
                </button>

<button onclick="toggleProfileDropdown()" class="flex items-center gap-3 bg-white p-1.5 pr-4 rounded-full border border-tan/20 dark:border-stone-800 card-shadow hover:shadow-md transition-all focus:outline-none">
                    <img src="<?= $profile_pic ?>" alt="Admin Profile" class="w-9 h-9 rounded-full object-cover border-2 border-[var(--cream-bg)]">
                    <div class="text-left hidden sm:block">
                        <p class="text-xs font-bold leading-none title-font"><?= $admin_name ?></p>
                        <p class="text-[10px] text-tan leading-none mt-1 font-bold uppercase">Super Admin</p>
                    </div>
                    <span class="material-symbols-outlined text-[18px] text-stone-500 dark:text-stone-400">expand_more</span>
                </button>

                <div id="profileDropdown" class="absolute right-0 top-14 w-56 bg-white rounded-2xl shadow-xl border border-tan/20 dark:border-stone-800 py-2 hidden transform origin-top-right transition-all z-50">
                    <div class="px-4 py-2 border-b border-gray-100">
                        <p class="text-xs text-gray-500">Signed in as</p>
                        <p class="text-sm font-bold text-primary dark:text-sage truncate"><?= $admin_name ?></p>
                    </div>
                    <a href="profileadmin.php" class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700 hover:bg-sage/30 hover:text-primary dark:text-sage transition-colors">
                        <span class="material-symbols-outlined text-[20px]">person</span>
                        My Profile
                    </a>
                    <div class="border-t border-gray-100 my-1"></div>
                    <a href="../auth/logout.php" class="flex items-center gap-2 px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors">
                        <span class="material-symbols-outlined text-[20px]">logout</span>
                        Log Out
                    </a>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8" data-aos="fade-up">

            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-[2.5rem] p-8 border border-tan/20 dark:border-stone-800 card-shadow">
                    <h3 class="text-xl font-bold text-primary dark:text-sage mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined">quiz</span> Pertanyaan Umum
                    </h3>

                    <div class="space-y-3">
                        <?php foreach($faqs as $index => $faq): ?>
                        <div class="faq-item border border-tan/20 dark:border-stone-800 rounded-2xl overflow-hidden bg-cream dark:bg-stone-800/30">
                            <button onclick="toggleFaq(this)" class="w-full flex justify-between items-center p-5 text-left focus:outline-none hover:bg-sage/20 transition-colors">
                                <span class="font-bold text-stone-800 dark:text-stone-200 text-sm"><?= $faq['question'] ?></span>
                                <span class="material-symbols-outlined text-stone-500 dark:text-stone-400 icon-rotate transition-transform duration-300">expand_more</span>
                            </button>
                            <div class="faq-content">
                                <div class="px-5 pb-5 pt-0 text-sm text-stone-500 dark:text-stone-400 leading-relaxed border-t border-dashed border-tan/20 dark:border-stone-800 bg-white p-4">
                                    <?= $faq['answer'] ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-primary text-white rounded-[2.5rem] p-8 shadow-xl relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full blur-3xl -mr-10 -mt-10"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-sage/20 rounded-full blur-2xl -ml-5 -mb-5"></div>

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

                <div class="bg-white rounded-[2.5rem] p-6 border border-tan/20 dark:border-stone-800 card-shadow text-center">
                    <span class="material-symbols-outlined text-4xl text-sage mb-2">security</span>
                    <h4 class="font-bold text-primary dark:text-sage mb-1">Keamanan Data</h4>
                    <p class="text-xs text-stone-500 dark:text-stone-400">Pastikan selalu Log Out setelah selesai mengelola sistem.</p>
                </div>
            </div>

        </div>

        <footer class="mt-12 text-center text-stone-500 dark:text-stone-400 text-xs body-font pb-4">
            <p>© 2026 Sari Anggrek Bookstore Management.</p>
        </footer>
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
            sidebar.classList.remove('w-64');
            sidebar.classList.add('w-20', 'sidebar-collapsed');
            mainDiv.classList.remove('ml-64');
            mainDiv.classList.add('ml-20');
        } else {
            sidebar.classList.remove('w-20', 'sidebar-collapsed');
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