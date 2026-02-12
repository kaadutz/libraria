<?php
// Pastikan session tidak double start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../config/db.php';

// Jika sudah login, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// Inisialisasi variabel status untuk SweetAlert
$register_status = null;
$message = null;
$error_details = null;

if (isset($_POST['register'])) {
    $role       = $_POST['role'];
    $full_name  = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email      = mysqli_real_escape_string($conn, $_POST['email']);
    $address    = mysqli_real_escape_string($conn, $_POST['address']);
    $password   = $_POST['password'];
    $confirm_pw = $_POST['confirm_password'];

    // NIK sekarang wajib untuk KEDUA ROLE
    $nik = mysqli_real_escape_string($conn, $_POST['nik']);

    // Logika Khusus Seller
    $bank_info = NULL;
    $bank_account = NULL;
    if ($role == 'seller') {
        $bank_info = mysqli_real_escape_string($conn, $_POST['bank_info']);
        $bank_account = mysqli_real_escape_string($conn, $_POST['bank_account']);
    }

    // --- VALIDASI INPUT ---

    // 1. Validasi Password
    if ($password !== $confirm_pw) {
        $register_status = 'error';
        $message = "Konfirmasi kata sandi tidak cocok!";
    }
    // 2. Validasi NIK (Wajib Semua)
    else if (empty($nik)) {
        $register_status = 'error';
        $message = "NIK wajib diisi!";
    }
    // 3. Validasi Bank (Khusus Penjual)
    else if ($role == 'seller' && (empty($bank_info) || empty($bank_account))) {
        $register_status = 'error';
        $message = "Penjual wajib mengisi Informasi Bank dan Nomor Rekening!";
    }
    // 4. Validasi Alamat
    else if (empty($address)) {
        $register_status = 'error';
        $message = "Alamat lengkap wajib diisi!";
    }
    else {
        // --- VALIDASI DUPLIKAT SPESIFIK ---

        // 5a. Cek Email Saja
        $check_email = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");

        // 5b. Cek NIK Saja
        $check_nik = mysqli_query($conn, "SELECT id FROM users WHERE nik = '$nik'");

        if (mysqli_num_rows($check_email) > 0) {
            $register_status = 'error';
            $message = "Email '$email' sudah terdaftar! Silakan gunakan email lain atau login.";
        }
        else if (mysqli_num_rows($check_nik) > 0) {
            $register_status = 'error';
            $message = "NIK '$nik' sudah terdaftar pada akun lain!";
        }
        else {
            // Jika Lolos Semua Validasi, Lanjut Upload & Insert

            // 6. PROSES UPLOAD FOTO
            $profile_image = NULL;
            $upload_ok = true;

            if (!empty($_FILES['profile_image']['name'])) {
                $target_dir = "../assets/uploads/profiles/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                $file_extension = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
                $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;

                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array($file_extension, $allowed)) {
                    if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                        $profile_image = $new_filename;
                    } else {
                        $upload_ok = false;
                        $register_status = 'error';
                        $message = "Gagal mengupload gambar.";
                    }
                } else {
                    $upload_ok = false;
                    $register_status = 'error';
                    $message = "Format gambar harus JPG, PNG, atau GIF.";
                }
            }

            if ($upload_ok && $register_status !== 'error') {
                // 7. Insert Data
                $val_img = ($profile_image) ? "'$profile_image'" : "NULL";
                $val_bank_info = ($bank_info) ? "'$bank_info'" : "NULL";
                $val_bank_acc = ($bank_account) ? "'$bank_account'" : "NULL";

                // Hash Password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $query = "INSERT INTO users (email, password, full_name, nik, address, role, profile_image, bank_info, bank_account)
                          VALUES ('$email', '$hashed_password', '$full_name', '$nik', '$address', '$role', $val_img, $val_bank_info, $val_bank_acc)";

                if (mysqli_query($conn, $query)) {
                    $register_status = 'success';
                    $message = "Akun berhasil dibuat! Silakan login untuk melanjutkan.";
                } else {
                    // Tangkap Error MySQL (Misal Race Condition)
                    $register_status = 'error';
                    if (mysqli_errno($conn) == 1062) { // Kode Error Duplicate Entry
                        $message = "Terjadi kesalahan: Email atau NIK sudah terdaftar di sistem.";
                    } else {
                        $message = "Terjadi kesalahan sistem database.";
                        // XSS Prevention: Escape error details
                        $error_details = htmlspecialchars(mysqli_error($conn), ENT_QUOTES, 'UTF-8');
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id" class="light">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Daftar Akun - Libraria</title>

    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=DM+Serif+Display&family=Inter:wght@300;400;500;600;700&family=Material+Icons+Outlined&display=swap" rel="stylesheet"/>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              primary: "#3a5020",
              "chocolate": "#633d0c",
              "tan": "#b08144",
              "sage": "#d1d6a7",
              "cream": "#fefbe9",
              "background-light": "#fefbe9",
              "background-dark": "#1a1c18",
            },
            fontFamily: {
              display: ["DM Serif Display", "serif"],
              sans: ["Inter", "sans-serif"],
              logo: ["Cinzel", "serif"],
            },
            borderRadius: {
              DEFAULT: "0.75rem",
              'xl': '1rem',
              '2xl': '1.5rem',
              '3xl': '2.5rem',
            },
            backgroundImage: {
                'library': "url('https://images.unsplash.com/photo-1521587760476-6c12a4b040da?q=80&w=2070&auto=format&fit=crop')",
            }
          },
        },
      };
    </script>

    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .paper-texture {
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
            opacity: 0.04;
        }
        .custom-scroll::-webkit-scrollbar { width: 5px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; margin: 10px 0; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #d1d6a7; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: #b08144; }
        input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }

        /* Custom Font untuk SweetAlert agar match */
        .swal2-popup {
            font-family: 'Inter', sans-serif !important;
            border-radius: 1.5rem !important;
        }
    </style>
