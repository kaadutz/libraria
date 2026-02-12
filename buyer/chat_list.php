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



$buyer_name = $_SESSION['full_name'];

// --- LOGIKA KIRIM PESAN ---
if (isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id'];
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    if (!empty($message)) {
        $q_send = "INSERT INTO messages (sender_id, receiver_id, message, is_read, created_at) VALUES ('$buyer_id', '$receiver_id', '$message', 0, NOW())";
        mysqli_query($conn, $q_send);
        // Redirect agar tidak resubmit, tetap di chat yang sama
        header("Location: chat_list.php?uid=" . $receiver_id);
        exit;
    }
}

// --- LOGIKA AMBIL DAFTAR CHAT (SIDEBAR) ---
$q_chats = "
    SELECT DISTINCT u.id, u.full_name, u.profile_image,
    (SELECT message FROM messages WHERE (sender_id = u.id AND receiver_id = '$buyer_id') OR (sender_id = '$buyer_id' AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_msg,
    (SELECT created_at FROM messages WHERE (sender_id = u.id AND receiver_id = '$buyer_id') OR (sender_id = '$buyer_id' AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_time,
    (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = '$buyer_id' AND is_read = 0) as unread
    FROM users u
    JOIN messages m ON u.id = m.sender_id OR u.id = m.receiver_id
    WHERE (m.sender_id = '$buyer_id' OR m.receiver_id = '$buyer_id') AND u.id != '$buyer_id'
    ORDER BY last_time DESC
";
$chat_list = mysqli_query($conn, $q_chats);

// --- LOGIKA BUKA CHAT SPESIFIK ---
$active_chat = null;
if (isset($_GET['uid'])) {
    $chat_uid = mysqli_real_escape_string($conn, $_GET['uid']);

    // Ambil Data Seller
    $q_seller = mysqli_query($conn, "SELECT * FROM users WHERE id = '$chat_uid'");
    if(mysqli_num_rows($q_seller) > 0) {
        $active_chat = mysqli_fetch_assoc($q_seller);

        // Tandai pesan sudah dibaca
        mysqli_query($conn, "UPDATE messages SET is_read = 1 WHERE sender_id = '$chat_uid' AND receiver_id = '$buyer_id'");

        // Ambil isi chat
        $q_msgs = mysqli_query($conn, "SELECT * FROM messages WHERE (sender_id = '$buyer_id' AND receiver_id = '$chat_uid') OR (sender_id = '$chat_uid' AND receiver_id = '$buyer_id') ORDER BY created_at ASC");
    }
}

// --- LOGIKA PREVIEW BUKU (BARU - DARI URL) ---
$context_book = null;
if (isset($_GET['book_id'])) {
    $bid = mysqli_real_escape_string($conn, $_GET['book_id']);
    $q_book = mysqli_query($conn, "SELECT title, image, sell_price FROM books WHERE id = '$bid'");
    if (mysqli_num_rows($q_book) > 0) {
        $context_book = mysqli_fetch_assoc($q_book);
    }
}

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
<title>Chat - Libraria</title>

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



    <main class="flex-1 pt-32 pb-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full h-[calc(100vh-2rem)]">

        <div class="bg-white dark:bg-stone-900 rounded-[2.5rem] border border-tan/20 dark:border-stone-800 shadow-xl overflow-hidden h-full flex flex-col md:flex-row">

            <div class="w-full md:w-80 border-r border-tan/20 dark:border-stone-800 bg-gray-50 dark:bg-stone-800/50 flex flex-col h-full <?= $active_chat ? 'hidden md:flex' : 'flex' ?>">
                <div class="p-6 border-b border-tan/20 dark:border-stone-800 bg-white dark:bg-stone-900">
                    <h2 class="text-xl font-bold text-primary dark:text-sage">Pesan</h2>
                </div>

                <div class="flex-1 overflow-y-auto p-3 space-y-2">
                    <?php if(mysqli_num_rows($chat_list) > 0): ?>
                        <?php while($chat = mysqli_fetch_assoc($chat_list)):
                            $c_img = !empty($chat['profile_image']) ? "../assets/uploads/profiles/".$chat['profile_image'] : "../assets/images/default_profile.png";
                            $active = ($active_chat && $active_chat['id'] == $chat['id']) ? 'bg-sage/30 border-[var(--light-sage)]' : 'bg-white dark:bg-stone-900 border-transparent hover:bg-gray-50 dark:bg-stone-800';
                        ?>
                        <a href="chat_list.php?uid=<?= $chat['id'] ?>" class="flex items-center gap-3 p-3 rounded-2xl border transition-all <?= $active ?>">
                            <div class="relative">
                                <img src="<?= $c_img ?>" class="w-12 h-12 rounded-full object-cover">
                                <?php if($chat['unread'] > 0): ?>
                                <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold flex items-center justify-center rounded-full border-2 border-white"><?= $chat['unread'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-baseline mb-1">
                                    <h4 class="font-bold text-sm text-stone-800 dark:text-stone-200 truncate"><?= $chat['full_name'] ?></h4>
                                    <span class="text-[10px] text-gray-400"><?= date('H:i', strtotime($chat['last_time'])) ?></span>
                                </div>
                                <p class="text-xs text-gray-500 truncate"><?= $chat['last_msg'] ?></p>
                            </div>
                        </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-10 text-gray-400">
                            <span class="material-symbols-outlined text-4xl mb-2">chat_bubble_outline</span>
                            <p class="text-sm">Belum ada pesan.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex-1 flex flex-col bg-[url('../assets/images/chat-bg.png')] bg-repeat h-full <?= $active_chat ? 'flex' : 'hidden md:flex' ?>">

                <?php if($active_chat):
                    $a_img = !empty($active_chat['profile_image']) ? "../assets/uploads/profiles/".$active_chat['profile_image'] : "../assets/images/default_profile.png";
                ?>
                    <div class="p-4 border-b border-tan/20 dark:border-stone-800 bg-white dark:bg-stone-900 flex items-center gap-4 shadow-sm z-10">
                        <a href="chat_list.php" class="md:hidden text-gray-500 hover:text-primary dark:text-sage">
                            <span class="material-symbols-outlined">arrow_back</span>
                        </a>
                        <img src="<?= $a_img ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200">
                        <div>
                            <h3 class="font-bold text-stone-800 dark:text-stone-200"><?= $active_chat['full_name'] ?></h3>
                            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Penjual</p>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto p-6 space-y-4 chat-area bg-white dark:bg-stone-900/50 backdrop-blur-sm" id="chatContainer">
                        <?php
                        while($msg = mysqli_fetch_assoc($q_msgs)):
                            $is_me = ($msg['sender_id'] == $buyer_id);

                            // --- SMART PRODUCT DETECTION (Agar tampil di history chat) ---
                            $product_card = "";
                            if (preg_match('/Halo kak, saya tertarik dengan buku \*(.*?)\*/', $msg['message'], $matches)) {
                                $book_title = mysqli_real_escape_string($conn, $matches[1]);
                                // Cari buku di toko penjual yang judulnya mirip
                                $seller_check_id = $active_chat['id'];
                                $q_book_check = mysqli_query($conn, "SELECT title, image, sell_price, stock FROM books WHERE title LIKE '%$book_title%' AND seller_id = '$seller_check_id' LIMIT 1");
                                if(mysqli_num_rows($q_book_check) > 0) {
                                    $b_data = mysqli_fetch_assoc($q_book_check);
                                    $b_img_chat = !empty($b_data['image']) ? "../assets/uploads/books/".$b_data['image'] : "../assets/images/book_placeholder.png";

                                    $product_card = '
                                    <div class="mt-2 mb-1 p-2 bg-gray-50 dark:bg-stone-800 rounded-lg border border-gray-200 flex items-center gap-3 bg-white dark:bg-stone-900/90">
                                        <img src="'.$b_img_chat.'" class="w-12 h-16 object-cover rounded-md">
                                        <div class="text-left">
                                            <p class="text-[10px] font-bold text-primary dark:text-sage uppercase">Produk Ditanyakan</p>
                                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200 line-clamp-1">'.$b_data['title'].'</p>
                                            <p class="text-xs text-chocolate dark:text-tan font-bold">Rp '.number_format($b_data['sell_price'],0,',','.').'</p>
                                        </div>
                                    </div>';
                                }
                            }
                        ?>
                        <div class="flex w-full">
                            <div class="chat-bubble shadow-sm <?= $is_me ? 'chat-own' : 'chat-other' ?>">
                                <?= $product_card // Tampilkan Kartu Produk jika ada ?>
                                <p class="text-sm leading-relaxed"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                <span class="text-[10px] block text-right mt-1 opacity-70">
                                    <?= date('H:i', strtotime($msg['created_at'])) ?>
                                    <?php if($is_me): ?>
                                        <span class="material-symbols-outlined text-[10px] align-middle ml-0.5"><?= $msg['is_read'] ? 'done_all' : 'check' ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>

                    <div class="bg-white dark:bg-stone-900 border-t border-tan/20 dark:border-stone-800 relative z-20">

                        <?php if ($context_book):
                            $b_img = !empty($context_book['image']) ? "../assets/uploads/books/".$context_book['image'] : "../assets/images/book_placeholder.png";
                        ?>
                        <div class="px-4 pt-4 pb-2 border-b border-dashed border-gray-200">
                            <div class="flex items-center gap-3 p-3 bg-cream dark:bg-stone-800 rounded-xl border border-[var(--light-sage)] relative shadow-sm">
                                <div class="w-12 h-16 bg-gray-200 rounded-lg overflow-hidden shrink-0 border border-tan/20 dark:border-stone-800">
                                    <img src="<?= $b_img ?>" class="w-full h-full object-cover">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] text-primary dark:text-sage font-bold uppercase mb-0.5 tracking-wide">Bertanya Produk:</p>
                                    <h4 class="text-sm font-bold text-stone-800 dark:text-stone-200 truncate"><?= $context_book['title'] ?></h4>
                                    <p class="text-xs text-chocolate dark:text-tan font-bold">Rp <?= number_format($context_book['sell_price'], 0, ',', '.') ?></p>
                                </div>
                                <a href="chat_list.php?uid=<?= $_GET['uid'] ?>" class="absolute top-2 right-2 text-stone-400 hover:text-red-500 p-1">
                                    <span class="material-symbols-outlined text-sm">close</span>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <form method="POST" class="p-4 flex gap-3 items-end">
                            <input type="hidden" name="receiver_id" value="<?= $active_chat['id'] ?>">

                            <?php
                                $default_msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
                            ?>

                            <div class="flex-1 relative">
                                <input type="text" name="message" value="<?= $default_msg ?>" placeholder="Tulis pesan..." class="w-full pl-4 pr-10 py-3 rounded-2xl bg-gray-50 dark:bg-stone-800 border-transparent focus:bg-white dark:bg-stone-900 focus:border-tan focus:ring-0 transition-all text-sm" autocomplete="off" autofocus>
                                <span class="material-symbols-outlined absolute right-3 top-3 text-gray-400 cursor-pointer hover:text-primary dark:text-sage">sentiment_satisfied</span>
                            </div>

                            <button type="submit" name="send_message" class="p-3 bg-primary text-white rounded-xl hover:bg-chocolate transition-all shadow-md active:scale-95 flex items-center justify-center">
                                <span class="material-symbols-outlined">send</span>
                            </button>
                        </form>
                    </div>

                <?php else: ?>
                    <div class="flex-1 flex flex-col items-center justify-center text-center p-8 opacity-60">
                        <div class="w-32 h-32 bg-sage/30 rounded-full flex items-center justify-center mb-6 animate-pulse">
                            <span class="material-symbols-outlined text-6xl text-primary dark:text-sage">chat</span>
                        </div>
                        <h3 class="text-2xl font-bold text-primary dark:text-sage mb-2 font-logo">Mulai Percakapan</h3>
                        <p class="text-gray-500 max-w-xs">Pilih percakapan dari daftar di sebelah kiri atau mulai chat baru dari halaman produk.</p>
                    </div>
                <?php endif; ?>

            </div>
        </div>

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

        // Auto Scroll ke bawah chat
        const chatContainer = document.getElementById('chatContainer');
        if(chatContainer) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
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
