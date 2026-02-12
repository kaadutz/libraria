<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../config/db.php';

// Cek Keamanan: Harus Login & Harus Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'];

// --- CRUD KATEGORI ---

// Tambah Kategori
if (isset($_POST['add_category'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);

    $check = mysqli_query($conn, "SELECT id FROM categories WHERE name = '$name'");
    if (mysqli_num_rows($check) > 0) {
        $alert = "<script>alert('Gagal: Kategori sudah ada!');</script>";
    } else {
        mysqli_query($conn, "INSERT INTO categories (name) VALUES ('$name')");
        header("Location: categories.php");
        exit;
    }
}

// Edit Kategori
if (isset($_POST['edit_category'])) {
    $id = $_POST['id'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);

    $check = mysqli_query($conn, "SELECT id FROM categories WHERE name = '$name' AND id != '$id'");
    if (mysqli_num_rows($check) > 0) {
        $alert = "<script>alert('Gagal: Nama kategori sudah digunakan!');</script>";
    } else {
        mysqli_query($conn, "UPDATE categories SET name='$name' WHERE id='$id'");
        header("Location: categories.php");
        exit;
    }
}

// Hapus Kategori
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    // Cek apakah ada buku dengan kategori ini
    $check_books = mysqli_query($conn, "SELECT id FROM books WHERE category_id = '$id'");
    if (mysqli_num_rows($check_books) > 0) {
        $alert = "<script>alert('Gagal: Kategori tidak bisa dihapus karena masih digunakan oleh buku!'); window.location='categories.php';</script>";
    } else {
        mysqli_query($conn, "DELETE FROM categories WHERE id='$id'");
        header("Location: categories.php");
        exit;
    }
}

// --- AMBIL DATA KATEGORI ---
$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");

// Ambil Foto Profil
$query_admin_profile = mysqli_query($conn, "SELECT profile_image FROM users WHERE id = '$admin_id'");
$data_admin = mysqli_fetch_assoc($query_admin_profile);
$profile_pic = !empty($data_admin['profile_image']) ? "../assets/uploads/profiles/" . $data_admin['profile_image'] : "../assets/images/default_profile.png";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Kategori Buku - Libraria Admin</title>

<script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

<style type="text/tailwindcss">
    :root {
        --deep-forest: #3E4B1C;
        --chocolate-brown: #663F05;
        --warm-tan: #B18143;
        --light-sage: #DCE3AC;
        --cream-bg: #FEF9E6;
        --sidebar-active: var(--deep-forest);
        --text-dark: #2D2418;
        --text-muted: #6B6155;
        --border-color: #E6E1D3;
    }
    body { 
        font-family: 'Quicksand', sans-serif;
        background-color: var(--cream-bg);
        color: var(--text-dark);
    }
    .title-font { font-weight: 700; }
    
    .card-shadow { box-shadow: 0 10px 40px -10px rgba(62, 75, 28, 0.08); }
    .sidebar-active { background-color: var(--sidebar-active); color: white; box-shadow: 0 4px 12px rgba(62, 75, 28, 0.3); }
    
    #sidebar, #main-content, #sidebar-logo, .sidebar-text-wrapper, .menu-text { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    #sidebar-header { justify-content: flex-start; padding-left: 1.5rem; padding-right: 1.5rem; }
    #sidebar-logo { height: 5rem; width: auto; }
    .sidebar-text-wrapper { opacity: 1; width: auto; margin-left: 0.75rem; overflow: hidden; white-space: nowrap; }
    .menu-text { opacity: 1; width: auto; display: inline-block; }

    .sidebar-collapsed #sidebar-header { justify-content: center !important; padding-left: 0 !important; padding-right: 0 !important; }
    .sidebar-collapsed #sidebar-logo { height: 3.5rem !important; width: auto; margin: 0 auto; }
    .sidebar-collapsed .sidebar-text-wrapper { opacity: 0 !important; width: 0 !important; margin-left: 0 !important; pointer-events: none; }
    .sidebar-collapsed .menu-text { opacity: 0 !important; width: 0 !important; display: none; }
    .sidebar-collapsed nav a { justify-content: center; padding-left: 0; padding-right: 0; }

    .modal { transition: opacity 0.25s ease; }
    body.modal-active { overflow-x: hidden; overflow-y: hidden !important; }
