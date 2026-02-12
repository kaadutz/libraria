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

$user_id = $_SESSION['user_id'];
$alert = '';

// --- UPDATE PROFIL & FOTO ---
if (isset($_POST['update_profile'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);

    // 1. VALIDASI DUPLIKAT EMAIL
    // Cek apakah email sudah dipakai user lain (selain user yang sedang login)
    $check_duplicate = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' AND id != '$user_id'");

    if (mysqli_num_rows($check_duplicate) > 0) {
        // Jika ditemukan data, tampilkan Pop Up Gagal
        $alert = "<script>
            alert('GAGAL UPDATE: Email \'$email\' sudah digunakan oleh pengguna lain! Silakan gunakan email berbeda.');
        </script>";
    } else {
        // Jika aman, Lanjutkan Proses Update

        // Update Text Data Dulu
        $update = "UPDATE users SET full_name = '$full_name', email = '$email' WHERE id = '$user_id'";

        if(mysqli_query($conn, $update)) {
            $_SESSION['full_name'] = $full_name; // Update session nama
            $alert = "<script>alert('Profil Berhasil Diperbarui!'); window.location='profileadmin.php';</script>";

            // Proses Upload Foto (Hanya jika ada file yang diupload)
            if (!empty($_FILES['profile_image']['name'])) {
                $target_dir = "../assets/uploads/profiles/";

                // Buat folder jika belum ada
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                $file_extension = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
                $new_filename = "profile_" . $user_id . "_" . time() . "." . $file_extension;
                $target_file = $target_dir . $new_filename;

                // Validasi Format
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array($file_extension, $allowed)) {
                    // Cek size (opsional, misal max 2MB)
                    if ($_FILES["profile_image"]["size"] <= 2000000) {
                        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                            // Update DB dengan nama file baru
                            mysqli_query($conn, "UPDATE users SET profile_image = '$new_filename' WHERE id = '$user_id'");
                            // Refresh alert sukses
                            $alert = "<script>alert('Profil & Foto Berhasil Diperbarui!'); window.location='profileadmin.php';</script>";
                        } else {
                            $alert = "<script>alert('Gagal mengupload gambar ke folder tujuan.');</script>";
                        }
                    } else {
                        $alert = "<script>alert('Ukuran file terlalu besar (Max 2MB).');</script>";
                    }
                } else {
                    $alert = "<script>alert('Format file harus JPG, JPEG, atau PNG!');</script>";
                }
            }
        } else {
            $alert = "<script>alert('Terjadi kesalahan database: " . mysqli_error($conn) . "');</script>";
        }
    }
}

// --- GANTI PASSWORD ---
if (isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass     = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    $get_user = mysqli_query($conn, "SELECT password FROM users WHERE id = '$user_id'");
    $row = mysqli_fetch_assoc($get_user);

    if ($current_pass !== $row['password']) {
        $alert = "<script>alert('GAGAL: Password saat ini salah!');</script>";
    } elseif ($new_pass !== $confirm_pass) {
        $alert = "<script>alert('GAGAL: Konfirmasi password baru tidak cocok!');</script>";
    } else {
        mysqli_query($conn, "UPDATE users SET password = '$new_pass' WHERE id = '$user_id'");
        $alert = "<script>alert('Password Berhasil Diubah! Silakan login ulang.'); window.location='../auth/logout.php';</script>";
    }
}

// Ambil Data Terbaru untuk ditampilkan di Form
$query_user = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
$data = mysqli_fetch_assoc($query_user);
$admin_name = $_SESSION['full_name'];

// URL Foto Profil Logic
$profile_pic = !empty($data['profile_image']) ? "../assets/uploads/profiles/" . $data['profile_image'] : "../assets/images/default_profile.png";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Profil Admin - Libraria</title>

<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
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

    /* Sidebar Logic */
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
    <?= $alert ?>

<div class="flex min-h-screen">

    <aside id="sidebar" class="w-64 bg-white border-r border-[var(--border-color)] flex flex-col fixed h-full z-30 overflow-hidden shadow-lg lg:shadow-none">
        <div id="sidebar-header" class="h-28 flex items-center border-b border-[var(--border-color)] shrink-0">
            <img id="sidebar-logo" src="../assets/images/logo.png" alt="Logo" class="object-contain flex-shrink-0">
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
                <div><h2 class="text-xl lg:text-2xl title-font text-[var(--text-dark)] hidden md:block">Pengaturan Akun</h2></div>
            </div>
            <div class="flex items-center gap-4 relative">

