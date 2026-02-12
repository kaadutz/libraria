<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../config/db.php';

// 1. Cek Keamanan
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit;
}

$buyer_id = $_SESSION['user_id'];
$swal_alert = ""; // Variabel untuk menampung script SweetAlert

// --- 2. LOGIC UPDATE PROFIL ---
if (isset($_POST['update_profile'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $nik       = mysqli_real_escape_string($conn, $_POST['nik']);
    $address   = mysqli_real_escape_string($conn, $_POST['address']);

    // CEK DUPLIKAT (Email atau NIK sudah dipakai orang lain?)
    $check = mysqli_query($conn, "SELECT id FROM users WHERE (email = '$email' OR nik = '$nik') AND id != '$buyer_id'");

    if (mysqli_num_rows($check) > 0) {
        // JIKA DUPLIKAT -> MUNCUL POPUP ERROR
        $swal_alert = "
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Gagal Memperbarui!',
                text: 'Email atau NIK sudah digunakan oleh pengguna lain.',
                confirmButtonColor: '#d33',
                confirmButtonText: 'Coba Lagi'
            });
        </script>";
    } else {
        // JIKA AMAN -> LANJUT UPDATE

        // Logic Upload Foto
        $img_sql = "";
        if (!empty($_FILES['profile_image']['name'])) {
            $target_dir = "../assets/uploads/profiles/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $new_name = "profile_" . $buyer_id . "_" . time() . "." . $ext;

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_dir . $new_name);
                $img_sql = ", profile_image='$new_name'";
            }
        }

        // Update Database (TANPA PHONE)
        $query_update = "UPDATE users SET full_name='$full_name', email='$email', nik='$nik', address='$address' $img_sql WHERE id='$buyer_id'";

        if (mysqli_query($conn, $query_update)) {
            $_SESSION['full_name'] = $full_name;
            $swal_alert = "
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Profil Anda telah diperbarui.',
                    confirmButtonColor: '#3E4B1C',
                    confirmButtonText: 'Oke'
                });
            </script>";
        } else {
            $swal_alert = "<script>Swal.fire('Error', 'Terjadi kesalahan sistem.', 'error');</script>";
        }
    }
}

// --- 3. LOGIC GANTI PASSWORD ---
if (isset($_POST['change_password'])) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass === $confirm_pass) {
        $query_pass = "UPDATE users SET password='$new_pass' WHERE id='$buyer_id'";
        if(mysqli_query($conn, $query_pass)){
            $swal_alert = "
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Password Diubah!',
                    text: 'Silakan login ulang dengan password baru nanti.',
                    confirmButtonColor: '#3E4B1C'
                });
            </script>";
        }
    } else {
        $swal_alert = "
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: 'Konfirmasi password tidak cocok.',
                confirmButtonColor: '#d33'
            });
        </script>";
    }
}

// --- 4. AMBIL DATA USER TERBARU ---
$query_user = mysqli_query($conn, "SELECT * FROM users WHERE id = '$buyer_id'");
$user = mysqli_fetch_assoc($query_user);
$profile_pic = !empty($user['profile_image']) ? "../assets/uploads/profiles/" . $user['profile_image'] : "../assets/images/default_profile.png";
$buyer_name = $user['full_name'];

// --- DATA NAVBAR ---
$query_cart = mysqli_query($conn, "SELECT SUM(qty) as total FROM carts WHERE buyer_id = '$buyer_id'");
$cart_count = mysqli_fetch_assoc($query_cart)['total'] ?? 0;
$query_notif = mysqli_query($conn, "SELECT COUNT(*) as total FROM messages WHERE receiver_id = '$buyer_id' AND is_read = 0");
$total_notif = mysqli_fetch_assoc($query_notif)['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Profil Saya - Libraria</title>

    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            --text-dark: #2D2418;
            --text-muted: #6B6155;
            --border-color: #E6E1D3;
        }
        body { font-family: 'Quicksand', sans-serif; background-color: var(--cream-bg); color: var(--text-dark); }
        .font-logo { font-family: 'Cinzel', serif; }
        .card-shadow { box-shadow: 0 10px 40px -10px rgba(62, 75, 28, 0.08); }

        /* Custom Font untuk SweetAlert */
        .swal2-popup { font-family: 'Quicksand', sans-serif !important; border-radius: 1.5rem !important; }
    </style>
