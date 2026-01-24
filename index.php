<?php
// Include konfigurasi database
include 'config/db.php';

// UPDATE QUERY: Hanya ambil 1 buku spesifik
$query_books = mysqli_query($conn, "SELECT books.*, 
                                           users.full_name as store_name,
                                           categories.name as category_name 
                                    FROM books 
                                    JOIN users ON books.seller_id = users.id 
                                    JOIN categories ON books.category_id = categories.id
                                    WHERE books.title = 'The Alchemist' AND books.stock > 0 
                                    LIMIT 1");

// Fallback jika tidak ditemukan
if(mysqli_num_rows($query_books) == 0) {
    $query_books = mysqli_query($conn, "SELECT books.*, 
                                           users.full_name as store_name,
                                           categories.name as category_name 
                                    FROM books 
                                    JOIN users ON books.seller_id = users.id 
                                    JOIN categories ON books.category_id = categories.id
                                    WHERE books.stock > 0 
                                    ORDER BY books.id DESC LIMIT 1");
}
?>
<!DOCTYPE html>
<html class="light scroll-smooth" lang="id">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Libraria - Temukan Ceritamu</title>
    
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=DM+Serif+Display&family=Inter:wght@300;400;500;600;700&family=Material+Icons+Outlined&display=swap" rel="stylesheet"/>
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <script>
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              primary: "#3a5020",         // Deep Forest Green
              "primary-light": "#537330", // Lighter Green
              "chocolate": "#633d0c",     // Rich Chocolate Brown
              "chocolate-light": "#8a5a1b", // Lighter Brown
              "tan": "#b08144",           // Warm Tan
              "sand": "#e6e2dd",          // Neutral Sand
              "sage": "#d1d6a7",          // Sage Green
              "sage-dark": "#aeb586",     // Darker Sage
              "cream": "#fefbe9",         // Cream/Off-white
              "background-light": "#fefbe9",
              "background-dark": "#1a1c18",
            },
            fontFamily: {
              display: ["DM Serif Display", "serif"],
              sans: ["Inter", "sans-serif"],
              logo: ["Cinzel", "serif"],
            },
            borderRadius: {
              DEFAULT: "0.75rem",
              'large': '2rem',
              'xlarge': '3rem',
            },
            boxShadow: {
                'card': '0 20px 40px -5px rgba(58, 80, 32, 0.08)', 
                'glow': '0 0 25px rgba(176, 129, 68, 0.25)',
                'paper': '2px 4px 12px rgba(99, 61, 12, 0.1)',
            }
          },
        },
      };
    </script>
    
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'DM Serif Display', serif; }
        .material-icons-outlined { vertical-align: middle; }
        
        .writing-vertical {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
        }
        
        #mobile-sidebar { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        #sidebar-overlay { transition: opacity 0.3s ease-in-out, visibility 0.3s; }
        
        .paper-texture {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none; z-index: 50; opacity: 0.04;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
        }

        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #fefbe9; }
        ::-webkit-scrollbar-thumb { background: #d1d6a7; border-radius: 5px; border: 2px solid #fefbe9; }
        ::-webkit-scrollbar-thumb:hover { background: #b08144; }
        .dark ::-webkit-scrollbar-track { background: #1a1c18; }
        .dark ::-webkit-scrollbar-thumb { border-color: #1a1c18; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-stone-800 dark:text-stone-200 transition-colors duration-500 antialiased selection:bg-tan selection:text-white relative">

    <div class="paper-texture"></div>

    <div id="sidebar-overlay" onclick="toggleMobileSidebar()" class="fixed inset-0 bg-black/60 z-[70] opacity-0 invisible backdrop-blur-sm"></div>
    
    <aside id="mobile-sidebar" class="fixed top-0 left-0 h-full w-72 bg-cream dark:bg-stone-900 shadow-2xl z-[80] transform -translate-x-full flex flex-col border-r border-tan/20 dark:border-stone-800">
        <div class="h-24 flex items-center justify-between px-6 border-b border-tan/10 dark:border-stone-800 bg-primary/5 dark:bg-stone-900/50">
            <div class="flex items-center gap-3">
                <img src="assets/images/logo.png" alt="Logo" class="h-10 w-auto">
                <span class="font-logo text-xl font-bold text-primary dark:text-sage tracking-widest">LIBRARIA</span>
            </div>
            <button onclick="toggleMobileSidebar()" class="text-stone-500 hover:text-red-500 transition-colors">
                <span class="material-icons-outlined text-2xl">close</span>
            </button>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="#" onclick="toggleMobileSidebar()" class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary dark:text-sage font-bold rounded-xl transition-colors">
                <span class="material-icons-outlined">home</span> Beranda
            </a>
            <a href="#katalog" onclick="toggleMobileSidebar()" class="flex items-center gap-3 px-4 py-3 text-stone-600 dark:text-stone-400 font-medium hover:bg-stone-100 dark:hover:bg-stone-800 rounded-xl transition-colors">
                <span class="material-icons-outlined">menu_book</span> Katalog Buku
            </a>
            <div class="my-4 border-t border-stone-200 dark:border-stone-800"></div>
            <a href="auth/login.php" class="flex items-center gap-3 px-4 py-3 text-stone-600 dark:text-stone-400 font-medium hover:bg-stone-100 dark:hover:bg-stone-800 rounded-xl transition-colors">
                <span class="material-icons-outlined">login</span> Masuk
            </a>
            <a href="auth/register.php" class="flex items-center gap-3 px-4 py-3 text-chocolate dark:text-tan font-bold hover:bg-chocolate/10 rounded-xl transition-colors">
                <span class="material-icons-outlined">person_add</span> Daftar Akun
            </a>
        </nav>
    </aside>

    <header id="navbar" class="fixed w-full z-[60] transition-all duration-700 ease-[cubic-bezier(0.4,0,0.2,1)] py-6 border-b border-transparent">
        <nav class="max-w-7xl mx-auto px-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button onclick="toggleMobileSidebar()" class="md:hidden p-2 text-white hover:bg-white/20 rounded-lg transition-colors" id="mobile-menu-btn">
                    <span class="material-icons-outlined text-2xl">menu</span>
                </button>
                <a href="index.php" class="flex items-center gap-3 md:gap-4 group relative z-50">
                    <div class="relative">
                        <div class="absolute inset-0 bg-white/20 blur-xl rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <img src="assets/images/logo.png" alt="Logo" class="h-14 md:h-16 w-auto object-contain drop-shadow-md transition-transform duration-500 group-hover:scale-110 group-hover:rotate-2">
                    </div>
                    <span class="font-logo text-2xl md:text-3xl tracking-[0.15em] text-white group-hover:text-tan transition-colors drop-shadow-md font-bold hidden sm:block" id="logo-text">LIBRARIA</span>
                </a>
            </div>
            <div class="flex items-center gap-3 md:gap-4 z-50">
                <button id="theme-toggle-btn" class="w-10 h-10 md:w-11 md:h-11 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-white hover:bg-cream hover:text-primary hover:shadow-glow transition-all duration-300 flex items-center justify-center group" onclick="toggleDarkMode()">
                    <span class="material-icons-outlined group-hover:rotate-[360deg] transition-transform duration-700" id="dark-mode-icon">dark_mode</span>
                </button>
                <a href="auth/login.php" class="hidden sm:inline-block px-5 py-2.5 font-medium text-white/90 hover:text-tan transition-colors duration-300 tracking-wide text-sm md:text-base" id="nav-login">Masuk</a>
                <a href="auth/register.php" class="px-6 py-2.5 bg-gradient-to-r from-chocolate to-chocolate-light hover:from-tan hover:to-chocolate text-cream font-medium rounded-full shadow-lg shadow-chocolate/30 hover:shadow-glow hover:-translate-y-0.5 active:translate-y-0 transition-all duration-300 border border-white/10 tracking-wide text-sm md:text-base flex items-center gap-2">
                    Daftar <span class="material-icons-outlined text-sm">arrow_forward</span>
                </a>
            </div>
        </nav>
    </header>

    <section class="relative bg-gradient-to-br from-primary via-[#2f421b] to-primary-light overflow-hidden pt-36 pb-24 lg:pt-48 lg:pb-40">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_var(--tw-gradient-stops))] from-sage/10 via-transparent to-transparent pointer-events-none"></div>
        <div class="absolute inset-0 opacity-20 pointer-events-none bg-[url('https://www.transparenttextures.com/patterns/paper.png')] mix-blend-overlay"></div>
        
        <div class="max-w-7xl mx-auto px-6 flex flex-col lg:flex-row items-center gap-16 lg:gap-24 relative z-10">
            <div class="flex-1 text-center lg:text-left" data-aos="fade-right" data-aos-duration="1200">
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-sage/20 text-sage rounded-full text-xs md:text-sm font-bold tracking-widest mb-8 border border-sage/30 backdrop-blur-sm shadow-sm hover:bg-sage/30 transition-colors cursor-default">
                    <span class="w-2 h-2 rounded-full bg-tan animate-pulse"></span>
                    KOLEKSI MUSIM INI
                </div>
                <h1 class="font-display text-5xl md:text-6xl lg:text-7xl/tight text-cream mb-8 drop-shadow-sm">
                    Temukan Cerita <br class="hidden lg:block">
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-tan to-sage italic pr-2">Terbaikmu</span> Disini
                </h1>
                <p class="text-sage/80 text-lg md:text-xl mb-10 max-w-xl mx-auto lg:mx-0 leading-relaxed font-light">
                    Jelajahi ribuan judul pilihan dari berbagai genre. Dari sastra klasik hingga buku terlaris modern, temukan buku yang berbicara pada jiwamu.
                </p>
                <div class="flex flex-wrap justify-center lg:justify-start gap-5">
                    <a href="#katalog" class="group px-8 py-4 bg-chocolate text-cream rounded-2xl font-semibold flex items-center gap-3 hover:-translate-y-1 hover:shadow-paper transition-all duration-300 border border-white/10 hover:bg-tan">
                        Lihat Koleksi <span class="material-icons-outlined group-hover:translate-x-1 transition-transform">menu_book</span>
                    </a>
                    <button class="px-8 py-4 bg-white/5 border border-white/10 text-cream rounded-2xl font-semibold hover:bg-white/10 hover:border-white/30 transition-all duration-300 backdrop-blur-sm">
                        Kategori
                    </button>
                </div>
            </div>

            <div class="flex-1 relative w-full flex justify-center lg:justify-end" data-aos="fade-left" data-aos-duration="1200" data-aos-delay="200">
                <div class="relative w-full max-w-md aspect-square flex items-center justify-center perspective-1000">
                    <div class="absolute inset-0 bg-tan/20 rounded-full blur-[80px] scale-75 animate-pulse"></div>
                    <div class="relative flex items-center justify-center gap-4 lg:gap-6 z-10 transform hover:scale-105 transition-transform duration-700 ease-out">
                        <div class="w-24 lg:w-32 h-64 lg:h-80 bg-gradient-to-br from-sage to-sage-dark rounded-l-md rounded-r-2xl shadow-2xl transform -translate-y-6 rotate-[-5deg] flex flex-col items-center justify-between py-6 transition-all duration-500 hover:-translate-y-10 group cursor-pointer border-l-4 border-white/10 relative overflow-hidden">
                            <span class="material-icons-outlined text-primary/80 text-4xl transform rotate-0 group-hover:scale-110 transition-transform">spa</span>
                            <span class="writing-vertical text-primary/80 font-bold tracking-[0.2em] text-xs lg:text-sm uppercase mb-4">Filsafat</span>
                        </div>
                        <div class="w-28 lg:w-40 h-72 lg:h-96 bg-gradient-to-br from-tan to-chocolate-light rounded-l-md rounded-r-3xl shadow-[0_35px_60px_-15px_rgba(0,0,0,0.5)] z-20 flex flex-col items-center justify-between py-8 transform scale-110 -translate-y-2 hover:translate-y-[-15px] transition-all duration-500 cursor-pointer border-l-4 border-white/20 relative overflow-hidden ring-1 ring-white/20">
                            <span class="material-icons-outlined text-cream text-6xl drop-shadow-md">auto_stories</span>
                            <span class="writing-vertical text-cream font-bold tracking-[0.25em] text-sm lg:text-base uppercase mb-8 drop-shadow-sm">Sastra</span>
                        </div>
                        <div class="w-24 lg:w-32 h-64 lg:h-80 bg-gradient-to-br from-cream to-sand rounded-l-md rounded-r-2xl shadow-2xl transform translate-y-6 rotate-[5deg] flex flex-col items-center justify-between py-6 transition-all duration-500 hover:translate-y-2 group cursor-pointer border-l-4 border-stone-200 relative overflow-hidden">
                            <span class="material-icons-outlined text-chocolate/80 text-4xl transform rotate-0 group-hover:scale-110 transition-transform">history_edu</span>
                            <span class="writing-vertical text-chocolate/80 font-bold tracking-[0.2em] text-xs lg:text-sm uppercase mb-4">Klasik</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="max-w-7xl mx-auto px-6 py-24 relative z-20 -mt-10">
        <div class="grid md:grid-cols-3 gap-6 lg:gap-10">
            <div class="group p-8 bg-white/90 dark:bg-stone-900/90 backdrop-blur-xl rounded-3xl border border-sage/40 dark:border-stone-800 shadow-lg shadow-sage/10 hover:shadow-2xl hover:shadow-sage/20 transition-all duration-500 hover:-translate-y-2" data-aos="fade-up" data-aos-delay="0">
                <div class="w-16 h-16 bg-sage/20 dark:bg-sage/10 rounded-2xl flex items-center justify-center mb-6 text-primary group-hover:bg-primary group-hover:text-cream transition-all duration-500 group-hover:rotate-6">
                    <span class="material-icons-outlined text-3xl">auto_awesome_mosaic</span>
                </div>
                <h3 class="text-xl font-bold mb-3 text-primary dark:text-sage font-display">Koleksi Lengkap</h3>
                <p class="text-stone-600 dark:text-stone-400 leading-relaxed text-sm">Akses ribuan judul dari berbagai genre termasuk Fiksi, Akademik, dan Biografi.</p>
            </div>
            
            <div class="group p-8 bg-white/90 dark:bg-stone-900/90 backdrop-blur-xl rounded-3xl border border-tan/40 dark:border-stone-800 shadow-lg shadow-tan/10 hover:shadow-2xl hover:shadow-tan/20 transition-all duration-500 hover:-translate-y-2" data-aos="fade-up" data-aos-delay="100">
                <div class="w-16 h-16 bg-tan/20 dark:bg-tan/10 rounded-2xl flex items-center justify-center mb-6 text-chocolate group-hover:bg-chocolate group-hover:text-cream transition-all duration-500 group-hover:rotate-6">
                    <span class="material-icons-outlined text-3xl">local_shipping</span>
                </div>
                <h3 class="text-xl font-bold mb-3 text-chocolate dark:text-tan font-display">Pengiriman Cepat</h3>
                <p class="text-stone-600 dark:text-stone-400 leading-relaxed text-sm">Pengiriman ke seluruh Indonesia yang cepat dan andal dengan pelacakan real-time.</p>
            </div>
            
            <div class="group p-8 bg-white/90 dark:bg-stone-900/90 backdrop-blur-xl rounded-3xl border border-primary/30 dark:border-stone-800 shadow-lg shadow-primary/10 hover:shadow-2xl hover:shadow-primary/20 transition-all duration-500 hover:-translate-y-2" data-aos="fade-up" data-aos-delay="200">
                <div class="w-16 h-16 bg-primary/10 dark:bg-primary/20 rounded-2xl flex items-center justify-center mb-6 text-primary dark:text-sage group-hover:bg-primary group-hover:text-cream transition-all duration-500 group-hover:rotate-6">
                    <span class="material-icons-outlined text-3xl">verified_user</span>
                </div>
                <h3 class="text-xl font-bold mb-3 text-primary dark:text-sage font-display">Pembayaran Aman</h3>
                <p class="text-stone-600 dark:text-stone-400 leading-relaxed text-sm">Proses pembayaran 100% aman dengan berbagai pilihan metode untuk kenyamanan Anda.</p>
            </div>
        </div>
    </section>

    <section id="katalog" class="bg-gradient-to-b from-background-light to-sand/50 dark:from-background-dark dark:to-black pb-24 pt-10 transition-colors duration-500">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col md:flex-row justify-between items-end mb-16 gap-6 border-b border-tan/30 dark:border-stone-800 pb-8" data-aos="fade-up">
                <div>
                    <h2 class="font-display text-4xl lg:text-5xl text-primary dark:text-cream mb-4">Pilihan Kurator</h2>
                    <p class="text-stone-500 dark:text-stone-400 text-lg font-light">Buku istimewa minggu ini</p>
                </div>
                <a href="auth/login.php" class="hidden md:inline-flex items-center gap-2 text-chocolate dark:text-tan font-semibold hover:text-primary dark:hover:text-sage transition-colors group px-6 py-2 rounded-full hover:bg-tan/10 dark:hover:bg-stone-800/50">
                    Lihat Semua Buku <span class="material-icons-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </a>
            </div>

            <div class="flex justify-center">
                <?php if(mysqli_num_rows($query_books) > 0): ?>
                    <?php while($book = mysqli_fetch_assoc($query_books)): ?>
                        <div class="group relative flex flex-col md:flex-row bg-gradient-to-br from-white to-cream dark:from-stone-900 dark:to-stone-950 rounded-[3rem] p-8 md:p-12 shadow-2xl border border-tan/30 dark:border-stone-700 w-full max-w-5xl" data-aos="zoom-in">
                            
                            <div class="w-full md:w-1/3 mb-8 md:mb-0 relative">
                                <div class="aspect-[3/4] bg-stone-100 dark:bg-stone-800 rounded-3xl relative overflow-hidden flex items-center justify-center shadow-lg border border-stone-100 dark:border-stone-700">
                                    <div class="absolute top-4 left-4 bg-primary/90 dark:bg-sage/90 backdrop-blur text-cream dark:text-primary text-[10px] font-bold px-3 py-1.5 rounded-full uppercase tracking-widest shadow-sm z-20 border border-white/20">
                                        <?= $book['category_name'] ?>
                                    </div>
                                    <img src="<?= $book['image'] ? 'assets/uploads/books/'.$book['image'] : 'https://via.placeholder.com/300x450?text=No+Cover' ?>" alt="<?= $book['title'] ?>" class="h-4/5 w-auto object-contain drop-shadow-2xl transition-all duration-700 group-hover:scale-105 group-hover:-rotate-2 z-10">
                                    <div class="absolute inset-0 bg-gradient-to-tr from-tan/20 to-white/0 dark:from-stone-900 dark:to-stone-800 opacity-50"></div>
                                </div>
                            </div>
                            
                            <div class="w-full md:w-2/3 md:pl-12 flex flex-col justify-center">
                                <h4 class="font-display text-4xl md:text-5xl text-primary dark:text-sage mb-4 leading-tight group-hover:text-chocolate dark:group-hover:text-tan transition-colors duration-300">
                                    <?= $book['title'] ?>
                                </h4>
                                <p class="text-stone-600 dark:text-stone-400 mb-6 text-lg leading-relaxed line-clamp-3">
                                    <?= $book['description'] ?>
                                </p>
                                <div class="flex items-center gap-3 mb-8">
                                    <div class="w-10 h-10 rounded-full bg-sage/30 dark:bg-stone-700 flex items-center justify-center text-primary dark:text-sage">
                                        <span class="material-icons-outlined">storefront</span>
                                    </div>
                                    <div>
                                        <p class="text-xs text-stone-400 uppercase tracking-wide font-bold">Penjual</p>
                                        <p class="text-stone-800 dark:text-stone-200 font-medium"><?= $book['store_name'] ?></p>
                                    </div>
                                </div>
                                <div class="mt-auto pt-8 border-t border-dashed border-tan/30 dark:border-stone-800 flex flex-wrap gap-6 justify-between items-center">
                                    <div>
                                        <span class="text-sm text-stone-400 font-medium block mb-1">Harga Spesial</span>
                                        <span class="text-3xl font-bold text-chocolate dark:text-tan font-sans">
                                            <span class="text-lg align-top mr-1">Rp</span><?= number_format($book['sell_price'], 0, ',', '.') ?>
                                        </span>
                                    </div>
                                    <div class="flex gap-4">
                                        <a href="auth/login.php" class="px-8 py-4 bg-gradient-to-r from-primary to-primary-light hover:from-chocolate hover:to-chocolate-light text-white rounded-xl font-bold shadow-lg shadow-primary/20 transition-all duration-300 flex items-center gap-2">
                                            <span>Lihat Detail</span>
                                            <span class="material-icons-outlined text-lg">arrow_forward</span>
                                        </a>w
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full py-24 text-center bg-cream/50 dark:bg-stone-800/30 rounded-[2rem] border-2 border-dashed border-tan/30 dark:border-stone-700 w-full">
                        <div class="w-20 h-20 bg-tan/10 dark:bg-stone-700 rounded-full flex items-center justify-center mx-auto mb-6 text-tan dark:text-stone-400 animate-bounce">
                            <span class="material-icons-outlined text-4xl">menu_book</span>
                        </div>
                        <h3 class="text-2xl font-display text-stone-900 dark:text-stone-200 mb-2">Belum ada buku tersedia</h3>
                        <p class="text-stone-500 max-w-md mx-auto">Koleksi buku kami sedang dikurasi. Silakan cek kembali nanti untuk penawaran terbaru.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-16 text-center md:hidden">
                <a href="auth/login.php" class="px-8 py-4 border border-primary/30 text-primary dark:text-sage rounded-xl hover:bg-primary/5 transition-colors font-semibold inline-block w-full">
                    Lihat Semua Buku
                </a>
            </div>
        </div>
    </section>

    <footer class="bg-cream dark:bg-stone-950 pt-24 pb-12 transition-colors duration-500 border-t border-tan/30 dark:border-stone-900 relative">
        <div class="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-cream dark:bg-stone-900 p-4 rounded-full border border-tan/30 dark:border-stone-800 shadow-sm">
            <span class="material-icons-outlined text-primary dark:text-sage text-2xl">local_florist</span>
        </div>
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid md:grid-cols-4 gap-12 lg:gap-16 mb-20">
                <div class="col-span-1 md:col-span-2 lg:col-span-1">
                    <div class="flex items-center gap-3 mb-6">
                        <img src="assets/images/logo.png" alt="Libraria" class="h-10 w-auto opacity-90">
                        <span class="font-logo text-2xl tracking-widest text-primary dark:text-sage font-bold">LIBRARIA</span>
                    </div>
                    <p class="text-stone-600 dark:text-stone-400 text-sm leading-relaxed mb-8 pr-4">
                        Libraria adalah tujuan utama Anda untuk literatur berkualitas tinggi dan komunitas pembaca yang bersemangat.
                    </p>
                    <div class="flex gap-4">
                        <a href="#" class="w-10 h-10 rounded-full bg-white dark:bg-stone-900 border border-tan/30 dark:border-stone-800 flex items-center justify-center text-primary dark:text-sage hover:bg-primary hover:text-white transition-all duration-300 shadow-sm hover:-translate-y-1"><span class="material-icons-outlined text-lg">facebook</span></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-white dark:bg-stone-900 border border-tan/30 dark:border-stone-800 flex items-center justify-center text-primary dark:text-sage hover:bg-primary hover:text-white transition-all duration-300 shadow-sm hover:-translate-y-1"><span class="material-icons-outlined text-lg">alternate_email</span></a>
                    </div>
                </div>
                <div>
                    <h4 class="font-bold mb-6 text-stone-900 dark:text-white font-display text-lg">Jelajahi</h4>
                    <ul class="space-y-4 text-stone-600 dark:text-stone-400 text-sm">
                        <li><a class="hover:text-primary dark:hover:text-tan transition-colors" href="#">Buku Populer</a></li>
                        <li><a class="hover:text-primary dark:hover:text-tan transition-colors" href="#">Rilis Terbaru</a></li>
                        <li><a class="hover:text-primary dark:hover:text-tan transition-colors" href="#">Paling Laris</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold mb-6 text-stone-900 dark:text-white font-display text-lg">Perusahaan</h4>
                    <ul class="space-y-4 text-stone-600 dark:text-stone-400 text-sm">
                        <li><a class="hover:text-primary dark:hover:text-tan transition-colors" href="#">Tentang Kami</a></li>
                        <li><a class="hover:text-primary dark:hover:text-tan transition-colors" href="#">Karir</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold mb-6 text-stone-900 dark:text-white font-display text-lg">Buletin</h4>
                    <p class="text-stone-600 dark:text-stone-400 text-sm mb-6">Dapatkan update terbaru.</p>
                    <form class="relative group">
                        <input class="w-full px-5 py-4 bg-white dark:bg-stone-900 border border-tan/30 dark:border-stone-800 rounded-2xl text-sm focus:ring-2 focus:ring-primary/50 outline-none transition-all placeholder:text-stone-400 shadow-sm" placeholder="Email" type="email"/>
                        <button type="button" class="absolute right-2 top-2 bottom-2 aspect-square bg-chocolate text-white rounded-xl hover:bg-tan flex items-center justify-center"><span class="material-icons-outlined text-sm">arrow_forward</span></button>
                    </form>
                </div>
            </div>
            <div class="pt-10 border-t border-tan/30 dark:border-stone-800 flex flex-col md:flex-row items-center justify-between gap-6 text-xs text-stone-500 uppercase tracking-widest font-medium">
                <p>&copy; 2025 Libraria. Dibuat dengan Cinta.</p>
            </div>
        </div>
    </footer>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ once: true, offset: 80, duration: 1000, easing: 'ease-out-cubic' });

        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark')
            document.getElementById('dark-mode-icon').textContent = 'light_mode';
        } else {
            document.documentElement.classList.remove('dark')
            document.getElementById('dark-mode-icon').textContent = 'dark_mode';
        }

        function toggleDarkMode() {
            const html = document.documentElement;
            const icon = document.getElementById('dark-mode-icon');
            html.classList.add('transition-colors', 'duration-500');
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.theme = 'light';
                icon.textContent = 'dark_mode';
            } else {
                html.classList.add('dark');
                localStorage.theme = 'dark';
                icon.textContent = 'light_mode';
            }
        }

        function toggleMobileSidebar() {
            const sidebar = document.getElementById('mobile-sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const mobileBtn = document.getElementById('mobile-menu-btn');
            
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('opacity-0', 'invisible');
                if(mobileBtn) mobileBtn.classList.add('opacity-0');
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('opacity-0', 'invisible');
                if(mobileBtn) mobileBtn.classList.remove('opacity-0');
            }
        }

        const navbar = document.getElementById('navbar');
        const logoText = document.getElementById('logo-text');
        const navLogin = document.getElementById('nav-login');
        const themeBtn = document.getElementById('theme-toggle-btn');
        const mobileBtn = document.getElementById('mobile-menu-btn');

        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.remove('bg-transparent', 'py-6', 'border-transparent');
                navbar.classList.add('bg-cream/95', 'dark:bg-stone-900/95', 'backdrop-blur-lg', 'shadow-paper', 'py-3', 'border-tan/30', 'dark:border-stone-800');
                logoText.classList.remove('text-white');
                logoText.classList.add('text-primary', 'dark:text-sage');
                if(navLogin) { navLogin.classList.remove('text-white/90'); navLogin.classList.add('text-stone-600', 'dark:text-stone-300'); }
                if(mobileBtn) { mobileBtn.classList.remove('text-white'); mobileBtn.classList.add('text-stone-600', 'dark:text-stone-300'); }
                if(themeBtn) { themeBtn.classList.remove('text-white', 'bg-white/10', 'border-white/20'); themeBtn.classList.add('text-primary', 'dark:text-sage', 'bg-primary/5', 'dark:bg-stone-800', 'border-primary/10', 'dark:border-stone-700'); }
            } else {
                navbar.classList.remove('bg-cream/95', 'dark:bg-stone-900/95', 'backdrop-blur-lg', 'shadow-paper', 'py-3', 'border-tan/30', 'dark:border-stone-800');
                navbar.classList.add('bg-transparent', 'py-6', 'border-transparent');
                logoText.classList.remove('text-primary', 'dark:text-sage');
                logoText.classList.add('text-white');
                if(navLogin) { navLogin.classList.remove('text-stone-600', 'dark:text-stone-300'); navLogin.classList.add('text-white/90'); }
                if(mobileBtn) { mobileBtn.classList.remove('text-stone-600', 'dark:text-stone-300'); mobileBtn.classList.add('text-white'); }
                if(themeBtn) { themeBtn.classList.add('text-white', 'bg-white/10', 'border-white/20'); themeBtn.classList.remove('text-primary', 'dark:text-sage', 'bg-primary/5', 'dark:bg-stone-800', 'border-primary/10', 'dark:border-stone-700'); }
            }
        });
    </script>
</body>
</html>
