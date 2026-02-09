# Flowchart Projek Libraria

Berikut adalah alur kerja sistem (flowchart) untuk aplikasi marketplace buku **Libraria**. Diagram ini menggambarkan interaksi pengguna mulai dari halaman utama, proses autentikasi, hingga fitur-fitur yang tersedia berdasarkan peran (Admin, Seller, Buyer).

```mermaid
graph TD
    Start([Mulai]) --> LandingPage[Halaman Utama / Landing Page]

    LandingPage -->|Lihat Katalog| PublicCatalog[Katalog Buku Publik]
    LandingPage -->|Login| LoginPage[Halaman Login]
    LandingPage -->|Daftar| RegisterPage[Halaman Registrasi]

    %% Proses Registrasi
    RegisterPage --> InputData[Input Data Diri]
    InputData -->|Validasi| CheckDuplicate{Cek Email/NIK}
    CheckDuplicate -- Duplikat --> ShowError[Tampilkan Error]
    ShowError --> RegisterPage
    CheckDuplicate -- Valid --> UploadPhoto[Upload Foto Profil Opsional]
    UploadPhoto --> SaveUser[Simpan Data User ke DB]
    SaveUser --> RedirectLogin[Redirect ke Halaman Login]

    %% Proses Login
    LoginPage --> InputLogin[Input Email & Password]
    InputLogin --> CheckAuth{Validasi Kredensial}
    CheckAuth -- Salah --> ShowLoginError[Tampilkan Error Login]
    ShowLoginError --> LoginPage
    CheckAuth -- Benar --> CheckRole{Cek Role User}

    %% Role Routing
    CheckRole -- Admin --> AdminDashboard[Dashboard Admin]
    CheckRole -- Seller --> SellerDashboard[Dashboard Penjual]
    CheckRole -- Buyer --> BuyerDashboard[Dashboard Pembeli]

    %% Fitur Admin
    subgraph Admin_Area [Panel Admin]
        AdminDashboard --> ManageUsers[Manajemen User]
        AdminDashboard --> ManageCategories[Manajemen Kategori Buku]
        AdminDashboard --> MonitorActivity[Monitoring Aktivitas]
    end

    %% Fitur Seller
    subgraph Seller_Area [Panel Penjual]
        SellerDashboard --> ViewStats[Statistik Penjual]
        SellerDashboard --> ManageProducts[Manajemen Produk / Buku]
        SellerDashboard --> ManageOrders_Seller[Manajemen Pesanan Masuk]
        SellerDashboard --> ReportSales[Laporan Penjual]
        SellerDashboard --> ChatSeller[Chat dengan Pembeli]

        ManageProducts --> AddEditDelete[Tambah/Edit/Hapus Buku]
        ManageOrders_Seller --> ProcessOrder[Terima/Tolak & Input Resi]
    end

    %% Fitur Buyer
    subgraph Buyer_Area [Panel Pembeli]
        BuyerDashboard --> BrowseCatalog[Jelajah Katalog]
        BrowseCatalog --> ViewDetail[Lihat Detail Buku]
        ViewDetail --> AddToCart[Tambah ke Keranjang]
        AddToCart --> CheckoutPage[Halaman Checkout]

        CheckoutPage --> PaymentProcess[Proses Pembayaran & Upload Bukti]
        PaymentProcess --> OrderHistory[Riwayat Pesanan]

        OrderHistory --> TrackOrder[Lacak Status & Resi]
        OrderHistory --> Invoice[Cetak Invoice]
        OrderHistory --> RequestRefund[Ajukan Refund jika Ditolak]

        BuyerDashboard --> ChatBuyer[Chat dengan Penjual]
    end

    %% Logout
    Admin_Area --> Logout([Keluar])
    Seller_Area --> Logout
    Buyer_Area --> Logout
    Logout --> Start
```
