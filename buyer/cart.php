<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');
include '../config/db.php';

// 1. Cek Keamanan
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit;
}

$buyer_id = $_SESSION['user_id'];


// --- 3. NOTIFIKASI PINTAR (GABUNGAN CHAT & STATUS PESANAN) ---
$notif_list = [];

// A. Ambil Pesan Belum Dibaca (Akan terus muncul sampai dibaca)
$q_msg_notif = mysqli_query($conn, "SELECT m.*, u.full_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.receiver_id = '$buyer_id' AND m.is_read = 0 ORDER BY m.created_at DESC");
if ($q_msg_notif) {
    while($msg = mysqli_fetch_assoc($q_msg_notif)){
        $notif_list[] = [
            'type' => 'chat',
            'title' => 'Pesan dari ' . explode(' ', $msg['full_name'])[0],
            'text' => substr($msg['message'], 0, 25) . '...',
            'icon' => 'chat',
            'color' => 'blue',
            'link' => 'chat_list.php',
            'time' => strtotime($msg['created_at'])
        ];
    }
}

// B. Ambil 5 Status Pesanan Terakhir
$q_order_notif = mysqli_query($conn, "
    SELECT invoice_number, status, order_date
    FROM orders
    WHERE buyer_id = '$buyer_id'
    AND status IN ('approved', 'shipping', 'rejected', 'refunded', 'finished')
    ORDER BY order_date DESC LIMIT 5
");

if ($q_order_notif) {
    while($ord = mysqli_fetch_assoc($q_order_notif)){
        $title = $ord['invoice_number'];
        $text = ""; $icon = ""; $color = "";

        if($ord['status'] == 'approved') {
            $text = "Pesanan Diterima Penjual. Segera dikemas.";
            $icon = "inventory_2"; $color = "indigo";
        } elseif($ord['status'] == 'shipping') {
            $text = "Paket sedang dalam perjalanan.";
            $icon = "local_shipping"; $color = "purple";
        } elseif($ord['status'] == 'rejected') {
            $text = "Pesanan/Refund Ditolak oleh Penjual.";
            $icon = "cancel"; $color = "red";
        } elseif($ord['status'] == 'refunded') {
            $text = "Pengajuan Refund Disetujui.";
            $icon = "currency_exchange"; $color = "green";
        } elseif($ord['status'] == 'finished') {
            $text = "Pesanan Selesai. Terima kasih!";
            $icon = "check_circle"; $color = "teal";
        }

        $notif_list[] = [
            'type' => 'order',
            'title' => $title,
            'text' => $text,
            'icon' => $icon,
            'color' => $color,
            'link' => 'my_orders.php',
            'time' => strtotime($ord['order_date'])
        ];
    }
}

// Sort notifikasi berdasarkan waktu terbaru
usort($notif_list, function($a, $b) {
    return $b['time'] - $a['time'];
});

$total_notif = count($notif_list);

// HITUNG ISI KERANJANG (If not already calculated)
if(!isset($cart_count)) {
    $query_cart = mysqli_query($conn, "SELECT SUM(qty) as total FROM carts WHERE buyer_id = '$buyer_id'");
    if ($query_cart) {
        $cart_data = mysqli_fetch_assoc($query_cart);
        $cart_count = $cart_data['total'] ?? 0;
    } else {
        $cart_count = 0;
    }
}




// --- AMBIL DATA USER UNTUK NAVBAR ---
$query_user_info = mysqli_query($conn, "SELECT full_name, profile_image FROM users WHERE id = '$buyer_id'");
$user_info = mysqli_fetch_assoc($query_user_info);
$buyer_name = $user_info['full_name'];
$profile_pic = !empty($user_info['profile_image']) ? "../assets/uploads/profiles/" . $user_info['profile_image'] : "../assets/images/default_profile.png";

// --- DATA KERANJANG ---
$query_display_cart = "
    SELECT c.id as cart_id, c.qty, b.id as book_id, b.title, b.image, b.sell_price, b.stock, u.full_name as seller_name
    FROM carts c
    JOIN books b ON c.book_id = b.id
    JOIN users u ON b.seller_id = u.id
    WHERE c.buyer_id = '$buyer_id'
    ORDER BY u.full_name ASC
";
$cart_items = mysqli_query($conn, $query_display_cart);

$grand_total = 0;
$total_items_count = 0;

// --- DATA NAVBAR LAINNYA ---
$query_notif = mysqli_query($conn, "SELECT COUNT(*) as total FROM messages WHERE receiver_id = '$buyer_id' AND is_read = 0");
$total_notif = mysqli_fetch_assoc($query_notif)['total'];

$query_cart_count = mysqli_query($conn, "SELECT SUM(qty) as total FROM carts WHERE buyer_id = '$buyer_id'");
$cart_count = mysqli_fetch_assoc($query_cart_count)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
<title>Keranjang Belanja - Libraria</title>

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

    <div id="toast-container" class="fixed top-28 right-5 z-[60] flex flex-col gap-3"></div>



    <nav class="fixed top-0 w-full z-50 px-4 sm:px-6 lg:px-8 pt-4 transition-all duration-300" id="navbar">
        <div class="bg-white dark:bg-stone-900/90 dark:bg-stone-900/90 backdrop-blur-md rounded-3xl border border-tan/20 dark:border-stone-800 shadow-sm max-w-7xl mx-auto px-4 py-3 transition-colors duration-300">
            <div class="flex justify-between items-center gap-4">
                <a href="index.php" class="flex items-center gap-3 group shrink-0">
                    <img src="../assets/images/logo.png" alt="Logo" class="h-10 w-auto group-hover:scale-110 transition-transform duration-300">
                    <div class="flex flex-col">
                        <span class="text-xl font-bold text-primary dark:text-sage font-logo tracking-wide leading-none">LIBRARIA</span>
                    </div>
                </a>

                <div class="hidden md:flex flex-1 max-w-xl mx-auto">
                    <form action="" method="GET" class="w-full relative group">
                        <input type="text" name="s" placeholder="Cari buku, penulis..."
                               value="<?php echo isset($_GET['s']) ? $_GET['s'] : '' ?>"
                               class="w-full pl-10 pr-4 py-2 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:bg-stone-800 dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-700 focus:ring-0 transition-all text-sm shadow-inner group-hover:bg-white dark:group-hover:bg-stone-800 text-stone-800 dark:text-stone-200 placeholder-stone-500">
                        <span class="material-symbols-outlined absolute left-3 top-2 text-stone-500 dark:text-stone-400 group-focus-within:text-tan text-lg">search</span>
                    </form>
                </div>

                <div class="flex items-center gap-2">
                    <button onclick="toggleDarkMode()" class="w-10 h-10 flex items-center justify-center rounded-full text-stone-500 dark:text-stone-400 hover:bg-primary hover:text-white dark:hover:bg-stone-800 transition-all duration-300">
                        <span class="material-icons-outlined text-xl">dark_mode</span>
                    </button>

                    <div class="hidden lg:flex items-center gap-1 text-sm font-bold text-stone-600 dark:text-stone-400 mr-2">
                        <a href="index.php" class="px-4 py-2 rounded-xl hover:bg-primary hover:text-white dark:hover:bg-stone-800 transition-all duration-300">Beranda</a>
                        <a href="all_books.php" class="px-4 py-2 rounded-xl hover:bg-primary hover:text-white dark:hover:bg-stone-800 transition-all duration-300">Buku</a>
                        <a href="my_orders.php" class="px-4 py-2 rounded-xl hover:bg-primary hover:text-white dark:hover:bg-stone-800 transition-all duration-300">Pesanan</a>
                        <a href="chat_list.php" class="px-4 py-2 rounded-xl hover:bg-primary hover:text-white dark:hover:bg-stone-800 transition-all duration-300">Chat</a>
                    </div>

                    <div class="relative">
                        <button onclick="toggleDropdown('notificationDropdown')" class="w-10 h-10 flex items-center justify-center rounded-full text-stone-500 dark:text-stone-400 hover:bg-primary hover:text-white dark:hover:bg-stone-800 transition-all duration-300 relative">
                            <span class="material-symbols-outlined">notifications</span>
                            <?php if(isset($total_notif) && $total_notif > 0): ?>
                                <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white dark:border-stone-900 animate-ping"></span>
                                <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white dark:border-stone-900"></span>
                            <?php endif; ?>
                        </button>
                        <div id="notificationDropdown" class="absolute right-0 mt-3 w-80 bg-white dark:bg-stone-900 rounded-2xl shadow-xl border border-tan/20 dark:border-stone-800 py-2 hidden transform origin-top-right transition-all z-50">
                            <div class="px-4 py-3 border-b border-tan/10 dark:border-stone-800 flex justify-between items-center">
                                <h4 class="font-bold text-primary dark:text-sage text-sm">Notifikasi</h4>
                                <?php if(isset($total_notif) && $total_notif > 0): ?><span class="text-[10px] bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 px-2 py-0.5 rounded-full font-bold"><?php echo $total_notif ?> Baru</span><?php endif; ?>
                            </div>
                            <div class="max-h-64 overflow-y-auto custom-scroll">
                                <?php if(isset($notif_list) && !empty($notif_list)): ?>
                                    <?php foreach($notif_list as $n): ?>
                                    <a href="<?php echo $n['link'] ?>" class="flex items-start gap-3 px-4 py-3 hover:bg-cream dark:hover:bg-stone-800 transition-colors border-b border-tan/10 dark:border-stone-800 last:border-0">
                                        <div class="p-2 bg-<?php echo $n['color'] ?>-100 dark:bg-<?php echo $n['color'] ?>-900/30 text-<?php echo $n['color'] ?>-600 dark:text-<?php echo $n['color'] ?>-400 rounded-full shrink-0">
                                            <span class="material-symbols-outlined text-lg"><?php echo $n['icon'] ?></span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-stone-800 dark:text-stone-200"><?php echo $n['title'] ?></p>
                                            <p class="text-xs text-stone-500 dark:text-stone-400"><?php echo $n['text'] ?></p>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-6 text-stone-400 text-xs italic">Tidak ada notifikasi baru.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <a href="cart.php" class="relative w-10 h-10 flex items-center justify-center rounded-full border border-tan/20 dark:border-stone-800 bg-white dark:bg-stone-900 text-stone-500 dark:text-stone-400 hover:text-primary dark:hover:text-sage hover:shadow-md transition-all">
                        <span class="material-symbols-outlined">shopping_bag</span>
                        <span id="cart-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold h-5 w-5 flex items-center justify-center rounded-full border-2 border-white dark:border-stone-900 animate-bounce <?php echo (isset($cart_count) && $cart_count > 0) ? '' : 'hidden' ?>"><?php echo isset($cart_count) ? $cart_count : 0 ?></span>
                    </a>

                    <div class="relative ml-1">
                        <button onclick="toggleDropdown('profileDropdown')" class="flex items-center gap-2 pl-1 pr-1 md:pr-3 py-1 rounded-full border border-transparent hover:bg-white dark:hover:bg-stone-800 hover:shadow-sm hover:border-tan/20 dark:hover:border-stone-700 transition-all duration-300 focus:outline-none">
                            <img src="<?php echo isset($profile_pic) ? $profile_pic : '../assets/images/default_profile.png' ?>" class="h-9 w-9 rounded-full object-cover border border-tan dark:border-stone-600">
                            <div class="hidden md:block text-left">
                                <p class="text-[10px] text-stone-500 dark:text-stone-400 font-bold uppercase leading-none mb-0.5">Hi,</p>
                                <p class="text-xs font-bold text-primary dark:text-sage leading-none truncate max-w-[80px]"><?php echo isset($buyer_name) ? explode(' ', $buyer_name)[0] : 'User' ?></p>
                            </div>
                            <span class="material-symbols-outlined text-stone-500 dark:text-stone-400 text-sm hidden md:block">expand_more</span>
                        </button>
                        <div id="profileDropdown" class="absolute right-0 mt-3 w-56 bg-white dark:bg-stone-900 rounded-2xl shadow-xl border border-tan/20 dark:border-stone-800 py-2 hidden transform origin-top-right transition-all z-50">
                            <div class="px-4 py-3 border-b border-tan/10 dark:border-stone-800 md:hidden">
                                <p class="text-sm font-bold text-primary dark:text-sage"><?php echo isset($buyer_name) ? $buyer_name : 'User' ?></p>
                            </div>
                            <div class="lg:hidden border-b border-tan/10 dark:border-stone-800 pb-2 mb-2">
                                <a href="index.php" class="flex items-center gap-3 px-4 py-2 hover:bg-cream dark:hover:bg-stone-800 text-sm text-stone-600 dark:text-stone-300"><span class="material-symbols-outlined text-lg">home</span> Beranda</a>
                                <a href="all_books.php" class="flex items-center gap-3 px-4 py-2 hover:bg-cream dark:hover:bg-stone-800 text-sm text-stone-600 dark:text-stone-300"><span class="material-symbols-outlined text-lg">menu_book</span> Buku</a>
                                <a href="my_orders.php" class="flex items-center gap-3 px-4 py-2 hover:bg-cream dark:hover:bg-stone-800 text-sm text-stone-600 dark:text-stone-300"><span class="material-symbols-outlined text-lg">receipt_long</span> Pesanan</a>
                                <a href="chat_list.php" class="flex items-center gap-3 px-4 py-2 hover:bg-cream dark:hover:bg-stone-800 text-sm text-stone-600 dark:text-stone-300"><span class="material-symbols-outlined text-lg">chat</span> Chat</a>
                            </div>
                            <a href="profile.php" class="flex items-center gap-3 px-4 py-2 hover:bg-cream dark:hover:bg-stone-800 text-sm font-bold text-stone-800 dark:text-stone-200"><span class="material-symbols-outlined text-lg">person</span> Akun Saya</a>
                            <a href="help.php" class="flex items-center gap-3 px-4 py-2 hover:bg-cream dark:hover:bg-stone-800 text-sm font-bold text-stone-800 dark:text-stone-200"><span class="material-symbols-outlined text-lg">help</span> Bantuan</a>
                            <div class="border-t border-tan/10 dark:border-stone-800 my-1"></div>
                            <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-2 hover:bg-red-50 dark:hover:bg-red-900/20 text-sm font-bold text-red-600 dark:text-red-400 transition-colors"><span class="material-symbols-outlined text-lg">logout</span> Keluar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>



    <main class="flex-1 pt-32 pb-12 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto w-full">

        <h1 class="text-3xl font-bold text-primary dark:text-sage title-font mb-8" data-aos="fade-right">Keranjang Belanja</h1>

        <?php if(mysqli_num_rows($cart_items) > 0): ?>
        <div class="flex flex-col lg:flex-row gap-8" data-aos="fade-up">

            <div class="flex-1 space-y-6">
                <?php
                $current_seller = "";
                while($item = mysqli_fetch_assoc($cart_items)):
                    $subtotal = $item['sell_price'] * $item['qty'];
                    $grand_total += $subtotal;
                    $total_items_count += $item['qty'];

                    if($current_seller != $item['seller_name']):
                        $current_seller = $item['seller_name'];
                ?>
                    <div class="flex items-center gap-2 mt-6 mb-2">
                        <span class="material-symbols-outlined text-tan">storefront</span>
                        <h3 class="font-bold text-primary dark:text-sage"><?= $current_seller ?></h3>
                    </div>
                <?php endif; ?>

                <div id="cart-item-<?= $item['cart_id'] ?>" class="bg-white dark:bg-stone-900 rounded-[2rem] p-4 border border-tan/20 dark:border-stone-800 card-shadow flex gap-4 items-center relative overflow-hidden group">

                    <div class="w-20 h-28 bg-cream dark:bg-stone-800 rounded-xl overflow-hidden shrink-0 border border-tan/20 dark:border-stone-800">
                        <img src="<?= !empty($item['image']) ? '../assets/uploads/books/'.$item['image'] : '../assets/images/book_placeholder.png' ?>" class="w-full h-full object-cover">
                    </div>

                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-stone-800 dark:text-stone-200 text-lg line-clamp-1 mb-1"><?= $item['title'] ?></h4>
                        <p class="text-chocolate dark:text-tan font-bold text-sm mb-3">Rp <?= number_format($item['sell_price'], 0, ',', '.') ?></p>

                        <div class="flex items-center gap-4">
                            <div class="flex items-center border border-tan/20 dark:border-stone-800 rounded-xl px-1 py-1">
                                <button onclick="updateCart(<?= $item['cart_id'] ?>, 'decrease')" class="w-7 h-7 flex items-center justify-center text-stone-500 dark:text-stone-400 hover:bg-cream dark:bg-stone-800 rounded-lg transition-colors"><span class="material-symbols-outlined text-sm">remove</span></button>

                                <span id="qty-display-<?= $item['cart_id'] ?>" class="w-8 text-center text-sm font-bold"><?= $item['qty'] ?></span>

                                <button onclick="updateCart(<?= $item['cart_id'] ?>, 'increase')" class="w-7 h-7 flex items-center justify-center text-stone-500 dark:text-stone-400 hover:bg-cream dark:bg-stone-800 rounded-lg transition-colors"><span class="material-symbols-outlined text-sm">add</span></button>
                            </div>
                            <span class="text-xs text-stone-500 dark:text-stone-400">Stok: <?= $item['stock'] ?></span>
                        </div>
                    </div>

                    <button onclick="updateCart(<?= $item['cart_id'] ?>, 'remove')" class="absolute top-4 right-4 text-stone-300 hover:text-red-500 transition-colors p-2" title="Hapus">
                        <span class="material-symbols-outlined">delete</span>
                    </button>
                </div>
                <?php endwhile; ?>
            </div>

            <div class="lg:w-96 shrink-0">
                <div class="bg-white dark:bg-stone-900 rounded-[2.5rem] p-8 border border-tan/20 dark:border-stone-800 card-shadow sticky top-32">
                    <h3 class="text-xl font-bold text-primary dark:text-sage mb-6 title-font">Ringkasan Belanja</h3>

                    <div class="space-y-3 mb-6 pb-6 border-b border-dashed border-tan/20 dark:border-stone-800">
                        <div class="flex justify-between text-sm text-stone-500 dark:text-stone-400">
                            <span>Total Estimasi</span>
                            <span class="italic text-xs">Belum termasuk ongkir</span>
                        </div>
                    </div>

                    <div class="flex justify-between items-end mb-8">
                        <span class="text-sm font-bold text-stone-800 dark:text-stone-200 uppercase">Total Harga</span>
                        <span id="grand-total-display" class="text-2xl font-bold text-chocolate dark:text-tan">Rp <?= number_format($grand_total, 0, ',', '.') ?></span>
                    </div>

                    <div class="mt-4">
                        <a href="checkout.php" class="w-full py-4 bg-primary text-white font-bold rounded-2xl shadow-xl hover:bg-chocolate transition-all flex items-center justify-center gap-2 group">
                            Lanjut Pembayaran <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                        </a>
                    </div>

                    <p class="text-[10px] text-center text-stone-500 dark:text-stone-400 mt-4">
                        <span class="material-symbols-outlined text-sm align-middle">verified_user</span> Transaksi Aman & Terpercaya
                    </p>
                </div>
            </div>

        </div>
        <?php else: ?>
            <div class="text-center py-20 bg-white dark:bg-stone-900 rounded-[2.5rem] border-2 border-dashed border-tan/20 dark:border-stone-800">
                <div class="w-24 h-24 bg-sage/20 rounded-full flex items-center justify-center mx-auto mb-6">
                    <span class="material-symbols-outlined text-6xl text-primary dark:text-sage opacity-50">remove_shopping_cart</span>
                </div>
                <h3 class="text-2xl font-bold text-stone-800 dark:text-stone-200 mb-2">Keranjangmu Masih Kosong</h3>
                <p class="text-stone-500 dark:text-stone-400 mb-8">Yuk isi dengan buku-buku yang kamu impikan!</p>
                <a href="index.php" class="px-8 py-3 bg-primary text-white rounded-xl font-bold hover:bg-chocolate transition-colors shadow-lg">
                    Mulai Belanja
                </a>
            </div>
        <?php endif; ?>

    </main>

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

        // --- AJAX UPDATE CART ---
        function updateCart(cartId, action) {
            // Konfirmasi hanya jika user klik tombol Hapus (bukan minus)
            if(action === 'remove' && !confirm('Hapus buku ini dari keranjang?')) return;

            const formData = new FormData();
            formData.append('cart_id', cartId);
            formData.append('action', action);

            // Fetch ke cart_update.php
            fetch('cart_update.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {

                    // CEK HASIL AKSI: APAKAH UPDATED ATAU REMOVED?
                    if (data.action_result === 'removed') {
                        // Jika dihapus (karena remove ATAU qty jadi 0)
                        const itemCard = document.getElementById('cart-item-' + cartId);
                        if(itemCard) {
                            itemCard.style.transition = "all 0.3s ease";
                            itemCard.style.opacity = "0";
                            itemCard.style.transform = "translateX(50px)";
                            setTimeout(() => itemCard.remove(), 300);
                        }

                        showToast(data.message, 'success');

                        // Jika keranjang jadi kosong total, reload page
                        if(data.cart_badge == 0) setTimeout(() => location.reload(), 500);

                    } else {
                        // Jika hanya update jumlah
                        const qtyDisplay = document.getElementById('qty-display-' + cartId);
                        if(qtyDisplay) qtyDisplay.innerText = data.new_qty;
                    }

                    // Selalu Update Total Harga & Badge Navbar
                    const grandTotalDisplay = document.getElementById('grand-total-display');
                    if(grandTotalDisplay) grandTotalDisplay.innerText = data.grand_total_rp;

                    const badge = document.getElementById('cart-badge');
                    if(badge) {
                        badge.innerText = data.cart_badge;
                        if(data.cart_badge > 0) badge.classList.remove('hidden');
                        else badge.classList.add('hidden');
                    }

                } else {
                    // Jika error (misal item sudah tidak ada di DB)
                    if (data.code === 'item_not_found') {
                        const itemCard = document.getElementById('cart-item-' + cartId);
                        if(itemCard) itemCard.remove();
                        location.reload();
                    } else {
                        showToast(data.message, 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Gagal menghubungi server.', 'error');
            });
        }

        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-primary' : 'bg-red-600';
            const icon = type === 'success' ? 'check_circle' : 'error';

            toast.className = `flex items-center gap-3 px-6 py-4 rounded-2xl shadow-2xl text-white ${bgColor} toast-enter cursor-pointer backdrop-blur-md bg-opacity-95`;
            toast.innerHTML = `<span class="material-symbols-outlined">${icon}</span><p class="text-sm font-bold">${message}</p>`;

            toast.onclick = () => { toast.classList.add('toast-exit'); setTimeout(() => toast.remove(), 300); };
            container.appendChild(toast);
            setTimeout(() => { if (toast.isConnected) { toast.classList.add('toast-exit'); setTimeout(() => toast.remove(), 300); } }, 3000);
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
