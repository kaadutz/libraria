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

// 2. Cek ID Buku
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$book_id = mysqli_real_escape_string($conn, $_GET['id']);

// 3. Ambil Data Buku Detail
$query = "
    SELECT b.*, 
           c.name as category_name, 
           u.full_name as seller_name, 
           u.address as seller_address, 
           u.profile_image as seller_image,
           u.id as seller_id
    FROM books b
    JOIN categories c ON b.category_id = c.id
    JOIN users u ON b.seller_id = u.id
    WHERE b.id = '$book_id'
";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    echo "<script>alert('Buku tidak ditemukan!'); window.location='index.php';</script>";
    exit;
}

$book = mysqli_fetch_assoc($result);
$img_src = !empty($book['image']) ? "../assets/uploads/books/".$book['image'] : "../assets/images/book_placeholder.png";
$seller_pic = !empty($book['seller_image']) ? "../assets/uploads/profiles/".$book['seller_image'] : "../assets/images/default_profile.png";
$book_author = !empty($book['author']) ? $book['author'] : 'Penulis tidak disebutkan';

// --- LOGIKA BARU: TEMPLATE PESAN & LINK CHAT ---
$template_chat = "Halo kak, saya tertarik dengan buku *" . $book['title'] . "*. Apakah stok masih tersedia?";
// Kita kirim uid (seller), msg (pesan), dan book_id (untuk preview gambar)
$link_chat_template = "chat_list.php?uid=" . $book['seller_id'] . "&msg=" . urlencode($template_chat) . "&book_id=" . $book['id'];

// --- DATA NAVBAR ---
$query_user = mysqli_query($conn, "SELECT profile_image FROM users WHERE id = '$buyer_id'");
$user_data = mysqli_fetch_assoc($query_user);
$profile_pic = !empty($user_data['profile_image']) ? "../assets/uploads/profiles/" . $user_data['profile_image'] : "../assets/images/default_profile.png";

$query_cart = mysqli_query($conn, "SELECT SUM(qty) as total FROM carts WHERE buyer_id = '$buyer_id'");
$cart_count = mysqli_fetch_assoc($query_cart)['total'] ?? 0;

