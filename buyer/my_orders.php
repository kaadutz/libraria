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

// --- LOGIKA UPLOAD BUKTI BAYAR ---
if (isset($_POST['upload_proof'])) {
    $order_id = $_POST['order_id'];
    if (!empty($_FILES['payment_proof']['name'])) {
        $target_dir = "../assets/uploads/proofs/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
        $new_name = "proof_" . $order_id . "_" . time() . "." . $ext;
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'])) {
            if(move_uploaded_file($_FILES['payment_proof']['tmp_name'], $target_dir . $new_name)) {
                $check = mysqli_query($conn, "SELECT status FROM orders WHERE id='$order_id'");
                $st = mysqli_fetch_assoc($check)['status'];
                $new_st = ($st == 'pending' || $st == 'rejected' || $st == 'waiting_approval') ? 'waiting_approval' : $st;

                mysqli_query($conn, "UPDATE orders SET payment_proof = '$new_name', status = '$new_st' WHERE id = '$order_id'");
                header("Location: my_orders.php?status=success_upload"); exit;
            }
        }
    }
}

// --- LOGIKA TERIMA BARANG ---
if (isset($_POST['finish_order'])) {
    $order_id = $_POST['order_id'];
    mysqli_query($conn, "UPDATE orders SET status = 'finished' WHERE id = '$order_id'");
    $success_msg = "Pesanan selesai! Terima kasih.";
}

