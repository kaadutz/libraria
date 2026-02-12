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
                $alert = "<script>alert('Error Database: $error');</script>";
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
                $alert = "<script>alert('Gagal update data: $error');</script>";
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
    <title>Kelola User - Libraria Admin</title>
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
    <?= isset($alert) ? $alert : '' ?>

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

        <header class="flex justify-between items-center mb-8 bg-white/50 dark:bg-stone-900/50 backdrop-blur-sm p-4 rounded-3xl border border-tan/20 dark:border-stone-800 sticky top-4 z-20 shadow-sm" data-aos="fade-down">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl hover:bg-sage text-primary dark:text-sage transition-colors focus:outline-none">
                    <span class="material-symbols-outlined">menu_open</span>
                </button>
                <div><h2 class="text-xl lg:text-2xl title-font text-stone-800 dark:text-stone-200 hidden md:block">Manajemen Pengguna</h2></div>
            </div>

            <div class="flex items-center gap-4 relative">
                <button onclick="toggleModal('addSellerModal')" class="hidden md:flex items-center gap-2 px-5 py-2.5 bg-chocolate text-white font-bold rounded-2xl hover:opacity-90 transition-all shadow-lg shadow-orange-900/20 text-sm">
                    <span class="material-symbols-outlined text-[18px]">person_add</span> Tambah Penjual
                </button>

                <button onclick="toggleDarkMode()" class="w-10 h-10 flex items-center justify-center rounded-full text-stone-500 dark:text-stone-400 hover:bg-primary hover:text-white dark:hover:bg-stone-800 transition-all duration-300">
                    <span class="material-icons-outlined text-xl">dark_mode</span>
                </button>

                <button onclick="toggleProfileDropdown()" class="flex items-center gap-3 bg-white dark:bg-stone-800 p-1.5 pr-4 rounded-full border border-tan/20 dark:border-stone-700 card-shadow hover:shadow-md transition-all focus:outline-none">
                    <img src="<?= $profile_pic ?>" alt="Profile" class="w-9 h-9 rounded-full object-cover border-2 border-cream dark:border-stone-600">
                    <div class="text-left hidden sm:block">
                        <p class="text-xs font-bold leading-none title-font text-stone-800 dark:text-stone-200"><?= $admin_name ?></p>
                        <p class="text-[10px] text-tan leading-none mt-1 font-bold uppercase">Super Admin</p>
                    </div>
                    <span class="material-symbols-outlined text-[18px] text-stone-500 dark:text-stone-400">expand_more</span>
                </button>

                <div id="profileDropdown" class="absolute right-0 top-14 w-56 bg-white dark:bg-stone-900 rounded-2xl shadow-xl border border-tan/20 dark:border-stone-700 py-2 hidden transform origin-top-right transition-all z-50">
                    <a href="profileadmin.php" class="flex items-center gap-2 px-4 py-3 text-sm text-stone-700 dark:text-stone-300 hover:bg-sage/30 hover:text-primary transition-colors"><span class="material-symbols-outlined text-[20px]">person</span> My Profile</a>
                    <a href="../auth/logout.php" class="flex items-center gap-2 px-4 py-3 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"><span class="material-symbols-outlined text-[20px]">logout</span> Log Out</a>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" data-aos="fade-up">
            <div class="bg-white dark:bg-stone-900 p-6 rounded-[2.5rem] border border-tan/20 dark:border-stone-800 card-shadow flex items-center gap-4 hover:-translate-y-1 transition-transform">
                <div class="w-12 h-12 rounded-full bg-sage/40 dark:bg-sage/20 flex items-center justify-center text-primary dark:text-sage"><span class="material-symbols-outlined text-2xl">group</span></div>
                <div><h4 class="text-2xl font-bold text-primary dark:text-sage"><?= $count_all ?></h4><p class="text-xs text-stone-500 dark:text-stone-400 font-bold uppercase">Total Pengguna</p></div>
            </div>
            <div class="bg-white dark:bg-stone-900 p-6 rounded-[2.5rem] border border-tan/20 dark:border-stone-800 card-shadow flex items-center gap-4 hover:-translate-y-1 transition-transform">
                <div class="w-12 h-12 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center text-orange-600 dark:text-orange-400"><span class="material-symbols-outlined text-2xl">storefront</span></div>
                <div><h4 class="text-2xl font-bold text-primary dark:text-sage"><?= $count_seller ?></h4><p class="text-xs text-stone-500 dark:text-stone-400 font-bold uppercase">Total Penjual</p></div>
            </div>
            <div class="bg-white dark:bg-stone-900 p-6 rounded-[2.5rem] border border-tan/20 dark:border-stone-800 card-shadow flex items-center gap-4 hover:-translate-y-1 transition-transform">
                <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400"><span class="material-symbols-outlined text-2xl">shopping_bag</span></div>
                <div><h4 class="text-2xl font-bold text-primary dark:text-sage"><?= $count_buyer ?></h4><p class="text-xs text-stone-500 dark:text-stone-400 font-bold uppercase">Total Pembeli</p></div>
            </div>
            <div class="bg-primary dark:bg-stone-800 p-6 rounded-[2.5rem] text-white card-shadow flex items-center gap-4 hover:-translate-y-1 transition-transform shadow-lg shadow-green-900/20">
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

            <div class="bg-white dark:bg-stone-900 rounded-[2.5rem] p-6 border border-tan/20 dark:border-stone-800 card-shadow hover:shadow-lg transition-all group relative overflow-hidden">

                <div class="absolute top-0 right-0 w-24 h-24 bg-sage/20 dark:bg-sage/10 rounded-bl-[3rem] transition-all group-hover:scale-110 pointer-events-none z-0"></div>

                <div class="flex items-start justify-between mb-6 relative z-10">
                    <div class="flex items-center gap-4">
                        <img src="<?= $user_pic ?>" class="w-16 h-16 rounded-2xl object-cover shadow-sm border border-stone-100 dark:border-stone-700 bg-stone-100 dark:bg-stone-800" onerror="this.src='../assets/images/logo.png'">
                        <div>
                            <h3 class="font-bold text-lg text-stone-800 dark:text-stone-200 leading-tight mb-1"><?= $usr['full_name'] ?></h3>
                            <span class="inline-block px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $usr['role'] == 'seller' ? 'bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400 border border-orange-100 dark:border-orange-900/50' : 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 border border-blue-100 dark:border-blue-900/50' ?>">
                                <?= ucfirst($usr['role']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="button"
                                onclick="openEditModal('<?= $usr['id'] ?>', '<?= htmlspecialchars($usr['full_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($usr['email'], ENT_QUOTES) ?>', '<?= htmlspecialchars($usr['nik'], ENT_QUOTES) ?>', '<?= htmlspecialchars($usr['role'], ENT_QUOTES) ?>', `<?= htmlspecialchars($usr['address'], ENT_QUOTES) ?>`)"
                                class="w-10 h-10 rounded-full bg-stone-50 dark:bg-stone-800 flex items-center justify-center text-stone-400 dark:text-stone-500 hover:bg-tan hover:text-white dark:hover:bg-tan dark:hover:text-white transition-all shadow-sm cursor-pointer z-20 relative">
                            <span class="material-symbols-outlined text-lg">edit</span>
                        </button>

                        <?php if(!$is_online): ?>
                        <a href="?delete=<?= $usr['id'] ?>" onclick="return confirm('Hapus pengguna ini selamanya?')" class="w-10 h-10 rounded-full bg-stone-50 dark:bg-stone-800 flex items-center justify-center text-stone-400 dark:text-stone-500 hover:bg-red-500 hover:text-white transition-all shadow-sm cursor-pointer z-20 relative">
                            <span class="material-symbols-outlined text-lg">delete</span>
                        </a>
                        <?php else: ?>
                        <button type="button" onclick="alert('User sedang online!')" class="w-10 h-10 rounded-full bg-stone-50 dark:bg-stone-800 flex items-center justify-center text-stone-300 dark:text-stone-600 cursor-not-allowed z-20 relative">
                            <span class="material-symbols-outlined text-lg">lock</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="space-y-3 relative z-10">
                    <div class="flex items-center gap-3 text-sm text-stone-600 dark:text-stone-400 bg-stone-50/50 dark:bg-stone-800/50 p-3 rounded-xl">
                        <span class="material-symbols-outlined text-tan">mail</span>
                        <span class="truncate"><?= $usr['email'] ?></span>
                    </div>
                    <div class="flex items-center gap-3 text-sm text-stone-600 dark:text-stone-400 bg-stone-50/50 dark:bg-stone-800/50 p-3 rounded-xl">
                        <span class="material-symbols-outlined text-tan">badge</span>
                        <span class="truncate font-mono"><?= $usr['nik'] ? $usr['nik'] : 'No NIK' ?></span>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-between border-t border-tan/20 dark:border-stone-800 pt-4 relative z-10">
                    <div class="text-xs text-stone-500 dark:text-stone-500">
                        Joined: <span class="font-bold"><?= date('d M Y', strtotime($usr['created_at'])) ?></span>
                    </div>
                    <div>
                        <?php if($is_online): ?>
                            <span class="flex items-center gap-1.5 text-[10px] font-bold text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20 px-2 py-1 rounded-lg">
                                <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> ONLINE
                            </span>
                        <?php else: ?>
                            <span class="flex items-center gap-1.5 text-[10px] font-bold text-stone-400 dark:text-stone-500 bg-stone-100 dark:bg-stone-800 px-2 py-1 rounded-lg">
                                <span class="w-2 h-2 rounded-full bg-stone-300 dark:bg-stone-600"></span> OFFLINE
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <div class="mt-8 flex justify-center items-center gap-4">
            <?php if($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>" class="px-4 py-2 bg-white dark:bg-stone-800 border border-tan/20 dark:border-stone-700 rounded-xl hover:bg-cream dark:hover:bg-stone-700 transition-colors text-sm font-bold text-primary dark:text-sage shadow-sm">Previous</a>
            <?php endif; ?>
            <span class="text-sm font-bold text-stone-500 dark:text-stone-400">Page <?= $page ?> of <?= $total_pages ?></span>
            <?php if($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>" class="px-4 py-2 bg-white dark:bg-stone-800 border border-tan/20 dark:border-stone-700 rounded-xl hover:bg-cream dark:hover:bg-stone-700 transition-colors text-sm font-bold text-primary dark:text-sage shadow-sm">Next</a>
            <?php endif; ?>
        </div>

    </main>
</div>

<div id="addSellerModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 transition-opacity duration-300">
    <div class="modal-overlay absolute w-full h-full bg-stone-900/60 backdrop-blur-sm" onclick="toggleModal('addSellerModal')"></div>
    <div class="modal-container bg-white dark:bg-stone-900 w-11/12 md:max-w-md mx-auto rounded-[2rem] shadow-2xl z-50 overflow-y-auto p-8 transform transition-all scale-95 duration-300 border border-tan/20 dark:border-stone-700">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-primary dark:text-sage title-font">Tambah Penjual</h3>
            <button onclick="toggleModal('addSellerModal')" class="w-8 h-8 rounded-full bg-stone-100 dark:bg-stone-800 flex items-center justify-center text-stone-400 hover:bg-red-100 hover:text-red-500 transition-colors"><span class="material-symbols-outlined text-lg">close</span></button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <div><label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1.5 ml-1">Nama Toko</label><input type="text" name="full_name" required class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 transition-all text-stone-800 dark:text-stone-200"></div>
            <div><label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1.5 ml-1">NIK (Wajib)</label><input type="number" name="nik" required class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 transition-all text-stone-800 dark:text-stone-200"></div>
            <div><label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1.5 ml-1">Email</label><input type="email" name="email" required class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 transition-all text-stone-800 dark:text-stone-200"></div>
            <div><label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1.5 ml-1">Password</label><input type="password" name="password" required class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 transition-all text-stone-800 dark:text-stone-200"></div>
            <button type="submit" name="add_seller" class="w-full py-4 mt-4 bg-primary dark:bg-sage text-white dark:text-primary font-bold rounded-xl hover:bg-chocolate dark:hover:bg-tan transition-all shadow-lg">Simpan Data</button>
        </form>
    </div>
</div>

<div id="editUserModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 transition-opacity duration-300">
    <div class="modal-overlay absolute w-full h-full bg-stone-900/60 backdrop-blur-sm" onclick="toggleModal('editUserModal')"></div>
    <div class="modal-container bg-white dark:bg-stone-900 w-11/12 md:max-w-md mx-auto rounded-[2rem] shadow-2xl z-50 overflow-y-auto p-8 transform transition-all scale-95 duration-300 max-h-[90vh] border border-tan/20 dark:border-stone-700">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-primary dark:text-sage title-font">Edit Data User</h3>
            <button onclick="toggleModal('editUserModal')" class="w-8 h-8 rounded-full bg-stone-100 dark:bg-stone-800 flex items-center justify-center text-stone-400 hover:bg-red-100 hover:text-red-500 transition-colors"><span class="material-symbols-outlined text-lg">close</span></button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="id" id="edit_id">

            <div>
                <label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1.5 ml-1">Nama Lengkap / Toko</label>
                <input type="text" name="full_name" id="edit_name" required class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 transition-all text-stone-800 dark:text-stone-200">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1.5 ml-1">Email</label>
                <input type="email" name="email" id="edit_email" required class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 transition-all text-stone-800 dark:text-stone-200">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1.5 ml-1">NIK</label>
                    <input type="text" name="nik" id="edit_nik" class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 transition-all text-stone-800 dark:text-stone-200">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1.5 ml-1">Role</label>
                    <select name="role" id="edit_role" class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 transition-all text-sm text-stone-800 dark:text-stone-200">
                        <option value="buyer">Buyer</option>
                        <option value="seller">Seller</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1.5 ml-1">Alamat</label>
                <textarea name="address" id="edit_address" rows="2" class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 transition-all text-stone-800 dark:text-stone-200"></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1.5 ml-1">Reset Password</label>
                <input type="password" name="password" placeholder="Kosongkan jika tidak ingin mengubah" class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 transition-all text-stone-800 dark:text-stone-200">
                <p class="text-[10px] text-orange-500 mt-1 ml-1">*Isi hanya jika ingin mereset password.</p>
            </div>

            <button type="submit" name="edit_user" class="w-full py-4 mt-4 bg-tan text-white font-bold rounded-xl hover:bg-chocolate transition-all shadow-lg">Update Data</button>
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
