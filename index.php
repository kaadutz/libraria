<?php
// Include konfigurasi database
include 'config/db.php';

// 1. QUERY PILIHAN KURATOR (Featured)
$query_featured = mysqli_query($conn, "SELECT books.*, 
                                       users.full_name as store_name,
                                       categories.name as category_name 
                                       FROM books 
                                       JOIN users ON books.seller_id = users.id 
                                       JOIN categories ON books.category_id = categories.id
                                       WHERE books.title = 'The Alchemist' AND books.stock > 0 
                                       LIMIT 1");

// Fallback jika buku spesifik tidak ada
if(mysqli_num_rows($query_featured) == 0) {
    $query_featured = mysqli_query($conn, "SELECT books.*, 
                                           users.full_name as store_name,
                                           categories.name as category_name 
                                           FROM books 
                                           JOIN users ON books.seller_id = users.id 
                                           JOIN categories ON books.category_id = categories.id
                                           WHERE books.stock > 0 
                                           ORDER BY books.id ASC LIMIT 1"); 
}
$featured_book = mysqli_fetch_assoc($query_featured);
$featured_id = $featured_book ? $featured_book['id'] : 0;

// 2. QUERY DAFTAR BUKU TERBARU (Katalog)
$query_latest = mysqli_query($conn, "SELECT books.*, 
                                     users.full_name as store_name,
                                     categories.name as category_name 
                                     FROM books 
                                     JOIN users ON books.seller_id = users.id 
                                     JOIN categories ON books.category_id = categories.id
                                     WHERE books.stock > 0 AND books.id != '$featured_id'
                                     ORDER BY books.id DESC 
                                     LIMIT 4");

// 3. QUERY KATEGORI (Untuk Marquee)
$query_categories = mysqli_query($conn, "SELECT DISTINCT name FROM categories ORDER BY name ASC LIMIT 10");
$kategori_data = [];
while($row = mysqli_fetch_assoc($query_categories)) {
    $kategori_data[] = $row['name'];
}
// Fallback data
if(empty($kategori_data)) {
    $kategori_data = ["Sastra Klasik", "Fiksi Ilmiah", "Filosofi", "Sejarah", "Bisnis", "Pengembangan Diri", "Seni & Desain", "Biografi"];
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
              primary: "#3a5020",       
              "primary-light": "#537330", 
              "chocolate": "#633d0c",     
              "chocolate-light": "#8a5a1b", 
              "tan": "#b08144",           
              "sand": "#e6e2dd",          
              "sage": "#d1d6a7",          
              "sage-dark": "#aeb586",     
              "cream": "#fefbe9",         
              "background-light": "#fefbe9",
              "background-dark": "#1a1c18",
            },
            fontFamily: {
              display: ["DM Serif Display", "serif"],
              sans: ["Inter", "sans-serif"],
              logo: ["Cinzel", "serif"],
            },
            boxShadow: {
                'card': '0 20px 40px -5px rgba(58, 80, 32, 0.08)', 
                'glow': '0 0 20px rgba(176, 129, 68, 0.4)',
                'paper': '2px 4px 12px rgba(99, 61, 12, 0.08)',
                'book-3d': '5px 5px 15px rgba(0,0,0,0.2), 10px 10px 25px rgba(0,0,0,0.1)',
            },
            animation: {
                'marquee': 'marquee 40s linear infinite',
                'float-slow': 'float 6s ease-in-out infinite',
            },
            keyframes: {
                marquee: {
                    '0%': { transform: 'translateX(0%)' },
                    '100%': { transform: 'translateX(-100%)' },
                },
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-15px)' },
                }
            }
          },
        },
      };
    </script>
    
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; overflow-x: hidden; }
        .font-display { font-family: 'DM Serif Display', serif; }
        .material-icons-outlined { vertical-align: middle; }
        
        .writing-vertical {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
        }
        
        /* Preloader */
        #preloader {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: #fefbe9; z-index: 9999;
            display: flex; justify-content: center; align-items: center;
            transition: opacity 0.8s ease-out, visibility 0.8s;
        }
        .dark #preloader { background-color: #1a1c18; }
        
        /* Smooth Scroll & Parallax Textures */
        .parallax-bg {
            position: fixed; top: 0; left: 0; width: 100%; height: 120%;
            pointer-events: none; z-index: 0; opacity: 0.05;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23633d0c' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            transform: translateY(0);
            will-change: transform;
        }

        /* 3D Tilt Card */
        .tilt-card {
            transform-style: preserve-3d;
            transform: perspective(1000px);
            transition: transform 0.1s ease;
        }
        .tilt-content {
            transform: translateZ(20px);
        }

        .btn-smooth { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .btn-smooth:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 10px 25px -5px rgba(99, 61, 12, 0.4); }
        
        .divider-classic { display: flex; align-items: center; gap: 1rem; opacity: 0.6; }
        .divider-classic::before { content: ''; height: 1px; width: 40px; background-color: currentColor; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-stone-800 dark:text-stone-200 transition-colors duration-500 antialiased selection:bg-tan selection:text-white relative">

    <div id="preloader">
        <div class="flex flex-col items-center justify-center gap-6">
            <img src="assets/images/logo.png" alt="Libraria Loading" class="w-24 h-auto animate-bounce drop-shadow-xl">
            <span class="font-logo text-2xl font-bold tracking-[0.2em] text-primary dark:text-sage animate-pulse">LIBRARIA</span>
        </div>
    </div>

    <div class="parallax-bg" id="parallax-texture"></div>

    <div id="sidebar-overlay" onclick="toggleMobileSidebar()" class="fixed inset-0 bg-black/60 z-[70] opacity-0 invisible backdrop-blur-sm transition-all duration-300"></div>
    <aside id="mobile-sidebar" class="fixed top-0 left-0 h-full w-72 bg-cream dark:bg-stone-900 shadow-2xl z-[80] transform -translate-x-full transition-transform duration-300 flex flex-col border-r border-tan/20 dark:border-stone-800">
        <div class="h-24 flex items-center justify-between px-6 border-b border-tan/10 dark:border-stone-800 bg-primary/5 dark:bg-stone-900/50">
            <span class="font-logo text-xl font-bold text-primary dark:text-sage tracking-widest">LIBRARIA</span>
            <button onclick="toggleMobileSidebar()" class="text-stone-500"><span class="material-icons-outlined text-2xl">close</span></button>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="#" class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary dark:text-sage font-bold rounded-xl"><span class="material-icons-outlined">home</span> Beranda</a>
            <a href="#katalog" onclick="toggleMobileSidebar()" class="flex items-center gap-3 px-4 py-3 text-stone-600 dark:text-stone-400 font-medium hover:bg-stone-100 dark:hover:bg-stone-800 rounded-xl"><span class="material-icons-outlined">menu_book</span> Katalog</a>
            <a href="auth/login.php" class="flex items-center gap-3 px-4 py-3 text-stone-600 dark:text-stone-400 font-medium hover:bg-stone-100 dark:hover:bg-stone-800 rounded-xl"><span class="material-icons-outlined">login</span> Masuk</a>
        </nav>
    </aside>

    <header id="navbar" class="fixed w-full z-[60] transition-all duration-700 py-6 border-b border-transparent">
        <nav class="max-w-7xl mx-auto px-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button onclick="toggleMobileSidebar()" class="md:hidden p-2 text-white hover:bg-white/20 rounded-lg" id="mobile-menu-btn">
                    <span class="material-icons-outlined text-2xl">menu</span>
                </button>
                <a href="index.php" class="flex items-center gap-3 group relative z-50">
                    <img src="assets/images/logo.png" alt="Logo" class="h-12 w-auto object-contain drop-shadow-md transition-transform duration-500 group-hover:scale-110">
                    <span class="font-logo text-2xl md:text-3xl tracking-[0.15em] text-white group-hover:text-tan transition-colors drop-shadow-md font-bold hidden sm:block" id="logo-text">LIBRARIA</span>
                </a>
            </div>
            <div class="flex items-center gap-3 md:gap-6 z-50">
                <button id="theme-toggle-btn" class="w-10 h-10 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-white hover:bg-cream hover:text-primary transition-all group flex items-center justify-center" onclick="toggleDarkMode()">
                    <span class="material-icons-outlined group-hover:rotate-[360deg] transition-transform duration-700" id="dark-mode-icon">dark_mode</span>
                </button>
                <a href="auth/login.php" class="hidden sm:inline-block font-medium text-white/90 hover:text-tan transition-colors" id="nav-login">Masuk</a>
                <a href="auth/register.php" class="btn-smooth px-6 py-2 bg-gradient-to-r from-chocolate to-[#8a5a1b] text-cream font-bold rounded-full shadow-lg">Daftar</a>
            </div>
        </nav>
    </header>

    <section class="relative bg-gradient-to-br from-primary via-[#2f421b] to-primary-light overflow-hidden pt-36 pb-20 lg:pt-48 lg:pb-24 min-h-[95vh] flex items-center">
        <div class="absolute top-20 right-20 w-32 h-32 bg-tan/10 rounded-full blur-3xl animate-float-slow"></div>
        <div class="absolute bottom-20 left-10 w-48 h-48 bg-sage/10 rounded-full blur-3xl animate-float-slow" style="animation-delay: 2s;"></div>

        <div class="max-w-7xl mx-auto px-6 flex flex-col lg:flex-row items-center gap-16 lg:gap-24 relative z-10 w-full">
            <div class="flex-1 text-center lg:text-left" data-aos="fade-right">
                <div class="inline-flex items-center gap-3 px-5 py-2.5 bg-sage/10 text-sage rounded-full text-xs font-bold tracking-widest mb-8 border border-sage/20 backdrop-blur-md">
                    <span class="w-2 h-2 rounded-full bg-tan animate-pulse"></span> PERPUSTAKAAN DIGITAL
                </div>
                <h1 class="font-display text-5xl md:text-7xl/tight text-cream mb-8 drop-shadow-lg">
                    Jelajahi Semesta <br/>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-tan via-sage to-tan italic pr-2">Pengetahuan</span>
                </h1>
                <p class="text-sage/80 text-lg md:text-xl mb-12 max-w-xl mx-auto lg:mx-0 leading-relaxed font-light">
                    Temukan ribuan koleksi buku langka, literatur klasik, hingga karya modern yang akan mengubah cara pandangmu.
                </p>
                
                <div class="flex flex-wrap justify-center lg:justify-start gap-8 mb-10 border-t border-white/10 pt-8">
                    <div>
                        <span class="block text-3xl font-display text-tan counter" data-target="1500">0</span>
                        <span class="text-xs text-sage/60 uppercase tracking-widest">Koleksi Buku</span>
                    </div>
                    <div>
                        <span class="block text-3xl font-display text-tan counter" data-target="350">0</span>
                        <span class="text-xs text-sage/60 uppercase tracking-widest">Penulis</span>
                    </div>
                    <div>
                        <span class="block text-3xl font-display text-tan counter" data-target="24">0</span>
                        <span class="text-xs text-sage/60 uppercase tracking-widest">Jam Layanan</span>
                    </div>
                </div>

                <div class="flex flex-wrap justify-center lg:justify-start gap-4">
                    <a href="#katalog" class="btn-smooth px-8 py-4 bg-chocolate text-cream rounded-2xl font-semibold flex items-center gap-3 border border-white/10 hover:bg-tan">
                        Mulai Membaca <span class="material-icons-outlined">menu_book</span>
                    </a>
                </div>
            </div>

            <div class="flex-1 relative w-full flex justify-center lg:justify-end" data-aos="fade-left" data-aos-delay="200">
                <div class="relative w-full max-w-md aspect-square flex items-center justify-center">
                    <div class="absolute inset-0 bg-tan/20 rounded-full blur-[90px] scale-75 animate-pulse"></div>
                    <div class="relative flex items-center justify-center gap-6 z-10">
                        
                        <div class="group w-32 h-80 bg-stone-800 rounded-r-2xl shadow-2xl transform translate-y-8 rotate-[-12deg] flex flex-col items-center justify-center border-l-4 border-tan/50 transition-all duration-500 hover:-translate-y-6 hover:rotate-[-5deg] cursor-pointer">
                            <span class="material-icons-outlined text-tan/80 text-4xl mb-4">psychology</span>
                            <span class="writing-vertical text-tan font-bold tracking-[0.2em] uppercase">Filosofi</span>
                        </div>

                        <div class="group w-40 h-96 bg-[#5c3a1e] rounded-r-3xl shadow-2xl z-20 flex flex-col items-center justify-center transform -translate-y-4 border-l-4 border-white/20 transition-all duration-500 hover:-translate-y-12 cursor-pointer">
                            <span class="material-icons-outlined text-cream/80 text-6xl mb-4">auto_stories</span>
                            <span class="writing-vertical text-cream font-bold tracking-[0.25em] uppercase">Sastra</span>
                        </div>

                        <div class="group w-32 h-80 bg-stone-300 rounded-r-2xl shadow-2xl transform translate-y-12 rotate-[12deg] flex flex-col items-center justify-center border-l-4 border-stone-400 transition-all duration-500 hover:translate-y-2 hover:rotate-[5deg] cursor-pointer">
                            <span class="material-icons-outlined text-stone-600/80 text-4xl mb-4">history_edu</span>
                            <span class="writing-vertical text-stone-600 font-bold tracking-[0.2em] uppercase">Sejarah</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="relative bg-chocolate text-cream py-6 overflow-hidden z-20 border-y-4 border-tan/20 group">
        <div class="absolute inset-y-0 left-0 w-10 md:w-32 bg-gradient-to-r from-chocolate to-transparent z-10 pointer-events-none"></div>
        <div class="absolute inset-y-0 right-0 w-10 md:w-32 bg-gradient-to-l from-chocolate to-transparent z-10 pointer-events-none"></div>

        <div class="whitespace-nowrap flex animate-marquee hover:[animation-play-state:paused]">
            <div class="flex gap-16 items-center mx-8">
                <?php foreach($kategori_data as $cat): ?>
                    <a href="#katalog" class="flex items-center gap-4 text-xl md:text-2xl font-display uppercase tracking-widest opacity-60 hover:opacity-100 hover:text-tan transition-all cursor-pointer">
                        <span class="w-1.5 h-1.5 rounded-full bg-tan/50"></span>
                        <?= $cat ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="flex gap-16 items-center mx-8">
                <?php foreach($kategori_data as $cat): ?>
                    <a href="#katalog" class="flex items-center gap-4 text-xl md:text-2xl font-display uppercase tracking-widest opacity-60 hover:opacity-100 hover:text-tan transition-all cursor-pointer">
                        <span class="w-1.5 h-1.5 rounded-full bg-tan/50"></span>
                        <?= $cat ?>
                    </a>
                <?php endforeach; ?>
            </div>
             <div class="flex gap-16 items-center mx-8">
                <?php foreach($kategori_data as $cat): ?>
                    <a href="#katalog" class="flex items-center gap-4 text-xl md:text-2xl font-display uppercase tracking-widest opacity-60 hover:opacity-100 hover:text-tan transition-all cursor-pointer">
                        <span class="w-1.5 h-1.5 rounded-full bg-tan/50"></span>
                        <?= $cat ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if($featured_book): ?>
    <section id="featured" class="max-w-7xl mx-auto px-6 py-20 relative z-20">
        <div class="text-center mb-12" data-aos="fade-up">
            <span class="text-tan font-bold tracking-widest text-xs uppercase mb-2 block">Highlight Minggu Ini</span>
            <h2 class="font-display text-4xl text-primary dark:text-cream">Pilihan Kurator</h2>
        </div>

        <div class="group relative flex flex-col md:flex-row bg-white/80 dark:bg-stone-900/90 backdrop-blur-md rounded-[3rem] p-8 md:p-12 shadow-2xl border border-tan/30 dark:border-stone-700 w-full max-w-5xl mx-auto overflow-hidden" data-aos="zoom-in">
            <div class="absolute top-0 right-0 mt-8 mr-8 z-20">
                <div class="bg-chocolate text-cream text-xs font-bold px-4 py-2 rounded-full shadow-lg flex items-center gap-2">
                    <span class="material-icons-outlined text-sm">stars</span> REKOMENDASI TERBAIK
                </div>
            </div>

            <div class="w-full md:w-5/12 mb-10 md:mb-0 relative flex items-center justify-center perspective-1000">
                <div class="relative w-4/5 aspect-[3/4] group-hover:scale-105 transition-transform duration-700 ease-out tilt-card">
                    <img src="<?= $featured_book['image'] ? 'assets/uploads/books/'.$featured_book['image'] : 'https://via.placeholder.com/300x450?text=No+Cover' ?>" alt="<?= $featured_book['title'] ?>" class="w-full h-full object-cover rounded-lg shadow-book-3d">
                    <div class="absolute inset-0 bg-gradient-to-tr from-white/20 to-transparent pointer-events-none mix-blend-overlay rounded-lg"></div>
                </div>
            </div>
            
            <div class="w-full md:w-7/12 md:pl-10 flex flex-col justify-center relative">
                <div class="divider-classic text-tan/60 w-full justify-start md:ml-0 mb-6 font-medium text-xs tracking-widest uppercase">Buku Paling Dicari</div>
                <h4 class="font-display text-4xl md:text-5xl text-primary dark:text-sage mb-6 leading-tight"><?= $featured_book['title'] ?></h4>
                <p class="text-stone-600 dark:text-stone-300 mb-8 text-lg leading-relaxed line-clamp-3 font-light"><?= $featured_book['description'] ?></p>

                <div class="grid grid-cols-2 gap-4 mb-8 p-4 bg-stone-50 dark:bg-stone-800/50 rounded-2xl border border-stone-100 dark:border-stone-700/50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-white dark:bg-stone-700 flex items-center justify-center text-primary dark:text-sage shadow-sm"><span class="material-icons-outlined text-lg">storefront</span></div>
                        <div>
                            <p class="text-[10px] text-stone-400 uppercase tracking-wide font-bold">Penjual</p>
                            <p class="text-stone-800 dark:text-stone-200 font-medium text-sm"><?= $featured_book['store_name'] ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 border-l border-stone-200 dark:border-stone-700 pl-4">
                        <div class="w-10 h-10 rounded-full bg-white dark:bg-stone-700 flex items-center justify-center text-chocolate dark:text-tan shadow-sm"><span class="material-icons-outlined text-lg">inventory_2</span></div>
                        <div>
                            <p class="text-[10px] text-stone-400 uppercase tracking-wide font-bold">Stok</p>
                            <p class="text-stone-800 dark:text-stone-200 font-medium text-sm"><?= $featured_book['stock'] ?> Unit</p>
                        </div>
                    </div>
                </div>

                <div class="mt-auto pt-6 border-t border-dashed border-tan/30 dark:border-stone-800 flex flex-wrap gap-6 justify-between items-center">
                    <div>
                        <span class="text-xs text-stone-400 font-bold uppercase tracking-wider block mb-1">Harga Spesial</span>
                        <span class="text-4xl font-display font-bold text-chocolate dark:text-tan"><span class="text-lg align-top mr-1 font-sans font-normal opacity-70">Rp</span><?= number_format($featured_book['sell_price'], 0, ',', '.') ?></span>
                    </div>
                    <a href="auth/login.php" class="btn-smooth px-8 py-4 bg-gradient-to-r from-primary to-primary-light hover:from-chocolate hover:to-chocolate-light text-white rounded-xl font-bold shadow-lg shadow-primary/20 flex items-center gap-2 group/btn">
                        <span>Beli Sekarang</span> <span class="material-icons-outlined text-lg group-hover/btn:translate-x-1 transition-transform">arrow_forward</span>
                    </a>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section id="katalog" class="bg-gradient-to-b from-background-light to-sand/50 dark:from-background-dark dark:to-black pb-32 pt-20 transition-colors duration-500 rounded-t-[3rem] border-t border-tan/10 shadow-[0_-20px_40px_rgba(0,0,0,0.02)] z-20 relative">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col md:flex-row justify-between items-end mb-16 gap-6" data-aos="fade-up">
                <div>
                    <span class="text-tan font-bold tracking-widest text-xs uppercase mb-2 block">Koleksi Kami</span>
                    <h2 class="font-display text-4xl lg:text-5xl text-primary dark:text-cream mb-2">Buku Terbaru</h2>
                    <p class="text-stone-500 dark:text-stone-400 font-light">Update bacaan terkini untuk Anda.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-8 lg:gap-10">
                <?php if(mysqli_num_rows($query_latest) > 0): ?>
                    <?php while($book = mysqli_fetch_assoc($query_latest)): ?>
                        <div class="tilt-card group flex flex-col sm:flex-row bg-white dark:bg-stone-900 rounded-3xl overflow-hidden shadow-lg border border-tan/20 dark:border-stone-800 hover:shadow-2xl hover:shadow-tan/10 transition-all duration-300 relative" data-aos="fade-up">
                            
                            <div class="sm:w-2/5 relative h-64 sm:h-auto bg-stone-100 dark:bg-stone-800 overflow-hidden tilt-content">
                                <img src="<?= $book['image'] ? 'assets/uploads/books/'.$book['image'] : 'https://via.placeholder.com/300x450?text=No+Cover' ?>" alt="<?= $book['title'] ?>" class="w-full h-full object-cover"> 
                                <div class="absolute inset-0 bg-black/10 group-hover:bg-transparent transition-colors"></div>
                                <div class="absolute top-3 left-3 bg-white/90 dark:bg-stone-900/90 backdrop-blur px-3 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider text-primary dark:text-sage shadow-sm border border-stone-100 dark:border-stone-700">
                                    <?= $book['category_name'] ?>
                                </div>
                            </div>
                            
                            <div class="sm:w-3/5 p-6 sm:p-8 flex flex-col tilt-content">
                                <h4 class="font-display text-2xl text-primary dark:text-cream mb-2 leading-tight line-clamp-2 group-hover:text-chocolate dark:group-hover:text-tan transition-colors"><?= $book['title'] ?></h4>
                                <div class="flex items-center gap-2 mb-4 text-xs text-stone-500 dark:text-stone-400">
                                    <span class="material-icons-outlined text-sm">store</span>
                                    <span><?= $book['store_name'] ?></span>
                                </div>
                                <p class="text-stone-600 dark:text-stone-400 text-sm line-clamp-2 mb-6 flex-grow font-light"><?= $book['description'] ?></p>
                                <div class="flex items-center justify-between mt-auto pt-4 border-t border-dashed border-stone-200 dark:border-stone-700">
                                    <div class="text-chocolate dark:text-tan font-bold text-xl font-sans"><span class="text-sm font-normal text-stone-400 mr-1">Rp</span><?= number_format($book['sell_price'], 0, ',', '.') ?></div>
                                    <a href="auth/login.php" class="w-10 h-10 rounded-full bg-primary/10 dark:bg-sage/10 text-primary dark:text-sage flex items-center justify-center hover:bg-primary hover:text-white transition-colors"><span class="material-icons-outlined">arrow_forward</span></a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full text-center py-20 bg-cream/30 rounded-3xl border-2 border-dashed border-tan/20">
                        <p class="text-stone-500">Belum ada buku tersedia saat ini.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-16 text-center">
                <a href="auth/login.php" class="btn-smooth inline-flex items-center gap-2 px-8 py-3 bg-white dark:bg-stone-800 border border-tan/30 dark:border-stone-700 text-chocolate dark:text-tan rounded-full font-bold shadow-md hover:bg-tan hover:text-white hover:border-tan transition-all group">
                    Lihat Semua Buku <span class="material-icons-outlined group-hover:rotate-90 transition-transform">login</span>
                </a>
            </div>
        </div>
    </section>

    <section class="py-20 bg-cream dark:bg-stone-950 border-t border-tan/10 relative z-20">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-12">
                <span class="text-tan font-bold tracking-widest text-xs uppercase mb-2 block">Suara Pembaca</span>
                <h2 class="font-display text-3xl text-primary dark:text-sage">Apa Kata Mereka?</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white dark:bg-stone-900 p-8 rounded-2xl shadow-lg border border-tan/10 dark:border-stone-800 relative" data-aos="fade-up" data-aos-delay="0">
                    <span class="material-icons-outlined text-4xl text-tan/20 absolute top-6 right-6">format_quote</span>
                    <p class="text-stone-600 dark:text-stone-400 italic mb-6">"Koleksi bukunya sangat lengkap dan kondisinya masih sangat bagus. Pengiriman cepat dan packing aman."</p>
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center font-bold text-primary">AD</div>
                        <div>
                            <h5 class="font-bold text-sm text-primary dark:text-sage">Andi Darmawan</h5>
                            <span class="text-xs text-stone-400">Pecinta Sejarah</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-stone-900 p-8 rounded-2xl shadow-lg border border-tan/10 dark:border-stone-800 relative" data-aos="fade-up" data-aos-delay="100">
                    <span class="material-icons-outlined text-4xl text-tan/20 absolute top-6 right-6">format_quote</span>
                    <p class="text-stone-600 dark:text-stone-400 italic mb-6">"Akhirnya nemu buku langka yang saya cari selama ini. Platform ini sangat membantu!"</p>
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-chocolate/20 flex items-center justify-center font-bold text-chocolate">SM</div>
                        <div>
                            <h5 class="font-bold text-sm text-primary dark:text-sage">Siti Maemunah</h5>
                            <span class="text-xs text-stone-400">Mahasiswa Sastra</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-stone-900 p-8 rounded-2xl shadow-lg border border-tan/10 dark:border-stone-800 relative" data-aos="fade-up" data-aos-delay="200">
                    <span class="material-icons-outlined text-4xl text-tan/20 absolute top-6 right-6">format_quote</span>
                    <p class="text-stone-600 dark:text-stone-400 italic mb-6">"Tampilannya estetik banget, bikin betah nyari buku. Proses transaksinya juga mudah."</p>
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-sage/40 flex items-center justify-center font-bold text-primary">BP</div>
                        <div>
                            <h5 class="font-bold text-sm text-primary dark:text-sage">Budi Prakoso</h5>
                            <span class="text-xs text-stone-400">Kolektor Buku</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-primary/5 dark:bg-stone-900 pt-20 pb-10 border-t border-tan/20 dark:border-stone-800 relative z-20">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col md:flex-row justify-between items-center gap-6 mb-10">
                <div class="flex items-center gap-3">
                    <img src="assets/images/logo.png" alt="Libraria" class="h-8 w-auto opacity-80 grayscale hover:grayscale-0 transition-all">
                    <span class="font-logo text-xl tracking-widest text-primary dark:text-sage font-bold">LIBRARIA</span>
                </div>
                <div class="text-sm text-stone-500">
                    &copy; 2025 Libraria. All rights reserved.
                </div>
            </div>
        </div>
    </footer>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ once: true, offset: 80, duration: 1000 });

        window.addEventListener('load', function() {
            const preloader = document.getElementById('preloader');
            preloader.style.opacity = '0';
            preloader.style.visibility = 'hidden';
            setTimeout(startCounters, 500);
        });

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
        const parallaxBg = document.getElementById('parallax-texture');

        window.addEventListener('scroll', () => {
            const scrollY = window.scrollY;

            if(parallaxBg) {
                parallaxBg.style.transform = `translateY(-${scrollY * 0.2}px)`;
            }

            if (scrollY > 20) {
                navbar.classList.remove('bg-transparent', 'py-6', 'border-transparent');
                navbar.classList.add('bg-cream/90', 'dark:bg-stone-900/90', 'backdrop-blur-xl', 'shadow-sm', 'py-3', 'border-tan/30', 'dark:border-stone-800');
                logoText.classList.remove('text-white');
                logoText.classList.add('text-primary', 'dark:text-sage');
                if(navLogin) { navLogin.classList.remove('text-white/90'); navLogin.classList.add('text-stone-600', 'dark:text-stone-300'); }
                if(mobileBtn) { mobileBtn.classList.remove('text-white'); mobileBtn.classList.add('text-stone-600', 'dark:text-stone-300'); }
                if(themeBtn) { themeBtn.classList.remove('text-white', 'bg-white/10', 'border-white/20'); themeBtn.classList.add('text-primary', 'dark:text-sage', 'bg-primary/5', 'dark:bg-stone-800', 'border-primary/10', 'dark:border-stone-700'); }
            } else {
                navbar.classList.remove('bg-cream/90', 'dark:bg-stone-900/90', 'backdrop-blur-xl', 'shadow-sm', 'py-3', 'border-tan/30', 'dark:border-stone-800');
                navbar.classList.add('bg-transparent', 'py-6', 'border-transparent');
                logoText.classList.remove('text-primary', 'dark:text-sage');
                logoText.classList.add('text-white');
                if(navLogin) { navLogin.classList.remove('text-stone-600', 'dark:text-stone-300'); navLogin.classList.add('text-white/90'); }
                if(mobileBtn) { mobileBtn.classList.remove('text-stone-600', 'dark:text-stone-300'); mobileBtn.classList.add('text-white'); }
                if(themeBtn) { themeBtn.classList.add('text-white', 'bg-white/10', 'border-white/20'); themeBtn.classList.remove('text-primary', 'dark:text-sage', 'bg-primary/5', 'dark:bg-stone-800', 'border-primary/10', 'dark:border-stone-700'); }
            }
        });

        function startCounters() {
            const counters = document.querySelectorAll('.counter');
            counters.forEach(counter => {
                const target = +counter.getAttribute('data-target');
                const increment = target / 50; 
                
                const updateCount = () => {
                    const count = +counter.innerText;
                    if (count < target) {
                        counter.innerText = Math.ceil(count + increment);
                        setTimeout(updateCount, 40);
                    } else {
                        counter.innerText = target + "+";
                    }
                };
                updateCount();
            });
        }

        const tiltCards = document.querySelectorAll('.tilt-card');
        tiltCards.forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = ((y - centerY) / centerY) * -5; 
                const rotateY = ((x - centerX) / centerX) * 5;

                card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
            });

            card.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0)';
            });
        });
    </script>
</body>
</html>
