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

$admin_id = $_SESSION['user_id']; // Ambil ID Admin
$admin_name = $_SESSION['full_name'];

// --- 1. AMBIL FOTO PROFIL ADMIN ---
$query_admin_profile = mysqli_query($conn, "SELECT profile_image FROM users WHERE id = '$admin_id'");
$data_admin = mysqli_fetch_assoc($query_admin_profile);
$profile_pic = !empty($data_admin['profile_image']) ? "../assets/uploads/profiles/" . $data_admin['profile_image'] : "../assets/images/default_profile.png";

// --- 2. AMBIL DATA DASHBOARD ---
$count_sellers = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM users WHERE role = 'seller'"));
$count_buyers  = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM users WHERE role = 'buyer'"));
$total_users   = $count_sellers + $count_buyers;

$count_books   = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM books"));
$count_cats    = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM categories"));

// Ambil 3 User Terbaru
$recent_users_query = mysqli_query($conn, "SELECT full_name, role, last_activity, created_at FROM users WHERE role != 'admin' ORDER BY id DESC LIMIT 3");

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Superadmin Dashboard - Libraria</title>

<script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
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
        background-color: var(--cream-bg);
        color: var(--text-dark);
    }
    .title-font { font-weight: 700; }
    
    /* Grid Layout */
    .asymmetric-grid {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 1.5rem;
    }
    .card-shadow {
        box-shadow: 0 10px 40px -10px rgba(62, 75, 28, 0.08);
    }

    /* Sidebar Active State */
    .sidebar-active {
        background-color: var(--sidebar-active);
        color: white;
        box-shadow: 0 4px 12px rgba(62, 75, 28, 0.3);
    }
    
    /* Smooth Transitions Global */
    #sidebar, #main-content, #sidebar-logo, .sidebar-text-wrapper, .menu-text {
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* --- SIDEBAR LOGIC (FIXED LOGO SIZE) --- */
    
    /* 1. Header & Logo Sizing (Normal State) */
    #sidebar-header {
        justify-content: flex-start; 
        padding-left: 1.5rem; 
        padding-right: 1.5rem;
    }
    #sidebar-logo {
        height: 5rem; /* h-20 (80px) */
        width: auto;
    }

    /* 2. Text Wrapper (Normal) */
    .sidebar-text-wrapper {
        opacity: 1;
        width: auto;
        margin-left: 0.75rem; 
        overflow: hidden;
        white-space: nowrap;
    }
    .menu-text {
        opacity: 1;
        width: auto;
        display: inline-block;
    }

    /* --- COLLAPSED STATE OVERRIDES --- */
    .sidebar-collapsed #sidebar-header {
        justify-content: center !important; 
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    
    .sidebar-collapsed #sidebar-logo {
        height: 3.5rem !important; 
        width: auto;
        margin: 0 auto;
    }

    .sidebar-collapsed .sidebar-text-wrapper {
        opacity: 0 !important;
        width: 0 !important;
        margin-left: 0 !important;
        pointer-events: none;
    }

    .sidebar-collapsed .menu-text {
        opacity: 0 !important;
        width: 0 !important;
        display: none; 
    }
    
    .sidebar-collapsed nav a {
        justify-content: center;
        padding-left: 0;
        padding-right: 0;
    }
</style>
</head>
<body class="overflow-x-hidden">

