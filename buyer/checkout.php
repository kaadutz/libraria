<?php
session_start();
include '../config/db.php';

// Cek Keamanan
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit;
}

$buyer_id = $_SESSION['user_id'];

// --- 1. AMBIL DATA USER (ALAMAT) ---
$q_user = mysqli_query($conn, "SELECT address, full_name, email, profile_image FROM users WHERE id = '$buyer_id'");
$user_data = mysqli_fetch_assoc($q_user);
$saved_address = $user_data['address'];
$profile_pic = !empty($user_data['profile_image']) ? "../assets/uploads/profiles/" . $user_data['profile_image'] : "../assets/images/default_profile.png";

// --- 2. AMBIL DATA KERANJANG DAN PECAH PER TOKO ---
// Query mengambil data bank penjual (bank_info, bank_account)
$q_cart = mysqli_query($conn, "SELECT c.*, b.title, b.image, b.sell_price, b.seller_id, u.full_name as seller_name, u.bank_info, u.bank_account FROM carts c JOIN books b ON c.book_id = b.id JOIN users u ON b.seller_id = u.id WHERE c.buyer_id = '$buyer_id'");

$sellers_data = [];
$grand_total = 0;

// Kelompokkan barang berdasarkan ID Penjual
while($row = mysqli_fetch_assoc($q_cart)) {
    $s_id = $row['seller_id'];
    $subtotal_item = $row['sell_price'] * $row['qty'];
    $grand_total += $subtotal_item;

    if (!isset($sellers_data[$s_id])) {
        $sellers_data[$s_id] = [
            'seller_name' => $row['seller_name'],
            'bank_info' => !empty($row['bank_info']) ? $row['bank_info'] : 'Bank Belum Diatur',
            'bank_account' => !empty($row['bank_account']) ? $row['bank_account'] : '-',
            'total_price' => 0,
            'items' => []
        ];
    }
    $sellers_data[$s_id]['total_price'] += $subtotal_item;
    $sellers_data[$s_id]['items'][] = $row;
}

if (empty($sellers_data)) { header("Location: cart.php"); exit; }

