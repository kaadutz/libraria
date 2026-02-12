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
$buyer_name = $_SESSION['full_name'];

// --- DATA NAVBAR (Profile, Cart, Notif) ---
$query_user = mysqli_query($conn, "SELECT profile_image FROM users WHERE id = '$buyer_id'");
$user_data = mysqli_fetch_assoc($query_user);
$profile_pic = !empty($user_data['profile_image']) ? "../assets/uploads/profiles/" . $user_data['profile_image'] : "../assets/images/default_profile.png";

$query_cart = mysqli_query($conn, "SELECT SUM(qty) as total FROM carts WHERE buyer_id = '$buyer_id'");
$cart_count = mysqli_fetch_assoc($query_cart)['total'] ?? 0;

$query_notif = mysqli_query($conn, "SELECT COUNT(*) as total FROM messages WHERE receiver_id = '$buyer_id' AND is_read = 0");
$total_chat_unread = mysqli_fetch_assoc($query_notif)['total'];
$total_notif = $total_chat_unread;

// --- 2. AMBIL DATA FAQ DARI DATABASE ---
// Filter hanya untuk 'buyer' dan 'all' (Umum)
$query_faq = mysqli_query($conn, "SELECT * FROM qna WHERE target IN ('buyer', 'all') ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Bantuan - Libraria</title>

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
            --text-dark: #2D2418;
            --text-muted: #6B6155;
            --border-color: #E6E1D3;
        }
        body { font-family: 'Quicksand', sans-serif; background-color: var(--cream-bg); color: var(--text-dark); }
        .font-logo { font-family: 'Cinzel', serif; }

        /* Accordion Animation */
        .faq-content { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
        .faq-item.active .faq-content { max-height: 500px; transition: max-height 0.5s ease-in; }
        .faq-item.active .icon-rotator { transform: rotate(180deg); }
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

                    <a href="help.php" class="w-10 h-10 flex items-center justify-center rounded-full bg-[var(--deep-forest)] text-white shadow-md transition-all" title="Bantuan">
                        <span class="material-symbols-outlined">help</span>
                    </a>

                    <div class="relative">
                        <button onclick="toggleDropdown('notificationDropdown')" class="w-10 h-10 flex items-center justify-center rounded-full text-[var(--text-muted)] hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] transition-all relative">
                            <span class="material-symbols-outlined">notifications</span>
                            <?php if($total_notif > 0): ?>
                                <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white animate-ping"></span>
                                <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white"></span>
                            <?php endif; ?>
                        </button>
                        <div id="notificationDropdown" class="absolute right-0 mt-3 w-72 bg-white rounded-2xl shadow-xl border border-[var(--border-color)] py-2 hidden transform origin-top-right transition-all z-50">
                            <div class="px-4 py-3 border-b border-[var(--border-color)] flex justify-between items-center">
                                <h4 class="font-bold text-[var(--deep-forest)] text-sm">Notifikasi</h4>
                                <?php if($total_notif > 0): ?><span class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-bold"><?= $total_notif ?> Baru</span><?php endif; ?>
                            </div>
                            <div class="max-h-64 overflow-y-auto">
                                <?php if($total_chat_unread > 0): ?>
                                <a href="chat_list.php" class="flex items-center gap-3 px-4 py-3 hover:bg-[var(--cream-bg)] transition-colors">
                                    <div class="p-2 bg-blue-100 text-blue-600 rounded-full shrink-0"><span class="material-symbols-outlined text-lg">chat</span></div>
                                    <div><p class="text-sm font-bold text-gray-800">Pesan Masuk</p><p class="text-xs text-gray-500">Ada <?= $total_chat_unread ?> pesan baru.</p></div>
                                </a>
                                <?php endif; ?>
                                <?php if($total_notif == 0): ?><div class="text-center py-6 text-gray-400 text-xs italic">Tidak ada notifikasi baru.</div><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <a href="cart.php" class="relative w-10 h-10 flex items-center justify-center rounded-full border border-[var(--border-color)] bg-white text-[var(--text-muted)] hover:text-[var(--deep-forest)] hover:shadow-md transition-all">
                        <span class="material-symbols-outlined">shopping_bag</span>
                        <span id="cart-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold h-5 w-5 flex items-center justify-center rounded-full border-2 border-white animate-bounce <?= $cart_count > 0 ? '' : 'hidden' ?>"><?= $cart_count ?></span>
                    </a>

                    <div class="relative ml-1">
                        <button onclick="toggleDropdown('profileDropdown')" class="flex items-center gap-2 pl-1 pr-1 md:pr-3 py-1 rounded-full border border-transparent hover:bg-white hover:shadow-sm hover:border-[var(--border-color)] transition-all duration-300 focus:outline-none">
                            <img src="<?= $profile_pic ?>" class="h-9 w-9 rounded-full object-cover border border-[var(--warm-tan)]">
                            <div class="hidden md:block text-left">
                                <p class="text-[10px] text-[var(--text-muted)] font-bold uppercase leading-none mb-0.5">Hi,</p>
                                <p class="text-xs font-bold text-[var(--deep-forest)] leading-none truncate max-w-[80px]"><?= explode(' ', $buyer_name)[0] ?></p>
                            </div>
                            <span class="material-symbols-outlined text-[var(--text-muted)] text-sm hidden md:block">expand_more</span>
                        </button>
                        <div id="profileDropdown" class="absolute right-0 mt-3 w-56 bg-white rounded-2xl shadow-xl border border-[var(--border-color)] py-2 hidden transform origin-top-right transition-all z-50">
                            <a href="profile.php" class="flex items-center gap-3 px-4 py-2 hover:bg-[var(--cream-bg)] text-sm font-bold text-[var(--text-dark)]"><span class="material-symbols-outlined text-lg">person</span> Akun Saya</a>
                            <div class="border-t border-[var(--border-color)] my-1"></div>
                            <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-2 hover:bg-red-50 text-sm font-bold text-red-600 transition-colors"><span class="material-symbols-outlined text-lg">logout</span> Keluar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-1 pt-32 pb-12 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto w-full">

        <div class="text-center mb-12" data-aos="fade-down">
            <span class="material-symbols-outlined text-5xl text-[var(--warm-tan)] mb-4">support_agent</span>
            <h1 class="text-3xl lg:text-4xl font-bold text-[var(--deep-forest)] title-font mb-2">Pusat Bantuan</h1>
            <p class="text-[var(--text-muted)]">Temukan jawaban atas pertanyaan Anda seputar belanja di Libraria.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-2 space-y-4" data-aos="fade-right">
                <h3 class="text-xl font-bold text-[var(--text-dark)] mb-6">Pertanyaan Umum (FAQ)</h3>

                <?php if(mysqli_num_rows($query_faq) > 0): ?>
                    <?php while($faq = mysqli_fetch_assoc($query_faq)): ?>
                    <div class="faq-item bg-white rounded-2xl border border-[var(--border-color)] overflow-hidden cursor-pointer group hover:shadow-md transition-all" onclick="toggleFaq(this)">
                        <div class="flex justify-between items-center p-5 bg-white">
                            <h4 class="font-bold text-[var(--deep-forest)] text-sm"><?= htmlspecialchars($faq['question']) ?></h4>
                            <span class="material-symbols-outlined text-[var(--text-muted)] icon-rotator transition-transform duration-300">expand_more</span>
                        </div>
                        <div class="faq-content bg-[var(--cream-bg)]/30 text-[var(--text-muted)] text-sm px-5">
                            <div class="pb-5 pt-0 leading-relaxed">
                                <?= nl2br(htmlspecialchars($faq['answer'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="p-8 text-center bg-white rounded-2xl border border-dashed border-[var(--border-color)]">
                        <span class="material-symbols-outlined text-4xl text-stone-300 mb-2">library_books</span>
                        <p class="text-stone-500 font-bold">Belum ada data FAQ.</p>
                        <p class="text-xs text-stone-400">Silakan hubungi admin jika butuh bantuan.</p>
                    </div>
                <?php endif; ?>

            </div>

            <div class="space-y-6" data-aos="fade-left">
                <h3 class="text-xl font-bold text-[var(--text-dark)] mb-6">Hubungi Kami</h3>

                <div class="bg-[var(--deep-forest)] text-white p-6 rounded-[2rem] shadow-xl relative overflow-hidden">
                    <div class="absolute -right-10 -top-10 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
                    <h4 class="font-bold text-lg mb-4 flex items-center gap-2"><span class="material-symbols-outlined">support_agent</span> Layanan Pelanggan</h4>
                    <p class="text-xs text-white/80 mb-6 leading-relaxed">Tim kami siap membantu Anda Senin - Jumat (09:00 - 17:00).</p>

                    <a href="https://wa.me/628123456789" target="_blank" class="flex items-center gap-3 bg-white/20 p-3 rounded-xl mb-3 hover:bg-white/30 transition-colors">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg" class="w-6 h-6">
                        <span class="text-sm font-bold">+62 812-3456-7890</span>
                    </a>

                    <a href="mailto:support@libraria.com" class="flex items-center gap-3 bg-white/20 p-3 rounded-xl hover:bg-white/30 transition-colors">
                        <span class="material-symbols-outlined">mail</span>
                        <span class="text-sm font-bold">support@libraria.com</span>
                    </a>
                </div>

                <div class="bg-white p-6 rounded-[2rem] border border-[var(--border-color)]">
                    <h4 class="font-bold text-[var(--deep-forest)] mb-4">Lokasi Kantor</h4>
                    <p class="text-sm text-[var(--text-muted)] flex items-start gap-2">
                        <span class="material-symbols-outlined text-[var(--warm-tan)] shrink-0">location_on</span>
                        Jl. Buku Pintar No. 123, Jakarta Selatan, Indonesia 12345
                    </p>
                </div>
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

        function toggleFaq(element) {
            const allItems = document.querySelectorAll('.faq-item');
            allItems.forEach(item => {
                if (item !== element) {
                    item.classList.remove('active');
                }
            });
            element.classList.toggle('active');
        }
    </script>
</body>
</html>