</style>
<script src="../assets/js/theme-manager.js"></script>
</head>
<body class="overflow-x-hidden">
    <?= isset($alert) ? $alert : '' ?>

<div class="flex min-h-screen">
    
    <aside id="sidebar" class="w-64 bg-white border-r border-[var(--border-color)] flex flex-col fixed h-full z-30 overflow-hidden shadow-lg lg:shadow-none">
        <div id="sidebar-header" class="h-28 flex items-center border-b border-[var(--border-color)] shrink-0">
            <img id="sidebar-logo" src="../assets/images/logo.png" alt="Libraria Logo" class="object-contain flex-shrink-0">
            <div class="sidebar-text-wrapper flex flex-col justify-center">
                <h1 class="text-2xl font-bold text-[var(--deep-forest)] tracking-tight title-font leading-none">LIBRARIA</h1>
                <p class="text-xs font-bold tracking-[0.2em] text-[var(--warm-tan)] mt-1 uppercase">Admin Panel</p>
            </div>
        </div>
        <nav class="flex-1 px-3 space-y-2 mt-6 overflow-y-auto overflow-x-hidden">
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">dashboard</span>
                <span class="font-medium menu-text whitespace-nowrap">Dashboard</span>
            </a>
            <a href="manage_users.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">group</span>
                <span class="font-medium menu-text whitespace-nowrap">Kelola User</span>
            </a>
            <a href="categories.php" class="flex items-center gap-3 px-4 py-3 sidebar-active rounded-2xl transition-all group shadow-md shadow-green-900/10">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">category</span>
                <span class="font-semibold menu-text whitespace-nowrap">Kategori Buku</span>
            </a>
            <a href="help.php" class="flex items-center gap-3 px-4 py-3 text-stone-500 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] rounded-2xl transition-all group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">help</span>
                <span class="font-medium menu-text whitespace-nowrap">Bantuan</span>
            </a>
        </nav>
        <div class="p-3 border-t border-[var(--border-color)]">
            <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-3 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-2xl transition-colors group">
                <span class="material-symbols-outlined flex-shrink-0 text-2xl">logout</span>
                <span class="font-medium menu-text whitespace-nowrap">Sign Out</span>
            </a>
        </div>
    </aside>

    <main id="main-content" class="flex-1 ml-64 p-4 lg:p-8 transition-all duration-300">
        
        <header class="flex justify-between items-center mb-10 bg-white/50 backdrop-blur-sm p-4 rounded-3xl border border-[var(--border-color)] sticky top-4 z-20 shadow-sm" data-aos="fade-down">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl hover:bg-[var(--light-sage)] text-[var(--deep-forest)] transition-colors focus:outline-none">
                    <span class="material-symbols-outlined">menu_open</span>
                </button>
                <div><h2 class="text-xl lg:text-2xl title-font text-[var(--text-dark)] hidden md:block">Kelola Kategori</h2></div>
            </div>

            <div class="flex items-center gap-4 relative">

<button onclick="toggleDarkMode()" class="w-10 h-10 rounded-full bg-white/10 border border-[var(--border-color)] text-[var(--text-muted)] hover:text-[var(--deep-forest)] hover:bg-[var(--light-sage)]/30 transition-all flex items-center justify-center group mr-2" title="Toggle Dark Mode">
    <span class="material-symbols-outlined group-hover:rotate-180 transition-transform duration-500" id="dark-mode-icon">dark_mode</span>
