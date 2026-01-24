<?php
session_start();
include '../config/db.php';

header('Content-Type: application/json');

// 1. Cek Login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    echo json_encode(['status' => 'error', 'message' => 'Silakan login sebagai pembeli.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $buyer_id = $_SESSION['user_id'];
    $book_id  = $_POST['book_id'];
    // Jika qty tidak dikirim (misal dari halaman home), default 1
    $qty_added = isset($_POST['qty']) ? (int)$_POST['qty'] : 1;

    // --- STEP 1: AMBIL DATA STOK ASLI DARI DATABASE ---
    $query_book = mysqli_query($conn, "SELECT stock, title FROM books WHERE id = '$book_id'");
    $book_data = mysqli_fetch_assoc($query_book);
    
    if (!$book_data) {
        echo json_encode(['status' => 'error', 'message' => 'Buku tidak ditemukan!']);
        exit;
    }
    
    $real_stock = (int)$book_data['stock'];

    // --- STEP 2: CEK JUMLAH YANG SUDAH ADA DI KERANJANG USER ---
    $query_cart = mysqli_query($conn, "SELECT id, qty FROM carts WHERE buyer_id = '$buyer_id' AND book_id = '$book_id'");
    $cart_item = mysqli_fetch_assoc($query_cart);
    
    // Jika belum ada di keranjang, anggap 0
    $current_qty_in_cart = $cart_item ? (int)$cart_item['qty'] : 0;

    // --- STEP 3: HITUNG TOTAL PERMINTAAN (Yg ada di Cart + Yg mau ditambah) ---
    $total_request = $current_qty_in_cart + $qty_added;

    // --- STEP 4: VALIDASI STOK ---
    if ($total_request > $real_stock) {
        // Hitung sisa kuota yang boleh diambil user ini
        $sisa_kuota = $real_stock - $current_qty_in_cart;

        if ($sisa_kuota <= 0) {
            $msg = "Stok penuh! Anda sudah memiliki {$current_qty_in_cart} buku ini di keranjang (Maks: {$real_stock}).";
        } else {
            $msg = "Stok tidak cukup! Anda sudah punya {$current_qty_in_cart}, hanya bisa tambah {$sisa_kuota} lagi.";
        }

        echo json_encode(['status' => 'error', 'message' => $msg]);
        exit;
    }

    // --- STEP 5: EKSEKUSI DATABASE ---
    if ($cart_item) {
        // Jika sudah ada, UPDATE jumlahnya
        $update_query = "UPDATE carts SET qty = '$total_request' WHERE id = '{$cart_item['id']}'";
        mysqli_query($conn, $update_query);
    } else {
        // Jika belum ada, INSERT baru
        $insert_query = "INSERT INTO carts (buyer_id, book_id, qty) VALUES ('$buyer_id', '$book_id', '$qty_added')";
        mysqli_query($conn, $insert_query);
    }

    // --- STEP 6: RETURN TOTAL ITEM TERBARU (Untuk Badge Navbar) ---
    $query_count = mysqli_query($conn, "SELECT SUM(qty) as total FROM carts WHERE buyer_id = '$buyer_id'");
    $data_count = mysqli_fetch_assoc($query_count);
    $total_items = $data_count['total'] ?? 0;

    echo json_encode([
        'status' => 'success', 
        'message' => 'Berhasil masuk keranjang!',
        'new_count' => $total_items
    ]);
}
?>