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

// --- 3. NOTIFIKASI PINTAR (GABUNGAN CHAT & STATUS PESANAN) ---
$notif_list = [];

// A. Ambil Pesan Belum Dibaca (Akan terus muncul sampai dibaca)
$q_msg_notif = mysqli_query($conn, "SELECT m.*, u.full_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.receiver_id = '$buyer_id' AND m.is_read = 0 ORDER BY m.created_at DESC");
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

// B. Ambil 5 Status Pesanan Terakhir (Akan selalu muncul di list teratas)
$q_order_notif = mysqli_query($conn, "
    SELECT invoice_number, status, order_date
    FROM orders
    WHERE buyer_id = '$buyer_id'
    AND status IN ('approved', 'shipping', 'rejected', 'refunded', 'finished')
    ORDER BY order_date DESC LIMIT 5
");

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

// Sort notifikasi berdasarkan waktu terbaru (Chat baru vs Status baru)
usort($notif_list, function($a, $b) {
    return $b['time'] - $a['time'];
});

$total_notif = count($notif_list);

// HITUNG ISI KERANJANG (If not already calculated)
if(!isset($cart_count)) {
    $query_cart = mysqli_query($conn, "SELECT SUM(qty) as total FROM carts WHERE buyer_id = '$buyer_id'");
    $cart_data = mysqli_fetch_assoc($query_cart);
    $cart_count = $cart_data['total'] ?? 0;
}


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
<title>Katalog Buku - Libraria</title>

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
        <div class="bg-white/90 dark:bg-stone-900/90 backdrop-blur-md rounded-3xl border border-tan/20 dark:border-stone-800 shadow-sm max-w-7xl mx-auto px-4 py-3 transition-colors duration-300">
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
                               class="w-full pl-10 pr-4 py-2 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-700 focus:ring-0 transition-all text-sm shadow-inner group-hover:bg-white dark:group-hover:bg-stone-800 text-stone-800 dark:text-stone-200 placeholder-stone-500">
                        <span class="material-symbols-outlined absolute left-3 top-2 text-stone-500 group-focus-within:text-tan text-lg">search</span>
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


    <main class="flex-1 pt-32 pb-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full">

        <div class="mb-8" data-aos="fade-down">
            <h1 class="text-3xl font-bold text-primary dark:text-sage title-font mb-2">Katalog Buku</h1>
            <p class="text-stone-500 dark:text-stone-400 text-sm">Temukan <?= $total_records ?> buku menarik untuk dibaca.</p>
        </div>

        <form id="filterForm" action="" method="GET" class="mb-10 bg-white dark:bg-stone-900 p-5 rounded-[2rem] border border-tan/20 dark:border-stone-800 shadow-sm flex flex-col md:flex-row gap-4 md:items-end" data-aos="fade-up">

            <?php if(isset($_GET['s'])): ?>
                <input type="hidden" name="s" value="<?= htmlspecialchars($_GET['s']) ?>">
            <?php endif; ?>

            <div class="flex-1">
                <label class="block text-xs font-bold text-stone-500 dark:text-stone-400 uppercase mb-2 ml-1">Kategori Buku</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-2.5 text-tan">category</span>
                    <select name="cat" onchange="this.form.submit()" class="w-full pl-10 pr-8 py-2.5 rounded-xl border border-tan/20 dark:border-stone-700 bg-cream dark:bg-stone-800 text-stone-800 dark:text-stone-200 text-sm font-bold focus:border-tan focus:ring-0 cursor-pointer shadow-inner">
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
                <label class="block text-xs font-bold text-stone-500 dark:text-stone-400 uppercase mb-2 ml-1">Urutkan</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-2.5 text-tan">sort</span>
                    <select name="sort" onchange="this.form.submit()" class="w-full pl-10 pr-8 py-2.5 rounded-xl border border-tan/20 dark:border-stone-700 bg-cream dark:bg-stone-800 text-stone-800 dark:text-stone-200 text-sm font-bold focus:border-tan focus:ring-0 cursor-pointer shadow-inner">
                        <option value="newest" <?= (!isset($_GET['sort']) || $_GET['sort'] == 'newest') ? 'selected' : '' ?>>Terbaru Ditambahkan</option>
                        <option value="price_low" <?= (isset($_GET['sort']) && $_GET['sort'] == 'price_low') ? 'selected' : '' ?>>Harga: Rendah ke Tinggi</option>
                        <option value="price_high" <?= (isset($_GET['sort']) && $_GET['sort'] == 'price_high') ? 'selected' : '' ?>>Harga: Tinggi ke Rendah</option>
                        <option value="oldest" <?= (isset($_GET['sort']) && $_GET['sort'] == 'oldest') ? 'selected' : '' ?>>Terlama Ditambahkan</option>
                    </select>
                </div>
            </div>

            <?php if(isset($_GET['cat']) || isset($_GET['s']) || (isset($_GET['sort']) && $_GET['sort'] != 'newest')): ?>
            <div class="md:pb-0.5">
                <a href="all_books.php" class="h-[42px] px-4 rounded-xl border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-900/20 text-red-500 dark:text-red-400 font-bold text-sm flex items-center justify-center gap-1 hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors" title="Hapus Filter">
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
                <div class="bg-white dark:bg-stone-900 rounded-[2rem] border border-tan/20 dark:border-stone-800 card-shadow overflow-hidden group relative flex flex-col h-full hover:shadow-xl transition-all duration-300 hover:-translate-y-1">

                    <div class="aspect-[2/3] bg-cream dark:bg-stone-800 relative overflow-hidden">
                        <span class="absolute top-3 left-3 z-10 px-2 py-1 bg-white/90 dark:bg-stone-900/90 backdrop-blur text-primary dark:text-sage text-[10px] font-bold uppercase rounded-lg shadow-sm border border-stone-100 dark:border-stone-700"><?= $book['category_name'] ?></span>

                        <img src="<?= $img_src ?>" alt="<?= $book['title'] ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">

                        <div class="absolute inset-x-0 bottom-0 p-4 bg-gradient-to-t from-black/80 via-black/40 to-transparent translate-y-full group-hover:translate-y-0 transition-transform duration-300 flex flex-col justify-end h-full">
                            <a href="detail_book.php?id=<?= $book['id'] ?>" class="w-full py-2.5 bg-white text-primary font-bold text-xs rounded-xl text-center hover:bg-tan hover:text-white transition-colors shadow-lg">Lihat Detail</a>
                        </div>
                    </div>

                    <div class="p-4 flex-1 flex flex-col">
                        <h3 class="text-sm font-bold text-stone-800 dark:text-stone-200 leading-snug mb-1 line-clamp-2 min-h-[2.5rem]" title="<?= $book['title'] ?>">
                            <?= $book['title'] ?>
                        </h3>

                        <p class="text-[11px] text-stone-500 dark:text-stone-400 mb-2 truncate">
                            <?= $book_author ?>
                        </p>

                        <div class="flex items-center gap-1.5 mb-3">
                            <span class="material-symbols-outlined text-[14px] text-stone-500 dark:text-stone-400">storefront</span>
                            <p class="text-xs text-stone-500 dark:text-stone-400 truncate font-medium"><?= $book['seller_name'] ?></p>
                        </div>

                        <div class="mt-auto pt-3 border-t border-dashed border-tan/20 dark:border-stone-800">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <p class="text-[10px] text-stone-500 dark:text-stone-400 uppercase font-bold">Harga</p>
                                    <span class="text-base font-bold text-chocolate dark:text-tan">
                                        Rp <?= number_format($book['sell_price'], 0, ',', '.') ?>
                                    </span>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] text-stone-500 dark:text-stone-400">Stok</p>
                                    <span class="text-xs font-bold text-primary dark:text-sage"><?= $book['stock'] ?></span>
                                </div>
                            </div>

                            <button onclick="addToCart(<?= $book['id'] ?>)" class="w-full py-2 rounded-xl bg-primary text-white text-xs font-bold flex items-center justify-center gap-2 hover:bg-chocolate transition-all shadow-md active:scale-95">
                                <span class="material-symbols-outlined text-sm">add_shopping_cart</span> Tambah
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full py-24 text-center bg-white dark:bg-stone-900 rounded-[2.5rem] border-2 border-dashed border-tan/20 dark:border-stone-800">
                    <span class="material-symbols-outlined text-6xl text-stone-400 mb-4 opacity-50">search_off</span>
                    <h3 class="text-xl font-bold text-stone-800 dark:text-stone-200">Buku tidak ditemukan</h3>
                    <p class="text-stone-500 dark:text-stone-400 text-sm mt-2 mb-6">Coba kata kunci lain atau reset filter.</p>
                    <a href="all_books.php" class="px-6 py-2.5 bg-sage text-primary rounded-xl text-sm font-bold hover:bg-primary hover:text-white transition-colors">Reset Filter</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if($total_pages > 1): ?>
        <div class="mt-12 flex justify-center items-center gap-4">
            <?php if($page > 1): ?>
                <a href="<?= getPaginationLink($page - 1) ?>" class="px-6 py-2.5 rounded-xl border border-primary dark:border-sage text-primary dark:text-sage font-bold text-sm hover:bg-primary hover:text-white transition-all shadow-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">arrow_back</span> Sebelumnya
                </a>
            <?php else: ?>
                <span class="px-6 py-2.5 rounded-xl border border-gray-300 dark:border-stone-700 text-gray-400 dark:text-stone-600 font-bold text-sm cursor-not-allowed flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">arrow_back</span> Sebelumnya
                </span>
            <?php endif; ?>

            <span class="text-sm font-bold text-stone-500 dark:text-stone-400">
                Halaman <?= $page ?> / <?= $total_pages ?>
            </span>

            <?php if($page < $total_pages): ?>
                <a href="<?= getPaginationLink($page + 1) ?>" class="px-6 py-2.5 rounded-xl bg-primary text-white font-bold text-sm hover:bg-chocolate transition-all shadow-md flex items-center gap-2">
                    Selanjutnya <span class="material-symbols-outlined text-lg">arrow_forward</span>
                </a>
            <?php else: ?>
                <span class="px-6 py-2.5 rounded-xl bg-gray-200 dark:bg-stone-800 text-gray-400 dark:text-stone-600 font-bold text-sm cursor-not-allowed flex items-center gap-2">
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