</button>

                <button onclick="toggleProfileDropdown()" class="flex items-center gap-3 bg-white p-1.5 pr-4 rounded-full border border-[var(--border-color)] card-shadow hover:shadow-md transition-all focus:outline-none">
                    <img src="<?= $profile_pic ?>" alt="Profile" class="w-9 h-9 rounded-full object-cover border-2 border-[var(--cream-bg)]">
                    <div class="text-left hidden sm:block">
                        <p class="text-xs font-bold leading-none title-font"><?= $admin_name ?></p>
                        <p class="text-[10px] text-[var(--warm-tan)] leading-none mt-1 font-bold uppercase">Super Admin</p>
                    </div>
                    <span class="material-symbols-outlined text-[18px] text-[var(--text-muted)]">expand_more</span>
                </button>
                <div id="profileDropdown" class="absolute right-0 top-14 w-56 bg-white rounded-2xl shadow-xl border border-[var(--border-color)] py-2 hidden transform origin-top-right transition-all z-50">
                    <a href="profileadmin.php" class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700 hover:bg-[var(--light-sage)]/30 hover:text-[var(--deep-forest)] transition-colors"><span class="material-symbols-outlined text-[20px]">person</span> My Profile</a>
                    <div class="border-t border-gray-100 my-1"></div>
                    <a href="../auth/logout.php" class="flex items-center gap-2 px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors"><span class="material-symbols-outlined text-[20px]">logout</span> Log Out</a>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <div class="lg:col-span-1" data-aos="fade-right">
                <div class="bg-white rounded-[2.5rem] p-8 border border-[var(--border-color)] card-shadow sticky top-32">
                    <h3 class="text-xl font-bold text-[var(--deep-forest)] title-font mb-6">Tambah Kategori</h3>
                    <form action="" method="POST">
                        <div class="mb-4">
                            <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-2 ml-1">Nama Kategori</label>
                            <input type="text" name="name" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all text-sm" placeholder="Contoh: Novel, Komik...">
                        </div>
                        <button type="submit" name="add_category" class="w-full py-3 bg-[var(--deep-forest)] text-white font-bold rounded-xl hover:bg-[var(--chocolate-brown)] transition-all shadow-lg text-sm flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-lg">add_circle</span> Simpan
                        </button>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-2" data-aos="fade-left">
                <div class="bg-white rounded-[2.5rem] p-8 border border-[var(--border-color)] card-shadow min-h-[500px]">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-[var(--text-dark)] title-font">Daftar Kategori</h3>
                        <span class="bg-[var(--light-sage)]/30 text-[var(--deep-forest)] px-3 py-1 rounded-lg text-xs font-bold"><?= mysqli_num_rows($categories) ?> Total</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php while($cat = mysqli_fetch_assoc($categories)): ?>
                        <div class="flex items-center justify-between p-4 bg-stone-50 rounded-2xl border border-stone-100 hover:border-[var(--warm-tan)] hover:shadow-md transition-all group">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-[var(--cream-bg)] flex items-center justify-center text-[var(--chocolate-brown)] font-bold">
                                    <?= strtoupper(substr($cat['name'], 0, 1)) ?>
                                </div>
                                <span class="font-bold text-[var(--text-dark)]"><?= $cat['name'] ?></span>
                            </div>
                            <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button onclick="openEditModal('<?= $cat['id'] ?>', '<?= htmlspecialchars($cat['name']) ?>')" class="p-2 text-stone-400 hover:text-[var(--deep-forest)] hover:bg-white rounded-lg transition-colors">
                                    <span class="material-symbols-outlined text-lg">edit</span>
                                </button>
                                <a href="?delete=<?= $cat['id'] ?>" onclick="return confirm('Hapus kategori ini?')" class="p-2 text-stone-400 hover:text-red-500 hover:bg-white rounded-lg transition-colors">
                                    <span class="material-symbols-outlined text-lg">delete</span>
                                </a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

        </div>

    </main>
</div>

