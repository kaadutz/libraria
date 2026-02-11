# Diagram Sistem Libraria

Berikut adalah visualisasi alur kerja dan data flow diagram untuk sistem Libraria.

## 1. Flowchart Sistem (Alur Pengguna)

Diagram ini menggambarkan alur perjalanan pengguna (User Journey) mulai dari Login hingga proses utama masing-masing role.

```mermaid
flowchart TD
    Start([Mulai]) --> Login[Login / Register]
    Login --> Auth{Cek Role}

    %% ADMIN FLOW
    Auth -- Admin --> AdminDashboard[Admin Dashboard]
    AdminDashboard --> ManageUsers[Kelola User]
    AdminDashboard --> ManageCategories[Kelola Kategori]
    AdminDashboard --> ViewReports[Lihat Laporan]

    %% SELLER FLOW
    Auth -- Seller --> SellerDashboard[Seller Dashboard]
    SellerDashboard --> ManageBooks[Kelola Buku]
    ManageBooks --> AddBook[Tambah Buku]
    ManageBooks --> EditBook[Edit Buku]
    ManageBooks --> DeleteBook[Hapus Buku]
    SellerDashboard --> ViewOrders[Lihat Pesanan Masuk]
    ViewOrders --> VerifyOrder{Verifikasi Pembayaran}
    VerifyOrder -- Valid --> ShipOrder[Input Resi & Kirim]
    VerifyOrder -- Tidak Valid --> Reject[Tolak Pesanan]
    ShipOrder --> FinishOrder[Selesai]

    %% BUYER FLOW
    Auth -- Buyer --> BuyerDashboard[Buyer Dashboard]
    BuyerDashboard --> Browse[Cari Buku]
    Browse --> AddCart[Tambah ke Keranjang]
    AddCart --> Checkout[Checkout]
    Checkout --> UploadPayment[Upload Bukti Transfer]
    UploadPayment --> WaitVerify[Menunggu Verifikasi Seller]
    WaitVerify --> ReceiveOrder[Terima Barang]
    ReceiveOrder --> Review[Selesai]

    %% SHARED
    AdminDashboard --> Logout([Logout])
    SellerDashboard --> Logout
    BuyerDashboard --> Logout
    Review --> Logout
    Reject --> Logout
```

## 2. Data Flow Diagram (DFD) Level 0 - Context Diagram

Diagram Konteks menggambarkan batasan sistem dan interaksi dengan entitas eksternal.

```mermaid
graph LR
    %% External Entities
    Admin[Admin]
    Seller[Seller]
    Buyer[Buyer]

    %% System
    System((Sistem Informasi\nLibraria))

    %% Flows - Admin
    Admin -->|Login Info, Data Kategori, Data User| System
    System -->|Laporan Sistem, Data User| Admin

    %% Flows - Seller
    Seller -->|Login Info, Data Buku, Update Status Pesanan, Konfirmasi Pembayaran| System
    System -->|Notifikasi Pesanan Masuk, Data Penjualan, Pesan Chat| Seller

    %% Flows - Buyer
    Buyer -->|Login Info, Order Buku, Bukti Pembayaran, Pesan Chat| System
    System -->|Info Katalog Buku, Status Pesanan, Invoice, Balasan Chat| Buyer
```

## 3. Data Flow Diagram (DFD) Level 1

Diagram ini memecah sistem menjadi proses-proses utama.

```mermaid
graph TD
    %% Entities
    Buyer[Buyer]
    Seller[Seller]
    Admin[Admin]

    %% Processes
    P1((1.0 Autentikasi))
    P2((2.0 Manajemen Buku))
    P3((3.0 Transaksi & Pesanan))
    P4((4.0 Admin & Kategori))
    P5((5.0 Chat / Pesan))

    %% Data Stores
    DB_Users[(Users)]
    DB_Books[(Books)]
    DB_Orders[(Orders)]
    DB_Chats[(Messages)]
    DB_Cats[(Categories)]

    %% Connections - Auth
    Buyer -->|Email/Pass| P1
    Seller -->|Email/Pass| P1
    Admin -->|Email/Pass| P1
    P1 -->|Validasi| DB_Users

    %% Connections - Book Mgmt
    Seller -->|Input Data Buku| P2
    P2 -->|Simpan/Update| DB_Books
    DB_Books -->|Info Stok| P2
    P2 -->|Cek Kategori| DB_Cats

    Buyer -->|Cari Buku| P2
    DB_Books -->|Detail Buku| Buyer

    %% Connections - Transaction
    Buyer -->|Checkout & Bayar| P3
    P3 -->|Simpan Order| DB_Orders
    DB_Orders -->|Status Order| Buyer
    P3 -->|Notifikasi Pesanan| Seller
    Seller -->|Update Resi/Status| P3
    P3 -->|Kurangi Stok| DB_Books

    %% Connections - Admin
    Admin -->|CRUD Kategori| P4
    P4 -->|Simpan Kategori| DB_Cats
    Admin -->|Kelola User| P4
    P4 -->|Update User| DB_Users

    %% Connections - Chat
    Buyer -->|Kirim Pesan| P5
    Seller -->|Balas Pesan| P5
    P5 -->|Simpan Chat| DB_Chats
    DB_Chats -->|Riwayat Chat| Buyer
    DB_Chats -->|Riwayat Chat| Seller
```

## 4. Data Flow Diagram (DFD) Level 2 - Proses Transaksi (3.0)

Detail dari proses Transaksi & Pesanan.

```mermaid
graph TD
    %% Entities
    Buyer[Buyer]
    Seller[Seller]

    %% Sub-Processes
    P31((3.1 Kelola Keranjang))
    P32((3.2 Checkout & Invoice))
    P33((3.3 Upload Pembayaran))
    P34((3.4 Verifikasi Pesanan))
    P35((3.5 Pengiriman & Resi))

    %% Data Stores
    DS_Cart[(Carts)]
    DS_Order[(Orders)]
    DS_OrderItems[(Order Items)]
    DS_Books[(Books)]

    %% Flow
    Buyer -->|Pilih Item| P31
    P31 -->|Simpan Item| DS_Cart

    P31 -->|Request Checkout| P32
    P32 -->|Buat Invoice| DS_Order
    DS_Cart -->|Ambil Item| P32
    P32 -->|Simpan Detail Item| DS_OrderItems

    Buyer -->|Upload Bukti Transfer| P33
    P33 -->|Update Status: Menunggu Konfirmasi| DS_Order

    DS_Order -->|Data Pesanan Baru| P34
    Seller -->|Cek Bukti Pembayaran| P34
    P34 -->|Approve/Reject| DS_Order
    P34 -->|Update Stok| DS_Books

    P34 -->|Jika Valid| P35
    Seller -->|Input Nomor Resi| P35
    P35 -->|Update Status: Dikirim| DS_Order
    DS_Order -->|Notifikasi Pengiriman| Buyer
```