<script src="../assets/js/theme-manager.js"></script>
</head>
<body class="overflow-x-hidden min-h-screen flex flex-col">

    <nav class="fixed top-0 w-full z-50 px-4 sm:px-6 lg:px-8 pt-4 transition-all duration-300" id="navbar">
        <div class="bg-white/90 backdrop-blur-md rounded-3xl border border-[var(--border-color)] shadow-sm max-w-7xl mx-auto px-4 py-3">
            <div class="flex justify-between items-center gap-4">
                <a href="index.php" class="flex items-center gap-3 group shrink-0">
                    <img src="../assets/images/logo.png" alt="Logo" class="h-10 w-auto group-hover:scale-110 transition-transform duration-300">
                    <div class="flex flex-col">
                        <span class="text-xl font-bold text-[var(--deep-forest)] font-logo tracking-wide leading-none">LIBRARIA</span>
                    </div>
                </a>

                <div class="hidden md:flex flex-1 max-w-xl mx-auto">
                    <form action="index.php" method="GET" class="w-full relative group">
                        <input type="text" name="s" placeholder="Cari buku..." class="w-full pl-10 pr-4 py-2 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all text-sm shadow-inner">
                        <span class="material-symbols-outlined absolute left-3 top-2 text-[var(--text-muted)] group-focus-within:text-[var(--warm-tan)] text-lg">search</span>
                    </form>
                </div>

                <div class="flex items-center gap-2">

<button onclick="toggleDarkMode()" class="w-10 h-10 rounded-full bg-white/10 border border-[var(--border-color)] text-[var(--text-muted)] hover:text-[var(--deep-forest)] hover:bg-[var(--light-sage)]/30 transition-all flex items-center justify-center group mr-2" title="Toggle Dark Mode">
    <span class="material-symbols-outlined group-hover:rotate-180 transition-transform duration-500" id="dark-mode-icon">dark_mode</span>
