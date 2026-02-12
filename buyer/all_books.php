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

// Ambil Foto Profil
$query_user = mysqli_query($conn, "SELECT profile_image FROM users WHERE id = '$buyer_id'");
$user_data = mysqli_fetch_assoc($query_user);
$profile_pic = !empty($user_data['profile_image']) ? "../assets/uploads/profiles/" . $user_data['profile_image'] : "../assets/images/default_profile.png";

// 2. HITUNG ISI KERANJANG
$query_cart = mysqli_query($conn, "SELECT SUM(qty) as total FROM carts WHERE buyer_id = '$buyer_id'");
$cart_data = mysqli_fetch_assoc($query_cart);
$cart_count = $cart_data['total'] ?? 0;

// 3. HITUNG NOTIFIKASI
$query_notif = mysqli_query($conn, "SELECT COUNT(*) as total FROM messages WHERE receiver_id = '$buyer_id' AND is_read = 0");
$total_chat_unread = mysqli_fetch_assoc($query_notif)['total'];
$total_notif = $total_chat_unread;

// --- 4. LOGIKA FILTER & PAGINATION ---
$limit = 10; // 10 Buku per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page > 1) ? ($page * $limit) - $limit : 0;

$where_clause = "WHERE b.stock > 0";

// Filter Pencarian
if (isset($_GET['s']) && !empty($_GET['s'])) {
    $search = mysqli_real_escape_string($conn, $_GET['s']);
    $where_clause .= " AND (b.title LIKE '%$search%' OR b.description LIKE '%$search%' OR b.author LIKE '%$search%')";
}

// Filter Kategori (Dari Dropdown)
if (isset($_GET['cat']) && !empty($_GET['cat'])) {
    $cat_id = mysqli_real_escape_string($conn, $_GET['cat']);
    $where_clause .= " AND b.category_id = '$cat_id'";
}

// Logic Sorting
$order_clause = "ORDER BY b.created_at DESC"; // Default
if (isset($_GET['sort'])) {
    if ($_GET['sort'] == 'price_low') {
        $order_clause = "ORDER BY b.sell_price ASC";
    } elseif ($_GET['sort'] == 'price_high') {
        $order_clause = "ORDER BY b.sell_price DESC";
    } elseif ($_GET['sort'] == 'oldest') {
        $order_clause = "ORDER BY b.created_at ASC";
    }
}

// Hitung Total Data
$query_total = "SELECT COUNT(*) as total FROM books b $where_clause";
$total_records = mysqli_fetch_assoc(mysqli_query($conn, $query_total))['total'];
$total_pages = ceil($total_records / $limit);

// Ambil Data Buku
$query_books = "
    SELECT b.*, c.name as category_name, u.full_name as seller_name
    FROM books b
    JOIN categories c ON b.category_id = c.id
    JOIN users u ON b.seller_id = u.id
    $where_clause
    $order_clause
    LIMIT $start, $limit
";
$books = mysqli_query($conn, $query_books);
$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");

