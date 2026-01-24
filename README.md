<p align="center">
  <img src="assets/images/logo.png" alt="Libraria Logo" width="200">
</p>

<h1 align="center">Libraria - Marketplace Buku Online</h1>

<p align="center">
  <strong>Platform jual beli buku modern yang menghubungkan Penjual dan Pembeli dengan fitur lengkap.</strong>
</p>

<p align="center">
  <a href="#fitur-utama">Fitur</a> •
  <a href="#teknologi">Teknologi</a> •
  <a href="#instalasi">Instalasi</a> •
  <a href="#struktur-folder">Struktur Folder</a>
</p>

---

## 📖 Tentang Projek

**Libraria** adalah aplikasi web marketplace berbasis PHP Native yang dirancang khusus untuk transaksi jual beli buku. Aplikasi ini memiliki tiga hak akses (role) berbeda: **Admin**, **Seller (Penjual)**, dan **Buyer (Pembeli)**.

Dibangun dengan antarmuka modern menggunakan **Tailwind CSS**, Libraria menawarkan pengalaman pengguna yang responsif dan interaktif, lengkap dengan fitur chat real-time, manajemen pesanan, hingga pencetakan struk otomatis.

## 🚀 Fitur Utama

### 🛍️ Modul Pembeli (Buyer)
* **Katalog Buku:** Pencarian buku berdasarkan judul, penulis, atau kategori.
* **Keranjang Belanja:** Menambah, mengubah, dan menghapus item belanja.
* **Checkout & Pembayaran:** Input alamat pengiriman dan nominal pembayaran (Simulasi POS).
* **Invoice Otomatis:** Pembuatan struk belanja digital yang bisa di-download sebagai gambar atau dicetak.
* **Manajemen Pesanan:**
    * Upload bukti pembayaran.
    * Lacak status pesanan (Pending, Dikemas, Dikirim, Selesai).
    * Tracking Resi Ekspedisi (JNE, J&T, Shopee Express) dengan link langsung.
    * **Fitur Refund:** Mengajukan pengembalian dana jika pesanan ditolak.
* **Live Chat:** Berkirim pesan langsung dengan penjual.

### 🏪 Modul Penjual (Seller)
* **Dashboard Statistik:** Grafik penjualan, total pendapatan, dan pesanan baru.
* **Manajemen Produk:** Tambah, edit, dan hapus buku beserta stok dan gambar.
* **Manajemen Pesanan:**
    * Terima/Tolak pesanan (Stok otomatis kembali jika ditolak).
    * Input Resi Pengiriman.
* **Laporan:** Export laporan penjualan ke PDF.
* **Live Chat:** Membalas pesan dari pembeli.

### 🛡️ Modul Admin (Superadmin)
* **Dashboard Utama:** Monitoring seluruh aktivitas user dan total buku.
* **Manajemen User:** Mengelola akun Penjual dan Pembeli.
* **Manajemen Kategori:** Membuat, mengedit, dan menghapus kategori buku.

## 💻 Teknologi yang Digunakan

* **Backend:** PHP (Native/Procedural)
* **Database:** MySQL
* **Frontend:** HTML5, CSS3
* **Styling:** Tailwind CSS (via CDN)
* **Font:** Google Fonts (Quicksand & Cinzel)
* **JavaScript Libraries:**
    * *Chart.js* (untuk grafik statistik)
    * *AOS - Animate On Scroll* (untuk animasi UI)
    * *html2canvas* (untuk download invoice menjadi gambar)

## 🛠️ Instalasi & Cara Menjalankan

Ikuti langkah berikut untuk menjalankan projek ini di komputer lokal (Localhost):

1.  **Persyaratan Sistem:**
    * Web Server (XAMPP, Laragon, atau MAMP).
    * PHP versi 7.4 atau 8.x.
    * Browser modern (Chrome/Firefox/Edge).

2.  **Setup Database:**
    * Buka phpMyAdmin (`http://localhost/phpmyadmin`).
    * Buat database baru dengan nama `libraria`.
    * Import file database (SQL) ke dalam database tersebut (Pastikan kamu sudah mengekspor tabel `users`, `books`, `categories`, `orders`, `order_items`, `carts`, `messages`).

3.  **Konfigurasi Koneksi:**
    * Buka file `config/db.php`.
    * Pastikan pengaturan koneksi sesuai dengan server lokal kamu:
        ```php
        $conn = mysqli_connect("localhost", "root", "", "libraria");
        ```

4.  **Jalankan Proyek:**
    * Pindahkan folder `libraria` ke dalam folder `htdocs` (jika pakai XAMPP) atau `www` (jika pakai Laragon).
    * Buka browser dan akses: `http://localhost/libraria`.

## 📂 Struktur Folder

```text
libraria/
├── admin/              # Halaman khusus Admin (Manage Users, Categories)
├── assets/             # Gambar statis, upload user, logo
│   ├── images/         # Logo.png dan aset desain
│   └── uploads/        # Buku, Profil, Bukti Bayar (dinamis)
├── auth/               # Login, Register, Logout
├── buyer/              # Halaman Pembeli (Cart, Checkout, Invoice)
├── config/             # Koneksi Database
├── seller/             # Halaman Penjual (Produk, Pesanan, Laporan)
└── index.php           # Landing Page utama
