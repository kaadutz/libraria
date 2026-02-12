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
<title>Profil Admin - Libraria</title>

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
    <?= $alert ?>

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
                <div><h2 class="text-xl lg:text-2xl title-font text-stone-800 dark:text-stone-200 hidden md:block">Pengaturan Akun</h2></div>
            </div>
            <div class="flex items-center gap-4 relative">

            <div class="flex items-center gap-4 relative">
                <!-- DARK MODE TOGGLE -->
                <button onclick="toggleDarkMode()" class="w-10 h-10 flex items-center justify-center rounded-full text-stone-500 dark:text-stone-400 hover:bg-primary hover:text-white dark:hover:bg-stone-800 transition-all duration-300">
                    <span class="material-icons-outlined text-xl">dark_mode</span>
                </button>

<button onclick="toggleProfileDropdown()" class="flex items-center gap-3 bg-white p-1.5 pr-4 rounded-full border border-tan/20 dark:border-stone-800 card-shadow hover:shadow-md transition-all focus:outline-none">
                    <img src="<?= $profile_pic ?>" alt="Profile" class="w-9 h-9 rounded-full object-cover border-2 border-[var(--cream-bg)]">
                    <div class="text-left hidden sm:block">
                        <p class="text-xs font-bold leading-none title-font"><?= $admin_name ?></p>
                        <p class="text-[10px] text-tan leading-none mt-1 font-bold uppercase">Super Admin</p>
                    </div>
                    <span class="material-symbols-outlined text-[18px] text-stone-500 dark:text-stone-400">expand_more</span>
                </button>
                <div id="profileDropdown" class="absolute right-0 top-14 w-56 bg-white rounded-2xl shadow-xl border border-tan/20 dark:border-stone-800 py-2 hidden transform origin-top-right transition-all z-50">
                    <a href="profileadmin.php" class="flex items-center gap-2 px-4 py-3 text-sm text-primary dark:text-sage bg-sage/30 font-bold transition-colors"><span class="material-symbols-outlined text-[20px]">person</span> My Profile</a>
                    <a href="../auth/logout.php" class="flex items-center gap-2 px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors"><span class="material-symbols-outlined text-[20px]">logout</span> Log Out</a>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-1" data-aos="fade-up">
                <div class="bg-white rounded-[2.5rem] p-8 border border-tan/20 dark:border-stone-800 card-shadow text-center relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-32 bg-primary z-0"></div>
                    <div class="absolute top-0 left-0 w-full h-32 bg-[url('https://www.transparenttextures.com/patterns/paper.png')] opacity-20 z-0"></div>

                    <div class="relative z-10">
                        <div class="w-32 h-32 mx-auto bg-white rounded-full p-2 mb-4 shadow-lg">
                            <img src="<?= $profile_pic ?>" alt="Profile" class="w-full h-full rounded-full object-cover border-4 border-[var(--cream-bg)]">
                        </div>

                        <h2 class="text-2xl font-bold text-stone-800 dark:text-stone-200 title-font mb-1"><?= $data['full_name'] ?></h2>
                        <span class="inline-block px-4 py-1.5 rounded-full bg-sage/50 text-primary dark:text-sage text-xs font-bold uppercase tracking-wider mb-6">
                            Super Administrator
                        </span>

                        <div class="space-y-4 text-left bg-cream dark:bg-stone-800/50 p-6 rounded-3xl border border-tan/20 dark:border-stone-800">
                            <div class="flex items-center gap-3 text-sm">
                                <span class="material-symbols-outlined text-stone-500 dark:text-stone-400">mail</span>
                                <span class="text-stone-800 dark:text-stone-200 font-medium truncate"><?= $data['email'] ?></span>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                <span class="material-symbols-outlined text-stone-500 dark:text-stone-400">calendar_month</span>
                                <span class="text-stone-800 dark:text-stone-200 font-medium">Joined <?= date('M Y', strtotime($data['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-8">

                <div class="bg-white rounded-[2.5rem] p-8 border border-tan/20 dark:border-stone-800 card-shadow" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="w-10 h-10 rounded-full bg-sage flex items-center justify-center text-primary dark:text-sage">
                            <span class="material-symbols-outlined">edit_note</span>
                        </span>
                        <h3 class="text-xl font-bold text-stone-800 dark:text-stone-200 title-font">Edit Data Diri</h3>
                    </div>

                    <form action="" method="POST" enctype="multipart/form-data" class="space-y-5">
                        <div>
                            <label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1.5 ml-1">Foto Profil</label>
                            <input type="file" name="profile_image" accept="image/*" class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white focus:border-tan focus:ring-0 transition-all text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-white hover:file:bg-chocolate">
                            <p class="text-[10px] text-gray-400 mt-1 ml-1">Format: JPG, PNG, GIF. Maks 2MB.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1.5 ml-1">Nama Lengkap</label>
                                <input type="text" name="full_name" value="<?= $data['full_name'] ?>" required class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border border-transparent focus:bg-white focus:border-tan focus:ring-0 transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1.5 ml-1">Email Address</label>
                                <input type="email" name="email" value="<?= $data['email'] ?>" required class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border border-transparent focus:bg-white focus:border-tan focus:ring-0 transition-all">
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" name="update_profile" class="px-8 py-3 bg-primary text-white font-bold rounded-xl hover:bg-chocolate hover:-translate-y-1 transition-all shadow-lg shadow-green-900/10 flex items-center gap-2">
                                <span class="material-symbols-outlined text-lg">save</span> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>

                <div class="bg-white rounded-[2.5rem] p-8 border border-tan/20 dark:border-stone-800 card-shadow" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center text-orange-600">
                            <span class="material-symbols-outlined">lock_reset</span>
                        </span>
                        <h3 class="text-xl font-bold text-stone-800 dark:text-stone-200 title-font">Ganti Password</h3>
                    </div>

                    <form action="" method="POST" class="space-y-5">
                        <div>
                            <label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1.5 ml-1">Password Saat Ini</label>
                            <input type="password" name="current_password" required class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border border-transparent focus:bg-white focus:border-tan focus:ring-0 transition-all">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1.5 ml-1">Password Baru</label>
                                <input type="password" name="new_password" required class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border border-transparent focus:bg-white focus:border-tan focus:ring-0 transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1.5 ml-1">Konfirmasi Password</label>
                                <input type="password" name="confirm_password" required class="w-full px-4 py-3 rounded-xl bg-cream dark:bg-stone-800 border border-transparent focus:bg-white focus:border-tan focus:ring-0 transition-all">
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" name="change_password" class="px-8 py-3 bg-tan text-white font-bold rounded-xl hover:bg-chocolate hover:-translate-y-1 transition-all shadow-lg shadow-orange-900/10 flex items-center gap-2">
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
