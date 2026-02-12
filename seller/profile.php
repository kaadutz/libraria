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

// Variabel Toast (Untuk pesan error/sukses)
$toast_message = "";
$toast_type = "";

// --- 1. HANDLE UPDATE PROFIL ---
if (isset($_POST['update_profile'])) {
    $full_name    = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email        = mysqli_real_escape_string($conn, $_POST['email']);
    $address      = mysqli_real_escape_string($conn, $_POST['address']);
    $bank_info    = mysqli_real_escape_string($conn, $_POST['bank_info']);
    $bank_account = mysqli_real_escape_string($conn, $_POST['bank_account']);

    // VALIDASI DUPLIKAT EMAIL (Kecuali email sendiri)
    $check_email = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' AND id != '$seller_id'");

    // VALIDASI DUPLIKAT NAMA TOKO (Kecuali nama sendiri)
    $check_name = mysqli_query($conn, "SELECT id FROM users WHERE full_name = '$full_name' AND id != '$seller_id'");

    if (mysqli_num_rows($check_email) > 0) {
        $toast_message = "Email sudah digunakan oleh akun lain!";
        $toast_type = "error";
    } elseif (mysqli_num_rows($check_name) > 0) {
        $toast_message = "Nama Toko sudah digunakan! Silakan pilih nama lain.";
        $toast_type = "error";
    } else {
        // Handle Upload Foto
        $img_sql = "";
        if (!empty($_FILES['profile_image']['name'])) {
            $target_dir = "../assets/uploads/profiles/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $new_name = "profile_" . $seller_id . "_" . time() . "." . $ext;

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_dir . $new_name);
                $img_sql = ", profile_image='$new_name'";
                $_SESSION['profile_image'] = $new_name; // Update Session Img
            }
        }

        // Update DB (NIK TIDAK DI-UPDATE KARENA READ ONLY)
        $query = "UPDATE users SET
                  full_name='$full_name',
                  email='$email',
                  address='$address',
                  bank_info='$bank_info',
                  bank_account='$bank_account'
                  $img_sql
                  WHERE id='$seller_id'";

        if (mysqli_query($conn, $query)) {
            $_SESSION['full_name'] = $full_name; // Update Session Name
            header("Location: profile.php?status=success_update");
            exit;
        } else {
            $toast_message = "Gagal update: " . mysqli_error($conn);
            $toast_type = "error";
        }
    }
}

