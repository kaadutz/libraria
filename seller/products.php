<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../config/db.php';

// Cek Keamanan
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller') {
    header("Location: ../auth/login.php");
    exit;
}

$seller_id = $_SESSION['user_id'];
$seller_name = $_SESSION['full_name'];

// --- 1. LOGIKA NOTIFIKASI ---
$query_orders = mysqli_query($conn, "SELECT COUNT(DISTINCT o.id) as total FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE oi.seller_id = '$seller_id' AND (o.status = 'pending' OR o.status = 'waiting_approval')");
$total_new_orders = mysqli_fetch_assoc($query_orders)['total'];

$query_unread = mysqli_query($conn, "SELECT COUNT(*) as total FROM messages WHERE receiver_id = '$seller_id' AND is_read = 0");
$total_unread_chat = mysqli_fetch_assoc($query_unread)['total'];

$total_notif = $total_new_orders + $total_unread_chat;

// Variabel Toast
$toast_message = "";
$toast_type = "";

// --- 2. LOGIKA CRUD PRODUK ---

// TAMBAH PRODUK
if (isset($_POST['add_product'])) {
    $title       = mysqli_real_escape_string($conn, $_POST['title']);
    $author      = mysqli_real_escape_string($conn, $_POST['author']);
    $category_id = mysqli_real_escape_string($conn, $_POST['category_id']);
    $cost_price  = str_replace('.', '', $_POST['cost_price']);
    $sell_price  = str_replace('.', '', $_POST['sell_price']);
    $stock       = mysqli_real_escape_string($conn, $_POST['stock']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    // VALIDASI DUPLIKAT
    $check_duplicate = mysqli_query($conn, "SELECT id FROM books WHERE title = '$title' AND seller_id = '$seller_id'");

    if (mysqli_num_rows($check_duplicate) > 0) {
        $toast_message = "Gagal: Judul buku sudah ada di toko Anda!";
        $toast_type = "error";
    } else {
        $image = NULL;
        if (!empty($_FILES['image']['name'])) {
            $target_dir = "../assets/uploads/books/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $new_name = "book_" . time() . "_" . uniqid() . "." . $ext;
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                move_uploaded_file($_FILES['image']['tmp_name'], $target_dir . $new_name);
                $image = $new_name;
            }
        }

        $query = "INSERT INTO books (seller_id, category_id, title, author, description, image, stock, cost_price, sell_price)
                  VALUES ('$seller_id', '$category_id', '$title', '$author', '$description', '$image', '$stock', '$cost_price', '$sell_price')";

        if (mysqli_query($conn, $query)) {
            header("Location: products.php?status=success_add");
            exit;
        } else {
            $toast_message = "Error Database: " . mysqli_error($conn);
            $toast_type = "error";
        }
    }
}

// EDIT PRODUK
if (isset($_POST['edit_product'])) {
    $id          = $_POST['id'];
    $title       = mysqli_real_escape_string($conn, $_POST['title']);
    $author      = mysqli_real_escape_string($conn, $_POST['author']);
    $category_id = mysqli_real_escape_string($conn, $_POST['category_id']);
    $cost_price  = str_replace('.', '', $_POST['cost_price']);
    $sell_price  = str_replace('.', '', $_POST['sell_price']);
    $stock       = mysqli_real_escape_string($conn, $_POST['stock']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    // VALIDASI DUPLIKAT SAAT EDIT
    $check_duplicate = mysqli_query($conn, "SELECT id FROM books WHERE title = '$title' AND seller_id = '$seller_id' AND id != '$id'");

    if (mysqli_num_rows($check_duplicate) > 0) {
        $toast_message = "Gagal: Judul buku sudah digunakan produk lain!";
        $toast_type = "error";
    } else {
        $img_sql = "";
        if (!empty($_FILES['image']['name'])) {
            $target_dir = "../assets/uploads/books/";
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $new_name = "book_" . time() . "_" . uniqid() . "." . $ext;

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                move_uploaded_file($_FILES['image']['tmp_name'], $target_dir . $new_name);
                $img_sql = ", image='$new_name'";
                $old_image = $_POST['old_image'];
                if ($old_image && file_exists($target_dir . $old_image)) {
                    unlink($target_dir . $old_image);
                }
            }
        }

        $query = "UPDATE books SET title='$title', author='$author', category_id='$category_id', cost_price='$cost_price',
                  sell_price='$sell_price', stock='$stock', description='$description' $img_sql
                  WHERE id='$id' AND seller_id='$seller_id'";

        if (mysqli_query($conn, $query)) {
            header("Location: products.php?status=success_edit");
            exit;
        }
    }
}

