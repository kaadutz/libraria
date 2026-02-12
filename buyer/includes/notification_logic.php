<?php
// buyer/includes/notification_logic.php
if (!isset($conn)) {
    // Fallback if conn is not set (should not happen if included properly)
    include_once '../config/db.php';
}

$notif_list = [];
$buyer_id = $_SESSION['user_id'];

// A. Chat
$q_msg_notif = mysqli_query($conn, "SELECT m.*, u.full_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.receiver_id = '$buyer_id' AND m.is_read = 0 ORDER BY m.created_at DESC");
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

// B. Orders (Status Updates)
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

// Sort notifikasi berdasarkan waktu terbaru (Chat baru vs Status baru)
usort($notif_list, function($a, $b) {
    return $b['time'] - $a['time'];
});

$total_notif = count($notif_list);
?>