// --- LOGIKA AJUKAN REFUND ---
if (isset($_POST['request_refund'])) {
    $order_id = $_POST['order_id'];

    // Ambil alamat Penjual
    $q_seller = mysqli_query($conn, "
        SELECT u.address
        FROM users u
        JOIN order_items oi ON u.id = oi.seller_id
        WHERE oi.order_id = '$order_id' LIMIT 1
    ");
    $seller = mysqli_fetch_assoc($q_seller);
    $seller_address = $seller['address'] ?? 'Alamat toko tidak tersedia.';

    mysqli_query($conn, "UPDATE orders SET status = 'refund', refund_time = NOW() WHERE id = '$order_id'");

    $_SESSION['refund_msg'] = "Pengajuan Berhasil!<br>Silakan datang ke alamat toko: <br><b>" . $seller_address . "</b><br>untuk pengembalian dana manual.";
    header("Location: my_orders.php"); exit;
}

if (isset($_GET['status']) && $_GET['status'] == 'success_upload') {
    $success_msg = "Bukti pembayaran berhasil diupload!";
}

// --- QUERY DATA PESANAN UTAMA ---
$query_orders = mysqli_query($conn, "SELECT * FROM orders WHERE buyer_id = '$buyer_id' ORDER BY order_date DESC");

// --- DATA NAVBAR & NOTIFIKASI DINAMIS ---
$query_cart = mysqli_query($conn, "SELECT SUM(qty) as total FROM carts WHERE buyer_id = '$buyer_id'");
$cart_count = mysqli_fetch_assoc($query_cart)['total'] ?? 0;

$q_user = mysqli_query($conn, "SELECT profile_image FROM users WHERE id = '$buyer_id'");
$user_data = mysqli_fetch_assoc($q_user);
$profile_pic = !empty($user_data['profile_image']) ? "../assets/uploads/profiles/" . $user_data['profile_image'] : "../assets/images/default_profile.png";

// NOTIFIKASI PINTAR
$notif_list = [];

// 1. Pesan Belum Dibaca
$q_msg_notif = mysqli_query($conn, "SELECT m.*, u.full_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.receiver_id = '$buyer_id' AND m.is_read = 0 ORDER BY m.created_at DESC");
while($msg = mysqli_fetch_assoc($q_msg_notif)){
    $notif_list[] = [
        'type' => 'chat', 'title' => 'Pesan dari ' . explode(' ', $msg['full_name'])[0],
        'text' => substr($msg['message'], 0, 25) . '...', 'icon' => 'chat', 'color' => 'blue',
        'link' => 'chat_list.php', 'time' => strtotime($msg['created_at'])
    ];
}

// 2. Status Pesanan Terakhir
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

    if($ord['status'] == 'approved') { $text = "Pesanan Diterima Penjual. Segera dikemas."; $icon = "inventory_2"; $color = "indigo"; }
    elseif($ord['status'] == 'shipping') { $text = "Paket sedang dalam perjalanan."; $icon = "local_shipping"; $color = "purple"; }
    elseif($ord['status'] == 'rejected') { $text = "Pesanan/Refund Ditolak oleh Penjual."; $icon = "cancel"; $color = "red"; }
    elseif($ord['status'] == 'refunded') { $text = "Pengajuan Refund Disetujui."; $icon = "currency_exchange"; $color = "green"; }
    elseif($ord['status'] == 'finished') { $text = "Pesanan Selesai. Terima kasih!"; $icon = "check_circle"; $color = "teal"; }

    $notif_list[] = [
        'type' => 'order', 'title' => $title, 'text' => $text, 'icon' => $icon, 'color' => $color,
        'link' => 'my_orders.php', 'time' => strtotime($ord['order_date'])
    ];
}
usort($notif_list, function($a, $b) { return $b['time'] - $a['time']; });
$total_notif = count($notif_list);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<title>Pesanan Saya - Libraria</title>

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

    <?php if(isset($success_msg)): ?>
    <div id="toast" class="fixed top-28 right-5 z-[60] flex items-center gap-3 px-6 py-4 rounded-2xl shadow-2xl text-white bg-primary animate-bounce">
        <span class="material-symbols-outlined">check_circle</span><p class="text-sm font-bold"><?= $success_msg ?></p>
    </div>
    <script>setTimeout(()=>{document.getElementById('toast').remove()},3000);</script>
    <?php endif; ?>



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



    <main class="flex-1 pt-32 pb-12 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto w-full">

        <div class="text-center mb-10" data-aos="fade-down">
            <h1 class="text-3xl lg:text-4xl font-bold text-primary dark:text-sage title-font mb-2">Riwayat Pesanan</h1>
            <p class="text-stone-500 dark:text-stone-400">Pantau status pesanan dan upload bukti pembayaran Anda di sini.</p>
        </div>

        <div class="space-y-6" data-aos="fade-up">

            <?php if(mysqli_num_rows($query_orders) > 0): ?>
                <?php while($order = mysqli_fetch_assoc($query_orders)):
                    $order_id = $order['id'];
                    $items_q = mysqli_query($conn, "SELECT oi.*, b.title, b.image, oi.seller_id FROM order_items oi JOIN books b ON oi.book_id = b.id WHERE oi.order_id = '$order_id'");
                    $first_item = mysqli_fetch_assoc($items_q);
                    $seller_id_chat = $first_item['seller_id'];

                    // --- LOGIKA PESAN CHAT OTOMATIS BERDASARKAN STATUS ---
                    $inv = $order['invoice_number'];
                    $chat_msg = "";

                    if($order['status'] == 'pending') $chat_msg = "Halo kak, saya sudah checkout pesanan *$inv*, mohon diproses ya.";
                    elseif($order['status'] == 'rejected') $chat_msg = "Halo kak, kenapa pesanan *$inv* saya ditolak? Mohon infonya.";
                    elseif($order['status'] == 'refund') $chat_msg = "Halo kak, saya mengajukan refund untuk pesanan *$inv*.";
                    elseif($order['status'] == 'shipping') $chat_msg = "Halo kak, pesanan *$inv* sudah sampai mana ya?";
                    elseif($order['status'] == 'finished') $chat_msg = "Halo kak, pesanan *$inv* sudah saya terima dengan baik. Terima kasih!";
                    else $chat_msg = "Halo kak, saya ingin bertanya mengenai pesanan *$inv*.";

                    // PERUBAHAN: LINK KE CHAT_LIST.PHP
                    $chat_link = "chat_list.php?uid=" . $seller_id_chat . "&msg=" . urlencode($chat_msg);

                    // Logic Status
                    $status_class = 'bg-gray-100 text-gray-600';
                    $status_label = ucfirst($order['status']);
                    $need_upload = ($order['status'] == 'pending' && empty($order['payment_proof']));

                    if($need_upload) { $status_class = 'bg-red-100 text-red-600'; $status_label = 'Belum Bayar'; }
                    elseif($order['status'] == 'waiting_approval') { $status_class = 'bg-yellow-100 text-yellow-700'; $status_label = 'Menunggu Konfirmasi'; }
                    elseif($order['status'] == 'approved') { $status_class = 'bg-indigo-100 text-indigo-700'; $status_label = 'Diproses'; }
                    elseif($order['status'] == 'shipping') { $status_class = 'bg-purple-100 text-purple-700'; $status_label = 'Dikirim'; }
                    elseif($order['status'] == 'finished') { $status_class = 'bg-green-100 text-green-700'; $status_label = 'Selesai'; }
                    elseif($order['status'] == 'rejected') { $status_class = 'bg-red-100 text-red-700'; $status_label = 'Ditolak'; }
                    elseif($order['status'] == 'refund') { $status_class = 'bg-orange-100 text-orange-700'; $status_label = 'Proses Refund'; }
                    elseif($order['status'] == 'refunded') { $status_class = 'bg-stone-200 text-stone-600 line-through'; $status_label = 'Refund Selesai'; }

                    $tracking_link = "#";
                    if($order['expedition_name'] == 'JNE') $tracking_link = "https://www.jne.co.id/";
                    elseif($order['expedition_name'] == 'J&T') $tracking_link = "https://jet.co.id/track";
                    elseif($order['expedition_name'] == 'Shopee Express') $tracking_link = "https://spx.co.id/";
                ?>
                <div class="bg-white rounded-[2.5rem] p-6 border border-tan/20 dark:border-stone-800 card-shadow hover:shadow-lg transition-all relative overflow-hidden">

                    <?php if($order['status'] == 'refund'):
                        $q_seller = mysqli_query($conn, "SELECT u.address, u.full_name FROM users u JOIN order_items oi ON u.id = oi.seller_id WHERE oi.order_id = '$order_id' LIMIT 1");
                        $seller = mysqli_fetch_assoc($q_seller);
                    ?>
                    <div class="mb-6 bg-orange-50 border-l-4 border-orange-500 p-4 rounded-r-xl">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-orange-500 mt-1">store</span>
                            <div>
                                <h4 class="font-bold text-orange-800 text-sm uppercase mb-1">Instruksi Pengembalian Dana</h4>
                                <p class="text-sm text-orange-700 leading-relaxed">
                                    Silakan datang ke alamat toko penjual untuk proses refund manual.
                                    <br><span class="font-semibold">Toko:</span> <?= $seller['full_name'] ?>
                                    <br><span class="font-semibold">Alamat:</span> <?= $seller['address'] ?? 'Hubungi Penjual' ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-dashed border-tan/20 dark:border-stone-800 pb-4 mb-4 gap-4">
                        <div>
                            <div class="flex items-center gap-3 mb-1">
                                <span class="material-symbols-outlined text-primary dark:text-sage">receipt_long</span>
                                <h3 class="font-bold text-stone-800 dark:text-stone-200 text-lg tracking-wide"><?= $order['invoice_number'] ?></h3>
                            </div>
                            <p class="text-xs text-stone-500 dark:text-stone-400 flex items-center gap-1">
                                <span class="material-symbols-outlined text-xs">calendar_month</span>
                                <?= date('d F Y, H:i', strtotime($order['order_date'])) ?>
                            </p>
                        </div>
                        <span class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm <?= $status_class ?>">
                            <?= $status_label ?>
                        </span>
                    </div>

                    <div class="space-y-4 mb-6">
                        <?php
                        mysqli_data_seek($items_q, 0);
                        while($item = mysqli_fetch_assoc($items_q)):
                            $img_src = !empty($item['image']) ? "../assets/uploads/books/".$item['image'] : "../assets/images/book_placeholder.png";
                        ?>
                        <div class="flex gap-4 p-2 rounded-xl hover:bg-cream dark:bg-stone-800/30 transition-colors">
                            <div class="w-16 h-20 bg-gray-100 rounded-lg overflow-hidden border border-tan/20 dark:border-stone-800 shrink-0 shadow-sm">
                                <img src="<?= $img_src ?>" class="w-full h-full object-cover">
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold text-sm text-stone-800 dark:text-stone-200 line-clamp-1 mb-1"><?= $item['title'] ?></h4>
                                <p class="text-xs text-stone-500 dark:text-stone-400 font-mono">Qty: <?= $item['qty'] ?> x Rp <?= number_format($item['price_at_transaction'], 0, ',', '.') ?></p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>

                    <?php if(($order['status'] == 'rejected') && !empty($order['reject_reason'])): ?>
                        <div class="mb-6 p-4 bg-red-50 border border-red-100 rounded-xl">
                            <p class="text-xs font-bold text-red-600 uppercase mb-1">Alasan Penolakan:</p>
                            <p class="text-sm text-red-800 leading-snug"><?= htmlspecialchars($order['reject_reason']) ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="bg-cream dark:bg-stone-800/50 rounded-2xl p-4 flex flex-col md:flex-row justify-between items-center gap-4 border border-tan/20 dark:border-stone-800">
                        <div class="text-left w-full md:w-auto">
                            <p class="text-xs text-stone-500 dark:text-stone-400 uppercase tracking-widest font-bold">Total Belanja</p>
                            <p class="text-xl font-black text-chocolate dark:text-tan">Rp <?= number_format($order['total_price'], 0, ',', '.') ?></p>
                        </div>

                        <div class="flex gap-3 w-full md:w-auto flex-wrap justify-end items-center">

                            <a href="<?= $chat_link ?>" class="px-4 py-2.5 bg-blue-50 text-blue-600 font-bold rounded-xl hover:bg-blue-100 transition-all text-sm flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">chat</span> Chat Penjual
                            </a>

                            <a href="invoice.php?id=<?= $order['id'] ?>" target="_blank" class="px-5 py-2.5 bg-white border border-tan/20 dark:border-stone-800 text-primary dark:text-sage font-bold rounded-xl hover:bg-gray-50 transition-all text-sm flex items-center justify-center gap-2 shadow-sm">
                                <span class="material-symbols-outlined text-sm">print</span> Struk
                            </a>

                            <?php if($need_upload): ?>
                                <button onclick="openUploadModal(<?= $order['id'] ?>, '<?= $order['invoice_number'] ?>')" class="flex-1 md:flex-none px-6 py-2.5 bg-primary text-white font-bold rounded-xl hover:bg-chocolate transition-all text-sm flex items-center justify-center gap-2 shadow-md animate-pulse">
                                    <span class="material-symbols-outlined text-sm">upload_file</span> Upload
                                </button>
                            <?php endif; ?>

                            <?php if($order['status'] == 'rejected'): ?>
                                <form method="POST" onsubmit="return confirm('Ajukan pengembalian dana ke penjual?')" class="flex-1 md:flex-none">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <button type="submit" name="request_refund" class="w-full px-6 py-2.5 bg-orange-500 text-white font-bold rounded-xl hover:bg-orange-600 transition-all text-sm flex items-center justify-center gap-2 shadow-md">
                                        <span class="material-symbols-outlined text-sm">assignment_return</span> Ajukan Refund
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if($order['status'] == 'shipping'): ?>
                                <form method="POST" onsubmit="return confirm('Pesanan sudah diterima?')">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <button type="submit" name="finish_order" class="px-6 py-2.5 bg-green-600 text-white font-bold rounded-xl hover:bg-green-700 transition-all text-sm flex items-center gap-2">
                                        <span class="material-symbols-outlined text-sm">check_circle</span> Terima
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if(($order['status'] == 'shipping' || $order['status'] == 'finished') && !empty($order['tracking_number'])): ?>
                        <div class="mt-4 pt-4 border-t border-dashed border-gray-200 text-sm flex justify-between items-center bg-yellow-50 p-3 rounded-xl border border-yellow-100">
                            <div>
                                <span class="font-bold text-primary dark:text-sage"><?= $order['expedition_name'] ?></span> - <span class="font-mono text-stone-600"><?= $order['tracking_number'] ?></span>
                            </div>
                            <a href="<?= $tracking_link ?>" target="_blank" class="px-3 py-1 bg-white border border-yellow-200 text-yellow-700 text-xs font-bold rounded-lg hover:bg-yellow-100 flex items-center gap-1 shadow-sm">
                                Lacak <span class="material-symbols-outlined text-xs">open_in_new</span>
                            </a>
                        </div>
                    <?php endif; ?>

                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-20 bg-white rounded-[2.5rem] border-2 border-dashed border-tan/20 dark:border-stone-800">
                    <span class="material-symbols-outlined text-6xl text-stone-500 dark:text-stone-400 opacity-50 mb-4">shopping_cart_off</span>
                    <h3 class="text-xl font-bold text-stone-800 dark:text-stone-200">Belum ada pesanan</h3>
                    <p class="text-stone-500 mb-6">Yuk mulai belanja buku favoritmu!</p>
                    <a href="index.php" class="px-6 py-3 bg-primary text-white font-bold rounded-xl hover:bg-chocolate transition-all shadow-lg">Belanja Sekarang</a>
                </div>
            <?php endif; ?>

        </div>

    </main>

    <div id="uploadModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-[70]">
        <div class="modal-overlay absolute w-full h-full bg-black/50 backdrop-blur-sm" onclick="toggleModal('uploadModal')"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded-[2rem] shadow-2xl z-50 p-8 transform scale-95 transition-all">
            <h3 class="text-xl font-bold text-primary dark:text-sage mb-2 title-font">Upload Bukti Bayar</h3>
            <p class="text-sm text-gray-500 mb-6">Invoice: <span id="modalInvoice" class="font-bold text-chocolate dark:text-tan"></span></p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="order_id" id="modalOrderId">
                <div class="mb-6">
                    <label class="block text-xs font-bold text-stone-500 dark:text-stone-400 uppercase mb-2">Pilih Foto / Screenshot</label>
                    <input type="file" name="payment_proof" required accept="image/*" class="w-full text-sm text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-sage file:text-primary dark:text-sage hover:file:bg-tan hover:file:text-white transition-all cursor-pointer bg-gray-50 rounded-xl border border-tan/20 dark:border-stone-800">
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="toggleModal('uploadModal')" class="flex-1 py-3 bg-gray-100 text-gray-600 font-bold rounded-xl hover:bg-gray-200 transition-colors">Batal</button>
                    <button type="submit" name="upload_proof" class="flex-1 py-3 bg-primary text-white font-bold rounded-xl hover:opacity-90 transition-colors shadow-md">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ once: true, duration: 800, offset: 50 });

        // Show SweetAlert for Refund Instructions
        <?php if(isset($_SESSION['refund_msg'])): ?>
        Swal.fire({
            icon: 'info',
            title: 'Instruksi Refund',
            html: '<?= $_SESSION['refund_msg'] ?>',
            confirmButtonText: 'Mengerti',
            confirmButtonColor: '#3E4B1C'
        });
        <?php unset($_SESSION['refund_msg']); endif; ?>

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

        function openUploadModal(id, invoice) {
            document.getElementById('modalOrderId').value = id;
            document.getElementById('modalInvoice').innerText = invoice;
            toggleModal('uploadModal');
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
