<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit; }

$buyer_id = $_SESSION['user_id'];

// --- 1. AMBIL DATA USER (ALAMAT) ---
$q_user = mysqli_query($conn, "SELECT address, full_name, email, profile_image FROM users WHERE id = '$buyer_id'");
$user_data = mysqli_fetch_assoc($q_user);
$saved_address = $user_data['address']; 
$profile_pic = !empty($user_data['profile_image']) ? "../assets/uploads/profiles/" . $user_data['profile_image'] : "../assets/images/default_profile.png";

// Ambil Data Keranjang
$q_cart = mysqli_query($conn, "SELECT c.*, b.title, b.image, b.sell_price, b.seller_id, b.stock, u.full_name as seller_name FROM carts c JOIN books b ON c.book_id = b.id JOIN users u ON b.seller_id = u.id WHERE c.buyer_id = '$buyer_id'");
$items = [];
$grand_total = 0;
$total_items = 0;

while($row = mysqli_fetch_assoc($q_cart)) {
    $items[] = $row;
    $grand_total += ($row['sell_price'] * $row['qty']);
    $total_items += $row['qty'];
}

if (empty($items)) { header("Location: cart.php"); exit; }

// --- PROSES BAYAR ---
if (isset($_POST['pay_now'])) {
    $money_input = str_replace('.', '', $_POST['money_input']); 
    
    // --- LOGIKA PEMILIHAN ALAMAT ---
    $address_choice = $_POST['address_choice']; 
    
    if ($address_choice == 'saved') {
        if (empty($saved_address)) {
            $error = "Alamat di profil kosong. Silakan pilih 'Input Alamat Baru'.";
        } else {
            $address = mysqli_real_escape_string($conn, $saved_address);
        }
    } else {
        $address = mysqli_real_escape_string($conn, $_POST['new_address']);
        if (empty($address)) $error = "Mohon isi alamat pengiriman baru.";
    }

    if (!isset($error)) {
        if ($money_input < $grand_total) {
            $error = "Uang pembayaran kurang Rp " . number_format($grand_total - $money_input, 0, ',', '.');
        } else {
            $proof_name = NULL; 

            $orders_by_seller = [];
            foreach ($items as $item) {
                $orders_by_seller[$item['seller_id']][] = $item;
            }

            $created_order_ids = [];

            mysqli_begin_transaction($conn);
            try {
                foreach ($orders_by_seller as $seller_id => $seller_items) {
                    $seller_total = 0;
                    foreach($seller_items as $si) $seller_total += ($si['sell_price'] * $si['qty']);

                    $invoice = "INV/" . date('Ymd') . "/" . strtoupper(uniqid());
                    
                    // Simpan pesanan
                    $q_order = "INSERT INTO orders (buyer_id, invoice_number, total_price, payment_proof, status, order_date) 
                                VALUES ('$buyer_id', '$invoice', '$seller_total', '$proof_name', 'waiting_approval', NOW())";
                    mysqli_query($conn, $q_order);
                    $order_id = mysqli_insert_id($conn);
                    $created_order_ids[] = $order_id;

                    foreach ($seller_items as $item) {
                        $book_id = $item['book_id'];
                        $qty = $item['qty'];
                        $price = $item['sell_price'];
                        
                        mysqli_query($conn, "INSERT INTO order_items (order_id, seller_id, book_id, qty, price_at_transaction) 
                                             VALUES ('$order_id', '$seller_id', '$book_id', '$qty', '$price')");
                        
                        mysqli_query($conn, "UPDATE books SET stock = stock - $qty WHERE id = '$book_id'");
                    }
                }

                mysqli_query($conn, "DELETE FROM carts WHERE buyer_id = '$buyer_id'");
                mysqli_commit($conn);
                
                $ids_param = implode(',', $created_order_ids);
                echo "<script>window.location='invoice.php?ids=$ids_param&pay=$money_input';</script>";
                exit;

            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Terjadi kesalahan transaksi.";
            }
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
        
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .slide-enter { animation: slideIn 0.3s ease-out forwards; }
    </style>
</head>
<body class="overflow-x-hidden min-h-screen">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10" data-aos="fade-in">
        
        <div class="flex items-center gap-4 mb-8">
            <a href="cart.php" class="w-12 h-12 flex items-center justify-center bg-white rounded-full shadow-sm border border-[var(--border-color)] hover:bg-[var(--deep-forest)] hover:text-white hover:border-[var(--deep-forest)] transition-all duration-300">
                <span class="material-symbols-outlined">arrow_back</span>
            </a>
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-[var(--deep-forest)] title-font">Konfirmasi Pembayaran</h1>
                <p class="text-sm text-[var(--text-muted)]">Periksa kembali pesanan Anda sebelum membayar.</p>
            </div>
        </div>

        <form method="POST" class="flex flex-col lg:flex-row gap-8">
            
            <div class="flex-1 space-y-6">
                
                <div class="bg-white rounded-[2.5rem] p-6 card-shadow border border-[var(--border-color)] flex items-center gap-4">
                    <img src="<?= $profile_pic ?>" class="w-14 h-14 rounded-full object-cover border-2 border-[var(--warm-tan)]">
                    <div>
                        <p class="text-xs text-[var(--text-muted)] font-bold uppercase mb-0.5">Akun Pembeli</p>
                        <h3 class="font-bold text-lg text-[var(--text-dark)] leading-tight"><?= $user_data['full_name'] ?></h3>
                        <p class="text-sm text-[var(--text-muted)]"><?= $user_data['email'] ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-[2.5rem] p-6 card-shadow border border-[var(--border-color)]">
                    <div class="flex justify-between items-center mb-6 border-b border-dashed border-[var(--border-color)] pb-4">
                        <h2 class="font-bold text-lg text-[var(--deep-forest)] flex items-center gap-2">
                            <span class="material-symbols-outlined text-[var(--warm-tan)]">shopping_bag</span> Rincian Pesanan
                        </h2>
                        <span class="bg-[var(--light-sage)]/30 text-[var(--deep-forest)] text-xs font-bold px-3 py-1 rounded-full"><?= $total_items ?> Item</span>
                    </div>
                    
                    <div class="space-y-6 max-h-[500px] overflow-y-auto pr-2 custom-scroll">
                        <?php foreach($items as $item): 
                            $img = !empty($item['image']) ? "../assets/uploads/books/".$item['image'] : "../assets/images/book_placeholder.png";
                        ?>
                        <div class="flex gap-4 group">
                            <div class="w-20 h-28 bg-[var(--cream-bg)] rounded-xl overflow-hidden shadow-sm shrink-0 border border-[var(--border-color)]">
                                <img src="<?= $img ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                            </div>
                            <div class="flex-1 flex flex-col justify-between py-1">
                                <div>
                                    <h4 class="font-bold text-base text-[var(--text-dark)] line-clamp-2 leading-snug mb-1"><?= $item['title'] ?></h4>
                                    <div class="flex items-center gap-1.5 text-xs text-[var(--text-muted)]">
                                        <span class="material-symbols-outlined text-[14px]">storefront</span>
                                        <?= $item['seller_name'] ?>
                                    </div>
                                </div>
                                <div class="flex justify-between items-end bg-[var(--cream-bg)] p-2 rounded-lg mt-2 border border-[var(--border-color)]">
                                    <span class="text-xs text-[var(--text-muted)] font-bold">Qty: <?= $item['qty'] ?></span>
                                    <span class="font-bold text-[var(--chocolate-brown)] text-sm">Rp <?= number_format($item['sell_price'] * $item['qty'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="w-full lg:w-[420px] shrink-0">
                <div class="bg-white rounded-[2.5rem] p-8 shadow-xl border border-[var(--border-color)] sticky top-8">
                    
                    <div class="text-center mb-8 bg-[var(--deep-forest)]/5 rounded-3xl py-6 border border-[var(--deep-forest)]/10">
                        <p class="text-xs font-bold uppercase text-[var(--text-muted)] tracking-widest mb-1">Total Tagihan</p>
                        <h2 class="text-4xl font-bold text-[var(--deep-forest)]">Rp <?= number_format($grand_total, 0, ',', '.') ?></h2>
                    </div>

                    <?php if(isset($error)): ?>
                        <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 text-sm font-bold flex items-center gap-3 border border-red-100 animate-pulse">
                            <span class="material-symbols-outlined">error</span>
                            <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <div class="space-y-6">
                        
                        <div>
                            <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-3 pl-1">Pengiriman Ke</label>
                            
                            <div class="space-y-3">
                                <label class="relative block cursor-pointer group">
                                    <input type="radio" name="address_choice" value="saved" class="peer sr-only" checked onchange="toggleAddressInput()">
                                    <div class="p-4 rounded-2xl border-2 border-[var(--border-color)] bg-white hover:border-[var(--warm-tan)] peer-checked:border-[var(--deep-forest)] peer-checked:bg-[var(--light-sage)]/20 transition-all">
                                        <div class="flex justify-between items-start mb-1">
                                            <span class="font-bold text-[var(--text-dark)] peer-checked:text-[var(--deep-forest)] flex items-center gap-2">
                                                <span class="material-symbols-outlined text-lg">home_pin</span> Alamat Tersimpan
                                            </span>
                                            <span class="w-5 h-5 rounded-full border-2 border-[var(--text-muted)] peer-checked:border-[var(--deep-forest)] peer-checked:bg-[var(--deep-forest)] flex items-center justify-center">
                                                <span class="w-2 h-2 rounded-full bg-white opacity-0 peer-checked:opacity-100"></span>
                                            </span>
                                        </div>
                                        <p class="text-sm text-[var(--text-muted)] leading-relaxed pl-7 border-l-2 border-[var(--border-color)] ml-1.5 mt-2">
                                            <?= !empty($saved_address) ? nl2br(htmlspecialchars($saved_address)) : '<span class="text-red-500 italic">Belum ada alamat di profil.</span>' ?>
                                        </p>
                                    </div>
                                </label>

                                <label class="relative block cursor-pointer group">
                                    <input type="radio" name="address_choice" value="new" class="peer sr-only" onchange="toggleAddressInput()">
                                    <div class="p-4 rounded-2xl border-2 border-[var(--border-color)] bg-white hover:border-[var(--warm-tan)] peer-checked:border-[var(--deep-forest)] peer-checked:bg-[var(--light-sage)]/20 transition-all">
                                        <div class="flex justify-between items-center">
                                            <span class="font-bold text-[var(--text-dark)] peer-checked:text-[var(--deep-forest)] flex items-center gap-2">
                                                <span class="material-symbols-outlined text-lg">edit_location_alt</span> Input Alamat Baru
                                            </span>
                                            <span class="w-5 h-5 rounded-full border-2 border-[var(--text-muted)] peer-checked:border-[var(--deep-forest)] peer-checked:bg-[var(--deep-forest)] flex items-center justify-center">
                                                <span class="w-2 h-2 rounded-full bg-white opacity-0 peer-checked:opacity-100"></span>
                                            </span>
                                        </div>
                                    </div>
                                </label>

                                <div id="newAddressContainer" class="hidden mt-2 relative slide-enter">
                                    <textarea name="new_address" id="newAddressInput" class="w-full p-4 rounded-2xl border-2 border-[var(--border-color)] bg-white focus:ring-[var(--deep-forest)] focus:border-[var(--deep-forest)] text-sm transition-all shadow-inner text-[var(--text-dark)]" rows="3" placeholder="Tulis alamat lengkap pengiriman (Jalan, RT/RW, Kota, Kode Pos)..."></textarea>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-3 pl-1">Pembayaran (Cash/Transfer)</label>
                            <div class="relative group">
                                <span class="material-symbols-outlined absolute left-4 top-1/2 transform -translate-y-1/2 text-[var(--deep-forest)] text-2xl">payments</span>
                                <input type="text" name="money_input" id="moneyInput" required class="w-full pl-14 pr-4 py-4 rounded-2xl border-2 border-[var(--border-color)] bg-white focus:bg-white focus:ring-[var(--deep-forest)] focus:border-[var(--deep-forest)] text-xl font-bold text-[var(--chocolate-brown)] shadow-inner transition-all placeholder:text-gray-300" placeholder="0" autocomplete="off">
                            </div>
                            
                            <div class="flex justify-between items-center mt-3 px-2 py-2 bg-[var(--cream-bg)] rounded-xl border border-[var(--border-color)]">
                                <span class="text-xs text-[var(--text-muted)] font-bold uppercase">Kembalian</span>
                                <span id="changeDisplay" class="text-sm font-bold text-[var(--text-muted)]">Rp 0</span>
                            </div>
                        </div>

                        <hr class="border-dashed border-[var(--border-color)]">

                        <button type="submit" name="pay_now" class="w-full py-4 bg-[var(--deep-forest)] text-white font-bold rounded-2xl shadow-lg hover:bg-[var(--chocolate-brown)] hover:shadow-xl transition-all transform active:scale-[0.98] flex justify-center items-center gap-2 text-lg group">
                            <span>Bayar & Cetak</span>
                            <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">print</span>
                        </button>
                        
                        <p class="text-[10px] text-center text-[var(--text-muted)] flex items-center justify-center gap-1">
                            <span class="material-symbols-outlined text-xs">verified_user</span> Transaksi Aman & Terenkripsi
                        </p>
                    </div>
                </div>
            </div>

        </form>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ once: true, duration: 800, offset: 50 });

        // --- LOGIC GANTI ALAMAT ---
        function toggleAddressInput() {
            const radios = document.getElementsByName('address_choice');
            const container = document.getElementById('newAddressContainer');
            const input = document.getElementById('newAddressInput');
            let isNew = false;

            for (const radio of radios) {
                if (radio.checked && radio.value === 'new') {
                    isNew = true;
                }
            }

            if (isNew) {
                container.classList.remove('hidden');
                input.required = true;
                input.focus();
            } else {
                container.classList.add('hidden');
                input.required = false;
            }
        }

        // --- LOGIC INPUT UANG ---
        const moneyInput = document.getElementById('moneyInput');
        const changeDisplay = document.getElementById('changeDisplay');
        const grandTotal = <?= $grand_total ?>;

        moneyInput.addEventListener('keyup', function(e){
            // Format Input (Hanya Angka)
            let val = this.value.replace(/[^,\d]/g, '').toString();
            this.value = formatRupiah(val);

            // Hitung Kembalian Live
            let nominal = parseInt(val.replace(/\./g, '')) || 0;
            let kembalian = nominal - grandTotal;

            if (kembalian >= 0) {
                changeDisplay.innerText = "Rp " + formatRupiah(kembalian.toString());
                changeDisplay.classList.remove('text-red-500', 'text-[var(--text-muted)]');
                changeDisplay.classList.add('text-green-600');
            } else {
                changeDisplay.innerText = "Kurang Rp " + formatRupiah(Math.abs(kembalian).toString());
                changeDisplay.classList.remove('text-green-600', 'text-[var(--text-muted)]');
                changeDisplay.classList.add('text-red-500');
            }
        });

        function formatRupiah(angka){
            var number_string = angka.replace(/[^,\d]/g, '').toString(),
            split   = number_string.split(','),
            sisa    = split[0].length % 3,
            rupiah  = split[0].substr(0, sisa),
            ribuan  = split[0].substr(sisa).match(/\d{3}/gi);
            if(ribuan){
                separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }
            return rupiah;
        }
    </script>
</body>
</html>