<div id="editCategoryModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 transition-opacity duration-300">
    <div class="modal-overlay absolute w-full h-full bg-stone-900/60 backdrop-blur-sm" onclick="toggleModal('editCategoryModal')"></div>
    <div class="modal-container bg-white w-11/12 md:max-w-sm mx-auto rounded-[2rem] shadow-2xl z-50 overflow-y-auto p-8 transform transition-all scale-95 duration-300">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-[var(--deep-forest)] title-font">Edit Kategori</h3>
            <button onclick="toggleModal('editCategoryModal')" class="w-8 h-8 rounded-full bg-stone-100 flex items-center justify-center text-stone-400 hover:bg-red-100 hover:text-red-500 transition-colors"><span class="material-symbols-outlined text-lg">close</span></button>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="id" id="edit_id">
            <div class="mb-4">
                <label class="block text-xs font-bold uppercase text-[var(--text-muted)] mb-2 ml-1">Nama Kategori</label>
                <input type="text" name="name" id="edit_name" required class="w-full px-4 py-3 rounded-xl bg-[var(--cream-bg)] border-transparent focus:bg-white focus:border-[var(--warm-tan)] focus:ring-0 transition-all text-sm">
            </div>
            <button type="submit" name="edit_category" class="w-full py-3 bg-[var(--warm-tan)] text-white font-bold rounded-xl hover:bg-[var(--chocolate-brown)] transition-all shadow-lg text-sm">Update</button>
        </form>
    </div>
</div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({ once: true, duration: 800, offset: 50 });

    let isSidebarOpen = true;
    const sidebar = document.getElementById('sidebar');
    const mainDiv = document.getElementById('main-content'); 

    function toggleSidebar() {
        if (isSidebarOpen) {
            sidebar.classList.remove('w-64');
            sidebar.classList.add('w-20', 'sidebar-collapsed');
            mainDiv.classList.remove('ml-64');
            mainDiv.classList.add('ml-20');
        } else {
            sidebar.classList.remove('w-20', 'sidebar-collapsed');
            sidebar.classList.add('w-64');
            mainDiv.classList.remove('ml-20');
            mainDiv.classList.add('ml-64');
        }
        isSidebarOpen = !isSidebarOpen;
    }

    function toggleProfileDropdown() {
        const dropdown = document.getElementById('profileDropdown');
        if (dropdown.classList.contains('hidden')) {
            dropdown.classList.remove('hidden');
            setTimeout(() => {
                dropdown.classList.remove('opacity-0', 'scale-95');
                dropdown.classList.add('opacity-100', 'scale-100');
            }, 10);
        } else {
            dropdown.classList.remove('opacity-100', 'scale-100');
            dropdown.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                dropdown.classList.add('hidden');
            }, 150);
        }
    }

    window.addEventListener('click', function(e) {
        const button = document.querySelector('button[onclick="toggleProfileDropdown()"]');
        const dropdown = document.getElementById('profileDropdown');
        if (button && dropdown && !button.contains(e.target) && !dropdown.contains(e.target)) {
            if (!dropdown.classList.contains('hidden')) {
                dropdown.classList.remove('opacity-100', 'scale-100');
                dropdown.classList.add('opacity-0', 'scale-95');
                setTimeout(() => { dropdown.classList.add('hidden'); }, 150);
            }
        }
    });

    function toggleModal(modalID) {
        const modal = document.getElementById(modalID);
        const container = modal.querySelector('.modal-container');
        modal.classList.toggle('opacity-0');
        modal.classList.toggle('pointer-events-none');
        document.body.classList.toggle('modal-active');
        if (!modal.classList.contains('opacity-0')) {
            setTimeout(() => { container.classList.remove('scale-95'); container.classList.add('scale-100'); }, 10);
        } else {
            container.classList.remove('scale-100'); container.classList.add('scale-95');
        }
    }

    function openEditModal(id, name) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        toggleModal('editCategoryModal');
    }
</script>

</body>
</html>
