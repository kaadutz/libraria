<?php
session_start();
// Set Timezone Wajib
date_default_timezone_set('Asia/Jakarta');

include '../config/db.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$error = '';
$message = '';
$redirect_login = false; // Flag khusus untuk redirect setelah ganti password
$step = isset($_SESSION['reset_step']) ? $_SESSION['reset_step'] : 1;

// ==========================================
// STEP 1: KIRIM OTP
// ==========================================
if (isset($_POST['send_otp'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    $check = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");
    if (mysqli_num_rows($check) > 0) {

        $otp = rand(100000, 999999);

        // Simpan OTP (Valid 15 Menit)
        $query = "UPDATE users SET reset_token = '$otp', reset_expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE email = '$email'";

        if(mysqli_query($conn, $query)) {
            // Kirim Email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = ''; // Email Anda
                $mail->Password   = '';  // App Password Anda
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('no-reply@libraria.com', 'Libraria Security');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Kode OTP Reset Password';
                $mail->Body    = "
                    <h3>Permintaan Reset Password</h3>
                    <p>Kode OTP Anda adalah:</p>
                    <h1 style='letter-spacing: 5px; color: #3E4B1C;'>$otp</h1>
                    <p>Kode ini berlaku 15 menit.</p>
                ";

                $mail->send();

                $_SESSION['reset_step'] = 2;
                $_SESSION['reset_email'] = $email;
                $step = 2;
                $message = "Kode OTP berhasil dikirim ke email Anda!";

            } catch (Exception $e) {
                $error = "Gagal kirim email: {$mail->ErrorInfo}";
            }
        } else {
            $error = "Gagal update database.";
        }
    } else {
        $error = "Email tidak ditemukan dalam sistem kami.";
    }
}

// ==========================================
// STEP 2: VERIFIKASI OTP
// ==========================================
if (isset($_POST['verify_otp'])) {
    $email = $_SESSION['reset_email'];
    $otp_input = mysqli_real_escape_string($conn, $_POST['otp']);

    // Cek Token & Waktu
    $query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email' AND reset_token = '$otp_input' AND reset_expires_at > NOW()");

    if (mysqli_num_rows($query) > 0) {
        $_SESSION['reset_step'] = 3;
        $step = 3;
        $message = "OTP Valid! Silakan buat password baru.";
    } else {
        $error = "Kode OTP salah atau sudah kadaluarsa!";
    }
}

// ==========================================
// STEP 3: GANTI PASSWORD
// ==========================================
if (isset($_POST['change_password'])) {
    $email = $_SESSION['reset_email'];
    $new_pass = $_POST['new_password'];
    $conf_pass = $_POST['confirm_password'];

    if ($new_pass === $conf_pass) {
        $update = mysqli_query($conn, "UPDATE users SET password = '$new_pass', reset_token = NULL, reset_expires_at = NULL WHERE email = '$email'");

        if($update) {
            unset($_SESSION['reset_step']);
            unset($_SESSION['reset_email']);
            // Aktifkan flag redirect untuk SweetAlert
            $redirect_login = true;
        } else {
            $error = "Terjadi kesalahan sistem.";
        }
    } else {
        $error = "Konfirmasi password tidak cocok!";
    }
}

// Tombol Reset
if (isset($_GET['restart'])) {
    unset($_SESSION['reset_step']);
    unset($_SESSION['reset_email']);
    header("Location: forgot_password.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Reset Password - Libraria</title>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- SweetAlert2 (Untuk Pop Up) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style type="text/tailwindcss">
        :root {
            --deep-forest: #3E4B1C;
            --chocolate-brown: #663F05;
            --warm-tan: #B18143;
            --light-sage: #DCE3AC;
            --cream-bg: #FEF9E6;
            --text-dark: #2D2418;
            --text-muted: #6B6155;
        }
        body {
            font-family: 'Quicksand', sans-serif;
            background-color: var(--cream-bg);
            color: var(--text-dark);
        }
        .title-font { font-weight: 700; }
        .otp-input { letter-spacing: 0.5em; text-align: center; font-size: 1.5rem; }
    </style>
<script src="../assets/js/theme-manager.js"></script>
</head>
<body class="flex items-center justify-center min-h-screen p-4 overflow-hidden relative">

<div class="absolute top-4 right-4 z-50">
    <button onclick="toggleDarkMode()" class="w-10 h-10 rounded-full bg-white/10 border border-stone-200 dark:border-stone-700 text-stone-500 dark:text-stone-400 hover:text-primary hover:bg-primary/10 transition-all flex items-center justify-center group shadow-lg backdrop-blur-sm" title="Toggle Dark Mode">
        <span class="material-icons-outlined group-hover:rotate-180 transition-transform duration-500" id="dark-mode-icon">dark_mode</span>
    </button>
</div>


    <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-[var(--light-sage)]/30 rounded-full blur-[100px] pointer-events-none"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-[var(--warm-tan)]/20 rounded-full blur-[100px] pointer-events-none"></div>

    <div class="w-full max-w-md bg-white rounded-[2.5rem] shadow-2xl p-8 md:p-12 border border-[#E6E1D3] relative z-10 text-center transition-all duration-500" data-aos="zoom-in">

        <div class="mb-8">
            <div class="w-20 h-20 bg-[var(--cream-bg)] rounded-full flex items-center justify-center mx-auto mb-4 text-[var(--deep-forest)] shadow-inner">
                <span class="material-symbols-outlined text-4xl">
                    <?php
                        if ($step == 1) echo 'lock_person';
                        elseif ($step == 2) echo 'mark_email_read';
                        else echo 'key';
                    ?>
                </span>
            </div>
            <h2 class="text-2xl font-bold text-[var(--deep-forest)] title-font">
                <?php
                    if ($step == 1) echo 'Lupa Password?';
                    elseif ($step == 2) echo 'Verifikasi OTP';
                    else echo 'Password Baru';
                ?>
            </h2>
            <p class="text-[var(--text-muted)] text-sm mt-2 font-medium">
                <?php
                    if ($step == 1) echo 'Masukkan email untuk menerima kode OTP.';
                    elseif ($step == 2) echo 'Cek email Anda dan masukkan 6 digit kode.';
                    else echo 'Silakan buat password baru Anda.';
                ?>
            </p>
        </div>

        <!-- STEP 1 -->
        <?php if($step == 1): ?>
        <form action="" method="POST" class="space-y-6 text-left">
            <div class="relative group">
                <span class="material-symbols-outlined absolute left-4 top-3.5 text-[var(--text-muted)] group-focus-within:text-[var(--deep-forest)]">mail</span>
                <input type="email" name="email" required placeholder="nama@email.com"
                       class="w-full pl-12 pr-4 py-3.5 rounded-2xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 text-sm font-bold transition-all">
            </div>
            <button type="submit" name="send_otp" class="w-full bg-[var(--deep-forest)] text-white font-bold py-3.5 rounded-2xl hover:bg-[var(--chocolate-brown)] hover:-translate-y-1 transition-all shadow-lg flex items-center justify-center gap-2">
                Kirim Kode OTP <span class="material-symbols-outlined text-lg">send</span>
            </button>
        </form>
        <?php endif; ?>

        <!-- STEP 2 -->
        <?php if($step == 2): ?>
        <form action="" method="POST" class="space-y-6 text-left">
            <div>
                <input type="number" name="otp" required placeholder="000000" maxlength="6" autofocus
                       class="w-full px-4 py-4 rounded-2xl bg-white border-2 border-[var(--warm-tan)] focus:ring-4 focus:ring-[var(--light-sage)] focus:border-[var(--deep-forest)] otp-input text-[var(--deep-forest)] font-bold transition-all placeholder:tracking-normal placeholder:text-base placeholder:font-normal">
            </div>
            <button type="submit" name="verify_otp" class="w-full bg-[var(--warm-tan)] text-white font-bold py-3.5 rounded-2xl hover:bg-[var(--chocolate-brown)] hover:-translate-y-1 transition-all shadow-lg flex items-center justify-center gap-2">
                Verifikasi Kode <span class="material-symbols-outlined text-lg">verified</span>
            </button>
        </form>
        <?php endif; ?>

        <!-- STEP 3 -->
        <?php if($step == 3): ?>
        <form action="" method="POST" class="space-y-5 text-left">
            <div class="relative group">
                <span class="material-symbols-outlined absolute left-4 top-3.5 text-[var(--text-muted)]">lock</span>
                <input type="password" name="new_password" required placeholder="Password Baru"
                       class="w-full pl-12 pr-4 py-3.5 rounded-2xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 text-sm font-bold transition-all">
            </div>
            <div class="relative group">
                <span class="material-symbols-outlined absolute left-4 top-3.5 text-[var(--text-muted)]">lock_reset</span>
                <input type="password" name="confirm_password" required placeholder="Konfirmasi Password"
                       class="w-full pl-12 pr-4 py-3.5 rounded-2xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 text-sm font-bold transition-all">
            </div>
            <button type="submit" name="change_password" class="w-full bg-[var(--deep-forest)] text-white font-bold py-3.5 rounded-2xl hover:bg-[var(--chocolate-brown)] hover:-translate-y-1 transition-all shadow-lg flex items-center justify-center gap-2">
                Simpan Password <span class="material-symbols-outlined text-lg">save</span>
            </button>
        </form>
        <?php endif; ?>

        <!-- Footer Links -->
        <div class="mt-8 pt-6 border-t border-stone-100 text-center">
            <?php if($step > 1): ?>
                <a href="?restart=true" class="text-xs text-red-500 hover:text-red-700 font-bold uppercase tracking-wider">
                    &larr; Batalkan & Ulangi
                </a>
            <?php else: ?>
                <a href="login.php" class="inline-flex items-center gap-2 text-[var(--text-muted)] text-sm font-bold hover:text-[var(--chocolate-brown)] transition-colors">
                    <span class="material-symbols-outlined text-lg">arrow_back</span>
                    Kembali ke Login
                </a>
            <?php endif; ?>
        </div>

    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();

        // --- SWEETALERT LOGIC ---

        <?php if($error): ?>
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: '<?= $error ?>',
                confirmButtonColor: '#3E4B1C',
                background: '#FEF9E6',
                color: '#2D2418'
            });
        <?php endif; ?>

        <?php if($message): ?>
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });

            Toast.fire({
                icon: 'success',
                title: '<?= $message ?>'
            });
        <?php endif; ?>

        // POP UP SUKSES GANTI PASSWORD
        <?php if($redirect_login): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Password Anda telah diperbarui. Silakan login.',
                confirmButtonText: 'Ke Halaman Login',
                confirmButtonColor: '#3E4B1C',
                allowOutsideClick: false,
                background: '#FEF9E6',
                color: '#2D2418'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'login.php';
                }
            });
        <?php endif; ?>

    </script>
</body>

</html>