<button onclick="toggleDarkMode()" class="w-10 h-10 rounded-full bg-white/10 border border-[var(--border-color)] text-[var(--text-muted)] hover:text-[var(--deep-forest)] hover:bg-[var(--light-sage)]/30 transition-all flex items-center justify-center group mr-2" title="Toggle Dark Mode">
    <span class="material-symbols-outlined group-hover:rotate-180 transition-transform duration-500" id="dark-mode-icon">dark_mode</span>
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
                    <a href="profileadmin.php" class="flex items-center gap-2 px-4 py-3 text-sm text-[var(--deep-forest)] bg-[var(--light-sage)]/30 font-bold transition-colors"><span class="material-symbols-outlined text-[20px]">person</span> My Profile</a>
                    <a href="../auth/logout.php" class="flex items-center gap-2 px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors"><span class="material-symbols-outlined text-[20px]">logout</span> Log Out</a>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-1" data-aos="fade-up">
                <div class="bg-white rounded-[2.5rem] p-8 border border-[var(--border-color)] card-shadow text-center relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-32 bg-[var(--deep-forest)] z-0"></div>
                    <div class="absolute top-0 left-0 w-full h-32 bg-[url('https://www.transparenttextures.com/patterns/paper.png')] opacity-20 z-0"></div>

                    <div class="relative z-10">
                        <div class="w-32 h-32 mx-auto bg-white rounded-full p-2 mb-4 shadow-lg">
                            <img src="<?= $profile_pic ?>" alt="Profile" class="w-full h-full rounded-full object-cover border-4 border-[var(--cream-bg)]">
                        </div>

                        <h2 class="text-2xl font-bold text-[var(--text-dark)] title-font mb-1"><?= $data['full_name'] ?></h2>
                        <span class="inline-block px-4 py-1.5 rounded-full bg-[var(--light-sage)]/50 text-[var(--deep-forest)] text-xs font-bold uppercase tracking-wider mb-6">
                            Super Administrator
                        </span>

                        <div class="space-y-4 text-left bg-[var(--cream-bg)]/50 p-6 rounded-3xl border border-[var(--border-color)]">
                            <div class="flex items-center gap-3 text-sm">
                                <span class="material-symbols-outlined text-[var(--text-muted)]">mail</span>
                                <span class="text-[var(--text-dark)] font-medium truncate"><?= $data['email'] ?></span>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                <span class="material-symbols-outlined text-[var(--text-muted)]">calendar_month</span>
                                <span class="text-[var(--text-dark)] font-medium">Joined <?= date('M Y', strtotime($data['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-8">

                <div class="bg-white rounded-[2.5rem] p-8 border border-[var(--border-color)] card-shadow" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="w-10 h-10 rounded-full bg-[var(--light-sage)] flex items-center justify-center text-[var(--deep-forest)]">
                            <span class="material-symbols-outlined">edit_note</span>
                        </span>
                        <h3 class="text-xl font-bold text-[var(--text-dark)] title-font">Edit Data Diri</h3>
                    </div>

                    <form action="" method="POST" enctype="multipart/form-data" class="space-y-5">
                        <div>
                            <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Foto Profil</label>
                            <input type="file" name="profile_image" accept="image/*" class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-[var(--deep-forest)] file:text-white hover:file:bg-[var(--chocolate-brown)]">
                            <p class="text-[10px] text-gray-400 mt-1 ml-1">Format: JPG, PNG, GIF. Maks 2MB.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Nama Lengkap</label>
                                <input type="text" name="full_name" value="<?= $data['full_name'] ?>" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Email Address</label>
                                <input type="email" name="email" value="<?= $data['email'] ?>" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all">
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" name="update_profile" class="px-8 py-3 bg-[var(--deep-forest)] text-white font-bold rounded-xl hover:bg-[var(--chocolate-brown)] hover:-translate-y-1 transition-all shadow-lg shadow-green-900/10 flex items-center gap-2">
                                <span class="material-symbols-outlined text-lg">save</span> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>

                <div class="bg-white rounded-[2.5rem] p-8 border border-[var(--border-color)] card-shadow" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center text-orange-600">
                            <span class="material-symbols-outlined">lock_reset</span>
                        </span>
                        <h3 class="text-xl font-bold text-[var(--text-dark)] title-font">Ganti Password</h3>
                    </div>

                    <form action="" method="POST" class="space-y-5">
                        <div>
                            <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Password Saat Ini</label>
                            <input type="password" name="current_password" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Password Baru</label>
                                <input type="password" name="new_password" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-1.5 ml-1">Konfirmasi Password</label>
                                <input type="password" name="confirm_password" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all">
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" name="change_password" class="px-8 py-3 bg-[var(--warm-tan)] text-white font-bold rounded-xl hover:bg-[var(--chocolate-brown)] hover:-translate-y-1 transition-all shadow-lg shadow-orange-900/10 flex items-center gap-2">
                                <span class="material-symbols-outlined text-lg">key</span> Update Password
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
</script>

</body>
</html>