// --- 2. HANDLE GANTI PASSWORD ---
if (isset($_POST['change_password'])) {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    $q = mysqli_query($conn, "SELECT password FROM users WHERE id='$seller_id'");
    $curr_user = mysqli_fetch_assoc($q);

    if (!password_verify($old_pass, $curr_user['password'])) {
        $toast_message = "Password lama salah!";
        $toast_type = "error";
    } elseif ($new_pass !== $confirm_pass) {
        $toast_message = "Konfirmasi password baru tidak cocok!";
        $toast_type = "error";
    } else {
        $hashed_new_pass = password_hash($new_pass, PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET password='$hashed_new_pass' WHERE id='$seller_id'");
        header("Location: profile.php?status=success_password");
        exit;
    }
}

// --- 3. AMBIL DATA USER ---
$query_user = mysqli_query($conn, "SELECT * FROM users WHERE id = '$seller_id'");
$user = mysqli_fetch_assoc($query_user);
$seller_name = $user['full_name'];
$profile_pic = !empty($user['profile_image']) ? "../assets/uploads/profiles/" . $user['profile_image'] : "../assets/images/default_profile.png";

// --- 4. DATA NOTIFIKASI (NAVBAR) ---
include 'includes/notification_logic.php';
// Re-calculate basic counts if needed for simple badges, though logic.php gives $total_notif
$total_new_orders = 0; // Logic.php groups by ID, so count might differ. Let's reuse logic.php's $notif_list count if needed or query.
// For simplicity in Sidebar badges, let's keep original queries or just use total_notif?
// Original sidebar used specific counts. Let's bring them back for sidebar.
$q_pending = mysqli_query($conn, "SELECT COUNT(DISTINCT o.id) as total FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE oi.seller_id = '$seller_id' AND (o.status = 'pending' OR o.status = 'waiting_approval')");
$total_new_orders = mysqli_fetch_assoc($q_pending)['total'];
$q_unread = mysqli_query($conn, "SELECT COUNT(*) as total FROM messages WHERE receiver_id = '$seller_id' AND is_read = 0");
$total_unread_chat = mysqli_fetch_assoc($q_unread)['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Profil Toko - Libraria Seller</title>

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

    #sidebar, #main-content { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    #sidebar-header { justify-content: flex-start; padding-left: 1.5rem; padding-right: 1.5rem; }
    #sidebar-logo { height: 5rem; width: auto; }
    .sidebar-text-wrapper { opacity: 1; width: auto; margin-left: 0.75rem; overflow: hidden; white-space: nowrap; }
    .menu-text { opacity: 1; width: auto; display: inline-block; }

    .sidebar-collapsed #sidebar-header { justify-content: center !important; padding-left: 0 !important; padding-right: 0 !important; }
    .sidebar-collapsed #sidebar-logo { height: 3.5rem !important; width: auto; margin: 0 auto; }
    .sidebar-collapsed .sidebar-text-wrapper { opacity: 0 !important; width: 0 !important; margin-left: 0 !important; pointer-events: none; }
    .sidebar-collapsed .menu-text { opacity: 0 !important; width: 0 !important; display: none; }
    .sidebar-collapsed nav a { justify-content: center; padding-left: 0; padding-right: 0; }

    /* Toast Animation */
    @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
    .toast-enter { animation: slideInRight 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
    .toast-exit { animation: fadeOut 0.4s ease forwards; }
</style>
</head>
<body class="bg-background-light dark:bg-background-dark text-stone-800 dark:text-stone-200 overflow-x-hidden transition-colors duration-300">

<div id="toast-container" class="fixed top-24 right-6 z-[100] flex flex-col gap-3"></div>

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
                <?php if($total_new_orders > 0): ?><span class="ml-auto bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full menu-text"><?= $total_new_orders ?></span><?php endif; ?>
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 dark:text-stone-400 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] dark:hover:text-[var(--warm-tan)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">bar_chart</span>
                <span class="font-medium menu-text whitespace-nowrap">Laporan</span>
            </a>
            <a href="chat.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 dark:text-stone-400 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] dark:hover:text-[var(--warm-tan)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">chat_bubble</span>
                <span class="font-medium menu-text whitespace-nowrap">Chat</span>
                <?php if($total_unread_chat > 0): ?><span class="ml-auto bg-blue-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full menu-text"><?= $total_unread_chat ?></span><?php endif; ?>
            </a>
            <a href="help.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 dark:text-stone-400 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] dark:hover:text-[var(--warm-tan)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">help</span>
                <span class="font-medium menu-text whitespace-nowrap">Bantuan</span>
            </a>
            <a href="sellers.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 dark:text-stone-400 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] dark:hover:text-[var(--warm-tan)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">storefront</span>
                <span class="font-medium menu-text whitespace-nowrap">Daftar Penjual</span>
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
                <div><h2 class="text-xl lg:text-2xl title-font text-[var(--text-dark)] dark:text-stone-200 hidden md:block">Profil Toko</h2></div>
            </div>
            <div class="flex items-center gap-4 relative">
                <button onclick="toggleDarkMode()" class="w-10 h-10 rounded-full bg-white dark:bg-stone-700 border border-[var(--border-color)] dark:border-stone-600 flex items-center justify-center text-[var(--deep-forest)] dark:text-[var(--warm-tan)] hover:text-[var(--deep-forest)] hover:shadow-md transition-all">
                    <span class="material-symbols-outlined" id="dark-mode-icon">dark_mode</span>
                </button>

                <button onclick="toggleDropdown('notificationDropdown')" class="w-10 h-10 rounded-full bg-white dark:bg-stone-700 border border-[var(--border-color)] dark:border-stone-600 flex items-center justify-center text-[var(--text-muted)] dark:text-stone-400 hover:text-[var(--deep-forest)] hover:shadow-md transition-all relative">
                    <span class="material-symbols-outlined">notifications</span>
                    <?php if($total_notif > 0): ?><span class="absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full border-2 border-white dark:border-stone-700 animate-ping"></span><span class="absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full border-2 border-white dark:border-stone-700"></span><?php endif; ?>
                </button>
                <div id="notificationDropdown" class="absolute right-16 top-14 w-80 bg-white dark:bg-stone-800 rounded-2xl shadow-xl border border-[var(--border-color)] dark:border-stone-700 py-2 hidden transform origin-top-right transition-all z-50">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-stone-700 flex justify-between items-center"><h4 class="font-bold text-[var(--deep-forest)] dark:text-[var(--warm-tan)]">Notifikasi</h4></div>
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8" data-aos="fade-up">

            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white dark:bg-stone-800 rounded-[2.5rem] p-8 border border-[var(--border-color)] dark:border-stone-700 card-shadow text-center relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-24 bg-[var(--deep-forest)]"></div>

                    <div class="relative z-10 w-32 h-32 mx-auto rounded-full p-1 bg-white border-4 border-[var(--cream-bg)] mb-4">
                        <img src="<?= $profile_pic ?>" id="previewImg" class="w-full h-full rounded-full object-cover">
                        <label for="uploadPhoto" class="absolute bottom-0 right-0 w-8 h-8 bg-[var(--warm-tan)] text-white rounded-full flex items-center justify-center cursor-pointer hover:bg-[var(--chocolate-brown)] transition-colors shadow-lg">
                            <span class="material-symbols-outlined text-sm">edit</span>
                        </label>
                    </div>

                    <h2 class="text-xl font-bold text-[var(--text-dark)] dark:text-stone-200 title-font"><?= $seller_name ?></h2>
                    <p class="text-sm text-[var(--text-muted)] dark:text-stone-400 font-medium mb-4">Seller Account</p>

                    <div class="flex justify-center gap-2 text-xs text-stone-500 mb-6">
                        <span class="px-3 py-1 rounded-full bg-stone-100 dark:bg-stone-700 border border-stone-200 dark:border-stone-600">Bergabung: <?= date('M Y', strtotime($user['created_at'])) ?></span>
                    </div>

                    <div class="border-t border-stone-100 dark:border-stone-700 pt-4 text-left space-y-3">
                        <div>
                            <p class="text-[10px] font-bold text-stone-400 uppercase tracking-widest">Email</p>
                            <p class="text-sm font-medium text-[var(--text-dark)] dark:text-stone-300 truncate"><?= $user['email'] ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-stone-400 uppercase tracking-widest">Rekening Utama</p>
                            <p class="text-xs font-medium text-[var(--text-dark)] dark:text-stone-300 truncate"><?= $user['bank_info'] ? $user['bank_info'] : '-' ?></p>
                            <p class="text-xs font-mono text-stone-500"><?= $user['bank_account'] ? $user['bank_account'] : '' ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-8">

                <div class="bg-white dark:bg-stone-800 rounded-[2.5rem] p-8 border border-[var(--border-color)] dark:border-stone-700 card-shadow">
                    <div class="flex items-center gap-3 mb-6 pb-4 border-b border-[var(--border-color)] dark:border-stone-700">
                        <span class="w-10 h-10 rounded-full bg-[var(--light-sage)] flex items-center justify-center text-[var(--deep-forest)]">
                            <span class="material-symbols-outlined">person_edit</span>
                        </span>
                        <h3 class="text-lg font-bold text-[var(--text-dark)] dark:text-stone-200 title-font">Edit Informasi Toko</h3>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="space-y-5">
                        <input type="file" name="profile_image" id="uploadPhoto" class="hidden" onchange="previewFile()">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] dark:text-stone-400 mb-1.5 ml-1">Nama Toko</label>
                                <input type="text" name="full_name" value="<?= $user['full_name'] ?>" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] dark:bg-stone-700 border-transparent focus:bg-white dark:focus:bg-stone-600 focus:border-[var(--warm-tan)] focus:ring-0 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] dark:text-stone-400 mb-1.5 ml-1">Email</label>
                                <input type="email" name="email" value="<?= $user['email'] ?>" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] dark:bg-stone-700 border-transparent focus:bg-white dark:focus:bg-stone-600 focus:border-[var(--warm-tan)] focus:ring-0 text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-[var(--text-muted)] dark:text-stone-400 mb-1.5 ml-1">NIK (Read Only)</label>
                            <input type="text" value="<?= $user['nik'] ?>" readonly class="w-full px-4 py-3 rounded-xl bg-gray-100 dark:bg-stone-900 text-gray-500 border-transparent cursor-not-allowed text-sm focus:ring-0">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] dark:text-stone-400 mb-1.5 ml-1">Nama Bank & Pemilik</label>
                                <input type="text" name="bank_info" value="<?= isset($user['bank_info']) ? htmlspecialchars($user['bank_info']) : '' ?>" placeholder="Cth: BCA a.n Siti Aminah" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] dark:bg-stone-700 border-transparent focus:bg-white dark:focus:bg-stone-600 focus:border-[var(--warm-tan)] focus:ring-0 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] dark:text-stone-400 mb-1.5 ml-1">Nomor Rekening</label>
                                <input type="number" name="bank_account" value="<?= isset($user['bank_account']) ? htmlspecialchars($user['bank_account']) : '' ?>" placeholder="Cth: 1234567890" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] dark:bg-stone-700 border-transparent focus:bg-white dark:focus:bg-stone-600 focus:border-[var(--warm-tan)] focus:ring-0 text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-[var(--text-muted)] dark:text-stone-400 mb-1.5 ml-1">Alamat Toko</label>
                            <textarea name="address" rows="3" class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] dark:bg-stone-700 border-transparent focus:bg-white dark:focus:bg-stone-600 focus:border-[var(--warm-tan)] focus:ring-0 text-sm resize-none"><?= isset($user['address']) ? $user['address'] : '' ?></textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" name="update_profile" class="px-8 py-3 bg-[var(--deep-forest)] text-white font-bold rounded-xl hover:opacity-90 transition-all shadow-lg text-sm flex items-center gap-2">
                                <span class="material-symbols-outlined text-lg">save</span> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>

                <div class="bg-white dark:bg-stone-800 rounded-[2.5rem] p-8 border border-[var(--border-color)] dark:border-stone-700 card-shadow">
                    <div class="flex items-center gap-3 mb-6 pb-4 border-b border-[var(--border-color)] dark:border-stone-700">
                        <span class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center text-red-600">
                            <span class="material-symbols-outlined">lock</span>
                        </span>
                        <h3 class="text-lg font-bold text-[var(--text-dark)] dark:text-stone-200 title-font">Ganti Kata Sandi</h3>
                    </div>

                    <form method="POST" class="space-y-5">
                        <div>
                            <label class="block text-xs font-bold uppercase text-[var(--text-muted)] dark:text-stone-400 mb-1.5 ml-1">Password Lama</label>
                            <input type="password" name="old_password" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] dark:bg-stone-700 border-transparent focus:bg-white dark:focus:bg-stone-600 focus:border-[var(--warm-tan)] focus:ring-0 text-sm">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] dark:text-stone-400 mb-1.5 ml-1">Password Baru</label>
                                <input type="password" name="new_password" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] dark:bg-stone-700 border-transparent focus:bg-white dark:focus:bg-stone-600 focus:border-[var(--warm-tan)] focus:ring-0 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] dark:text-stone-400 mb-1.5 ml-1">Konfirmasi Password Baru</label>
                                <input type="password" name="confirm_password" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] dark:bg-stone-700 border-transparent focus:bg-white dark:focus:bg-stone-600 focus:border-[var(--warm-tan)] focus:ring-0 text-sm">
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" name="change_password" class="px-8 py-3 bg-[var(--warm-tan)] text-white font-bold rounded-xl hover:opacity-90 transition-all shadow-lg text-sm flex items-center gap-2">
                                <span class="material-symbols-outlined text-lg">key</span> Ganti Password
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>

    </main>
