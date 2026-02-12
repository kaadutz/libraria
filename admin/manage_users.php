<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../config/db.php';

// Cek Keamanan
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// --- LOGIKA PAGINATION ---
$limit = 4;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

$result_count = mysqli_query($conn, "SELECT COUNT(id) AS total FROM users WHERE role != 'admin'");
$user_count_data = mysqli_fetch_assoc($result_count);
$total_pages = ceil($user_count_data['total'] / $limit);

$users = mysqli_query($conn, "SELECT * FROM users WHERE role != 'admin' ORDER BY created_at DESC LIMIT $start, $limit");

// --- LOGIKA STATISTIK ---
$count_all    = $user_count_data['total'];
$count_seller = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE role = 'seller'"));
$count_buyer  = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE role = 'buyer'"));
$count_online = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE role != 'admin' AND last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"));

// --- LOGIKA CRUD ---

// 1. TAMBAH PENJUAL
if (isset($_POST['add_seller'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $nik       = mysqli_real_escape_string($conn, $_POST['nik']);
    $password  = $_POST['password'];

    // Validasi 1: Cek Email Duplikat
    $check_email = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");

    // Validasi 2: Cek NIK Duplikat (Hanya jika NIK diisi)
    $nik_duplicate = false;
    if(!empty($nik)) {
        $check_nik = mysqli_query($conn, "SELECT id FROM users WHERE nik = '$nik'");
        if(mysqli_num_rows($check_nik) > 0) {
            $nik_duplicate = true;
        }
    }

    if (mysqli_num_rows($check_email) > 0) {
        $alert = "<script>alert('GAGAL: Email sudah terdaftar! Gunakan email lain.');</script>";
    } elseif ($nik_duplicate) {
        $alert = "<script>alert('GAGAL: NIK sudah terdaftar! Periksa kembali data NIK.');</script>";
    } else {
        // Jika NIK kosong, masukkan NULL agar tidak bentrok dengan UNIQUE KEY
        $nik_val = empty($nik) ? "NULL" : "'$nik'";

        $query = "INSERT INTO users (full_name, email, nik, password, role) VALUES ('$full_name', '$email', $nik_val, '$password', 'seller')";

        if (mysqli_query($conn, $query)) {
            $alert = "<script>alert('Penjual Berhasil Ditambahkan!'); window.location='manage_users.php';</script>";
        } else {
            // Tangkap error MySQL (misal kode 1062 untuk duplicate entry)
            $error = mysqli_error($conn);
            if(strpos($error, 'Duplicate entry') !== false) {
                $alert = "<script>alert('GAGAL: Data duplikat terdeteksi di database.');</script>";
            } else {
                // XSS Prevention: Escape error message in JS context
                $safe_error = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
                $alert = "<script>alert('Error Database: $safe_error');</script>";
            }
        }
    }
}

// 2. EDIT USER (UPDATE SEMUA DATA)
if (isset($_POST['edit_user'])) {
    $id        = $_POST['id'];
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $nik       = mysqli_real_escape_string($conn, $_POST['nik']);
    $role      = mysqli_real_escape_string($conn, $_POST['role']);
    $address   = mysqli_real_escape_string($conn, $_POST['address']);
    $password  = $_POST['password'];

    // Cek duplikat email KECUALI milik user ini sendiri
    $check_email = mysqli_query($conn, "SELECT id FROM users WHERE email='$email' AND id != '$id'");

    // Cek duplikat NIK KECUALI milik user ini sendiri (hanya jika NIK diisi)
    $nik_duplicate = false;
    if(!empty($nik)) {
        $check_nik = mysqli_query($conn, "SELECT id FROM users WHERE nik='$nik' AND id != '$id'");
        if(mysqli_num_rows($check_nik) > 0) {
            $nik_duplicate = true;
        }
    }

    if(mysqli_num_rows($check_email) > 0){
        $alert = "<script>alert('GAGAL: Email sudah digunakan user lain!');</script>";
    } elseif ($nik_duplicate) {
        $alert = "<script>alert('GAGAL: NIK sudah digunakan user lain!');</script>";
    } else {
        // Siapkan nilai NIK untuk query (NULL jika kosong)
        $nik_val = empty($nik) ? "NULL" : "'$nik'";

        // Cek apakah password diubah
        if(!empty($password)){
            $query = "UPDATE users SET full_name='$full_name', email='$email', nik=$nik_val, role='$role', address='$address', password='$password' WHERE id='$id'";
        } else {
            $query = "UPDATE users SET full_name='$full_name', email='$email', nik=$nik_val, role='$role', address='$address' WHERE id='$id'";
        }

        if(mysqli_query($conn, $query)) {
            $alert = "<script>alert('Data Pengguna Berhasil Diperbarui!'); window.location='manage_users.php';</script>";
        } else {
            $error = mysqli_error($conn);
            if(strpos($error, 'Duplicate entry') !== false) {
                $alert = "<script>alert('GAGAL: Data duplikat terdeteksi di database.');</script>";
            } else {
                // XSS Prevention: Escape error message in JS context
                $safe_error = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
                $alert = "<script>alert('Gagal update data: $safe_error');</script>";
            }
        }
    }
}

// 3. HAPUS USER
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $check_status = mysqli_query($conn, "SELECT last_activity FROM users WHERE id='$id'");
    $u_data = mysqli_fetch_assoc($check_status);

    $is_online = false;
    if ($u_data['last_activity']) {
        if (time() - strtotime($u_data['last_activity']) < 300) $is_online = true;
    }

    if ($is_online) {
        $alert = "<script>alert('GAGAL: Pengguna sedang ONLINE! Tunggu hingga offline.'); window.location='manage_users.php';</script>";
    } else {
        mysqli_query($conn, "DELETE FROM users WHERE id='$id'");
        $alert = "<script>alert('Pengguna Berhasil Dihapus!'); window.location='manage_users.php';</script>";
    }
}

$admin_name = $_SESSION['full_name'];
$profile_pic = "../assets/images/logo.png"; // Placeholder
if(isset($_SESSION['user_id'])){
    $uid = $_SESSION['user_id'];
    $q = mysqli_query($conn, "SELECT profile_image FROM users WHERE id='$uid'");
    $d = mysqli_fetch_assoc($q);
    if(!empty($d['profile_image'])){
        $profile_pic = "../assets/uploads/profiles/".$d['profile_image'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Kelola User - Libraria Admin</title>

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

    .modal { transition: opacity 0.25s ease; }
    body.modal-active { overflow-x: hidden; overflow-y: hidden !important; }
</style>
<script src="../assets/js/theme-manager.js"></script>
</head>
<body class="overflow-x-hidden">
    <?= isset($alert) ? $alert : '' ?>

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
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">dashboard</span>
                <span class="font-medium menu-text whitespace-nowrap">Dashboard</span>
            </a>
            <a href="manage_users.php" class="flex items-center gap-3 px-4 py-3 sidebar-active rounded-2xl transition-all group shadow-md shadow-green-900/10">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">group</span>
                <span class="font-semibold menu-text whitespace-nowrap">Kelola User</span>
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

        <header class="flex justify-between items-center mb-8 bg-white/50 backdrop-blur-sm p-4 rounded-3xl border border-[var(--border-color)] sticky top-4 z-20 shadow-sm" data-aos="fade-down">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl hover:bg-[var(--light-sage)] text-[var(--deep-forest)] transition-colors focus:outline-none">
                    <span class="material-symbols-outlined">menu_open</span>
                </button>
                <div><h2 class="text-xl lg:text-2xl title-font text-[var(--text-dark)] hidden md:block">Manajemen Pengguna</h2></div>
            </div>
            <div class="flex items-center gap-4 relative">

<button onclick="toggleDarkMode()" class="w-10 h-10 rounded-full bg-white/10 border border-[var(--border-color)] text-[var(--text-muted)] hover:text-[var(--deep-forest)] hover:bg-[var(--light-sage)]/30 transition-all flex items-center justify-center group mr-2" title="Toggle Dark Mode">
    <span class="material-symbols-outlined group-hover:rotate-180 transition-transform duration-500" id="dark-mode-icon">dark_mode</span>
</button>

                <button onclick="toggleModal('addSellerModal')" class="hidden md:flex items-center gap-2 px-5 py-2.5 bg-[var(--chocolate-brown)] text-white font-bold rounded-2xl hover:opacity-90 transition-all shadow-lg shadow-orange-900/20 text-sm">
                    <span class="material-symbols-outlined text-[18px]">person_add</span> Tambah Penjual
                </button>
                <button onclick="toggleProfileDropdown()" class="flex items-center gap-3 bg-white p-1.5 pr-4 rounded-full border border-[var(--border-color)] card-shadow hover:shadow-md transition-all focus:outline-none">
                    <img src="<?= $profile_pic ?>" alt="Profile" class="w-9 h-9 rounded-full object-cover border-2 border-[var(--cream-bg)]">
                    <div class="text-left hidden sm:block">
                        <p class="text-xs font-bold leading-none title-font"><?= $admin_name ?></p>
                        <p class="text-[10px] text-[var(--warm-tan)] leading-none mt-1 font-bold uppercase">Super Admin</p>
                    </div>
                    <span class="material-symbols-outlined text-[18px] text-[var(--text-muted)]">expand_more</span>
                </button>
                <div id="profileDropdown" class="absolute right-0 top-14 w-56 bg-white rounded-2xl shadow-xl border border-[var(--border-color)] py-2 hidden transform origin-top-right transition-all z-50">
                    <a href="profileadmin.php" class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] transition-colors"><span class="material-symbols-outlined text-[20px]">person</span> My Profile</a>
                    <a href="../auth/logout.php" class="flex items-center gap-2 px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors"><span class="material-symbols-outlined text-[20px]">logout</span> Log Out</a>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" data-aos="fade-up">
            <div class="bg-white p-6 rounded-[2.5rem] border border-[var(--border-color)] card-shadow flex items-center gap-4 hover:-translate-y-1 transition-transform">
                <div class="w-12 h-12 rounded-full bg-[var(--light-sage)]/40 flex items-center justify-center text-[var(--deep-forest)]"><span class="material-symbols-outlined text-2xl">group</span></div>
                <div><h4 class="text-2xl font-bold text-[var(--deep-forest)]"><?= $count_all ?></h4><p class="text-xs text-[var(--text-muted)] font-bold uppercase">Total Pengguna</p></div>
            </div>
            <div class="bg-white p-6 rounded-[2.5rem] border border-[var(--border-color)] card-shadow flex items-center gap-4 hover:-translate-y-1 transition-transform">
                <div class="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center text-orange-600"><span class="material-symbols-outlined text-2xl">storefront</span></div>
                <div><h4 class="text-2xl font-bold text-[var(--deep-forest)]"><?= $count_seller ?></h4><p class="text-xs text-[var(--text-muted)] font-bold uppercase">Total Penjual</p></div>
            </div>
            <div class="bg-white p-6 rounded-[2.5rem] border border-[var(--border-color)] card-shadow flex items-center gap-4 hover:-translate-y-1 transition-transform">
                <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-600"><span class="material-symbols-outlined text-2xl">shopping_bag</span></div>
                <div><h4 class="text-2xl font-bold text-[var(--deep-forest)]"><?= $count_buyer ?></h4><p class="text-xs text-[var(--text-muted)] font-bold uppercase">Total Pembeli</p></div>
            </div>
            <div class="bg-[var(--deep-forest)] p-6 rounded-[2.5rem] text-white card-shadow flex items-center gap-4 hover:-translate-y-1 transition-transform shadow-lg shadow-green-900/20">
                <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-white backdrop-blur-sm"><span class="material-symbols-outlined text-2xl animate-pulse">wifi</span></div>
                <div><h4 class="text-2xl font-bold text-white"><?= $count_online ?></h4><p class="text-xs text-white/80 font-bold uppercase">Sedang Online</p></div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6" data-aos="fade-up" data-aos-delay="100">
            <?php while($usr = mysqli_fetch_assoc($users)):
                $is_online = false;
                if ($usr['last_activity']) {
                    if (time() - strtotime($usr['last_activity']) < 300) $is_online = true;
                }
                $user_pic = !empty($usr['profile_image']) ? "../assets/uploads/profiles/" . $usr['profile_image'] : "../assets/images/default_profile.png";
            ?>

            <div class="bg-white rounded-[2.5rem] p-6 border border-[var(--border-color)] card-shadow hover:shadow-lg transition-all group relative overflow-hidden">

                <div class="absolute top-0 right-0 w-24 h-24 bg-[var(--light-sage)]/20 rounded-bl-[3rem] transition-all group-hover:scale-110 pointer-events-none z-0"></div>

                <div class="flex items-start justify-between mb-6 relative z-10">
                    <div class="flex items-center gap-4">
                        <img src="<?= $user_pic ?>" class="w-16 h-16 rounded-2xl object-cover shadow-sm border border-stone-100 bg-stone-100" onerror="this.src='../assets/images/logo.png'">
                        <div>
                            <h3 class="font-bold text-lg text-[var(--text-dark)] leading-tight mb-1"><?= $usr['full_name'] ?></h3>
                            <span class="inline-block px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $usr['role'] == 'seller' ? 'bg-orange-50 text-orange-600 border border-orange-100' : 'bg-blue-50 text-blue-600 border border-blue-100' ?>">
                                <?= ucfirst($usr['role']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="button"
                                onclick="openEditModal('<?= $usr['id'] ?>', '<?= htmlspecialchars($usr['full_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($usr['email'], ENT_QUOTES) ?>', '<?= htmlspecialchars($usr['nik'], ENT_QUOTES) ?>', '<?= htmlspecialchars($usr['role'], ENT_QUOTES) ?>', `<?= htmlspecialchars($usr['address'], ENT_QUOTES) ?>`)"
                                class="w-10 h-10 rounded-full bg-stone-50 flex items-center justify-center text-stone-400 hover:bg-[var(--warm-tan)] hover:text-white transition-all shadow-sm cursor-pointer z-20 relative">
                            <span class="material-symbols-outlined text-lg">edit</span>
                        </button>

                        <?php if(!$is_online): ?>
                        <a href="?delete=<?= $usr['id'] ?>" onclick="return confirm('Hapus pengguna ini selamanya?')" class="w-10 h-10 rounded-full bg-stone-50 flex items-center justify-center text-stone-400 hover:bg-red-500 hover:text-white transition-all shadow-sm cursor-pointer z-20 relative">
                            <span class="material-symbols-outlined text-lg">delete</span>
                        </a>
                        <?php else: ?>
                        <button type="button" onclick="alert('User sedang online!')" class="w-10 h-10 rounded-full bg-stone-50 flex items-center justify-center text-stone-300 cursor-not-allowed z-20 relative">
                            <span class="material-symbols-outlined text-lg">lock</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="space-y-3 relative z-10">
                    <div class="flex items-center gap-3 text-sm text-stone-600 bg-stone-50/50 p-3 rounded-xl">
                        <span class="material-symbols-outlined text-[var(--warm-tan)]">mail</span>
                        <span class="truncate"><?= $usr['email'] ?></span>
                    </div>
                    <div class="flex items-center gap-3 text-sm text-stone-600 bg-stone-50/50 p-3 rounded-xl">
                        <span class="material-symbols-outlined text-[var(--warm-tan)]">badge</span>
                        <span class="truncate font-mono"><?= $usr['nik'] ? $usr['nik'] : 'No NIK' ?></span>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-between border-t border-[var(--border-color)] pt-4 relative z-10">
                    <div class="text-xs text-[var(--text-muted)]">
                        Joined: <span class="font-bold"><?= date('d M Y', strtotime($usr['created_at'])) ?></span>
                    </div>
                    <div>
                        <?php if($is_online): ?>
                            <span class="flex items-center gap-1.5 text-[10px] font-bold text-green-600 bg-green-50 px-2 py-1 rounded-lg">
                                <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> ONLINE
                            </span>
                        <?php else: ?>
                            <span class="flex items-center gap-1.5 text-[10px] font-bold text-stone-400 bg-stone-100 px-2 py-1 rounded-lg">
                                <span class="w-2 h-2 rounded-full bg-stone-300"></span> OFFLINE
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <div class="mt-8 flex justify-center items-center gap-4">
            <?php if($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>" class="px-4 py-2 bg-white border border-[var(--border-color)] rounded-xl hover:bg-[var(--cream-bg)] transition-colors text-sm font-bold text-[var(--deep-forest)] shadow-sm">Previous</a>
            <?php endif; ?>
            <span class="text-sm font-bold text-[var(--text-muted)]">Page <?= $page ?> of <?= $total_pages ?></span>
            <?php if($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>" class="px-4 py-2 bg-white border border-[var(--border-color)] rounded-xl hover:bg-[var(--cream-bg)] transition-colors text-sm font-bold text-[var(--deep-forest)] shadow-sm">Next</a>
            <?php endif; ?>
        </div>

    </main>
</div>

<div id="addSellerModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 transition-opacity duration-300">
    <div class="modal-overlay absolute w-full h-full bg-stone-900/60 backdrop-blur-sm" onclick="toggleModal('addSellerModal')"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded-[2rem] shadow-2xl z-50 overflow-y-auto p-8 transform transition-all scale-95 duration-300">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-[var(--deep-forest)] title-font">Tambah Penjual</h3>
            <button onclick="toggleModal('addSellerModal')" class="w-8 h-8 rounded-full bg-stone-100 flex items-center justify-center text-stone-400 hover:bg-red-100 hover:text-red-500 transition-colors"><span class="material-symbols-outlined text-lg">close</span></button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <div><label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Nama Toko</label><input type="text" name="full_name" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all"></div>
            <div><label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">NIK (Wajib)</label><input type="number" name="nik" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all"></div>
            <div><label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Email</label><input type="email" name="email" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all"></div>
            <div><label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Password</label><input type="password" name="password" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all"></div>
            <button type="submit" name="add_seller" class="w-full py-4 mt-4 bg-[var(--deep-forest)] text-white font-bold rounded-xl hover:bg-[var(--chocolate-brown)] transition-all shadow-lg">Simpan Data</button>
        </form>
    </div>
</div>

<div id="editUserModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 transition-opacity duration-300">
    <div class="modal-overlay absolute w-full h-full bg-stone-900/60 backdrop-blur-sm" onclick="toggleModal('editUserModal')"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded-[2rem] shadow-2xl z-50 overflow-y-auto p-8 transform transition-all scale-95 duration-300 max-h-[90vh]">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-[var(--deep-forest)] title-font">Edit Data User</h3>
            <button onclick="toggleModal('editUserModal')" class="w-8 h-8 rounded-full bg-stone-100 flex items-center justify-center text-stone-400 hover:bg-red-100 hover:text-red-500 transition-colors"><span class="material-symbols-outlined text-lg">close</span></button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="id" id="edit_id">

            <div>
                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Nama Lengkap / Toko</label>
                <input type="text" name="full_name" id="edit_name" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Email</label>
                <input type="email" name="email" id="edit_email" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">NIK</label>
                    <input type="text" name="nik" id="edit_nik" class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Role</label>
                    <select name="role" id="edit_role" class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all text-sm">
                        <option value="buyer">Buyer</option>
                        <option value="seller">Seller</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Alamat</label>
                <textarea name="address" id="edit_address" rows="2" class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all"></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Reset Password</label>
                <input type="password" name="password" placeholder="Kosongkan jika tidak ingin mengubah" class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all">
                <p class="text-[10px] text-orange-500 mt-1 ml-1">*Isi hanya jika ingin mereset password.</p>
            </div>

            <button type="submit" name="edit_user" class="w-full py-4 mt-4 bg-[var(--warm-tan)] text-white font-bold rounded-xl hover:bg-[var(--chocolate-brown)] transition-all shadow-lg">Update Data</button>
        </form>
    </div>
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
        dropdown.classList.toggle('hidden');
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

    // Fungsi untuk mengisi form edit secara dinamis
    function openEditModal(id, name, email, nik, role, address) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_nik').value = nik;
        document.getElementById('edit_role').value = role;
        document.getElementById('edit_address').value = address;
        toggleModal('editUserModal');
    }
</script>

</body>
</html>