<div class="flex min-h-screen">
    
    <aside id="sidebar" class="w-64 bg-white border-r border-[var(--border-color)] flex flex-col fixed h-full z-30 overflow-hidden shadow-lg lg:shadow-none">
        
        <div id="sidebar-header" class="h-28 flex items-center border-b border-[var(--border-color)] shrink-0">
            <img id="sidebar-logo" src="../assets/images/logo.png" alt="Libraria Logo" class="object-contain flex-shrink-0">
            <div class="sidebar-text-wrapper flex flex-col justify-center">
                <h1 class="text-2xl font-bold text-[var(--deep-forest)] tracking-tight title-font leading-none">LIBRARIA</h1>
                <p class="text-xs font-bold tracking-[0.2em] text-[var(--warm-tan)] mt-1 uppercase">Admin Panel</p>
            </div>
        </div>
        
        <nav class="flex-1 px-3 space-y-2 mt-6 overflow-y-auto overflow-x-hidden">
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 sidebar-active rounded-2xl transition-all group shadow-md shadow-green-900/10">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">dashboard</span>
                <span class="font-semibold menu-text whitespace-nowrap">Dashboard</span>
            </a>
            
            <a href="manage_users.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">group</span>
                <span class="font-medium menu-text whitespace-nowrap">Kelola User</span>
            </a>

            <a href="categories.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">category</span>
                <span class="font-medium menu-text whitespace-nowrap">Kategori Buku</span>
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
                
                <div>
                    <h2 class="text-xl lg:text-2xl title-font text-[var(--text-dark)] hidden md:block">Dashboard</h2>
                </div>
            </div>

            <div class="flex items-center gap-4 relative">
                <button onclick="toggleProfileDropdown()" class="flex items-center gap-3 bg-white p-1.5 pr-4 rounded-full border border-[var(--border-color)] card-shadow hover:shadow-md transition-all focus:outline-none">
                    
                    <img src="<?= $profile_pic ?>" alt="Admin Profile" class="w-9 h-9 rounded-full object-cover border-2 border-[var(--cream-bg)]">
                    
                    <div class="text-left hidden sm:block">
                        <p class="text-xs font-bold leading-none title-font"><?= $admin_name ?></p>
                        <p class="text-[10px] text-[var(--warm-tan)] leading-none mt-1 font-bold uppercase">Super Admin</p>
                    </div>
                    <span class="material-symbols-outlined text-[18px] text-[var(--text-muted)]">expand_more</span>
                </button>

                <div id="profileDropdown" class="absolute right-0 top-14 w-56 bg-white rounded-2xl shadow-xl border border-[var(--border-color)] py-2 hidden transform origin-top-right transition-all z-50">
                    <div class="px-4 py-2 border-b border-gray-100">
                        <p class="text-xs text-gray-500">Signed in as</p>
                        <p class="text-sm font-bold text-[var(--deep-forest)] truncate"><?= $admin_name ?></p>
                    </div>
                    <a href="profileadmin.php" class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] transition-colors">
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

        <div class="asymmetric-grid">
            
            <section class="col-span-12 lg:col-span-8 relative overflow-hidden bg-[var(--deep-forest)] rounded-[2.5rem] p-10 text-white flex items-center min-h-[300px] shadow-2xl shadow-[#3E4B1C]/20 group" data-aos="fade-up" data-aos-delay="100">
                <div class="relative z-10 max-w-lg transition-transform duration-500 group-hover:translate-x-2">
                    <h1 class="text-3xl lg:text-4xl font-bold mb-4 leading-tight title-font">Halo, <?= $admin_name ?>!</h1>
                    <p class="text-white/80 mb-8 text-lg body-font font-light">
                        Sistem berjalan lancar. Anda memiliki akses penuh untuk mengelola ekosistem Libraria.
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <a href="manage_users.php" class="px-6 py-3 bg-[var(--chocolate-brown)] text-white font-bold rounded-2xl hover:opacity-90 hover:shadow-lg transition-all title-font text-sm flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">group</span> Kelola User
                        </a>
                        <a href="categories.php" class="px-6 py-3 bg-white/10 backdrop-blur-md text-white font-bold rounded-2xl hover:bg-white/20 transition-all border border-white/30 title-font text-sm flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">category</span> Kategori
                        </a>
                    </div>
                </div>
                <div class="absolute right-[-20px] bottom-[-20px] opacity-10 pointer-events-none transition-transform duration-700 group-hover:scale-110">
                    <span class="material-symbols-outlined text-[300px]">admin_panel_settings</span>
                </div>
            </section>

            <div class="col-span-12 lg:col-span-4 flex flex-col gap-5">
                <div class="bg-white p-6 rounded-[2rem] border border-[var(--border-color)] card-shadow flex items-center gap-5 hover:-translate-y-1 transition-transform duration-300" data-aos="fade-left" data-aos-delay="200">
                    <div class="w-14 h-14 bg-[var(--light-sage)]/40 rounded-2xl flex items-center justify-center text-[var(--deep-forest)] shadow-inner">
                        <span class="material-symbols-outlined text-3xl">group</span>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-[var(--text-muted)] uppercase tracking-widest">Total Pengguna</p>
                        <h3 class="text-2xl font-bold title-font text-[var(--text-dark)]"><?= $total_users ?></h3>
                        <div class="flex gap-2 mt-1">
                            <span class="text-[10px] px-2 py-0.5 bg-green-100 text-green-700 rounded-full"><?= $count_sellers ?> Penjual</span>
                            <span class="text-[10px] px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full"><?= $count_buyers ?> Pembeli</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-[2rem] border border-[var(--border-color)] card-shadow flex items-center gap-5 hover:-translate-y-1 transition-transform duration-300" data-aos="fade-left" data-aos-delay="300">
                    <div class="w-14 h-14 bg-[var(--warm-tan)]/20 rounded-2xl flex items-center justify-center text-[var(--warm-tan)] shadow-inner">
                        <span class="material-symbols-outlined text-3xl">category</span>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-[var(--text-muted)] uppercase tracking-widest">Total Kategori</p>
                        <h3 class="text-2xl font-bold title-font text-[var(--text-dark)]"><?= $count_cats ?></h3>
                    </div>
                </div>

                <div class="bg-[var(--warm-tan)] p-6 rounded-[2rem] text-white flex items-center gap-5 shadow-lg shadow-[#B18143]/20 hover:-translate-y-1 transition-transform duration-300" data-aos="fade-left" data-aos-delay="400">
                    <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                        <span class="material-symbols-outlined text-3xl">menu_book</span>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-white/80 uppercase tracking-widest">Buku Terdaftar</p>
                        <h3 class="text-2xl font-bold title-font"><?= $count_books ?></h3>
                    </div>
                </div>
            </div>

            <section class="col-span-12 bg-white rounded-[2.5rem] p-8 border border-[var(--border-color)] card-shadow relative overflow-hidden" data-aos="fade-up" data-aos-delay="500">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 relative z-10 gap-4">
                    <div>
                        <h3 class="text-xl font-bold title-font mb-1 text-[var(--text-dark)]">Pengguna Terbaru</h3>
                        <p class="text-sm text-[var(--text-muted)] body-font font-medium">Status Online/Offline realtime</p>
                    </div>
                    <a href="manage_users.php" class="flex items-center gap-2 px-5 py-2.5 bg-[var(--light-sage)] text-[var(--deep-forest)] rounded-2xl font-bold hover:bg-[var(--deep-forest)] hover:text-white transition-all text-sm title-font shadow-sm hover:shadow-md">
                        <span class="material-symbols-outlined text-lg">visibility</span>
                        Lihat Semua
                    </a>
                </div>

                <div class="relative z-10 overflow-x-auto rounded-xl">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[var(--text-muted)] text-sm border-b border-[var(--border-color)] bg-stone-50/50">
                                <th class="pb-4 pt-2 px-4 font-bold uppercase tracking-wider">Nama Pengguna</th>
                                <th class="pb-4 pt-2 px-4 font-bold uppercase tracking-wider">Role</th>
                                <th class="pb-4 pt-2 px-4 font-bold uppercase tracking-wider">Bergabung</th>
                                <th class="pb-4 pt-2 px-4 font-bold uppercase tracking-wider text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-[var(--text-dark)]">
                            <?php if (mysqli_num_rows($recent_users_query) > 0): ?>
                                <?php while($usr = mysqli_fetch_assoc($recent_users_query)): 
                                    $is_online = false;
                                    if ($usr['last_activity']) {
                                        $last_active = strtotime($usr['last_activity']);
                                        if (time() - $last_active < 300) { 
                                            $is_online = true;
                                        }
                                    }
                                ?>
                                <tr class="border-b border-[var(--border-color)] last:border-0 hover:bg-[var(--cream-bg)]/50 transition-colors">
                                    <td class="py-4 px-4 font-bold flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-stone-200 flex items-center justify-center text-stone-500 font-bold text-xs">
                                            <?= strtoupper(substr($usr['full_name'], 0, 1)) ?>
                                        </div>
                                        <?= $usr['full_name'] ?>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-bold border 
                                            <?= $usr['role'] == 'seller' ? 'bg-orange-50 text-orange-700 border-orange-100' : 'bg-blue-50 text-blue-700 border-blue-100' ?>">
                                            <?= ucfirst($usr['role']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 text-stone-500"><?= date('d M Y', strtotime($usr['created_at'])) ?></td>
                                    <td class="py-4 px-4 text-center">
                                        <?php if($is_online): ?>
                                            <span class="inline-flex items-center gap-1.5 text-green-700 font-bold bg-green-50 px-3 py-1 rounded-full text-xs border border-green-100">
                                                <span class="relative flex h-2 w-2">
                                                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                                  <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                                                </span>
                                                Online
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 text-stone-500 font-bold bg-stone-100 px-3 py-1 rounded-full text-xs border border-stone-200">
                                                <span class="w-2 h-2 rounded-full bg-stone-400"></span> Offline
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-gray-400 italic">Belum ada pengguna terdaftar.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>

        <footer class="mt-12 text-center text-[var(--text-muted)] text-xs body-font pb-4">
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
</script>

</body>
</html>
