<?php
session_start();

// 1. Hapus semua variabel sesi
$_SESSION = [];

// 2. Hapus sesi dari memori
session_unset();

// 3. Hancurkan sesi sepenuhnya
session_destroy();

// 4. Redirect kembali ke halaman login
header("Location: ../index.php");
exit;
?>