</button>

                    <div class="hidden lg:flex items-center gap-1 text-sm font-bold text-[var(--text-muted)] mr-2">
                        <a href="index.php" class="px-3 py-2 rounded-xl hover:bg-[var(--cream-bg)] hover:text-[var(--deep-forest)] transition-colors">Beranda</a>
                        <a href="my_orders.php" class="px-3 py-2 rounded-xl hover:bg-[var(--cream-bg)] hover:text-[var(--deep-forest)] transition-colors">Pesanan</a>
                        <a href="chat_list.php" class="px-3 py-2 rounded-xl hover:bg-[var(--cream-bg)] hover:text-[var(--deep-forest)] transition-colors">Chat</a>
                    </div>

                    <a href="help.php" class="w-10 h-10 flex items-center justify-center rounded-full text-[var(--text-muted)] hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] transition-all">
                        <span class="material-symbols-outlined">help</span>
                    </a>

                    <div class="relative">
                        <button onclick="toggleDropdown('notificationDropdown')" class="w-10 h-10 flex items-center justify-center rounded-full text-[var(--text-muted)] hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] transition-all relative">
                            <span class="material-symbols-outlined">notifications</span>
                            <?php if($total_notif > 0): ?><span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white animate-ping"></span><?php endif; ?>
                        </button>
                        <div id="notificationDropdown" class="absolute right-0 mt-3 w-72 bg-white rounded-2xl shadow-xl border border-[var(--border-color)] py-2 hidden transform origin-top-right transition-all z-50">
                            <div class="px-4 py-3 border-b border-[var(--border-color)]"><h4 class="font-bold text-[var(--deep-forest)] text-sm">Notifikasi</h4></div>
                            <div class="max-h-64 overflow-y-auto">
                                <?php if($total_notif == 0): ?><div class="text-center py-6 text-gray-400 text-xs italic">Tidak ada notifikasi baru.</div><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <a href="cart.php" class="relative w-10 h-10 flex items-center justify-center rounded-full border border-[var(--border-color)] bg-white text-[var(--text-muted)] hover:text-[var(--deep-forest)] hover:shadow-md transition-all">
                        <span class="material-symbols-outlined">shopping_bag</span>
                        <span id="cart-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold h-5 w-5 flex items-center justify-center rounded-full border-2 border-white animate-bounce <?= $cart_count > 0 ? '' : 'hidden' ?>"><?= $cart_count ?></span>
                    </a>

                    <div class="relative ml-1">
                        <button onclick="toggleDropdown('profileDropdown')" class="flex items-center gap-2 pl-1 pr-1 md:pr-3 py-1 rounded-full border border-[var(--warm-tan)] bg-white shadow-sm hover:shadow-md transition-all duration-300 focus:outline-none">
                            <img src="<?= $profile_pic ?>" class="h-9 w-9 rounded-full object-cover border border-[var(--warm-tan)]">
                            <div class="hidden md:block text-left">
                                <p class="text-[10px] text-[var(--text-muted)] font-bold uppercase leading-none mb-0.5">Hi,</p>
                                <p class="text-xs font-bold text-[var(--deep-forest)] leading-none truncate max-w-[80px]"><?= explode(' ', $buyer_name)[0] ?></p>
                            </div>
                            <span class="material-symbols-outlined text-[var(--text-muted)] text-sm hidden md:block">expand_more</span>
                        </button>
                        <div id="profileDropdown" class="absolute right-0 mt-3 w-56 bg-white rounded-2xl shadow-xl border border-[var(--border-color)] py-2 hidden transform origin-top-right transition-all z-50">
                            <a href="profile.php" class="flex items-center gap-3 px-4 py-2 bg-[var(--cream-bg)] text-sm font-bold text-[var(--deep-forest)]"><span class="material-symbols-outlined text-lg">person</span> Akun Saya</a>
                            <div class="border-t border-[var(--border-color)] my-1"></div>
                            <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-2 hover:bg-red-50 text-sm font-bold text-red-600 transition-colors"><span class="material-symbols-outlined text-lg">logout</span> Keluar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-1 pt-32 pb-12 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto w-full">

        <h1 class="text-3xl lg:text-4xl font-bold text-[var(--deep-forest)] title-font mb-8 text-center" data-aos="fade-down">Pengaturan Akun</h1>

        <div class="flex flex-col lg:flex-row gap-8" data-aos="fade-up">

            <div class="lg:w-1/3 space-y-6">
                <div class="bg-white rounded-[2.5rem] p-8 border border-[var(--border-color)] card-shadow text-center relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-24 bg-[var(--deep-forest)] opacity-10"></div>
                    <div class="absolute -right-10 -top-10 w-40 h-40 bg-[var(--light-sage)] rounded-full blur-3xl opacity-30"></div>

                    <div class="relative z-10">
                        <div class="w-32 h-32 mx-auto rounded-full p-1 bg-white border-4 border-[var(--cream-bg)] shadow-lg mb-4">
                            <img src="<?= $profile_pic ?>" id="previewImg" class="w-full h-full rounded-full object-cover">
                        </div>
                        <h2 class="text-xl font-bold text-[var(--text-dark)]"><?= $user['full_name'] ?></h2>
                        <p class="text-sm text-[var(--text-muted)]"><?= $user['email'] ?></p>

                        <div class="mt-6 pt-6 border-t border-dashed border-[var(--border-color)]">
                            <p class="text-xs text-[var(--text-muted)] uppercase font-bold tracking-widest mb-1">Bergabung Sejak</p>
                            <p class="text-sm font-bold text-[var(--deep-forest)]"><?= date('d F Y', strtotime($user['created_at'])) ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-[var(--deep-forest)] text-white p-6 rounded-[2rem] shadow-xl relative overflow-hidden">
                    <div class="absolute -right-10 -bottom-10 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
                    <h4 class="font-bold text-lg mb-2">Butuh Bantuan?</h4>
                    <p class="text-xs text-white/80 mb-4">Jika Anda mengalami kendala dengan akun Anda, silakan hubungi kami.</p>
                    <a href="help.php" class="inline-flex items-center gap-2 px-4 py-2 bg-white/20 hover:bg-white/30 rounded-xl text-xs font-bold transition-colors">
                        <span class="material-symbols-outlined text-sm">support_agent</span> Hubungi Admin
                    </a>
                </div>
            </div>

            <div class="lg:w-2/3 space-y-8">

                <form method="POST" enctype="multipart/form-data" class="bg-white rounded-[2.5rem] p-8 border border-[var(--border-color)] card-shadow">
                    <h3 class="text-xl font-bold text-[var(--deep-forest)] mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined">edit_square</span> Edit Informasi
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-xs font-bold text-[var(--text-muted)] uppercase mb-2">Nama Lengkap</label>
                            <input type="text" name="full_name" value="<?= $user['full_name'] ?>" class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all text-sm font-bold text-[var(--text-dark)]" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-[var(--text-muted)] uppercase mb-2">Email</label>
                            <input type="email" name="email" value="<?= $user['email'] ?>" class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all text-sm font-bold text-[var(--text-dark)]" required>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-[var(--text-muted)] uppercase mb-2">NIK / Identitas</label>
                            <input type="number" name="nik" value="<?= $user['nik'] ?>" class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all text-sm font-bold text-[var(--text-dark)]">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-[var(--text-muted)] uppercase mb-2">Ganti Foto Profil</label>
                            <input type="file" name="profile_image" id="fileInput" accept="image/*" class="w-full text-sm text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-[var(--light-sage)] file:text-[var(--deep-forest)] hover:file:bg-[var(--warm-tan)] hover:file:text-white transition-all cursor-pointer">
                        </div>
                    </div>

                    <div class="mb-8">
                        <label class="block text-xs font-bold text-[var(--text-muted)] uppercase mb-2">Alamat Lengkap</label>
                        <textarea name="address" rows="3" class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all text-sm font-bold text-[var(--text-dark)]"><?= $user['address'] ?></textarea>
                    </div>

                    <div class="text-right">
                        <button type="submit" name="update_profile" class="px-8 py-3 bg-[var(--deep-forest)] text-white font-bold rounded-xl hover:bg-[var(--chocolate-brown)] transition-all shadow-lg flex items-center gap-2 ml-auto">
                            <span class="material-symbols-outlined text-sm">save</span> Simpan Perubahan
                        </button>
                    </div>
                </form>

                <form method="POST" class="bg-white rounded-[2.5rem] p-8 border border-[var(--border-color)] card-shadow relative overflow-hidden">
                    <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-red-50 rounded-full blur-3xl opacity-50"></div>

                    <h3 class="text-xl font-bold text-[var(--deep-forest)] mb-6 flex items-center gap-2 relative z-10">
                        <span class="material-symbols-outlined">lock_reset</span> Ganti Password
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 relative z-10">
                        <div>
                            <label class="block text-xs font-bold text-[var(--text-muted)] uppercase mb-2">Password Baru</label>
                            <input type="password" name="new_password" class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all text-sm" placeholder="••••••••" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-[var(--text-muted)] uppercase mb-2">Konfirmasi Password</label>
                            <input type="password" name="confirm_password" class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all text-sm" placeholder="••••••••" required>
                        </div>
                    </div>

                    <div class="text-right relative z-10">
                        <button type="submit" name="change_password" class="px-8 py-3 bg-white border border-[var(--border-color)] text-[var(--deep-forest)] font-bold rounded-xl hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-all shadow-sm flex items-center gap-2 ml-auto">
                            <span class="material-symbols-outlined text-sm">key</span> Update Password
                        </button>
                    </div>
                </form>

            </div>
        </div>

    </main>

    <footer class="bg-white border-t border-[var(--border-color)] py-10 mt-auto">
        <div class="max-w-7xl mx-auto px-6 text-center">
            <h2 class="text-2xl font-bold text-[var(--deep-forest)] font-logo mb-2 tracking-widest">LIBRARIA</h2>
            <p class="text-xs text-[var(--text-muted)] mb-6">Platform jual beli buku terpercaya untuk masa depan literasi.</p>
            <p class="text-[10px] text-[var(--text-muted)] font-bold tracking-widest uppercase">&copy; 2025 Libraria Bookstore. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ once: true, duration: 800, offset: 50 });

        function toggleDropdown(id) {
            const dropdown = document.getElementById(id);
            const allDropdowns = document.querySelectorAll('[id$="Dropdown"]');
            allDropdowns.forEach(dd => { if(dd.id !== id) dd.classList.add('hidden'); });
            if(dropdown) dropdown.classList.toggle('hidden');
        }

        window.onclick = function(event) {
            if (!event.target.closest('button')) {
                const dropdowns = document.querySelectorAll('[id$="Dropdown"]');
                dropdowns.forEach(dd => dd.classList.add('hidden'));
            }
        }

        const fileInput = document.getElementById('fileInput');
        const previewImg = document.getElementById('previewImg');

        fileInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>

    <?= $swal_alert ?>

</body>
</html>
