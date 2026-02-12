<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../config/db.php';

// 1. Cek Keamanan
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller') {
    header("Location: ../auth/login.php");
    exit;
}

$seller_id = $_SESSION['user_id'];
$seller_name = $_SESSION['full_name'];

// --- LOGIKA KIRIM PESAN ---
if (isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id']; // ID Pembeli
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    if (!empty($message)) {
        $q_send = "INSERT INTO messages (sender_id, receiver_id, message, is_read, created_at) VALUES ('$seller_id', '$receiver_id', '$message', 0, NOW())";
        mysqli_query($conn, $q_send);
        header("Location: chat.php?uid=" . $receiver_id);
        exit;
    }
}

// --- LOGIKA AMBIL DAFTAR CHAT (SIDEBAR) ---
// Menampilkan daftar pembeli yang pernah chat dengan seller ini
$q_chats = "
    SELECT DISTINCT u.id, u.full_name, u.profile_image,
    (SELECT message FROM messages WHERE (sender_id = u.id AND receiver_id = '$seller_id') OR (sender_id = '$seller_id' AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_msg,
    (SELECT created_at FROM messages WHERE (sender_id = u.id AND receiver_id = '$seller_id') OR (sender_id = '$seller_id' AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_time,
    (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = '$seller_id' AND is_read = 0) as unread
    FROM users u
    JOIN messages m ON u.id = m.sender_id OR u.id = m.receiver_id
    WHERE (m.sender_id = '$seller_id' OR m.receiver_id = '$seller_id') AND u.id != '$seller_id'
    ORDER BY last_time DESC
";
$chat_list = mysqli_query($conn, $q_chats);

// --- LOGIKA BUKA CHAT SPESIFIK ---
$active_chat = null;
if (isset($_GET['uid'])) {
    $chat_uid = mysqli_real_escape_string($conn, $_GET['uid']);

    // Ambil Data Pembeli
    $q_buyer = mysqli_query($conn, "SELECT * FROM users WHERE id = '$chat_uid'");
    if(mysqli_num_rows($q_buyer) > 0) {
        $active_chat = mysqli_fetch_assoc($q_buyer);

        // Tandai pesan sudah dibaca
        mysqli_query($conn, "UPDATE messages SET is_read = 1 WHERE sender_id = '$chat_uid' AND receiver_id = '$seller_id'");

        // Ambil isi chat
        $q_msgs = mysqli_query($conn, "SELECT * FROM messages WHERE (sender_id = '$seller_id' AND receiver_id = '$chat_uid') OR (sender_id = '$chat_uid' AND receiver_id = '$seller_id') ORDER BY created_at ASC");
    }
}

// --- LOGIKA NOTIFIKASI NAVBAR ---
$query_orders = mysqli_query($conn, "SELECT COUNT(DISTINCT o.id) as total FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE oi.seller_id = '$seller_id' AND (o.status = 'pending' OR o.status = 'waiting_approval')");
$total_new_orders = mysqli_fetch_assoc($query_orders)['total'];

$query_unread = mysqli_query($conn, "SELECT COUNT(*) as total FROM messages WHERE receiver_id = '$seller_id' AND is_read = 0");
$total_unread_chat = mysqli_fetch_assoc($query_unread)['total'];
$total_notif = $total_new_orders + $total_unread_chat;
?>

<!DOCTYPE html>
<html lang="id">
<head>
<title>Chat Pembeli - Libraria Seller</title>

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



    <main id="main-content" class="flex-1 ml-64 p-4 h-screen overflow-hidden flex flex-col transition-all duration-300">

        <header class="flex justify-between items-center mb-4 bg-white dark:bg-stone-900/50 backdrop-blur-sm p-4 rounded-3xl border border-tan/20 dark:border-stone-800 shrink-0 shadow-sm">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl hover:bg-sage text-primary dark:text-sage transition-colors focus:outline-none">
                    <span class="material-symbols-outlined">menu_open</span>
                </button>
                <h2 class="text-xl font-bold text-stone-800 dark:text-stone-200 hidden md:block">Chat Pelanggan</h2>
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center gap-3 bg-white dark:bg-stone-900 p-1.5 pr-4 rounded-full border border-tan/20 dark:border-stone-800 shadow-sm">
                    <div class="w-9 h-9 rounded-full bg-tan text-white flex items-center justify-center font-bold text-sm border-2 border-[var(--cream-bg)]"><?= strtoupper(substr($seller_name, 0, 1)) ?></div>
                    <p class="text-xs font-bold hidden sm:block text-stone-800 dark:text-stone-200"><?= $seller_name ?></p>
                </div>
            </div>
        </header>

        <div class="flex-1 bg-white dark:bg-stone-900 rounded-[2.5rem] border border-tan/20 dark:border-stone-800 shadow-xl overflow-hidden flex flex-col md:flex-row relative">

            <div class="w-full md:w-80 border-r border-tan/20 dark:border-stone-800 bg-gray-50 dark:bg-stone-800/50 flex flex-col h-full <?= $active_chat ? 'hidden md:flex' : 'flex' ?>">
                <div class="p-4 border-b border-tan/20 dark:border-stone-800 bg-white dark:bg-stone-900">
                    <input type="text" placeholder="Cari nama pembeli..." class="w-full px-4 py-2 rounded-xl bg-gray-50 dark:bg-stone-800 border-transparent focus:bg-white dark:bg-stone-900 focus:border-tan focus:ring-0 text-sm">
                </div>

                <div class="flex-1 overflow-y-auto p-2 space-y-2">
                    <?php if(mysqli_num_rows($chat_list) > 0): ?>
                        <?php while($chat = mysqli_fetch_assoc($chat_list)):
                            $c_img = !empty($chat['profile_image']) ? "../assets/uploads/profiles/".$chat['profile_image'] : "../assets/images/default_profile.png";
                            $active = ($active_chat && $active_chat['id'] == $chat['id']) ? 'bg-sage/30 border-[var(--light-sage)]' : 'bg-white dark:bg-stone-900 border-transparent hover:bg-gray-50 dark:bg-stone-800';
                        ?>
                        <a href="chat.php?uid=<?= $chat['id'] ?>" class="flex items-center gap-3 p-3 rounded-2xl border transition-all <?= $active ?>">
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
                            <p class="text-sm">Belum ada chat masuk.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex-1 flex flex-col bg-[url('../assets/images/chat-bg.png')] bg-repeat h-full <?= $active_chat ? 'flex' : 'hidden md:flex' ?>">

                <?php if($active_chat):
                    $a_img = !empty($active_chat['profile_image']) ? "../assets/uploads/profiles/".$active_chat['profile_image'] : "../assets/images/default_profile.png";
                ?>
                    <div class="p-4 border-b border-tan/20 dark:border-stone-800 bg-white dark:bg-stone-900 flex items-center gap-4 shadow-sm z-10">
                        <a href="chat.php" class="md:hidden text-gray-500 hover:text-primary dark:text-sage">
                            <span class="material-symbols-outlined">arrow_back</span>
                        </a>
                        <img src="<?= $a_img ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200">
                        <div>
                            <h3 class="font-bold text-stone-800 dark:text-stone-200"><?= $active_chat['full_name'] ?></h3>
                            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Pembeli</p>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto p-6 space-y-4 chat-area bg-white dark:bg-stone-900/50 backdrop-blur-sm" id="chatContainer">
                        <?php
                        while($msg = mysqli_fetch_assoc($q_msgs)):
                            $is_me = ($msg['sender_id'] == $seller_id);

                            // --- SMART PRODUCT DETECTION ---
                            // Cek apakah pesan berisi template tanya produk
                            $product_card = "";
                            if (preg_match('/Halo kak, saya tertarik dengan buku \*(.*?)\*/', $msg['message'], $matches)) {
                                $book_title = mysqli_real_escape_string($conn, $matches[1]);
                                // Cari buku milik seller ini yang judulnya mirip
                                $q_book_check = mysqli_query($conn, "SELECT title, image, sell_price, stock FROM books WHERE title LIKE '%$book_title%' AND seller_id = '$seller_id' LIMIT 1");
                                if(mysqli_num_rows($q_book_check) > 0) {
                                    $b_data = mysqli_fetch_assoc($q_book_check);
                                    $b_img = !empty($b_data['image']) ? "../assets/uploads/books/".$b_data['image'] : "../assets/images/book_placeholder.png";

                                    $product_card = '
                                    <div class="mt-2 mb-1 p-2 bg-gray-50 dark:bg-stone-800 rounded-lg border border-gray-200 flex items-center gap-3">
                                        <img src="'.$b_img.'" class="w-12 h-16 object-cover rounded-md">
                                        <div class="text-left">
                                            <p class="text-[10px] font-bold text-primary dark:text-sage uppercase">Produk Ditanyakan</p>
                                            <p class="text-xs font-bold text-gray-800 dark:text-gray-200 line-clamp-1">'.$b_data['title'].'</p>
                                            <p class="text-xs text-chocolate dark:text-tan font-bold">Rp '.number_format($b_data['sell_price'],0,',','.').'</p>
                                            <p class="text-[10px] text-gray-500">Stok: '.$b_data['stock'].'</p>
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

                    <div class="bg-white dark:bg-stone-900 border-t border-tan/20 dark:border-stone-800 p-4 relative z-20">
                        <form method="POST" class="flex gap-3 items-end">
                            <input type="hidden" name="receiver_id" value="<?= $active_chat['id'] ?>">
                            <div class="flex-1 relative">
                                <input type="text" name="message" placeholder="Balas pesan..." class="w-full pl-4 pr-10 py-3 rounded-2xl bg-gray-50 dark:bg-stone-800 border-transparent focus:bg-white dark:bg-stone-900 focus:border-tan focus:ring-0 transition-all text-sm" autocomplete="off" autofocus>
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
                        <h3 class="text-2xl font-bold text-primary dark:text-sage mb-2 font-logo">Pilih Chat</h3>
                        <p class="text-gray-500 max-w-xs">Silakan pilih pesan dari daftar di sebelah kiri untuk mulai membalas.</p>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </main>
</div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({ once: true, duration: 800, offset: 50 });

    let isSidebarOpen = true;
    const sidebar = document.getElementById('sidebar');
    const mainDiv = document.getElementById('main-content');

    function toggleSidebar() {
        if (isSidebarOpen) {
            sidebar.classList.remove('w-64'); sidebar.classList.add('w-20', 'sidebar-collapsed');
            mainDiv.classList.remove('ml-64'); mainDiv.classList.add('ml-20');
        } else {
            sidebar.classList.remove('w-20', 'sidebar-collapsed'); sidebar.classList.add('w-64');
            mainDiv.classList.remove('ml-20'); mainDiv.classList.add('ml-64');
        }
        isSidebarOpen = !isSidebarOpen;
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
