<?php
// Pastikan tidak ada session start ganda
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../config/db.php';

// Variabel untuk menampung status login (untuk SweetAlert)
$login_status = null;
$redirect_url = null;
$message = null;

// 1. Cek Sesi (Jika sudah login, lempar langsung tanpa popup)
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: ../admin/index.php");
    } elseif ($_SESSION['role'] == 'seller') {
        header("Location: ../seller/index.php");
    } elseif ($_SESSION['role'] == 'buyer') {
        header("Location: ../buyer/index.php");
    } else {
        header("Location: ../index.php");
    }
    exit;
}

// 2. Proses Login
if (isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    // Cek user berdasarkan email
    $query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");
    
    // Cek apakah email ditemukan
    if (mysqli_num_rows($query) === 1) {
        $user = mysqli_fetch_assoc($query);

        // Verifikasi Password
        if ($password === $user['password']) {
            
            // Set Session Lengkap
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['role']          = $user['role']; 
            $_SESSION['full_name']     = $user['full_name'];
            $_SESSION['profile_image'] = $user['profile_image']; 

            // Update Last Activity
            mysqli_query($conn, "UPDATE users SET last_activity = NOW() WHERE id = {$user['id']}");

            // Tentukan URL Redirect (JANGAN PAKAI HEADER DISINI AGAR POPUP MUNCUL DULU)
            if ($user['role'] == 'admin') {
                $redirect_url = "../admin/index.php";
            } elseif ($user['role'] == 'seller') {
                $redirect_url = "../seller/index.php";
            } elseif ($user['role'] == 'buyer') {
                $redirect_url = "../buyer/index.php";
            } else {
                $redirect_url = "../index.php";
            }
            
            // Set status sukses
            $login_status = 'success';
            $message = "Selamat datang kembali, " . $user['full_name'];

        } else {
            $login_status = 'error';
            $message = "Kata sandi yang Anda masukkan salah.";
        }
    } else {
        $login_status = 'error';
        $message = "Email tidak ditemukan dalam sistem.";
    }
}
?>

