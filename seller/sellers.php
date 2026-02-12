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
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Chat - Libraria</title>

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
        
        /* Chat Styling */
        .chat-bubble { max-width: 75%; padding: 12px 16px; border-radius: 1rem; position: relative; }
        .chat-own { background-color: var(--deep-forest); color: white; border-bottom-right-radius: 0; margin-left: auto; }
        .chat-other { background-color: white; border: 1px solid var(--border-color); border-bottom-left-radius: 0; color: var(--text-dark); }
        
        /* Scrollbar */
        .chat-area::-webkit-scrollbar { width: 6px; }
        .chat-area::-webkit-scrollbar-track { background: transparent; }
        .chat-area::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }
    </style>
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

                <div class="hidden md:flex flex-1 max-w-xl mx-auto"></div>

                <div class="flex items-center gap-2">
                    <div class="hidden lg:flex items-center gap-1 text-sm font-bold text-[var(--text-muted)] mr-2">
                        <a href="index.php" class="px-3 py-2 rounded-xl hover:bg-[var(--cream-bg)] hover:text-[var(--deep-forest)] transition-colors">Beranda</a>
                        <a href="my_orders.php" class="px-3 py-2 rounded-xl hover:bg-[var(--cream-bg)] hover:text-[var(--deep-forest)] transition-colors">Pesanan</a>
                        <a href="chat_list.php" class="px-3 py-2 rounded-xl bg-[var(--deep-forest)] text-white shadow-md transition-colors">Chat</a>
                    </div>

                    <a href="help.php" class="w-10 h-10 flex items-center justify-center rounded-full text-[var(--text-muted)] hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] transition-all">
                        <span class="material-symbols-outlined">help</span>
                    </a>

                    <div class="relative">
                        <button onclick="toggleDropdown('notificationDropdown')" class="w-10 h-10 flex items-center justify-center rounded-full text-[var(--text-muted)] hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] transition-all relative">
                            <span class="material-symbols-outlined">notifications</span>
                            <?php if($total_notif > 0): ?><span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white animate-ping"></span><?php endif; ?>
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

    <main class="flex-1 pt-32 pb-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full h-[calc(100vh-2rem)]">
        
        <div class="bg-white rounded-[2.5rem] border border-[var(--border-color)] shadow-xl overflow-hidden h-full flex flex-col md:flex-row">
            
            <div class="w-full md:w-80 border-r border-[var(--border-color)] bg-gray-50/50 flex flex-col h-full <?= $active_chat ? 'hidden md:flex' : 'flex' ?>">
                <div class="p-6 border-b border-[var(--border-color)] bg-white">
                    <h2 class="text-xl font-bold text-[var(--deep-forest)]">Pesan</h2>
                </div>
                
                <div class="flex-1 overflow-y-auto p-3 space-y-2">
                    <?php if(mysqli_num_rows($chat_list) > 0): ?>
                        <?php while($chat = mysqli_fetch_assoc($chat_list)): 
                            $c_img = !empty($chat['profile_image']) ? "../assets/uploads/profiles/".$chat['profile_image'] : "../assets/images/default_profile.png";
                            $active = ($active_chat && $active_chat['id'] == $chat['id']) ? 'bg-[var(--light-sage)]/30 border-[var(--light-sage)]' : 'bg-white border-transparent hover:bg-gray-50';
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
                                    <h4 class="font-bold text-sm text-[var(--text-dark)] truncate"><?= $chat['full_name'] ?></h4>
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
                    <div class="p-4 border-b border-[var(--border-color)] bg-white flex items-center gap-4 shadow-sm z-10">
                        <a href="chat_list.php" class="md:hidden text-gray-500 hover:text-[var(--deep-forest)]">
                            <span class="material-symbols-outlined">arrow_back</span>
                        </a>
                        <img src="<?= $a_img ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200">
                        <div>
                            <h3 class="font-bold text-[var(--text-dark)]"><?= $active_chat['full_name'] ?></h3>
                            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Penjual</p>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto p-6 space-y-4 chat-area bg-white/50 backdrop-blur-sm" id="chatContainer">
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
                                    <div class="mt-2 mb-1 p-2 bg-gray-50 rounded-lg border border-gray-200 flex items-center gap-3 bg-white/90">
                                        <img src="'.$b_img_chat.'" class="w-12 h-16 object-cover rounded-md">
                                        <div class="text-left">
                                            <p class="text-[10px] font-bold text-[var(--deep-forest)] uppercase">Produk Ditanyakan</p>
                                            <p class="text-xs font-bold text-gray-800 line-clamp-1">'.$b_data['title'].'</p>
                                            <p class="text-xs text-[var(--chocolate-brown)] font-bold">Rp '.number_format($b_data['sell_price'],0,',','.').'</p>
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

                    <div class="bg-white border-t border-[var(--border-color)] relative z-20">
                        
                        <?php if ($context_book): 
                            $b_img = !empty($context_book['image']) ? "../assets/uploads/books/".$context_book['image'] : "../assets/images/book_placeholder.png";
                        ?>
                        <div class="px-4 pt-4 pb-2 border-b border-dashed border-gray-200">
                            <div class="flex items-center gap-3 p-3 bg-[var(--cream-bg)] rounded-xl border border-[var(--light-sage)] relative shadow-sm">
                                <div class="w-12 h-16 bg-gray-200 rounded-lg overflow-hidden shrink-0 border border-[var(--border-color)]">
                                    <img src="<?= $b_img ?>" class="w-full h-full object-cover">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] text-[var(--deep-forest)] font-bold uppercase mb-0.5 tracking-wide">Bertanya Produk:</p>
                                    <h4 class="text-sm font-bold text-[var(--text-dark)] truncate"><?= $context_book['title'] ?></h4>
                                    <p class="text-xs text-[var(--chocolate-brown)] font-bold">Rp <?= number_format($context_book['sell_price'], 0, ',', '.') ?></p>
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
                                <input type="text" name="message" value="<?= $default_msg ?>" placeholder="Tulis pesan..." class="w-full pl-4 pr-10 py-3 rounded-2xl bg-gray-50 border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all text-sm" autocomplete="off" autofocus>
                                <span class="material-symbols-outlined absolute right-3 top-3 text-gray-400 cursor-pointer hover:text-[var(--deep-forest)]">sentiment_satisfied</span>
                            </div>
                            
                            <button type="submit" name="send_message" class="p-3 bg-[var(--deep-forest)] text-white rounded-xl hover:bg-[var(--chocolate-brown)] transition-all shadow-md active:scale-95 flex items-center justify-center">
                                <span class="material-symbols-outlined">send</span>
                            </button>
                        </form>
                    </div>

                <?php else: ?>
                    <div class="flex-1 flex flex-col items-center justify-center text-center p-8 opacity-60">
                        <div class="w-32 h-32 bg-[var(--light-sage)]/30 rounded-full flex items-center justify-center mb-6 animate-pulse">
                            <span class="material-symbols-outlined text-6xl text-[var(--deep-forest)]">chat</span>
                        </div>
                        <h3 class="text-2xl font-bold text-[var(--deep-forest)] mb-2 font-logo">Mulai Percakapan</h3>
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
</body>
</html>
