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
$buyer_name = $_SESSION['full_name'];

// --- LOGIC: CHECKOUT ---
if (isset($_POST['checkout'])) {
    $q_cart = mysqli_query($conn, "
        SELECT c.*, b.sell_price as current_price, b.seller_id 
        FROM carts c 
        JOIN books b ON c.book_id = b.id 
        WHERE c.buyer_id = '$buyer_id'
    ");

    if (mysqli_num_rows($q_cart) > 0) {
        $orders_by_seller = [];
        while ($item = mysqli_fetch_assoc($q_cart)) {
            $orders_by_seller[$item['seller_id']][] = $item;
        }

        mysqli_begin_transaction($conn);
        try {
            foreach ($orders_by_seller as $seller_id => $items) {
                $invoice = "INV/" . date('Ymd') . "/" . strtoupper(uniqid());
                $date = date('Y-m-d H:i:s');
                
                $q_order = "INSERT INTO orders (buyer_id, invoice_number, order_date, status) VALUES ('$buyer_id', '$invoice', '$date', 'pending')";
                mysqli_query($conn, $q_order);
                $order_id = mysqli_insert_id($conn);

                foreach ($items as $item) {
                    $book_id = $item['book_id'];
                    $qty = $item['qty'];
                    $price = $item['current_price']; 

                    mysqli_query($conn, "INSERT INTO order_items (order_id, seller_id, book_id, qty, price_at_transaction) VALUES ('$order_id', '$seller_id', '$book_id', '$qty', '$price')");
                    mysqli_query($conn, "UPDATE books SET stock = stock - $qty WHERE id = '$book_id'");
                }
            }
            mysqli_query($conn, "DELETE FROM carts WHERE buyer_id = '$buyer_id'");
            mysqli_commit($conn);
            $success_checkout = true;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Terjadi kesalahan saat checkout.";
        }
    }
}

if (isset($success_checkout)) {
    echo "<script>alert('Pesanan berhasil dibuat!'); window.location='my_orders.php?status=pending';</script>";
    exit;
}

// --- DATA TAMPILAN ---
$query_user = mysqli_query($conn, "SELECT profile_image FROM users WHERE id = '$buyer_id'");
$user_data = mysqli_fetch_assoc($query_user);
$profile_pic = !empty($user_data['profile_image']) ? "../assets/uploads/profiles/" . $user_data['profile_image'] : "../assets/images/default_profile.png";

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

$query_notif = mysqli_query($conn, "SELECT COUNT(*) as total FROM messages WHERE receiver_id = '$buyer_id' AND is_read = 0");
$total_notif = mysqli_fetch_assoc($query_notif)['total'];

// Cart count awal
$query_cart_count = mysqli_query($conn, "SELECT SUM(qty) as total FROM carts WHERE buyer_id = '$buyer_id'");
$cart_count = mysqli_fetch_assoc($query_cart_count)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Keranjang Belanja - Libraria</title>

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
        .card-shadow { box-shadow: 0 10px 40px -10px rgba(62, 75, 28, 0.08); }
        
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
        .toast-enter { animation: slideIn 0.3s ease-out forwards; }
        .toast-exit { animation: fadeOut 0.3s ease-out forwards; }
    </style>
</head>
<body class="overflow-x-hidden min-h-screen flex flex-col">

    <div id="toast-container" class="fixed top-28 right-5 z-[60] flex flex-col gap-3"></div>

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
                        <input type="text" name="s" placeholder="Cari buku lain..." class="w-full pl-10 pr-4 py-2 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all text-sm shadow-inner">
                        <span class="material-symbols-outlined absolute left-3 top-2 text-[var(--text-muted)] group-focus-within:text-[var(--warm-tan)] text-lg">search</span>
                    </form>
                </div>

                <div class="flex items-center gap-2">
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
                        <div id="notificationDropdown" class="absolute right-0 mt-3 w-72 bg-white rounded-2xl shadow-xl border border-[var(--border-color)] py-2 hidden z-50">
                            <div class="px-4 py-3 border-b border-[var(--border-color)]"><h4 class="font-bold text-sm">Notifikasi</h4></div>
                            <div class="p-4 text-xs text-center text-[var(--text-muted)]">
                                <?= $total_notif > 0 ? "Ada $total_notif pesan baru." : "Tidak ada notifikasi baru." ?>
                            </div>
                        </div>
                    </div>

                    <a href="cart.php" class="relative w-10 h-10 flex items-center justify-center rounded-full border border-[var(--border-color)] bg-white text-[var(--text-muted)] hover:text-[var(--deep-forest)] hover:shadow-md transition-all">
                        <span class="material-symbols-outlined">shopping_bag</span>
                        <span id="cart-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold h-5 w-5 flex items-center justify-center rounded-full border-2 border-white animate-bounce <?= $cart_count > 0 ? '' : 'hidden' ?>">
                            <?= $cart_count ?>
                        </span>
                    </a>

                    <div class="relative ml-1">
                        <button onclick="toggleDropdown('profileDropdown')" class="flex items-center gap-2 pl-1 pr-1 md:pr-3 py-1 rounded-full border border-transparent hover:bg-white hover:shadow-sm hover:border-[var(--border-color)] transition-all duration-300 focus:outline-none">
                            <img src="<?= $profile_pic ?>" class="h-9 w-9 rounded-full object-cover border border-[var(--warm-tan)]">
                            <span class="material-symbols-outlined text-[var(--text-muted)] text-sm hidden md:block">expand_more</span>
                        </button>
                        <div id="profileDropdown" class="absolute right-0 mt-3 w-56 bg-white rounded-2xl shadow-xl border border-[var(--border-color)] py-2 hidden z-50">
                            <a href="profile.php" class="flex items-center gap-3 px-4 py-2 hover:bg-[var(--cream-bg)] text-sm font-bold text-[var(--text-dark)]"><span class="material-symbols-outlined text-lg">person</span> Akun Saya</a>
                            <div class="border-t border-[var(--border-color)] my-1"></div>
                            <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-2 hover:bg-red-50 text-sm font-bold text-red-600 transition-colors"><span class="material-symbols-outlined text-lg">logout</span> Keluar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-1 pt-32 pb-12 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto w-full">
        
        <h1 class="text-3xl font-bold text-[var(--deep-forest)] title-font mb-8" data-aos="fade-right">Keranjang Belanja</h1>

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
                        <span class="material-symbols-outlined text-[var(--warm-tan)]">storefront</span>
                        <h3 class="font-bold text-[var(--deep-forest)]"><?= $current_seller ?></h3>
                    </div>
                <?php endif; ?>

                <div id="cart-item-<?= $item['cart_id'] ?>" class="bg-white rounded-[2rem] p-4 border border-[var(--border-color)] card-shadow flex gap-4 items-center relative overflow-hidden group">
                    
                    <div class="w-20 h-28 bg-[var(--cream-bg)] rounded-xl overflow-hidden shrink-0 border border-[var(--border-color)]">
                        <img src="<?= !empty($item['image']) ? '../assets/uploads/books/'.$item['image'] : '../assets/images/book_placeholder.png' ?>" class="w-full h-full object-cover">
                    </div>

                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-[var(--text-dark)] text-lg line-clamp-1 mb-1"><?= $item['title'] ?></h4>
                        <p class="text-[var(--chocolate-brown)] font-bold text-sm mb-3">Rp <?= number_format($item['sell_price'], 0, ',', '.') ?></p>
                        
                        <div class="flex items-center gap-4">
                            <div class="flex items-center border border-[var(--border-color)] rounded-xl px-1 py-1">
                                <button onclick="updateCart(<?= $item['cart_id'] ?>, 'decrease')" class="w-7 h-7 flex items-center justify-center text-[var(--text-muted)] hover:bg-[var(--cream-bg)] rounded-lg transition-colors"><span class="material-symbols-outlined text-sm">remove</span></button>
                                
                                <span id="qty-display-<?= $item['cart_id'] ?>" class="w-8 text-center text-sm font-bold"><?= $item['qty'] ?></span>
                                
                                <button onclick="updateCart(<?= $item['cart_id'] ?>, 'increase')" class="w-7 h-7 flex items-center justify-center text-[var(--text-muted)] hover:bg-[var(--cream-bg)] rounded-lg transition-colors"><span class="material-symbols-outlined text-sm">add</span></button>
                            </div>
                            <span class="text-xs text-[var(--text-muted)]">Stok: <?= $item['stock'] ?></span>
                        </div>
                    </div>

                    <button onclick="updateCart(<?= $item['cart_id'] ?>, 'remove')" class="absolute top-4 right-4 text-stone-300 hover:text-red-500 transition-colors p-2" title="Hapus">
                        <span class="material-symbols-outlined">delete</span>
                    </button>
                </div>
                <?php endwhile; ?>
            </div>

            <div class="lg:w-96 shrink-0">
                <div class="bg-white rounded-[2.5rem] p-8 border border-[var(--border-color)] card-shadow sticky top-32">
                    <h3 class="text-xl font-bold text-[var(--deep-forest)] mb-6 title-font">Ringkasan Belanja</h3>
                    
                    <div class="space-y-3 mb-6 pb-6 border-b border-dashed border-[var(--border-color)]">
                        <div class="flex justify-between text-sm text-[var(--text-muted)]">
                            <span>Total Estimasi</span>
                            <span class="italic text-xs">Belum termasuk ongkir</span>
                        </div>
                    </div>

                    <div class="flex justify-between items-end mb-8">
                        <span class="text-sm font-bold text-[var(--text-dark)] uppercase">Total Harga</span>
                        <span id="grand-total-display" class="text-2xl font-bold text-[var(--chocolate-brown)]">Rp <?= number_format($grand_total, 0, ',', '.') ?></span>
                    </div>

                    <div class="mt-4">
    <a href="checkout.php" class="w-full py-4 bg-[var(--deep-forest)] text-white font-bold rounded-2xl shadow-xl hover:bg-[var(--chocolate-brown)] transition-all flex items-center justify-center gap-2 group">
        Lanjut Pembayaran <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
    </a>
</div>
                    
                    <p class="text-[10px] text-center text-[var(--text-muted)] mt-4">
                        <span class="material-symbols-outlined text-sm align-middle">verified_user</span> Transaksi Aman & Terpercaya
                    </p>
                </div>
            </div>

        </div>
        <?php else: ?>
            <div class="text-center py-20 bg-white rounded-[2.5rem] border-2 border-dashed border-[var(--border-color)]">
                <div class="w-24 h-24 bg-[var(--light-sage)]/20 rounded-full flex items-center justify-center mx-auto mb-6">
                    <span class="material-symbols-outlined text-6xl text-[var(--deep-forest)] opacity-50">remove_shopping_cart</span>
                </div>
                <h3 class="text-2xl font-bold text-[var(--text-dark)] mb-2">Keranjangmu Masih Kosong</h3>
                <p class="text-[var(--text-muted)] mb-8">Yuk isi dengan buku-buku yang kamu impikan!</p>
                <a href="index.php" class="px-8 py-3 bg-[var(--deep-forest)] text-white rounded-xl font-bold hover:bg-[var(--chocolate-brown)] transition-colors shadow-lg">
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
            if(action === 'remove' && !confirm('Hapus buku ini dari keranjang?')) return;

            const formData = new FormData();
            formData.append('cart_id', cartId);
            formData.append('action', action);

            // Fetch ke cart_update.php (nama baru)
            fetch('cart_update.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    
                    if (data.action === 'remove') {
                        const itemCard = document.getElementById('cart-item-' + cartId);
                        itemCard.remove();
                        showToast('Produk dihapus dari keranjang', 'success');
                        
                        if(data.cart_badge == 0) location.reload();

                    } else {
                        const qtyDisplay = document.getElementById('qty-display-' + cartId);
                        qtyDisplay.innerText = data.new_qty;
                    }

                    const grandTotalDisplay = document.getElementById('grand-total-display');
                    grandTotalDisplay.innerText = data.grand_total_rp;

                    const badge = document.getElementById('cart-badge');
                    badge.innerText = data.cart_badge;
                    if(data.cart_badge > 0) badge.classList.remove('hidden');
                    else badge.classList.add('hidden');

                } else {
                    showToast(data.message, 'error');
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
            const bgColor = type === 'success' ? 'bg-[var(--deep-forest)]' : 'bg-red-600';
            const icon = type === 'success' ? 'check_circle' : 'error';

            toast.className = `flex items-center gap-3 px-6 py-4 rounded-2xl shadow-2xl text-white ${bgColor} toast-enter cursor-pointer backdrop-blur-md bg-opacity-95`;
            toast.innerHTML = `<span class="material-symbols-outlined">${icon}</span><p class="text-sm font-bold">${message}</p>`;
            
            toast.onclick = () => { toast.classList.add('toast-exit'); setTimeout(() => toast.remove(), 300); };
            container.appendChild(toast);
            setTimeout(() => { if (toast.isConnected) { toast.classList.add('toast-exit'); setTimeout(() => toast.remove(), 300); } }, 3000);
        }
    </script>
</body>
</html>