</div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="../assets/js/theme-manager.js"></script>
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
        if (!event.target.closest('button') && !event.target.closest('label')) {
            const dropdowns = document.querySelectorAll('[id$="Dropdown"]');
            dropdowns.forEach(dd => dd.classList.add('hidden'));
        }
    }

    // TOAST FUNCTION
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        const bgColor = type === 'success' ? 'bg-[var(--deep-forest)]' : 'bg-red-600';
        const icon = type === 'success' ? 'check_circle' : 'error';

        toast.className = `flex items-center gap-3 px-6 py-4 rounded-2xl shadow-2xl text-white ${bgColor} toast-enter cursor-pointer backdrop-blur-md bg-opacity-95 transform transition-all duration-300 hover:scale-105`;
        toast.innerHTML = `<span class="material-symbols-outlined text-2xl">${icon}</span><p class="text-sm font-bold">${message}</p>`;

        toast.onclick = () => { toast.classList.add('toast-exit'); setTimeout(() => toast.remove(), 400); };
        container.appendChild(toast);
        setTimeout(() => { if (toast.isConnected) { toast.classList.add('toast-exit'); setTimeout(() => toast.remove(), 400); } }, 4000);
    }

    // PHP Status Handling (Success Messages)
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    if (status === 'success_update') showToast('Profil berhasil diperbarui!', 'success');
    if (status === 'success_password') showToast('Password berhasil diubah!', 'success');

    // PHP Error Injection (Duplicate/Error Messages)
    <?php if(!empty($toast_message)): ?>
        showToast("<?= $toast_message ?>", "<?= $toast_type ?>");
    <?php endif; ?>

    // Image Preview
    function previewFile() {
        const preview = document.getElementById('previewImg');
        const file = document.querySelector('input[type=file]').files[0];
        const reader = new FileReader();

        reader.onloadend = function () {
            preview.src = reader.result;
        }

        if (file) {
            reader.readAsDataURL(file);
        } else {
            preview.src = "../assets/images/default_profile.png";
        }
    }
</script>

</body>
</html>
