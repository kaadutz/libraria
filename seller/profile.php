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

// --- 1. HANDLE UPDATE PROFIL ---
if (isset($_POST['update_profile'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $phone     = mysqli_real_escape_string($conn, $_POST['phone']); // Pastikan kolom ini ada di DB
    $address   = mysqli_real_escape_string($conn, $_POST['address']); // Pastikan kolom ini ada di DB
    
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
            
            // Update Session Image
            $_SESSION['profile_image'] = $new_name;
        }
    }

    // Update DB
    $query = "UPDATE users SET full_name='$full_name', email='$email', phone='$phone', address='$address' $img_sql WHERE id='$seller_id'";
    
    if (mysqli_query($conn, $query)) {
        $_SESSION['full_name'] = $full_name; // Update session name
        $alert = "<script>alert('Profil berhasil diperbarui!'); window.location='profile.php';</script>";
    } else {
        $alert = "<script>alert('Gagal memperbarui profil: " . mysqli_error($conn) . "');</script>";
    }
}

// --- 2. HANDLE GANTI PASSWORD ---
if (isset($_POST['change_password'])) {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // Ambil password lama dari DB
    $q = mysqli_query($conn, "SELECT password FROM users WHERE id='$seller_id'");
    $curr_user = mysqli_fetch_assoc($q);

    if ($old_pass !== $curr_user['password']) {
        $alert = "<script>alert('Password lama salah!');</script>";
    } elseif ($new_pass !== $confirm_pass) {
        $alert = "<script>alert('Konfirmasi password baru tidak cocok!');</script>";
    } else {
        mysqli_query($conn, "UPDATE users SET password='$new_pass' WHERE id='$seller_id'");
        $alert = "<script>alert('Password berhasil diubah!'); window.location='profile.php';</script>";
    }
}

// --- 3. AMBIL DATA USER ---
$query_user = mysqli_query($conn, "SELECT * FROM users WHERE id = '$seller_id'");
$user = mysqli_fetch_assoc($query_user);
$seller_name = $user['full_name'];
$profile_pic = !empty($user['profile_image']) ? "../assets/uploads/profiles/" . $user['profile_image'] : "../assets/images/default_profile.png";

// --- 4. DATA NOTIFIKASI (NAVBAR) ---
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
<title>Profil Toko - Libraria Seller</title>