$query_notif = mysqli_query($conn, "SELECT COUNT(*) as total FROM messages WHERE receiver_id = '$buyer_id' AND is_read = 0");
$total_chat_unread = mysqli_fetch_assoc($query_notif)['total'];
$total_notif = $total_chat_unread;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= $book['title'] ?> - Libraria</title>

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
                            <?php if($total_notif > 0): ?>
                                <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white animate-ping"></span>
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
                                    <div><p class="text-sm font-bold text-gray-800">Pesan Masuk</p><p class="text-xs text-gray-500">Anda memiliki <?= $total_chat_unread ?> pesan belum dibaca.</p></div>
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

    <main class="flex-1 pt-32 pb-12 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto w-full">
        
        <nav class="flex text-sm text-[var(--text-muted)] mb-6" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center"><a href="index.php" class="hover:text-[var(--deep-forest)]">Beranda</a></li>
                <li><span class="mx-1">/</span></li>
                <li class="inline-flex items-center"><a href="index.php?cat=<?= $book['category_id'] ?>" class="hover:text-[var(--deep-forest)]"><?= $book['category_name'] ?></a></li>
                <li><span class="mx-1">/</span></li>
                <li aria-current="page"><span class="font-bold text-[var(--deep-forest)] truncate max-w-[200px]"><?= $book['title'] ?></span></li>
            </ol>
        </nav>

        <div class="bg-white rounded-[2.5rem] p-6 md:p-10 border border-[var(--border-color)] shadow-xl shadow-[#3E4B1C]/5 relative overflow-hidden" data-aos="fade-up">
            
            <div class="flex flex-col lg:flex-row gap-10">
                
                <div class="lg:w-5/12 flex flex-col gap-4">
                    <div class="aspect-[3/4] bg-[var(--cream-bg)] rounded-3xl overflow-hidden relative shadow-inner border border-[var(--border-color)]">
                        <img src="<?= $img_src ?>" alt="<?= $book['title'] ?>" class="w-full h-full object-cover">
                    </div>
                </div>

                <div class="lg:w-7/12 flex flex-col">
                    
                    <div class="mb-4">
                        <span class="inline-block px-3 py-1 bg-[var(--light-sage)]/30 text-[var(--deep-forest)] rounded-lg text-xs font-bold uppercase tracking-wider mb-2 border border-[var(--light-sage)]">
                            <?= $book['category_name'] ?>
                        </span>
                        
                        <h1 class="text-3xl md:text-4xl font-bold text-[var(--text-dark)] leading-tight mb-2 title-font">
                            <?= $book['title'] ?>
                        </h1>

                        <div class="flex items-center gap-2 text-[var(--text-muted)] text-sm font-semibold mb-2">
                            <span class="material-symbols-outlined text-lg">person</span>
                            <span>Penulis: <span class="text-[var(--deep-forest)]"><?= $book_author ?></span></span>
                        </div>
                    </div>

                    <div class="flex items-end gap-4 mb-6 pb-6 border-b border-dashed border-[var(--border-color)]">
                        <div>
                            <p class="text-xs text-[var(--text-muted)] font-bold uppercase mb-1">Harga Terbaik</p>
                            <span class="text-3xl md:text-4xl font-bold text-[var(--chocolate-brown)]">
                                Rp <?= number_format($book['sell_price'], 0, ',', '.') ?>
                            </span>
                        </div>
                        <div class="ml-auto text-right">
                            <p class="text-xs text-[var(--text-muted)] font-bold uppercase mb-1">Stok Tersedia</p>
                            <span class="text-xl font-bold <?= $book['stock'] <= 5 ? 'text-red-500' : 'text-[var(--deep-forest)]' ?>">
                                <?= $book['stock'] ?> <span class="text-sm font-normal text-[var(--text-muted)]">Pcs</span>
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 mb-8 p-4 bg-[var(--cream-bg)]/50 rounded-2xl border border-[var(--border-color)]">
                        <img src="<?= $seller_pic ?>" class="w-12 h-12 rounded-full object-cover border-2 border-white shadow-sm">
                        <div class="flex-1">
                            <p class="text-xs text-[var(--text-muted)] font-bold uppercase">Penjual</p>
                            <h4 class="text-sm font-bold text-[var(--deep-forest)]"><?= $book['seller_name'] ?></h4>
                            <p class="text-xs text-stone-500 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px]">location_on</span> <?= $book['seller_address'] ? $book['seller_address'] : 'Lokasi tidak tersedia' ?>
                            </p>
                        </div>
                        <a href="<?= $link_chat_template ?>" class="p-2 bg-white text-[var(--deep-forest)] rounded-xl shadow-sm border border-[var(--border-color)] hover:bg-[var(--deep-forest)] hover:text-white transition-colors" title="Tanya Produk Ini">
                            <span class="material-symbols-outlined">chat</span>
                        </a>
                    </div>

                    <div class="mb-8 flex-1">
                        <h3 class="text-lg font-bold text-[var(--text-dark)] mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[var(--warm-tan)]">description</span> Deskripsi Buku
                        </h3>
                        <div class="prose prose-stone text-sm text-stone-600 leading-relaxed whitespace-pre-line max-h-60 overflow-y-auto custom-scroll pr-2">
                            <?= $book['description'] ?>
                        </div>
                    </div>

                    <div class="mt-auto flex flex-col sm:flex-row gap-4 pt-6 border-t border-[var(--border-color)]">
                        <div class="flex items-center border border-[var(--border-color)] rounded-xl bg-white w-fit px-2 h-12">
                            <button onclick="updateQty(-1)" class="w-8 h-full flex items-center justify-center text-[var(--text-muted)] hover:text-[var(--deep-forest)]"><span class="material-symbols-outlined text-sm">remove</span></button>
                            <input type="number" id="qtyInput" value="1" min="1" max="<?= $book['stock'] ?>" class="w-12 text-center border-none focus:ring-0 font-bold text-[var(--text-dark)] bg-transparent p-0" readonly>
                            <button onclick="updateQty(1)" class="w-8 h-full flex items-center justify-center text-[var(--text-muted)] hover:text-[var(--deep-forest)]"><span class="material-symbols-outlined text-sm">add</span></button>
                        </div>

                        <button onclick="addToCart(<?= $book['id'] ?>)" class="flex-1 py-3 px-6 border-2 border-[var(--deep-forest)] text-[var(--deep-forest)] font-bold rounded-xl hover:bg-[var(--deep-forest)] hover:text-white transition-all flex items-center justify-center gap-2 group h-12">
                            <span class="material-symbols-outlined group-hover:scale-110 transition-transform">add_shopping_cart</span> Tambah Keranjang
                        </button>

                        <button onclick="buyNow(<?= $book['id'] ?>)" class="flex-1 py-3 px-6 bg-[var(--chocolate-brown)] text-white font-bold rounded-xl hover:opacity-90 transition-all shadow-lg shadow-orange-900/20 flex items-center justify-center gap-2 h-12">
                            <span class="material-symbols-outlined">shopping_bag</span> Beli Sekarang
                        </button>
                    </div>

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
            const allDropdowns = document.querySelectorAll('[id$="Dropdown"]');
            allDropdowns.forEach(dd => {
                if (dd.id !== id) dd.classList.add('hidden');
            });
            const dropdown = document.getElementById(id);
            if(dropdown) dropdown.classList.toggle('hidden');
        }

        window.onclick = function(event) {
            if (!event.target.closest('button')) {
                const dropdowns = document.querySelectorAll('[id$="Dropdown"]');
                dropdowns.forEach(dd => dd.classList.add('hidden'));
            }
        }

        function updateQty(change) {
            const input = document.getElementById('qtyInput');
            let val = parseInt(input.value) + change;
            if (val < 1) val = 1;
            if (val > <?= $book['stock'] ?>) {
                val = <?= $book['stock'] ?>;
                showToast('Stok maksimal tercapai!', 'error');
            }
            input.value = val;
        }

        function addToCart(bookId) {
            const qty = document.getElementById('qtyInput').value;
            const formData = new FormData();
            formData.append('book_id', bookId);
            formData.append('qty', qty);

            fetch('add_to_cart.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const badge = document.getElementById('cart-badge');
                    badge.innerText = data.new_count;
                    badge.classList.remove('hidden');
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => { console.error('Error:', error); showToast('Gagal menghubungi server.', 'error'); });
        }

        function buyNow(bookId) {
            const qty = document.getElementById('qtyInput').value;
            const formData = new FormData();
            formData.append('book_id', bookId);
            formData.append('qty', qty);

            fetch('add_to_cart.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') { window.location.href = 'cart.php'; } 
                else { showToast(data.message, 'error'); }
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