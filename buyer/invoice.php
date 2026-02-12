<?php
session_start();
include '../config/db.php';


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




if (!isset($_SESSION['user_id']) || !isset($_GET['ids'])) {
    header("Location: index.php");
    exit;
}

$buyer_name = $_SESSION['full_name'];
$order_ids = explode(',', $_GET['ids']);
$pay_amount = isset($_GET['pay']) ? (int)$_GET['pay'] : 0;

$invoice_data = [];
$grand_total_all = 0;
$invoice_numbers = [];

foreach ($order_ids as $oid) {
    $oid = (int)$oid;
    $q_ord = mysqli_query($conn, "SELECT * FROM orders WHERE id = '$oid'");
    $ord = mysqli_fetch_assoc($q_ord);

    if($ord) {
        $grand_total_all += $ord['total_price'];
        $invoice_numbers[] = $ord['invoice_number'];

        $q_items = mysqli_query($conn, "SELECT oi.*, b.title FROM order_items oi JOIN books b ON oi.book_id = b.id WHERE oi.order_id = '$oid'");
        $items = [];
        while($item = mysqli_fetch_assoc($q_items)) {
            $items[] = $item;
        }
        $ord['items'] = $items;
        $invoice_data[] = $ord;
    }
}

$kembalian = $pay_amount - $grand_total_all;
// Ambil nomor invoice pertama aja buat nama file
$main_inv_num = $invoice_numbers[0] ?? 'INV';
?>

<!DOCTYPE html>
<html lang="id">
<head>
<title>Struk Pembayaran - Libraria</title>

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

    <div id="receiptArea" class="bg-white dark:bg-stone-900 p-8 w-full max-w-sm shadow-2xl relative my-10 mx-auto">
        <div class="jagged-top"></div>

        <div class="stamp-paid">LUNAS</div>

        <div class="text-center mb-6 relative z-10">
            <div class="flex justify-center mb-2">
                <img src="../assets/images/logo.png" class="h-10 grayscale opacity-80">
            </div>
            <h1 class="text-xl font-bold tracking-widest uppercase mb-1 font-struk text-black">LIBRARIA STORE</h1>
            <p class="text-[10px] text-gray-500 font-struk uppercase">Jl. Buku Pintar No. 123, Jakarta</p>
            <p class="text-[10px] text-gray-500 font-struk uppercase">Telp: 0812-3456-7890</p>
        </div>

        <div class="border-b-2 border-dashed border-gray-300 mb-4"></div>

        <div class="mb-4 text-[10px] font-struk text-gray-600 dark:text-gray-300 relative z-10">
            <div class="flex justify-between"><span>Tgl : <?= date('d/m/Y') ?></span><span>Jam : <?= date('H:i') ?></span></div>
            <div class="flex justify-between mt-1"><span>Kasir : System</span><span>Pel : <?= substr($buyer_name, 0, 10) ?>...</span></div>
            <div class="mt-1">Ref : <?= $main_inv_num ?></div>
        </div>

        <div class="border-b-2 border-dashed border-gray-300 mb-4"></div>

        <div class="space-y-2 mb-4 font-struk text-[11px] text-black relative z-10">
            <?php foreach($invoice_data as $inv): ?>
                <?php foreach($inv['items'] as $item):
                    $sub = $item['price_at_transaction'] * $item['qty'];
                ?>
                <div>
                    <div class="font-bold mb-0.5"><?= $item['title'] ?></div>
                    <div class="flex justify-between">
                        <span><?= $item['qty'] ?> x <?= number_format($item['price_at_transaction'], 0, ',', '.') ?></span>
                        <span><?= number_format($sub, 0, ',', '.') ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>

        <div class="border-b-2 border-dashed border-gray-300 mb-4"></div>

        <div class="space-y-1 font-struk text-xs relative z-10 text-black">
            <div class="flex justify-between font-bold text-sm">
                <span>TOTAL</span>
                <span>Rp <?= number_format($grand_total_all, 0, ',', '.') ?></span>
            </div>
            <div class="flex justify-between mt-2">
                <span>TUNAI</span>
                <span>Rp <?= number_format($pay_amount, 0, ',', '.') ?></span>
            </div>
            <div class="flex justify-between">
                <span>KEMBALI</span>
                <span>Rp <?= number_format($kembalian, 0, ',', '.') ?></span>
            </div>
        </div>

        <div class="mt-8 text-center relative z-10">
            <p class="font-struk text-[10px] text-gray-500 uppercase">*** TERIMA KASIH ***</p>
            <p class="font-struk text-[10px] text-gray-500 mt-1">Barang yang sudah dibeli tidak dapat ditukar/dikembalikan</p>

            <div class="mt-4 opacity-70">
                <svg id="barcode" class="w-full h-8"></svg>
                <div class="h-8 w-3/4 mx-auto bg-[repeating-linear-gradient(90deg,black_1px,transparent_1px,transparent_3px,black_3px,black_5px)]"></div>
            </div>
        </div>

        <div class="jagged-bottom"></div>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 mt-4">
        <a href="index.php" class="px-6 py-3 bg-stone-700 text-white rounded-xl font-bold hover:bg-stone-600 transition-all text-center flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-sm">home</span> Home
        </a>
        <button onclick="window.print()" class="px-6 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition-all shadow-lg flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-sm">print</span> Print
        </button>
        <button onclick="downloadImage()" class="px-6 py-3 bg-green-600 text-white rounded-xl font-bold hover:bg-green-700 transition-all shadow-lg flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-sm">download</span> Simpan
        </button>
    </div>

    <script>
        function downloadImage() {
            const receipt = document.getElementById('receiptArea');

            // Tambah padding sementara biar hasil crop bagus
            receipt.style.margin = "0";

            html2canvas(receipt, {
                scale: 3, // High Res
                backgroundColor: null, // Transparan background luar
                useCORS: true
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'Struk_Libraria_<?= date('Ymd_His') ?>.png';
                link.href = canvas.toDataURL('image/png');
                link.click();

                // Balikin margin
                receipt.style.margin = "2.5rem auto";
            });
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