// --- 3. PROSES BAYAR & UPLOAD BUKTI ---
if (isset($_POST['pay_now'])) {

    // Logika Alamat Pengiriman
    $address_choice = $_POST['address_choice'];
    if ($address_choice == 'saved') {
        if (empty($saved_address)) $error = "Alamat di profil kosong. Silakan pilih 'Input Alamat Baru'.";
        else $address = mysqli_real_escape_string($conn, $saved_address);
    } else {
        $address = mysqli_real_escape_string($conn, $_POST['new_address']);
        if (empty($address)) $error = "Mohon isi alamat pengiriman baru.";
    }

    // Validasi: Pastikan SEMUA toko sudah diupload bukti transfernya
    foreach ($sellers_data as $s_id => $data) {
        if (empty($_FILES["proof_$s_id"]['name'])) {
            $error = "Mohon unggah bukti transfer untuk toko: " . $data['seller_name'];
            break;
        }
    }

    if (!isset($error)) {
        mysqli_begin_transaction($conn);
        try {
            // Proses per toko (Masing-masing toko dapat 1 Invoice/Order)
            foreach ($sellers_data as $s_id => $data) {

                // 3a. Upload Bukti Transfer Khusus Toko Ini
                $proof_name = NULL;
                $file_ext = strtolower(pathinfo($_FILES["proof_$s_id"]['name'], PATHINFO_EXTENSION));
                $proof_name = "proof_" . $buyer_id . "_" . $s_id . "_" . time() . "." . $file_ext;
                move_uploaded_file($_FILES["proof_$s_id"]['tmp_name'], "../assets/uploads/proofs/" . $proof_name);

                // 3b. Simpan Data Order (Status = waiting_approval)
                $invoice = "INV/" . date('Ymd') . "/" . strtoupper(uniqid());
                $seller_total = $data['total_price'];

                $q_order = "INSERT INTO orders (buyer_id, invoice_number, total_price, payment_proof, status, order_date)
                            VALUES ('$buyer_id', '$invoice', '$seller_total', '$proof_name', 'waiting_approval', NOW())";
                mysqli_query($conn, $q_order);
                $order_id = mysqli_insert_id($conn);

                // 3c. Simpan Detail Item & Kurangi Stok
                foreach ($data['items'] as $item) {
                    $book_id = $item['book_id'];
                    $qty = $item['qty'];
                    $price = $item['sell_price'];

                    mysqli_query($conn, "INSERT INTO order_items (order_id, seller_id, book_id, qty, price_at_transaction)
                                         VALUES ('$order_id', '$s_id', '$book_id', '$qty', '$price')");

                    // Kurangi stok buku
                    mysqli_query($conn, "UPDATE books SET stock = stock - $qty WHERE id = '$book_id'");
                }
            }

            // Hapus Keranjang setelah sukses
            mysqli_query($conn, "DELETE FROM carts WHERE buyer_id = '$buyer_id'");
            mysqli_commit($conn);

            // Redirect ke halaman pesanan saya
            echo "<script>alert('Pesanan berhasil dibuat! Menunggu konfirmasi penjual.'); window.location='my_orders.php';</script>";
            exit;

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Terjadi kesalahan sistem.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Checkout - Libraria</title>

    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <script src="../assets/js/theme-config.js"></script>
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
        body { font-family: 'Quicksand', sans-serif; }
        .font-logo { font-family: 'Cinzel', serif; }
        .card-shadow { box-shadow: 0 10px 40px -10px rgba(62, 75, 28, 0.08); }

        /* Custom File Input Styling */
        .file-input::-webkit-file-upload-button {
            @apply px-4 py-2 bg-[var(--deep-forest)] text-white text-xs font-bold rounded-lg border-none cursor-pointer hover:bg-[var(--chocolate-brown)] transition-all mr-3;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-stone-800 dark:text-stone-200 overflow-x-hidden min-h-screen transition-colors duration-300">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10" data-aos="fade-in">

        <div class="flex items-center gap-4 mb-8">
            <a href="cart.php" class="w-12 h-12 flex items-center justify-center bg-white rounded-full shadow-sm border border-[var(--border-color)] hover:bg-[var(--deep-forest)] hover:text-white transition-all duration-300">
                <span class="material-symbols-outlined">arrow_back</span>
            </a>
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-[var(--deep-forest)] title-font">Pembayaran (Checkout)</h1>
                <p class="text-sm text-[var(--text-muted)]">Mohon transfer sesuai nominal ke masing-masing rekening toko.</p>
            </div>
        </div>

        <?php if(isset($error)): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 text-sm font-bold flex items-center gap-3 border border-red-100 animate-pulse">
                <span class="material-symbols-outlined">error</span> <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="flex flex-col lg:flex-row gap-8">

            <div class="flex-1 space-y-8">

                <?php foreach($sellers_data as $s_id => $data): ?>
                <div class="bg-white rounded-[2rem] p-6 md:p-8 card-shadow border border-[var(--border-color)]">

                    <div class="flex justify-between items-center mb-6 pb-4 border-b border-dashed border-[var(--border-color)]">
                        <h2 class="font-bold text-lg text-[var(--deep-forest)] flex items-center gap-2">
                            <span class="material-symbols-outlined text-[var(--warm-tan)]">storefront</span> <?= $data['seller_name'] ?>
                        </h2>
                        <span class="bg-[var(--light-sage)]/30 text-[var(--deep-forest)] px-3 py-1 rounded-full text-xs font-bold">
                            Total: Rp <?= number_format($data['total_price'], 0, ',', '.') ?>
                        </span>
                    </div>

                    <div class="space-y-4 mb-6">
                        <?php foreach($data['items'] as $item):
                            $img = !empty($item['image']) ? "../assets/uploads/books/".$item['image'] : "../assets/images/book_placeholder.png";
                        ?>
                        <div class="flex gap-4 p-3 bg-[var(--cream-bg)]/50 rounded-xl border border-[var(--border-color)]">
                            <img src="<?= $img ?>" class="w-16 h-20 object-cover rounded-lg shadow-sm border border-[var(--border-color)]">
                            <div class="flex-1 flex flex-col justify-between py-1">
                                <h4 class="font-bold text-sm text-[var(--text-dark)] leading-snug line-clamp-1"><?= $item['title'] ?></h4>
                                <div class="flex justify-between items-end">
                                    <span class="text-xs text-[var(--text-muted)] font-medium">Qty: <?= $item['qty'] ?></span>
                                    <span class="font-bold text-[var(--chocolate-brown)] text-sm">Rp <?= number_format($item['sell_price'] * $item['qty'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="bg-green-50 p-6 rounded-2xl border border-green-200 grid md:grid-cols-2 gap-6">
                        <div>
                            <p class="text-xs font-bold uppercase text-green-800 tracking-widest mb-3">Transfer Ke Rekening:</p>
                            <div class="bg-white p-4 rounded-xl shadow-sm border border-green-100">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="material-symbols-outlined text-green-600 text-sm">account_balance</span>
                                    <span class="font-bold text-sm text-[var(--deep-forest)]"><?= $data['bank_info'] ?></span>
                                </div>
                                <p class="text-2xl font-mono tracking-wider font-bold text-green-700"><?= $data['bank_account'] ?></p>
                            </div>
                            <p class="text-xs text-green-700 font-medium mt-2 flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">info</span> Wajib transfer: <b>Rp <?= number_format($data['total_price'], 0, ',', '.') ?></b>
                            </p>
                        </div>

                        <div class="flex flex-col justify-center">
                            <label class="text-xs font-bold uppercase text-green-800 tracking-widest mb-3">Upload Bukti Transfer:</label>
                            <input type="file" name="proof_<?= $s_id ?>" accept="image/*" required class="w-full text-sm text-gray-600 bg-white border border-green-200 rounded-xl p-2 file-input shadow-sm focus:outline-none focus:border-green-500">
                            <p class="text-[10px] text-gray-500 mt-2 italic">*Hanya format JPG, JPEG, PNG. Maks 2MB.</p>
                        </div>
                    </div>

                </div>
                <?php endforeach; ?>

            </div>

            <div class="w-full lg:w-[400px] shrink-0">
                <div class="bg-white rounded-[2rem] p-8 shadow-xl border border-[var(--border-color)] sticky top-8">

                    <h3 class="font-bold text-lg mb-6 text-[var(--deep-forest)] flex items-center gap-2">
                        <span class="material-symbols-outlined text-[var(--warm-tan)]">local_shipping</span> Alamat Pengiriman
                    </h3>

                    <div class="space-y-4 mb-8">
                        <label class="relative block cursor-pointer group">
                            <input type="radio" name="address_choice" value="saved" class="peer sr-only" checked onchange="toggleAddressInput()">
                            <div class="p-4 rounded-xl border-2 border-[var(--border-color)] bg-white peer-checked:border-[var(--deep-forest)] peer-checked:bg-[var(--light-sage)]/20 transition-all">
                                <span class="font-bold text-[var(--text-dark)] flex items-center gap-2 mb-1">
                                    <span class="material-symbols-outlined text-base">home_pin</span> Alamat Tersimpan
                                </span>
                                <p class="text-sm text-[var(--text-muted)] line-clamp-3 ml-6"><?= !empty($saved_address) ? nl2br(htmlspecialchars($saved_address)) : '<span class="text-red-500 italic">Belum ada alamat di profil.</span>' ?></p>
                            </div>
                        </label>

                        <label class="relative block cursor-pointer group">
                            <input type="radio" name="address_choice" value="new" class="peer sr-only" onchange="toggleAddressInput()">
                            <div class="p-4 rounded-xl border-2 border-[var(--border-color)] bg-white peer-checked:border-[var(--deep-forest)] peer-checked:bg-[var(--light-sage)]/20 transition-all">
                                <span class="font-bold text-[var(--text-dark)] flex items-center gap-2">
                                    <span class="material-symbols-outlined text-base">edit_location_alt</span> Input Alamat Baru
                                </span>
                            </div>
                        </label>

                        <div id="newAddressContainer" class="hidden mt-2">
                            <textarea name="new_address" id="newAddressInput" class="w-full p-3 rounded-xl border-2 border-[var(--border-color)] bg-white focus:ring-[var(--deep-forest)] focus:border-[var(--deep-forest)] text-sm transition-all shadow-inner" rows="3" placeholder="Tulis alamat lengkap..."></textarea>
                        </div>
                    </div>

                    <div class="border-t border-dashed border-[var(--border-color)] pt-6 mb-6 text-center bg-[var(--cream-bg)]/50 p-4 rounded-2xl">
                        <p class="text-sm font-bold text-[var(--text-muted)] mb-1 uppercase tracking-widest">Total Belanja</p>
                        <h2 class="text-3xl font-black text-[var(--deep-forest)]">Rp <?= number_format($grand_total, 0, ',', '.') ?></h2>
                    </div>

                    <button type="submit" name="pay_now" class="w-full py-4 bg-[var(--deep-forest)] text-white font-bold rounded-2xl shadow-lg hover:bg-[var(--chocolate-brown)] hover:shadow-xl transition-all transform active:scale-95 flex justify-center items-center gap-2 text-lg">
                        <span class="material-symbols-outlined">send</span> Kirim Bukti Transfer
                    </button>

                    <p class="text-[10px] text-center text-[var(--text-muted)] mt-4 flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-xs">verified_user</span> Transaksi Aman & Terverifikasi
                    </p>

                </div>
            </div>

        </form>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="../assets/js/theme-manager.js"></script>
    <script>
        AOS.init({ once: true, duration: 800 });

        // Logic toggle alamat
        function toggleAddressInput() {
            const radios = document.getElementsByName('address_choice');
            const container = document.getElementById('newAddressContainer');
            const input = document.getElementById('newAddressInput');
            let isNew = false;

            for (const radio of radios) { if (radio.checked && radio.value === 'new') isNew = true; }

            if (isNew) { container.classList.remove('hidden'); input.required = true; input.focus(); }
            else { container.classList.add('hidden'); input.required = false; }
        }
    </script>
</body>
</html>