<?php
session_start();
include '../config/db.php';

header('Content-Type: application/json');

// Cek Login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    echo json_encode(['status' => 'error', 'message' => 'Silakan login.']);
    exit;
}

$buyer_id = $_SESSION['user_id'];

if (isset($_POST['action']) && isset($_POST['cart_id'])) {
    $cart_id = (int)$_POST['cart_id'];
    $action  = $_POST['action'];

    // Ambil data item saat ini untuk validasi
    $q_check = mysqli_query($conn, "SELECT c.qty, b.stock, b.sell_price FROM carts c JOIN books b ON c.book_id = b.id WHERE c.id = '$cart_id' AND c.buyer_id = '$buyer_id'");
    $item = mysqli_fetch_assoc($q_check);

    // Error handling jika item tidak ditemukan (misal double click delete)
    if (!$item) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Item tidak ditemukan atau sudah dihapus.',
            'code' => 'item_not_found' // Kode khusus untuk JS reload page
        ]);
        exit;
    }

    $current_qty = (int)$item['qty'];
    $max_stock   = (int)$item['stock'];
    $price       = (int)$item['sell_price'];
    $new_qty     = $current_qty;

    // --- LOGIKA UPDATE JUMLAH ---
    if ($action == 'increase') {
        if ($current_qty < $max_stock) {
            $new_qty++;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Stok maksimal tercapai!']);
            exit;
        }
    } elseif ($action == 'decrease') {
        $new_qty--;
    } elseif ($action == 'remove') {
        $new_qty = 0; // Set 0 agar masuk logika delete
    }

    // --- EKSEKUSI DATABASE ---
    if ($new_qty > 0) {
        // Jika jumlah masih > 0, Update data
        mysqli_query($conn, "UPDATE carts SET qty = '$new_qty' WHERE id = '$cart_id'");
        $action_result = 'updated';
    } else {
        // Jika jumlah <= 0 (dari decrease atau remove), Hapus data
        mysqli_query($conn, "DELETE FROM carts WHERE id = '$cart_id'");
        $action_result = 'removed'; 
    }

    // --- HITUNG ULANG TOTAL (REALTIME) ---
    
    // 1. Badge Navbar (Total Item)
    $q_count = mysqli_query($conn, "SELECT SUM(qty) as total FROM carts WHERE buyer_id = '$buyer_id'");
    $d_count = mysqli_fetch_assoc($q_count);
    $total_items_badge = $d_count['total'] ?? 0;

    // 2. Grand Total (Total Harga)
    $q_total = mysqli_query($conn, "SELECT SUM(c.qty * b.sell_price) as grand_total FROM carts c JOIN books b ON c.book_id = b.id WHERE c.buyer_id = '$buyer_id'");
    $d_total = mysqli_fetch_assoc($q_total);
    $grand_total = $d_total['grand_total'] ?? 0;

    // 3. Subtotal Item (Hanya relevan jika updated)
    $item_subtotal = ($new_qty > 0) ? $new_qty * $price : 0;

    echo json_encode([
        'status' => 'success',
        'action_result' => $action_result, // Mengirim status 'updated' atau 'removed'
        'new_qty' => $new_qty,
        'item_subtotal_rp' => "Rp " . number_format($item_subtotal, 0, ',', '.'),
        'grand_total_rp' => "Rp " . number_format($grand_total, 0, ',', '.'),
        'cart_badge' => $total_items_badge,
        'message' => ($action_result == 'removed') ? 'Produk dihapus dari keranjang' : 'Keranjang diperbarui'
    ]);
    exit;
}
?>