<script src="../assets/js/theme-manager.js"></script>
</head>
<body class="bg-background-light h-screen flex items-center justify-center p-4 lg:p-0 overflow-hidden relative">

<div class="absolute top-4 right-4 z-50">
    <button onclick="toggleDarkMode()" class="w-10 h-10 rounded-full bg-white/10 border border-stone-200 dark:border-stone-700 text-stone-500 dark:text-stone-400 hover:text-primary hover:bg-primary/10 transition-all flex items-center justify-center group shadow-lg backdrop-blur-sm" title="Toggle Dark Mode">
        <span class="material-icons-outlined group-hover:rotate-180 transition-transform duration-500" id="dark-mode-icon">dark_mode</span>
    </button>
</div>


    <div class="absolute inset-0 paper-texture pointer-events-none z-0"></div>

    <div class="w-full h-full lg:w-[90%] lg:h-[90%] bg-white rounded-3xl shadow-[0_25px_60px_-15px_rgba(0,0,0,0.15)] overflow-hidden flex flex-col lg:flex-row relative border border-white/50 z-10 backdrop-blur-sm">

        <div class="hidden lg:flex w-5/12 bg-primary relative flex-col justify-between p-12 text-cream overflow-hidden">
            <div class="absolute inset-0 bg-cover bg-center mix-blend-overlay opacity-25" style="background-image: url('https://images.unsplash.com/photo-1507842217121-9e93c8aaf27c?q=80&w=1920&auto=format&fit=crop');"></div>
            <div class="absolute inset-0 bg-gradient-to-br from-primary via-primary/90 to-[#2a3c15]"></div>
            <div class="absolute -right-32 -top-32 w-[500px] h-[500px] bg-sage/10 rounded-full blur-3xl animate-pulse"></div>
            <div class="absolute -left-20 bottom-0 w-80 h-80 bg-tan/20 rounded-full blur-3xl"></div>

            <div class="relative z-10 flex items-center gap-3" data-aos="fade-down" data-aos-duration="1000">
                <img src="../assets/images/logo.png" alt="Logo" class="h-12 w-auto object-contain brightness-0 invert drop-shadow-md">
                <span class="font-logo text-2xl tracking-[0.2em] font-bold text-sage/90">LIBRARIA</span>
            </div>

            <div class="relative z-10" data-aos="fade-right" data-aos-duration="1200" data-aos-delay="200">
                <span class="inline-block w-16 h-1 bg-tan mb-8 rounded-full"></span>
                <h2 class="font-display text-5xl lg:text-6xl mb-6 leading-tight drop-shadow-lg text-cream">
                    Start Your <br><span class="text-tan italic">Story</span> Here.
                </h2>
                <p class="text-sage/80 text-lg font-light leading-relaxed max-w-sm pl-1">
                    Bergabung dengan ekosistem literasi terbesar. Temukan bacaan, jual koleksi, dan bangun komunitasmu.
                </p>
            </div>

            <div class="relative z-10 text-[10px] text-sage/30 uppercase tracking-[0.3em] font-bold flex items-center gap-2">
                <span class="w-8 h-[1px] bg-sage/30"></span> EST. 2025
            </div>
        </div>

        <div class="w-full lg:w-7/12 bg-white flex flex-col h-full relative">

            <div class="lg:hidden p-6 border-b border-stone-100 flex items-center justify-between bg-white z-20">
                <span class="font-logo text-xl text-primary font-bold">LIBRARIA</span>
                <a href="../index.php" class="text-stone-400"><span class="material-icons-outlined">close</span></a>
            </div>

            <div class="flex-1 overflow-y-auto custom-scroll p-6 lg:p-14 flex flex-col">
                <div class="w-full max-w-lg mx-auto">
                    <div class="mb-8">
                        <h2 class="font-display text-3xl lg:text-4xl text-stone-800 mb-2">Buat Akun Baru</h2>
                        <p class="text-stone-500 text-sm">Lengkapi data diri untuk memulai.</p>
                    </div>

                    <?php if($register_status == 'error' && empty($message)): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-4 py-4 mb-8 rounded-r-lg text-sm flex items-center gap-3 shadow-sm animate-bounce">
                            <span class="material-icons-outlined text-red-500">error</span>
                            <span class="font-medium">Terjadi kesalahan sistem.</span>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">

                        <div>
                            <label class="text-xs font-bold text-stone-400 uppercase tracking-widest ml-1 mb-3 block">Daftar Sebagai</label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="cursor-pointer group relative">
                                    <input type="radio" name="role" value="buyer" class="peer sr-only" checked onclick="toggleRole('buyer')">
                                    <div class="p-4 rounded-2xl border-2 border-stone-100 bg-stone-50/50 text-center transition-all duration-300 peer-checked:border-primary peer-checked:bg-primary/5 hover:border-primary/30 h-full flex flex-col items-center justify-center gap-2">
                                        <div class="absolute top-3 right-3 opacity-0 peer-checked:opacity-100 transition-all text-primary scale-50 peer-checked:scale-100">
                                            <span class="material-icons-outlined text-xl">check_circle</span>
                                        </div>
                                        <div class="w-12 h-12 rounded-full bg-white shadow-sm flex items-center justify-center text-stone-400 peer-checked:text-primary peer-checked:shadow-md transition-all">
                                            <span class="material-icons-outlined text-2xl">shopping_bag</span>
                                        </div>
                                        <span class="text-sm font-bold text-stone-500 peer-checked:text-primary transition-colors">Pembeli</span>
                                    </div>
                                </label>

                                <label class="cursor-pointer group relative">
                                    <input type="radio" name="role" value="seller" class="peer sr-only" onclick="toggleRole('seller')">
                                    <div class="p-4 rounded-2xl border-2 border-stone-100 bg-stone-50/50 text-center transition-all duration-300 peer-checked:border-tan peer-checked:bg-tan/5 hover:border-tan/30 h-full flex flex-col items-center justify-center gap-2">
                                        <div class="absolute top-3 right-3 opacity-0 peer-checked:opacity-100 transition-all text-tan scale-50 peer-checked:scale-100">
                                            <span class="material-icons-outlined text-xl">check_circle</span>
                                        </div>
                                        <div class="w-12 h-12 rounded-full bg-white shadow-sm flex items-center justify-center text-stone-400 peer-checked:text-tan peer-checked:shadow-md transition-all">
                                            <span class="material-icons-outlined text-2xl">storefront</span>
                                        </div>
                                        <span class="text-sm font-bold text-stone-500 peer-checked:text-tan transition-colors">Penjual</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="group">
                            <label class="text-xs font-bold text-stone-500 uppercase tracking-widest ml-1 mb-1.5 block">Foto Profil (Opsional)</label>
                            <div class="relative">
                                <span class="material-icons-outlined absolute left-4 top-3.5 text-stone-400 group-focus-within:text-primary transition-colors">image</span>
                                <input type="file" name="profile_image" accept="image/*"
                                    class="w-full pl-12 pr-4 py-3 bg-stone-50 border-stone-200 rounded-2xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-stone-800 placeholder-stone-400 font-medium text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-white hover:file:bg-tan">
                            </div>
                        </div>

                        <div class="group">
                            <label id="label_name" class="text-xs font-bold text-stone-500 uppercase tracking-widest ml-1 mb-1.5 block">Nama Lengkap</label>
                            <div class="relative">
                                <span class="material-icons-outlined absolute left-4 top-3.5 text-stone-400 group-focus-within:text-primary transition-colors">badge</span>
                                <input type="text" name="full_name" required value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>"
                                    class="w-full pl-12 pr-4 py-3.5 bg-stone-50 border-stone-200 rounded-2xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-stone-800 placeholder-stone-400 font-medium text-sm"
                                    placeholder="Masukkan nama anda">
                            </div>
                        </div>

                        <div class="group">
                            <label class="text-xs font-bold text-stone-500 uppercase tracking-widest ml-1 mb-1.5 block">NIK (16 Digit)</label>
                            <div class="relative">
                                <span class="material-icons-outlined absolute left-4 top-3.5 text-stone-400 group-focus-within:text-primary transition-colors">verified_user</span>
                                <input type="number" name="nik" value="<?= isset($_POST['nik']) ? htmlspecialchars($_POST['nik']) : '' ?>" required
                                    class="w-full pl-12 pr-4 py-3.5 bg-stone-50 border-stone-200 rounded-2xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-stone-800 placeholder-stone-400 font-medium text-sm"
                                    placeholder="Nomor Induk Kependudukan">
                            </div>
                            <p class="text-[10px] text-stone-400 mt-1 ml-2">*Dibutuhkan untuk validasi keamanan akun.</p>
                        </div>

                        <div id="bank_container" class="hidden space-y-6 pt-4 border-t border-dashed border-stone-200">
                            <h3 class="text-sm font-bold text-primary flex items-center gap-1">
                                <span class="material-icons-outlined text-base">account_balance_wallet</span> Informasi Rekening Pencairan
                            </h3>
                            <div class="group">
                                <label class="text-xs font-bold text-stone-500 uppercase tracking-widest ml-1 mb-1.5 block">Nama Bank & Pemilik</label>
                                <div class="relative">
                                    <span class="material-icons-outlined absolute left-4 top-3.5 text-stone-400 group-focus-within:text-primary transition-colors">account_balance</span>
                                    <input type="text" name="bank_info" id="bank_info_input"
                                        class="w-full pl-12 pr-4 py-3.5 bg-stone-50 border-stone-200 rounded-2xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-stone-800 placeholder-stone-400 font-medium text-sm"
                                        placeholder="Cth: BCA a.n. Siti Aminah">
                                </div>
                            </div>
                            <div class="group">
                                <label class="text-xs font-bold text-stone-500 uppercase tracking-widest ml-1 mb-1.5 block">Nomor Rekening</label>
                                <div class="relative">
                                    <span class="material-icons-outlined absolute left-4 top-3.5 text-stone-400 group-focus-within:text-primary transition-colors">credit_card</span>
                                    <input type="number" name="bank_account" id="bank_acc_input"
                                        class="w-full pl-12 pr-4 py-3.5 bg-stone-50 border-stone-200 rounded-2xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-stone-800 placeholder-stone-400 font-medium text-sm"
                                        placeholder="Cth: 1234567890">
                                </div>
                            </div>
                        </div>

                        <div class="group">
                            <label class="text-xs font-bold text-stone-500 uppercase tracking-widest ml-1 mb-1.5 block">Alamat Lengkap</label>
                            <div class="relative">
                                <span class="material-icons-outlined absolute left-4 top-3.5 text-stone-400 group-focus-within:text-primary transition-colors">location_on</span>
                                <textarea name="address" required rows="2"
                                    class="w-full pl-12 pr-4 py-3.5 bg-stone-50 border-stone-200 rounded-2xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-stone-800 placeholder-stone-400 font-medium text-sm resize-none"
                                    placeholder="Jl. Contoh No. 123, Kota, Provinsi"><?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?></textarea>
                            </div>
                        </div>

                        <div class="group">
                            <label class="text-xs font-bold text-stone-500 uppercase tracking-widest ml-1 mb-1.5 block">Alamat Email</label>
                            <div class="relative">
                                <span class="material-icons-outlined absolute left-4 top-3.5 text-stone-400 group-focus-within:text-primary transition-colors">alternate_email</span>
                                <input type="email" name="email" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required
                                    class="w-full pl-12 pr-4 py-3.5 bg-stone-50 border-stone-200 rounded-2xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-stone-800 placeholder-stone-400 font-medium text-sm"
                                    placeholder="nama@email.com">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="group">
                                <label class="text-xs font-bold text-stone-500 uppercase tracking-widest ml-1 mb-1.5 block">Kata Sandi</label>
                                <div class="relative">
                                    <span class="material-icons-outlined absolute left-4 top-3.5 text-stone-400 group-focus-within:text-primary transition-colors">lock</span>
                                    <input type="password" name="password" required
                                        class="w-full pl-12 pr-4 py-3.5 bg-stone-50 border-stone-200 rounded-2xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-stone-800 placeholder-stone-400 font-medium text-sm" placeholder="••••••••">
                                </div>
                            </div>
                            <div class="group">
                                <label class="text-xs font-bold text-stone-500 uppercase tracking-widest ml-1 mb-1.5 block">Konfirmasi</label>
                                <div class="relative">
                                    <span class="material-icons-outlined absolute left-4 top-3.5 text-stone-400 group-focus-within:text-primary transition-colors">lock_reset</span>
                                    <input type="password" name="confirm_password" required
                                        class="w-full pl-12 pr-4 py-3.5 bg-stone-50 border-stone-200 rounded-2xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-stone-800 placeholder-stone-400 font-medium text-sm" placeholder="••••••••">
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="register" class="w-full bg-gradient-to-r from-chocolate to-[#54330a] text-cream font-bold py-4 rounded-2xl hover:shadow-lg hover:shadow-chocolate/25 hover:-translate-y-1 transition-all duration-300 mt-4 flex items-center justify-center gap-3 group">
                            <span class="tracking-wide">Daftar Sekarang</span>
                            <span class="material-icons-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                        </button>
                    </form>

                    <div class="mt-10 pt-6 border-t border-stone-100 flex flex-col items-center gap-5">
                        <p class="text-sm text-stone-500">
                            Sudah punya akun? <a href="login.php" class="font-bold text-primary hover:text-tan transition-colors">Masuk Disini</a>
                        </p>
                        <a href="../index.php" class="inline-flex items-center gap-2 text-stone-400 text-xs font-bold uppercase tracking-wider hover:text-chocolate transition-colors group px-4 py-2 rounded-full hover:bg-stone-50">
                            <span class="material-icons-outlined text-sm group-hover:-translate-x-1 transition-transform">arrow_back</span> Kembali ke Beranda
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        AOS.init();

        function toggleRole(role) {
            const labelName = document.getElementById('label_name');
            const inputName = document.querySelector('input[name="full_name"]');

            const bankContainer = document.getElementById('bank_container');
            const bankInfoInput = document.getElementById('bank_info_input');
            const bankAccInput = document.getElementById('bank_acc_input');

            if (role === 'seller') {
                labelName.innerText = 'Nama Toko';
                inputName.placeholder = 'Masukkan nama toko';

                // Tampilkan Bank
                bankContainer.classList.remove('hidden');
                bankInfoInput.required = true;
                bankAccInput.required = true;

            } else {
                labelName.innerText = 'Nama Lengkap';
                inputName.placeholder = 'Masukkan nama anda';

                // Sembunyikan Bank
                bankContainer.classList.add('hidden');
                bankInfoInput.required = false;
                bankAccInput.required = false;
                bankInfoInput.value = '';
                bankAccInput.value = '';
            }
        }

        // --- LOGIKA POPUP NOTIFICATION (SweetAlert) ---
        <?php if ($register_status == 'success'): ?>
            Swal.fire({
                icon: 'success',
                title: 'Pendaftaran Berhasil!',
                text: '<?= $message ?>',
                confirmButtonColor: '#633d0c', // Warna Chocolate
                background: '#fefbe9',         // Warna Cream
                iconColor: '#3a5020',          // Warna Primary Green
                confirmButtonText: 'Lanjut Login'
            }).then((result) => {
                if (result.isConfirmed || result.isDismissed) {
                    window.location.href = 'login.php';
                }
            });
        <?php elseif ($register_status == 'error'): ?>
            Swal.fire({
                icon: 'error',
                title: 'Pendaftaran Gagal',
                text: '<?= $message ?>',
                footer: '<?= $error_details ?>', // Menampilkan error teknis jika ada
                confirmButtonColor: '#633d0c',
                background: '#fefbe9',
            });
        <?php endif; ?>

    </script>
</body>
</html>
