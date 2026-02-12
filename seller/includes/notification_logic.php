<?php
// seller/includes/notification_logic.php
if (!isset($conn)) {
    include_once '../config/db.php';
}

$notif_list = [];
$seller_id = $_SESSION['user_id'];

// A. New Orders (Pending/Waiting) - Grouped by Order ID
$q_new_orders = mysqli_query($conn, "
    SELECT o.invoice_number, u.full_name, o.created_at
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN users u ON o.buyer_id = u.id
    WHERE oi.seller_id = '$seller_id' AND (o.status = 'pending' OR o.status = 'waiting_approval')
    GROUP BY o.id
    ORDER BY o.created_at DESC LIMIT 5
");

if($q_new_orders){
    while($ord = mysqli_fetch_assoc($q_new_orders)){
        $notif_list[] = [
            'type' => 'order',
            'title' => 'Pesanan Baru',
            'text' => $ord['invoice_number'] . ' dari ' . explode(' ', $ord['full_name'])[0],
            'icon' => 'shopping_bag',
            'color' => 'orange',
            'link' => 'orders.php',
            'time' => strtotime($ord['created_at'])
        ];
    }
}

// B. Unread Chat - Grouped by Sender
$q_unread_chat = mysqli_query($conn, "
    SELECT m.*, u.full_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.receiver_id = '$seller_id' AND m.is_read = 0
    GROUP BY m.sender_id
    ORDER BY m.created_at DESC
");

if($q_unread_chat){
    while($chat = mysqli_fetch_assoc($q_unread_chat)){
        $notif_list[] = [
            'type' => 'chat',
            'title' => 'Pesan dari ' . explode(' ', $chat['full_name'])[0],
            'text' => substr($chat['message'], 0, 25) . '...',
            'icon' => 'chat',
            'color' => 'blue',
            'link' => 'chat.php?uid=' . $chat['sender_id'],
            'time' => strtotime($chat['created_at'])
        ];
    }
}

// Sort notifikasi berdasarkan waktu terbaru
usort($notif_list, function($a, $b) {
    return $b['time'] - $a['time'];
});

$total_notif = count($notif_list);
?>