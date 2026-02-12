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

include 'includes/notification_logic.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Pesanan Saya - Libraria</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
    <script src="../assets/js/theme-config.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&family=Cinzel:wght@700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style type="text/tailwindcss">
        :root { --deep-forest: #3E4B1C; --chocolate-brown: #663F05; --warm-tan: #B18143; --light-sage: #DCE3AC; --cream-bg: #FEF9E6; --text-dark: #2D2418; --text-muted: #6B6155; --border-color: #E6E1D3; }
        body { font-family: 'Quicksand', sans-serif; }
        .font-logo { font-family: 'Cinzel', serif; }
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow-x: hidden; overflow-y: hidden !important; }
        .card-shadow { box-shadow: 0 10px 40px -10px rgba(62, 75, 28, 0.08); }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #dce3ac; border-radius: 10px; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-stone-800 dark:text-stone-200 overflow-x-hidden min-h-screen flex flex-col transition-colors duration-300">

    <?php if(isset($success_msg)): ?>
    <div id="toast" class="fixed top-28 right-5 z-[60] flex items-center gap-3 px-6 py-4 rounded-2xl shadow-2xl text-white bg-[var(--deep-forest)] animate-bounce">
        <span class="material-symbols-outlined">check_circle</span><p class="text-sm font-bold"><?= $success_msg ?></p>
    </div>
    <script>setTimeout(()=>{document.getElementById('toast').remove()},3000);</script>
    <?php endif; ?>

    <nav class="fixed top-0 w-full z-50 px-4 sm:px-6 lg:px-8 pt-4 transition-all duration-300" id="navbar">
        <div class="bg-white/90 dark:bg-stone-900/90 backdrop-blur-md rounded-3xl border border-[var(--border-color)] dark:border-stone-700 shadow-sm max-w-7xl mx-auto px-4 py-3">
            <div class="flex justify-between items-center gap-4">
                <a href="index.php" class="flex items-center gap-3 group shrink-0">
                    <img src="../assets/images/logo.png" alt="Logo" class="h-10 w-auto group-hover:scale-110 transition-transform duration-300">
                    <div class="flex flex-col"><span class="text-xl font-bold text-[var(--deep-forest)] font-logo tracking-wide leading-none">LIBRARIA</span></div>
                </a>
                <div class="hidden md:flex flex-1 max-w-xl mx-auto">
                    <form action="index.php" method="GET" class="w-full relative group">
                        <input type="text" name="s" placeholder="Cari buku..." class="w-full pl-10 pr-4 py-2 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all text-sm shadow-inner">
                        <span class="material-symbols-outlined absolute left-3 top-2 text-[var(--text-muted)] group-focus-within:text-[var(--warm-tan)] text-lg">search</span>
                    </form>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="toggleDarkMode()" class="w-10 h-10 rounded-full bg-[var(--cream-bg)] dark:bg-stone-800 text-[var(--deep-forest)] dark:text-[var(--warm-tan)] hover:bg-[var(--deep-forest)] hover:text-white transition-all flex items-center justify-center">
                        <span class="material-symbols-outlined" id="dark-mode-icon">dark_mode</span>
                    </button>

                    <div class="hidden lg:flex items-center gap-1 text-sm font-bold text-[var(--text-muted)] dark:text-stone-400 mr-2">
                        <a href="index.php" class="px-3 py-2 rounded-xl hover:bg-[var(--cream-bg)] hover:text-[var(--deep-forest)] transition-colors">Beranda</a>
                        <a href="my_orders.php" class="px-3 py-2 rounded-xl bg-[var(--deep-forest)] text-white shadow-md transition-colors">Pesanan</a>
                        <a href="chat_list.php" class="px-3 py-2 rounded-xl hover:bg-[var(--cream-bg)] hover:text-[var(--deep-forest)] transition-colors">Chat</a>
                    </div>
                    <a href="help.php" class="w-10 h-10 flex items-center justify-center rounded-full text-[var(--text-muted)] dark:text-stone-400 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] transition-all"><span class="material-symbols-outlined">help</span></a>

                    <div class="relative">
                        <button onclick="toggleDropdown('notificationDropdown')" class="w-10 h-10 flex items-center justify-center rounded-full text-[var(--text-muted)] hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] transition-all relative">
                            <span class="material-symbols-outlined">notifications</span>
                            <?php if($total_notif > 0): ?><span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white animate-ping"></span><span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white"></span><?php endif; ?>
                        </button>
                        <div id="notificationDropdown" class="absolute right-0 mt-3 w-80 bg-white rounded-2xl shadow-xl border border-[var(--border-color)] py-2 hidden transform origin-top-right transition-all z-50">
                            <div class="px-4 py-3 border-b border-[var(--border-color)] flex justify-between items-center">
                                <h4 class="font-bold text-[var(--deep-forest)] text-sm">Notifikasi</h4>
                                <?php if($total_notif > 0): ?><span class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-bold"><?= $total_notif ?> Baru</span><?php endif; ?>
                            </div>
                            <div class="max-h-64 overflow-y-auto custom-scroll">
                                <?php if(!empty($notif_list)): foreach($notif_list as $n): ?>
                                    <a href="<?= $n['link'] ?>" class="flex items-start gap-3 px-4 py-3 hover:bg-[var(--cream-bg)] transition-colors border-b border-gray-50 last:border-0">
                                        <div class="p-2 bg-<?= $n['color'] ?>-100 text-<?= $n['color'] ?>-600 rounded-full shrink-0"><span class="material-symbols-outlined text-lg"><?= $n['icon'] ?></span></div>
                                        <div><p class="text-sm font-bold text-gray-800"><?= $n['title'] ?></p><p class="text-xs text-gray-500"><?= $n['text'] ?></p></div>
                                    </a>
                                <?php endforeach; else: ?>
                                    <div class="text-center py-6 text-gray-400 text-xs italic">Tidak ada notifikasi baru.</div>
                                <?php endif; ?>
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

    <main class="flex-1 pt-32 pb-12 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto w-full">

        <div class="text-center mb-10" data-aos="fade-down">
            <h1 class="text-3xl lg:text-4xl font-bold text-[var(--deep-forest)] title-font mb-2">Riwayat Pesanan</h1>
            <p class="text-[var(--text-muted)]">Pantau status pesanan dan upload bukti pembayaran Anda di sini.</p>
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
                <div class="bg-white rounded-[2.5rem] p-6 border border-[var(--border-color)] card-shadow hover:shadow-lg transition-all relative overflow-hidden">

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

                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-dashed border-[var(--border-color)] pb-4 mb-4 gap-4">
                        <div>
                            <div class="flex items-center gap-3 mb-1">
                                <span class="material-symbols-outlined text-[var(--deep-forest)]">receipt_long</span>
                                <h3 class="font-bold text-[var(--text-dark)] text-lg tracking-wide"><?= $order['invoice_number'] ?></h3>
                            </div>
                            <p class="text-xs text-[var(--text-muted)] flex items-center gap-1">
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
                        <div class="flex gap-4 p-2 rounded-xl hover:bg-[var(--cream-bg)]/30 transition-colors">
                            <div class="w-16 h-20 bg-gray-100 rounded-lg overflow-hidden border border-[var(--border-color)] shrink-0 shadow-sm">
                                <img src="<?= $img_src ?>" class="w-full h-full object-cover">
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold text-sm text-[var(--text-dark)] line-clamp-1 mb-1"><?= $item['title'] ?></h4>
                                <p class="text-xs text-[var(--text-muted)] font-mono">Qty: <?= $item['qty'] ?> x Rp <?= number_format($item['price_at_transaction'], 0, ',', '.') ?></p>
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

                    <div class="bg-[var(--cream-bg)]/50 rounded-2xl p-4 flex flex-col md:flex-row justify-between items-center gap-4 border border-[var(--border-color)]">
                        <div class="text-left w-full md:w-auto">
                            <p class="text-xs text-[var(--text-muted)] uppercase tracking-widest font-bold">Total Belanja</p>
                            <p class="text-xl font-black text-[var(--chocolate-brown)]">Rp <?= number_format($order['total_price'], 0, ',', '.') ?></p>
                        </div>

                        <div class="flex gap-3 w-full md:w-auto flex-wrap justify-end items-center">

                            <a href="<?= $chat_link ?>" class="px-4 py-2.5 bg-blue-50 text-blue-600 font-bold rounded-xl hover:bg-blue-100 transition-all text-sm flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">chat</span> Chat Penjual
                            </a>

                            <a href="invoice.php?id=<?= $order['id'] ?>" target="_blank" class="px-5 py-2.5 bg-white border border-[var(--border-color)] text-[var(--deep-forest)] font-bold rounded-xl hover:bg-gray-50 transition-all text-sm flex items-center justify-center gap-2 shadow-sm">
                                <span class="material-symbols-outlined text-sm">print</span> Struk
                            </a>

                            <?php if($need_upload): ?>
                                <button onclick="openUploadModal(<?= $order['id'] ?>, '<?= $order['invoice_number'] ?>')" class="flex-1 md:flex-none px-6 py-2.5 bg-[var(--deep-forest)] text-white font-bold rounded-xl hover:bg-[var(--chocolate-brown)] transition-all text-sm flex items-center justify-center gap-2 shadow-md animate-pulse">
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
                                <span class="font-bold text-[var(--deep-forest)]"><?= $order['expedition_name'] ?></span> - <span class="font-mono text-stone-600"><?= $order['tracking_number'] ?></span>
                            </div>
                            <a href="<?= $tracking_link ?>" target="_blank" class="px-3 py-1 bg-white border border-yellow-200 text-yellow-700 text-xs font-bold rounded-lg hover:bg-yellow-100 flex items-center gap-1 shadow-sm">
                                Lacak <span class="material-symbols-outlined text-xs">open_in_new</span>
                            </a>
                        </div>
                    <?php endif; ?>

                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-20 bg-white rounded-[2.5rem] border-2 border-dashed border-[var(--border-color)]">
                    <span class="material-symbols-outlined text-6xl text-[var(--text-muted)] opacity-50 mb-4">shopping_cart_off</span>
                    <h3 class="text-xl font-bold text-[var(--text-dark)]">Belum ada pesanan</h3>
                    <p class="text-stone-500 mb-6">Yuk mulai belanja buku favoritmu!</p>
                    <a href="index.php" class="px-6 py-3 bg-[var(--deep-forest)] text-white font-bold rounded-xl hover:bg-[var(--chocolate-brown)] transition-all shadow-lg">Belanja Sekarang</a>
                </div>
            <?php endif; ?>

        </div>

    </main>

    <div id="uploadModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-[70]">
        <div class="modal-overlay absolute w-full h-full bg-black/50 backdrop-blur-sm" onclick="toggleModal('uploadModal')"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded-[2rem] shadow-2xl z-50 p-8 transform scale-95 transition-all">
            <h3 class="text-xl font-bold text-[var(--deep-forest)] mb-2 title-font">Upload Bukti Bayar</h3>
            <p class="text-sm text-gray-500 mb-6">Invoice: <span id="modalInvoice" class="font-bold text-[var(--chocolate-brown)]"></span></p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="order_id" id="modalOrderId">
                <div class="mb-6">
                    <label class="block text-xs font-bold text-[var(--text-muted)] uppercase mb-2">Pilih Foto / Screenshot</label>
                    <input type="file" name="payment_proof" required accept="image/*" class="w-full text-sm text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-[var(--light-sage)] file:text-[var(--deep-forest)] hover:file:bg-[var(--warm-tan)] hover:file:text-white transition-all cursor-pointer bg-gray-50 rounded-xl border border-[var(--border-color)]">
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="toggleModal('uploadModal')" class="flex-1 py-3 bg-gray-100 text-gray-600 font-bold rounded-xl hover:bg-gray-200 transition-colors">Batal</button>
                    <button type="submit" name="upload_proof" class="flex-1 py-3 bg-[var(--deep-forest)] text-white font-bold rounded-xl hover:opacity-90 transition-colors shadow-md">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="../assets/js/theme-manager.js"></script>
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
</body>
</html>