// HAPUS PRODUK (DENGAN CEK STOK)
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    // Cek Stok Dulu
    $check_stock = mysqli_query($conn, "SELECT stock, image FROM books WHERE id='$id' AND seller_id='$seller_id'");
    $data = mysqli_fetch_assoc($check_stock);

    if ($data['stock'] > 0) {
        // Gagal Hapus karena stok masih ada
        header("Location: products.php?status=error_stock_exists");
        exit;
    } else {
        // Proses Hapus
        if ($data['image'] && file_exists("../assets/uploads/books/" . $data['image'])) {
            unlink("../assets/uploads/books/" . $data['image']);
        }
        mysqli_query($conn, "DELETE FROM books WHERE id='$id' AND seller_id='$seller_id'");
        header("Location: products.php?status=success_delete");
        exit;
    }
}

// --- 3. DATA PRODUK & PAGINATION ---
$limit = 8;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

$count_query = mysqli_query($conn, "SELECT COUNT(id) as total FROM books WHERE seller_id = '$seller_id'");
$total_data = mysqli_fetch_assoc($count_query)['total'];
$total_pages = ceil($total_data / $limit);

$books = mysqli_query($conn, "
    SELECT b.*, c.name as category_name
    FROM books b
    JOIN categories c ON b.category_id = c.id
    WHERE b.seller_id = '$seller_id'
    ORDER BY b.stock ASC, b.created_at DESC
    LIMIT $start, $limit
"); // Added Order by Stock ASC biar yg kosong muncul diatas
$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
<title>Produk Saya - Libraria Seller</title>

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

<div id="toast-container" class="fixed top-24 right-6 z-[100] flex flex-col gap-3"></div>

<div class="flex min-h-screen">

    <aside id="sidebar" class="w-64 bg-cream dark:bg-stone-900 border-r border-tan/20 dark:border-stone-800 flex flex-col fixed h-full z-30 overflow-hidden shadow-lg lg:shadow-none transition-colors duration-300">

        <div id="sidebar-header" class="h-28 flex items-center border-b border-tan/20 dark:border-stone-800 shrink-0 px-6">
            <img id="sidebar-logo" src="../assets/images/logo.png" alt="Libraria Logo" class="h-12 w-auto object-contain flex-shrink-0">
            <div class="sidebar-text-wrapper flex flex-col justify-center ml-3">
                <h1 class="text-xl font-bold text-primary dark:text-sage tracking-tight font-logo leading-none">LIBRARIA</h1>
                <p class="text-[10px] font-bold tracking-[0.2em] text-tan mt-1 uppercase">Seller Panel</p>
            </div>
        </div>

        <nav class="flex-1 px-4 space-y-2 mt-6 overflow-y-auto overflow-x-hidden">
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">dashboard</span>
                <span class="menu-text whitespace-nowrap">Dashboard</span>
            </a>

            <a href="categories.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">category</span>
                <span class="menu-text whitespace-nowrap">Kategori</span>
            </a>

            <a href="products.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">inventory_2</span>
                <span class="menu-text whitespace-nowrap">Produk Saya</span>
            </a>

            <a href="orders.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">shopping_cart_checkout</span>
                <span class="menu-text whitespace-nowrap">Pesanan Masuk</span>
            </a>

            <a href="reports.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">bar_chart</span>
                <span class="menu-text whitespace-nowrap">Laporan</span>
            </a>

            <a href="chat.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">chat_bubble</span>
                <span class="menu-text whitespace-nowrap">Chat</span>
            </a>

            <a href="sellers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">storefront</span>
                <span class="menu-text whitespace-nowrap">Daftar Penjual</span>
            </a>

            <a href="help.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all group font-medium hover:bg-primary/10 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-400 hover:text-primary dark:hover:text-sage">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">help</span>
                <span class="menu-text whitespace-nowrap">Bantuan</span>
            </a>
        </nav>

        <div class="p-4 border-t border-tan/20 dark:border-stone-800">
            <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-3 text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-xl transition-colors group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">logout</span>
                <span class="font-medium menu-text whitespace-nowrap">Sign Out</span>
            </a>
        </div>
    </aside>

    <main id="main-content" class="flex-1 ml-64 p-4 lg:p-8 transition-all duration-300">

        <header class="flex justify-between items-center mb-8 bg-white/50 dark:bg-stone-900/50 backdrop-blur-sm p-4 rounded-3xl border border-tan/20 dark:border-stone-800 sticky top-4 z-20 shadow-sm" data-aos="fade-down">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl hover:bg-sage text-primary dark:text-sage transition-colors focus:outline-none">
                    <span class="material-symbols-outlined">menu_open</span>
                </button>
                <div><h2 class="text-xl lg:text-2xl title-font text-stone-800 dark:text-stone-200 hidden md:block">Manajemen Produk</h2></div>
            </div>

            <div class="flex items-center gap-4 relative">
                <button onclick="toggleModal('addProductModal')" class="hidden md:flex items-center gap-2 px-5 py-2.5 bg-chocolate text-white font-bold rounded-2xl hover:opacity-90 transition-all shadow-lg shadow-orange-900/20 text-sm">
                    <span class="material-symbols-outlined text-[18px]">add_circle</span> Tambah Produk
                </button>

                <button onclick="toggleDarkMode()" class="w-10 h-10 flex items-center justify-center rounded-full text-stone-500 dark:text-stone-400 hover:bg-primary hover:text-white dark:hover:bg-stone-800 transition-all duration-300">
                    <span class="material-icons-outlined text-xl">dark_mode</span>
                </button>

                <button onclick="toggleDropdown('notificationDropdown')" class="w-10 h-10 rounded-full bg-white dark:bg-stone-900 border border-tan/20 dark:border-stone-700 flex items-center justify-center text-stone-500 dark:text-stone-400 hover:text-primary dark:hover:text-sage hover:shadow-md transition-all relative">
                    <span class="material-symbols-outlined">notifications</span>
                    <?php if($total_notif > 0): ?>
                        <span class="absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full border-2 border-white dark:border-stone-900 animate-ping"></span>
                    <?php endif; ?>
                </button>
                <div id="notificationDropdown" class="absolute right-16 top-14 w-80 bg-white dark:bg-stone-900 rounded-2xl shadow-xl border border-tan/20 dark:border-stone-700 py-2 hidden transform origin-top-right transition-all z-50">
                    <div class="px-4 py-3 border-b border-tan/10 dark:border-stone-800 flex justify-between items-center">
                        <h4 class="font-bold text-primary dark:text-sage">Notifikasi</h4>
                        <?php if($total_notif > 0): ?>
                            <span class="text-[10px] bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 px-2 py-1 rounded-full font-bold"><?= $total_notif ?> Baru</span>
                        <?php endif; ?>
                    </div>
                    <div class="max-h-64 overflow-y-auto custom-scroll">
                        <?php if($total_new_orders > 0): ?>
                        <a href="orders.php" class="flex items-start gap-3 px-4 py-3 hover:bg-cream dark:hover:bg-stone-800 transition-colors border-b border-tan/10 dark:border-stone-800">
                            <div class="p-2 bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 rounded-full"><span class="material-symbols-outlined text-lg">shopping_bag</span></div>
                            <div>
                                <p class="text-sm font-bold text-stone-800 dark:text-stone-200">Pesanan Baru!</p>
                                <p class="text-xs text-stone-500 dark:text-stone-400">Ada <?= $total_new_orders ?> pesanan menunggu konfirmasi.</p>
                            </div>
                        </a>
                        <?php endif; ?>

                        <?php if($total_unread_chat > 0): ?>
                        <a href="chat.php" class="flex items-start gap-3 px-4 py-3 hover:bg-cream dark:hover:bg-stone-800 transition-colors border-b border-tan/10 dark:border-stone-800">
                            <div class="p-2 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-full"><span class="material-symbols-outlined text-lg">chat</span></div>
                            <div>
                                <p class="text-sm font-bold text-stone-800 dark:text-stone-200">Pesan Masuk</p>
                                <p class="text-xs text-stone-500 dark:text-stone-400">Ada <?= $total_unread_chat ?> pesan belum dibaca.</p>
                            </div>
                        </a>
                        <?php endif; ?>

                        <?php if($total_notif == 0): ?>
                            <div class="text-center py-6 text-stone-400 text-xs italic">Tidak ada notifikasi baru.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <button onclick="toggleDropdown('profileDropdown')" class="flex items-center gap-3 bg-white dark:bg-stone-900 p-1.5 pr-4 rounded-full border border-tan/20 dark:border-stone-700 card-shadow hover:shadow-md transition-all focus:outline-none">
                    <div class="w-9 h-9 rounded-full bg-tan text-white flex items-center justify-center font-bold text-sm border-2 border-cream dark:border-stone-600"><?= strtoupper(substr($seller_name, 0, 1)) ?></div>
                    <div class="text-left hidden sm:block">
                        <p class="text-xs font-bold leading-none title-font text-stone-800 dark:text-stone-200"><?= $seller_name ?></p>
                        <p class="text-[10px] text-stone-500 dark:text-stone-400 leading-none mt-1 font-bold uppercase">Seller</p>
                    </div>
                    <span class="material-symbols-outlined text-[18px] text-stone-500 dark:text-stone-400">expand_more</span>
                </button>

                <div id="profileDropdown" class="absolute right-0 top-14 w-56 bg-white dark:bg-stone-900 rounded-2xl shadow-xl border border-tan/20 dark:border-stone-700 py-2 hidden transform origin-top-right transition-all z-50">
                    <a href="profile.php" class="flex items-center gap-2 px-4 py-3 text-sm text-stone-700 dark:text-stone-300 hover:bg-sage/30 hover:text-primary transition-colors"><span class="material-symbols-outlined text-[20px]">store</span> Profil Toko</a>
                    <div class="border-t border-tan/10 dark:border-stone-800 my-1"></div>
                    <a href="../auth/logout.php" class="flex items-center gap-2 px-4 py-3 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"><span class="material-symbols-outlined text-[20px]">logout</span> Log Out</a>
                </div>
            </div>
        </header>

        <button onclick="toggleModal('addProductModal')" class="md:hidden fixed bottom-6 right-6 w-14 h-14 bg-chocolate text-white rounded-full shadow-2xl flex items-center justify-center z-40 hover:scale-110 transition-transform">
            <span class="material-symbols-outlined text-2xl">add</span>
        </button>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" data-aos="fade-up">
            <?php if(mysqli_num_rows($books) > 0): ?>
                <?php while($book = mysqli_fetch_assoc($books)):
                    $img_src = !empty($book['image']) ? "../assets/uploads/books/".$book['image'] : "../assets/images/book_placeholder.png";
                    $author = !empty($book['author']) ? $book['author'] : 'Penulis Tidak Diketahui';

                    // --- LOGIKA TAMPILAN STOK HABIS ---
                    $is_out_of_stock = ($book['stock'] <= 0);
                    $card_style = $is_out_of_stock ? "border-red-200 opacity-90" : "border-tan/20 dark:border-stone-800";
                    $bg_badge = $is_out_of_stock ? "bg-red-500 text-white" : "bg-white/90 dark:bg-stone-900/90 backdrop-blur text-primary dark:text-sage";
                ?>
                <div class="bg-white dark:bg-stone-900 rounded-[2rem] border <?= $card_style ?> card-shadow group relative flex flex-col h-full hover:shadow-lg transition-all hover:-translate-y-1 overflow-hidden fix-mask">

                    <div class="relative aspect-[3/4] bg-cream dark:bg-stone-800 overflow-hidden">

                        <span class="absolute top-4 left-4 z-10 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest shadow-sm border border-transparent <?= $bg_badge ?>">
                            <?= $is_out_of_stock ? "STOK HABIS" : $book['category_name'] ?>
                        </span>

                        <img src="<?= $img_src ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110 <?= $is_out_of_stock ? 'grayscale-[0.5]' : '' ?>">

                        <div class="absolute bottom-0 left-0 w-full bg-gradient-to-t from-black/60 to-transparent p-4 pt-12">
                            <p class="<?= $is_out_of_stock ? 'text-red-300' : 'text-white' ?> text-xs font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">inventory_2</span> Stok: <?= $book['stock'] ?>
                            </p>
                        </div>
                    </div>

                    <div class="p-5 flex-1 flex flex-col">
                        <h3 class="text-lg font-bold text-stone-800 dark:text-stone-200 leading-tight mb-1 line-clamp-2 min-h-[3rem]" title="<?= $book['title'] ?>"><?= $book['title'] ?></h3>

                        <p class="text-xs text-stone-500 dark:text-stone-400 mb-3 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">person</span> <?= $author ?>
                        </p>

                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <p class="text-[10px] text-stone-500 dark:text-stone-400 font-bold uppercase">Harga Jual</p>
                                <p class="text-lg font-bold text-chocolate dark:text-tan">Rp <?= number_format($book['sell_price'], 0, ',', '.') ?></p>
                            </div>
                        </div>

                        <div class="mt-auto pt-4 border-t border-dashed border-tan/20 dark:border-stone-800 flex items-center gap-2">

                            <a href="product_detail.php?id=<?= $book['id'] ?>" class="flex-1 py-2 bg-sage/20 text-primary dark:text-sage rounded-xl font-bold text-xs flex items-center justify-center gap-1 hover:bg-primary hover:text-white transition-all">
                                <span class="material-symbols-outlined text-base">visibility</span>
                            </a>

                            <button onclick="openEditModal(<?= htmlspecialchars(json_encode($book)) ?>)"
                                    class="w-full flex-1 py-2 rounded-xl font-bold text-xs flex items-center justify-center gap-1 transition-all <?= $is_out_of_stock ? 'bg-green-100 text-green-700 hover:bg-green-600 hover:text-white ring-2 ring-green-200' : 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 hover:bg-blue-600 hover:text-white' ?>"
                                    title="<?= $is_out_of_stock ? 'Isi Stok Sekarang' : 'Edit Produk' ?>">
                                <span class="material-symbols-outlined text-base"><?= $is_out_of_stock ? 'add_box' : 'edit' ?></span>
                                <?= $is_out_of_stock ? 'Isi Stok' : 'Edit' ?>
                            </button>

                            <?php if ($is_out_of_stock): ?>
                                <a href="?delete=<?= $book['id'] ?>" onclick="return confirm('Stok habis. Hapus produk ini permanen?')" class="w-10 h-10 rounded-xl bg-red-100 text-red-600 flex items-center justify-center hover:bg-red-600 hover:text-white transition-all shadow-sm" title="Hapus">
                                    <span class="material-symbols-outlined text-lg">delete</span>
                                </a>
                            <?php else: ?>
                                <button onclick="showToast('Habiskan stok dulu sebelum menghapus!', 'error')" class="w-10 h-10 rounded-xl bg-gray-100 dark:bg-stone-800 text-gray-400 dark:text-stone-600 flex items-center justify-center cursor-not-allowed" title="Habiskan Stok Dulu">
                                    <span class="material-symbols-outlined text-lg">delete_forever</span>
                                </button>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full py-20 text-center bg-white dark:bg-stone-900 rounded-[2rem] border-2 border-dashed border-tan/20 dark:border-stone-800">
                    <span class="material-symbols-outlined text-6xl text-stone-400 mb-4">menu_book</span>
                    <h3 class="text-xl font-bold text-stone-800 dark:text-stone-200">Belum ada produk</h3>
                    <p class="text-stone-500 dark:text-stone-400 mb-6">Mulai tambahkan koleksi buku Anda sekarang.</p>
                    <button onclick="toggleModal('addProductModal')" class="px-6 py-3 bg-primary text-white font-bold rounded-xl hover:opacity-90 transition-all shadow-lg">
                        Tambah Produk Pertama
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-16 flex justify-center items-center gap-4">
            <?php if($page > 1): ?><a href="?page=<?= $page - 1 ?>" class="px-5 py-2.5 bg-white dark:bg-stone-900 border border-tan/20 dark:border-stone-800 rounded-2xl hover:bg-cream dark:hover:bg-stone-800 transition-colors text-sm font-bold text-primary dark:text-sage shadow-sm">Previous</a><?php endif; ?>
            <div class="px-4 py-2 rounded-xl bg-white dark:bg-stone-900 border border-tan/20 dark:border-stone-800 text-sm font-bold text-stone-500 dark:text-stone-400">Page <?= $page ?> of <?= $total_pages ?></div>
            <?php if($page < $total_pages): ?><a href="?page=<?= $page + 1 ?>" class="px-5 py-2.5 bg-white dark:bg-stone-900 border border-tan/20 dark:border-stone-800 rounded-2xl hover:bg-cream dark:hover:bg-stone-800 transition-colors text-sm font-bold text-primary dark:text-sage shadow-sm">Next</a><?php endif; ?>
        </div>

    </main>
</div>

<div id="addProductModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
    <div class="modal-overlay absolute w-full h-full bg-black/50 backdrop-blur-sm" onclick="toggleModal('addProductModal')"></div>
    <div class="modal-container bg-white dark:bg-stone-900 w-11/12 md:max-w-2xl mx-auto rounded-[2rem] shadow-2xl z-50 overflow-y-auto max-h-[90vh] p-8 transform scale-95 transition-all border border-tan/20 dark:border-stone-700">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-primary dark:text-sage title-font">Tambah Produk</h3>
            <button onclick="toggleModal('addProductModal')" class="text-stone-400 hover:text-red-500"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1">Judul Buku</label><input type="text" name="title" required class="w-full px-4 py-2.5 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 text-stone-800 dark:text-stone-200"></div>
                <div><label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1">Penulis</label><input type="text" name="author" required class="w-full px-4 py-2.5 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 text-stone-800 dark:text-stone-200"></div>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1">Kategori</label>
                <select name="category_id" required class="w-full px-4 py-2.5 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 text-stone-800 dark:text-stone-200">
                    <?php mysqli_data_seek($categories, 0); while($cat = mysqli_fetch_assoc($categories)): ?>
                        <option value="<?= $cat['id'] ?>"><?= $cat['name'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div><label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1">Harga Modal (Rp)</label><input type="text" name="cost_price" required onkeyup="formatRupiah(this)" class="w-full px-4 py-2.5 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 text-stone-800 dark:text-stone-200"></div>
                <div><label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1">Harga Jual (Rp)</label><input type="text" name="sell_price" required onkeyup="formatRupiah(this)" class="w-full px-4 py-2.5 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 text-stone-800 dark:text-stone-200"></div>
                <div><label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1">Stok</label><input type="number" name="stock" required class="w-full px-4 py-2.5 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 text-stone-800 dark:text-stone-200"></div>
            </div>
            <div><label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1">Deskripsi</label><textarea name="description" rows="3" class="w-full px-4 py-2.5 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 text-stone-800 dark:text-stone-200"></textarea></div>
            <div><label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1">Upload Cover</label><input type="file" name="image" accept="image/*" class="w-full text-sm text-stone-500 dark:text-stone-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-white hover:file:bg-chocolate"></div>
            <button type="submit" name="add_product" class="w-full py-3.5 mt-2 bg-primary text-white font-bold rounded-xl hover:opacity-90 transition-all shadow-lg">Simpan Produk</button>
        </form>
    </div>
</div>

<div id="editProductModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
    <div class="modal-overlay absolute w-full h-full bg-black/50 backdrop-blur-sm" onclick="toggleModal('editProductModal')"></div>
    <div class="modal-container bg-white dark:bg-stone-900 w-11/12 md:max-w-2xl mx-auto rounded-[2rem] shadow-2xl z-50 overflow-y-auto max-h-[90vh] p-8 transform scale-95 transition-all border border-tan/20 dark:border-stone-700">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-primary dark:text-sage title-font">Edit Produk</h3>
            <button onclick="toggleModal('editProductModal')" class="text-stone-400 hover:text-red-500"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="old_image" id="edit_old_image">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1">Judul Buku</label><input type="text" name="title" id="edit_title" required class="w-full px-4 py-2.5 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 text-stone-800 dark:text-stone-200"></div>
                <div><label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1">Penulis</label><input type="text" name="author" id="edit_author" required class="w-full px-4 py-2.5 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 text-stone-800 dark:text-stone-200"></div>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1">Kategori</label>
                <select name="category_id" id="edit_category" required class="w-full px-4 py-2.5 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 text-stone-800 dark:text-stone-200">
                    <?php mysqli_data_seek($categories, 0); while($cat = mysqli_fetch_assoc($categories)): ?>
                        <option value="<?= $cat['id'] ?>"><?= $cat['name'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div><label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1">Harga Modal</label><input type="text" name="cost_price" id="edit_cost" required onkeyup="formatRupiah(this)" class="w-full px-4 py-2.5 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 text-stone-800 dark:text-stone-200"></div>
                <div><label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1">Harga Jual</label><input type="text" name="sell_price" id="edit_sell" required onkeyup="formatRupiah(this)" class="w-full px-4 py-2.5 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 text-stone-800 dark:text-stone-200"></div>
                <div><label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1">Stok</label><input type="number" name="stock" id="edit_stock" required class="w-full px-4 py-2.5 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 text-stone-800 dark:text-stone-200"></div>
            </div>
            <div><label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1">Deskripsi</label><textarea name="description" id="edit_desc" rows="3" class="w-full px-4 py-2.5 rounded-xl bg-cream dark:bg-stone-800 border-transparent focus:bg-white dark:focus:bg-stone-900 focus:border-tan dark:focus:border-stone-600 focus:ring-0 text-stone-800 dark:text-stone-200"></textarea></div>
            <div><label class="block text-xs font-bold uppercase text-stone-500 dark:text-stone-400 mb-1">Ganti Cover</label><input type="file" name="image" class="w-full text-sm text-stone-500 dark:text-stone-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-white hover:file:bg-chocolate"></div>
            <button type="submit" name="edit_product" class="w-full py-3.5 mt-2 bg-tan text-white font-bold rounded-xl hover:opacity-90 transition-all shadow-lg">Update Produk</button>
        </form>
    </div>
</div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({ once: true, duration: 800, offset: 50 });

    let isSidebarOpen = true;
    const sidebar = document.getElementById('sidebar');
    const mainDiv = document.getElementById('main-content');

    function toggleSidebar() {
        if (isSidebarOpen) {
            sidebar.classList.remove('w-64');
            sidebar.classList.add('w-20', 'sidebar-collapsed');
            mainDiv.classList.remove('ml-64');
            mainDiv.classList.add('ml-20');
        } else {
            sidebar.classList.remove('w-20', 'sidebar-collapsed');
            sidebar.classList.add('w-64');
            mainDiv.classList.remove('ml-20');
            mainDiv.classList.add('ml-64');
        }
        isSidebarOpen = !isSidebarOpen;
    }

    function toggleDropdown(id) {
        const element = document.getElementById(id);
        const allDropdowns = document.querySelectorAll('[id$="Dropdown"]');
        allDropdowns.forEach(dd => { if(dd.id !== id) dd.classList.add('hidden'); });
        if (element) element.classList.toggle('hidden');
    }

    window.onclick = function(event) {
        if (!event.target.closest('button')) {
            const dropdowns = document.querySelectorAll('[id$="Dropdown"]');
            dropdowns.forEach(dd => dd.classList.add('hidden'));
        }
    }

    // --- TOAST FUNCTION ---
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        const bgColor = type === 'success' ? 'bg-primary' : 'bg-red-600';
        const icon = type === 'success' ? 'check_circle' : 'error';

        toast.className = `flex items-center gap-3 px-6 py-4 rounded-2xl shadow-2xl text-white ${bgColor} toast-enter cursor-pointer backdrop-blur-md bg-opacity-95 transform transition-all duration-300 hover:scale-105`;
        toast.innerHTML = `<span class="material-symbols-outlined text-2xl">${icon}</span><p class="text-sm font-bold">${message}</p>`;

        toast.onclick = () => { toast.classList.add('toast-exit'); setTimeout(() => toast.remove(), 400); };
        container.appendChild(toast);
        setTimeout(() => { if (toast.isConnected) { toast.classList.add('toast-exit'); setTimeout(() => toast.remove(), 400); } }, 4000);
    }

    // Check for PHP Status Messages (URL Params)
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    if (status === 'success_add') showToast('Produk berhasil ditambahkan!', 'success');
    if (status === 'success_edit') showToast('Produk berhasil diperbarui!', 'success');
    if (status === 'success_delete') showToast('Produk berhasil dihapus!', 'success');
    if (status === 'error_stock_exists') showToast('Gagal hapus: Habiskan stok terlebih dahulu!', 'error');

    // Check for PHP Error Variables (Injected via PHP)
    <?php if(!empty($toast_message)): ?>
        showToast("<?= $toast_message ?>", "<?= $toast_type ?>");
    <?php endif; ?>


    function toggleModal(modalID) {
        const modal = document.getElementById(modalID);
        const container = modal.querySelector('.modal-container');
        modal.classList.toggle('opacity-0');
        modal.classList.toggle('pointer-events-none');
        document.body.classList.toggle('modal-active');
        if (!modal.classList.contains('opacity-0')) {
            setTimeout(() => { container.classList.remove('scale-95'); container.classList.add('scale-100'); }, 10);
        } else {
            container.classList.remove('scale-100'); container.classList.add('scale-95');
        }
    }

    function openEditModal(book) {
        document.getElementById('edit_id').value = book.id;
        document.getElementById('edit_title').value = book.title;
        document.getElementById('edit_author').value = book.author || '';
        document.getElementById('edit_category').value = book.category_id;
        document.getElementById('edit_cost').value = formatRupiahString(book.cost_price);
        document.getElementById('edit_sell').value = formatRupiahString(book.sell_price);
        document.getElementById('edit_stock').value = book.stock;
        document.getElementById('edit_desc').value = book.description;
        document.getElementById('edit_old_image').value = book.image;
        toggleModal('editProductModal');
    }

    function formatRupiah(input) {
        let value = input.value.replace(/[^,\d]/g, '').toString();
        let split = value.split(',');
        let sisa = split[0].length % 3;
        let rupiah = split[0].substr(0, sisa);
        let ribuan = split[0].substr(sisa).match(/\d{3}/gi);
        if (ribuan) { let separator = sisa ? '.' : ''; rupiah += separator + ribuan.join('.'); }
        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        input.value = rupiah;
    }

    function formatRupiahString(angka) { return parseInt(angka).toString().replace(/\B(?=(\d{3})+(?!\d))/g, "."); }
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
