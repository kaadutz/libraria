<?php
session_start();
include '../config/db.php';

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
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Struk Pembayaran - Libraria</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&family=Quicksand:wght@700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

    <style>
        .font-struk { font-family: 'Courier Prime', monospace; }
        .font-ui { font-family: 'Quicksand', sans-serif; }

        /* Efek Kertas Sobek (Jagged Edge) */
        .jagged-top {
            background: linear-gradient(135deg, transparent 10px, #ffffff 0) top left,
                        linear-gradient(225deg, transparent 10px, #ffffff 0) top right;
            background-size: 20px 20px;
            background-repeat: repeat-x;
            height: 20px;
            width: 100%;
            position: absolute;
            top: -10px;
            left: 0;
        }
        .jagged-bottom {
            background: linear-gradient(45deg, transparent 10px, #ffffff 0) bottom left,
                        linear-gradient(315deg, transparent 10px, #ffffff 0) bottom right;
            background-size: 20px 20px;
            background-repeat: repeat-x;
            height: 20px;
            width: 100%;
            position: absolute;
            bottom: -10px;
            left: 0;
        }

        /* Stempel Lunas */
        .stamp-paid {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-15deg);
            border: 4px solid #16a34a; /* Green 600 */
            color: #16a34a;
            font-family: 'Courier Prime', monospace;
            font-size: 4rem;
            font-weight: bold;
            padding: 0.5rem 1rem;
            text-transform: uppercase;
            border-radius: 10px;
            opacity: 0.2;
            pointer-events: none;
            z-index: 0;
            mask-image: url('https://s3-us-west-2.amazonaws.com/s.cdpn.io/8399/grunge.png');
            mask-size: 944px 604px;
            mix-blend-mode: multiply;
        }
    </style>
<script src="../assets/js/theme-manager.js"></script>
</head>
<body class="bg-stone-800 min-h-screen flex flex-col items-center justify-center p-6 font-ui">

<div class="absolute top-4 right-4 z-50 print:hidden">
    <button onclick="toggleDarkMode()" class="w-10 h-10 rounded-full bg-white/10 border border-stone-200 dark:border-stone-700 text-stone-500 dark:text-stone-400 hover:text-primary hover:bg-primary/10 transition-all flex items-center justify-center group shadow-lg backdrop-blur-sm" title="Toggle Dark Mode">
        <span class="material-icons-outlined group-hover:rotate-180 transition-transform duration-500" id="dark-mode-icon">dark_mode</span>
    </button>
</div>


    <div id="receiptArea" class="bg-white p-8 w-full max-w-sm shadow-2xl relative my-10 mx-auto">
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

        <div class="mb-4 text-[10px] font-struk text-gray-600 relative z-10">
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
</body>
</html>