<!DOCTYPE html>
<html lang="id" class="light">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Masuk - Libraria</title>
    
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=DM+Serif+Display&family=Inter:wght@300;400;500;600;700&family=Material+Icons+Outlined&display=swap" rel="stylesheet"/>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
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
              'large': '2rem',
              'xlarge': '3rem',
            },
            backgroundImage: {
                'library': "url('https://images.unsplash.com/photo-1507842217121-9e93c8aaf27c?q=80&w=1920&auto=format&fit=crop')",
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
        /* Custom Font untuk SweetAlert agar match dengan website */
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

    <div class="w-full h-full lg:w-[90%] lg:h-[90%] bg-white rounded-xlarge shadow-2xl overflow-hidden flex flex-col lg:flex-row relative border border-stone-200/50 z-10">
        
        <div class="hidden lg:flex w-5/12 bg-[#3a5020] relative flex-col justify-between p-12 text-white overflow-hidden">
            <div class="absolute inset-0 bg-library bg-cover bg-center mix-blend-overlay opacity-40 z-0"></div>
            <div class="absolute inset-0 bg-gradient-to-b from-[#3a5020]/90 via-[#3a5020]/80 to-[#3a5020]/95 z-0"></div>
            <div class="absolute -right-24 -top-24 w-96 h-96 bg-[#d1d6a7]/20 rounded-full blur-3xl animate-pulse z-0"></div>
            <div class="absolute -left-20 bottom-20 w-80 h-80 bg-[#b08144]/20 rounded-full blur-3xl z-0"></div>

            <div class="relative z-10 flex items-center gap-3" data-aos="fade-down" data-aos-duration="1000">
                <img src="../assets/images/logo.png" alt="Logo" class="h-12 w-auto object-contain brightness-0 invert drop-shadow-md">
                <span class="font-logo text-3xl tracking-[0.15em] font-bold text-[#d1d6a7]">LIBRARIA</span>
            </div>

            <div class="relative z-10 mb-10" data-aos="fade-right" data-aos-duration="1200" data-aos-delay="200">
                <span class="inline-block w-12 h-1 bg-[#b08144] mb-6"></span>
                <h2 class="font-display text-5xl lg:text-6xl mb-6 leading-tight drop-shadow-lg text-white">
                    Selamat <br>Datang <span class="text-[#b08144] italic">Kembali.</span>
                </h2>
                <p class="text-[#d1d6a7]/90 text-lg font-light leading-relaxed max-w-sm border-l-2 border-white/20 pl-4">
                    Lanjutkan perjalananmu menelusuri ribuan dunia. Masuk untuk mengelola koleksi atau menemukan bacaan baru.
                </p>
            </div>

            <div class="relative z-10 text-xs text-[#d1d6a7]/50 uppercase tracking-widest font-medium">
                © 2025 Libraria Bookstore Official
            </div>
        </div>

        <div class="w-full lg:w-7/12 bg-cream flex flex-col justify-center h-full relative p-8 md:p-16 lg:p-24">
            <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-bl from-tan/10 to-transparent rounded-bl-full pointer-events-none"></div>

            <div class="max-w-md mx-auto w-full z-10">
                <div class="mb-10 text-center lg:text-left">
                    <h2 class="font-display text-4xl text-primary mb-3">Masuk</h2>
                    <p class="text-stone-500">Masukkan email dan kata sandi untuk mengakses akun Anda.</p>
                </div>

                <form action="" method="POST" class="space-y-6">
                    
                    <div class="group">
                        <label class="text-xs font-bold text-stone-500 uppercase tracking-wider ml-1 mb-1 block group-focus-within:text-primary transition-colors">Alamat Email</label>
                        <div class="relative">
                            <span class="material-icons-outlined absolute left-4 top-3.5 text-stone-400 group-focus-within:text-primary transition-colors">email</span>
                            <input type="email" name="email" required 
                                   class="w-full pl-12 pr-4 py-3.5 bg-white border border-stone-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-stone-800 placeholder-stone-400 shadow-sm"
                                   placeholder="nama@email.com">
                        </div>
                    </div>

                    <div class="group">
                        <div class="flex justify-between items-center ml-1 mb-1">
                            <label class="text-xs font-bold text-stone-500 uppercase tracking-wider group-focus-within:text-primary transition-colors">Kata Sandi</label>
                            <a href="forgot_password.php" class="text-xs font-semibold text-tan hover:text-chocolate transition-colors">Lupa Kata Sandi?</a>
                        </div>
                        <div class="relative">
                            <span class="material-icons-outlined absolute left-4 top-3.5 text-stone-400 group-focus-within:text-primary transition-colors">lock</span>
                            <input type="password" name="password" id="password" required 
                                   class="w-full pl-12 pr-12 py-3.5 bg-white border border-stone-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none text-stone-800 placeholder-stone-400 shadow-sm"
                                   placeholder="••••••••">
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center cursor-pointer text-stone-400 hover:text-primary transition-colors" onclick="togglePassword()">
                                <span id="eyeIcon" class="material-icons-outlined">visibility</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="login" class="w-full bg-chocolate text-cream font-bold py-4 rounded-xl hover:bg-tan hover:-translate-y-1 transition-all shadow-lg shadow-chocolate/20 flex items-center justify-center gap-2 group mt-2">
                        Masuk Sekarang <span class="material-icons-outlined group-hover:translate-x-1 transition-transform">login</span>
                    </button>
                </form>

                <div class="mt-8 pt-8 border-t border-stone-200 flex flex-col items-center gap-4">
                    <p class="text-sm text-stone-600">
                        Belum memiliki akun? 
                        <a href="register.php" class="font-bold text-primary hover:text-tan transition-colors underline decoration-2 decoration-transparent hover:decoration-tan">
                            Daftar Disini
                        </a>
                    </p>
                    
                    <a href="../index.php" class="inline-flex items-center gap-2 text-stone-400 text-sm font-medium hover:text-chocolate transition-colors group px-4 py-2 rounded-full hover:bg-stone-100">
                        <span class="material-icons-outlined text-base group-hover:-translate-x-1 transition-transform">arrow_back</span>
                        Kembali ke Beranda
                    </a>
                </div>

            </div>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <script>
        AOS.init();

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerText = 'visibility_off';
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerText = 'visibility';
            }
        }

        // --- LOGIKA POPUP NOTIFICATION ---
        <?php if ($login_status == 'success'): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil Masuk!',
                text: '<?= $message ?>',
                confirmButtonColor: '#633d0c', // Warna Chocolate
                background: '#fefbe9',         // Warna Cream
                iconColor: '#3a5020',          // Warna Primary Green
                timer: 2000,                   // Otomatis pindah dalam 2 detik
                showConfirmButton: false
            }).then(() => {
                window.location.href = '<?= $redirect_url ?>';
            });
        <?php elseif ($login_status == 'error'): ?>
            Swal.fire({
                icon: 'error',
                title: 'Gagal Masuk',
                text: '<?= $message ?>',
                confirmButtonColor: '#633d0c',
                background: '#fefbe9',
            });
        <?php endif; ?>
    </script>
</body>
</html>