<script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
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
</style>
</head>
<body class="overflow-x-hidden">
    <?= isset($alert) ? $alert : '' ?>

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
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">dashboard</span>
                <span class="font-medium menu-text whitespace-nowrap">Dashboard</span>
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
                <?php if($total_new_orders > 0): ?><span class="ml-auto bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full menu-text"><?= $total_new_orders ?></span><?php endif; ?>
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">bar_chart</span>
                <span class="font-medium menu-text whitespace-nowrap">Laporan</span>
            </a>
            <a href="chat.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">chat_bubble</span>
                <span class="font-medium menu-text whitespace-nowrap">Chat</span>
                <?php if($total_unread_chat > 0): ?><span class="ml-auto bg-blue-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full menu-text"><?= $total_unread_chat ?></span><?php endif; ?>
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
        
        <header class="flex justify-between items-center mb-8 bg-white/50 backdrop-blur-sm p-4 rounded-3xl border border-[var(--border-color)] sticky top-4 z-20 shadow-sm" data-aos="fade-down">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl hover:bg-[var(--light-sage)] text-[var(--deep-forest)] transition-colors focus:outline-none">
                    <span class="material-symbols-outlined">menu_open</span>
                </button>
                <div><h2 class="text-xl lg:text-2xl title-font text-[var(--text-dark)] hidden md:block">Profil Toko</h2></div>
            </div>
            <div class="flex items-center gap-4 relative">
                <button onclick="toggleDropdown('notificationDropdown')" class="w-10 h-10 rounded-full bg-white border border-[var(--border-color)] flex items-center justify-center text-[var(--text-muted)] hover:text-[var(--deep-forest)] hover:shadow-md transition-all relative">
                    <span class="material-symbols-outlined">notifications</span>
                    <?php if($total_notif > 0): ?><span class="absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full border-2 border-white animate-ping"></span><?php endif; ?>
                </button>
                <div id="notificationDropdown" class="absolute right-16 top-14 w-80 bg-white rounded-2xl shadow-xl border border-[var(--border-color)] py-2 hidden transform origin-top-right transition-all z-50">
                    <div class="px-4 py-3 border-b border-gray-100 flex justify-between items-center"><h4 class="font-bold text-[var(--deep-forest)]">Notifikasi</h4></div>
                    <div class="max-h-64 overflow-y-auto">
                        <?php if($total_new_orders > 0): ?>
                        <a href="orders.php" class="flex items-start gap-3 px-4 py-3 hover:bg-[var(--cream-bg)] transition-colors border-b border-gray-50">
                            <div class="p-2 bg-orange-100 text-orange-600 rounded-full"><span class="material-symbols-outlined text-lg">shopping_bag</span></div>
                            <div><p class="text-sm font-bold">Pesanan Baru!</p><p class="text-xs text-gray-500">Ada <?= $total_new_orders ?> pesanan menunggu.</p></div>
                        </a>
                        <?php endif; ?>
                        <?php if($total_notif == 0): ?><div class="text-center py-6 text-gray-400 text-xs italic">Tidak ada notifikasi.</div><?php endif; ?>
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
                    <a href="profile.php" class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] transition-colors bg-[var(--light-sage)]/20 font-bold"><span class="material-symbols-outlined text-[20px]">store</span> Profil Toko</a>
                    <a href="../auth/logout.php" class="flex items-center gap-2 px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors"><span class="material-symbols-outlined text-[20px]">logout</span> Log Out</a>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8" data-aos="fade-up">
            
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white rounded-[2.5rem] p-8 border border-[var(--border-color)] card-shadow text-center relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-24 bg-[var(--deep-forest)]"></div>
                    
                    <div class="relative z-10 w-32 h-32 mx-auto rounded-full p-1 bg-white border-4 border-[var(--cream-bg)] mb-4">
                        <img src="<?= $profile_pic ?>" id="previewImg" class="w-full h-full rounded-full object-cover">
                        <label for="uploadPhoto" class="absolute bottom-0 right-0 w-8 h-8 bg-[var(--warm-tan)] text-white rounded-full flex items-center justify-center cursor-pointer hover:bg-[var(--chocolate-brown)] transition-colors shadow-lg">
                            <span class="material-symbols-outlined text-sm">edit</span>
                        </label>
                    </div>

                    <h2 class="text-xl font-bold text-[var(--text-dark)] title-font"><?= $seller_name ?></h2>
                    <p class="text-sm text-[var(--text-muted)] font-medium mb-4">Seller Account</p>
                    
                    <div class="flex justify-center gap-2 text-xs text-stone-500 mb-6">
                        <span class="px-3 py-1 rounded-full bg-stone-100 border border-stone-200">Bergabung: <?= date('M Y', strtotime($user['created_at'])) ?></span>
                    </div>

                    <div class="border-t border-stone-100 pt-4 text-left space-y-3">
                        <div>
                            <p class="text-[10px] font-bold text-stone-400 uppercase tracking-widest">Email</p>
                            <p class="text-sm font-medium text-[var(--text-dark)] truncate"><?= $user['email'] ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-stone-400 uppercase tracking-widest">NIK</p>
                            <p class="text-sm font-medium text-[var(--text-dark)] font-mono bg-stone-50 px-2 py-1 rounded w-fit"><?= $user['nik'] ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-8">
                
                <div class="bg-white rounded-[2.5rem] p-8 border border-[var(--border-color)] card-shadow">
                    <div class="flex items-center gap-3 mb-6 pb-4 border-b border-[var(--border-color)]">
                        <span class="w-10 h-10 rounded-full bg-[var(--light-sage)] flex items-center justify-center text-[var(--deep-forest)]">
                            <span class="material-symbols-outlined">person_edit</span>
                        </span>
                        <h3 class="text-lg font-bold text-[var(--text-dark)] title-font">Edit Informasi Toko</h3>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="space-y-5">
                        <input type="file" name="profile_image" id="uploadPhoto" class="hidden" onchange="previewFile()">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Nama Toko</label>
                                <input type="text" name="full_name" value="<?= $user['full_name'] ?>" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Email</label>
                                <input type="email" name="email" value="<?= $user['email'] ?>" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Nomor Telepon</label>
                                <input type="text" name="phone" value="<?= isset($user['phone']) ? $user['phone'] : '' ?>" placeholder="08..." class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">NIK (Read Only)</label>
                                <input type="text" value="<?= $user['nik'] ?>" readonly class="w-full px-4 py-3 rounded-xl bg-stone-100 text-stone-500 border-transparent cursor-not-allowed text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Alamat Toko</label>
                            <textarea name="address" rows="3" class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 text-sm resize-none"><?= isset($user['address']) ? $user['address'] : '' ?></textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" name="update_profile" class="px-8 py-3 bg-[var(--deep-forest)] text-white font-bold rounded-xl hover:opacity-90 transition-all shadow-lg text-sm flex items-center gap-2">
                                <span class="material-symbols-outlined text-lg">save</span> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>

                <div class="bg-white rounded-[2.5rem] p-8 border border-[var(--border-color)] card-shadow">
                    <div class="flex items-center gap-3 mb-6 pb-4 border-b border-[var(--border-color)]">
                        <span class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center text-red-600">
                            <span class="material-symbols-outlined">lock</span>
                        </span>
                        <h3 class="text-lg font-bold text-[var(--text-dark)] title-font">Ganti Kata Sandi</h3>
                    </div>

                    <form method="POST" class="space-y-5">
                        <div>
                            <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Password Lama</label>
                            <input type="password" name="old_password" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 text-sm">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Password Baru</label>
                                <input type="password" name="new_password" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Konfirmasi Password Baru</label>
                                <input type="password" name="confirm_password" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 text-sm">
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