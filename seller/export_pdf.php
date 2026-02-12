<?php
// Pastikan path vendor autoloader benar (asumsi folder seller ada di dalam root project)
require_once __DIR__ . '/../vendor/autoload.php'; 
include '../config/db.php';

session_start();

// Cek Keamanan
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller') {
    header("Location: ../auth/login.php");
    exit;
}

$seller_id = $_SESSION['user_id'];
$seller_name = $_SESSION['full_name'];

// Ambil Filter Tanggal dari URL
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// --- QUERY DATA (Sama dengan reports.php) ---
$query_report = "
    SELECT 
        o.invoice_number,
        o.order_date,
        u.full_name AS buyer_name,
        b.title AS book_title,
        oi.qty,
        oi.price_at_transaction,
        (oi.qty * oi.price_at_transaction) AS subtotal
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN books b ON oi.book_id = b.id
    JOIN users u ON o.buyer_id = u.id
    WHERE oi.seller_id = '$seller_id' 
    AND o.status = 'finished'
    AND DATE(o.order_date) BETWEEN '$start_date' AND '$end_date'
    ORDER BY o.order_date DESC
";

$result = mysqli_query($conn, $query_report);

// Hitung Total
$total_revenue = 0;
$data_rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    $total_revenue += $row['subtotal'];
    $data_rows[] = $row;
}

// --- MULAI MEMBUAT PDF ---
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8', 
    'format' => 'A4', 
    'orientation' => 'P' // P = Portrait, L = Landscape
]);

// CSS Khusus untuk PDF (Karena mPDF tidak support Tailwind full)
$css = '
    body { font-family: sans-serif; font-size: 10pt; }
    .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
    .header h1 { margin: 0; color: #3E4B1C; font-size: 18pt; text-transform: uppercase; }
    .header p { margin: 2px 0; color: #666; }
    
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { background-color: #3E4B1C; color: #fff; padding: 10px; font-size: 9pt; text-transform: uppercase; }
    td { border-bottom: 1px solid #ddd; padding: 8px; font-size: 9pt; vertical-align: top; }
    
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-bold { font-weight: bold; }
    .grand-total { background-color: #f5f5f5; font-weight: bold; font-size: 11pt; }
    
    .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 8pt; color: #888; border-top: 1px solid #eee; padding-top: 10px; }
';

// Konten HTML
$html = '
    <div class="header">
        <h1>LIBRARIA BOOKSTORE</h1>
        <p>Laporan Penjualan Toko: <strong>' . htmlspecialchars($seller_name) . '</strong></p>
        <p>Periode: ' . date('d/m/Y', strtotime($start_date)) . ' s/d ' . date('d/m/Y', strtotime($end_date)) . '</p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Tanggal</th>
                <th width="15%">Invoice</th>
                <th width="30%">Produk</th>
                <th width="5%">Qty</th>
                <th width="15%" class="text-right">Harga</th>
                <th width="15%" class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>';

if (count($data_rows) > 0) {
    $no = 1;
    foreach ($data_rows as $row) {
        $html .= '
            <tr>
                <td class="text-center">' . $no++ . '</td>
                <td>' . date('d/m/Y', strtotime($row['order_date'])) . '</td>
                <td>' . $row['invoice_number'] . '<br><small style="color:#888">' . htmlspecialchars($row['buyer_name']) . '</small></td>
                <td>' . htmlspecialchars($row['book_title']) . '</td>
                <td class="text-center">' . $row['qty'] . '</td>
                <td class="text-right">Rp ' . number_format($row['price_at_transaction'], 0, ',', '.') . '</td>
                <td class="text-right">Rp ' . number_format($row['subtotal'], 0, ',', '.') . '</td>
            </tr>';
    }
    // Baris Total
    $html .= '
        <tr>
            <td colspan="6" class="text-right grand-total">TOTAL PENDAPATAN</td>
            <td class="text-right grand-total">Rp ' . number_format($total_revenue, 0, ',', '.') . '</td>
        </tr>';
} else {
    $html .= '
        <tr>
            <td colspan="7" class="text-center" style="padding: 20px;">Tidak ada data penjualan pada periode ini.</td>
        </tr>';
}

$html .= '
        </tbody>
    </table>

    <div class="footer">
        Dicetak pada: ' . date('d F Y H:i') . ' oleh ' . $seller_name . ' | Sistem Laporan Libraria
    </div>
';

// Generate PDF
$mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
$mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

// Output ke browser (D: Download, I: Inline View)
$filename = 'Laporan_Penjualan_' . date('Ymd') . '.pdf';
$mpdf->Output($filename, 'I'); 
?>