// Helper Link Pagination
function getPaginationLink($target_page) {
    $params = $_GET;
    $params['page'] = $target_page;
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Katalog Buku - Libraria</title>

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

        /* Toast Animation */
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
        .toast-enter { animation: slideIn 0.3s ease-out forwards; }
        .toast-exit { animation: fadeOut 0.3s ease-out forwards; }
    </style>
<script src="../assets/js/theme-manager.js"></script>
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
                    <form action="" method="GET" class="w-full relative group">
                        <input type="text" name="s" placeholder="Cari buku, penulis..." value="<?= isset($_GET['s']) ? $_GET['s'] : '' ?>" class="w-full pl-10 pr-4 py-2 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all text-sm shadow-inner group-hover:bg-white group-hover:shadow-md">
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
                    <a href="help.php" class="w-10 h-10 flex items-center justify-center rounded-full text-[var(--text-muted)] hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] transition-all" title="Bantuan"><span class="material-symbols-outlined">help</span></a>
                    <div class="relative">
                        <button onclick="toggleDropdown('notificationDropdown')" class="w-10 h-10 flex items-center justify-center rounded-full text-[var(--text-muted)] hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] transition-all relative">
                            <span class="material-symbols-outlined">notifications</span>
                            <?php if($total_notif > 0): ?><span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white animate-ping"></span><?php endif; ?>
                        </button>
                        <div id="notificationDropdown" class="absolute right-0 mt-3 w-72 bg-white rounded-2xl shadow-xl border border-[var(--border-color)] py-2 hidden transform origin-top-right transition-all z-50">
                            <div class="px-4 py-3 border-b border-[var(--border-color)] flex justify-between items-center"><h4 class="font-bold text-[var(--deep-forest)] text-sm">Notifikasi</h4><?php if($total_notif > 0): ?><span class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-bold"><?= $total_notif ?> Baru</span><?php endif; ?></div>
                            <div class="max-h-64 overflow-y-auto">
                                <?php if($total_chat_unread > 0): ?>
                                <a href="chat_list.php" class="flex items-center gap-3 px-4 py-3 hover:bg-[var(--cream-bg)] transition-colors"><div class="p-2 bg-blue-100 text-blue-600 rounded-full shrink-0"><span class="material-symbols-outlined text-lg">chat</span></div><div><p class="text-sm font-bold text-gray-800">Pesan Masuk</p><p class="text-xs text-gray-500">Anda memiliki <?= $total_chat_unread ?> pesan belum dibaca.</p></div></a>
                                <?php endif; ?>
                                <?php if($total_notif == 0): ?><div class="text-center py-6 text-gray-400 text-xs italic">Tidak ada notifikasi baru.</div><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <a href="cart.php" class="relative w-10 h-10 flex items-center justify-center rounded-full border border-[var(--border-color)] bg-white text-[var(--text-muted)] hover:text-[var(--deep-forest)] hover:shadow-md transition-all"><span class="material-symbols-outlined">shopping_bag</span><span id="cart-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold h-5 w-5 flex items-center justify-center rounded-full border-2 border-white animate-bounce <?= $cart_count > 0 ? '' : 'hidden' ?>"><?= $cart_count ?></span></a>
                    <div class="relative ml-1">
                        <button onclick="toggleDropdown('profileDropdown')" class="flex items-center gap-2 pl-1 pr-1 md:pr-3 py-1 rounded-full border border-transparent hover:bg-white hover:shadow-sm hover:border-[var(--border-color)] transition-all duration-300 focus:outline-none">
                            <img src="<?= $profile_pic ?>" class="h-9 w-9 rounded-full object-cover border border-[var(--warm-tan)]">
                            <div class="hidden md:block text-left"><p class="text-[10px] text-[var(--text-muted)] font-bold uppercase leading-none mb-0.5">Hi,</p><p class="text-xs font-bold text-[var(--deep-forest)] leading-none truncate max-w-[80px]"><?= explode(' ', $buyer_name)[0] ?></p></div>
                            <span class="material-symbols-outlined text-[var(--text-muted)] text-sm hidden md:block">expand_more</span>
                        </button>
                        <div id="profileDropdown" class="absolute right-0 mt-3 w-56 bg-white rounded-2xl shadow-xl border border-[var(--border-color)] py-2 hidden transform origin-top-right transition-all z-50">
                            <a href="profile.php" class="flex items-center gap-3 px-4 py-2 hover:bg-[var(--cream-bg)] text-sm font-bold text-[var(--text-dark)]"><span class="material-symbols-outlined text-lg">person</span> Akun Saya</a>
                            <a href="help.php" class="flex items-center gap-3 px-4 py-2 hover:bg-[var(--cream-bg)] text-sm font-bold text-[var(--text-dark)]"><span class="material-symbols-outlined text-lg">help</span> Bantuan</a>
                            <div class="border-t border-[var(--border-color)] my-1"></div>
                            <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-2 hover:bg-red-50 text-sm font-bold text-red-600 transition-colors"><span class="material-symbols-outlined text-lg">logout</span> Keluar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-1 pt-32 pb-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full">

        <div class="mb-8" data-aos="fade-down">
            <h1 class="text-3xl font-bold text-[var(--deep-forest)] title-font mb-2">Katalog Buku</h1>
            <p class="text-[var(--text-muted)] text-sm">Temukan <?= $total_records ?> buku menarik untuk dibaca.</p>
        </div>

        <form id="filterForm" action="" method="GET" class="mb-10 bg-white p-5 rounded-[2rem] border border-[var(--border-color)] shadow-sm flex flex-col md:flex-row gap-4 md:items-end" data-aos="fade-up">

            <?php if(isset($_GET['s'])): ?>
                <input type="hidden" name="s" value="<?= htmlspecialchars($_GET['s']) ?>">
            <?php endif; ?>

            <div class="flex-1">
                <label class="block text-xs font-bold text-[var(--text-muted)] uppercase mb-2 ml-1">Kategori Buku</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-2.5 text-[var(--warm-tan)]">category</span>
                    <select name="cat" onchange="this.form.submit()" class="w-full pl-10 pr-8 py-2.5 rounded-xl border border-[var(--border-color)] bg-[var(--cream-bg)] text-[var(--text-dark)] text-sm font-bold focus:border-[var(--warm-tan)] focus:ring-0 cursor-pointer shadow-inner">
                        <option value="">Semua Kategori</option>
                        <?php
                        mysqli_data_seek($categories, 0); // Reset pointer
                        while($cat = mysqli_fetch_assoc($categories)):
                        ?>
                        <option value="<?= $cat['id'] ?>" <?= (isset($_GET['cat']) && $_GET['cat'] == $cat['id']) ? 'selected' : '' ?>>
                            <?= $cat['name'] ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="w-full md:w-64">
                <label class="block text-xs font-bold text-[var(--text-muted)] uppercase mb-2 ml-1">Urutkan</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-2.5 text-[var(--warm-tan)]">sort</span>
                    <select name="sort" onchange="this.form.submit()" class="w-full pl-10 pr-8 py-2.5 rounded-xl border border-[var(--border-color)] bg-[var(--cream-bg)] text-[var(--text-dark)] text-sm font-bold focus:border-[var(--warm-tan)] focus:ring-0 cursor-pointer shadow-inner">
                        <option value="newest" <?= (!isset($_GET['sort']) || $_GET['sort'] == 'newest') ? 'selected' : '' ?>>Terbaru Ditambahkan</option>
                        <option value="price_low" <?= (isset($_GET['sort']) && $_GET['sort'] == 'price_low') ? 'selected' : '' ?>>Harga: Rendah ke Tinggi</option>
                        <option value="price_high" <?= (isset($_GET['sort']) && $_GET['sort'] == 'price_high') ? 'selected' : '' ?>>Harga: Tinggi ke Rendah</option>
                        <option value="oldest" <?= (isset($_GET['sort']) && $_GET['sort'] == 'oldest') ? 'selected' : '' ?>>Terlama Ditambahkan</option>
                    </select>
                </div>
            </div>

            <?php if(isset($_GET['cat']) || isset($_GET['s']) || (isset($_GET['sort']) && $_GET['sort'] != 'newest')): ?>
            <div class="md:pb-0.5">
                <a href="all_books.php" class="h-[42px] px-4 rounded-xl border border-red-200 bg-red-50 text-red-500 font-bold text-sm flex items-center justify-center gap-1 hover:bg-red-100 transition-colors" title="Hapus Filter">
                    <span class="material-symbols-outlined text-lg">filter_alt_off</span>
                </a>
            </div>
            <?php endif; ?>
        </form>

        <div id="book-list" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6" data-aos="fade-up" data-aos-delay="200">
            <?php if(mysqli_num_rows($books) > 0): ?>
                <?php while($book = mysqli_fetch_assoc($books)):
                    $img_src = !empty($book['image']) ? "../assets/uploads/books/".$book['image'] : "../assets/images/book_placeholder.png";
                    $book_author = !empty($book['author']) ? $book['author'] : 'Penulis tidak disebutkan';
                ?>
                <div class="bg-white rounded-[2rem] border border-[var(--border-color)] card-shadow overflow-hidden group relative flex flex-col h-full hover:shadow-xl transition-all duration-300 hover:-translate-y-1">

                    <div class="aspect-[2/3] bg-[var(--cream-bg)] relative overflow-hidden">
                        <span class="absolute top-3 left-3 z-10 px-2 py-1 bg-white/90 backdrop-blur text-[var(--deep-forest)] text-[10px] font-bold uppercase rounded-lg shadow-sm border border-[var(--light-sage)]"><?= $book['category_name'] ?></span>

                        <img src="<?= $img_src ?>" alt="<?= $book['title'] ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">

                        <div class="absolute inset-x-0 bottom-0 p-4 bg-gradient-to-t from-black/80 via-black/40 to-transparent translate-y-full group-hover:translate-y-0 transition-transform duration-300 flex flex-col justify-end h-full">
                            <a href="detail_book.php?id=<?= $book['id'] ?>" class="w-full py-2.5 bg-white text-[var(--deep-forest)] font-bold text-xs rounded-xl text-center hover:bg-[var(--warm-tan)] hover:text-white transition-colors shadow-lg">Lihat Detail</a>
                        </div>
                    </div>

                    <div class="p-4 flex-1 flex flex-col">
                        <h3 class="text-sm font-bold text-[var(--text-dark)] leading-snug mb-1 line-clamp-2 min-h-[2.5rem]" title="<?= $book['title'] ?>">
                            <?= $book['title'] ?>
                        </h3>

                        <p class="text-[11px] text-[var(--text-muted)] mb-2 truncate">
                            <?= $book_author ?>
                        </p>

                        <div class="flex items-center gap-1.5 mb-3">
                            <span class="material-symbols-outlined text-[14px] text-[var(--text-muted)]">storefront</span>
                            <p class="text-xs text-[var(--text-muted)] truncate font-medium"><?= $book['seller_name'] ?></p>
                        </div>

                        <div class="mt-auto pt-3 border-t border-dashed border-[var(--border-color)]">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <p class="text-[10px] text-[var(--text-muted)] uppercase font-bold">Harga</p>
                                    <span class="text-base font-bold text-[var(--chocolate-brown)]">
                                        Rp <?= number_format($book['sell_price'], 0, ',', '.') ?>
                                    </span>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] text-[var(--text-muted)]">Stok</p>
                                    <span class="text-xs font-bold text-[var(--deep-forest)]"><?= $book['stock'] ?></span>
                                </div>
                            </div>

                            <button onclick="addToCart(<?= $book['id'] ?>)" class="w-full py-2 rounded-xl bg-[var(--deep-forest)] text-white text-xs font-bold flex items-center justify-center gap-2 hover:bg-[var(--chocolate-brown)] transition-all shadow-md active:scale-95">
                                <span class="material-symbols-outlined text-sm">add_shopping_cart</span> Tambah
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full py-24 text-center bg-white rounded-[2.5rem] border-2 border-dashed border-[var(--border-color)]">
                    <span class="material-symbols-outlined text-6xl text-[var(--text-muted)] mb-4 opacity-50">search_off</span>
                    <h3 class="text-xl font-bold text-[var(--text-dark)]">Buku tidak ditemukan</h3>
                    <p class="text-[var(--text-muted)] text-sm mt-2 mb-6">Coba kata kunci lain atau reset filter.</p>
                    <a href="all_books.php" class="px-6 py-2.5 bg-[var(--light-sage)] text-[var(--deep-forest)] rounded-xl text-sm font-bold hover:bg-[var(--deep-forest)] hover:text-white transition-colors">Reset Filter</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if($total_pages > 1): ?>
        <div class="mt-12 flex justify-center items-center gap-4">
            <?php if($page > 1): ?>
                <a href="<?= getPaginationLink($page - 1) ?>" class="px-6 py-2.5 rounded-xl border border-[var(--deep-forest)] text-[var(--deep-forest)] font-bold text-sm hover:bg-[var(--deep-forest)] hover:text-white transition-all shadow-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">arrow_back</span> Sebelumnya
                </a>
            <?php else: ?>
                <span class="px-6 py-2.5 rounded-xl border border-gray-300 text-gray-400 font-bold text-sm cursor-not-allowed flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">arrow_back</span> Sebelumnya
                </span>
            <?php endif; ?>

            <span class="text-sm font-bold text-[var(--text-muted)]">
                Halaman <?= $page ?> / <?= $total_pages ?>
            </span>

            <?php if($page < $total_pages): ?>
                <a href="<?= getPaginationLink($page + 1) ?>" class="px-6 py-2.5 rounded-xl bg-[var(--deep-forest)] text-white font-bold text-sm hover:bg-[var(--chocolate-brown)] transition-all shadow-md flex items-center gap-2">
                    Selanjutnya <span class="material-symbols-outlined text-lg">arrow_forward</span>
                </a>
            <?php else: ?>
                <span class="px-6 py-2.5 rounded-xl bg-gray-200 text-gray-400 font-bold text-sm cursor-not-allowed flex items-center gap-2">
                    Selanjutnya <span class="material-symbols-outlined text-lg">arrow_forward</span>
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

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
            if(dropdown) dropdown.classList.toggle('hidden');
        }

        window.onclick = function(event) {
            if (!event.target.closest('button')) {
                const dropdowns = document.querySelectorAll('[id$="Dropdown"]');
                dropdowns.forEach(dd => dd.classList.add('hidden'));
            }
        }

        function addToCart(bookId) {
            const formData = new FormData();
            formData.append('book_id', bookId);

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